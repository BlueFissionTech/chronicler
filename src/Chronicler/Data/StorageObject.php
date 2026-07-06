<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Data;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\Data\Data;
use BlueFission\Data\Schema;
use BlueFission\DataTypes;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

class StorageObject extends Data
{
    use DevElationValues;

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

        if (Str::isNotEmpty($name)) {
            $this->name = Str::trim($name);
        }
        $this->payload($payload);
        $this->schema($schema);
        $this->meta($meta);
    }

    public function schema(array|Schema|null $schema = null): ?Schema
    {
        if (Val::isNotNull($schema)) {
            $this->schema = $schema instanceof Schema ? $schema : new Schema($schema);
        }

        return $this->schema instanceof Schema ? $this->schema : null;
    }

    public function payload(array|object|null $payload = null): array
    {
        if (Val::isNotNull($payload)) {
            $payload = $this->applyValue($payload);
            if (!Arr::is($payload)) {
                $object = $payload instanceof Obj ? $payload : (new Obj())->assign($payload);
                $payload = $object->toArray();
            }
            $this->payload = $this->valueArray($payload);
        }

        return Arr::toArray($this->payload);
    }

    public function meta(array|null $meta = null): array
    {
        if (Val::isNotNull($meta)) {
            $this->meta = $this->valueArray($meta);
        }

        return Arr::toArray($this->meta);
    }

    public function validate(): bool
    {
        $schema = $this->schema();
        if (!$schema instanceof Schema) {
            return true;
        }

        return $schema->validate($this->payload());
    }

    public function transform(): array
    {
        $schema = $this->schema();
        if (!$schema instanceof Schema) {
            return $this->payload();
        }

        $payload = $schema->transform($this->payload());
        $this->payload($payload);

        return $payload;
    }
}
