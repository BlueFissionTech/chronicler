# Tests

Baseline tests are service-free and should run on a clean checkout after
dependencies are installed:

```powershell
vendor/bin/phpunit --do-not-cache-result
```

Optional integration tests for Kafka, Neo4j, databases, or external APIs must be
kept opt-in. Document any future environment variables here before adding those
tests to the suite.

The evidence lifecycle tests are service-free. When the local PHP runtime has
SQLite3, the same suite also creates a temporary database to verify the optional
DevElation-backed adapter across close/reopen; no environment variables or
running service are required. These fixtures include changed-content replay,
tombstone id reuse, invalid supersedes and replacement references, lifecycle
event tampering and unknown references, plus derivation attempts after each
inactive state.
