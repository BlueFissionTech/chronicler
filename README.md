# Chronicler

Chronicler is a Blue Fission PHP library for storage-oriented value objects,
query builders, and connector scaffolds that complement DevElation without
requiring optional services in the baseline test suite.

The first scaffold focuses on:

- GraphQL field and selection helpers.
- Traceable storage packets with source, provenance, confidence, visibility,
  read-only authority, and diagnostics metadata.
- Immutable evidence records with canonical SHA-256 identity, exact parent
  lineage, append-only lifecycle events, and digest-bound tombstones.
- Artifact references, retrieval metadata, asset inventories, and generic
  resource references for storage-backed media, documents, graph links, and
  generated outputs.
- Kafka-style message envelopes and offset tracking.
- Neo4j-style graph node, edge, path, and traversal objects.
- Advanced data structures used around storage adapters, including Bloom
  filters, skip lists, priority queues, weighted collections, root namespace
  `IVal` primitives (`Vec`, `Set`, `Dict`, `Deq`, `Pri`, `Pile`) backed by
  php-ds when available, and spatial points.

See [SPEC.md](SPEC.md) and [ARCHITECTURE.md](ARCHITECTURE.md) for the initial
scope and extension model.

## Evidence lifecycle

`EvidenceRecord` captures raw, structured, or derived evidence without granting
write authority. Structured and derived records must reference the exact id and
digest of retained parent records. `EvidenceLedger` keeps original records
available for audit while excluding quarantined, invalidated, or withdrawn
records from active reads.

Tombstones are scoped to an exact record id, digest, and scope. Callers must
name the projected record when applying one, preventing an unrelated envelope
from being marked withdrawn. Replaying the same idempotency key is safe, while
out-of-order sequences and target mismatches are rejected.

The default ledger is service-free and in-memory. `SqliteEvidenceStore` is an
optional DevElation-backed adapter for durable append-only records, events, and
tombstones; it requires the PHP SQLite3 extension and can be closed and reopened
without changing evidence identity.
