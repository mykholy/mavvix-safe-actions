<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\BrowserOrder;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Mavvix\SafeActions\Filament\SafeBulkAction;
use Mavvix\SafeActions\Preflight\PreflightContext;

final class SafeActionsDemo extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function boot(): void
    {
        app()->setLocale(session('safe_actions_demo_locale', 'en'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(BrowserOrder::query()->where('tenant_id', 1))
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('status'),
            ])
            ->toolbarActions([
                SafeBulkAction::make(
                    'archive',
                    static function (HasTable $component): PreflightContext {
                        return new PreflightContext(
                            actorType: 'user',
                            actorId: (string) Auth::id(),
                            guard: 'web',
                            actionKey: 'archive',
                            tenantType: 'tenant',
                            tenantId: '1',
                        );
                    },
                    static fn (Model $record): bool => $record->status === 'pending',
                    static function (Model $record): void {
                        $record->status = 'archived';
                        $record->save();
                    },
                )->label('Archive selected'),
                SafeBulkAction::update(
                    'archive_with_preview',
                    static function (HasTable $component): PreflightContext {
                        return new PreflightContext(
                            actorType: 'user',
                            actorId: (string) Auth::id(),
                            guard: 'web',
                            actionKey: 'archive',
                            tenantType: 'tenant',
                            tenantId: '1',
                        );
                    },
                    static fn (Model $record): bool => $record->status === 'pending',
                    ['status' => 'archived'],
                )->label('Archive with preview'),
            ])
            ->checkIfRecordIsSelectableUsing(static fn (BrowserOrder $record): bool => $record->status !== 'locked');
    }

    public function render(): View
    {
        return view('safe-actions-demo-table');
    }
}
