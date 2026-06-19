<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Data\GraphQL;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\Obj;
use BlueFission\Str;
use InvalidArgumentException;

final class FieldNode extends Obj
{
    protected $_data = [
        'name' => '',
        'alias' => null,
        'arguments' => [],
        'selection' => null,
    ];

    protected $_types = [
        'name' => DataTypes::STRING,
        'alias' => DataTypes::STRING,
        'arguments' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function __construct(string $name, array $arguments = [], ?SelectionSet $selection = null, ?string $alias = null)
    {
        parent::__construct();

        $name = $this->normalizeName($name);
        if ($name === '') {
            throw new InvalidArgumentException('GraphQL field name cannot be empty.');
        }

        $this->name = $name;
        $this->arguments($arguments);
        $this->selection($selection);
        if ($alias !== null) {
            $this->alias($alias);
        }
    }

    public static function named(string $name): self
    {
        return new self($name);
    }

    public static function fromArray(array $definition): self
    {
        $name = (string)($definition['name'] ?? '');
        $field = new self($name, Arr::toArray($definition['arguments'] ?? []));

        if (isset($definition['alias'])) {
            $field->alias((string)$definition['alias']);
        }

        if (isset($definition['selection']) && Arr::is($definition['selection'])) {
            $field->selection(SelectionSet::fromArray($definition['selection']));
        }

        return $field;
    }

    public function alias(?string $alias = null): ?string
    {
        if ($alias !== null) {
            $this->alias = $this->normalizeName($alias);
        }

        return $this->alias;
    }

    public function argument(string $name, mixed $value): self
    {
        $arguments = $this->arguments();
        $arguments[$this->normalizeName($name)] = $value;
        $this->arguments($arguments);

        return $this;
    }

    public function arguments(?array $arguments = null): array
    {
        if ($arguments !== null) {
            $this->arguments = Arr::toArray($arguments);
        }

        return Arr::toArray($this->arguments);
    }

    public function selection(?SelectionSet $selection = null): ?SelectionSet
    {
        if ($selection !== null) {
            $this->selection = $selection;
        }

        return $this->selection instanceof SelectionSet ? $this->selection : null;
    }

    public function select(FieldNode|string $field): self
    {
        $selection = $this->selection() ?? new SelectionSet();
        $selection->add($field);
        $this->selection($selection);

        return $this;
    }

    public function toGraphQL(int $indent = 0): string
    {
        $prefix = Str::make(' ')->repeat($indent)->val();
        $parts = [];
        $alias = $this->alias();
        if ($alias) {
            $parts[] = $alias . ':';
        }

        $parts[] = (string)$this->name;
        $arguments = $this->formatArguments($this->arguments());
        if ($arguments !== '') {
            $parts[] = $arguments;
        }

        $line = $prefix . implode(' ', $parts);
        $selection = $this->selection();
        if ($selection) {
            $line .= ' ' . $selection->toGraphQL($indent);
        }

        return $line;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'alias' => $this->alias(),
            'arguments' => $this->arguments(),
            'selection' => $this->selection()?->toArray() ?? [],
        ];
    }

    private function formatArguments(array $arguments): string
    {
        if (!$arguments) {
            return '';
        }

        $parts = [];
        foreach ($arguments as $name => $value) {
            $parts[] = $this->normalizeName((string)$name) . ': ' . $this->formatValue($value);
        }

        return '(' . implode(', ', $parts) . ')';
    }

    private function formatValue(mixed $value): string
    {
        if (Str::is($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (Arr::is($value)) {
            $parts = [];
            if (Arr::isAssoc($value)) {
                foreach ($value as $key => $item) {
                    $parts[] = $this->normalizeName((string)$key) . ': ' . $this->formatValue($item);
                }

                return '{' . implode(', ', $parts) . '}';
            }

            foreach ($value as $item) {
                $parts[] = $this->formatValue($item);
            }

            return '[' . implode(', ', $parts) . ']';
        }

        return (string)$value;
    }

    private function normalizeName(string $name): string
    {
        $name = Str::trim($name);

        return preg_match('/^[_A-Za-z][_0-9A-Za-z]*$/', $name) ? $name : '';
    }
}
