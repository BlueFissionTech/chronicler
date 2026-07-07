<?php

declare(strict_types=1);

namespace BlueFission;

use ArrayIterator;
use BlueFission\Behavioral\Behaviors\Event;
use IteratorAggregate;
use Traversable;

/**
 * Value object wrapper around Ds\Set with an array fallback.
 *
 * @implements IteratorAggregate<int, mixed>
 */
class Set extends Val implements IVal, IteratorAggregate
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
            if (!$this->isDsSet($this->_data)) {
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
        return $this->isDsSet($this->_data) || Arr::is($this->_data);
    }

    public function add(mixed $value): IVal
    {
        if ($this->isDsSet($this->_data)) {
            $this->_data->add($value);
        } elseif (!Arr::has($this->_data, $value, true)) {
            $data = Arr::make($this->_data);
            $data->push($value);
            $this->_data = $data->val();
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function remove(mixed $value): IVal
    {
        if ($this->isDsSet($this->_data)) {
            if ($this->_data->contains($value)) {
                $this->_data->remove($value);
                $this->trigger(Event::CHANGE);
            }

            return $this;
        }

        if (Arr::has($this->_data, $value, true)) {
            $items = Arr::make($this->_data);
            $items->remove($value);
            $this->_data = $items->values()->val();
            $this->trigger(Event::CHANGE);
        }

        return $this;
    }

    public function has(mixed $value): bool
    {
        if ($this->isDsSet($this->_data)) {
            return $this->_data->contains($value);
        }

        return Arr::has($this->_data, $value, true);
    }

    public function contains(mixed $value): bool
    {
        return $this->has($value);
    }

    public function clear(): IVal
    {
        if ($this->isDsSet($this->_data)) {
            $this->_data->clear();
        } else {
            $this->_data = [];
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function count(): int
    {
        if ($this->isDsSet($this->_data)) {
            return $this->_data->count();
        }

        return Arr::size($this->_data);
    }

    public function union(mixed $set): mixed
    {
        $set = $this->normalizeSetArgument($set);

        if ($this->isDsSet($this->_data) && $this->isDsSet($set)) {
            return $this->_data->union($set);
        }

        return Arr::append($this->toArray(), Arr::toArray($set));
    }

    public function intersect(mixed $set): mixed
    {
        $set = $this->normalizeSetArgument($set);

        if ($this->isDsSet($this->_data) && $this->isDsSet($set)) {
            return $this->_data->intersect($set);
        }

        return Arr::intersect($this->toArray(), Arr::toArray($set));
    }

    public function diff(mixed $set): mixed
    {
        $set = $this->normalizeSetArgument($set);

        if ($this->isDsSet($this->_data) && $this->isDsSet($set)) {
            return $this->_data->diff($set);
        }

        return Arr::diff($this->toArray(), Arr::toArray($set));
    }

    public function values(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        if ($this->isDsSet($this->_data)) {
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
        return class_exists('\Ds\Set');
    }

    protected function isDsSet(mixed $value): bool
    {
        return $this->supportsDs() && $value instanceof \Ds\Set;
    }

    protected function buildStorage(mixed $seed): mixed
    {
        $items = Arr::toArray($seed);

        if ($this->supportsDs()) {
            return new \Ds\Set($items);
        }

        return Arr::unique($items);
    }

    private function normalizeSetArgument(mixed $set): mixed
    {
        if ($set instanceof self) {
            return $set->_data;
        }

        if ($set instanceof IVal) {
            return $set->val();
        }

        return $set;
    }
}
