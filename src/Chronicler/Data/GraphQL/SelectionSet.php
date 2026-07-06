<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Data\GraphQL;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\Obj;
use BlueFission\Str;

final class SelectionSet extends Obj
{
    use DevElationValues;

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

        Arr::make($fields)->each(function (mixed $field): void {
            $this->add($field);
        });
    }

    public static function fromArray(array $definition): self
    {
        $selection = new self();
        Arr::make($definition)->each(function (mixed $value, mixed $key) use ($selection): void {
            if ($value instanceof FieldNode) {
                $selection->add($value);
                return;
            }

            if (Str::is($key) && Arr::is($value)) {
                $selection->add(new FieldNode($key, [], self::fromArray($value)));
                return;
            }

            if (Arr::is($value)) {
                $selection->add(FieldNode::fromArray($value));
                return;
            }

            $selection->add((string)$value);
        });

        return $selection;
    }

    public function add(FieldNode|string $field): self
    {
        $this->fields = $this->appendArrayValue(
            $this->fields(),
            $field instanceof FieldNode ? $field : new FieldNode($field)
        );

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
        $lines = Arr::make(['{']);
        Arr::make($this->fields())->each(function (mixed $field) use ($lines, $innerIndent): void {
            if ($field instanceof FieldNode) {
                $lines->push($field->toGraphQL($innerIndent));
            }
        });
        $lines->push(Str::make(' ')->repeat($indent)->append('}')->val());

        return Arr::make($lines->val())->join(PHP_EOL)->val();
    }

    /** @return array<int, array<string, mixed>> */
    public function toArray(): array
    {
        $fields = Arr::make();
        Arr::make($this->fields())->each(function (mixed $field) use ($fields): void {
            if ($field instanceof FieldNode) {
                $fields->push($field->toArray());
            }
        });

        return $fields->val();
    }
}
