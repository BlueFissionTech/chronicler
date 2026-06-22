<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Tests\Data;

use BlueFission\Chronicler\Data\StorageObject;
use BlueFission\Chronicler\Data\StoragePacket;
use PHPUnit\Framework\TestCase;

final class StoragePacketTest extends TestCase
{
    public function testStoragePacketCarriesReadOnlyTraceablePayload(): void
    {
        $packet = new StoragePacket(
            StoragePacket::TYPE_EVENT,
            ['id' => 'event-1'],
            ['system' => 'storage-adapter', 'record' => 'orders'],
            ['cursor' => 'orders:0:42'],
            0.93,
            'internal',
            ['scope' => 'read-model', 'can_write' => true]
        );

        $packed = $packet->toArray();

        $this->assertSame(StoragePacket::TYPE_EVENT, $packed['type']);
        $this->assertSame(['id' => 'event-1'], $packed['payload']);
        $this->assertSame('orders:0:42', $packed['provenance']['cursor']);
        $this->assertFalse($packed['authority']['can_write']);
        $this->assertSame([], $packed['diagnostics']);
    }

    public function testStoragePacketReportsMissingTraceabilityMetadata(): void
    {
        $packet = (new StoragePacket(StoragePacket::TYPE_EVIDENCE, ['value' => 7]))
            ->diagnoseTraceability();

        $diagnostics = $packet->diagnostics();

        $this->assertSame('missing_source', $diagnostics[0]['code']);
        $this->assertSame('missing_provenance', $diagnostics[1]['code']);
        $this->assertSame('missing_authority', $diagnostics[2]['code']);
        $this->assertFalse($packet->authority()['can_write']);
    }

    public function testStoragePacketCanBeBuiltFromStorageObjectMetadata(): void
    {
        $object = new StorageObject('feature', ['score' => '0.75'], null, [
            'source' => ['system' => 'query-builder'],
            'provenance' => ['query' => 'feature-score'],
            'confidence' => 0.75,
            'visibility' => 'private',
            'authority' => ['scope' => 'projection'],
        ]);

        $packet = StoragePacket::fromStorageObject($object, StoragePacket::TYPE_FEATURE);

        $this->assertSame(StoragePacket::TYPE_FEATURE, $packet->toArray()['type']);
        $this->assertSame(['score' => '0.75'], $packet->payload());
        $this->assertSame('query-builder', $packet->source()['system']);
        $this->assertFalse($packet->authority()['can_write']);
    }
}
