<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Selection;

use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;

/**
 * Resolve a bounded Filament selection against the live filtered table query.
 * This adapter only inspects data; it is not an authorization token.
 */
final class FilamentSelectionInspector
{
    public function __construct(private readonly SelectionInspector $inspector) {}

    /** @param callable(Model): bool $mayAct */
    public function inspect(HasTable $livewire, callable $mayAct, ?int $limit = null): SelectionInspection
    {
        $limit ??= (int) config('safe-actions.max_explicit_selection', 500);
        $table = $livewire->getTable();

        // Pivot-row identities and non-Eloquent data sources need a different resolver.
        if (! $table->hasQuery() || $table->hasPivotRecordKeys() || $table->allowsDuplicates()) {
            throw UnsafeSelection::unsupported();
        }

        $scope = $livewire->getFilteredTableQuery();

        if (! $scope instanceof Builder) {
            throw UnsafeSelection::unsupported();
        }

        if ($livewire->isTrackingDeselectedTableRecords) {
            return $this->inspectAllMatching($livewire, $scope, $mayAct, $limit);
        }

        $keys = $livewire->selectedTableRecords;

        if ($table->selectsCurrentPageOnly()) {
            $pageKeys = [];

            foreach ($livewire->getTableRecords() as $record) {
                if (! $record instanceof Model) {
                    throw UnsafeSelection::unsupported();
                }

                $pageKeys[(string) $record->getKey()] = true;
            }

            foreach ($keys as $key) {
                if (! (is_int($key) || is_string($key)) || ! isset($pageKeys[(string) $key])) {
                    throw UnsafeSelection::scopeMismatch();
                }
            }
        }

        if (($max = $table->getMaxSelectableRecords()) && count($keys) > $max) {
            throw UnsafeSelection::limitExceeded();
        }

        return $this->inspector->inspect(
            $scope,
            $keys,
            fn (Model $record): bool => $table->isRecordSelectable($record) && $mayAct($record),
            $limit,
        );
    }

    /** @param callable(Model): bool $mayAct */
    private function inspectAllMatching(HasTable $livewire, Builder $scope, callable $mayAct, int $limit): SelectionInspection
    {
        $table = $livewire->getTable();

        if ($table->selectsCurrentPageOnly() || $limit < 1) {
            throw UnsafeSelection::unsupported();
        }

        // A bounded scan prevents inverse selection from unexpectedly enumerating
        // the entire table. A future streaming resolver will handle larger sets.
        $excluded = [];

        foreach ($livewire->deselectedTableRecords as $key) {
            if (! is_string($key) && ! is_int($key)) {
                throw UnsafeSelection::invalid();
            }

            $excluded[(string) $key] = true;
        }

        if (count($excluded) !== count($livewire->deselectedTableRecords)) {
            throw UnsafeSelection::invalid();
        }

        $records = (clone $scope)->limit($limit + 1)->get();

        if ($records->count() > $limit) {
            throw UnsafeSelection::limitExceeded();
        }

        $keys = [];

        foreach ($records as $record) {
            if (isset($excluded[(string) $record->getKey()]) || ! $table->isRecordSelectable($record)) {
                continue;
            }

            $keys[] = $record->getKey();
        }

        if (($max = $table->getMaxSelectableRecords()) && count($keys) > $max) {
            throw UnsafeSelection::limitExceeded();
        }

        return $this->inspector->inspect($scope, $keys, $mayAct, $limit);
    }
}
