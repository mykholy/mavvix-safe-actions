# Mavvix Safe Actions

**Fail-closed bulk changes for Filament 5.** Resolve the exact selection, preview its size, revalidate at confirmation, and mutate records within a single database transaction.

> **Pre-alpha on main.** No Packagist publication or stable tag yet; production use is not recommended.

## How it works

1. Evaluate the current Filament table selection against the live filtered and tenant-scoped Eloquent query.
2. Reject missing, stale, out-of-scope, unselectable or unauthorized records.
3. Persist an expiring ticket bound to the actor, guard, tenant, panel, action and query fingerprint.
4. Show the verified record count in a native Filament confirmation modal.
5. Re-resolve and lock the selected records, check policies again, perform a single-database transaction, and consume the ticket.

The ticket can be used successfully only once. A failure aborts the transaction without consuming it.

## Compatibility

| Runtime / mode | Status |
| --- | --- |
| Filament 5 + Laravel 12 / PHP 8.3 | CI tested |
| Filament 5 + Laravel 13 / PHP 8.4 | CI tested |
| Laravel 11 | Not yet validated against secure framework versions |
| Filament 3 / 4 | Not supported |
| Eloquent tables with single-column primary keys | Supported |
| Filtered, current-page and bounded all-matching selections | Livewire tests |
| Pivot-row identities and duplicate relationship rows | Refused |
| Non-Eloquent data sources | Refused |
| Multi-database transactions | Refused |
| MySQL 8.4 / PostgreSQL 16 functional CI | Tested (parallel concurrency testing outstanding) |

Selections are capped at **500 records** by default. Larger selections fail closed; this package does not silently truncate execution.

## Install for development

Packagist publication is not available yet. In a disposable Filament 5 application:

~~~bash
composer config repositories.mavvix vcs https://github.com/mykholy/mavvix-safe-actions
composer require "mykholy/mavvix-safe-actions:dev-main"
php artisan vendor:publish --tag=safe-actions-migrations
php artisan migrate
~~~

Clone the default branch and run composer install, vendor/bin/phpunit and vendor/bin/pint --test.

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

Preflight tickets are short-lived, but their database rows are kept until pruned.
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

- Revalidates selection identity, filtered query, actor/tenant context and per-record eligibility at execution.
- Serializes uses of the same ticket and locks the selected rows within a transaction where supported by the database.
- Rolls back database changes when a callback fails before commit. It **cannot** roll back external APIs, sent messages, dispatched jobs or mutations on another database connection.
- Cannot guarantee a field-level immutable diff, full protection against all cross-process races, or rollback after a successful commit.
- Rejects unsupported pivots and unbounded selections. Functional tests pass on MySQL and PostgreSQL; parallel multi-session concurrency tests are still required.
- Still requires security review, real-driver concurrency tests, browser coverage and a supported-version matrix before v1.0.0.

Callbacks must be short-running, synchronous and database-only. Do not perform irreversible work from the mutation callback. Use an outbox and a separate worker if you need external effects.

## Documentation and development

[Implementation plan](IMPLEMENTATION_PLAN.md) · [Task board](TASKS.md) · [Selection compatibility](docs/selection-compatibility.md) · [Security model](docs/security-model.md) · [Changelog](CHANGELOG.md) · [Contributing](CONTRIBUTING.md) · [CI](.github/workflows/tests.yml)

To report a vulnerability, use a private GitHub Security Advisory if available. Do not publish exploit details in a public issue.

**License:** MIT — see [LICENSE](LICENSE).
