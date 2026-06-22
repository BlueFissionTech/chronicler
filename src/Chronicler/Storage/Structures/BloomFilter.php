<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Structures;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\Num;
use BlueFission\Obj;

final class BloomFilter extends Obj
{
    protected $_data = [
        'size' => 1024,
        'hashes' => 3,
        'bits' => [],
    ];

    protected $_types = [
        'size' => DataTypes::INTEGER,
        'hashes' => DataTypes::INTEGER,
        'bits' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function __construct(int $size = 1024, int $hashes = 3)
    {
        parent::__construct();

        $this->size = (int)Num::max(1, $size);
        $this->hashes = (int)Num::max(1, $hashes);
        $this->bits = $this->fillBits((int)$this->size);
    }

    public function add(string $item): self
    {
        $bits = $this->bits();
        foreach ($this->indexes($item) as $index) {
            $bits[$index] = 1;
        }
        $this->bits = $bits;

        return $this;
    }

    public function mightContain(string $item): bool
    {
        $bits = $this->bits();
        foreach ($this->indexes($item) as $index) {
            if (($bits[$index] ?? 0) !== 1) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, int> */
    public function indexes(string $item): array
    {
        $indexes = [];
        for ($salt = 0; $salt < (int)$this->hashes; $salt++) {
            $hash = crc32($salt . ':' . $item);
            $indexes[] = (int)($hash % (int)$this->size);
        }

        return $indexes;
    }

    public function bits(): array
    {
        return Arr::toArray($this->bits);
    }

    /** @return array<int, int> */
    private function fillBits(int $size): array
    {
        $bits = [];
        for ($index = 0; $index < $size; $index++) {
            $bits[$index] = 0;
        }

        return $bits;
    }
}
