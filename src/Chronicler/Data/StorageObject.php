<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Data;

use BlueFission\Arr;
use BlueFission\Data\Data;
use BlueFission\Data\Schema;
use BlueFission\DataTypes;
use BlueFission\DevElation as Dev;
use BlueFission\Obj as DynamicObject;

class StorageObject extends Data
{
    protected $_data = [
        'name' => '',
        'schema' => null,
        'payload' => [],
        'meta' => [],
    ];

    protected $_types = [
        'name' => DataTypes::STRING,
        'payload' => DataTypes::ARRAY,
        'meta' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function __construct(string $name = '', array|object $payload = [], array|Schema|null $schema = null, array $meta = [])
    {
        parent::__construct();

        if ($name !== '') {
            $this->name = $name;
        }
        $this->payload($payload);
        $this->schema($schema);
        $this->meta($meta);
    }

    public function schema(array|Schema|null $schema = null): ?Schema
    {
        if ($schema !== null) {
            $this->schema = $schema instanceof Schema ? $schema : new Schema($schema);
        }

        return $this->schema instanceof Schema ? $this->schema : null;
    }

    public function payload(array|object|null $payload = null): array
    {
        if ($payload !== null) {
            $payload = Dev::apply(null, $payload);
            if (!Arr::is($payload)) {
                $object = $payload instanceof DynamicObject ? $payload : (new DynamicObject())->assign($payload);
                $payload = $object->toArray();
            }
            $this->payload = Arr::toArray($payload);
        }

        return Arr::toArray($this->payload);
    }

    public function meta(array|null $meta = null): array
    {
        if ($meta !== null) {
            $this->meta = Arr::toArray($meta);
        }

        return Arr::toArray($this->meta);
    }

    public function validate(): bool
    {
        $schema = $this->schema();
        if (!$schema) {
            return true;
        }

        return $schema->validate($this->payload());
    }

    public function transform(): array
    {
        $schema = $this->schema();
        if (!$schema) {
            return $this->payload();
        }

        $payload = $schema->transform($this->payload());
        $this->payload($payload);

        return $payload;
    }
}
