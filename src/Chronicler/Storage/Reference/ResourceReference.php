<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Reference;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;

final class ResourceReference extends Obj
{
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

        $this->id = $id;
        $this->kind = $kind;
        $this->target = $target;
        $this->relation = $relation;
        $this->attributes($attributes);
    }

    public function attribute(string $name, mixed $value): self
    {
        $attributes = $this->attributes();
        $attributes[$name] = Dev::apply(null, $value);
        $this->attributes($attributes);

        return $this;
    }

    public function attributes(?array $attributes = null): array
    {
        if ($attributes !== null) {
            $this->attributes = Arr::toArray(Dev::apply(null, $attributes));
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
