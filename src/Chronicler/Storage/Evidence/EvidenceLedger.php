<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Evidence;

use InvalidArgumentException;
use RuntimeException;

final class EvidenceLedger
{
    /** @var array<string, EvidenceRecord> */
    private array $records = [];

    /** @var list<array<string, mixed>> */
    private array $events = [];

    /** @var array<string, EvidenceTombstone> */
    private array $tombstones = [];

    /** @var array<string, string> */
    private array $idempotency = [];

    /** @var array<string, int> */
    private array $sequences = [];

    public function __construct(private readonly ?EvidenceStore $store = null)
    {
        if ($store === null) {
            return;
        }

        foreach ($store->records() as $record) {
            $this->records[$record->id()] = $record;
        }
        foreach ($store->events() as $event) {
            $this->events[] = $event;
        }
        foreach ($store->tombstones() as $tombstone) {
            $this->registerTombstone($tombstone);
        }
    }

    public function append(EvidenceRecord $record): void
    {
        $existing = $this->records[$record->id()] ?? null;
        if ($existing !== null) {
            if (hash_equals($existing->digest(), $record->digest())) {
                return;
            }

            throw new InvalidArgumentException('An evidence id cannot be reused for different content.');
        }

        foreach ($record->parents() as $parentRef) {
            $parent = $this->records[$parentRef['id']] ?? null;
            if ($parent === null || !hash_equals($parent->digest(), $parentRef['digest'])) {
                throw new InvalidArgumentException('Evidence parents must identify an exact retained record.');
            }
            if ($parent->scope() !== $record->scope()) {
                throw new InvalidArgumentException('Evidence parents must share the child record scope.');
            }
            if ($this->state($parent->id()) !== 'active') {
                throw new InvalidArgumentException('New evidence cannot derive from an inactive parent.');
            }
        }

        $this->store?->appendRecord($record);
        $this->records[$record->id()] = $record;
        $this->appendLifecycleEvent($record, 'appended', 'capture', 'chronicler');
    }

    public function quarantine(string $id, string $reason, string $actor = 'chronicler'): void
    {
        $this->appendLifecycleEvent($this->requireRecord($id), 'quarantined', $reason, $actor);
    }

    public function invalidate(string $id, string $reason, string $actor = 'chronicler'): void
    {
        $this->appendLifecycleEvent($this->requireRecord($id), 'invalidated', $reason, $actor);
    }

    public function withdraw(string $projectedRecordId, EvidenceTombstone $tombstone): void
    {
        $record = $this->requireRecord($projectedRecordId);
        if (!$tombstone->appliesTo($record)) {
            throw new InvalidArgumentException('A tombstone must bind the exact target id, digest, and scope.');
        }

        $known = $this->idempotency[$tombstone->idempotencyKey()] ?? null;
        if ($known !== null) {
            if ($known === $tombstone->id()) {
                return;
            }

            throw new InvalidArgumentException('A tombstone idempotency key cannot identify different events.');
        }

        $lastSequence = $this->sequences[$record->id()] ?? 0;
        if ($tombstone->sequence() <= $lastSequence) {
            throw new InvalidArgumentException('Tombstone sequence must advance monotonically for its target.');
        }

        $this->store?->appendTombstone($tombstone);
        $this->registerTombstone($tombstone);
        $this->appendLifecycleEvent($record, 'withdrawn', 'tombstone:' . $tombstone->id(), 'chronicler');
    }

    public function record(string $id): ?EvidenceRecord
    {
        return $this->records[$id] ?? null;
    }

    public function active(string $id): ?EvidenceRecord
    {
        return $this->state($id) === 'active' ? $this->record($id) : null;
    }

    public function state(string $id): string
    {
        $record = $this->requireRecord($id);
        $types = [];
        foreach ($this->events as $event) {
            if ($event['record_id'] === $record->id() && hash_equals($event['record_digest'], $record->digest())) {
                $types[] = $event['type'];
            }
        }

        if (in_array('invalidated', $types, true)) {
            return 'invalidated';
        }
        $withdrawn = array_filter(
            $this->tombstones,
            static fn (EvidenceTombstone $item): bool => $item->appliesTo($record)
        );
        if ($withdrawn !== [] || in_array('withdrawn', $types, true)) {
            return 'withdrawn';
        }
        if (in_array('quarantined', $types, true)) {
            return 'quarantined';
        }

        return 'active';
    }

    /** @return array{record: EvidenceRecord, state: string, events: list<array<string, mixed>>, tombstones: list<EvidenceTombstone>} */
    public function history(string $id): array
    {
        $record = $this->requireRecord($id);

        return [
            'record' => $record,
            'state' => $this->state($id),
            'events' => array_values(array_filter(
                $this->events,
                static fn (array $event): bool => $event['record_id'] === $id
            )),
            'tombstones' => array_values(array_filter(
                $this->tombstones,
                static fn (EvidenceTombstone $item): bool => $item->targetId() === $id
            )),
        ];
    }

    public function close(): void
    {
        $this->store?->close();
    }

    private function requireRecord(string $id): EvidenceRecord
    {
        return $this->records[$id] ?? throw new RuntimeException("Evidence record {$id} was not found.");
    }

    private function appendLifecycleEvent(EvidenceRecord $record, string $type, string $reason, string $actor): void
    {
        $sequence = 1;
        foreach ($this->events as $known) {
            if ($known['record_id'] === $record->id()) {
                $sequence = max($sequence, ((int) ($known['sequence'] ?? 0)) + 1);
            }
        }
        $occurredAt = gmdate(DATE_RFC3339);
        $event = [
            'id' => hash('sha256', CanonicalJson::encode([
                'record_id' => $record->id(),
                'record_digest' => $record->digest(),
                'type' => $type,
                'reason' => $reason,
                'actor' => $actor,
                'occurred_at' => $occurredAt,
                'sequence' => $sequence,
            ])),
            'record_id' => $record->id(),
            'record_digest' => $record->digest(),
            'type' => $type,
            'reason' => $reason,
            'actor' => $actor,
            'occurred_at' => $occurredAt,
            'sequence' => $sequence,
        ];

        $this->store?->appendEvent($event);
        $this->events[] = $event;
    }

    private function registerTombstone(EvidenceTombstone $tombstone): void
    {
        $this->tombstones[$tombstone->id()] = $tombstone;
        $this->idempotency[$tombstone->idempotencyKey()] = $tombstone->id();
        $this->sequences[$tombstone->targetId()] = max(
            $this->sequences[$tombstone->targetId()] ?? 0,
            $tombstone->sequence()
        );
    }
}
