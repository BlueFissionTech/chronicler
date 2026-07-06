<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Structures;

use BlueFission\Arr;
use BlueFission\Num;

final class Vector extends ArrayStructure
{
    public function __construct(array $items = [])
    {
        parent::__construct();

        Arr::make($items)->each(function (mixed $item): void {
            $this->push($item);
        });
    }

    public function push(mixed $value): self
    {
        $this->mutateItems($this->appendArrayValue($this->items(), $value));

        return $this;
    }

    public function append(mixed $value): self
    {
        return $this->push($value);
    }

    public function insert(int $index, mixed $value): self
    {
        $items = Arr::make($this->items())
            ->splice([$this->applyValue($value)], $this->boundedIndex($index), 0)
            ->values()
            ->val();

        $this->mutateItems($items);

        return $this;
    }

    public function set(int $index, mixed $value): self
    {
        $items = Arr::make($this->items());
        $items->set($index, $this->applyValue($value));
        $this->mutateItems($items->values()->val());

        return $this;
    }

    public function get(int $index, mixed $default = null): mixed
    {
        return Arr::getPath($this->items(), $index, $default);
    }

    public function remove(int $index): mixed
    {
        if (!$this->hasIndex($index)) {
            return null;
        }

        $value = $this->get($index);
        $items = Arr::make($this->items());
        $items->delete($index);
        $this->mutateItems($items->values()->val());

        return $value;
    }

    public function hasIndex(int $index): bool
    {
        return Arr::hasKey($this->items(), $index);
    }

    public function contains(mixed $value, bool $strict = true): bool
    {
        return Arr::has($this->items(), $value, $strict);
    }

    public function first(mixed $default = null): mixed
    {
        return Arr::getPath($this->items(), 0, $default);
    }

    public function last(mixed $default = null): mixed
    {
        if ($this->isEmpty()) {
            return $default;
        }

        return Arr::getPath($this->items(), Num::make($this->count())->minus(1)->int(), $default);
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
