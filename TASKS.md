# Engineering checklist

## PR-00 — Foundation / selection discovery

- [x] Initialize Composer package and service provider
- [x] Add bounded explicit-selection core and regression fixtures
- [x] Add CI test matrix configuration
- [x] Document limitations and public Filament APIs
- [x] Install dependencies and obtain passing CI evidence (Laravel 12 / PHP 8.3, Laravel 13 / PHP 8.4)
- [x] Run the explicit-record Filament v5 Livewire selection harness in both CI environments
- [x] Add bounded adapter and Livewire regression coverage for filtered/all-matching selections (tested on Laravel 12/13)
- [ ] Test pivot identity, global-search and grouped selections
- [ ] Obtain an independently reviewable selection adapter contract

## PR-01 onward

- [x] Read-only persisted preflight snapshot with actor/tenant/action binding and expiry (CI tested)
- [x] One-time consumption and execution-time scope/permission revalidation (CI tested)
- [x] Native Filament action modal with verified record count (CI tested)
- [ ] Field-by-field read-only impact diff
- [x] Atomic bounded database mutation and revalidation (SQLite CI)
- [x] Sequential duplicate-submission, stale-scope, revoked-policy and rollback tests
- [x] Two-process duplicate confirmation and stale-scope regression tests on MySQL and PostgreSQL
- [x] Abrupt worker exit after UPDATE/before COMMIT rolls back record and ticket on MySQL/PostgreSQL
- [ ] Additional contention scenarios, lock timeouts and scale benchmarks
- [x] CI test matrix on SQLite, MySQL 8.4 and PostgreSQL 16
- [x] Larastan/PHPStan level 5 analysis on CI without suppression baseline
- [x] Native modal and exception translations in English and Arabic
- [x] Configurable pruning command for old preflight tickets
- [x] Reject changes to current selected rows after preview
- [ ] Real-driver performance benchmarks for 1, 100 and 500 records
- [x] Drop security-EOL Laravel 11 from Composer compatibility constraints
- [ ] Independent security review, accessibility/browser validation and release documentation

**Release status: pre-alpha.** Do not tag v1.0.0 until all gates in `IMPLEMENTATION_PLAN.md` are satisfied.
