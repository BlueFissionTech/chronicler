<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Structures;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Str;

final class BloomFilter extends Obj
{
    use DevElationValues;

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
            $bits = $this->assignArrayValue($bits, $index, 1);
        }
        $this->bits = $bits;

        return $this;
    }

    public function mightContain(string $item): bool
    {
        $bits = $this->bits();
        foreach ($this->indexes($item) as $index) {
            if ((int)Arr::getPath($bits, $index, 0) !== 1) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, int> */
    public function indexes(string $item): array
    {
        $indexes = Arr::make();
        for ($salt = 0; $salt < (int)$this->hashes; $salt++) {
            $hash = Str::make((string)$salt)->append(':')->append($item)->encrypt(Str::SHA)->sub(0, 8);
            $hashNumber = Num::make(0)->hex($hash)->val();
            $indexes->push((int)Num::make($hashNumber)->abs()->val() % (int)$this->size);
        }

        return $indexes->val();
    }

    public function bits(): array
    {
        return $this->valueArray($this->bits);
    }

    /** @return array<int, int> */
    private function fillBits(int $size): array
    {
        $bits = Arr::make();
        for ($index = 0; $index < $size; $index++) {
            $bits->push(0);
        }

        return $bits->val();
    }
}
