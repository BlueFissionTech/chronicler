<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Data\GraphQL;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\Data\Schema;
use BlueFission\DataTypes;
use BlueFission\Obj;
use BlueFission\Str;
use RuntimeException;

final class SchemaRegistry extends Obj
{
    use DevElationValues;

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
        $this->schemas = $this->assignArrayValue($this->schemas(), Str::trim($name), $schema);

        return $this;
    }

    public function registerType(string $name, array $definition): self
    {
        $this->types = $this->assignArrayValue($this->types(), Str::trim($name), $definition);

        return $this;
    }

    public function schema(string $name): ?Schema
    {
        $schema = Arr::getPath($this->schemas(), $name);

        return $schema instanceof Schema ? $schema : null;
    }

    public function type(string $name): array
    {
        $types = $this->types();

        return Arr::toArray(Arr::getPath($types, $name, []));
    }

    public function has(string $name): bool
    {
        return Arr::hasKey($this->schemas(), $name) || Arr::hasKey($this->types(), $name);
    }

    public function validate(string $name, array|object $data): bool
    {
        $schema = $this->schema($name);
        if (!$schema instanceof Schema) {
            throw new RuntimeException('Schema not registered: ' . $name);
        }

        return $schema->validate($data);
    }

    /** @return array<int, string> */
    public function traverse(string $name, string $field = 'fields'): array
    {
        $type = $this->type($name);
        if (!Arr::isNotEmpty($type)) {
            return [];
        }

        $fields = Arr::toArray(Arr::getPath($type, $field, []));
        $names = Arr::make();
        Arr::make($fields)->each(function (mixed $value, mixed $key) use ($names): void {
            $names->push(Str::is($key) ? $key : (string)$value);
        });

        return $names->val();
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
