<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Filament;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mavvix\SafeActions\Exceptions\UnsafeExecution;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;
use Mavvix\SafeActions\Preflight\DeclaredChangePreview;
use Mavvix\SafeActions\Preflight\PreflightContext;
use Mavvix\SafeActions\Preflight\PreflightStore;

/**
 * A guarded, bounded, synchronous, single-connection Filament bulk action.
 */
final class SafeBulkAction
{
    /**
     * Custom database-only mutation. This API shows a record count rather than
     * pretending it can infer an arbitrary callback's future effects.
     *
     * @param  callable(HasTable): PreflightContext  $context
     * @param  callable(Model): bool  $authorize
     * @param  callable(Model): void  $mutate
     */
    public static function make(
        string $name,
        callable $context,
        callable $authorize,
        callable $mutate,
    ): BulkAction {
        return self::build($name, $context, $authorize, $mutate, null);
    }

    /**
     * Deterministic scalar update: the same declared fields drive the read-only
     * preview and the database mutation. Model observers may have other effects.
     *
     * @param  callable(HasTable): PreflightContext  $context
     * @param  callable(Model): bool  $authorize
     * @param  array<string, scalar|null>  $changes
     */
    public static function update(
        string $name,
        callable $context,
        callable $authorize,
        array $changes,
    ): BulkAction {
        DeclaredChangePreview::validate($changes);

        return self::build(
            $name,
            $context,
            $authorize,
            static function (Model $record) use ($changes): void {
                $record->forceFill($changes);
                $record->save();
            },
            $changes,
        );
    }

    /**
     * @param  callable(HasTable): PreflightContext  $context
     * @param  callable(Model): bool  $authorize
     * @param  callable(Model): void  $mutate
     * @param  array<string, scalar|null>|null  $declaredChanges
     */
    private static function build(
        string $name,
        callable $context,
        callable $authorize,
        callable $mutate,
        ?array $declaredChanges,
    ): BulkAction {
        $fields = [
            Hidden::make('safe_action_ticket')->required(),
            TextInput::make('safe_action_count')
                ->label(fn (): string => (string) __('safe-actions::messages.modal.record_count'))
                ->readOnly()
                ->dehydrated(false),
        ];

        if ($declaredChanges !== null) {
            $fields[] = Textarea::make('safe_action_impact')
                ->label(fn (): string => (string) __('safe-actions::messages.modal.impact'))
                ->rows(4)
                ->readOnly()
                ->dehydrated(false);
        }

        return BulkAction::make($name)
            ->requiresConfirmation()
            ->modalDescription(fn (): string => (string) __('safe-actions::messages.modal.description'))
            ->modalSubmitActionLabel(fn (): string => (string) __('safe-actions::messages.modal.confirm'))
            ->modalSubmitAction(fn (Action $action): Action => $action->color('gray'))
            ->schema($fields)
            ->mountUsing(function (Schema $schema, HasTable $livewire) use ($context, $authorize, $declaredChanges): void {
                try {
                    $trustedContext = $context($livewire);
                    $ticket = app(FilamentSafeActionWorkflow::class)->preview(
                        $livewire,
                        $trustedContext,
                        $authorize,
                    );

                    $formData = [
                        'safe_action_ticket' => $ticket->id,
                        'safe_action_count' => $ticket->recordCount,
                    ];

                    if ($declaredChanges !== null) {
                        $scope = $livewire->getFilteredTableQuery();

                        if (! $scope instanceof Builder) {
                            throw UnsafeSelection::unsupported();
                        }

                        $inspection = app(PreflightStore::class)->read($ticket->id, $trustedContext, $scope);

                        if ($inspection === null) {
                            throw UnsafeExecution::unavailable();
                        }

                        $formData['safe_action_impact'] = app(DeclaredChangePreview::class)
                            ->summarize($scope, $inspection, $declaredChanges);
                    }
                } catch (UnsafeSelection|UnsafeExecution $exception) {
                    Notification::make()
                        ->title((string) __('safe-actions::messages.notifications.preview_failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    throw new Halt;
                }

                $schema->fill($formData);
            })
            ->action(function (array $data, HasTable $livewire) use ($context, $authorize, $mutate): void {
                try {
                    $ticketId = $data['safe_action_ticket'] ?? null;

                    if (! is_string($ticketId) || $ticketId === '') {
                        throw UnsafeExecution::unavailable();
                    }

                    app(FilamentSafeActionWorkflow::class)->execute(
                        $livewire,
                        $ticketId,
                        $context($livewire),
                        $authorize,
                        $mutate,
                    );
                } catch (UnsafeSelection|UnsafeExecution $exception) {
                    Notification::make()
                        ->title((string) __('safe-actions::messages.notifications.execute_failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    throw new Halt;
                }
            });
    }
}
