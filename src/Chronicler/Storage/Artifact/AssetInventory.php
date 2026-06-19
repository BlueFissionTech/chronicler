<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Artifact;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\Obj;

final class AssetInventory extends Obj
{
    protected $_data = [
        'assets' => [],
    ];

    protected $_types = [
        'assets' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function add(ArtifactReference $reference): self
    {
        $assets = $this->assets();
        $assets[$reference->id] = $reference;
        $this->assets = $assets;

        return $this;
    }

    public function has(string $id): bool
    {
        return Arr::hasKey($this->assets(), $id);
    }

    public function get(string $id): ?ArtifactReference
    {
        $asset = $this->assets()[$id] ?? null;

        return $asset instanceof ArtifactReference ? $asset : null;
    }

    public function byType(string $type): array
    {
        $matches = [];
        foreach ($this->assets() as $reference) {
            if ($reference instanceof ArtifactReference && $reference->type === $type) {
                $matches[] = $reference;
            }
        }

        return Arr::values($matches);
    }

    public function assets(): array
    {
        return Arr::toArray($this->assets);
    }

    public function all(): array
    {
        return Arr::values($this->assets());
    }

    public function toArray(): array
    {
        $assets = [];
        foreach ($this->assets() as $id => $reference) {
            if ($reference instanceof ArtifactReference) {
                $assets[$id] = $reference->toArray();
            }
        }

        return $assets;
    }
}
