# Changelog

All noteworthy changes will be documented here. Until the first stable release, features may change without a backward-compatibility guarantee.

## Unreleased — pre-alpha

### Changed

- Removed unsupported Laravel 11 and Testbench 9 from the Composer compatibility constraints; Laravel 11 security maintenance ended March 12, 2026.

### Added

- Composer package and Laravel auto-discovery.
- Canonical, bounded explicit selection inspector.
- Filament 5 adapter for filtered, current-page and bounded all-matching selections.
- Reject-on-mismatch validation for tenant scope, selected records and row selectability.
- Database-backed preflight tickets with identity, scope fingerprint, expiration and single-use semantics.
- Transactional guarded executor with row revalidation, record locking where supported, and rollback on callback failure.
- Native Filament bulk action with a confirmation modal and verified record count.
- Livewire, Testbench, security regression, PHPUnit and Laravel Pint CI coverage.
- MySQL 8.4 and PostgreSQL 16 functional test jobs in CI.
- Independent-process parallel ticket replay and stale-scope regression coverage for MySQL and PostgreSQL.
- Crash recovery regression: abrupt process termination after UPDATE but before COMMIT rolls back both the row and preflight ticket on MySQL and PostgreSQL.
- Mandatory Larastan/PHPStan level 5 CI gate with typed Eloquent preflight records.
- Extra confirmation guard that rejects changed Livewire selection even when filters remain unchanged.
- Pruning command for expired and consumed preflight tickets with a configurable retention period.
- English and Arabic modal, notification and error messages, with a translator-free fallback for unit tests.

### Not yet complete

- Independent security review, broader lock-contention testing and scalability benchmarks.
- Full browser UX and accessibility review.
- Large-scale queued execution, audit history, impact diffs and rollback after successful commit.
- Filament 4 support or a versioned stable compatibility contract.
