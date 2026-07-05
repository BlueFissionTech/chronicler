<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Graph;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

final class Edge extends Obj
{
    use DevElationValues;

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

        $this->id = Str::trim($id);
        $this->type = Str::trim($type);
        $this->from = $from instanceof Node ? $from->id : Str::trim($from);
        $this->to = $to instanceof Node ? $to->id : Str::trim($to);
        $this->directed = $directed;
        $this->properties($properties);
    }

    public function properties(?array $properties = null): array
    {
        if (Val::isNotNull($properties)) {
            $this->properties = $this->valueArray($properties);
        }

        return $this->valueArray($this->properties);
    }

    public function connects(string|Node $from, string|Node $to): bool
    {
        $from = $from instanceof Node ? $from->id : $from;
        $to = $to instanceof Node ? $to->id : $to;

        if ((bool)$this->directed) {
            return $this->matchesEndpoints((string)$from, (string)$to);
        }

        return $this->matchesEndpoints((string)$from, (string)$to)
            || $this->matchesEndpoints((string)$to, (string)$from);
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

    private function matchesEndpoints(string $from, string $to): bool
    {
        return Str::make((string)$this->from)->match($from)
            && Str::make((string)$this->to)->match($to);
    }
}
