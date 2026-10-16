<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;
use Mavvix\SafeActions\Selection\SelectionInspector;
use Mavvix\SafeActions\Tests\Fixtures\ProbeOrder;
use Mavvix\SafeActions\Tests\TestCase;

final class ReportedSelectionMismatchTest extends TestCase
{
    public function test_rejects_a_superset_of_the_828_selectable_records(): void
    {
        $rows = [];

        for ($index = 0; $index < 842; $index++) {
            $rows[] = [
                'tenant_id' => 1,
                'status' => $index < 828 ? 'pending' : 'archived',
            ];
        }

        foreach (array_chunk($rows, 100) as $batch) {
            ProbeOrder::query()->insert($batch);
        }

        $scope = ProbeOrder::query()->where('tenant_id', 1)->where('status', 'pending');
        $selected = $scope->pluck('id')->all();
        $inspector = new SelectionInspector;

        self::assertCount(828, $selected);
        self::assertSame(828, $inspector->inspect($scope, $selected, fn (Model $row): bool => true, limit: 1000)->recordCount);

        $this->expectException(UnsafeSelection::class);

        $inspector->inspect(
            $scope,
            ProbeOrder::query()->pluck('id')->all(),
            fn (Model $row): bool => true,
            limit: 1000,
        );
    }

    public function test_rejects_selection_with_18_ineligible_records_among_101(): void
    {
        $rows = [];

        for ($index = 0; $index < 101; $index++) {
            $rows[] = [
                'tenant_id' => 1,
                'status' => $index < 83 ? 'pending' : 'locked',
            ];
        }

        foreach (array_chunk($rows, 100) as $batch) {
            ProbeOrder::query()->insert($batch);
        }

        self::assertSame(101, ProbeOrder::query()->count());
        self::assertSame(18, ProbeOrder::query()->where('status', 'locked')->count());

        $this->expectException(UnsafeSelection::class);

        (new SelectionInspector)->inspect(
            ProbeOrder::query()->where('tenant_id', 1),
            ProbeOrder::query()->pluck('id')->all(),
            fn (Model $record): bool => $record->status !== 'locked',
        );
    }
}
