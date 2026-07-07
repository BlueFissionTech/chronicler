<?php

declare(strict_types=1);

namespace BlueFission;

use ArrayIterator;
use BlueFission\Behavioral\Behaviors\Event;
use IteratorAggregate;
use Traversable;

/**
 * Value object wrapper around Ds\Map with an array fallback.
 *
 * @implements IteratorAggregate<mixed, mixed>
 */
class Dict extends Val implements IVal, IteratorAggregate
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
            if (!$this->isDsMap($this->_data)) {
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
        return $this->isDsMap($this->_data) || Arr::is($this->_data);
    }

    public function put(mixed $key, mixed $value): IVal
    {
        if ($this->isDsMap($this->_data)) {
            $this->_data->put($key, $value);
        } else {
            $this->_data[$key] = $value;
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function set(string|int $key, mixed $value): IVal
    {
        return $this->put($key, $value);
    }

    public function get(mixed $key, mixed $default = null): mixed
    {
        if ($this->isDsMap($this->_data)) {
            return $this->_data->get($key, $default);
        }

        return Arr::getPath($this->_data, $key, $default);
    }

    public function remove(mixed $key): IVal
    {
        if ($this->isDsMap($this->_data)) {
            if ($this->_data->hasKey($key)) {
                $this->_data->remove($key);
                $this->trigger(Event::CHANGE);
            }

            return $this;
        }

        if (Arr::hasKey($this->_data, $key)) {
            $items = Arr::make($this->_data);
            $items->delete($key);
            $this->_data = $items->val();
            $this->trigger(Event::CHANGE);
        }

        return $this;
    }

    public function clear(): IVal
    {
        if ($this->isDsMap($this->_data)) {
            $this->_data->clear();
        } else {
            $this->_data = [];
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function hasKey(mixed $key): bool
    {
        if ($this->isDsMap($this->_data)) {
            return $this->_data->hasKey($key);
        }

        return Arr::hasKey($this->_data, $key);
    }

    public function has(string|int $key): bool
    {
        return $this->hasKey($key);
    }

    public function hasValue(mixed $value): bool
    {
        if ($this->isDsMap($this->_data)) {
            return $this->_data->hasValue($value);
        }

        return Arr::has($this->_data, $value, true);
    }

    public function count(): int
    {
        if ($this->isDsMap($this->_data)) {
            return $this->_data->count();
        }

        return Arr::size($this->_data);
    }

    public function keys(): array
    {
        if ($this->isDsMap($this->_data)) {
            return $this->_data->keys()->toArray();
        }

        return Arr::make($this->_data)->keys()->val();
    }

    public function values(): array
    {
        if ($this->isDsMap($this->_data)) {
            return $this->_data->values()->toArray();
        }

        return Arr::make($this->_data)->values()->val();
    }

    public function toArray(): array
    {
        if ($this->isDsMap($this->_data)) {
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
        return class_exists('\Ds\Map');
    }

    protected function isDsMap(mixed $value): bool
    {
        return $this->supportsDs() && $value instanceof \Ds\Map;
    }

    protected function buildStorage(mixed $seed): mixed
    {
        $items = Arr::toArray($seed);

        if ($this->supportsDs()) {
            return new \Ds\Map($items);
        }

        return $items;
    }
}
