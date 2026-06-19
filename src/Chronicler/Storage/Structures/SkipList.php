<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Structures;

use BlueFission\DataTypes;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;

final class SkipList extends Obj
{
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

        $this->max_level = max(1, $maxLevel);
        $this->probability = min(0.99, max(0.01, $probability));
        $this->head = new SkipListNode('', null, (int)$this->max_level);
    }

    public function insert(string $key, mixed $value): self
    {
        $update = array_fill(0, (int)$this->max_level + 1, $this->head);
        $current = $this->head;

        for ($i = $this->level; $i >= 0; $i--) {
            while ($current->forward[$i] !== null && $current->forward[$i]->key < $key) {
                $current = $current->forward[$i];
            }
            $update[$i] = $current;
        }

        $current = $current->forward[0];
        if ($current !== null && $current->key === $key) {
            $current->value = Dev::apply(null, $value);

            return $this;
        }

        $newLevel = $this->randomLevel();
        if ($newLevel > $this->level) {
            for ($i = $this->level + 1; $i <= $newLevel; $i++) {
                $update[$i] = $this->head;
            }
            $this->level = $newLevel;
        }

        $node = new SkipListNode($key, Dev::apply(null, $value), $newLevel);
        for ($i = 0; $i <= $newLevel; $i++) {
            $node->forward[$i] = $update[$i]->forward[$i] ?? null;
            $update[$i]->forward[$i] = $node;
        }

        $this->count = (int)$this->count + 1;

        return $this;
    }

    public function search(string $key): mixed
    {
        $current = $this->head;
        for ($i = $this->level; $i >= 0; $i--) {
            while ($current->forward[$i] !== null && $current->forward[$i]->key < $key) {
                $current = $current->forward[$i];
            }
        }

        $current = $current->forward[0];

        return $current !== null && $current->key === $key ? $current->value : null;
    }

    public function has(string $key): bool
    {
        return $this->search($key) !== null;
    }

    public function delete(string $key): bool
    {
        $update = array_fill(0, (int)$this->max_level + 1, $this->head);
        $current = $this->head;

        for ($i = $this->level; $i >= 0; $i--) {
            while ($current->forward[$i] !== null && $current->forward[$i]->key < $key) {
                $current = $current->forward[$i];
            }
            $update[$i] = $current;
        }

        $current = $current->forward[0];
        if ($current === null || $current->key !== $key) {
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

        $this->count = max(0, (int)$this->count - 1);

        return true;
    }

    public function keys(): array
    {
        $keys = [];
        $current = $this->head->forward[0];
        while ($current !== null) {
            $keys[] = $current->key;
            $current = $current->forward[0];
        }

        return $keys;
    }

    public function toArray(): array
    {
        $values = [];
        $current = $this->head->forward[0];
        while ($current !== null) {
            $values[$current->key] = $current->value;
            $current = $current->forward[0];
        }

        return $values;
    }

    private function randomLevel(): int
    {
        $level = 0;
        while (mt_rand() / mt_getrandmax() < (float)$this->probability && $level < (int)$this->max_level) {
            $level++;
        }

        return $level;
    }
}
