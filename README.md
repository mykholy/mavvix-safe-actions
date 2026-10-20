# Mavvix Safe Actions

<div class="filament-hidden">
  <p align="center">
    <img src="https://raw.githubusercontent.com/mykholy/mavvix-safe-actions/main/docs/assets/mavvix-safe-actions-hero.webp" alt="Concept illustration of tenant-aware Filament bulk actions with preflight verification and database-only confirmation." width="100%">
  </p>

  <p align="center">
    <sub>Concept illustration only — for copyable code, use the actual API examples below.</sub>
  </p>
</div>

[![Tests](https://github.com/mykholy/mavvix-safe-actions/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/mykholy/mavvix-safe-actions/actions/workflows/tests.yml)
[![Browser E2E](https://github.com/mykholy/mavvix-safe-actions/actions/workflows/browser-e2e.yml/badge.svg?branch=main)](https://github.com/mykholy/mavvix-safe-actions/actions/workflows/browser-e2e.yml)
[![Latest GitHub Release](https://img.shields.io/github/v/release/mykholy/mavvix-safe-actions?label=release)](https://github.com/mykholy/mavvix-safe-actions/releases/latest)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/mykholy/mavvix-safe-actions/blob/main/LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%5E8.2-777bb4)](https://github.com/mykholy/mavvix-safe-actions/blob/main/composer.json)
[![Filament](https://img.shields.io/badge/Filament-5-orange)](https://filamentphp.com/)

**Fail-closed bulk actions for Filament 5.** Inspect the exact selection, show a bounded confirmation preview, re-check actor/tenant scope and permissions, then execute database-only changes in one transaction. **No silent truncation; no implicit authorization bypass.**

[Install](#installation) · [Code example](#native-filament-action) · [Declared preview](#read-only-declared-change-preview) · [Support matrix](#compatibility) · [Security](https://github.com/mykholy/mavvix-safe-actions/blob/main/docs/security-model.md) · [Changelog](https://github.com/mykholy/mavvix-safe-actions/blob/main/CHANGELOG.md) · [Contributing](https://github.com/mykholy/mavvix-safe-actions/blob/main/CONTRIBUTING.md)

> **Stable, narrowly scoped API — not a security certification.** Production integrations must supply their own trusted tenant query, policies and side-effect-safe callbacks. Automated MySQL/PostgreSQL and browser tests have passed; an independent security audit, human screen-reader review and production-shaped load qualification remain outstanding. See the [first-party security assessment](https://github.com/mykholy/mavvix-safe-actions/blob/main/docs/security-review-v1.0.0.md) and [security policy](https://github.com/mykholy/mavvix-safe-actions/blob/main/SECURITY.md).

## Installation

Install the published [Packagist package](https://packagist.org/packages/mykholy/mavvix-safe-actions) in a Laravel 12/13 application using Filament 5:

```bash
composer require 'mykholy/mavvix-safe-actions:^1.0'
php artisan vendor:publish --tag=safe-actions-migrations
php artisan migrate
```

The package registers its Laravel service provider automatically. Pin the stable `^1.0` release instead of `dev-main`, and run migrations before executing bulk actions. The host application must provide its own trusted tenant-scoped query and per-record authorization policy.

For maintainers preparing public listings, see the [Packagist submission checklist](https://github.com/mykholy/mavvix-safe-actions/blob/main/docs/packagist-submission.md) and [Filament plugin directory copy](https://github.com/mykholy/mavvix-safe-actions/blob/main/docs/filament-plugin-listing.md).


## Why Safe Actions?

Filament can show a selection that becomes stale or no longer matches a filtered query before the user confirms a bulk change. Safe Actions treats the original selection as **untrusted input** and refuses to proceed when its exact identity or authorization cannot be re-established.

| Without an explicit safety boundary | With Mavvix Safe Actions |
| --- | --- |
| The UI selection can become stale | Selected IDs, filter scope and persisted row state are checked again |
| A now-unauthorized row can be reached | Every selected row is re-authorized before any mutation |
| Repeated confirmation can cause duplicate work | A short-lived ticket is consumed once in the database transaction |
| Partial database updates can be committed | Supported same-connection changes share a transaction and row locks |
| Large “select all” operations can expand unexpectedly | A 500-record default cap fails closed rather than truncating |

**Important:** The package cannot make an unsafe host query, callback, model observer, external API call or cross-database operation safe automatically.

## How it works

1. Evaluate the current Filament table selection against the live filtered and tenant-scoped Eloquent query.
2. Reject missing, stale, out-of-scope, unselectable or unauthorized records.
3. Persist an expiring ticket bound to the actor, guard, tenant, panel, action, query fingerprint and selected model state.
4. Show the verified record count in a native Filament confirmation modal.
5. Re-resolve and lock the selected records, recheck policy and their persisted values, perform a single-database transaction, and consume the ticket.

The ticket can be used successfully only once. A failure aborts the transaction without consuming it.

## Compatibility

| Runtime / mode | Status |
| --- | --- |
| Filament 5 + Laravel 12 / PHP 8.2 and 8.3 | CI tested; Laravel 12 security support through February 24, 2027 |
| Filament 5 + Laravel 13 / PHP 8.4 | CI tested; independent host installation smoke tested |
| Laravel 11 | Not supported (upstream security support ended March 12, 2026) |
| Filament 3 / 4 | Not supported |
| Eloquent tables with single-column primary keys | Supported |
| Filtered, current-page and bounded all-matching selections | Livewire tests |
| Pivot-row identities and duplicate relationship rows | Refused |
| Grouped, HAVING, DISTINCT, JOIN or UNION source queries, or table aliases | Refused (identity resolution not proven) |
| Non-Eloquent data sources | Refused |
| Multi-database transactions | Refused |
| MySQL 8.4 / PostgreSQL 16 functional CI | Tested: two-process replay, stale rows, pre-commit crash and bounded lock-timeout failure; production load testing outstanding |

Selections are capped at **500 records** by default. For inverse (all-matching except explicitly deselected IDs) selection, the exclusion is applied to the filtered, tenant-scoped SQL query **before** checking the cap. Larger resulting selections still fail closed; this package does not silently truncate execution.

## What the package actually protects

| Guard | How it works |
| --- | --- |
| **Exact record set** | Resolves Eloquent primary keys from the current filtered, tenant-scoped table query; refuses mismatches |
| **Bounded preview** | Shows the verified row count and optionally a developer-declared field change summary (not a guaranteed complete diff) |
| **Fresh authorization** | Re-checks selected rows and the application's record policy on confirmation |
| **One-use preflight** | Persists a short-lived ticket bound to the actor, guard, panel, tenant, action and selected state |
| **Single-database transaction** | Locks selected records where supported, executes the supplied database-only mutation, and consumes its ticket atomically |

**Supported boundary:** Laravel 12/13, Filament 5, Eloquent tables with single-column primary keys, one database connection, and up to **500 selected records by default**. The application must provide the **correct tenant-scoped query**, trusted identity and authorization logic. External I/O and callbacks with irreversible side effects are not transactionally protected. See [limitations](#guarantees-and-limitations).

## Native Filament action

The action is opt-in. The developer supplies trusted request context, record authorization and a database-only mutation callback.

~~~php
use Filament\Facades\Filament;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Mavvix\SafeActions\Filament\SafeBulkAction;
use Mavvix\SafeActions\Preflight\PreflightContext;

public function table(Table $table): Table
{
    return $table
        ->query(Order::query()->where('tenant_id', $this->tenant->getKey()))
        ->toolbarActions([
            SafeBulkAction::make(
                name: 'archive',
                context: function (HasTable $livewire): PreflightContext {
                    $actor = Filament::auth()->user();
                    $tenant = Filament::getTenant();

                    return new PreflightContext(
                        actorType: $actor->getMorphClass(),
                        actorId: (string) $actor->getAuthIdentifier(),
                        guard: Filament::getCurrentPanel()->getAuthGuard(),
                        actionKey: 'archive',
                        tenantType: $tenant?->getMorphClass(),
                        tenantId: $tenant ? (string) $tenant->getKey() : null,
                        panelId: Filament::getCurrentPanel()->getId(),
                    );
                },
                authorize: fn (Model $record): bool => Gate::allows('update', $record),
                mutate: function (Model $record): void {
                    $record->status = 'archived';
                    $record->save();
                },
            ),
        ]);
}
~~~

Resolve the actor, guard, tenant and action key on the **server**. Never derive them from request input. The caller must enforce tenant boundaries in the table query, even when the Filament panel itself is tenant-aware.

The native action previews a **record count**, not a complete before/after data diff. It checks both the caller-supplied policy callback and the Filament row-selectability rule at confirmation.

## Read-only declared change preview

For common updates to a few scalar columns, use the optional declarative
action. This uses the **same server-declared values** for both the before/after
preview and the subsequent guarded mutation:

~~~php
SafeBulkAction::update(
    name: 'archive',
    context: $trustedContextResolver,
    authorize: fn (Model $order): bool => Gate::allows('update', $order),
    changes: ['status' => 'archived'],
)->label('Archive selected');
~~~

The modal displays each declared column's previous value (or "multiple values"
if the selection differs) and intended target value. It **does not run mutation
code during preview**. The displayed fields are not a full database diff:
model observers, casts, database triggers and external side effects may behave
differently. The configured mutation cannot contain arbitrary callbacks; use
the custom action when you need complex behavior. Protected identifiers, tenant_id, timestamps and common ownership columns
(organization_id, workspace_id, team_id, account_id, company_id and owner_id)
are always refused in the declarative API, even if the application overrides
its published configuration. To add custom tenant/ownership fields, publish
the configuration and edit `protected_update_columns`:

~~~bash
php artisan vendor:publish --tag=safe-actions-config
~~~

~~~php
'protected_update_columns' => ['branch_id'],
~~~

This only constrains `SafeBulkAction::update()`; custom mutation callbacks
still require explicit policy and tenant-boundary review. Always review Eloquent
observers before using either action in a real application.

## Headless API

~~~php
$scope = Order::query()->where('tenant_id', $tenant->getKey());

$inspection = app(\Mavvix\SafeActions\Selection\SelectionInspector::class)
    ->inspect($scope, $selectedIds, $authorize);

$ticket = app(\Mavvix\SafeActions\Preflight\PreflightStore::class)
    ->issue($inspection, $trustedContext, $scope);

// In a later authenticated confirmation request, reconstruct the scope:
$result = app(\Mavvix\SafeActions\Execution\GuardedExecutor::class)
    ->execute($ticket->id, $trustedContext, $freshScope, $authorize, $mutate);
~~~

The third argument to PreflightStore::issue() binds the ticket to a query fingerprint. Omitting it intentionally creates a **read-only ticket that cannot be executed**.

## Languages

The native Filament confirmation and safety errors are translated into English
and Arabic through Laravel's localization system. The current application locale
is used; the package does not override your panel's language or text direction.

To customize the strings:

~~~bash
php artisan vendor:publish --tag=safe-actions-translations
~~~

Edit the translated messages in the application's lang/vendor/safe-actions
directory. These translations only change presentation, never authorization.

## Ticket retention

Preflight tickets are short-lived, but their database rows are kept until pruned. On upgrading an earlier pre-alpha installation, publish and run the latest migrations: old outstanding tickets without a record-state fingerprint deliberately cannot execute and must be previewed again.
Schedule the built-in command in your Laravel application to prevent unbounded growth:

~~~bash
php artisan safe-actions:prune-preflights
php artisan safe-actions:prune-preflights --hours=48
~~~

The default retention is **24 hours after expiration or consumption**. The
command never deletes a currently valid, unconsumed ticket. It is not scheduled
automatically; add it to your application's Laravel scheduler as appropriate.
Do not treat these short-lived tickets as an immutable audit trail.

## Guarantees and limitations

- Revalidates selection identity, filtered query, actor/tenant context, per-record eligibility and persisted model values at execution. Any selected record changed since preview requires a new ticket, even when the query and IDs are unchanged.
- Serializes uses of the same ticket and locks the selected rows within a transaction where supported by the database.
- Rolls back database changes when a callback fails before commit. It **cannot** roll back external APIs, sent messages, dispatched jobs or mutations on another database connection.
- Suppresses Eloquent `retrieved` observers for package-owned read-only queries, but does not suppress mutation lifecycle events. Host authorization callbacks and Filament's own table hydration remain under host-application control.
- Cannot guarantee a field-level immutable diff, full protection against all cross-process races, or rollback after a successful commit.
- Rejects unsupported pivots and unbounded selections. Functional and two-process replay/stale-scope regression tests pass on MySQL and PostgreSQL; abrupt pre-commit process-crash recovery tests also pass. A small independent-PDO multi-worker contention suite exercises 4/8 confirmations across 100/500 rows, including competing and isolated tenants. These synthetic cases are not a production-scale workload; broader real-world capacity qualification remains required.
- Automated Chromium end-to-end coverage, keyboard focus and Escape cancellation, and axe-core scanning have passed; **human screen-reader/accessibility review**, **independent security review** and production-like concurrent load qualification remain outstanding despite the stable SemVer API.

Callbacks must be short-running, synchronous and database-only. Do not perform irreversible work from the mutation callback. Use an outbox and a separate worker if you need external effects.

## Tests and verification

The project runs a [PHP/Laravel matrix](https://github.com/mykholy/mavvix-safe-actions/blob/main/.github/workflows/tests.yml), [Chromium browser scenarios](https://github.com/mykholy/mavvix-safe-actions/blob/main/.github/workflows/browser-e2e.yml), [static analysis](https://github.com/mykholy/mavvix-safe-actions/blob/main/.github/workflows/static-analysis.yml), [Composer security audits](https://github.com/mykholy/mavvix-safe-actions/blob/main/.github/workflows/dependency-audit.yml) and [fresh host installations](https://github.com/mykholy/mavvix-safe-actions/blob/main/.github/workflows/host-installation.yml). The MySQL and PostgreSQL jobs cover replay, stale scopes, rollback and synthetic multi-worker contention. These tests are useful engineering evidence, **not** independent security certification or a production latency SLO.

```bash
composer validate --strict
vendor/bin/phpunit
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
```

## Documentation and development

[Packagist submission](https://github.com/mykholy/mavvix-safe-actions/blob/main/docs/packagist-submission.md) · [Filament listing](https://github.com/mykholy/mavvix-safe-actions/blob/main/docs/filament-plugin-listing.md) · [Implementation plan](https://github.com/mykholy/mavvix-safe-actions/blob/main/IMPLEMENTATION_PLAN.md) · [Release playbook](https://github.com/mykholy/mavvix-safe-actions/blob/main/docs/release-playbook.md) · [Compatibility verification](https://github.com/mykholy/mavvix-safe-actions/blob/main/docs/compatibility-verification.md) · [Task board](https://github.com/mykholy/mavvix-safe-actions/blob/main/TASKS.md) · [Selection compatibility](https://github.com/mykholy/mavvix-safe-actions/blob/main/docs/selection-compatibility.md) · [Security model](https://github.com/mykholy/mavvix-safe-actions/blob/main/docs/security-model.md) · [Measured CI benchmarks](https://github.com/mykholy/mavvix-safe-actions/blob/main/docs/benchmark-results-2026-10-17.md) · [First-party security review](https://github.com/mykholy/mavvix-safe-actions/blob/main/docs/security-review-v1.0.0.md) · [v1.0.0 release notes](https://github.com/mykholy/mavvix-safe-actions/blob/main/docs/releases/v1.0.0.md) · [Release review packet](https://github.com/mykholy/mavvix-safe-actions/blob/main/docs/release-candidate-review-packet-2026-10-18.md) · [Release readiness](https://github.com/mykholy/mavvix-safe-actions/blob/main/docs/release-readiness-2026-10-17.md) · [Changelog](https://github.com/mykholy/mavvix-safe-actions/blob/main/CHANGELOG.md) · [Contributing](https://github.com/mykholy/mavvix-safe-actions/blob/main/CONTRIBUTING.md) · [CI](https://github.com/mykholy/mavvix-safe-actions/blob/main/.github/workflows/tests.yml)

To report a vulnerability, use a private GitHub Security Advisory if available. Do not publish exploit details in a public issue.

**License:** MIT — see [LICENSE](https://github.com/mykholy/mavvix-safe-actions/blob/main/LICENSE).
