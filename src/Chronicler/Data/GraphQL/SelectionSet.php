<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Data\GraphQL;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\Obj;
use BlueFission\Str;

final class SelectionSet extends Obj
{
    protected $_data = [
        'fields' => [],
    ];

    protected $_types = [
        'fields' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function __construct(array $fields = [])
    {
        parent::__construct();

        foreach ($fields as $field) {
            $this->add($field);
        }
    }

    public static function fromArray(array $definition): self
    {
        $selection = new self();
        foreach ($definition as $key => $value) {
            if ($value instanceof FieldNode) {
                $selection->add($value);
                continue;
            }

            if (Str::is($key) && Arr::is($value)) {
                $selection->add(new FieldNode($key, [], self::fromArray($value)));
                continue;
            }

            if (Arr::is($value)) {
                $selection->add(FieldNode::fromArray($value));
                continue;
            }

            $selection->add((string)$value);
        }

        return $selection;
    }

    public function add(FieldNode|string $field): self
    {
        $fields = $this->fields();
        $fields[] = $field instanceof FieldNode ? $field : new FieldNode($field);
        $this->fields = $fields;

        return $this;
    }

    /** @return array<int, FieldNode> */
    public function fields(): array
    {
        return Arr::toArray($this->fields);
    }

    public function toGraphQL(int $indent = 0): string
    {
        $innerIndent = $indent + 2;
        $lines = ['{'];
        foreach ($this->fields() as $field) {
            if ($field instanceof FieldNode) {
                $lines[] = $field->toGraphQL($innerIndent);
            }
        }
        $lines[] = Str::make(' ')->repeat($indent)->val() . '}';

        return implode(PHP_EOL, $lines);
    }

    /** @return array<int, array<string, mixed>> */
    public function toArray(): array
    {
        $fields = [];
        foreach ($this->fields() as $field) {
            if ($field instanceof FieldNode) {
                $fields[] = $field->toArray();
            }
        }

        return $fields;
    }
}
