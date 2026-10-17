<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;
use Mavvix\SafeActions\Preflight\DeclaredChangePreview;
use Mavvix\SafeActions\Selection\SelectionInspector;
use Mavvix\SafeActions\Tests\Fixtures\ProbeOrder;
use Mavvix\SafeActions\Tests\TestCase;

final class DeclaredChangePreviewTest extends TestCase
{
    public function test_describes_a_column_change_without_writing_to_the_database(): void
    {
        $row = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $scope = ProbeOrder::query()->where('tenant_id', 1);
        $inspection = (new SelectionInspector)->inspect($scope, [$row->getKey()], fn (Model $record): bool => true);

        $description = (new DeclaredChangePreview)->summarize($scope, $inspection, ['status' => 'archived']);

        self::assertSame('Status: pending → archived', $description);
        self::assertSame('pending', $row->fresh()->status);
    }

    public function test_multiple_old_values_are_aggregated_without_exposing_each_record(): void
    {
        $one = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $two = ProbeOrder::create(['tenant_id' => 1, 'status' => 'locked']);
        $scope = ProbeOrder::query()->where('tenant_id', 1);
        $inspection = (new SelectionInspector)->inspect($scope, [$one->getKey(), $two->getKey()], fn (Model $record): bool => true);

        self::assertSame(
            'Status: (multiple values) → archived',
            (new DeclaredChangePreview)->summarize($scope, $inspection, ['status' => 'archived']),
        );
    }

    public function test_rejects_stale_membership_before_describing_changes(): void
    {
        $row = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $scope = ProbeOrder::query()->where('tenant_id', 1);
        $inspection = (new SelectionInspector)->inspect($scope, [$row->getKey()], fn (Model $record): bool => true);
        $row->update(['tenant_id' => 2]);

        $this->expectException(UnsafeSelection::class);

        (new DeclaredChangePreview)->summarize($scope, $inspection, ['status' => 'archived']);
    }

    public function test_declared_update_rejects_protected_columns(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DeclaredChangePreview::validate(['tenant_id' => 2]);
    }

    public function test_declared_update_rejects_non_scalar_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DeclaredChangePreview::validate(['status' => ['archived']]);
    }
}
