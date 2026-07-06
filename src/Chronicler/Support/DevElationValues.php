<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Support;

use BlueFission\Arr;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;
use BlueFission\Val;

trait DevElationValues
{
    protected function applyValue(mixed $value, ?string $filter = null): mixed
    {
        return Dev::apply($filter, $value);
    }

    protected function valueArray(mixed $value, ?string $filter = null): array
    {
        $value = $this->applyValue($value, $filter);

        if ($value instanceof Obj) {
            return $value->toArray();
        }

        return Arr::toArray($value);
    }

    protected function pathValue(array|object $source, string|array $path, mixed $default = null): mixed
    {
        return Arr::getPath(Arr::toArray($source), $path, $default);
    }

    protected function assignArrayValue(array $array, string|int $key, mixed $value, ?string $filter = null): array
    {
        $values = Arr::make($array);
        $values->set($key, $this->applyValue($value, $filter));

        return $values->val();
    }

    protected function appendArrayValue(array $array, mixed $value, ?string $filter = null): array
    {
        return Arr::make($array)->push($this->applyValue($value, $filter))->val();
    }

    protected function hasValue(mixed $value): bool
    {
        return Val::isNotNull($value);
    }
}
