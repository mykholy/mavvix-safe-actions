<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Feature;

use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Mavvix\SafeActions\Preflight\SafeActionPreflight;
use Mavvix\SafeActions\Tests\Fixtures\GuardedOrdersTable;
use Mavvix\SafeActions\Tests\Fixtures\ProbeOrder;
use Mavvix\SafeActions\Tests\TestCase;

final class FilamentSafeActionWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        View::addNamespace('safe-actions-test', __DIR__.'/../Fixtures/views');
    }

    public function test_preview_and_confirmation_only_archive_selected_tenant_records(): void
    {
        $own = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $other = ProbeOrder::create(['tenant_id' => 2, 'status' => 'pending']);

        $component = Livewire::test(GuardedOrdersTable::class)
            ->set('selectedTableRecords', [$own->getKey()])
            ->call('previewSafeArchive');

        $ticketId = $component->get('safeTicketId');

        self::assertNotEmpty($ticketId);
        self::assertSame(1, SafeActionPreflight::query()->count());

        $component->call('executeSafeArchive')
            ->assertSet('safeActionError', null)
            ->assertSet('safeExecutedCount', 1);

        self::assertSame('archived', $own->fresh()->status);
        self::assertSame('pending', $other->fresh()->status);

        $component->call('executeSafeArchive')
            ->assertSet('safeExecutedCount', null)
            ->assertSet('safeActionError', 'The preflight is missing, expired, already used or belongs to another context.');
    }

    public function test_confirmation_rechecks_filament_row_selectability(): void
    {
        $one = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $two = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);

        $component = Livewire::test(GuardedOrdersTable::class)
            ->set('selectedTableRecords', [$one->getKey(), $two->getKey()])
            ->call('previewSafeArchive');

        self::assertNotEmpty($component->get('safeTicketId'));

        $two->update(['status' => 'locked']);

        $component->call('executeSafeArchive')
            ->assertSet('safeExecutedCount', null)
            ->assertSet('safeActionError', 'One or more selected records are not authorized.');

        self::assertSame('pending', $one->fresh()->status);
        self::assertSame('locked', $two->fresh()->status);
    }

    public function test_changing_filament_filters_after_preview_blocks_confirmation(): void
    {
        $row = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);

        $component = Livewire::test(GuardedOrdersTable::class)
            ->set('selectedTableRecords', [$row->getKey()])
            ->call('previewSafeArchive');

        self::assertNotEmpty($component->get('safeTicketId'));

        $component->set('tableFilters.pending.isActive', true)
            ->call('executeSafeArchive')
            ->assertSet('safeExecutedCount', null)
            ->assertSet('safeActionError', 'The permitted query changed after the preflight. Start a new preview.');

        self::assertSame('pending', $row->fresh()->status);
    }
}
