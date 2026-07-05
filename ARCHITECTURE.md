# Chronicler Architecture

## Package Shape

Chronicler uses the same broad PSR-4 shape as sibling Blue Fission libraries:

- `BlueFission\\` maps to `src/`.
- Library classes live under `BlueFission\Chronicler`.
- Tests live under `BlueFission\Chronicler\Tests`.

## Layers

### Data

`src/Chronicler/Data` holds mapping, schema-oriented helpers, and neutral
storage packets. These classes inherit DevElation data/schema behavior when
they represent data records or schema registries.

GraphQL helpers live under `Data/GraphQL` because they describe typed data
selection and mapping boundaries rather than a transport-specific client.

`StoragePacket` is the consumer-safe traceability shape for storage-derived
payloads. It carries source, provenance, confidence, visibility, read-only
authority, diagnostics, and arbitrary metadata while staying free of reasoning,
planning, or model-provider semantics.

### Storage

`src/Chronicler/Storage` holds service-free storage structures:

- `Event` for Kafka-style message envelopes and offset tracking.
- `Graph` for Neo4j-style graph, node, relationship, path, and traversal
  objects. Chronicler graph and node classes extend DevElation's base graph
  classes and layer database mapping helpers over them. `Graph` also uses
  DevElation prototype domain behavior for member/domain context instead of
  carrying a separate local family registry.
- `Artifact` for artifact references, retrieval metadata, and asset inventory
  objects. These classes describe storage identity and retrieval intent without
  opening object-store, filesystem, or HTTP clients.
- `Reference` for generic graph, document, artifact, and event-offset links.
- `Structures` for Bloom filters, skip lists, priority queues, weighted
  collections, spatial points, and similar storage-adjacent structures.

### Connections

`src/Chronicler/Connections` holds connector profiles and connection wrappers.
These wrappers inherit DevElation's `BlueFission\Connections\Connection` so they
participate in the same action and event lifecycle as other DevElation
connections. The scaffold records query intent but does not open external
service sockets.

## Extension Rules

- Add network drivers as optional adapters over existing objects.
- Keep connection configuration in `ConnectionProfile`.
- Keep query intent serializable through `Storage\QueryBuilder`.
- Use DevElation schemas to validate payloads before connector adapters send
  requests to external services.
- Use `StoragePacket` when data leaves a storage adapter boundary and needs
  provenance, authority, or diagnostic context.
- Use artifact and resource references for stable storage links before binding
  to a concrete filesystem, object store, document service, or graph driver.
- Keep service-backed tests opt-in and document required environment variables
  in `tests.md`.
