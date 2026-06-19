<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;

final class QueryBuilder extends Obj
{
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

        if ($driver !== '') {
            $this->driver = $driver;
        }
        if ($operation !== '') {
            $this->operation = $operation;
        }
        if ($target !== '') {
            $this->target = $target;
        }
    }

    public function clause(string $name, mixed $value): self
    {
        $clauses = $this->clauses();
        $clauses[$name] = Dev::apply(null, $value);
        $this->clauses = $clauses;

        return $this;
    }

    public function parameter(string $name, mixed $value): self
    {
        $parameters = $this->parameters();
        $parameters[$name] = Dev::apply(null, $value);
        $this->parameters = $parameters;

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
