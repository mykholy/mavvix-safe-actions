<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;
use Mavvix\SafeActions\Preflight\PreflightContext;
use Mavvix\SafeActions\Preflight\PreflightStore;
use Mavvix\SafeActions\Preflight\SafeActionPreflight;
use Mavvix\SafeActions\Selection\SelectionInspection;
use Mavvix\SafeActions\Selection\SelectionInspector;
use Mavvix\SafeActions\Tests\Fixtures\ProbeOrder;
use Mavvix\SafeActions\Tests\TestCase;

final class PreflightStoreTest extends TestCase
{
    private function context(string $actorId = '42', string $tenantId = '1', string $actionKey = 'archive'): PreflightContext
    {
        return new PreflightContext('user', $actorId, 'web', $actionKey, 'tenant', $tenantId, 'admin');
    }

    private function inspection(): SelectionInspection
    {
        $record = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);

        return (new SelectionInspector)->inspect(
            ProbeOrder::query()->where('tenant_id', 1),
            [$record->getKey()],
            fn (Model $model): bool => $model->status === 'pending',
        );
    }

    public function test_preflight_is_readable_only_in_its_actor_tenant_and_action_context(): void
    {
        $inspection = $this->inspection();
        $store = new PreflightStore;
        $ticket = $store->issue($inspection, $this->context());

        self::assertSame($inspection->recordKeys, $store->read($ticket->id, $this->context())->recordKeys);
        self::assertNull($store->read($ticket->id, $this->context(actorId: '43')));
        self::assertNull($store->read($ticket->id, $this->context(tenantId: '2')));
        self::assertNull($store->read($ticket->id, $this->context(actionKey: 'delete')));
        self::assertSame(1, SafeActionPreflight::query()->count());
    }

    public function test_expired_ticket_is_unavailable(): void
    {
        $store = new PreflightStore;
        $ticket = $store->issue($this->inspection(), $this->context());

        Carbon::setTestNow(now()->addMinutes(6));

        try {
            self::assertNull($store->read($ticket->id, $this->context()));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_snapshot_does_not_mutate_source_records(): void
    {
        $inspection = $this->inspection();
        (new PreflightStore)->issue($inspection, $this->context());

        self::assertSame('pending', ProbeOrder::query()->firstOrFail()->status);
    }

    public function test_storage_rejects_forged_or_inconsistent_selection(): void
    {
        $this->expectException(UnsafeSelection::class);

        (new PreflightStore)->issue(new SelectionInspection(2, 'invalid', ['1']), $this->context());
    }

    public function test_storage_detects_modified_record_keys(): void
    {
        $store = new PreflightStore;
        $ticket = $store->issue($this->inspection(), $this->context());
        SafeActionPreflight::query()->whereKey($ticket->id)->update(['record_keys' => json_encode(['999'])]);

        $this->expectException(UnsafeSelection::class);

        $store->read($ticket->id, $this->context());
    }
}
