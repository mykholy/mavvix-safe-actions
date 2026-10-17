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
        $query = $scope->getQuery();

        if (
            $query->distinct !== false ||
            ! empty($query->groups) ||
            ! empty($query->havings) ||
            ! empty($query->unions) ||
            $query->aggregate !== null
        ) {
            throw UnsafeSelection::unsupported();
        }
    }
}
