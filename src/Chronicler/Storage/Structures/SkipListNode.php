<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Structures;

final class SkipListNode
{
    /** @var array<int, SkipListNode|null> */
    public array $forward;

    public function __construct(
        public string $key,
        public mixed $value,
        int $level
    ) {
        $this->forward = [];
        for ($index = 0; $index <= $level; $index++) {
            $this->forward[$index] = null;
        }
    }
}
