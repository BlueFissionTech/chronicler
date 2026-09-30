<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Tests\Storage;

use BlueFission\Chronicler\Storage\Evidence\EvidenceLedger;
use BlueFission\Chronicler\Storage\Evidence\EvidenceRecord;
use BlueFission\Chronicler\Storage\Evidence\EvidenceTombstone;
use BlueFission\Chronicler\Storage\Evidence\SqliteEvidenceStore;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EvidenceLifecycleTest extends TestCase
{
    public function testCanonicalDigestIsStableAndTamperingIsRejected(): void
    {
        $record = $this->raw('evidence-1', ['b' => 2, 'a' => 1]);
        $same = $this->raw('evidence-1', ['a' => 1, 'b' => 2]);

        self::assertSame($record->digest(), $same->digest());

        $tampered = $record->toArray();
        $tampered['payload']['a'] = 99;

        $this->expectException(InvalidArgumentException::class);
        EvidenceRecord::fromArray($tampered);
    }

    public function testDerivedEvidenceRetainsExactParentAndCannotEraseProvenance(): void
    {
        $ledger = new EvidenceLedger();
        $raw = $this->raw('raw-1', ['text' => 'source']);
        $ledger->append($raw);

        $derived = EvidenceRecord::capture(
            'derived-1',
            'derived',
            ['system' => 'summarizer'],
            ['summary' => 'source'],
            ['operation' => 'summary-v1'],
            [['id' => $raw->id(), 'digest' => $raw->digest()]],
            'observed',
            'internal',
            'current',
            '2026-09-30T12:00:00+00:00'
        );
        $ledger->append($derived);

        self::assertSame($raw->digest(), $ledger->record('derived-1')?->parents()[0]['digest']);
        self::assertSame('none', $ledger->record('derived-1')?->toArray()['authority']);
        self::assertSame($raw, $ledger->record('raw-1'));
    }

    public function testQuarantineAndInvalidationAreAppendOnlyAndBlockNewDerivations(): void
    {
        $ledger = new EvidenceLedger();
        $raw = $this->raw('raw-1');
        $ledger->append($raw);
        $ledger->quarantine('raw-1', 'untrusted source');
        $ledger->invalidate('raw-1', 'digest investigation');

        self::assertSame('invalidated', $ledger->state('raw-1'));
        self::assertNotNull($ledger->record('raw-1'));
        self::assertNull($ledger->active('raw-1'));
        self::assertCount(3, $ledger->history('raw-1')['events']);

        $this->expectException(InvalidArgumentException::class);
        $ledger->append(EvidenceRecord::capture(
            'derived-1',
            'derived',
            ['system' => 'summarizer'],
            ['summary' => 'unsafe'],
            ['operation' => 'summary-v1'],
            [['id' => $raw->id(), 'digest' => $raw->digest()]],
            recordedAt: '2026-09-30T12:00:00+00:00'
        ));
    }

    public function testTombstoneCannotWithdrawAnUnrelatedProjectionOrResurrect(): void
    {
        $ledger = new EvidenceLedger();
        $target = $this->raw('target-1');
        $unrelated = $this->raw('unrelated-1');
        $ledger->append($target);
        $ledger->append($unrelated);

        $tombstone = EvidenceTombstone::create(
            'tombstone-1',
            $target,
            'source withdrew evidence',
            'source-system',
            1,
            'withdraw-target-1',
            '2026-09-30T12:10:00+00:00'
        );

        try {
            $ledger->withdraw('unrelated-1', $tombstone);
            self::fail('An unrelated projection must not accept the tombstone.');
        } catch (InvalidArgumentException) {
            self::assertSame('active', $ledger->state('unrelated-1'));
        }

        $ledger->withdraw('target-1', $tombstone);
        $ledger->withdraw('target-1', $tombstone);

        self::assertSame('withdrawn', $ledger->state('target-1'));
        self::assertNull($ledger->active('target-1'));
        self::assertNotNull($ledger->record('target-1'));
        self::assertSame('active', $ledger->state('unrelated-1'));
        self::assertCount(1, $ledger->history('target-1')['tombstones']);

        $outOfOrder = EvidenceTombstone::create(
            'tombstone-2',
            $target,
            'duplicate withdrawal',
            'source-system',
            1,
            'withdraw-target-1-again',
            '2026-09-30T12:11:00+00:00'
        );
        $this->expectException(InvalidArgumentException::class);
        $ledger->withdraw('target-1', $outOfOrder);
    }

    public function testOptionalDevElationSqliteStoreSurvivesCloseAndReopen(): void
    {
        if (!class_exists('SQLite3')) {
            self::markTestSkipped('SQLite3 is optional.');
        }

        $path = tempnam(sys_get_temp_dir(), 'chronicler-evidence-');
        self::assertIsString($path);

        try {
            $ledger = new EvidenceLedger(new SqliteEvidenceStore($path));
            $record = $this->raw('persisted-1');
            $ledger->append($record);
            $ledger->quarantine('persisted-1', 'manual review');
            $ledger->withdraw('persisted-1', EvidenceTombstone::create(
                'persisted-tombstone-1',
                $record,
                'source withdrawal',
                'source-system',
                1,
                'persisted-withdrawal-1',
                '2026-09-30T12:10:00+00:00'
            ));
            $ledger->close();

            $restored = new EvidenceLedger(new SqliteEvidenceStore($path));
            self::assertSame($record->digest(), $restored->record('persisted-1')?->digest());
            self::assertSame('withdrawn', $restored->state('persisted-1'));
            self::assertCount(3, $restored->history('persisted-1')['events']);
            self::assertCount(1, $restored->history('persisted-1')['tombstones']);
            $restored->close();
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function raw(string $id, array $payload = ['value' => 7]): EvidenceRecord
    {
        return EvidenceRecord::capture(
            $id,
            'raw',
            ['system' => 'fixture', 'record' => $id],
            $payload,
            ['cursor' => 'fixture:' . $id],
            trust: 'observed',
            freshness: 'current',
            recordedAt: '2026-09-30T12:00:00+00:00'
        );
    }
}
