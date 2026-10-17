<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Fixtures;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Mavvix\SafeActions\Exceptions\UnsafeExecution;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;
use Mavvix\SafeActions\Filament\FilamentSafeActionWorkflow;
use Mavvix\SafeActions\Filament\SafeBulkAction;
use Mavvix\SafeActions\Preflight\PreflightContext;
use Mavvix\SafeActions\Selection\FilamentSelectionInspector;

final class GuardedOrdersTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public bool $currentPageOnly = false;

    public ?int $inspectedCount = null;

    public ?string $inspectionError = null;

    public ?string $safeTicketId = null;

    public ?string $safeActionError = null;

    public ?int $safeExecutedCount = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(ProbeOrder::query()->where('tenant_id', 1))
            ->columns([TextColumn::make('status')->searchable()])
            ->toolbarActions([
                SafeBulkAction::make(
                    'safe_archive',
                    fn (HasTable $livewire): PreflightContext => $this->actionContext(),
                    fn (Model $record): bool => $record->status === 'pending',
                    function (Model $record): void {
                        $record->status = 'archived';
                        $record->save();
                    },
                ),
                SafeBulkAction::update(
                    'safe_declared_archive',
                    fn (HasTable $livewire): PreflightContext => $this->actionContext(),
                    fn (Model $record): bool => $record->status === 'pending',
                    ['status' => 'archived'],
                )->label('Archive with preview'),
            ])
            ->selectCurrentPageOnly($this->currentPageOnly)
            ->filters([
                Filter::make('pending')->query(fn (Builder $query): Builder => $query->where('status', 'pending')),
            ])
            ->checkIfRecordIsSelectableUsing(fn (ProbeOrder $record): bool => $record->status !== 'locked');
    }

    public function inspectSelection(): void
    {
        $this->inspectedCount = null;
        $this->inspectionError = null;

        try {
            $this->inspectedCount = app(FilamentSelectionInspector::class)
                ->inspect($this, fn (Model $record): bool => $record->status !== 'forbidden')
                ->recordCount;
        } catch (UnsafeSelection $exception) {
            $this->inspectionError = $exception->getMessage();
        }
    }

    private function actionContext(): PreflightContext
    {
        return new PreflightContext('user', '42', 'web', 'archive', 'tenant', '1', 'admin');
    }

    public function previewSafeArchive(): void
    {
        $this->safeActionError = null;
        $this->safeTicketId = null;

        try {
            $ticket = app(FilamentSafeActionWorkflow::class)->preview(
                $this,
                $this->actionContext(),
                fn (Model $record): bool => $record->status === 'pending',
            );

            $this->safeTicketId = $ticket->id;
        } catch (UnsafeSelection $exception) {
            $this->safeActionError = $exception->getMessage();
        }
    }

    public function executeSafeArchive(): void
    {
        $this->safeActionError = null;
        $this->safeExecutedCount = null;

        try {
            $result = app(FilamentSafeActionWorkflow::class)->execute(
                $this,
                $this->safeTicketId ?? '',
                $this->actionContext(),
                fn (Model $record): bool => $record->status === 'pending',
                function (Model $record): void {
                    $record->status = 'archived';
                    $record->save();
                },
            );

            $this->safeExecutedCount = $result->affectedRecords;
        } catch (UnsafeSelection|UnsafeExecution $exception) {
            $this->safeActionError = $exception->getMessage();
        }
    }

    public function render(): View
    {
        return view('safe-actions-test::table');
    }
}
