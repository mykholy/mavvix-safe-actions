<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Filament;

use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;
use Mavvix\SafeActions\Execution\ExecutionResult;
use Mavvix\SafeActions\Execution\GuardedExecutor;
use Mavvix\SafeActions\Preflight\PreflightContext;
use Mavvix\SafeActions\Preflight\PreflightStore;
use Mavvix\SafeActions\Preflight\PreflightTicket;
use Mavvix\SafeActions\Selection\FilamentSelectionInspector;

final class FilamentSafeActionWorkflow
{
    public function __construct(
        private readonly FilamentSelectionInspector $inspector,
        private readonly PreflightStore $preflights,
        private readonly GuardedExecutor $executor,
    ) {}

    /** @param  callable(Model): bool  $authorize */
    public function preview(HasTable $component, PreflightContext $context, callable $authorize): PreflightTicket
    {
        $inspection = $this->inspector->inspect($component, $authorize);

        return $this->preflights->issue($inspection, $context, $this->getScope($component));
    }

    /**
     * @param  callable(Model): bool  $authorize
     * @param  callable(Model): void  $mutate
     */
    public function execute(
        HasTable $component,
        string $ticketId,
        PreflightContext $context,
        callable $authorize,
        callable $mutate,
    ): ExecutionResult {
        $table = $component->getTable();

        if (! $table->hasQuery() || $table->hasPivotRecordKeys() || $table->allowsDuplicates()) {
            throw UnsafeSelection::unsupported();
        }

        return $this->executor->execute(
            $ticketId,
            $context,
            $this->getScope($component),
            fn (Model $record): bool => $table->isRecordSelectable($record) && $authorize($record),
            $mutate,
        );
    }

    private function getScope(HasTable $component): Builder
    {
        $query = $component->getFilteredTableQuery();

        if (! $query instanceof Builder) {
            throw UnsafeSelection::unsupported();
        }

        return $query;
    }
}
