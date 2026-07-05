<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Structures;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

final class SkipList extends Obj
{
    use DevElationValues;

    protected $_data = [
        'max_level' => 16,
        'probability' => 0.5,
        'count' => 0,
    ];

    protected $_types = [
        'max_level' => DataTypes::INTEGER,
        'probability' => DataTypes::FLOAT,
        'count' => DataTypes::INTEGER,
    ];

    protected $_lockDataType = true;

    private SkipListNode $head;
    private int $level = 0;

    public function __construct(int $maxLevel = 16, float $probability = 0.5)
    {
        parent::__construct();

        $this->max_level = (int)Num::max(1, $maxLevel);
        $this->probability = Num::min(0.99, Num::max(0.01, $probability));
        $this->head = new SkipListNode('', null, (int)$this->max_level);
    }

    public function insert(string $key, mixed $value): self
    {
        $key = Str::trim($key);
        $update = $this->nodeWindow($this->head);
        $current = $this->head;

        for ($i = $this->level; $i >= 0; $i--) {
            while ($current->forward[$i] !== null && $current->forward[$i]->key < $key) {
                $current = $current->forward[$i];
            }
            $update[$i] = $current;
        }

        $current = $current->forward[0];
        if (Val::isNotNull($current) && Str::make($current->key)->match($key)) {
            $current->value = $this->applyValue($value);

            return $this;
        }

        $newLevel = $this->randomLevel();
        if ($newLevel > $this->level) {
            for ($i = $this->level + 1; $i <= $newLevel; $i++) {
                $update[$i] = $this->head;
            }
            $this->level = $newLevel;
        }

        $node = new SkipListNode($key, $this->applyValue($value), $newLevel);
        for ($i = 0; $i <= $newLevel; $i++) {
            $node->forward[$i] = $update[$i]->forward[$i] ?? null;
            $update[$i]->forward[$i] = $node;
        }

        $this->count = Num::make($this->count)->increment()->int();

        return $this;
    }

    public function search(string $key): mixed
    {
        $key = Str::trim($key);
        $current = $this->head;
        for ($i = $this->level; $i >= 0; $i--) {
            while ($current->forward[$i] !== null && $current->forward[$i]->key < $key) {
                $current = $current->forward[$i];
            }
        }

        $current = $current->forward[0];

        return Val::isNotNull($current) && Str::make($current->key)->match($key) ? $current->value : null;
    }

    public function has(string $key): bool
    {
        return $this->search($key) !== null;
    }

    public function delete(string $key): bool
    {
        $key = Str::trim($key);
        $update = $this->nodeWindow($this->head);
        $current = $this->head;

        for ($i = $this->level; $i >= 0; $i--) {
            while ($current->forward[$i] !== null && $current->forward[$i]->key < $key) {
                $current = $current->forward[$i];
            }
            $update[$i] = $current;
        }

        $current = $current->forward[0];
        if (Val::isNull($current) || !Str::make($current->key)->match($key)) {
            return false;
        }

        for ($i = 0; $i <= $this->level; $i++) {
            if (($update[$i]->forward[$i] ?? null) !== $current) {
                break;
            }
            $update[$i]->forward[$i] = $current->forward[$i] ?? null;
        }

        while ($this->level > 0 && $this->head->forward[$this->level] === null) {
            $this->level--;
        }

        $this->count = (int)Num::max(0, (int)$this->count - 1);

        return true;
    }

    public function keys(): array
    {
        $keys = Arr::make();
        $current = $this->head->forward[0];
        while ($current !== null) {
            $keys->push($current->key);
            $current = $current->forward[0];
        }

        return $keys->val();
    }

    public function toArray(): array
    {
        $values = Arr::make();
        $current = $this->head->forward[0];
        while ($current !== null) {
            $values->set($current->key, $current->value);
            $current = $current->forward[0];
        }

        return $values->val();
    }

    private function randomLevel(): int
    {
        $level = 0;
        while (Num::rand(PHP_INT_MAX, 0) / PHP_INT_MAX < (float)$this->probability && $level < (int)$this->max_level) {
            $level++;
        }

        return $level;
    }

    /** @return array<int, SkipListNode> */
    private function nodeWindow(SkipListNode $node): array
    {
        $nodes = Arr::make();
        for ($level = 0; $level <= (int)$this->max_level; $level++) {
            $nodes->set($level, $node);
        }

        return $nodes->val();
    }
}
