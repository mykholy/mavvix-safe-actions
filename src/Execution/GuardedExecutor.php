<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Execution;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mavvix\SafeActions\Exceptions\UnsafeExecution;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;
use Mavvix\SafeActions\Preflight\PreflightContext;
use Mavvix\SafeActions\Preflight\PreflightStore;
use Mavvix\SafeActions\Preflight\RecordStateFingerprint;
use Mavvix\SafeActions\Preflight\SafeActionPreflight;
use Mavvix\SafeActions\Preflight\ScopeFingerprint;
use Mavvix\SafeActions\Selection\ExplicitKeys;
use Mavvix\SafeActions\Selection\ScopeShapeGuard;
use Throwable;

/**
 * A deliberately narrow, synchronous, single-database transaction boundary.
 *
 * The caller must supply trusted, freshly constructed tenant/actor-scoped queries
 * and authorization logic. Mutations must not perform external side effects.
 */
final class GuardedExecutor
{
    public function __construct(
        private readonly PreflightStore $store,
        private readonly ScopeFingerprint $fingerprint,
    ) {}

    /**
     * @param  callable(Model): bool  $authorize
     * @param  callable(Model): void  $mutate
     *
     * @throws Throwable
     */
    public function execute(
        string $preflightId,
        PreflightContext $context,
        Builder $permittedScope,
        callable $authorize,
        callable $mutate,
    ): ExecutionResult {
        ScopeShapeGuard::assertSupported($permittedScope);

        $model = $permittedScope->getModel();
        $connection = $model->getConnection();

        if ($connection->getName() !== (new SafeActionPreflight)->getConnection()->getName()) {
            throw UnsafeExecution::connectionMismatch();
        }

        if ($permittedScope->getQuery()->limit !== null || $permittedScope->getQuery()->offset !== null) {
            throw UnsafeExecution::unsupportedScope();
        }

        // Do not retry this callback automatically: host-provided mutation code
        // may not be safe to invoke more than once.
        return $connection->transaction(function () use ($preflightId, $context, $permittedScope, $authorize, $mutate, $model): ExecutionResult {
            // Serialize confirmation attempts on the same ticket.
            $ticket = SafeActionPreflight::query()
                ->whereKey($preflightId)
                ->lockForUpdate()
                ->first();

            if (! $ticket || $ticket->consumed_at !== null || $ticket->expires_at->isPast()) {
                throw UnsafeExecution::unavailable();
            }

            $inspection = $this->store->read($preflightId, $context);

            if ($inspection === null) {
                throw UnsafeExecution::unavailable();
            }

            if ($ticket->scope_digest === null) {
                throw UnsafeExecution::unbound();
            }

            if (! hash_equals($ticket->scope_digest, $this->fingerprint->forQuery($permittedScope))) {
                throw UnsafeExecution::scopeChanged();
            }

            $requested = ExplicitKeys::forModel(
                $model,
                $inspection->recordKeys,
                (int) config('safe-actions.max_explicit_selection', 500),
            );

            // Re-read and lock the actual rows, not objects cached during preview.
            $records = (clone $permittedScope)
                ->whereKey($requested->keys)
                ->addSelect($model->qualifyColumn('*'))
                ->orderBy($model->getQualifiedKeyName())
                ->lockForUpdate()
                ->get();

            if ($records->count() !== $requested->count()) {
                throw UnsafeSelection::scopeMismatch();
            }

            $resolved = ExplicitKeys::forModel(
                $model,
                $records->map(fn (Model $record): mixed => $record->getKey())->all(),
                (int) config('safe-actions.max_explicit_selection', 500),
            );

            if (! hash_equals($requested->digest(), $resolved->digest())) {
                throw UnsafeSelection::scopeMismatch();
            }

            // Check all records before invoking the first mutation.
            foreach ($records as $record) {
                if (! $authorize($record)) {
                    throw UnsafeSelection::unauthorized();
                }
            }

            if ($ticket->record_state_digest === null) {
                throw UnsafeExecution::stateUnbound();
            }

            if (! hash_equals($ticket->record_state_digest, (new RecordStateFingerprint)->forRecords($records))) {
                throw UnsafeExecution::recordChanged();
            }

            foreach ($records as $record) {
                // Recheck before every modification in case authorization depends
                // on state updated by a preceding model operation.
                if (! $authorize($record)) {
                    throw UnsafeSelection::unauthorized();
                }

                $mutate($record);
            }

            $ticket->forceFill(['consumed_at' => now()])->save();

            return new ExecutionResult($preflightId, $records->count());
        });
    }
}
