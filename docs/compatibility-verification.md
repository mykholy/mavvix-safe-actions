# Supported runtime verification for a first stable release

Filament 5 requires PHP 8.2+. Laravel 12 accepts PHP 8.2+, while Laravel 13 requires PHP 8.3+. We intentionally exclude Laravel 11 because its security-support window has ended.

## Automated CI configurations

| Integration | PHP | Host | Test coverage |
| --- | --- | --- | --- |
| Filament 5, Laravel 12 | 8.2 | Testbench | PHPUnit and Pint |
| Filament 5, Laravel 12 | 8.3 | Testbench | PHPUnit, Pint, Larastan, MySQL 8.4, PostgreSQL 16 |
| Filament 5, Laravel 13 | 8.4 | Testbench | PHPUnit and Pint |
| Laravel 12 standalone host | 8.3 | Fresh composer create-project | Path installation, migration, guarded transaction and replay rejection |
| Laravel 13 standalone host | 8.4 | Fresh composer create-project | Path installation, migration, guarded transaction and replay rejection |
| Filament 5 native UI | 8.3 / Laravel 12 | Chromium / Playwright | Mounted modal, safety errors, AR/EN, desktop/mobile, automated axe |

**Evidence requirement:** A row is verified only after that configuration's CI completes successfully on the candidate commit. Local tests and this matrix are not an independent security assessment.

## Identity and database boundaries

- Supported: single-column Eloquent integer or string/UUID key identities, native Filament selection, bounded filtered/all-matching, synchronous one-connection operations, tenant scoping supplied by host application.
- Refused: ambiguous joins/table aliases (including global scopes), grouped/having/union/distinct queries, pivot identities, relationship-row duplicates, unbounded selections and cross-database atomicity.
- Driver scope: SQLite for developer tests; MySQL 8.4 and PostgreSQL 16 for concurrent transaction tests. Other drivers are **not** performance or concurrency certified.
- No guarantee for actions with external I/O, side-effecting Eloquent observers, unsupported query adapters, or more than 500 records.

## Release review

Before tag `v1.0.0`: confirm all CI jobs on the *release commit* pass, perform independent security review and manual keyboard/screen-reader tests, measure concurrency load and lock-timeout behavior on production-representative infrastructure, publish verified release notes, and test Packagist install. Until then, stay pre-alpha and do not claim certification.
