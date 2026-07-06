<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\Obj;
use BlueFission\Str;

final class QueryBuilder extends Obj
{
    use DevElationValues;

    protected $_data = [
        'driver' => '',
        'operation' => '',
        'target' => '',
        'clauses' => [],
        'parameters' => [],
    ];

    protected $_types = [
        'driver' => DataTypes::STRING,
        'operation' => DataTypes::STRING,
        'target' => DataTypes::STRING,
        'clauses' => DataTypes::ARRAY,
        'parameters' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function __construct(string $driver = '', string $operation = '', string $target = '')
    {
        parent::__construct();

        if (Str::isNotEmpty($driver)) {
            $this->driver = Str::trim($driver);
        }
        if (Str::isNotEmpty($operation)) {
            $this->operation = Str::trim($operation);
        }
        if (Str::isNotEmpty($target)) {
            $this->target = Str::trim($target);
        }
    }

    public function clause(string $name, mixed $value): self
    {
        $this->clauses = $this->assignArrayValue($this->clauses(), Str::trim($name), $value);

        return $this;
    }

    public function parameter(string $name, mixed $value): self
    {
        $this->parameters = $this->assignArrayValue($this->parameters(), Str::trim($name), $value);

        return $this;
    }

    public function clauses(): array
    {
        return Arr::toArray($this->clauses);
    }

    public function parameters(): array
    {
        return Arr::toArray($this->parameters);
    }

    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'operation' => $this->operation,
            'target' => $this->target,
            'clauses' => $this->clauses(),
            'parameters' => $this->parameters(),
        ];
    }
}
