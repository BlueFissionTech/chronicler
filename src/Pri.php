<?php

declare(strict_types=1);

namespace BlueFission;

use ArrayIterator;
use BlueFission\Behavioral\Behaviors\Event;
use IteratorAggregate;
use Traversable;

/**
 * Value object wrapper around Ds\PriorityQueue with an array fallback.
 *
 * @implements IteratorAggregate<int, mixed>
 */
class Pri extends Val implements IVal, IteratorAggregate
{
    protected $_type = DataTypes::GENERIC;

    protected $_forceType = false;

    public function __construct($value = null, bool $snapshot = true)
    {
        parent::__construct($this->buildStorage($value), $snapshot, false);
    }

    public static function make($value = null): IVal
    {
        return new static($value);
    }

    public function cast(): IVal
    {
        if ($this->supportsDs()) {
            if (!$this->isDsPriorityQueue($this->_data)) {
                $this->_data = $this->buildStorage($this->_data);
            }

            return $this;
        }

        if (!Arr::is($this->_data)) {
            $this->_data = Arr::toArray($this->_data);
        }

        return $this;
    }

    public function _is(): bool
    {
        return $this->isDsPriorityQueue($this->_data) || Arr::is($this->_data);
    }

    public function insert(mixed $value, int $priority): IVal
    {
        if ($this->isDsPriorityQueue($this->_data)) {
            $this->_data->push($value, $priority);
        } else {
            $data = Arr::make($this->_data);
            $data->push([$value, $priority]);
            $this->_data = $this->sortFallback($data->val());
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function push(mixed $value, int $priority = 0): IVal
    {
        return $this->insert($value, $priority);
    }

    public function enqueue(mixed $value, int $priority = 0): IVal
    {
        return $this->insert($value, $priority);
    }

    public function extract(): mixed
    {
        if ($this->isDsPriorityQueue($this->_data)) {
            if ($this->_data->isEmpty()) {
                return null;
            }

            $value = $this->_data->pop();
            $this->trigger(Event::CHANGE);

            return $value;
        }

        if (Arr::isEmpty($this->_data)) {
            return null;
        }

        $data = Arr::make($this->_data);
        $entry = $data->shift();
        $this->_data = $data->val();
        $this->trigger(Event::CHANGE);

        return Arr::is($entry) ? Arr::getPath($entry, 0) : null;
    }

    public function pop(): mixed
    {
        return $this->extract();
    }

    public function peek(): mixed
    {
        if ($this->isDsPriorityQueue($this->_data)) {
            return $this->_data->isEmpty() ? null : $this->_data->peek();
        }

        if (Arr::isEmpty($this->_data)) {
            return null;
        }

        $entry = Arr::getPath($this->_data, 0);

        return Arr::is($entry) ? Arr::getPath($entry, 0) : null;
    }

    public function isEmpty(): bool
    {
        if ($this->isDsPriorityQueue($this->_data)) {
            return $this->_data->isEmpty();
        }

        return Arr::isEmpty($this->_data);
    }

    public function clear(): IVal
    {
        if ($this->isDsPriorityQueue($this->_data)) {
            $this->_data->clear();
        } else {
            $this->_data = [];
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function count(): int
    {
        if ($this->isDsPriorityQueue($this->_data)) {
            return $this->_data->count();
        }

        return Arr::size($this->_data);
    }

    public function values(): array
    {
        if ($this->isDsPriorityQueue($this->_data)) {
            return $this->_data->toArray();
        }

        $values = Arr::make();
        Arr::make($this->_data)->each(function (mixed $entry) use ($values): void {
            if (Arr::is($entry)) {
                $values->push(Arr::getPath($entry, 0));
            }
        });

        return $values->val();
    }

    public function toArray(): array
    {
        if ($this->isDsPriorityQueue($this->_data)) {
            return $this->_data->toArray();
        }

        return Arr::toArray($this->_data);
    }

    public function val($value = null): mixed
    {
        if (func_num_args() === 0) {
            return parent::val();
        }

        $this->_data = $this->buildStorage($value);
        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->values());
    }

    protected function supportsDs(): bool
    {
        return class_exists('\Ds\PriorityQueue');
    }

    protected function isDsPriorityQueue(mixed $value): bool
    {
        return $this->supportsDs() && $value instanceof \Ds\PriorityQueue;
    }

    protected function buildStorage(mixed $seed): mixed
    {
        $items = Arr::toArray($seed);

        if ($this->supportsDs()) {
            $queue = new \Ds\PriorityQueue();
            Arr::make($items)->each(function (mixed $item) use ($queue): void {
                if (Arr::is($item) && Arr::size($item) >= 2) {
                    $queue->push(Arr::getPath($item, 0), (int)Arr::getPath($item, 1));
                }
            });

            return $queue;
        }

        $queue = [];
        Arr::make($items)->each(function (mixed $item) use (&$queue): void {
            if (Arr::is($item) && Arr::size($item) >= 2) {
                $queue[] = [Arr::getPath($item, 0), (int)Arr::getPath($item, 1)];
            }
        });

        return $this->sortFallback($queue);
    }

    private function sortFallback(array $entries): array
    {
        $decorated = [];
        foreach ($entries as $index => $entry) {
            if (!Arr::is($entry) || Arr::size($entry) < 2) {
                continue;
            }

            $decorated[] = [
                'value' => Arr::getPath($entry, 0),
                'priority' => (int)Arr::getPath($entry, 1),
                'index' => $index,
            ];
        }

        $collection = new \BlueFission\Collections\Collection($decorated);
        $decorated = $collection->sort(
            static function (array $left, array $right): int {
                if ($left['priority'] !== $right['priority']) {
                    return $right['priority'] <=> $left['priority'];
                }

                return $left['index'] <=> $right['index'];
            }
        )->contents();

        $sorted = [];
        foreach ($decorated as $entry) {
            $sorted[] = [$entry['value'], $entry['priority']];
        }

        return $sorted;
    }
}
