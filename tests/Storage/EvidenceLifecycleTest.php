<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Tests\Storage;

use BlueFission\Chronicler\Storage\Evidence\CanonicalJson;
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

    public function testTypedAccessorsPreserveCanonicalShapeAndVerifiedTrustRequiresProof(): void
    {
        $record = $this->raw('accessor-1');

        self::assertSame(1, $record->version());
        self::assertSame('chronicler-evidence-v1', $record->canonicalization());
        self::assertSame('fixture', $record->source()['system']);
        self::assertSame(['value' => 7], $record->payload());
        self::assertSame('fixture:accessor-1', $record->provenance()['cursor']);
        self::assertSame('observed', $record->trust());
        self::assertSame('internal', $record->sensitivity());
        self::assertSame('current', $record->freshness());
        self::assertSame('none', $record->authority());
        self::assertSame('2026-09-30T12:00:00+00:00', $record->recordedAt());

        $unproved = $record->toArray();
        $unproved['trust'] = 'verified';
        $unproved['digest'] = CanonicalJson::digest($unproved);
        $this->assertInvalid(static fn (): EvidenceRecord => EvidenceRecord::fromArray($unproved));

        $verified = EvidenceRecord::capture(
            'verified-1',
            'raw',
            ['system' => 'fixture', 'record' => 'verified-1'],
            ['value' => 8],
            [
                'cursor' => 'fixture:verified-1',
                'verification' => [
                    'state' => 'verified',
                    'issuer' => 'fixture-verifier',
                    'proof' => 'proof:verified-1',
                ],
            ],
            trust: 'verified',
            freshness: 'current',
            recordedAt: '2026-09-30T12:00:00+00:00'
        );
        self::assertSame('verified', $verified->trust());
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

    public function testTombstoneReplayBindsFullDigestAndIdsCannotBeReused(): void
    {
        $ledger = new EvidenceLedger();
        $target = $this->raw('replay-target');
        $ledger->append($target);

        $original = EvidenceTombstone::create(
            'replay-tombstone',
            $target,
            'source withdrawal',
            'source-system',
            1,
            'replay-key',
            '2026-09-30T12:10:00+00:00'
        );
        $ledger->withdraw($target->id(), $original);
        $ledger->withdraw($target->id(), $original);

        $changedReplay = EvidenceTombstone::create(
            'replay-tombstone',
            $target,
            'changed reason',
            'source-system',
            1,
            'replay-key',
            '2026-09-30T12:10:00+00:00'
        );
        $this->assertInvalid(function () use ($ledger, $target, $changedReplay): void {
            $ledger->withdraw($target->id(), $changedReplay);
        });

        $reusedId = EvidenceTombstone::create(
            'replay-tombstone',
            $target,
            'different event',
            'source-system',
            2,
            'different-key',
            '2026-09-30T12:11:00+00:00'
        );
        $this->assertInvalid(function () use ($ledger, $target, $reusedId): void {
            $ledger->withdraw($target->id(), $reusedId);
        });

        $reusedKey = EvidenceTombstone::create(
            'different-tombstone-id',
            $target,
            'different event',
            'source-system',
            2,
            'replay-key',
            '2026-09-30T12:12:00+00:00'
        );
        $this->assertInvalid(function () use ($ledger, $target, $reusedKey): void {
            $ledger->withdraw($target->id(), $reusedKey);
        });
        self::assertCount(1, $ledger->history($target->id())['tombstones']);
    }

    public function testSupersedesAndReplacementReferencesMustResolveExactly(): void
    {
        $ledger = new EvidenceLedger();
        $target = $this->raw('lineage-target');
        $replacement = $this->raw('lineage-replacement');
        $other = $this->raw('lineage-other');
        $crossScope = $this->raw('lineage-cross-scope', scope: 'other');
        foreach ([$target, $replacement, $other, $crossScope] as $record) {
            $ledger->append($record);
        }

        $first = EvidenceTombstone::create(
            'lineage-first', $target, 'withdrawal', 'source-system', 1, 'lineage-key-1',
            '2026-09-30T12:10:00+00:00'
        );
        $ledger->withdraw($target->id(), $first);

        $unknownSupersedes = EvidenceTombstone::create(
            'lineage-unknown', $target, 'correction', 'source-system', 2, 'lineage-key-unknown',
            '2026-09-30T12:11:00+00:00', 'missing-tombstone'
        );
        $this->assertInvalid(function () use ($ledger, $target, $unknownSupersedes): void {
            $ledger->withdraw($target->id(), $unknownSupersedes);
        });

        $otherTombstone = EvidenceTombstone::create(
            'lineage-other-tombstone', $other, 'other withdrawal', 'source-system', 1, 'lineage-other-key',
            '2026-09-30T12:11:00+00:00'
        );
        $ledger->withdraw($other->id(), $otherTombstone);
        $crossTargetSupersedes = EvidenceTombstone::create(
            'lineage-cross-target', $target, 'correction', 'source-system', 2, 'lineage-key-cross-target',
            '2026-09-30T12:12:00+00:00', $otherTombstone->id()
        );
        $this->assertInvalid(function () use ($ledger, $target, $crossTargetSupersedes): void {
            $ledger->withdraw($target->id(), $crossTargetSupersedes);
        });

        $unknownReplacement = EvidenceTombstone::create(
            'lineage-unknown-replacement', $target, 'correction', 'source-system', 2, 'lineage-key-unknown-replacement',
            '2026-09-30T12:13:00+00:00', $first->id(),
            ['id' => 'missing-record', 'digest' => $replacement->digest()]
        );
        $this->assertInvalid(function () use ($ledger, $target, $unknownReplacement): void {
            $ledger->withdraw($target->id(), $unknownReplacement);
        });

        $mismatchedReplacement = EvidenceTombstone::create(
            'lineage-mismatched-replacement', $target, 'correction', 'source-system', 2, 'lineage-key-mismatch',
            '2026-09-30T12:14:00+00:00', $first->id(),
            ['id' => $replacement->id(), 'digest' => $target->digest()]
        );
        $this->assertInvalid(function () use ($ledger, $target, $mismatchedReplacement): void {
            $ledger->withdraw($target->id(), $mismatchedReplacement);
        });

        $crossScopeReplacement = EvidenceTombstone::create(
            'lineage-cross-scope-replacement', $target, 'correction', 'source-system', 2, 'lineage-key-cross-scope',
            '2026-09-30T12:15:00+00:00', $first->id(),
            ['id' => $crossScope->id(), 'digest' => $crossScope->digest()]
        );
        $this->assertInvalid(function () use ($ledger, $target, $crossScopeReplacement): void {
            $ledger->withdraw($target->id(), $crossScopeReplacement);
        });

        $valid = EvidenceTombstone::create(
            'lineage-valid', $target, 'correction', 'source-system', 2, 'lineage-key-valid',
            '2026-09-30T12:16:00+00:00', $first->id(),
            ['id' => $replacement->id(), 'digest' => $replacement->digest()]
        );
        $ledger->withdraw($target->id(), $valid);
        self::assertSame($first->id(), $valid->supersedes());
        self::assertSame($replacement->id(), $valid->replacement()['id']);
        self::assertCount(2, $ledger->history($target->id())['tombstones']);
    }

    public function testParentAndReplacementIdsMustBeNonEmptyStrings(): void
    {
        $parent = $this->raw('typed-parent');
        $derived = EvidenceRecord::capture(
            'typed-derived',
            'derived',
            ['system' => 'fixture'],
            ['value' => 1],
            ['operation' => 'derive-v1'],
            [['id' => $parent->id(), 'digest' => $parent->digest()]],
            recordedAt: '2026-09-30T12:00:00+00:00'
        );
        $invalidParent = $derived->toArray();
        $invalidParent['parents'][0]['id'] = 42;
        $invalidParent['digest'] = CanonicalJson::digest($invalidParent);
        $this->assertInvalid(static fn (): EvidenceRecord => EvidenceRecord::fromArray($invalidParent));

        $tombstone = EvidenceTombstone::create(
            'typed-tombstone', $parent, 'withdrawal', 'source-system', 1, 'typed-key',
            '2026-09-30T12:10:00+00:00', replacement: ['id' => 'replacement', 'digest' => $parent->digest()]
        );
        $invalidReplacement = $tombstone->toArray();
        $invalidReplacement['replacement']['id'] = 42;
        $invalidReplacement['digest'] = CanonicalJson::digest($invalidReplacement);
        $this->assertInvalid(static fn (): EvidenceTombstone => EvidenceTombstone::fromArray($invalidReplacement));
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

    public function testPersistedLifecycleEventTamperingFailsClosedOnReopen(): void
    {
        if (!class_exists('SQLite3')) {
            self::markTestSkipped('SQLite3 is optional.');
        }

        $path = tempnam(sys_get_temp_dir(), 'chronicler-event-tamper-');
        self::assertIsString($path);

        try {
            $ledger = new EvidenceLedger(new SqliteEvidenceStore($path));
            $ledger->append($this->raw('tampered-event-record'));
            $ledger->close();

            $database = new \SQLite3($path);
            $body = $database->querySingle('SELECT body FROM evidence_events LIMIT 1');
            self::assertIsString($body);
            $event = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $event['reason'] = 'tampered reason';
            $statement = $database->prepare('UPDATE evidence_events SET body = :body');
            self::assertNotFalse($statement);
            $statement->bindValue(':body', json_encode($event, JSON_THROW_ON_ERROR), SQLITE3_TEXT);
            self::assertNotFalse($statement->execute());
            $database->close();

            $store = new SqliteEvidenceStore($path);
            try {
                $this->assertInvalid(static fn (): EvidenceLedger => new EvidenceLedger($store));
            } finally {
                $store->close();
            }

            unset($event['id'], $event['digest']);
            $event['record_id'] = 'missing-record';
            $event['id'] = 'sha256:' . hash('sha256', CanonicalJson::encode($event));
            $event['digest'] = CanonicalJson::digest($event);
            $database = new \SQLite3($path);
            $statement = $database->prepare('UPDATE evidence_events SET body = :body');
            self::assertNotFalse($statement);
            $statement->bindValue(':body', json_encode($event, JSON_THROW_ON_ERROR), SQLITE3_TEXT);
            self::assertNotFalse($statement->execute());
            $database->close();

            $store = new SqliteEvidenceStore($path);
            try {
                $this->assertInvalid(static fn (): EvidenceLedger => new EvidenceLedger($store));
            } finally {
                $store->close();
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testEachInactiveStateBlocksDerivationAfterReopen(): void
    {
        if (!class_exists('SQLite3')) {
            self::markTestSkipped('SQLite3 is optional.');
        }

        $path = tempnam(sys_get_temp_dir(), 'chronicler-inactive-');
        self::assertIsString($path);

        try {
            $ledger = new EvidenceLedger(new SqliteEvidenceStore($path));
            $quarantined = $this->raw('reopen-quarantined');
            $invalidated = $this->raw('reopen-invalidated');
            $withdrawn = $this->raw('reopen-withdrawn');
            foreach ([$quarantined, $invalidated, $withdrawn] as $record) {
                $ledger->append($record);
            }
            $ledger->quarantine($quarantined->id(), 'review');
            $ledger->invalidate($invalidated->id(), 'invalid');
            $ledger->withdraw($withdrawn->id(), EvidenceTombstone::create(
                'reopen-tombstone', $withdrawn, 'withdrawal', 'source-system', 1, 'reopen-key',
                '2026-09-30T12:10:00+00:00'
            ));
            $ledger->close();

            $restored = new EvidenceLedger(new SqliteEvidenceStore($path));
            self::assertSame('quarantined', $restored->state($quarantined->id()));
            self::assertSame('invalidated', $restored->state($invalidated->id()));
            self::assertSame('withdrawn', $restored->state($withdrawn->id()));

            foreach ([$quarantined, $invalidated, $withdrawn] as $index => $parent) {
                $derived = EvidenceRecord::capture(
                    'blocked-derived-' . $index,
                    'derived',
                    ['system' => 'fixture'],
                    ['value' => $index],
                    ['operation' => 'derive-v1'],
                    [['id' => $parent->id(), 'digest' => $parent->digest()]],
                    recordedAt: '2026-09-30T12:20:00+00:00'
                );
                $this->assertInvalid(function () use ($restored, $derived): void {
                    $restored->append($derived);
                });
            }
            $restored->close();
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function raw(string $id, array $payload = ['value' => 7], string $scope = 'default'): EvidenceRecord
    {
        return EvidenceRecord::capture(
            $id,
            'raw',
            ['system' => 'fixture', 'record' => $id],
            $payload,
            ['cursor' => 'fixture:' . $id],
            trust: 'observed',
            freshness: 'current',
            recordedAt: '2026-09-30T12:00:00+00:00',
            scope: $scope
        );
    }

    private function assertInvalid(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected invalid evidence input to fail closed.');
        } catch (InvalidArgumentException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
    }
}
