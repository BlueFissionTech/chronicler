<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Evidence;

interface EvidenceStore
{
    /** @return list<EvidenceRecord> */
    public function records(): array;

    /** @return list<EvidenceLifecycleEvent> */
    public function events(): array;

    /** @return list<EvidenceTombstone> */
    public function tombstones(): array;

    public function appendRecord(EvidenceRecord $record): void;

    public function appendEvent(EvidenceLifecycleEvent $event): void;

    public function appendTombstone(EvidenceTombstone $tombstone): void;

    public function close(): void;
}
