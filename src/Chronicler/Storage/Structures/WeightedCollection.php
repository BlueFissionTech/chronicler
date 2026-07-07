<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Structures;

use ArrayAccess;
use ArrayIterator;
use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\Collections\Collection;
use BlueFission\DataTypes;
use BlueFission\Date;
use BlueFission\IVal;
use BlueFission\Net\HTTP;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;
use IteratorAggregate;
use Traversable;

final class WeightedCollection extends Obj implements ArrayAccess, IteratorAggregate
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

    public function setSort(bool $sorts): self
    {
        $this->autosort = $sorts;

        if ($sorts) {
            $this->sortEntries();
        }

        return $this;
    }

    public function setMax(int $max): self
    {
        $this->max = (int)Num::max(1, $max);
        $this->setEntries($this->trimToMax($this->entries()));
        $this->sortEntries();

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

    public function setDecay(bool $decays, ?float $rate = null): self
    {
        return $this->decay($decays, $rate);
    }

    public function optimize(int|float $tolerance = 10, array $noise = []): self
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

    public function sort(?callable $callback = null): Collection
    {
        if ($callback) {
            $entries = Arr::make($this->entries())->sort($callback)->val();
            $this->setEntries($this->withPercentages($entries));
        } else {
            $this->sortEntries();
        }

        return new Collection($this->keyedEntries());
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
        $weightValues = $weights->val();
        $mean = $count > 0 ? Num::make($total)->divide($count)->val() : 0.0;
        $variance = $this->variance($weightValues, $mean, true);
        $popVariance = $this->variance($weightValues, $mean, false);
        $std = Num::make($variance)->sqrt()->val();
        $popStd = Num::make($popVariance)->sqrt()->val();

        return [
            'count' => $count,
            'total' => $total,
            'min' => $this->aggregateWeight($weights->val(), 'min'),
            'max' => $this->aggregateWeight($weights->val(), 'max'),
            'mode' => $this->mode($weightValues),
            'median' => $this->median($weightValues),
            'mean' => $mean,
            'mean1' => $mean,
            'mean2' => $mean,
            'mean3' => $mean,
            'variance1' => $variance,
            'variance2' => $variance,
            'variance3' => $variance,
            'popvariance1' => $popVariance,
            'popvariance2' => $popVariance,
            'popvariance3' => $popVariance,
            'std1' => $std,
            'std2' => $std,
            'std3' => $std,
            'popstd1' => $popStd,
            'popstd2' => $popStd,
            'popstd3' => $popStd,
            'cv1' => $mean > 0 ? Num::make($std)->divide($mean)->val() : 0.0,
            'cv2' => $mean > 0 ? Num::make($std)->divide($mean)->val() : 0.0,
            'cv3' => $mean > 0 ? Num::make($std)->divide($mean)->val() : 0.0,
            'outliers' => 0,
            'super_outliers' => 0,
            'values' => $this->ranked(),
        ];
    }

    public function data(): array
    {
        $stats = $this->stats();
        $stats['values'] = $this->keyedEntries();

        return $stats;
    }

    public function toArray(): array
    {
        return $this->ranked();
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->keyedEntries());
    }

    public function offsetExists(mixed $offset): bool
    {
        $key = $this->offsetKey($offset);

        return Val::isNotNull($key) ? $this->has($key) : false;
    }

    public function offsetGet(mixed $offset): mixed
    {
        $key = $this->offsetKey($offset);
        if (Val::isNull($key)) {
            return null;
        }

        return $this->entryFor($key);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $key = $this->offsetKey($offset);

        if (Arr::is($value) && Arr::hasKey($value, 'value')) {
            $weight = (int)Arr::getPath($value, 'weight', 1);
            $this->add(Arr::getPath($value, 'value'), $key, $weight);
            return;
        }

        $this->add($value, $key);
    }

    public function offsetUnset(mixed $offset): void
    {
        $key = $this->offsetKey($offset);
        if (Val::isNotNull($key)) {
            $this->remove($key);
        }
    }

    public function __toString(): string
    {
        return HTTP::jsonEncode($this->data());
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

    private function keyedEntries(): array
    {
        $entries = [];
        Arr::make($this->ranked())->each(function (mixed $entry) use (&$entries): void {
            $entry = Arr::toArray($entry);
            $key = Arr::getPath($entry, 'key');
            if (Val::isNotNull($key)) {
                $entries[(string)$key] = $entry;
            }
        });

        return $entries;
    }

    private function entryFor(string|int $key): ?array
    {
        $index = $this->indexOf($key);
        if (Val::isNull($index)) {
            return null;
        }

        return Arr::toArray(Arr::getPath($this->entries(), $index, []));
    }

    private function variance(array $weights, int|float $mean, bool $sample): float
    {
        $count = Arr::count($weights);
        if ($count < 1 || ($sample && $count < 2)) {
            return 0.0;
        }

        $sum = 0.0;
        Arr::make($weights)->each(function (mixed $weight) use (&$sum, $mean): void {
            $sum = Num::make($sum)->plus(Num::make($weight)->minus($mean)->pow(2)->val())->val();
        });

        $denominator = $sample ? Num::make($count)->minus(1)->val() : $count;

        return (float)Num::make($sum)->divide($denominator)->val();
    }

    private function median(array $weights): float
    {
        $count = Arr::count($weights);
        if ($count === 0) {
            return 0.0;
        }

        $weights = Arr::make($weights)->sort()->values()->val();
        $middle = (int)floor($count / 2);

        if ($count % 2 === 1) {
            return (float)Arr::getPath($weights, $middle, 0);
        }

        return (float)Num::make(Arr::getPath($weights, $middle - 1, 0))
            ->plus(Arr::getPath($weights, $middle, 0))
            ->divide(2)
            ->val();
    }

    private function mode(array $weights): float
    {
        if (!Arr::isNotEmpty($weights)) {
            return 0.0;
        }

        $counts = [];
        Arr::make($weights)->each(function (mixed $weight) use (&$counts): void {
            $key = (string)$weight;
            $counts[$key] = Arr::getPath($counts, $key, 0) + 1;
        });

        $mode = (float)Arr::getPath($weights, 0, 0);
        $max = 0;
        Arr::make($counts)->each(function (mixed $count, string|int $weight) use (&$mode, &$max): void {
            if ($count > $max) {
                $max = (int)$count;
                $mode = (float)$weight;
            }
        });

        return $mode;
    }

    private function offsetKey(mixed $key): string|int|null
    {
        if (Str::is($key)) {
            return (string)$key;
        }

        if (Num::is($key)) {
            return (int)$key;
        }

        return null;
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
