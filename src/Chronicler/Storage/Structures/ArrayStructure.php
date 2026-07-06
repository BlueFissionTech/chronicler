<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Structures;

use BlueFission\Arr;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\IVal;
use BlueFission\Obj;

abstract class ArrayStructure extends Obj
{
    use DevElationValues;

    protected $_data = [
        'items' => [],
    ];

    protected $_types = [
        'items' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function count(): int
    {
        return Arr::count($this->items());
    }

    public function size(): int
    {
        return $this->count();
    }

    public function isEmpty(): bool
    {
        return !Arr::isNotEmpty($this->items());
    }

    public function clear(): static
    {
        $this->mutateItems([]);

        return $this;
    }

    public function items(): array
    {
        return $this->valueArray($this->items);
    }

    public function values(): array
    {
        return Arr::toArray(Arr::values($this->items()));
    }

    public function toArray(): array
    {
        return $this->items();
    }

    protected function mutateItems(array $items): void
    {
        $this->setItems($items);
        $this->dispatch(Event::CHANGE, ['items' => $this->items()]);
    }

    private function setItems(array $items): void
    {
        if (($this->_data['items'] ?? null) instanceof IVal) {
            $this->_data['items']->val($items);
            return;
        }

        $this->_data['items'] = $items;
    }
}
