# Mavvix Safe Actions — Implementation Plan

**Status:** Pre-alpha implementation underway — selection adapter and read-only preflight storage complete; mutation execution not implemented  
**Date:** 17 October 2026  
**Product:** Free, open-source FilamentPHP plugin  
**Composer package:** `mykholy/mavvix-safe-actions` (development branch; not published on Packagist)  
**License:** MIT (planned)  
**Primary stack:** Filament 5, PHP 8.2+, Laravel 11.28+/12/13 (respect each Laravel version's PHP constraints)

> Product promise: Preview the exact intended bulk-action scope, enforce record-level authorization, and revalidate before changing data. Fail closed when the target scope cannot be proven.

## 1. Why this package exists

Filament already offers `authorizeIndividualRecords()`, `chunkSelectedRecords()`, selectable-row constraints, and bulk-action notifications. Existing plugins also cover preflight/invariants (ActionGuard) and asynchronous bulk execution (Queueable Bulk Actions). **We will not market existing features as inventions.**

Our differentiation is a narrow, independently tested workflow that connects **exact selection → server-side target resolution → reviewable preflight → guarded execution** and makes discrepancies visible instead of silently expanding the scope. See references at the end.

### Target users

- Teams using Filament admin panels in production.
- Developers working with high-impact status changes, assignments, and operational bulk updates.
- Multi-tenant applications where modifying another tenant's record would be a security incident.

### Non-goals for the first stable version

- General audit-logging suite, approvals, job dashboard, automatic rollback, or a full workflow engine.
- Arbitrary SQL actions, raw-query updates, cross-database changes, external API side-effects, or irreversible destructive operations with a safety guarantee.
- Undocumented automatic coverage of all Filament selection modes. **`Select all matching` ships only if proven with real Filament integration tests; otherwise it is deliberately disabled and documented.**
- Filament 4 or 3 compatibility without independent test coverage; no assumption that 5.x adapters can simply be reused.

## 2. The v1 contract

### A. A guarded action

The developer declares an operation, a per-record policy, and a pure preview of intended changes. A user first sees an impact summary; execution is allowed only after a fresh check.

**Conceptual API — to be finalized after the Filament integration spike:**

```php
use Mavvix\SafeActions\Actions\SafeBulkAction;

SafeBulkAction::make('archiveOrders')
    ->label('Archive selected orders')
    ->authorizeEach('update')
    ->preview(fn (Order $record): array => [
        'status' => ['from' => $record->status, 'to' => 'archived'],
    ])
    ->mutate(fn (Order $record): void => $record->update([
        'status' => 'archived',
    ]))
    ->requiresConfirmation();
```

Do **not** implement this exact fluent interface blindly. Validate feasibility against Filament 5 public extension points and adjust naming/signatures to idiomatic Filament conventions. Never bypass Filament's own authorization machinery.

### B. Visible preflight result

- Selected and resolved record counts; approved, denied, disappeared, stale, and excluded counts.
- Reason categories without disclosing other tenants' sensitive data.
- Field-level impact summary for declared, supported database changes; sample details with a configurable display cap.
- Scope mismatch warning; confirmation disabled if exact intended targets cannot be established.
- Preflight created-at, expiration, executing actor, action, panel and tenant context.

### C. Guarded execution

- Require a fresh server-side token linked to actor, action, panel, tenant and server-stored target snapshot.
- Re-resolve target keys through the original permitted table/model query and preserve global scopes.
- Re-authorize every target on the server **at mutation time**, with a lock for supported same-database operations when applicable.
- Execute at most once for a given run. Duplicate submits and concurrent attempts must not double-apply changes.
- Report explicit result: completed / rejected / partially completed / failed, with counts and safe failure reasons.
- No side effects during preflight. A user closing the modal cannot alter data.

### D. Reliable safety language

- Preflight is an observation, **not** a guarantee that rows cannot change afterward.
- Revalidation narrows risk, but it does not make multi-transaction execution globally atomic.
- Database transactions and row locks are only offered where supported by the configured driver and mutation type.
- `fail_all` is advertised as atomic only for a **bounded, single-database transaction** with no external side effects. In other cases, the action must reject or disclose explicitly that it is best-effort/partial.
- Unrecognized selection modes, missing tenant scoping, unsupported keys or unverifiable source queries fail closed.

## 3. Supported behavior and explicit limits

| Capability | v1 stable target | Failure mode |
| --- | --- | --- |
| Manually selected row IDs | Supported with full end-to-end verification | Reject unknown/mismatched keys |
| Current-page selection | Supported after adapter test | Reject if scope cannot be replayed |
| All matching filtered records | Conditional; do not advertise until proven | Disable with clear explanation |
| Search/filter scoped query | Re-resolve server-side using Filament public APIs | Reject changed scope |
| Policy authorization | Mandatory per target | Reject or explicitly skip according to configured strategy |
| Tenant isolation | Preserve host app global/panel scopes; verify current tenant | Reject any cross-tenant target |
| Integer and UUID/string model keys | Supported after tested serialization and lookup | Reject unsupported/composite keys |
| Synchronous operations | Bounded and transactional where supported | Refuse excessive size, suggest alternative |
| Asynchronous jobs/progress | Out of initial stable scope | Future iteration / separate module |
| Undo/rollback | Out of initial stable scope | No rollback claim |
| External effects: mail, payment, webhooks | Not guaranteed safe | Developer must use a separate, explicitly documented integration |

**Default limits, to calibrate in proof-of-concept:** 500 targets per synchronous run, 5-minute preflight expiry, 25 displayed detail rows. Caps are configurable only within documented supported ranges. Increase limits **only after measured workload tests**; never increase them solely to claim 10,000+ support.

A demo may contain 100,000 total database rows, but that is **not** a claim that 100,000 synchronous mutations are supported.

## 4. Technical architecture

**Dependency direction:** `Filament adapter → application services → domain/value objects`. The safety domain must not import Livewire or Filament classes. Keep the package deliberately small; do not create repositories/interfaces unless they isolate a real boundary.

```text
src/
  Actions/
    SafeBulkAction.php              # thin Filament adapter
  Selection/
    FilamentSelectionResolver.php   # server-side table scope integration
    SelectionSnapshot.php           # immutable value object
  Preflight/
    PreflightService.php
    PreflightResult.php
    ImpactSummary.php
  Execution/
    SafeActionExecutor.php
    ExecutionResult.php
    ExecutionMode.php
  Security/
    TargetAuthorizer.php
    ScopeVerifier.php
    ContextBinder.php
  Models/
    SafeActionRun.php
    SafeActionTarget.php
  SafeActionsServiceProvider.php
config/safe-actions.php
resources/views/actions/preflight.blade.php
lang/en/...
lang/ar/...
database/migrations/...
tests/Unit/
tests/Feature/
tests/Integration/
workbench/                 # demo/test host app, if adopted
```

**File layout is a proposed boundary map, not a quota.** Combine classes where that improves cohesion; avoid empty directories, `Manager`/`Helper` classes with no clear responsibility, and one-method abstractions added for aesthetics.

### Persistence

Use a host-app database with publishable migrations and two small tables initially:

- `safe_action_runs`: opaque UUID, actor morph identity, guard, panel, tenant identity/type, action key, source model alias, state, scope digest, preview time, expiry, summary counts, failure category and timestamps.
- `safe_action_targets`: run UUID, ordinal/index, model key in lossless typed representation, initial eligibility state, diagnostic code (not sensitive record data); uniqueness `(run_id, typed_key)` or a stable equivalent.

Never serialize an Eloquent builder, executable Closure, authorization result object, credentials or raw model data into a run. Resolve all behavior from developer-registered action definitions at runtime. No `unserialize()` of user-controlled data. No user-submitted class names or raw SQL.

### State machine

```text
CREATED → PREFLIGHTED → EXECUTING → COMPLETED
                    ↘ EXPIRED
                    ↘ REJECTED
EXECUTING → PARTIAL | FAILED
```

State transitions must be validated under a database lock / compare-and-swap equivalent. The confirmation token is one-time, unpredictable and bound to the authenticated context. Cleanup of expired runs must be documented; do not silently delete evidence before the configured retention period.

### Selection protocol

1. Filament presents a selection; adapter identifies the actual mode and allowed server-side query context.
2. Resolve explicit keys against the scoped query, preserving `where`, filters, search, global scopes, SoftDeletes and Filament tenancy.
3. Store the resolved target **key set** and a digest of the relevant context; never rely on row count alone for target identity.
4. Compare resolved, selected, excluded and forbidden targets. If the selection shape is unrecognized, reject.
5. Display a read-only preview; do not call mutate/event code.
6. On confirmation, verify actor/guard/panel/tenant/action/expiry/one-time state and the stored key set.
7. Re-resolve under current authorization and tenant boundaries; reject stale/changed scope according to contract, then execute with per-record checks and clearly defined transaction behavior.

Beware of the TOCTOU gap between global prevalidation and later chunk mutations. For mutations that require no partial results, choose a bounded single-transaction execution and lock affected rows; do not promise atomicity for anything else.

## 5. Engineering decisions to validate during the initial spike

These are **release blockers**, not details left for the last PR:

- Which Filament 5 **public** APIs expose raw selected keys, current-page selection, filtered selection, `Select all matching` state, and the underlying scoped query? Inspect installed source and integration behavior.
- Can the adapter reproduce the same exact set seen by the UI with no private property access, monkey patch, or fragile Livewire DOM hack?
- How does `checkIfRecordIsSelectableUsing()` interact with server resolution? Test the case where the UI excludes records but all-matching includes them.
- How should policy-denied rows count without leaking sensitive tenant information?
- Which database drivers support locking/transaction strategy reliably? Validate SQLite for fast tests; MySQL and PostgreSQL for real locking tests.
- Do native Filament modal lifecycle hooks support a two-phase preview/confirmation cleanly, including cancellation and expired state?

**Gate:** No production implementation of the `SafeBulkAction` facade until the spike demonstrates one correct feature test each for explicit selection, filtered selection, forbidden row and tenant isolation.

## 6. Execution roadmap, ordered as pull requests

### PR-00 — Foundation and technical spike

- [ ] Confirm package/vendor naming, namespace, Composer availability and trademark use.
- [ ] Create isolated package and small Laravel + Filament 5 host demo.
- [ ] Pin a supported Composer matrix; test PHP 8.2/8.3+ only in valid framework combinations (Laravel 13 requires PHP 8.3+).
- [ ] Investigate Filament selection internals/public APIs and document findings in `docs/selection-compatibility.md`.
- [ ] Reproduce explicit selection, filtered selection and non-selectable row behavior using Livewire feature tests.
- [ ] Decide supported selection modes using actual passing tests.

**Exit:** reproducible tests and written adapter contract; no UI marketing claims.

### PR-01 — Package core and contracts

- [ ] PSR-4, auto-discovery service provider, local demo/workbench, publishable config and migration.
- [ ] Implement immutable result/snapshot objects and enum states.
- [ ] Create database schema with indexes, schema isolation and safe retention configuration.
- [ ] Validate install, migration up/down, uninstall behavior and two panels without state leakage.

**Exit:** installable package with passing lifecycle tests.

### PR-02 — Exact target resolution

- [ ] Build `FilamentSelectionResolver` as a thin adapter.
- [ ] Persist exact selection with stable typed keys and scoped context.
- [ ] Handle explicit/current page selection; all-matching only if independently proven.
- [ ] Reconcile missing, inaccessible, soft-deleted, filtered-out, duplicate and stale keys.
- [ ] Test 842/828 mismatch and 101/18 prohibited-row regression cases.

**Exit:** zero unauthorized or out-of-scope targets returned as actionable.

### PR-03 — Preflight & UI

- [ ] Pure preview contract, deterministic impact counts and diff samples.
- [ ] Accessible Filament-native modal, EN/AR copy, dark/light appearance.
- [ ] Display selection context, excluded categories and preview-expiry state.
- [ ] Prevent confirmation on any unresolved scope mismatch.
- [ ] UI test verifies preview and cancel have no DB mutations, queued jobs or external calls.

**Exit:** what the user sees corresponds to the exact server-saved target set.

### PR-04 — Guarded execution

- [ ] One-time run state and duplicate-click prevention.
- [ ] Actor, policy, tenant, action and target revalidation.
- [ ] Bounded transaction mode for declared database-only operations; lock/revalidate safely.
- [ ] Deterministic failure policy and honest partial/failure status.
- [ ] Instrument counts and diagnostic codes without logging sensitive rows.
- [ ] Test double submit, competing admins, permission changes and stale preview.

**Exit:** no mutation can occur from a canceled, expired, reused, unauthorized or changed-context preview.

### PR-05 — Quality, compatibility and performance

- [ ] Pest unit/integration/Livewire UI tests (use supported framework versions).
- [ ] PHPStan/Larastan at the highest sustainable configured strictness; Pint; Composer validate.
- [ ] GitHub Actions with valid PHP/Laravel/Filament matrix; dependency auditing and secret scan.
- [ ] MySQL and PostgreSQL integration jobs for locking/transaction cases.
- [ ] 10k/100k dataset fixtures; measure preflight throughput, p95 time, peak RAM and query count; publish machine and database configuration.
- [ ] Fuzz/edge tests for string/UUID keys, unusual input and unauthorized object references.

**Exit:** all supported test matrix cells green, no known high-severity security defects, performance numbers documented rather than invented.

### PR-06 — Documentation, release, distribution

- [ ] README: clear value proposition, install, 60-second snippet, UI screenshots, limitations, compatibility matrix.
- [ ] `docs/`: architecture, selection semantics, supported drivers, auth/tenant security, performance and migration guide.
- [ ] Real screenshots and short demo; CHANGELOG, CONTRIBUTING, SECURITY, CODE_OF_CONDUCT, LICENSE.
- [ ] Tag a release candidate, run clean-room Composer install in a new Laravel app.
- [ ] Run one external developer review and incorporate critical feedback.
- [ ] Only then tag `v1.0.0`, verify Packagist availability and submit to Filament Plugins.

**Exit:** installation and usage work from public docs without maintainer intervention; release tag follows evidence, not a deadline.

## 7. Test and acceptance matrix

### Required attack/regression tests

- A select-all UI says 828 but underlying query sees 842: **0 of the unintended 14 mutated**.
- 101 action candidates include 18 non-selectable records: **0 prohibited mutations**.
- Filters/search change after preview: reject or re-preview; **never silently expand**.
- Actor loses `update` permission after preview: block that record; no success claim for it.
- User switches tenant, panel or auth guard: old token fails; no foreign-tenant mutation.
- Selected UUID, duplicate IDs, missing/deleted/soft-deleted records: result consistent and bounded.
- Preview expiry, canceled modal, replayed token, concurrent duplicate requests: **no accidental mutation**.
- Exception midway: documented transaction mode determines atomic vs partial outcome; reports are accurate.
- Model callbacks and observers do not run during preview.
- Execution cannot bypass row eligibility via raw `Model::where()->update()` paths presented as guarded.

### Definition of Done for each PR

- Public behavior documented and covered by a failing test before the fix (where feasible).
- Code formatted; static analysis clean; all relevant tests green locally and in CI.
- No suppressed exceptions, ignored tests, leftover debug code, TODO-shaped production implementations, or unreviewed dependency bumps.
- Security and backward-compatibility effects explained in PR description.
- README/example updated with behavior changes. No unrelated refactors in safety-critical PRs.

### Stable-release gate

`v1.0.0` is **blocked** until: full supported CI matrix is green; clean install works; regression tests pass; docs match actual runtime; package has a clear security policy; race/tenant limitations are honestly specified; and one external developer validates the core flow. Avoid arbitrary coverage or speed claims; publish measured outcomes.

## 8. Code standards: professional, readable and maintainable

1. Follow Laravel and Filament conventions over novelty. Prefer framework-provided primitives where they satisfy a verified contract.
2. Small classes with one clear reason to change; private helpers only where they simplify a complex method.
3. Use strict types and purposeful return types. Name domain concepts explicitly: `TargetSnapshot`, `ScopeMismatch`, `PreflightResult`, not `DataManager` or `CommonHelper`.
4. Separate pure preview/validation from mutation. Never run business callbacks to “simulate” a preview.
5. Use interfaces only across meaningful boundaries (e.g. independently replaceable target resolver), not one interface per class.
6. Limit comments to non-obvious invariants, tricky race conditions and rationale. No redundant docblocks or decorative code comments.
7. Avoid duplicate Filament logic, hidden magic, reflection into protected internals or giant service classes.
8. Error messages are actionable and accurate. Never swallow errors to produce a green notification.
9. Changes are small and reviewable; commits/PRs explain **why** a safety decision exists.
10. No fake benchmarks, fabricated tests, invented usage, manipulated authorship statements or claims that a package was manually written if that is not true. The target is human-reviewable engineering quality, not concealment.

## 9. Release and commercial strategy

- Free v1: MIT, zero license activation, core safety workflow complete and usable independently.
- Do not place ordinary security patches behind a Pro paywall.
- Possible later separate **Mavvix Controlled Changes Pro**: change-set history, approval workflows, queued high-volume execution with progress, supported compensation/rollback for narrowly declared operations. Validate demand first; do not architect v1 around unshipped features.
- Public launch surfaces: GitHub, Packagist, Filament plugin directory, technical Reddit/LinkedIn demo; show a real 842/828 or 101/18 reproduction and passing regression fix.

## 10. Primary documentation and adjacent products

- Filament 5 bulk action semantics: https://filamentphp.com/docs/5.x/tables/actions
- Filament 5 testing actions: https://filamentphp.com/docs/5.x/testing/testing-actions
- Filament 5 installation requirements: https://filamentphp.com/docs/5.x/introduction/installation
- Filament 5 plugin development: https://filamentphp.com/docs/5.x/plugins/getting-started
- Laravel 13 server requirements: https://laravel.com/framework/docs/deployment
- ActionGuard (adjacent, preflight invariants): https://filamentphp.com/plugins/aleksey-krivonos-actionguard
- Queueable Bulk Actions (adjacent, async processing): https://filamentphp.com/plugins/eddie-rusinskas-queueable-bulk-actions
- Filament bulk selection issue: https://github.com/filamentphp/filament/issues/20050
- Selection count mismatch issue: https://github.com/filamentphp/filament/issues/19219

---

**Current project state:** This document is an implementation specification. Repository creation, code, tests, benchmarks, CI and publication have not been completed by writing this document.
