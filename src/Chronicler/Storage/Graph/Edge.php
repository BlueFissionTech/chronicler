<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Graph;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;

final class Edge extends Obj
{
    protected $_data = [
        'id' => '',
        'type' => '',
        'from' => '',
        'to' => '',
        'directed' => true,
        'properties' => [],
    ];

    protected $_types = [
        'id' => DataTypes::STRING,
        'type' => DataTypes::STRING,
        'from' => DataTypes::STRING,
        'to' => DataTypes::STRING,
        'directed' => DataTypes::BOOLEAN,
        'properties' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function __construct(string $id, string $type, string|Node $from, string|Node $to, array $properties = [], bool $directed = true)
    {
        parent::__construct();

        $this->id = $id;
        $this->type = $type;
        $this->from = $from instanceof Node ? $from->id : $from;
        $this->to = $to instanceof Node ? $to->id : $to;
        $this->directed = $directed;
        $this->properties($properties);
    }

    public function properties(?array $properties = null): array
    {
        if ($properties !== null) {
            $this->properties = Arr::toArray(Dev::apply(null, $properties));
        }

        return Arr::toArray($this->properties);
    }

    public function connects(string|Node $from, string|Node $to): bool
    {
        $from = $from instanceof Node ? $from->id : $from;
        $to = $to instanceof Node ? $to->id : $to;

        if ($this->directed) {
            return $this->from === $from && $this->to === $to;
        }

        return ($this->from === $from && $this->to === $to) || ($this->from === $to && $this->to === $from);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'from' => $this->from,
            'to' => $this->to,
            'directed' => $this->directed,
            'properties' => $this->properties(),
        ];
    }
}
