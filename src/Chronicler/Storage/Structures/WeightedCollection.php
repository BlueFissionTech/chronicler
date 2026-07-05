<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Structures;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\Date;
use BlueFission\IVal;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

final class WeightedCollection extends Obj
{
    use DevElationValues;

    protected $_data = [
        'entries' => [],
        'max' => 1048576,
        'decay' => 0.001,
        'autosort' => true,
        'decay_enabled' => false,
        'sequence' => 0,
    ];

    protected $_types = [
        'entries' => DataTypes::ARRAY,
        'max' => DataTypes::INTEGER,
        'decay' => DataTypes::FLOAT,
        'autosort' => DataTypes::BOOLEAN,
        'decay_enabled' => DataTypes::BOOLEAN,
        'sequence' => DataTypes::INTEGER,
    ];

    protected $_lockDataType = true;

    public function __construct(int $max = 1048576, float $decay = 0.001)
    {
        parent::__construct();

        $this->max = (int)Num::max(1, $max);
        $this->decay = (float)Num::max(0, $decay);
    }

    public function add(mixed $value, string|int|null $key = null, int|float $weight = 1): self
    {
        $sequence = $this->nextSequence();
        $key = $this->normalizeKey($key, $sequence);
        $entries = $this->entries();
        $index = $this->indexOf($key);

        if (Val::isNotNull($index)) {
            $entry = Arr::toArray(Arr::getPath($entries, $index, []));
            $entry = $this->assignArrayValue($entry, 'value', $value);
            $entry = $this->assignArrayValue($entry, 'weight', Num::make(Arr::getPath($entry, 'weight', 0))->plus($weight)->val());
            $entry = $this->assignArrayValue($entry, 'timestamp', $this->now());
            $entries = $this->assignArrayValue($entries, $index, $entry);
        } else {
            $entries = $this->appendArrayValue($entries, $this->entry($key, $value, $weight, $sequence));
        }

        $this->setEntries($this->trimToMax($entries));
        $this->sortEntries();

        return $this;
    }

    public function get(string|int $key, bool $reinforce = true): mixed
    {
        $index = $this->indexOf($key);
        if (Val::isNull($index)) {
            return null;
        }

        $entry = Arr::toArray(Arr::getPath($this->entries(), $index, []));
        if ($reinforce) {
            $this->weight($key, Num::make(Arr::getPath($entry, 'weight', 0))->increment()->val());
            $entry = Arr::toArray(Arr::getPath($this->entries(), $this->indexOf($key), []));
        }

        return Arr::getPath($entry, 'value');
    }

    public function has(string|int $key): bool
    {
        return Val::isNotNull($this->indexOf($key));
    }

    public function weight(string|int $key, int|float|null $weight = null): int|float|null
    {
        $index = $this->indexOf($key);
        if (Val::isNull($index)) {
            return null;
        }

        $entry = Arr::toArray(Arr::getPath($this->entries(), $index, []));
        if (Val::isNull($weight)) {
            return Arr::getPath($entry, 'weight', 0);
        }

        $entry = $this->assignArrayValue($entry, 'weight', $weight);
        $entry = $this->assignArrayValue($entry, 'timestamp', $this->now());
        $entries = $this->assignArrayValue($this->entries(), $index, $entry);
        $this->setEntries($entries);
        $this->sortEntries();

        return Arr::getPath($entry, 'weight', 0);
    }

    public function remove(string|int $key): bool
    {
        $index = $this->indexOf($key);
        if (Val::isNull($index)) {
            return false;
        }

        $entries = Arr::make($this->entries());
        $entries->delete($index);
        $this->setEntries(Arr::values($entries->val()));
        $this->sortEntries();

        return true;
    }

    public function clear(): self
    {
        $this->setEntries([]);
        $this->sequence = 0;

        return $this;
    }

    public function autoSort(bool $autosort = true): self
    {
        $this->autosort = $autosort;

        return $this;
    }

    public function decay(bool $enabled = true, ?float $rate = null): self
    {
        $this->decay_enabled = $enabled;
        if (Val::isNotNull($rate)) {
            $this->decay = (float)Num::max(0, $rate);
        }

        return $this;
    }

    public function optimize(int|float $tolerance = 0, array $noise = []): self
    {
        $filtered = Arr::make();
        Arr::make($this->entries())->each(function (mixed $entry) use ($filtered, $tolerance, $noise): void {
            $entry = Arr::toArray($entry);
            $weight = $this->effectiveWeight($entry);
            $value = Arr::getPath($entry, 'value');

            if ($weight >= $tolerance && !Arr::has($noise, $value, true)) {
                $filtered->push($this->assignArrayValue($entry, 'weight', $weight));
            }
        });

        $this->setEntries($filtered->val());
        $this->sortEntries();

        return $this;
    }

    public function entries(): array
    {
        return $this->valueArray($this->entries);
    }

    public function ranked(): array
    {
        $this->sortEntries();

        return $this->entries();
    }

    public function values(): array
    {
        $values = Arr::make();
        Arr::make($this->ranked())->each(function (mixed $entry) use ($values): void {
            $values->push(Arr::getPath(Arr::toArray($entry), 'value'));
        });

        return $values->val();
    }

