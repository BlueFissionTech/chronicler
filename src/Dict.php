<?php

declare(strict_types=1);

namespace BlueFission;

use BlueFission\Arr;
use BlueFission\Chronicler\Storage\Structures\ArrayStructure;

final class Dict extends ArrayStructure
{
    public function __construct(array $items = [])
    {
        parent::__construct();

        Arr::make($items)->each(function (mixed $value, string|int $key): void {
            $this->set($key, $value);
        });
    }

    public function set(string|int $key, mixed $value): self
    {
        $items = Arr::make($this->items());
        $items->set($key, $this->applyValue($value));
        $this->mutateItems($items->val());

        return $this;
    }

    public function put(string|int $key, mixed $value): self
    {
        return $this->set($key, $value);
    }

    public function get(string|int $key, mixed $default = null): mixed
    {
        return Arr::getPath($this->items(), $key, $default);
    }

    public function has(string|int $key): bool
    {
        return Arr::hasKey($this->items(), $key);
    }

    public function remove(string|int $key): bool
    {
        if (!$this->has($key)) {
            return false;
        }

        $items = Arr::make($this->items());
        $items->delete($key);
        $this->mutateItems($items->val());

        return true;
    }

    public function keys(): array
    {
        return Arr::make($this->items())->keys()->val();
    }
}
