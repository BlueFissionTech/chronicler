<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Graph;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\Data\Graph\Node as BaseNode;
use BlueFission\Str;
use BlueFission\Val;

final class Node extends BaseNode
{
    use DevElationValues;

    public function __construct(string $id, array $properties = [], array $labels = [])
    {
        parent::__construct($id, [
            'labels' => Arr::values($this->valueArray($labels)),
            'properties' => $this->valueArray($properties),
        ]);
    }

    public function label(string $label): self
    {
        $label = Str::trim($label);
        if (Str::make($label)->isEmpty()) {
            return $this;
        }

        $labels = $this->labels();
        if (!Arr::has($labels, $label, true)) {
            $labels = $this->appendArrayValue($labels, $label);
        }
        $this->labels($labels);

        return $this;
    }

    public function labels(?array $labels = null): array
    {
        if (Val::isNotNull($labels)) {
            $data = $this->record();
            $data = $this->assignArrayValue($data, 'labels', Arr::values($this->valueArray($labels)));
            $this->data = $data;
        }

        return $this->valueArray($this->pathValue($this->record(), 'labels', []));
    }

    public function property(string $name, mixed $value): self
    {
        $this->properties($this->assignArrayValue($this->properties(), Str::trim($name), $value));

        return $this;
    }

    public function properties(?array $properties = null): array
    {
        if (Val::isNotNull($properties)) {
            $data = $this->record();
            $data = $this->assignArrayValue($data, 'properties', $this->valueArray($properties));
            $this->data = $data;
        }

        return $this->valueArray($this->pathValue($this->record(), 'properties', []));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'labels' => $this->labels(),
            'properties' => $this->properties(),
        ];
    }

    private function record(): array
    {
        return $this->valueArray($this->data);
    }
}
