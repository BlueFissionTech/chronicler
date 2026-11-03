<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Evidence;

use InvalidArgumentException;
use RuntimeException;

final class EvidenceLedger
{
    /** @var array<string, EvidenceRecord> */
    private array $records = [];

    /** @var list<EvidenceLifecycleEvent> */
    private array $events = [];

    /** @var array<string, EvidenceTombstone> */
    private array $tombstones = [];

    /** @var array<string, string> idempotency key to canonical tombstone digest */
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
            $this->registerEvent($event);
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

        $existing = $this->tombstones[$tombstone->id()] ?? null;
        if ($existing !== null) {
            if (hash_equals($existing->digest(), $tombstone->digest())
                && $existing->idempotencyKey() === $tombstone->idempotencyKey()
            ) {
                return;
            }

            throw new InvalidArgumentException('A tombstone id cannot be reused for different content.');
        }

        $known = $this->idempotency[$tombstone->idempotencyKey()] ?? null;
        if ($known !== null) {
            if (hash_equals($known, $tombstone->digest())) {
                return;
            }

            throw new InvalidArgumentException('A tombstone idempotency key cannot identify different events.');
        }

        $this->assertTombstoneReferences($tombstone);

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
            if ($event->recordId() === $record->id() && hash_equals($event->recordDigest(), $record->digest())) {
                $types[] = $event->type();
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
            'events' => array_values(array_map(
                static fn (EvidenceLifecycleEvent $event): array => $event->toArray(),
                array_filter(
                    $this->events,
                    static fn (EvidenceLifecycleEvent $event): bool => $event->recordId() === $id
                )
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
            if ($known->recordId() === $record->id()) {
                $sequence = max($sequence, $known->sequence() + 1);
            }
        }
        $event = EvidenceLifecycleEvent::create($record, $type, $reason, $actor, $sequence);

        $this->store?->appendEvent($event);
        $this->registerEvent($event);
    }

    private function registerTombstone(EvidenceTombstone $tombstone): void
    {
        $record = $this->records[$tombstone->targetId()] ?? null;
        if ($record === null || !$tombstone->appliesTo($record)) {
            throw new InvalidArgumentException('Stored tombstone must bind an exact retained target.');
        }

        $existing = $this->tombstones[$tombstone->id()] ?? null;
        if ($existing !== null && !hash_equals($existing->digest(), $tombstone->digest())) {
            throw new InvalidArgumentException('Stored tombstone id is reused for different content.');
        }
        $known = $this->idempotency[$tombstone->idempotencyKey()] ?? null;
        if ($known !== null && !hash_equals($known, $tombstone->digest())) {
            throw new InvalidArgumentException('Stored tombstone idempotency key identifies different content.');
        }

        $this->assertTombstoneReferences($tombstone);

        $lastSequence = $this->sequences[$tombstone->targetId()] ?? 0;
        if ($tombstone->sequence() <= $lastSequence) {
            throw new InvalidArgumentException('Stored tombstone sequence must advance monotonically for its target.');
        }

        $this->tombstones[$tombstone->id()] = $tombstone;
        $this->idempotency[$tombstone->idempotencyKey()] = $tombstone->digest();
        $this->sequences[$tombstone->targetId()] = max(
            $this->sequences[$tombstone->targetId()] ?? 0,
            $tombstone->sequence()
        );
    }

    private function registerEvent(EvidenceLifecycleEvent $event): void
    {
        $record = $this->records[$event->recordId()] ?? null;
        if ($record === null || !hash_equals($record->digest(), $event->recordDigest())) {
            throw new InvalidArgumentException('Lifecycle events must identify an exact retained record.');
        }

        $lastSequence = 0;
        foreach ($this->events as $known) {
            if ($known->id() === $event->id()) {
                if (hash_equals($known->digest(), $event->digest())) {
                    return;
                }

                throw new InvalidArgumentException('Lifecycle event id is reused for different content.');
            }
            if ($known->recordId() === $event->recordId()) {
                $lastSequence = max($lastSequence, $known->sequence());
            }
        }
        if ($event->sequence() !== $lastSequence + 1) {
            throw new InvalidArgumentException('Lifecycle event sequence must be contiguous for its record.');
        }

        $this->events[] = $event;
    }

    private function assertTombstoneReferences(EvidenceTombstone $tombstone): void
    {
        $supersedes = $tombstone->supersedes();
        if ($supersedes !== null) {
            $known = $this->tombstones[$supersedes] ?? null;
            if ($known === null
                || $known->targetId() !== $tombstone->targetId()
                || !hash_equals($known->targetDigest(), $tombstone->targetDigest())
                || $known->scope() !== $tombstone->scope()
                || $known->sequence() >= $tombstone->sequence()
            ) {
                throw new InvalidArgumentException('Supersedes must identify an earlier tombstone for the exact target and scope.');
            }
        }

        $replacement = $tombstone->replacement();
        if ($replacement === null) {
            return;
        }
        $record = $this->records[$replacement['id']] ?? null;
        if ($record === null
            || $record->id() === $tombstone->targetId()
            || !hash_equals($record->digest(), $replacement['digest'])
            || $record->scope() !== $tombstone->scope()
        ) {
            throw new InvalidArgumentException('Replacement must identify a distinct retained record with the exact digest and scope.');
        }
    }
}
