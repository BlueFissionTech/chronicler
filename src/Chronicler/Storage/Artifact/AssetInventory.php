<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Artifact;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\Obj;

final class AssetInventory extends Obj
{
    use DevElationValues;

    protected $_data = [
        'assets' => [],
    ];

    protected $_types = [
        'assets' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function add(ArtifactReference $reference): self
    {
        $this->assets = $this->assignArrayValue($this->assets(), (string)$reference->id, $reference);

        return $this;
    }

    public function has(string $id): bool
    {
        return Arr::hasKey($this->assets(), $id);
    }

    public function get(string $id): ?ArtifactReference
    {
        $asset = Arr::getPath($this->assets(), $id);

        return $asset instanceof ArtifactReference ? $asset : null;
    }

    public function byType(string $type): array
    {
        $matches = Arr::make();
        Arr::make($this->assets())->each(function (mixed $reference) use ($type, $matches): void {
            if ($reference instanceof ArtifactReference && $reference->type === $type) {
                $matches->push($reference);
            }
        });

        return Arr::values($matches->val());
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
        $assets = Arr::make();
        Arr::make($this->assets())->each(function (mixed $reference, mixed $id) use ($assets): void {
            if ($reference instanceof ArtifactReference) {
                $assets->set($id, $reference->toArray());
            }
        });

        return $assets->val();
    }
}
