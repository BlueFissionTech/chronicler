<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Tests\Storage;

use BlueFission\Chronicler\Storage\Structures\BloomFilter;
use BlueFission\Chronicler\Storage\Structures\SkipList;
use BlueFission\Chronicler\Storage\Structures\SpatialPoint;
use BlueFission\Chronicler\Storage\Structures\WeightedCollection;
use BlueFission\Deq;
use BlueFission\Dict;
use BlueFission\IVal;
use BlueFission\Pile;
use BlueFission\Pri;
use BlueFission\Set;
use BlueFission\Val;
use BlueFission\Vec;
use PHPUnit\Framework\TestCase;

final class AdvancedStructuresTest extends TestCase
{
    public function testBloomFilterRecognizesInsertedItems(): void
    {
        $filter = new BloomFilter(128, 4);
        $filter->add('cache-key');

        $this->assertTrue($filter->mightContain('cache-key'));
        $this->assertFalse($filter->mightContain('definitely-not-present'));
    }

    public function testSkipListKeepsOrderedKeysAndSearchesValues(): void
    {
        $list = new SkipList();
        $list->insert('b', 2)->insert('a', 1)->insert('c', 3);

        $this->assertSame(['a', 'b', 'c'], $list->keys());
        $this->assertSame(2, $list->search('b'));
        $this->assertTrue($list->delete('b'));
        $this->assertNull($list->search('b'));
    }

    public function testSpatialPointExportsGeoJsonAndDistance(): void
    {
        $a = new SpatialPoint(40.7128, -74.0060);
        $b = new SpatialPoint(40.7138, -74.0060);

        $this->assertSame('Point', $a->toGeoJson()['type']);
        $this->assertGreaterThan(100, $a->distanceTo($b));
    }

    public function testPriorityQueueOrdersByPriorityAndStableSequence(): void
    {
        $queue = new Pri();
        $this->assertInstanceOf(IVal::class, $queue);
        $this->assertInstanceOf(Val::class, $queue);
        $this->assertInstanceOf(Pri::class, Pri::make());
        $this->assertStorageMatches($queue, '\Ds\PriorityQueue');

        $queue
            ->insert('low', 1)
            ->insert('high', 10)
            ->insert('also-high', 10);

        $this->assertSame('high', $queue->peek());
        $this->assertSame(3, $queue->count());
        $this->assertSame('high', $queue->extract());
        $this->assertSame('also-high', $queue->extract());
        $this->assertSame('low', $queue->extract());
        $this->assertNull($queue->extract());
    }

    public function testWeightedCollectionRanksAndReinforcesItems(): void
    {
        $collection = new WeightedCollection();
        $collection
            ->add('alpha', 'a', 2)
            ->add('beta', 'b', 1);

        $this->assertSame('alpha', $collection->get('a'));
        $this->assertSame(3, $collection->weight('a'));

        $collection->weight('b', 5);

        $this->assertSame('beta', $collection->values()[0]);
        $this->assertSame(2, $collection->stats()['count']);
        $this->assertTrue($collection->remove('a'));
        $this->assertFalse($collection->has('a'));
    }

    public function testWeightedCollectionPreservesOrganizedCollectionSurface(): void
    {
        $collection = new WeightedCollection();
        $collection
            ->setMax(5000)
            ->setDecay(true, 0.001)
            ->setSort(true)
            ->add('value1', 'key1', 1)
            ->add('value2', 'key2', 2);

        $collection->sort();
        $values = [];
        foreach ($collection as $entry) {
            $values[] = $entry['value'];
        }

        $this->assertSame(['value2', 'value1'], $values);

        $collection->optimize(1.5);
        $this->assertSame('value2', $collection->get('key2', false));
        $this->assertNull($collection->get('key1', false));

        $tokens = ['A', 'B', 'C', 'D', 'E'];
        $weights = [600, 470, 170, 430, 300];
        $weighted = new WeightedCollection();
        foreach ($tokens as $index => $token) {
            $weighted->add($token, $token, $weights[$index]);
        }

        $stats = $weighted->stats();
        $data = $weighted->data();

        $this->assertArrayHasKey('mean1', $stats);
        $this->assertSame(5, $data['count']);
        $this->assertSame(1970.0, $data['total']);
        $this->assertSame(600.0, $data['max']);
        $this->assertSame(170.0, $data['min']);
        $this->assertSame(394.0, $data['mean1']);
        $this->assertSame(21704, (int)$data['popvariance1']);
        $this->assertSame(147, (int)$data['popstd1']);
        $this->assertSame(27130, (int)$data['variance1']);
        $this->assertSame(164, (int)$data['std1']);
        $this->assertArrayHasKey('A', $data['values']);
        $this->assertSame('A', $weighted['A']['value']);
        $this->assertJson((string)$weighted);
    }

