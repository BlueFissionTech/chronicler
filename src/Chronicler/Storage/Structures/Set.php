<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Structures;

use BlueFission\Arr;

final class Set extends ArrayStructure
{
    public function __construct(array $items = [])
    {
        parent::__construct();

        Arr::make($items)->each(function (mixed $item): void {
            $this->add($item);
        });
    }

    public function add(mixed $value): self
    {
        $value = $this->applyValue($value);
        if ($this->has($value)) {
            return $this;
        }

        $this->mutateItems($this->appendArrayValue($this->items(), $value));

        return $this;
    }

    public function has(mixed $value, bool $strict = true): bool
    {
        return Arr::has($this->items(), $value, $strict);
    }

    public function remove(mixed $value, bool $strict = true): bool
    {
        if (!$this->has($value, $strict)) {
            return false;
        }

        $items = Arr::make();
        Arr::make($this->items())->each(function (mixed $item) use ($items, $value, $strict): void {
            $same = $strict ? $item === $value : $item == $value;
            if (!$same) {
                $items->push($item);
            }
        });

        $this->mutateItems($items->values()->val());

        return true;
    }
}
