# Mavvix Safe Actions v1.0.0 — first-party security assessment

**Review date:** 2026-10-18 (Africa/Cairo)  
**Reviewed code baseline:** `be676e64ecac60d0e309f71e9edd716a2406f37c`  
**Assessment type:** internal source-control checklist supported by recorded automated tests and repository CI; not an independent audit.  
**Independence:** **NOT INDEPENDENT.** This document is project-maintained evidence, not an external security audit, accredited penetration test, human reviewer attestation, or cryptographic signature.
**Documentation revision:** 2026-10-19. Published release tags retain their original immutable documentation.

## Review scope and conclusions

| Security control | Inspection and evidence | Assessment |
| --- | --- | --- |
| Input identities | `ExplicitKeys` requires canonical bounded keys, rejects empty, duplicates, malformed numbers, and >500 by default | Implemented and covered by tests |
| Authorization | `SelectionInspector`, `FilamentSelectionInspector` and `GuardedExecutor` re-evaluate record access, selectability and changed scope | Verified in source/CI; **host must provide correct policies and tenant scope** |
| Context binding | `PreflightStore` binds actor, guard, tenant, panel, action and short expiration to the ticket | Implemented and tested |
| Query and row changes | `ScopeFingerprint` hashes query/bindings; `RecordStateFingerprint` compares persisted selected attributes | Tested optimistic protection, **not** an immutable ledger or authenticated MAC |
| Duplicate confirmation | Ticket row locked, consumed with business records in one transaction; no automatic mutation retries | MySQL 8.4 / PostgreSQL 16 replay, stale-scope, crash, lock-timeout and multi-worker test evidence |
| Unsupported SQL shapes | `ScopeShapeGuard` rejects aliases, joins, aggregates, grouped/having/distinct/union selections | Source verified; all unsupported model/SQL configurations are not exhaustively proven |
| Declarative changes | Built-in ownership fields, identifiers and timestamps are blocked even if custom config is replaced | Regression-tested. Custom callbacks are **not** subject to this field allowlist |
| Read-only preview | No mutation callback is executed during preflight; package-owned Eloquent hydration suppresses `retrieved` hooks | CI covers package-owned queries; host callbacks must remain pure |
| Database rollback | Same-connection database mutation and ticket consumption share a transaction | MySQL/PostgreSQL regression evidence; external APIs/jobs/files are excluded |
| Dependencies | Automated Composer dependency audit and PHPStan/Larastan workflows passed | Point-in-time dependency check; not a proof of no undiscovered vulnerabilities |
| Browser interaction | Real Chromium E2E, English/Arabic and automated accessibility checks passed | No live NVDA/JAWS/VoiceOver human assessment |

## CI evidence: exact baseline SHA

- [Tests: Laravel/PHP and MySQL/PostgreSQL](https://github.com/mykholy/mavvix-safe-actions/actions/runs/39072809335)
- [Static analysis](https://github.com/mykholy/mavvix-safe-actions/actions/runs/39072809417)
- [Dependency audit](https://github.com/mykholy/mavvix-safe-actions/actions/runs/39072809329)
- [Clean host installation](https://github.com/mykholy/mavvix-safe-actions/actions/runs/39072809337)
- [Browser E2E](https://github.com/mykholy/mavvix-safe-actions/actions/runs/39072809429)

All five workflows completed successfully on baseline SHA. **The v1.0.0 tag must point to a new, fully green release commit with these changes plus review documents and workflow.** A release automation will check the exact final main commit before tagging.

## Threats and limitations that remain

1. **Host application trust boundary:** The caller must build the authenticated, tenant-scoped Eloquent query and authorization callback from server-trusted state. A developer can explicitly supply an insecure scope or bypass Laravel Gate; the plugin cannot infer a missing tenant boundary.
2. **External side effects:** Eloquent observers, cross-database mutations, queued jobs, API requests, emails, webhooks and file writes may not roll back. Host implementers must use a transactional outbox and after-commit processing where needed.
3. **Mutation callback:** `SafeBulkAction::make()` accepts arbitrary developer PHP; the plugin does not sandbox or validate its side effects. Custom code can mutate fields not listed in the declarative update protection.
4. **Read-only policy callbacks:** Host-provided authorization, table selectability, global scopes and model accessors must not have side effects.
5. **Identity/driver portability:** Non-Eloquent sources, join/group/distinct/union shapes, pivots and duplicate relationship rows are unsupported. Other database engines are not concurrency-certified.
6. **Residual review:** Independent third-party security testing, manual assistive-tech testing, and production-shaped concurrency/load qualification are *not completed*. The maintainer chooses to publish a narrowly scoped SemVer stable API despite those outstanding recommended assurance activities.
7. **No performance or absolute safety warranty:** Small synthetic contention tests and sequential benchmarks do not establish a production SLO or security certification.

## Findings and treatment

- **Resolved in PR #33:** Inverse deselection exclusion applied before bounded scan; ownership columns configurable.
- **Resolved in PR #34:** Common ownership column restrictions cannot be disabled by host configuration. Independent-PDO MySQL/PostgreSQL multi-worker regression added.
- **No newly demonstrated critical exploit** was established during this desk review. This does **not** mean no vulnerabilities exist or that independent testing has been performed.
- **Open recommended reviews:** independent security and human accessibility assessments ([#6](https://github.com/mykholy/mavvix-safe-actions/issues/6), [#5](https://github.com/mykholy/mavvix-safe-actions/issues/5)).

## First-party assessment and disclosure

**First-party review outcome:** No reproduced blocker from the inspected source and recorded CI within the supported narrow contract. Proceed with an **explicitly maintainer-directed, scoped v1.0.0 release** only if the exact final main commit passes all five CI workflows. Preserve the outstanding issues and caveats; do not advertise the release as independently security-certified or fully production-qualified.

**Independent sign-off:** Not obtained. No individual reviewer or external organization is represented as having certified the package.  
**Release authorization:** The project maintainer directed a scoped stable release; that decision is distinct from independent security approval.
