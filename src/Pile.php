<?php

declare(strict_types=1);

namespace BlueFission;

use ArrayIterator;
use BlueFission\Behavioral\Behaviors\Event;
use IteratorAggregate;
use Traversable;

/**
 * Value object wrapper around Ds\Stack with an array fallback.
 *
 * @implements IteratorAggregate<int, mixed>
 */
class Pile extends Val implements IVal, IteratorAggregate
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
            if (!$this->isDsStack($this->_data)) {
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
        return $this->isDsStack($this->_data) || Arr::is($this->_data);
    }

    public function push(mixed $value): IVal
    {
        if ($this->isDsStack($this->_data)) {
            $this->_data->push($value);
        } else {
            $data = Arr::make($this->_data);
            $data->push($value);
            $this->_data = $data->val();
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function pop(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        if ($this->isDsStack($this->_data)) {
            $value = $this->_data->pop();
        } else {
            $data = Arr::make($this->_data);
            $value = $data->pop();
            $this->_data = $data->val();
        }

        $this->trigger(Event::CHANGE);

        return $value;
    }

    public function peek(mixed $default = null): mixed
    {
        if ($this->isEmpty()) {
            return $default;
        }

        if ($this->isDsStack($this->_data)) {
            return $this->_data->peek();
        }

        return Arr::getPath($this->_data, $this->count() - 1, $default);
    }

    public function isEmpty(): bool
    {
        if ($this->isDsStack($this->_data)) {
            return $this->_data->isEmpty();
        }

        return Arr::isEmpty($this->_data);
    }

    public function clear(): IVal
    {
        if ($this->isDsStack($this->_data)) {
            $this->_data->clear();
        } else {
            $this->_data = [];
        }

        $this->trigger(Event::CHANGE);

        return $this;
    }

    public function count(): int
    {
        if ($this->isDsStack($this->_data)) {
            return $this->_data->count();
        }

        return Arr::size($this->_data);
    }

    public function values(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        if ($this->isDsStack($this->_data)) {
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
        return class_exists('\Ds\Stack');
    }

    protected function isDsStack(mixed $value): bool
    {
        return $this->supportsDs() && $value instanceof \Ds\Stack;
    }

    protected function buildStorage(mixed $seed): mixed
    {
        $items = Arr::toArray($seed);

        if ($this->supportsDs()) {
            $stack = new \Ds\Stack();
            Arr::make($items)->each(function (mixed $item) use ($stack): void {
                $stack->push($item);
            });

            return $stack;
        }

        return $items;
    }
}
