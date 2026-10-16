# Mavvix Safe Actions

**Guarded selections for consequential Filament bulk actions.**

> **Development preview — not ready for production use.** No stable release or Packagist publication yet.

A selected checkbox is not an authorization decision. Mavvix Safe Actions is being built to help Filament teams validate the *exact* set of records affected by a bulk operation before it changes production data.

## Current status

This first engineering increment includes:

- A Laravel auto-discovered package and publishable configuration.
- `SelectionInspector`, a read-only, fail-closed check for explicitly supplied record IDs.
- Strict, bounded primary-key validation; exact set comparison; per-record eligibility callbacks.
- Regression fixtures for filtered-out, unauthorized, missing and cross-tenant records.
- A passing PHP 8.3 / Laravel 12 and PHP 8.4 / Laravel 13 CI matrix, including a native Filament Livewire selection probe.

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

Selection IDs **must** come from a trusted server-side representation, not arbitrary browser-submitted IDs. Inspecting never calls model writes, but your own authorization callback must also be read-only. No action should mutate based on this inspection alone: scope, tenant, actor and policies must be revalidated inside the eventual execution boundary.

## Roadmap

See [implementation plan](IMPLEMENTATION_PLAN.md), [task board](TASKS.md) and [selection compatibility](docs/selection-compatibility.md). We will test the real Filament v5 selection modes before providing a public `SafeBulkAction` facade.

## Security

Please do not assume preflight results guarantee safe execution. If you discover a vulnerability or scope-bypass problem, open a private GitHub security advisory when available rather than posting exploitation details in a public issue.

## License

MIT. See [LICENSE](LICENSE).
