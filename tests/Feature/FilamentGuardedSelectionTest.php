<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Feature;

use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Mavvix\SafeActions\Tests\Fixtures\GuardedOrdersTable;
use Mavvix\SafeActions\Tests\Fixtures\ProbeOrder;
use Mavvix\SafeActions\Tests\TestCase;

final class FilamentGuardedSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        View::addNamespace('safe-actions-test', __DIR__.'/../Fixtures/views');
    }

    public function test_explicit_selection_is_bound_to_table_tenant_scope(): void
    {
        $own = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $other = ProbeOrder::create(['tenant_id' => 2, 'status' => 'pending']);

        Livewire::test(GuardedOrdersTable::class)
            ->set('selectedTableRecords', [$own->getKey(), $other->getKey()])
            ->call('inspectSelection')
            ->assertSet('inspectedCount', null)
            ->assertSet('inspectionError', 'Some selected records are not available within the permitted scope.');
    }

    public function test_filter_cannot_be_escaped_by_explicit_selection(): void
    {
        $pending = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $archived = ProbeOrder::create(['tenant_id' => 1, 'status' => 'archived']);

        Livewire::test(GuardedOrdersTable::class)
            ->set('tableFilters.pending.isActive', true)
            ->set('selectedTableRecords', [$pending->getKey(), $archived->getKey()])
            ->call('inspectSelection')
            ->assertSet('inspectedCount', null)
            ->assertSet('inspectionError', 'Some selected records are not available within the permitted scope.');
    }

    public function test_unselectable_record_fails_explicit_inspection(): void
    {
        $locked = ProbeOrder::create(['tenant_id' => 1, 'status' => 'locked']);

        Livewire::test(GuardedOrdersTable::class)
            ->set('selectedTableRecords', [$locked->getKey()])
            ->call('inspectSelection')
            ->assertSet('inspectedCount', null)
            ->assertSet('inspectionError', 'One or more selected records are not authorized.');
    }

    public function test_bounded_all_matching_selection_excludes_deselected_rows(): void
    {
        $first = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        ProbeOrder::create(['tenant_id' => 1, 'status' => 'locked']);
        ProbeOrder::create(['tenant_id' => 2, 'status' => 'pending']);

        Livewire::test(GuardedOrdersTable::class)
            ->set('isTrackingDeselectedTableRecords', true)
            ->set('deselectedTableRecords', [$first->getKey()])
            ->call('inspectSelection')
            ->assertSet('inspectedCount', 1)
            ->assertSet('inspectionError', null);
    }

    public function test_all_matching_is_restricted_by_live_table_filter(): void
    {
        ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        ProbeOrder::create(['tenant_id' => 1, 'status' => 'archived']);
        ProbeOrder::create(['tenant_id' => 2, 'status' => 'pending']);

        Livewire::test(GuardedOrdersTable::class)
            ->set('tableFilters.pending.isActive', true)
            ->set('isTrackingDeselectedTableRecords', true)
            ->call('inspectSelection')
            ->assertSet('inspectedCount', 1)
            ->assertSet('inspectionError', null);
    }

    public function test_all_matching_stops_at_configured_safe_limit(): void
    {
        foreach (range(1, 501) as $index) {
            ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        }

        Livewire::test(GuardedOrdersTable::class)
            ->set('isTrackingDeselectedTableRecords', true)
            ->call('inspectSelection')
            ->assertSet('inspectedCount', null)
            ->assertSet('inspectionError', 'The selection exceeds the configured safe limit.');
    }

    public function test_current_page_only_rejects_a_key_from_another_page(): void
    {
        $orders = collect(range(1, 20))->map(fn (): ProbeOrder => ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']));
        $last = $orders->last();

        Livewire::test(GuardedOrdersTable::class)
            ->set('currentPageOnly', true)
            ->set('selectedTableRecords', [$last->getKey()])
            ->call('inspectSelection')
            ->assertSet('inspectedCount', null)
            ->assertSet('inspectionError', 'Some selected records are not available within the permitted scope.');
    }

    public function test_current_page_only_rejects_inverse_selection(): void
    {
        ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);

        Livewire::test(GuardedOrdersTable::class)
            ->set('currentPageOnly', true)
            ->set('isTrackingDeselectedTableRecords', true)
            ->call('inspectSelection')
            ->assertSet('inspectedCount', null)
            ->assertSet('inspectionError', 'This Filament selection mode is not supported safely.');
    }

    public function test_inverse_selection_rejects_noncanonical_deselected_keys(): void
    {
        ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);

        Livewire::test(GuardedOrdersTable::class)
            ->set('isTrackingDeselectedTableRecords', true)
            ->set('deselectedTableRecords', ['0001'])
            ->call('inspectSelection')
            ->assertSet('inspectedCount', null)
            ->assertSet('inspectionError', 'The selection contains invalid or duplicate record keys.');
    }

    public function test_all_matching_revalidates_policy_for_each_record(): void
    {
        ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        ProbeOrder::create(['tenant_id' => 1, 'status' => 'forbidden']);

        Livewire::test(GuardedOrdersTable::class)
            ->set('isTrackingDeselectedTableRecords', true)
            ->call('inspectSelection')
            ->assertSet('inspectedCount', null)
            ->assertSet('inspectionError', 'One or more selected records are not authorized.');
    }
}
