<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Tests\Storage;

use BlueFission\Chronicler\Storage\Event\MessageEnvelope;
use BlueFission\Chronicler\Storage\Event\OffsetTracker;
use PHPUnit\Framework\TestCase;

final class EventStructuresTest extends TestCase
{
    public function testMessageEnvelopePacksKafkaShape(): void
    {
        $message = (new MessageEnvelope('orders', ['id' => 10], 'order-10'))
            ->header('trace', 'abc');

        $packed = $message->pack();

        $this->assertSame('orders', $packed['topic']);
        $this->assertSame('order-10', $packed['partition_key']);
        $this->assertSame('abc', $packed['headers']['trace']);
        $this->assertSame(['id' => 10], $packed['payload']);
    }

    public function testOffsetTrackerStoresOffsetsByTopicAndPartition(): void
    {
        $tracker = new OffsetTracker();
        $tracker->commit('orders', 0, 42);

        $this->assertSame(42, $tracker->offset('orders', 0));
        $this->assertSame(8, $tracker->lag('orders', 0, 50));
        $this->assertNull($tracker->lag('orders', 1, 50));
    }
}
