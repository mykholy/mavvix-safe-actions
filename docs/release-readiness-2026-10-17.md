# Mavvix Safe Actions — release readiness

**Snapshot:** October 17, 2026  
**Status:** pre-alpha on the default branch. No production-certified v1.0.0 release or Packagist stable publication is claimed.

## Verified automated gates

| Gate | Evidence | Result |
| --- | --- | --- |
| PHP 8.3 + Laravel 12 / PHP 8.4 + Laravel 13 | [CI](https://github.com/mykholy/mavvix-safe-actions/actions/runs/38993850253) | Passed |
| MySQL 8.4 and PostgreSQL 16 functional, replay, rollback and process-crash tests | [CI](https://github.com/mykholy/mavvix-safe-actions/actions/runs/38993850253) | Passed |
| Larastan/PHPStan source analysis without suppression baseline | [Static analysis](https://github.com/mykholy/mavvix-safe-actions/actions/runs/38993850252) | Passed, configured level 5 |
| Composer known-security-advisory check for Laravel 12 and 13 graphs | [Dependency security audit](https://github.com/mykholy/mavvix-safe-actions/actions/runs/38993850261) | Passed |
| Install package in an independent Laravel 12 host, publish migrations, execute exactly once | [Fresh host](https://github.com/mykholy/mavvix-safe-actions/actions/runs/38993850315) | Passed |
| Real Chromium Filament table selection, cancel, confirm, unauthorized and expired ticket handling, Arabic/mobile | [Browser CI](https://github.com/mykholy/mavvix-safe-actions/actions/runs/38993850280) | Passed |
| Automated axe-core scan of English desktop and Arabic mobile/dark confirmation | [Accessibility browser run](https://github.com/mykholy/mavvix-safe-actions/actions/runs/38993252688) | 0 reported violations per tested view; one incomplete item each |
| Developer-declared scalar change preview; does not call the mutation during preview | [PR #22](https://github.com/mykholy/mavvix-safe-actions/pull/22) | Tested and merged |
| Measured bounded execution on MySQL and PostgreSQL | [Benchmark results](benchmark-results-2026-10-17.md) | Single-run diagnostics only |

The package deliberately refuses uncertain selection modes and is not a framework for arbitrary side-effecting bulk mutations.

## Remaining release blockers

1. **Independent security review:** someone other than the author/implementer must review tenant isolation, Livewire trust boundaries, preflight replay/expiry, query fingerprints, unsafe Eloquent mutators/observers and lock failure semantics. Fix and regression-test every high-severity issue.
2. **Manual accessibility review:** keyboard-only flows, focus trapping/return, modal cancel behavior, screen-reader announcements in both languages and dark/mobile layouts. Automated scanning is not a certification.
3. **Correctness and performance envelope:** representative contention/deadlock/timeout cases under multiple sessions, benchmark warmed-up repetitions and P50/P95/P99, and host-specific observer/queue effects.
4. **Compatibility and documentation:** confirm pinned supported Laravel 12/13 and Filament 5 versions in a clean host; document active modes and rejected pivots/grouping, README API examples, migrations, cleanup and troubleshooting.
5. **Release management:** after sign-off, tag a genuinely reviewed version and publish metadata to Packagist/Filament directory. Do not silently equate a merged PR or green CI with stable security assurance.

## Non-goals and explicit caveats

- The preview for a custom closure cannot infer its effects. The declarative preview supports limited scalar fields only; observers, triggers, casts, queues and external systems may have additional effects.
- The callback must remain bounded, synchronous, one-connection and database-only. Rollback does **not** reverse messages, remote API requests or third-party effects.
- The default 500-record ceiling is not a statement of unlimited throughput.
- A package maintainer's self-review is valuable but **cannot be labeled independent security sign-off**.

Track work in [Issue #5](https://github.com/mykholy/mavvix-safe-actions/issues/5) and [Issue #6](https://github.com/mykholy/mavvix-safe-actions/issues/6).
