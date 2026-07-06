<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Structures;

use BlueFission\Arr;
use BlueFission\Num;

final class Deq extends ArrayStructure
{
    public function __construct(array $items = [])
    {
        parent::__construct();

        Arr::make($items)->each(function (mixed $item): void {
            $this->pushBack($item);
        });
    }

    public function pushBack(mixed $value): self
    {
        $this->mutateItems($this->appendArrayValue($this->items(), $value));

        return $this;
    }

    public function pushFront(mixed $value): self
    {
        $items = Arr::make($this->items());
        $items->unshift($this->applyValue($value));
        $this->mutateItems($items->values()->val());

        return $this;
    }

    public function enqueue(mixed $value): self
    {
        return $this->pushBack($value);
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

        $items = Arr::make($this->items());
        $value = $items->pop();
        $this->mutateItems($items->values()->val());

        return $value;
    }

    public function popFront(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        $items = Arr::make($this->items());
        $value = $items->shift();
        $this->mutateItems($items->values()->val());

        return $value;
    }

    public function peekBack(mixed $default = null): mixed
    {
        if ($this->isEmpty()) {
            return $default;
        }

        return Arr::getPath($this->items(), Num::make($this->count())->minus(1)->int(), $default);
    }

    public function peekFront(mixed $default = null): mixed
    {
        return Arr::getPath($this->items(), 0, $default);
    }
}
