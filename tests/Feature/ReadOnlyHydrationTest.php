<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Mavvix\SafeActions\Execution\GuardedExecutor;
use Mavvix\SafeActions\Preflight\DeclaredChangePreview;
use Mavvix\SafeActions\Preflight\PreflightContext;
use Mavvix\SafeActions\Preflight\PreflightStore;
use Mavvix\SafeActions\Selection\SelectionInspector;
use Mavvix\SafeActions\Tests\Fixtures\ProbeOrder;
use Mavvix\SafeActions\Tests\TestCase;

final class ReadOnlyHydrationTest extends TestCase
{
    private function context(): PreflightContext
    {
        return new PreflightContext('user', '42', 'web', 'archive', 'tenant', '1', 'admin');
    }

    public function test_preflight_and_declared_preview_suppress_model_retrieved_observers(): void
    {
        $record = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $retrievals = 0;

        ProbeOrder::retrieved(static function () use (&$retrievals): void {
            $retrievals++;
        });

        $scope = ProbeOrder::query()->where('tenant_id', 1);
        $inspection = (new SelectionInspector)->inspect(
            $scope,
            [$record->getKey()],
            static fn (Model $model): bool => $model->status === 'pending',
        );

        (new PreflightStore)->issue($inspection, $this->context(), $scope);

        $summary = (new DeclaredChangePreview)->summarize(
            $scope,
            $inspection,
            ['status' => 'archived'],
        );

        self::assertSame(0, $retrievals);
        self::assertSame('Status: pending → archived', $summary);
        self::assertSame('pending', DB::table('probe_orders')->where('id', $record->getKey())->value('status'));
    }

    public function test_guarded_execution_preserves_update_events_but_suppresses_retrieved_events(): void
    {
        $record = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $retrievals = 0;
        $updates = 0;

        ProbeOrder::retrieved(static function () use (&$retrievals): void {
            $retrievals++;
        });

        ProbeOrder::updated(static function () use (&$updates): void {
            $updates++;
        });

        $scope = ProbeOrder::query()->where('tenant_id', 1);
        $inspection = (new SelectionInspector)->inspect(
            $scope,
            [$record->getKey()],
            static fn (Model $model): bool => $model->status === 'pending',
        );

        $ticket = (new PreflightStore)->issue($inspection, $this->context(), $scope);

        $result = app(GuardedExecutor::class)->execute(
            $ticket->id,
            $this->context(),
            ProbeOrder::query()->where('tenant_id', 1),
            static fn (Model $model): bool => $model->status === 'pending',
            static function (Model $model): void {
                $model->status = 'archived';
                $model->save();
            },
        );

        self::assertSame(1, $result->affectedRecords);
        self::assertSame(0, $retrievals);
        self::assertSame(1, $updates);
        self::assertSame('archived', DB::table('probe_orders')->where('id', $record->getKey())->value('status'));
    }
}
