<?php

declare(strict_types=1);

namespace BlueFission;

use ArrayIterator;
use BlueFission\Behavioral\Behaviors\Event;
use IteratorAggregate;
use Traversable;

/**
 * Value object wrapper around Ds\Vector with an array fallback.
 *
 * @implements IteratorAggregate<int, mixed>
 */
class Vec extends Val implements IVal, IteratorAggregate
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
            if (!$this->isDsVector($this->_data)) {
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
        return $this->isDsVector($this->_data) || Arr::is($this->_data);
    }

    public function add($value): IVal
    {
        return $this->push($value);
    }

    public function push(mixed $value): IVal
    {
        if ($this->isDsVector($this->_data)) {
            $this->_data->push($value);
        } else {
            $data = Arr::make($this->_data);
            $data->push($value);
            $this->_data = $data->val();
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function append(mixed $value): IVal
    {
        return $this->push($value);
    }

    public function insert(int $index, mixed $value): IVal
    {
        $index = $this->boundedIndex($index);

        if ($this->isDsVector($this->_data)) {
            $this->_data->insert($index, $value);
        } else {
            $items = Arr::make($this->_data)
                ->splice([$value], $index, 0)
                ->values()
                ->val();
            $this->_data = $items;
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function remove(int $index): IVal
    {
        if (!$this->hasIndex($index)) {
            return $this;
        }

        if ($this->isDsVector($this->_data)) {
            $this->_data->remove($index);
        } else {
            $items = Arr::make($this->_data);
            $items->delete($index);
            $this->_data = $items->values()->val();
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function get(int $index, mixed $default = null): mixed
    {
        if ($this->isDsVector($this->_data)) {
            return $this->hasIndex($index) ? $this->_data->get($index) : $default;
        }

        return Arr::getPath($this->_data, $index, $default);
    }

    public function set(int $index, mixed $value): IVal
    {
        if ($this->isDsVector($this->_data)) {
            $this->_data->set($index, $value);
        } else {
            $this->_data[$index] = $value;
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function clear(): IVal
    {
        if ($this->isDsVector($this->_data)) {
            $this->_data->clear();
        } else {
            $this->_data = [];
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function count(): int
    {
        if ($this->isDsVector($this->_data)) {
            return $this->_data->count();
        }

        return Arr::size($this->_data);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function hasIndex(int $index): bool
    {
        if ($this->isDsVector($this->_data)) {
            return $index >= 0 && $index < $this->_data->count();
        }

        return Arr::hasKey($this->_data, $index);
    }

    public function contains(mixed $value, bool $strict = true): bool
    {
        if ($this->isDsVector($this->_data)) {
            return Arr::has($this->_data->toArray(), $value, $strict);
        }

        return Arr::has($this->_data, $value, $strict);
    }

    public function first(mixed $default = null): mixed
    {
        return $this->get(0, $default);
    }

    public function last(mixed $default = null): mixed
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
        if ($this->isDsVector($this->_data)) {
            return $this->_data->toArray();
        }

        return Arr::toArray($this->_data);
    }

    public function val($value = null): mixed
    {
        if (func_num_args() === 0) {
            return $this->toArray();
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
        return class_exists('\Ds\Vector');
    }

    protected function isDsVector(mixed $value): bool
    {
        return $this->supportsDs() && $value instanceof \Ds\Vector;
    }

    protected function buildStorage(mixed $seed): mixed
    {
        $items = Arr::toArray($seed);

        if ($this->supportsDs()) {
            return new \Ds\Vector($items);
        }

        return Arr::make($items)->values()->val();
    }

    private function boundedIndex(int $index): int
    {
        if ($index < 0) {
            return 0;
        }

        if ($index > $this->count()) {
            return $this->count();
        }

        return $index;
    }
}
