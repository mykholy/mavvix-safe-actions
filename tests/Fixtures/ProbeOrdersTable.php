<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Fixtures;

use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

final class ProbeOrdersTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** @var list<int|string> */
    public array $seenIds = [];

    public function table(Table $table): Table
    {
        return $table
            ->query(ProbeOrder::query()->where('tenant_id', 1))
            ->columns([TextColumn::make('status')])
            ->selectCurrentPageOnly()
            ->checkIfRecordIsSelectableUsing(fn (ProbeOrder $record): bool => $record->status !== 'locked')
            ->toolbarActions([
                BulkAction::make('inspect')
                    ->action(function (Collection $records): void {
                        $this->seenIds = $records->modelKeys();
                    }),
            ]);
    }

    public function render(): View
    {
        return view('safe-actions-test::table');
    }
}
