<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;
use Mavvix\SafeActions\Selection\SelectionInspector;
use Mavvix\SafeActions\Tests\Fixtures\GloballyJoinedProbeOrder;
use Mavvix\SafeActions\Tests\Fixtures\ProbeOrder;
use Mavvix\SafeActions\Tests\TestCase;

final class SelectionInspectorTest extends TestCase
{
    public function test_scoped_explicit_selection_is_inspected_without_mutating_rows(): void
    {
        $first = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $second = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        ProbeOrder::create(['tenant_id' => 2, 'status' => 'pending']);

        $scope = ProbeOrder::query()->where('tenant_id', 1)->where('status', 'pending');
        $result = (new SelectionInspector)->inspect($scope, [$second->getKey(), $first->getKey()], fn (Model $row): bool => true);

        self::assertSame(2, $result->recordCount);
        self::assertSame(2, $scope->count(), 'The inspector must not modify the builder.');
        self::assertSame('pending', $first->fresh()->status);
        self::assertSame('pending', $second->fresh()->status);
    }

    public function test_selection_cannot_escape_tenant_scope(): void
    {
        ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $foreign = ProbeOrder::create(['tenant_id' => 2, 'status' => 'pending']);

        $this->expectException(UnsafeSelection::class);

        (new SelectionInspector)->inspect(
            ProbeOrder::query()->where('tenant_id', 1),
            [$foreign->getKey()],
            fn (Model $row): bool => true,
        );
    }

    public function test_stale_or_filtered_out_keys_fail_closed(): void
    {
        $archived = ProbeOrder::create(['tenant_id' => 1, 'status' => 'archived']);

        $this->expectException(UnsafeSelection::class);

        (new SelectionInspector)->inspect(
            ProbeOrder::query()->where('status', 'pending'),
            [$archived->getKey()],
            fn (Model $row): bool => true,
        );
    }

    public function test_one_forbidden_record_fails_entire_inspection(): void
    {
        $allowed = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $locked = ProbeOrder::create(['tenant_id' => 1, 'status' => 'locked']);

        $this->expectException(UnsafeSelection::class);

        (new SelectionInspector)->inspect(
            ProbeOrder::query()->where('tenant_id', 1),
            [$allowed->getKey(), $locked->getKey()],
            fn (Model $row): bool => $row->status !== 'locked',
        );
    }

    public function test_complex_query_shapes_fail_closed_before_selecting_records(): void
    {
        $row = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);

        $scopes = [
            ProbeOrder::query()->where('tenant_id', 1)->groupBy('status'),
            ProbeOrder::query()->where('tenant_id', 1)->having('status', 'pending'),
            ProbeOrder::query()->where('tenant_id', 1)->distinct(),
            ProbeOrder::query()->where('tenant_id', 1)->union(
                ProbeOrder::query()->where('tenant_id', 2),
            ),
        ];

        foreach ($scopes as $scope) {
            try {
                (new SelectionInspector)->inspect($scope, [$row->getKey()], fn (): bool => true);
                self::fail('Grouped, having, distinct and union scopes must not resolve mutable targets.');
            } catch (UnsafeSelection $exception) {
                self::assertSame('This Filament selection mode is not supported safely.', $exception->getMessage());
            }
        }

        self::assertSame('pending', $row->fresh()->status);
    }

    public function test_joins_and_aliased_from_queries_fail_before_record_hydration(): void
    {
        $record = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);

        $scopes = [
            ProbeOrder::query()->join('probe_orders as duplicate', 'duplicate.tenant_id', '=', 'probe_orders.tenant_id'),
            ProbeOrder::query()->from('probe_orders as alias'),
            GloballyJoinedProbeOrder::query(),
        ];

        foreach ($scopes as $scope) {
            try {
                (new SelectionInspector)->inspect($scope, [$record->getKey()], fn (): bool => true);
                self::fail('An ambiguous row-identity query was accepted.');
            } catch (UnsafeSelection $exception) {
                self::assertSame('This Filament selection mode is not supported safely.', $exception->getMessage());
            }
        }

        self::assertSame('pending', $record->fresh()->status);
    }

    public function test_missing_key_fails_entire_inspection(): void
    {
        $existing = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);

        $this->expectException(UnsafeSelection::class);

        (new SelectionInspector)->inspect(
            ProbeOrder::query()->where('tenant_id', 1),
            [$existing->getKey(), 99999],
            fn (Model $row): bool => true,
        );
    }
}
