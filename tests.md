# Tests

Baseline tests are service-free and should run on a clean checkout after
dependencies are installed:

```powershell
vendor/bin/phpunit --do-not-cache-result
```

Optional integration tests for Kafka, Neo4j, databases, or external APIs must be
kept opt-in. Document any future environment variables here before adding those
tests to the suite.
