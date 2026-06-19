<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Data\GraphQL;

use BlueFission\Arr;
use BlueFission\Data\Schema;
use BlueFission\DataTypes;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;
use BlueFission\Str;
use RuntimeException;

final class SchemaRegistry extends Obj
{
    protected $_data = [
        'schemas' => [],
        'types' => [],
    ];

    protected $_types = [
        'schemas' => DataTypes::ARRAY,
        'types' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function registerSchema(string $name, Schema $schema): self
    {
        $schemas = $this->schemas();
        $schemas[$name] = $schema;
        $this->schemas = $schemas;

        return $this;
    }

    public function registerType(string $name, array $definition): self
    {
        $types = $this->types();
        $types[$name] = Dev::apply(null, $definition);
        $this->types = $types;

        return $this;
    }

    public function schema(string $name): ?Schema
    {
        $schemas = $this->schemas();
        $schema = $schemas[$name] ?? null;

        return $schema instanceof Schema ? $schema : null;
    }

    public function type(string $name): array
    {
        $types = $this->types();

        return Arr::toArray($types[$name] ?? []);
    }

    public function has(string $name): bool
    {
        return Arr::hasKey($this->schemas(), $name) || Arr::hasKey($this->types(), $name);
    }

    public function validate(string $name, array|object $data): bool
    {
        $schema = $this->schema($name);
        if (!$schema) {
            throw new RuntimeException('Schema not registered: ' . $name);
        }

        return $schema->validate($data);
    }

    /** @return array<int, string> */
    public function traverse(string $name, string $field = 'fields'): array
    {
        $type = $this->type($name);
        if (!$type) {
            return [];
        }

        $fields = Arr::toArray($type[$field] ?? []);
        $names = [];
        foreach ($fields as $key => $value) {
            $names[] = Str::is($key) ? $key : (string)$value;
        }

        return $names;
    }

    /** @return array<string, Schema> */
    public function schemas(): array
    {
        return Arr::toArray($this->schemas);
    }

    /** @return array<string, array<string, mixed>> */
    public function types(): array
    {
        return Arr::toArray($this->types);
    }
}
