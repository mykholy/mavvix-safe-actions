<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Preflight;

use Illuminate\Database\Eloquent\Builder;
use JsonException;

/**
 * Binds a preview to the exact Eloquent query shape and bindings.
 *
 * This is a change detector, not a substitute for executing the query again.
 */
final class ScopeFingerprint
{
    /**
     * @throws JsonException
     */
    public function forQuery(Builder $scope): string
    {
        $query = clone $scope;
        $model = $query->getModel();

        return hash('sha256', json_encode([
            'model' => $model::class,
            'connection' => $model->getConnection()->getName(),
            'table' => $model->getTable(),
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ], JSON_THROW_ON_ERROR));
    }
}
