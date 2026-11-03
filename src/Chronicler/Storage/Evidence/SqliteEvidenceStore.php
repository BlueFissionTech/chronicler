<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Evidence;

use BlueFission\Connections\Database\SQLiteLink;
use JsonException;
use RuntimeException;
use SQLite3;
use SQLite3Result;
use SQLite3Stmt;

final class SqliteEvidenceStore implements EvidenceStore
{
    private SQLiteLink $link;

    public function __construct(string $path)
    {
        $this->link = new SQLiteLink(['database' => $path]);
        $this->link->open();
        $this->execute('CREATE TABLE IF NOT EXISTS evidence_records (id TEXT PRIMARY KEY, body TEXT NOT NULL)');
        $this->execute('CREATE TABLE IF NOT EXISTS evidence_events (id TEXT PRIMARY KEY, body TEXT NOT NULL)');
        $this->execute('CREATE TABLE IF NOT EXISTS evidence_tombstones (id TEXT PRIMARY KEY, body TEXT NOT NULL)');
    }

    public function records(): array
    {
        return array_map(
            static fn (array $row): EvidenceRecord => EvidenceRecord::fromArray($row),
            $this->readBodies('evidence_records')
        );
    }

    public function events(): array
    {
        return array_map(
            static fn (array $row): EvidenceLifecycleEvent => EvidenceLifecycleEvent::fromArray($row),
            $this->readBodies('evidence_events')
        );
    }

    public function tombstones(): array
    {
        return array_map(
            static fn (array $row): EvidenceTombstone => EvidenceTombstone::fromArray($row),
            $this->readBodies('evidence_tombstones')
        );
    }

    public function appendRecord(EvidenceRecord $record): void
    {
        $this->insert('evidence_records', $record->id(), $record->toArray());
    }

    public function appendEvent(EvidenceLifecycleEvent $event): void
    {
        $this->insert('evidence_events', $event->id(), $event->toArray());
    }

    public function appendTombstone(EvidenceTombstone $tombstone): void
    {
        $this->insert('evidence_tombstones', $tombstone->id(), $tombstone->toArray());
    }

    public function close(): void
    {
        $this->link->close();
    }

    private function database(): SQLite3
    {
        $database = $this->link->connection();
        if (!$database instanceof SQLite3) {
            throw new RuntimeException('The DevElation SQLite connection is unavailable.');
        }

        return $database;
    }

    private function execute(string $sql): void
    {
        if (!$this->database()->exec($sql)) {
            throw new RuntimeException($this->database()->lastErrorMsg());
        }
    }

    /** @throws JsonException */
    private function insert(string $table, string $id, array $body): void
    {
        $statement = $this->prepare("INSERT INTO {$table} (id, body) VALUES (:id, :body)");
        $statement->bindValue(':id', $id, SQLITE3_TEXT);
        $statement->bindValue(':body', CanonicalJson::encode($body), SQLITE3_TEXT);
        if ($statement->execute() === false) {
            throw new RuntimeException($this->database()->lastErrorMsg());
        }
    }

    /** @return list<array<string, mixed>> */
    private function readBodies(string $table): array
    {
        $result = $this->database()->query("SELECT body FROM {$table} ORDER BY rowid ASC");
        if (!$result instanceof SQLite3Result) {
            throw new RuntimeException($this->database()->lastErrorMsg());
        }

        $items = [];
        while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
            $items[] = json_decode($row['body'], true, 512, JSON_THROW_ON_ERROR);
        }

        return $items;
    }

    private function prepare(string $sql): SQLite3Stmt
    {
        $statement = $this->database()->prepare($sql);
        if (!$statement instanceof SQLite3Stmt) {
            throw new RuntimeException($this->database()->lastErrorMsg());
        }

        return $statement;
    }
}
