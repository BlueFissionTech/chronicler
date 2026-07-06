<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Structures;

use BlueFission\Arr;
use BlueFission\Num;

final class Pile extends ArrayStructure
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

    public function pop(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        $items = Arr::make($this->items());
        $value = $items->pop();
        $this->mutateItems($items->values()->val());

        return $value;
    }

    public function peek(mixed $default = null): mixed
    {
        if ($this->isEmpty()) {
            return $default;
        }

        return Arr::getPath($this->items(), Num::make($this->count())->minus(1)->int(), $default);
    }
}
