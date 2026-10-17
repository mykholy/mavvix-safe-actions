# Mavvix Safe Actions — historical release-candidate review packet

> **Historical review packet.** The owner subsequently authorized and published a narrowly scoped stable SemVer release with a **non-independent first-party assessment**. Its release record is [v1.0.0](https://github.com/mykholy/mavvix-safe-actions/releases/tag/v1.0.0), and the current source-level assessment is [security-review-v1.0.0.md](security-review-v1.0.0.md). Independent security review, manual accessibility and production-shaped load evaluation remain open. The unsigned independent sign-off and initial NO-GO section below are preserved as the original state of the review.

**Prepared:** 2026-10-18 (Africa/Cairo)  
**Status:** evidence for review, **NOT an independent security sign-off** and **NOT stable**.

## Candidate and reproducible CI evidence

- Hardening pull request: [#34](https://github.com/mykholy/mavvix-safe-actions/pull/34).
- PR head checked: `4ffed66f470ac1094e8899ccbf2202d385b7a008`. Verify the *merged main SHA* separately, including all required Actions, before any release.
- [Tests including MySQL 8.4 / PostgreSQL 16](https://github.com/mykholy/mavvix-safe-actions/actions/runs/39072503194): pass.
- [Browser end-to-end](https://github.com/mykholy/mavvix-safe-actions/actions/runs/39072503168): pass.
- [Static analysis](https://github.com/mykholy/mavvix-safe-actions/actions/runs/39072503189): pass.
- [Dependency security audit](https://github.com/mykholy/mavvix-safe-actions/actions/runs/39072503154): pass.
- [Clean Laravel host install](https://github.com/mykholy/mavvix-safe-actions/actions/runs/39072503250): pass.
- Runnable MySQL/PostgreSQL contention regression: `SAFE_ACTIONS_CONCURRENT_REPORT=/tmp/concurrent.jsonl vendor/bin/phpunit --filter ConcurrentLoadTest --fail-on-skipped` in a disposable test database with configured PDO and `pcntl`.

## Recorded synthetic contention timings

**Interpretation:** Wall-clock milliseconds for the full synchronized fork/test cohort on ephemeral CI runners, *not* per-request production latency or throughput. Each data point is a **single CI run**, not a P95/P99 estimate.

| Load scenario | MySQL 8.4 wall (ms) | PostgreSQL 16 wall (ms) | Outcome |
| --- | ---: | ---: | --- |
| 8 confirmations, one ticket, 100 selected rows | 198.079 | 246.363 | 1 commit, 7 rejected |
| 8 independently issued tickets for the same 100 rows | 226.597 | 258.382 | 1 commit, 7 rejected |
| 4 confirmations, one ticket, 500 rows | 372.524 | 485.498 | 1 commit, 3 rejected |
| 4 tenant-isolated confirmations, 100 rows each | 191.828 | 235.617 | 4 commits |

Both drivers retained correct selected-row counts and ticket-consumption semantics. There is **no** evidence here for 8 concurrent production HTTP users, a realistic database index profile, long-running observers or background job contention. See [benchmark methodology](benchmarks.md).

## Mandatory independent security review — still unsigned

The reviewer must be **independent of the implementation**, record their identity and dated findings, and verify all of:

- [ ] Authenticated actor, guard, panel, action and server-trusted tenant context cannot be spoofed.
- [ ] Forged Livewire selection, inverse exclusions, filters, global scopes, query aliases, unsupported pivots and changed selectability cannot expand the target set.
- [ ] Replay, stale scope, stale selected-row values, expired tickets and historical migrations fail closed.
- [ ] One connection, row locks, deadlock/timeout reporting and rollback guarantees match source and MySQL/PostgreSQL evidence.
- [ ] Declarative update cannot alter built-in ownership, identity or timestamps even when host config overrides the custom list.
- [ ] Eloquent hooks, after-commit listeners, queues, external API calls and side effects have explicit host constraints.
- [ ] Any high/critical findings are fixed with regression tests and the final reviewed SHA is recorded.

Reviewer: **not assigned**  
Approval/signature/date: **not provided**

## Mandatory human accessibility verification — still unsigned

On a real Filament 5 installation, test modal open, review, cancel, confirm, failed validation and focus return in **English LTR and Arabic RTL**. Use keyboard-only navigation plus **NVDA/JAWS on Windows or VoiceOver on macOS/iOS**, including a narrow/mobile view and dark appearance. Capture actual announced labels and errors, focus containment and restoration, and record browser/screen-reader versions.

Accessibility reviewer: **not assigned**  
Approval/signature/date: **not provided**

## Production-like qualification — still open

Run a representative host application with realistic row indexes, observers, several admins, background jobs and 1/100/500-row selections. Document concurrent request counts, wall duration, P50/P95/P99 across **many repetitions**, lock timeouts, deadlock counts, DB/CPU/memory usage and recovery. Define and review acceptance thresholds before testing; do not extrapolate the synthetic cohort measurements above.

## Release decision

**NO-GO for v1.0.0 until the outstanding review, accessibility, and production-qualification gates are evidenced.** A green CI matrix is not a substitute for independent sign-off. If all gates are later met, follow [the release playbook](release-playbook.md), freeze the approved SHA, tag it and verify exact Composer installation before publishing Packagist/Filament listings.
