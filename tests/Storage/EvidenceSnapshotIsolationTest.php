<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Tests\Storage;

use BlueFission\Chronicler\Storage\Evidence\CanonicalJson;
use BlueFission\Chronicler\Storage\Evidence\EvidenceLedger;
use BlueFission\Chronicler\Storage\Evidence\EvidenceLifecycleEvent;
use BlueFission\Chronicler\Storage\Evidence\EvidenceRecord;
use BlueFission\Chronicler\Storage\Evidence\EvidenceTombstone;
use BlueFission\Str;
use InvalidArgumentException;
use JsonSerializable;
use PHPUnit\Framework\TestCase;
use stdClass;

final class EvidenceSnapshotIsolationTest extends TestCase
{
    public function testCaptureDetachesPayloadAndMetadataReferences(): void
    {
        $text = 'source';
        $system = 'fixture';
        $cursor = 'fixture:raw';
        $record = EvidenceRecord::capture(
            'raw', 'raw', ['system' => &$system, 'record' => 'raw'],
            ['nested' => ['text' => &$text]], ['cursor' => &$cursor],
            recordedAt: '2026-10-20T12:00:00+00:00'
        );
        $bytes = CanonicalJson::encode($record->toArray());
        $digest = $record->digest();
        $ledger = new EvidenceLedger();
        $ledger->append($record);

        $text = 'changed';
        $system = 'other';
        $cursor = 'other:cursor';
        $export = $record->toArray();
        $export['payload']['nested']['text'] = 'export-change';
        $export['source']['system'] = 'export-system';

        self::assertSame('source', $record->payload()['nested']['text']);
        self::assertSame('fixture', $record->source()['system']);
        self::assertSame('fixture:raw', $record->provenance()['cursor']);
        self::assertSame($bytes, CanonicalJson::encode($record->toArray()));
        self::assertSame($digest, $record->digest());
        self::assertTrue($record->verifyDigest());
        self::assertSame($bytes, CanonicalJson::encode($ledger->history('raw')['record']->toArray()));
    }

    public function testImportsDetachIdentityAndParentReferences(): void
    {
        $parent = $this->raw('parent');
        $parentId = $parent->id();
        $parentDigest = $parent->digest();
        $derived = EvidenceRecord::capture(
            'derived', 'derived', ['system' => 'fixture'], ['text' => 'summary'],
            ['operation' => 'summarize'], [['id' => &$parentId, 'digest' => &$parentDigest]],
            recordedAt: '2026-10-20T12:00:00+00:00'
        );
        $wire = $derived->toArray();
        $identity = 'derived';
        $wire['id'] = &$identity;
        $imported = EvidenceRecord::fromArray($wire);
        $bytes = CanonicalJson::encode($imported->toArray());

        $identity = 'other';
        $parentId = 'other-parent';
        $parentDigest = 'sha256:' . str_repeat('0', 64);
        $export = $imported->parents();
        $export[0]['id'] = 'export-parent';

        self::assertSame('derived', $imported->id());
        self::assertSame($parent->id(), $derived->parents()[0]['id']);
        self::assertSame($parent->digest(), $derived->parents()[0]['digest']);
        self::assertSame($parent->id(), $imported->parents()[0]['id']);
        self::assertSame($bytes, CanonicalJson::encode($imported->toArray()));
        self::assertTrue($derived->verifyDigest());
        self::assertTrue($imported->verifyDigest());
    }

