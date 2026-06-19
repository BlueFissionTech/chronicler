<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Graph;

use BlueFission\Arr;
use BlueFission\Data\Graph\Node as BaseNode;
use BlueFission\DevElation as Dev;

final class Node extends BaseNode
{
    public function __construct(string $id, array $properties = [], array $labels = [])
    {
        parent::__construct($id, [
            'labels' => Arr::values(Arr::toArray($labels)),
            'properties' => Arr::toArray(Dev::apply(null, $properties)),
        ]);
    }

    public function label(string $label): self
    {
        $labels = $this->labels();
        if (!Arr::hasValue($labels, $label, true)) {
            $labels[] = $label;
        }
        $this->labels($labels);

        return $this;
    }

    public function labels(?array $labels = null): array
    {
        if ($labels !== null) {
            $data = $this->record();
            $data['labels'] = Arr::values(Arr::toArray($labels));
            $this->data = $data;
        }

        return Arr::toArray($this->record()['labels'] ?? []);
    }

    public function property(string $name, mixed $value): self
    {
        $properties = $this->properties();
        $properties[$name] = Dev::apply(null, $value);
        $this->properties($properties);

        return $this;
    }

    public function properties(?array $properties = null): array
    {
        if ($properties !== null) {
            $data = $this->record();
            $data['properties'] = Arr::toArray(Dev::apply(null, $properties));
            $this->data = $data;
        }

        return Arr::toArray($this->record()['properties'] ?? []);
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
        return Arr::toArray(Dev::apply(null, $this->data));
    }
}