    public function testVectorSupportsIndexedListOperations(): void
    {
        $vec = new Vec(['alpha', 'gamma']);
        $this->assertInstanceOf(IVal::class, $vec);
        $this->assertInstanceOf(Val::class, $vec);
        $this->assertInstanceOf(Vec::class, Vec::make());
        $this->assertStorageMatches($vec, '\Ds\Vector');

        $vec->insert(1, 'beta')->set(2, 'delta')->push('epsilon');

        $this->assertSame(['alpha', 'beta', 'delta', 'epsilon'], $vec->values());
        $this->assertSame('beta', $vec->get(1));
        $this->assertTrue($vec->contains('delta'));
        $this->assertSame($vec, $vec->remove(1));
        $this->assertSame(['alpha', 'delta', 'epsilon'], $vec->toArray());
    }

    public function testDequeSupportsBothEnds(): void
    {
        $deq = new Deq(['middle']);
        $this->assertInstanceOf(IVal::class, $deq);
        $this->assertInstanceOf(Val::class, $deq);
        $this->assertInstanceOf(Deq::class, Deq::make());
        $this->assertStorageMatches($deq, '\Ds\Deque');

        $deq->pushFront('front')->pushBack('back');

        $this->assertSame('front', $deq->peekFront());
        $this->assertSame('back', $deq->peekBack());
        $this->assertSame('front', $deq->popFront());
        $this->assertSame('back', $deq->popBack());
        $this->assertSame(['middle'], $deq->values());
    }

    public function testPileSupportsLastInFirstOutAccess(): void
    {
        $pile = new Pile();
        $this->assertInstanceOf(IVal::class, $pile);
        $this->assertInstanceOf(Val::class, $pile);
        $this->assertInstanceOf(Pile::class, Pile::make());
        $this->assertStorageMatches($pile, '\Ds\Stack');

        $pile->push('first')->push('second');

        $this->assertSame('second', $pile->peek());
        $this->assertSame('second', $pile->pop());
        $this->assertSame('first', $pile->pop());
        $this->assertNull($pile->pop());
    }

    public function testSetKeepsUniqueStrictValues(): void
    {
        $set = new Set(['1', '1', 1]);
        $this->assertInstanceOf(IVal::class, $set);
        $this->assertInstanceOf(Val::class, $set);
        $this->assertInstanceOf(Set::class, Set::make());
        $this->assertStorageMatches($set, '\Ds\Set');

        $set->add('2')->add('2');

        $this->assertSame(['1', 1, '2'], $set->values());
        $this->assertTrue($set->has(1));
        $this->assertSame($set, $set->remove('1'));
        $this->assertSame([1, '2'], $set->values());
    }

    public function testDictionaryStoresKeyedValues(): void
    {
        $dict = new Dict(['alpha' => 1]);
        $this->assertInstanceOf(IVal::class, $dict);
        $this->assertInstanceOf(Val::class, $dict);
        $this->assertInstanceOf(Dict::class, Dict::make());
        $this->assertStorageMatches($dict, '\Ds\Map');

        $dict->set('beta', 2)->put('gamma', 3);

        $this->assertTrue($dict->has('beta'));
        $this->assertSame(2, $dict->get('beta'));
        $this->assertSame(['alpha', 'beta', 'gamma'], $dict->keys());
        $this->assertSame($dict, $dict->remove('alpha'));
        $this->assertSame(['beta' => 2, 'gamma' => 3], $dict->toArray());
    }

    private function assertStorageMatches(IVal $value, string $dsClass): void
    {
        $ref = new \ReflectionClass($value);
        $property = $ref->getProperty('_data');
        $property->setAccessible(true);
        $data = $property->getValue($value);

        if (class_exists($dsClass)) {
            $this->assertInstanceOf($dsClass, $data);
            return;
        }

        $this->assertIsArray($data);
    }
}
