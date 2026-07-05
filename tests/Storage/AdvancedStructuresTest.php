<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Tests\Storage;

use BlueFission\Chronicler\Storage\Structures\BloomFilter;
use BlueFission\Chronicler\Storage\Structures\PriorityQueue;
use BlueFission\Chronicler\Storage\Structures\SkipList;
use BlueFission\Chronicler\Storage\Structures\SpatialPoint;
use BlueFission\Chronicler\Storage\Structures\WeightedCollection;
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
        $queue = new PriorityQueue();
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
}
