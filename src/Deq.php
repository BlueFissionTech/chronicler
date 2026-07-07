<?php

declare(strict_types=1);

namespace BlueFission;

use ArrayIterator;
use BlueFission\Behavioral\Behaviors\Event;
use IteratorAggregate;
use Traversable;

/**
 * Value object wrapper around Ds\Deque with an array fallback.
 *
 * @implements IteratorAggregate<int, mixed>
 */
class Deq extends Val implements IVal, IteratorAggregate
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
            if (!$this->isDsDeque($this->_data)) {
                $this->_data = $this->buildStorage($this->toArray());
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
        return $this->isDsDeque($this->_data) || Arr::is($this->_data);
    }

    public function pushFront(mixed $value): IVal
    {
        if ($this->isDsDeque($this->_data)) {
            $this->_data->unshift($value);
        } else {
            $data = Arr::make($this->_data);
            $data->unshift($value);
            $this->_data = $data->val();
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function pushBack(mixed $value): IVal
    {
        if ($this->isDsDeque($this->_data)) {
            $this->_data->push($value);
        } else {
            $data = Arr::make($this->_data);
            $data->push($value);
            $this->_data = $data->val();
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function enqueue(mixed $value): IVal
    {
        return $this->pushBack($value);
    }

    public function popFront(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        if ($this->isDsDeque($this->_data)) {
            $value = $this->_data->shift();
        } else {
            $data = Arr::make($this->_data);
            $value = $data->shift();
            $this->_data = $data->values()->val();
        }

        $this->trigger(Event::CHANGE);

        return $value;
    }

    public function dequeue(): mixed
    {
        return $this->popFront();
    }

    public function popBack(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        if ($this->isDsDeque($this->_data)) {
            $value = $this->_data->pop();
        } else {
            $data = Arr::make($this->_data);
            $value = $data->pop();
            $this->_data = $data->values()->val();
        }

        $this->trigger(Event::CHANGE);

        return $value;
    }

    public function get(int $index, mixed $default = null): mixed
    {
        if ($this->isDsDeque($this->_data)) {
            return $this->hasIndex($index) ? $this->_data->get($index) : $default;
        }

        return Arr::getPath($this->_data, $index, $default);
    }

    public function set(int $index, mixed $value): IVal
    {
        if ($this->isDsDeque($this->_data)) {
            $this->_data->set($index, $value);
        } else {
            $this->_data[$index] = $value;
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function clear(): IVal
    {
        if ($this->isDsDeque($this->_data)) {
            $this->_data->clear();
        } else {
            $this->_data = [];
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function count(): int
    {
        if ($this->isDsDeque($this->_data)) {
            return $this->_data->count();
        }

        return Arr::size($this->_data);
    }

    public function isEmpty(): bool
    {
        if ($this->isDsDeque($this->_data)) {
            return $this->_data->isEmpty();
        }

        return Arr::isEmpty($this->_data);
    }

    public function hasIndex(int $index): bool
    {
        if ($this->isDsDeque($this->_data)) {
            return $index >= 0 && $index < $this->_data->count();
        }

        return Arr::hasKey($this->_data, $index);
    }

    public function peekFront(mixed $default = null): mixed
    {
        return $this->get(0, $default);
    }

    public function peekBack(mixed $default = null): mixed
    {
        if ($this->isEmpty()) {
            return $default;
        }

        return $this->get($this->count() - 1, $default);
    }

    public function values(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        if ($this->isDsDeque($this->_data)) {
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
        return new ArrayIterator($this->toArray());
    }

    protected function supportsDs(): bool
    {
        return class_exists('\Ds\Deque');
    }

    protected function isDsDeque(mixed $value): bool
    {
        return $this->supportsDs() && $value instanceof \Ds\Deque;
    }

    protected function buildStorage(mixed $seed): mixed
    {
        $items = Arr::toArray($seed);

        if ($this->supportsDs()) {
            return new \Ds\Deque($items);
        }

        return Arr::make($items)->values()->val();
    }
}
