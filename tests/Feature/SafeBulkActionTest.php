<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Feature;

use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Mavvix\SafeActions\Tests\Fixtures\GuardedOrdersTable;
use Mavvix\SafeActions\Tests\Fixtures\ProbeOrder;
use Mavvix\SafeActions\Tests\TestCase;

final class SafeBulkActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        View::addNamespace('safe-actions-test', __DIR__.'/../Fixtures/views');
    }

    public function test_confirmation_modal_previews_and_commits_selected_rows(): void
    {
        $selected = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $foreign = ProbeOrder::create(['tenant_id' => 2, 'status' => 'pending']);

        $component = Livewire::test(GuardedOrdersTable::class)
            ->set('selectedTableRecords', [$selected->getKey()])
            ->call('mountTableBulkAction', 'safe_archive');

        $this->assertDatabaseCount('safe_action_preflights', 1);

        $component->call('callMountedTableBulkAction')->assertHasNoErrors();

        self::assertSame('archived', $selected->fresh()->status);
        self::assertSame('pending', $foreign->fresh()->status);
    }

    public function test_modal_rejects_changed_selection_after_mount(): void
    {
        $original = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $replacement = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);

        $component = Livewire::test(GuardedOrdersTable::class)
            ->set('selectedTableRecords', [$original->getKey()])
            ->call('mountTableBulkAction', 'safe_archive');

        $component->set('selectedTableRecords', [$replacement->getKey()])
            ->call('callMountedTableBulkAction')
            ->assertHasNoErrors();

        self::assertSame('pending', $original->fresh()->status);
        self::assertSame('pending', $replacement->fresh()->status);
        $this->assertDatabaseCount('safe_action_preflights', 1);
    }

    public function test_action_modal_does_not_execute_unauthorized_selection(): void
    {
        $locked = ProbeOrder::create(['tenant_id' => 1, 'status' => 'locked']);

        Livewire::test(GuardedOrdersTable::class)
            ->set('selectedTableRecords', [$locked->getKey()])
            ->call('mountTableBulkAction', 'safe_archive')
            ->assertHasNoErrors();

        self::assertSame('locked', $locked->fresh()->status);
        $this->assertDatabaseCount('safe_action_preflights', 0);
    }
}
