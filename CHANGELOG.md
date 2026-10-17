# Changelog

All noteworthy changes will be documented here. Until the first stable release, features may change without a backward-compatibility guarantee.

## Unreleased — pre-alpha

### Changed

- Removed unsupported Laravel 11 and Testbench 9 from the Composer compatibility constraints; Laravel 11 security maintenance ended March 12, 2026.

### Added

- Query-bound preflights now capture an optimistic fingerprint of selected model values and reject otherwise-authorized stale changes at confirmation; earlier tickets must be re-previewed after upgrade.
- Regression tests for same-scope data drift, old ticket rejection and query projections that omit columns.
- Explicit rejection of grouped, HAVING, DISTINCT and UNION queries; Livewire regression cases for global search with explicit and inverse selections.
- Refuse unsafe join-backed/global-scope SQL and table aliases before model hydration; independently reproduce stale-row lock contention when changed data remains inside the permitted scope.
- Suppress package-owned Eloquent `retrieved` events during read-only inspection and locked-row hydration without suppressing mutation-time model lifecycle events.
- Surface MySQL/PostgreSQL lock waits and deadlocks as localized fail-closed errors, preserve unconsumed tickets on database rollback, and test real two-process contention without retrying mutation callbacks.
- Composer package and Laravel auto-discovery.
- Canonical, bounded explicit selection inspector.
- Filament 5 adapter for filtered, current-page and bounded all-matching selections.
- Reject-on-mismatch validation for tenant scope, selected records and row selectability.
- Database-backed preflight tickets with identity, scope fingerprint, expiration and single-use semantics.
- Transactional guarded executor with row revalidation, record locking where supported, and rollback on callback failure.
- Native Filament bulk action with a confirmation modal and verified record count.
- Optional developer-declared scalar updates with a bounded, read-only previous-to-target field preview.
- Fresh Laravel 12 host installation + migration + actual guarded execution smoke test.
- Real Chromium English and Arabic Filament modal testing, including expired token and unauthorized row failure cases.
- Automated axe-core WCAG scan of English desktop and Arabic dark/mobile modal, with no reported violations and one incomplete item in each run.
- Explicit Composer security audit jobs for Laravel 12 and 13.
- PHP 8.2 / Laravel 12 PHPUnit compatibility verified with PHPUnit 10, and a second clean-room Laravel 13 / PHP 8.4 host installation/guarded replay test.
- Chromium automated keyboard focus containment, confirmation alertdialog semantics and Escape cancellation checks.
- Reproducible single-run MySQL and PostgreSQL measurements for 1/100/500 records.
- Warmed-up, sequential repeated microbenchmark harness (3 warmups + 20 measured runs per size and database) with empirical P50/P95/P99 reported as CI artifacts; not a concurrent workload claim.
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
- Manual browser UX, keyboard navigation and screen-reader accessibility review.
- Large-scale queued execution, audit history, impact diffs and rollback after successful commit.
- Filament 4 support or a versioned stable compatibility contract.
