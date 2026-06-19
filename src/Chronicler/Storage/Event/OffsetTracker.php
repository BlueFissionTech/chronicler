<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Event;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\Obj;

final class OffsetTracker extends Obj
{
    protected $_data = [
        'offsets' => [],
    ];

    protected $_types = [
        'offsets' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function commit(string $topic, int $partition, int $offset): self
    {
        $offsets = $this->offsets();
        $offsets[$topic] = Arr::toArray($offsets[$topic] ?? []);
        $offsets[$topic][(string)$partition] = $offset;
        $this->offsets = $offsets;

        return $this;
    }

    public function offset(string $topic, int $partition): ?int
    {
        $offsets = $this->offsets();

        return isset($offsets[$topic][(string)$partition]) ? (int)$offsets[$topic][(string)$partition] : null;
    }

    public function lag(string $topic, int $partition, int $latestOffset): ?int
    {
        $offset = $this->offset($topic, $partition);
        if ($offset === null) {
            return null;
        }

        return max(0, $latestOffset - $offset);
    }

    public function offsets(): array
    {
        return Arr::toArray($this->offsets);
    }
}
