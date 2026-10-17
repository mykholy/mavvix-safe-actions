<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Selection;

use Illuminate\Database\Eloquent\Builder;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;

/**
 * Bulk identity resolution requires one unaggregated model row per primary key.
 * Complex SQL shapes are deliberately unsupported until proven in host tests.
 */
final class ScopeShapeGuard
{
    public static function assertSupported(Builder $scope): void
    {
        // Eloquent global scopes can add joins or GROUP BY clauses after the
        // application creates its builder. Validate the actual SQL shape.
        $query = (clone $scope)->applyScopes()->getQuery();
        $expectedFrom = $scope->getModel()->getTable();

        if (
            $query->from !== $expectedFrom ||
            $query->distinct !== false ||
            ! empty($query->joins) ||
            ! empty($query->groups) ||
            ! empty($query->havings) ||
            ! empty($query->unions) ||
            $query->aggregate !== null
        ) {
            throw UnsafeSelection::unsupported();
        }
    }
}
