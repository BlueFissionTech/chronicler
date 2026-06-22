<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Tests\Data;

use BlueFission\Chronicler\Data\StorageObject;
use BlueFission\Data\Schema;
use PHPUnit\Framework\TestCase;

final class StorageObjectTest extends TestCase
{
    public function testStorageObjectTransformsThroughDevelationSchema(): void
    {
        $schema = new Schema([
            'count' => ['type' => 'int', 'required' => true],
        ]);

        $object = new StorageObject('counter', ['count' => '7'], $schema);

        $this->assertTrue($object->validate());
        $this->assertSame(['count' => 7], $object->transform());
    }

    public function testStorageObjectNormalizesObjectPayloadThroughDynamicObject(): void
    {
        $payload = (object)[
            'id' => 'event-1',
            'status' => 'queued',
        ];

        $object = new StorageObject('event', $payload);

        $this->assertSame([
            'id' => 'event-1',
            'status' => 'queued',
        ], $object->payload());
    }
}
