<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mavvix\SafeActions\Execution\GuardedExecutor;
use Mavvix\SafeActions\Exceptions\UnsafeExecution;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;
use Mavvix\SafeActions\Preflight\PreflightContext;
use Mavvix\SafeActions\Preflight\PreflightStore;
use Mavvix\SafeActions\Preflight\SafeActionPreflight;
use Mavvix\SafeActions\Selection\SelectionInspector;
use Mavvix\SafeActions\Tests\Fixtures\ProbeOrder;
use Mavvix\SafeActions\Tests\TestCase;
use RuntimeException;

final class GuardedExecutorTest extends TestCase
{
    private function context(string $actor = '42'): PreflightContext
    {
        return new PreflightContext('user', $actor, 'web', 'archive', 'tenant', '1', 'admin');
    }

    private function scope(bool $pendingOnly = true): Builder
    {
        $query = ProbeOrder::query()->where('tenant_id', 1);

        return $pendingOnly ? $query->where('status', 'pending') : $query;
    }

    /** @param array<int> $ids */
    private function issue(array $ids, ?Builder $scope = null, bool $bindScope = true): string
    {
        $scope ??= $this->scope();

        $inspection = (new SelectionInspector)->inspect(
            $scope,
            $ids,
            fn (Model $record): bool => $record->status === 'pending',
        );

        return (new PreflightStore)->issue(
            $inspection,
            $this->context(),
            $bindScope ? $scope : null,
        )->id;
    }

    private function executor(): GuardedExecutor
    {
        return app(GuardedExecutor::class);
    }

    private function mutate(Model $record): void
    {
        $record->status = 'archived';
        $record->save();
    }

    public function test_executes_exact_scoped_records_and_consumes_ticket_once(): void
    {
        $one = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $two = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $other = ProbeOrder::create(['tenant_id' => 2, 'status' => 'pending']);

        $id = $this->issue([$two->getKey(), $one->getKey()]);
        $mutations = 0;

        $result = $this->executor()->execute(
            $id,
            $this->context(),
            $this->scope(),
            fn (Model $record): bool => $record->status === 'pending',
            function (Model $record) use (&$mutations): void {
                $mutations++;
                $this->mutate($record);
            },
        );

        self::assertSame($id, $result->preflightId);
        self::assertSame(2, $result->affectedRecords);
        self::assertSame(2, $mutations);
        self::assertSame('archived', $one->fresh()->status);
        self::assertSame('archived', $two->fresh()->status);
        self::assertSame('pending', $other->fresh()->status);
        self::assertNotNull(SafeActionPreflight::findOrFail($id)->consumed_at);

        $this->expectException(UnsafeExecution::class);

        $this->executor()->execute($id, $this->context(), $this->scope(false), fn (): bool => true, fn (): null => null);
    }

    public function test_read_only_legacy_ticket_is_not_executable(): void
    {
        $order = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $id = $this->issue([$order->getKey()], bindScope: false);

        try {
            $this->executor()->execute($id, $this->context(), $this->scope(), fn (): bool => true, fn (Model $record) => $this->mutate($record));
            self::fail('Unbound ticket must not execute.');
        } catch (UnsafeExecution $exception) {
            self::assertSame('This preflight was not bound to a resource query and cannot be executed.', $exception->getMessage());
        }

        self::assertSame('pending', $order->fresh()->status);
        self::assertNull(SafeActionPreflight::findOrFail($id)->consumed_at);
    }

    public function test_rejects_an_actor_from_another_session_context(): void
    {
        $order = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $id = $this->issue([$order->getKey()]);

        $this->expectException(UnsafeExecution::class);

        $this->executor()->execute(
            $id,
            $this->context('99'),
            $this->scope(),
            fn (): bool => true,
            fn (Model $record) => $this->mutate($record),
        );
    }

    public function test_changed_query_filter_blocks_confirmation(): void
    {
        $order = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $id = $this->issue([$order->getKey()]);

        try {
            $this->executor()->execute($id, $this->context(), $this->scope(false), fn (): bool => true, fn (Model $record) => $this->mutate($record));
            self::fail('Changed query must not execute.');
        } catch (UnsafeExecution $exception) {
            self::assertSame('The permitted query changed after the preflight. Start a new preview.', $exception->getMessage());
        }

        self::assertSame('pending', $order->fresh()->status);
        self::assertNull(SafeActionPreflight::findOrFail($id)->consumed_at);
    }

    public function test_stale_record_outside_the_scope_fails_closed(): void
    {
        $order = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $id = $this->issue([$order->getKey()]);
        $order->update(['status' => 'locked']);

        $this->expectException(UnsafeSelection::class);

        $this->executor()->execute($id, $this->context(), $this->scope(), fn (): bool => true, fn (Model $record) => $this->mutate($record));
    }

    public function test_revoked_policy_fails_before_any_record_changes(): void
    {
        $one = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $two = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $scope = $this->scope(false);
        $id = $this->issue([$one->getKey(), $two->getKey()], $scope);
        $two->update(['status' => 'locked']);

        try {
            $this->executor()->execute(
                $id,
                $this->context(),
                $this->scope(false),
                fn (Model $record): bool => $record->status === 'pending',
                fn (Model $record) => $this->mutate($record),
            );
            self::fail('Revoked policy must reject the batch.');
        } catch (UnsafeSelection $exception) {
            self::assertSame('One or more selected records are not authorized.', $exception->getMessage());
        }

        self::assertSame('pending', $one->fresh()->status);
        self::assertSame('locked', $two->fresh()->status);
        self::assertNull(SafeActionPreflight::findOrFail($id)->consumed_at);
    }

    public function test_any_mutation_exception_rolls_back_every_record_and_the_ticket(): void
    {
        $one = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $two = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $id = $this->issue([$one->getKey(), $two->getKey()]);

        try {
            $invocations = 0;

            $this->executor()->execute(
                $id,
                $this->context(),
                $this->scope(),
                fn (): bool => true,
                function (Model $record) use (&$invocations): void {
                    $this->mutate($record);
                    $invocations++;

                    if ($invocations === 2) {
                        throw new RuntimeException('Simulated storage failure');
                    }
                },
            );
            self::fail('The transaction should fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated storage failure', $exception->getMessage());
        }

        self::assertSame('pending', $one->fresh()->status);
        self::assertSame('pending', $two->fresh()->status);
        self::assertNull(SafeActionPreflight::findOrFail($id)->consumed_at);
    }

    public function test_expired_ticket_cannot_execute(): void
    {
        $order = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $id = $this->issue([$order->getKey()]);

        Carbon::setTestNow(now()->addMinutes(6));

        try {
            $this->expectException(UnsafeExecution::class);

            $this->executor()->execute($id, $this->context(), $this->scope(), fn (): bool => true, fn (Model $record) => $this->mutate($record));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_ticket_with_modified_selection_is_rejected(): void
    {
        $order = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $id = $this->issue([$order->getKey()]);

        SafeActionPreflight::query()->whereKey($id)->update(['record_keys' => '["999"]']);

        $this->expectException(UnsafeSelection::class);

        $this->executor()->execute($id, $this->context(), $this->scope(), fn (): bool => true, fn (Model $record) => $this->mutate($record));
    }
}
