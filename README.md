# Chronicler

Chronicler is a Blue Fission PHP library for storage-oriented value objects,
query builders, and connector scaffolds that complement DevElation without
requiring optional services in the baseline test suite.

The first scaffold focuses on:

- GraphQL field and selection helpers.
- Traceable storage packets with source, provenance, confidence, visibility,
  read-only authority, and diagnostics metadata.
- Artifact references, retrieval metadata, asset inventories, and generic
  resource references for storage-backed media, documents, graph links, and
  generated outputs.
- Kafka-style message envelopes and offset tracking.
- Neo4j-style graph node, edge, path, and traversal objects.
- Advanced data structures used around storage adapters, including Bloom
  filters, skip lists, and spatial points.

See [SPEC.md](SPEC.md) and [ARCHITECTURE.md](ARCHITECTURE.md) for the initial
scope and extension model.
