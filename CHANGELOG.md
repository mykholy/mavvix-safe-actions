# Changelog

All noteworthy changes will be documented here. Until the first stable release, features may change without a backward-compatibility guarantee.

## Unreleased — pre-alpha

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

### Not yet complete

- Independent security review and MySQL/PostgreSQL parallel-submission testing.
- Full browser UX and accessibility review.
- Large-scale queued execution, audit history, impact diffs and rollback after successful commit.
- Filament 4 support or a versioned stable compatibility contract.
