# Chronicler Specification

## Purpose and Scope

Chronicler is a DevElation-oriented extension library for storage objects,
connector scaffolds, and query helpers. It complements DevElation by keeping
storage-specific primitives in their own package while inheriting DevElation's
dynamic objects, schema handling, behavioral dispatching, and connector
contracts.

This initial scaffold is intentionally service-free. Kafka, Neo4j, GraphQL, and
other external systems are represented as typed objects and query structures;
actual network drivers should remain opt-in adapters.

## User Stories

- As a PHP developer, I can compose GraphQL selections as objects so client and
  resolver code does not concatenate raw query strings.
- As an event-streaming worker, I can wrap message payloads with topic,
  partition key, headers, timestamp, and offset metadata.
- As a storage integrator, I can emit a deterministic packet that preserves
  payload, source ownership, provenance, confidence, visibility, authority, and
  diagnostics metadata without granting write authority to consumers.
- As an evidence producer, I can retain immutable raw inputs and create
  structured or derived records that bind the exact identity and digest of
  every parent.
- As an evidence consumer, I can distinguish trust, sensitivity, freshness,
  quarantine, invalidation, and withdrawal without allowing source recovery or
  a derived summary to resurrect superseded evidence.
- As an artifact producer, I can register media, documents, datasets, and
  generated outputs with retrieval metadata and stable storage references.
- As a storage consumer, I can follow graph, document, artifact, and event-offset
  references without requiring a service-specific client in the baseline
  package.
- As a stateful stream processor, I can track committed offsets by topic and
  partition without depending on Kafka at test time.
- As a graph data consumer, I can represent nodes, relationships, paths, and
  traversals in native PHP objects before binding them to Neo4j or another graph
  driver.
- As a storage implementer, I can use baseline data structures such as Bloom
  filters, skip lists, priority queues, weighted collections, root namespace
  `IVal` primitives (`Vec`, `Set`, `Dict`, `Deq`, `Pri`, `Pile`), and spatial
  points around cache checks, indexes, ranking, ordered access, and GIS
  queries.

## Acceptance Criteria

- The package requires PHP 8.2 or newer and `bluefission/develation`.
- Source code lives under `src/Chronicler`, with primitive-style value objects
  allowed at the root `BlueFission` namespace when they intentionally extend
  the DevElation `Val`/`IVal` primitive surface.
- First-class directories exist for `Data`, `Storage`, and `Connections`.
- Baseline tests do not require Kafka, Neo4j, databases, Docker, or network
  access.
- Objects use DevElation dynamic object patterns, helpers, schemas, behavioral
  dispatching, and connection inheritance where appropriate.
- Storage packets expose traceability diagnostics when source, provenance, or
  authority metadata is incomplete.
- Evidence records use a versioned canonical JSON representation and SHA-256
  digest, preserve source and provenance, and always expose `authority=none`.
- Structured and derived records retain exact parent id/digest lineage; an
  inactive parent cannot seed new derived evidence.
- Lifecycle changes are append-only, versioned, canonically digested, and
  revalidated on reopen. Tampered events, unknown record references, and broken
  per-record sequences fail closed. Quarantined, invalidated, and withdrawn
  records remain auditable but are excluded from active reads and cannot seed
  new derivations.
- Tombstones bind an exact target id, digest, and scope, use monotonic sequence
  and full-digest idempotency, reject reused ids with changed content, and
  cannot be projected onto unrelated evidence. Supersedes and replacement
  references must resolve exact retained same-scope lineage.
- Source and provenance maps validate required identity and lineage fields
  while retaining namespaced extension data. Verified trust requires explicit
  issuer, proof, and verification state; a digest establishes content identity,
  not authenticity or host authority.
- The baseline evidence lifecycle is service-free. A DevElation-backed SQLite
  adapter is optional and preserves state across close/reopen when SQLite3 is
  available.
- Artifact contracts expose stable references, retrieval metadata, asset
  inventories, and generic resource links.
- Optional service integrations remain adapter-level extensions over the
  service-free objects introduced here.
- Reusable structures support general storage, sequence, keyed lookup, stack,
  queue, and ranking use cases without depending on optional PHP extensions.
- Root data-structure primitives prefer php-ds backing stores when the extension
  is available and fall back to arrays on clean PHP installations.

## Non-Goals

- Shipping production Kafka or Neo4j network drivers in the first scaffold.
- Replacing DevElation's existing data, schema, storage, graph, or connection
  primitives.
- Owning reasoning, planning, model, or write-authority semantics at consumer
  boundaries.
- Pulling in third-party data-structure packages for the baseline library.
