<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Feature;

use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Mavvix\SafeActions\Tests\Fixtures\ProbeOrder;
use Mavvix\SafeActions\Tests\Fixtures\ProbeOrdersTable;
use Mavvix\SafeActions\Tests\TestCase;

final class FilamentSelectionIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        View::addNamespace('safe-actions-test', __DIR__.'/../Fixtures/views');
    }

    public function test_explicit_selection_reaches_the_native_bulk_action(): void
    {
        $first = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $second = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);

        Livewire::test(ProbeOrdersTable::class)
            ->selectTableRecords([$first->getKey(), $second->getKey()])
            ->callAction(TestAction::make('inspect')->table()->bulk())
            ->assertSet('seenIds', [$first->getKey(), $second->getKey()]);
    }

    public function test_inspection_action_never_modifies_record_values(): void
    {
        $order = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);

        Livewire::test(ProbeOrdersTable::class)
            ->selectTableRecords([$order->getKey()])
            ->callAction(TestAction::make('inspect')->table()->bulk());

        self::assertSame('pending', $order->fresh()->status);
    }
}
