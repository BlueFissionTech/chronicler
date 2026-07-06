<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Event;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

final class OffsetTracker extends Obj
{
    use DevElationValues;

    protected $_data = [
        'offsets' => [],
    ];

    protected $_types = [
        'offsets' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function commit(string $topic, int $partition, int $offset): self
    {
        $topic = Str::trim($topic);
        $topicOffsets = Arr::toArray(Arr::getPath($this->offsets(), $topic, []));
        $topicOffsets = $this->assignArrayValue($topicOffsets, (string)$partition, $offset);
        $this->offsets = $this->assignArrayValue($this->offsets(), $topic, $topicOffsets);

        return $this;
    }

    public function offset(string $topic, int $partition): ?int
    {
        $offset = Arr::getPath($this->offsets(), [Str::trim($topic), (string)$partition]);

        return Val::isNull($offset) ? null : (int)$offset;
    }

    public function lag(string $topic, int $partition, int $latestOffset): ?int
    {
        $offset = $this->offset($topic, $partition);
        if (Val::isNull($offset)) {
            return null;
        }

        return (int)Num::max(0, $latestOffset - $offset);
    }

    public function offsets(): array
    {
        return Arr::toArray($this->offsets);
    }
}