    public function testEventAndTombstoneImportsDetachReferences(): void
    {
        $record = $this->raw('target');
        $wire = EvidenceLifecycleEvent::create(
            $record, 'quarantined', 'review', 'fixture', 1, '2026-10-20T12:00:00+00:00'
        )->toArray();
        $reason = 'review';
        $wire['reason'] = &$reason;
        $event = EvidenceLifecycleEvent::fromArray($wire);
        $eventBytes = CanonicalJson::encode($event->toArray());
        $reason = 'changed';
        $eventExport = $event->toArray();
        $eventExport['reason'] = 'export-change';
        self::assertSame('review', $event->reason());
        self::assertSame($eventBytes, CanonicalJson::encode($event->toArray()));
        self::assertSame($event->digest(), CanonicalJson::digest($event->toArray()));

        $replacement = $this->raw('replacement');
        $replacementId = $replacement->id();
        $tombstone = EvidenceTombstone::create(
            'withdrawal', $record, 'withdraw', 'fixture', 1, 'withdrawal-key',
            '2026-10-20T12:00:00+00:00', replacement: [
                'id' => &$replacementId, 'digest' => $replacement->digest(),
            ]
        );
        $tombstoneWire = $tombstone->toArray();
        $issuer = 'fixture';
        $tombstoneWire['issuer'] = &$issuer;
        $imported = EvidenceTombstone::fromArray($tombstoneWire);
        $bytes = CanonicalJson::encode($imported->toArray());
        $issuer = 'changed';
        $replacementId = 'changed';
        $export = $imported->toArray();
        $export['replacement']['id'] = 'export-change';
        self::assertSame('fixture', $imported->issuer());
        self::assertSame('replacement', $tombstone->replacement()['id']);
        self::assertSame('replacement', $imported->replacement()['id']);
        self::assertSame($bytes, CanonicalJson::encode($imported->toArray()));
        self::assertSame($imported->digest(), CanonicalJson::digest($imported->toArray()));
    }

    public function testMutableObjectsAreRejectedWithoutCallingSerializers(): void
    {
        $object = new class implements JsonSerializable {
            public int $calls = 0;

            public function jsonSerialize(): mixed
            {
                $this->calls++;
                return ['text' => 'source'];
            }
        };
        foreach ([$object, new stdClass(), Str::make('source')] as $value) {
            $this->assertRejected(static fn () => CanonicalJson::encode(['nested' => [$value]]));
            $this->assertRejected(fn () => $this->raw('object', ['nested' => [$value]]));
            $wire = $this->raw('import')->toArray();
            $wire['payload'] = ['nested' => [$value]];
            $this->assertRejected(static fn () => EvidenceRecord::fromArray($wire));
        }
        self::assertSame(0, $object->calls);
    }

    public function testSnapshotPreservesNativeTypesOrderingAndCanonicalBytes(): void
    {
        $input = ['z' => 1.0, 'a' => [true, null, 0, -0.0, 'é', '/']];
        $snapshot = CanonicalJson::snapshot($input);
        self::assertSame(['z', 'a'], array_keys($snapshot));
        self::assertSame($input, $snapshot);
        self::assertSame('{"a":[true,null,0,-0.0,"é","/"],"z":1.0}', CanonicalJson::encode($snapshot));
    }

    public function testRecursiveArraysNonfiniteNumbersAndResourcesAreRejected(): void
    {
        $recursive = [];
        $recursive['self'] = &$recursive;
        $this->assertRejected(static fn () => CanonicalJson::snapshot($recursive));
        unset($recursive['self']);
        foreach ([INF, -INF, NAN] as $value) {
            $this->assertRejected(static fn () => CanonicalJson::snapshot(['value' => $value]));
        }
        $resource = fopen('php://memory', 'r+');
        try {
            $this->assertRejected(static fn () => CanonicalJson::snapshot(['value' => $resource]));
        } finally {
            fclose($resource);
        }
    }

    private function raw(string $id, array $payload = ['text' => 'source']): EvidenceRecord
    {
        return EvidenceRecord::capture(
            $id, 'raw', ['system' => 'fixture', 'record' => $id], $payload,
            ['cursor' => 'fixture:' . $id], recordedAt: '2026-10-20T12:00:00+00:00'
        );
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
            return;
        }
        self::fail('Mutable or non-JSON input was accepted.');
    }
}
