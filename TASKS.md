# Engineering checklist

## PR-00 — Foundation / selection discovery

- [x] Initialize Composer package and service provider
- [x] Add bounded explicit-selection core and regression fixtures
- [x] Add CI test matrix configuration
- [x] Document limitations and public Filament APIs
- [x] Install dependencies and obtain passing CI evidence (Laravel 12 / PHP 8.3, Laravel 13 / PHP 8.4)
- [x] Run the explicit-record Filament v5 Livewire selection harness in both CI environments
- [x] Add bounded adapter and Livewire regression coverage for filtered/all-matching selections (tested on Laravel 12/13)
- [x] Apply inverse-selection exclusions before the bounded SQL scan; verify exactly 499 of 501 rows can be selected safely
- [x] Add host-configurable protected ownership columns to the declarative update API
- [x] Test global search, explicit and inverse selections, and reject unsupported grouped/aggregate query shapes
- [ ] Test pivot identity and Filament UI-level group/relationship selections
- [ ] Obtain an independently reviewable selection adapter contract

## PR-01 onward

- [x] Read-only persisted preflight snapshot with actor/tenant/action binding and expiry (CI tested)
- [x] Ensure package-owned read-only hydration never dispatches Eloquent retrieved events; preserve mutation lifecycle events
- [x] One-time consumption and execution-time scope/permission revalidation (CI tested)
- [x] Native Filament action modal with verified record count (CI tested)
- [x] Bounded read-only old-to-new field summaries for explicitly developer-declared scalar updates
- [ ] General arbitrary-mutation impact previews remain unsupported by design
- [x] Atomic bounded database mutation and revalidation (SQLite CI)
- [x] Sequential duplicate-submission, stale-scope, revoked-policy and rollback tests
- [x] Two-process duplicate confirmation, stale-scope, and same-scope row-state regression tests on MySQL and PostgreSQL
- [x] Abrupt worker exit after UPDATE/before COMMIT rolls back record and ticket on MySQL/PostgreSQL
- [x] Real database two-process lock-timeout regression with ticket preservation and localized error (MySQL/PostgreSQL)
- [ ] Broader deadlock, cancellation and scale benchmarks
- [x] CI test matrix on SQLite, MySQL 8.4 and PostgreSQL 16
- [x] Larastan/PHPStan level 5 analysis on CI without suppression baseline
- [x] Native modal and exception translations in English and Arabic
- [x] Configurable pruning command for old preflight tickets
- [x] Reject changes to current selected rows after preview
- [x] Reject changes to persisted selected model values between preview and confirmation, including same-scope updates
- [x] Single-run MySQL/PostgreSQL microbenchmarks for 1, 100 and 500 records, archived as CI artifacts
- [x] Repeated warmed-up sequential microbenchmarks with empirical P50/P95/P99 exported in CI (20 samples per case)
- [ ] Independent representative throughput/percentile benchmarks under concurrency load
- [x] Drop security-EOL Laravel 11 from Composer compatibility constraints
- [x] Fresh Laravel 12 Composer install + migration + guarded execution smoke test
- [x] Real Chromium modal cancel, confirm, expired-token, unauthorized-row, English/Arabic and mobile-dark checks
- [x] Automated axe-core desktop and mobile RTL/dark scan with no reported violations (manual checks outstanding)
- [x] Automated browser keyboard focus trap and Escape-cancel regression (manual assistive technology validation outstanding)
- [x] Composer security audit CI on Laravel 12 and 13
- [ ] Independent security review and sign-off
- [ ] Manual keyboard/screen reader accessibility validation and complex selection UX
- [ ] Independent production-like latency, contention and scalability benchmarks
- [x] Publish a release playbook and CI-linked compatibility evidence
- [ ] Independent reviewer approval of the final versioned compatibility/release contract

**Release status: pre-alpha.** Do not tag v1.0.0 until all gates in `IMPLEMENTATION_PLAN.md` are satisfied.
