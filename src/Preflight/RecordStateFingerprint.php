<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Preflight;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use JsonException;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;
use Mavvix\SafeActions\Selection\ExplicitKeys;
use Mavvix\SafeActions\Selection\ScopeShapeGuard;
use Mavvix\SafeActions\Selection\SelectionInspection;

/**
 * Detects persisted model state changes between preview and confirmation.
 * This is an optimistic concurrency check, not an immutable audit snapshot.
 */
final class RecordStateFingerprint
{
    public function forScope(Builder $scope, SelectionInspection $inspection): string
    {
        ScopeShapeGuard::assertSupported($scope);

        $query = $scope->getQuery();

        if ($query->limit !== null || $query->offset !== null) {
            throw UnsafeSelection::unsupported();
        }

        $model = $scope->getModel();

        // Include the model's columns even when the host selects a subset.
        $records = Model::withoutEvents(
            fn () => (clone $scope)
                ->whereKey($inspection->recordKeys)
                ->addSelect($model->qualifyColumn('*'))
                ->get(),
        );

        if ($records->count() !== $inspection->recordCount) {
            throw UnsafeSelection::scopeMismatch();
        }

        $resolved = ExplicitKeys::forModel(
            $model,
            $records->map(fn (Model $record): mixed => $record->getKey())->all(),
            $inspection->recordCount,
        );

        if (! hash_equals($inspection->selectionDigest, $resolved->digest())) {
            throw UnsafeSelection::scopeMismatch();
        }

        return $this->forRecords($records);
    }

    /** @param iterable<Model> $records */
    public function forRecords(iterable $records): string
    {
        $states = [];

        foreach ($records as $record) {
            // getAttributes() exposes persisted values without calling accessors.
            $attributes = $record->getAttributes();
            ksort($attributes, SORT_STRING);

            $states[] = [
                'key' => (string) $record->getKey(),
                'attributes' => $attributes,
            ];
        }

        usort($states, static fn (array $a, array $b): int => strcmp($a['key'], $b['key']));

        try {
            return hash('sha256', json_encode($states, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            // Non-JSON-encodable model attributes cannot be compared safely.
            throw UnsafeSelection::unsupported();
        }
    }
}
