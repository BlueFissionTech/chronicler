<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Reference;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

final class ResourceReference extends Obj
{
    use DevElationValues;

    public const KIND_GRAPH_NODE = 'graph_node';
    public const KIND_GRAPH_EDGE = 'graph_edge';
    public const KIND_DOCUMENT = 'document';
    public const KIND_ARTIFACT = 'artifact';
    public const KIND_EVENT_OFFSET = 'event_offset';

    protected $_data = [
        'id' => '',
        'kind' => '',
        'target' => '',
        'relation' => '',
        'attributes' => [],
    ];

    protected $_types = [
        'id' => DataTypes::STRING,
        'kind' => DataTypes::STRING,
        'target' => DataTypes::STRING,
        'relation' => DataTypes::STRING,
        'attributes' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function __construct(string $id, string $kind, string $target, string $relation = '', array $attributes = [])
    {
        parent::__construct();

        $this->id = Str::trim($id);
        $this->kind = Str::trim($kind);
        $this->target = Str::trim($target);
        $this->relation = Str::trim($relation);
        $this->attributes($attributes);
    }

    public function attribute(string $name, mixed $value): self
    {
        $this->attributes($this->assignArrayValue($this->attributes(), Str::trim($name), $value));

        return $this;
    }

    public function attributes(?array $attributes = null): array
    {
        if (Val::isNotNull($attributes)) {
            $this->attributes = $this->valueArray($attributes);
        }

        return Arr::toArray($this->attributes);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'target' => $this->target,
            'relation' => $this->relation,
            'attributes' => $this->attributes(),
        ];
    }
}
