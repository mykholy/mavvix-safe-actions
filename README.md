# Mavvix Safe Actions

**Guarded selections for consequential Filament bulk actions.**

> **Development preview — not ready for production use.** No stable release or Packagist publication yet.

A selected checkbox is not an authorization decision. Mavvix Safe Actions is being built to help Filament teams validate the *exact* set of records affected by a bulk operation before it changes production data.

## Current status

This first engineering increment includes:

- A Laravel auto-discovered package and publishable configuration.
- `SelectionInspector` for explicit IDs, plus a bounded `FilamentSelectionInspector` adapter using the live filtered table query.
- Strict, bounded primary-key validation; exact set comparison; per-record eligibility callbacks.
- Regression fixtures for filtered-out, unauthorized, missing and cross-tenant records.
- PHP 8.3 / Laravel 12 and PHP 8.4 / Laravel 13 CI coverage, including native Filament Livewire tests for filters, tenant scope, selectable rows and bounded all-matching.

**It does not yet integrate into Filament action modals, save preflight snapshots, confirm or execute bulk mutations.** Do not use its result as an authorization token or as a guarantee against concurrent changes. The caller is responsible for constructing the correct scoped Eloquent query and invoking actual Laravel policies in their callback.

## Development setup

The implementation currently lives in a draft pull request, not on `main`. To inspect and test the code:

```bash
git clone https://github.com/mykholy/mavvix-safe-actions.git
cd mavvix-safe-actions
git switch feat/pr00-selection-foundation
composer install
vendor/bin/phpunit
vendor/bin/pint --test
```

Do not install `dev-main` as an application dependency yet: the repository has no published package release or production-ready action API. This branch is for development and review only.

## Selection inspector (internal integration API, subject to change)

```php
use Illuminate\Database\Eloquent\Model;
use Mavvix\SafeActions\Selection\SelectionInspector;

$inspection = app(SelectionInspector::class)->inspect(
    Order::query()->where('tenant_id', $tenant->getKey())->where('status', 'pending'),
    [42, 43],
    fn (Model $order): bool => Gate::forUser($user)->allows('update', $order),
);

// $inspection->recordCount === 2
```

The headless inspector needs a trusted caller-supplied query and key list. The Filament adapter reads its current Livewire selection but must only be used for inspection, never as an execution authorization. Inspecting never calls model writes, but your own authorization callback must also be read-only. No action should mutate based on this inspection alone: scope, tenant, actor and policies must be revalidated inside the eventual execution boundary.

## Filament integration (read-only)

The `FilamentSelectionInspector` takes an actual Livewire component implementing `HasTable`. It inspects explicitly selected rows and **bounded** all-matching inverse selections using Filament's filtered query, the row-selectability closure and your authorization callback. A strict limit (500 by default) prevents unbounded inverse scans. It fails closed for pivot-row identities, non-Eloquent data sources and unsupported modes.

```php
$inspection = app(\Mavvix\SafeActions\Selection\FilamentSelectionInspector::class)
    ->inspect($this, fn (Model $record): bool => Gate::allows('update', $record));
```

Only call this from a Livewire component whose table query already scopes the actor's tenant. This adapter **does not** bind its result to an actor, make a transaction, or authorize a future mutation. No action execution API is shipped.

## Roadmap

See [implementation plan](IMPLEMENTATION_PLAN.md), [task board](TASKS.md) and [selection compatibility](docs/selection-compatibility.md). We will test the real Filament v5 selection modes before providing a public `SafeBulkAction` facade.

## Security

Please do not assume preflight results guarantee safe execution. If you discover a vulnerability or scope-bypass problem, open a private GitHub security advisory when available rather than posting exploitation details in a public issue.

## License

MIT. See [LICENSE](LICENSE).
