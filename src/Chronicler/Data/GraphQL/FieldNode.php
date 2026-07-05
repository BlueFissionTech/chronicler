<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Data\GraphQL;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\Flag;
use BlueFission\Net\HTTP;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;
use InvalidArgumentException;

final class FieldNode extends Obj
{
    use DevElationValues;

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
        if (Str::make($name)->isEmpty()) {
            throw new InvalidArgumentException('GraphQL field name cannot be empty.');
        }

        $this->name = $name;
        $this->arguments($arguments);
        $this->selection($selection);
        if (Val::isNotNull($alias)) {
            $this->alias($alias);
        }
    }

    public static function named(string $name): self
    {
        return new self($name);
    }

    public static function fromArray(array $definition): self
    {
        $name = (string)Arr::getPath($definition, 'name', '');
        $field = new self($name, Arr::toArray(Arr::getPath($definition, 'arguments', [])));

        if (Arr::hasKey($definition, 'alias')) {
            $field->alias((string)Arr::getPath($definition, 'alias'));
        }

        $selection = Arr::getPath($definition, 'selection');
        if (Arr::is($selection)) {
            $field->selection(SelectionSet::fromArray($selection));
        }

        return $field;
    }

    public function alias(?string $alias = null): ?string
    {
        if (Val::isNotNull($alias)) {
            $this->alias = $this->normalizeName($alias);
        }

        return $this->alias;
    }

    public function argument(string $name, mixed $value): self
    {
        $this->arguments($this->assignArrayValue($this->arguments(), $this->normalizeName($name), $value));

        return $this;
    }

    public function arguments(?array $arguments = null): array
    {
        if (Val::isNotNull($arguments)) {
            $this->arguments = $this->valueArray($arguments);
        }

        return Arr::toArray($this->arguments);
    }

    public function selection(?SelectionSet $selection = null): ?SelectionSet
    {
        if (Val::isNotNull($selection)) {
            $this->selection = $selection;
        }

        return $this->selection instanceof SelectionSet ? $this->selection : null;
    }

    public function select(FieldNode|string $field): self
    {
        $selection = $this->selection();
        if (!$selection instanceof SelectionSet) {
            $selection = new SelectionSet();
        }

        $selection->add($field);
        $this->selection($selection);

        return $this;
    }

    public function toGraphQL(int $indent = 0): string
    {
        $prefix = Str::make(' ')->repeat($indent)->val();
        $parts = Arr::make();
        $alias = $this->alias();
        if (Str::isNotEmpty($alias)) {
            $parts->push(Str::make((string)$alias)->append(':')->val());
        }

        $parts->push((string)$this->name);
        $arguments = $this->formatArguments($this->arguments());
        if (Str::isNotEmpty($arguments)) {
            $parts->push($arguments);
        }

        $line = Str::make($prefix)->append(Arr::make($parts->val())->join(' ')->val())->val();
        $selection = $this->selection();
        if ($selection instanceof SelectionSet) {
            $line = Str::make($line)->append(' ')->append($selection->toGraphQL($indent))->val();
        }

        return $line;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $selection = $this->selection();

        return [
            'name' => $this->name,
            'alias' => $this->alias(),
            'arguments' => $this->arguments(),
            'selection' => $selection instanceof SelectionSet ? $selection->toArray() : [],
        ];
    }

    private function formatArguments(array $arguments): string
    {
        if (!Arr::isNotEmpty($arguments)) {
            return '';
        }

        $parts = Arr::make();
        foreach ($arguments as $name => $value) {
            $parts->push(
                Str::make($this->normalizeName((string)$name))
                    ->append(': ')
                    ->append($this->formatValue($value))
                    ->val()
            );
        }

        return Str::make('(')->append(Arr::make($parts->val())->join(', ')->val())->append(')')->val();
    }

    private function formatValue(mixed $value): string
    {
        if (Str::is($value)) {
            return HTTP::jsonEncode($value);
        }

        if (Flag::isValid($value)) {
            return (string)Flag::make($value);
        }

        if (Val::isNull($value)) {
            return 'null';
        }

        if (Arr::is($value)) {
            $parts = Arr::make();
            if (Arr::isAssoc($value)) {
                foreach ($value as $key => $item) {
                    $parts->push(
                        Str::make($this->normalizeName((string)$key))
                            ->append(': ')
                            ->append($this->formatValue($item))
                            ->val()
                    );
                }

                return Str::make('{')->append(Arr::make($parts->val())->join(', ')->val())->append('}')->val();
            }

            foreach ($value as $item) {
                $parts->push($this->formatValue($item));
            }

            return Str::make('[')->append(Arr::make($parts->val())->join(', ')->val())->append(']')->val();
        }

        return (string)$value;
    }

    private function normalizeName(string $name): string
    {
        $name = Str::trim($name);

        return Str::matches($name, '/^[_A-Za-z][_0-9A-Za-z]*$/') ? $name : '';
    }
}
