# lbaf-mysql

A simple and quick `mysqli` wrapper for MySQL/MariaDB.

## Requirements

- PHP ~8.2 with `ext-mysqli`
- Docker (for integration tests only)

## Install

```sh
composer install
```

## Running tests

Unit tests need nothing extra:

```sh
composer test:unit
```

Integration tests run against a real MariaDB in Docker. Start the container once per session, then run the suite:

```sh
composer db:up
composer test:integration
composer db:down   # when done
```

`db:up` is idempotent — safe to call when the container is already running. The container uses tmpfs, so data is ephemeral.

To run everything:

```sh
composer test
```

### Test database connection

Defaults (overridable via env in `phpunit.xml.dist` or shell):

| Variable | Default |
|---|---|
| `LBAF_TEST_DB_HOST` | `127.0.0.1` |
| `LBAF_TEST_DB_PORT` | `33306` |
| `LBAF_TEST_DB_USER` | `root` |
| `LBAF_TEST_DB_PASSWORD` | *(empty)* |
| `LBAF_TEST_DB_NAME` | `lbaf_test` |

The bootstrap fails loudly if the database is unreachable — no silent skips.
