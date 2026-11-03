# Chronicler

Chronicler is a Blue Fission PHP library for storage-oriented value objects,
query builders, and connector scaffolds that complement DevElation without
requiring optional services in the baseline test suite.

## Installation

Install Chronicler with Composer:

```bash
composer require bluefission/chronicler
```

The package requires PHP 8.2 or newer and resolves DevElation through
`bluefission/develation:^1.3.39`.

## Scope

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

Evidence inputs must contain native JSON values: null, booleans, integers,
finite floats, strings, and nested arrays. Mutable objects (including primitive
wrappers), resources, recursive arrays, and excessive nesting are rejected.
Constructors detach caller-owned PHP references, and exported arrays cannot
mutate retained records, lifecycle events, or tombstones. Valid native JSON
inputs keep their canonical bytes and digest identities.

`EvidenceRecord` captures raw, structured, or derived evidence without granting
write authority. Structured and derived records must reference the exact id and
digest of retained parent records. `EvidenceLedger` keeps original records
available for audit while excluding quarantined, invalidated, or withdrawn
records from active reads.

Tombstones are scoped to an exact record id, digest, and scope. Callers must
name the projected record when applying one, preventing an unrelated envelope
from being marked withdrawn. Replay is idempotent only when the tombstone id,
idempotency key, target, scope, and full canonical digest all match. Reused ids,
changed-content replay, out-of-order sequences, and target mismatches fail
closed. Supersedes references must resolve an earlier tombstone for the exact
target and scope; replacements must resolve a distinct retained record with the
exact digest and same scope.

Source and provenance maps remain extensible, but required source identity and
lineage fields are validated. Caller-asserted `verified` trust also requires an
explicit verification issuer, proof reference, and verification state. Typed
read-only accessors are available alongside each canonical `toArray()` shape.

The default ledger is service-free and in-memory. `SqliteEvidenceStore` is an
optional DevElation-backed adapter for durable append-only records, versioned
and digested lifecycle events, and tombstones; it requires the PHP SQLite3
extension and can be closed and reopened without changing evidence identity.
Tampered events, unknown record references, and broken event sequences make the
stored ledger unavailable rather than changing projected state silently.