    public function stats(): array
    {
        $weights = Arr::make();
        $total = 0.0;

        Arr::make($this->entries())->each(function (mixed $entry) use ($weights, &$total): void {
            $weight = $this->effectiveWeight(Arr::toArray($entry));
            $weights->push($weight);
            $total = Num::make($total)->plus($weight)->val();
        });

        $count = Arr::count($weights->val());

        return [
            'count' => $count,
            'total' => $total,
            'min' => $this->aggregateWeight($weights->val(), 'min'),
            'max' => $this->aggregateWeight($weights->val(), 'max'),
            'mean' => $count > 0 ? Num::make($total)->divide($count)->val() : 0.0,
            'values' => $this->ranked(),
        ];
    }

    public function toArray(): array
    {
        return $this->ranked();
    }

    private function entry(string $key, mixed $value, int|float $weight, int $sequence): array
    {
        return [
            'key' => $key,
            'value' => $this->applyValue($value),
            'weight' => $weight,
            'percentage' => 0.0,
            'decay' => (float)$this->decay,
            'timestamp' => $this->now(),
            'sequence' => $sequence,
        ];
    }

    private function sortEntries(): void
    {
        if (!(bool)$this->autosort) {
            return;
        }

        $entries = Arr::make($this->entries())->sort(function (array $left, array $right): int {
            $leftWeight = $this->effectiveWeight($left);
            $rightWeight = $this->effectiveWeight($right);

            if ($leftWeight !== $rightWeight) {
                return $rightWeight <=> $leftWeight;
            }

            return (int)Arr::getPath($left, 'sequence', 0) <=> (int)Arr::getPath($right, 'sequence', 0);
        })->val();

        $this->setEntries($this->withPercentages($entries));
    }

    private function withPercentages(array $entries): array
    {
        $total = 0.0;
        Arr::make($entries)->each(function (mixed $entry) use (&$total): void {
            $total = Num::make($total)->plus($this->effectiveWeight(Arr::toArray($entry)))->val();
        });

        $denominator = $total > 0 ? $total : 1.0;
        $weighted = Arr::make();
        Arr::make($entries)->each(function (mixed $entry) use ($weighted, $denominator): void {
            $entry = Arr::toArray($entry);
            $entry = $this->assignArrayValue(
                $entry,
                'percentage',
                Num::make($this->effectiveWeight($entry))->divide($denominator)->val()
            );
            $weighted->push($entry);
        });

        return $weighted->val();
    }

    private function indexOf(string|int $key): ?int
    {
        $target = $this->normalizeKey($key);
        foreach ($this->entries() as $index => $entry) {
            if (Str::make((string)Arr::getPath(Arr::toArray($entry), 'key', ''))->match($target)) {
                return (int)$index;
            }
        }

        return null;
    }

    private function normalizeKey(string|int|null $key, ?int $sequence = null): string
    {
        if (Val::isNull($key)) {
            $sequence = Val::isNotNull($sequence) ? $sequence : Num::make($this->sequence)->increment()->int();

            return Str::make('item_')->append((string)$sequence)->val();
        }

        return Str::trim((string)$key);
    }

    private function trimToMax(array $entries): array
    {
        $entries = Arr::make($entries)->sort(function (array $left, array $right): int {
            $leftWeight = $this->effectiveWeight($left);
            $rightWeight = $this->effectiveWeight($right);

            if ($leftWeight !== $rightWeight) {
                return $rightWeight <=> $leftWeight;
            }

            return (int)Arr::getPath($left, 'sequence', 0) <=> (int)Arr::getPath($right, 'sequence', 0);
        })->val();

        while (Arr::count($entries) > (int)$this->max) {
            $list = Arr::make($entries);
            $list->delete(Arr::count($entries) - 1);
            $entries = $list->val();
        }

        return Arr::toArray($entries);
    }

    private function effectiveWeight(array $entry): float
    {
        $weight = (float)Arr::getPath($entry, 'weight', 0);
        if (!(bool)$this->decay_enabled) {
            return $weight;
        }

        $age = Num::make($this->now())->minus((int)Arr::getPath($entry, 'timestamp', $this->now()))->val();
        $decay = Num::make((float)Arr::getPath($entry, 'decay', $this->decay))->multiply($age)->val();

        return (float)Num::max(0, Num::make($weight)->minus($decay)->val());
    }

    private function aggregateWeight(array $weights, string $mode): float
    {
        if (!Arr::isNotEmpty($weights)) {
            return 0.0;
        }

        $result = (float)Arr::getPath($weights, 0, 0);
        Arr::make($weights)->each(function (mixed $weight) use (&$result, $mode): void {
            $result = $mode === 'min'
                ? (float)Num::min($result, $weight)
                : (float)Num::max($result, $weight);
        });

        return $result;
    }

    private function nextSequence(): int
    {
        $this->sequence = Num::make($this->sequence)->increment()->int();

        return (int)$this->sequence;
    }

    private function now(): int
    {
        return (int)Date::now()->timestamp();
    }

    private function setEntries(array $entries): void
    {
        if (($this->_data['entries'] ?? null) instanceof IVal) {
            $this->_data['entries']->val($entries);
            return;
        }

        $this->_data['entries'] = $entries;
    }
}
