<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Structures;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\IVal;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Val;

class PriorityQueue extends Obj
{
    use DevElationValues;

    protected $_data = [
        'entries' => [],
        'sequence' => 0,
    ];

    protected $_types = [
        'entries' => DataTypes::ARRAY,
        'sequence' => DataTypes::INTEGER,
    ];

    protected $_lockDataType = true;

    public function __construct(array $entries = [])
    {
        parent::__construct();

        Arr::make($entries)->each(function (mixed $entry): void {
            $this->seed($entry);
        });
    }

    public function insert(mixed $value, int $priority = 0): self
    {
        $this->setEntries($this->appendArrayValue($this->entries(), [
            'value' => $this->applyValue($value),
            'priority' => $priority,
            'sequence' => $this->nextSequence(),
        ]));
        $this->sortEntries();

        return $this;
    }

    public function enqueue(mixed $value, int $priority = 0): self
    {
        return $this->insert($value, $priority);
    }

    public function push(mixed $value, int $priority = 0): self
    {
        return $this->insert($value, $priority);
    }

    public function extract(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        $entries = Arr::make($this->entries());
        $entry = $entries->shift();
        $this->setEntries($entries->val());

        return Arr::getPath(Arr::toArray($entry), 'value');
    }

    public function pop(): mixed
    {
        return $this->extract();
    }

    public function peek(): mixed
    {
        return Arr::getPath($this->entries(), [0, 'value']);
    }

    public function isEmpty(): bool
    {
        return !Arr::isNotEmpty($this->entries());
    }

    public function count(): int
    {
        return Arr::count($this->entries());
    }

    public function clear(): self
    {
        $this->setEntries([]);
        $this->sequence = 0;

        return $this;
    }

    public function entries(): array
    {
        return $this->valueArray($this->entries);
    }

    public function values(): array
    {
        $values = Arr::make();
        Arr::make($this->entries())->each(function (mixed $entry) use ($values): void {
            $values->push(Arr::getPath(Arr::toArray($entry), 'value'));
        });

        return $values->val();
    }

    public function toArray(): array
    {
        return $this->entries();
    }

    private function seed(mixed $entry): void
    {
        $entry = Arr::toArray($entry);
        $value = Arr::hasKey($entry, 'value') ? Arr::getPath($entry, 'value') : Arr::getPath($entry, 0);
        $priority = Arr::hasKey($entry, 'priority') ? Arr::getPath($entry, 'priority') : Arr::getPath($entry, 1, 0);

        if (Val::isNull($value)) {
            return;
        }

        $this->insert($value, (int)$priority);
    }

    private function sortEntries(): void
    {
        $this->setEntries(Arr::make($this->entries())->sort(
            static function (array $left, array $right): int {
                $leftPriority = (int)Arr::getPath($left, 'priority', 0);
                $rightPriority = (int)Arr::getPath($right, 'priority', 0);

                if ($leftPriority !== $rightPriority) {
                    return $rightPriority <=> $leftPriority;
                }

                return (int)Arr::getPath($left, 'sequence', 0) <=> (int)Arr::getPath($right, 'sequence', 0);
            }
        )->val());
    }

    private function nextSequence(): int
    {
        $this->sequence = Num::make($this->sequence)->increment()->int();

        return (int)$this->sequence;
    }

    private function setEntries(array $entries): void
    {
        if (($this->_data['entries'] ?? null) instanceof IVal) {
            $this->_data['entries']->val($entries);
            return;
        }

        $this->_data['entries'] = $entries;
    }
}
