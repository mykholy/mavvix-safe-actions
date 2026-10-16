<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Filament;

use Filament\Actions\BulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Mavvix\SafeActions\Exceptions\UnsafeExecution;
use Mavvix\SafeActions\Preflight\PreflightContext;

/**
 * An opt-in, synchronously executed bulk action with server-issued preflight.
 *
 * Preview occurs when the confirmation modal mounts. The hidden ticket is only
 * an identifier: the executor always checks its server-side ownership and scope.
 */
final class SafeBulkAction
{
    /**
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
        return BulkAction::make($name)
            ->requiresConfirmation()
            ->modalDescription('Review the verified record count before confirming. Permissions are checked again during execution.')
            ->modalSubmitActionLabel('Confirm changes')
            ->schema([
                Hidden::make('safe_action_ticket')->required(),
                TextInput::make('safe_action_count')
                    ->label('Records to change')
                    ->readOnly()
                    ->dehydrated(false),
            ])
            ->mountUsing(function (Schema $schema, HasTable $livewire) use ($context, $authorize): void {
                $ticket = app(FilamentSafeActionWorkflow::class)->preview(
                    $livewire,
                    $context($livewire),
                    $authorize,
                );

                $schema->fill([
                    'safe_action_ticket' => $ticket->id,
                    'safe_action_count' => $ticket->recordCount,
                ]);
            })
            ->action(function (array $data, HasTable $livewire) use ($context, $authorize, $mutate): void {
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
            });
    }
}
