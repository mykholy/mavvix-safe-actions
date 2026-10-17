# Mavvix Safe Actions — historical release readiness snapshot

> **Historical evidence as of October 17, 2026.** The repository later published a narrowly scoped v1.0.0 stable SemVer API with explicit first-party-only security review. Read the [current README](../README.md), [GitHub releases](https://github.com/mykholy/mavvix-safe-actions/releases/latest) and [recorded security assessment](security-review-v1.0.0.md). Remaining independent audit and accessibility checks are still not complete. The pre-release NO-GO decision below is retained as historical context.

**Reviewed:** October 17, 2026.  
**Status: pre-alpha / release candidate preparation.** Green automated tests do **not** establish independent security or production safety, and no stable release or Packagist listing is claimed.

## Implemented automated gates and evidence

| Gate | Evidence | Verified scope |
| --- | --- | --- |
| Laravel 12 / PHP 8.2 and 8.3; Laravel 13 / PHP 8.4 | [PR #29 CI](https://github.com/mykholy/mavvix-safe-actions/actions/runs/39040432451) | PHPUnit and Pint matrix passed |
| Laravel 12 and 13 independent Composer host installation | [PR #29 host CI](https://github.com/mykholy/mavvix-safe-actions/pull/29) | Path install, publish migrations and execute-once smoke test |
| MySQL 8.4 and PostgreSQL 16 concurrency/crash recovery | [PR #27 CI](https://github.com/mykholy/mavvix-safe-actions/pull/27) | Independent sessions, duplicate tickets, stale scope and same-scope data changes |
| Bounded lock-timeout failure | [PR #31 CI](https://github.com/mykholy/mavvix-safe-actions/pull/31) | 1-second timeout against held row lock; localized failure, no second commit |
| Read-only Eloquent hydration event suppression | [PR #28 CI](https://github.com/mykholy/mavvix-safe-actions/pull/28) | Model `retrieved` observer does not fire in package-owned reads; `updated` still fires |
| Query identity and global scopes | [PR #27 CI](https://github.com/mykholy/mavvix-safe-actions/pull/27) | Unsupported joins, aliases and aggregation rejected |
| Static analysis and dependency security | [PR #31 workflows](https://github.com/mykholy/mavvix-safe-actions/pull/31) | PHPStan level 5, Laravel 12/13 Composer audit |
| Chromium end-to-end, keyboard and automated axe | [PR #30 browser CI](https://github.com/mykholy/mavvix-safe-actions/actions/runs/39041153222) | EN/AR, cancel/confirm, unauthorized/expired ticket, focus containment, Escape, dark/mobile |
| Sequential warmed-up MySQL/PostgreSQL measurements | [PR #26 benchmark artifacts](https://github.com/mykholy/mavvix-safe-actions/actions/runs/39016948200) | 3 warmups + 20 measured samples per 1/100/500-row case; P50/P95/P99 |

Release eligibility must be judged against the **final merged commit's own GitHub Actions runs**, rather than assuming these separate earlier commits constitute a production certification.

## Gates still preventing `v1.0.0`

1. **Independent security sign-off.** Someone other than the implementer must review selection trust boundaries, Filament tenant/panel context, policies, replay/expiry, query fingerprints, Eloquent lifecycle behavior, transaction failure semantics and any high-severity findings. See [Issue #6](https://github.com/mykholy/mavvix-safe-actions/issues/6).
2. **Human accessibility sign-off.** Screen reader, keyboard-only focus/return, announcement readability and RTL/mobile tests; automated Chromium/axe coverage does not replace this.
3. **Production-like concurrency and scalability evidence.** Multiple independent admins/sessions, representative data and model observers, lock/deadlock rate, P50/P95/P99, DB/load/CPU/memory and recovery behavior. Current timing numbers are sequential CI diagnostics.
4. **Release management.** Freeze reviewed SHA, approve a release candidate, run migration upgrade verification, tag only after acceptance, test exact-version Composer install, submit Packagist and Filament listing with accurate metadata and genuine screenshots.

Do not label this work stable merely because the latest workflow matrix is green. The package deliberately limits synchronous selections to 500 records and never claims rollback for external effects or arbitrary callback side effects.

See [release playbook](release-playbook.md), [security model](security-model.md), [compatibility matrix](compatibility-verification.md), and [benchmark details](benchmarks.md).
