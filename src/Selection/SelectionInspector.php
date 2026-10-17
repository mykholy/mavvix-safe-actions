<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Selection;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;

final class SelectionInspector
{
    /**
     * Inspects an explicit selection without modifying records. The caller must
     * supply the exact permitted query, including its filters and tenant scope.
     *
     * @param  array<array-key, mixed>  $selectedKeys
     * @param  callable(Model): bool  $mayAct
     */
    public function inspect(Builder $permittedScope, array $selectedKeys, callable $mayAct, int $limit = 500): SelectionInspection
    {
        ScopeShapeGuard::assertSupported($permittedScope);

        $model = $permittedScope->getModel();
        $requested = ExplicitKeys::forModel($model, $selectedKeys, $limit);

        // whereKey is applied to a clone: the caller's query remains reusable.
        $records = (clone $permittedScope)->whereKey($requested->keys)->get();

        if ($records->count() !== $requested->count()) {
            throw UnsafeSelection::scopeMismatch();
        }

        $resolved = ExplicitKeys::forModel($model, $records->map(fn (Model $record): mixed => $record->getKey())->all(), $limit);

        if ($resolved->keys !== $requested->keys) {
            throw UnsafeSelection::scopeMismatch();
        }

        foreach ($records as $record) {
            if (! $mayAct($record)) {
                throw UnsafeSelection::unauthorized();
            }
        }

        return new SelectionInspection($requested->count(), $requested->digest(), $requested->keys);
    }
}
