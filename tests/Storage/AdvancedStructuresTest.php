<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Tests\Storage;

use BlueFission\Chronicler\Storage\Structures\BloomFilter;
use BlueFission\Chronicler\Storage\Structures\SkipList;
use BlueFission\Chronicler\Storage\Structures\SpatialPoint;
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
}
