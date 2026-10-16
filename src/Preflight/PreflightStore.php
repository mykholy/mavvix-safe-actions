<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Preflight;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;
use Mavvix\SafeActions\Selection\SelectionInspection;

/**
 * Persists bounded, read-only snapshots. A query-bound snapshot can later be
 * passed to GuardedExecutor; issuing a ticket never grants authorization.
 */
final class PreflightStore
{
    public function __construct(private readonly ScopeFingerprint $fingerprint) {}

    public function issue(
        SelectionInspection $inspection,
        PreflightContext $context,
        ?Builder $permittedScope = null,
    ): PreflightTicket {
        $this->validateInspection($inspection);

        $ttl = (int) config('safe-actions.preflight_ttl_seconds', 300);

        if ($ttl < 30 || $ttl > 900) {
            throw new InvalidArgumentException('Preflight TTL must be between 30 and 900 seconds.');
        }

        $expiresAt = now()->addSeconds($ttl)->toImmutable();
        $id = (string) Str::uuid();

        SafeActionPreflight::query()->create([
            'id' => $id,
            'actor_type' => $context->actorType,
            'actor_id' => $context->actorId,
            'guard' => $context->guard,
            'tenant_type' => $context->tenantType,
            'tenant_id' => $context->tenantId,
            'panel_id' => $context->panelId,
            'action_key' => $context->actionKey,
            'record_keys' => $inspection->recordKeys,
            'record_count' => $inspection->recordCount,
            'selection_digest' => $inspection->selectionDigest,
            'scope_digest' => $permittedScope === null ? null : $this->fingerprint->forQuery($permittedScope),
            'expires_at' => $expiresAt,
        ]);

        return new PreflightTicket($id, $inspection->recordCount, $expiresAt);
    }

    public function read(string $id, PreflightContext $context): ?SelectionInspection
    {
        $snapshot = SafeActionPreflight::query()
            ->whereKey($id)
            ->where('actor_type', $context->actorType)
            ->where('actor_id', $context->actorId)
            ->where('guard', $context->guard)
            ->where('tenant_type', $context->tenantType)
            ->where('tenant_id', $context->tenantId)
            ->where('panel_id', $context->panelId)
            ->where('action_key', $context->actionKey)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $snapshot) {
            return null;
        }

        $inspection = new SelectionInspection(
            $snapshot->record_count,
            $snapshot->selection_digest,
            $snapshot->record_keys,
        );

        $this->validateInspection($inspection);

        return $inspection;
    }

    private function validateInspection(SelectionInspection $inspection): void
    {
        $keys = $inspection->recordKeys;

        if ($inspection->recordCount > (int) config('safe-actions.max_explicit_selection', 500)) {
            throw UnsafeSelection::limitExceeded();
        }

        if ($inspection->recordCount < 1 || $inspection->recordCount !== count($keys)) {
            throw UnsafeSelection::invalid();
        }

        $previous = null;

        foreach ($keys as $key) {
            if (! is_string($key) || $key === '' || strlen($key) > 191) {
                throw UnsafeSelection::invalid();
            }

            if ($previous !== null && strcmp($previous, $key) >= 0) {
                throw UnsafeSelection::invalid();
            }

            $previous = $key;
        }

        if (! hash_equals($inspection->selectionDigest, hash('sha256', json_encode($keys, JSON_THROW_ON_ERROR)))) {
            throw UnsafeSelection::invalid();
        }
    }
}
