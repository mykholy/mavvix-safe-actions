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
        $state = $this->readSelectionState($livewire);

        // Pivot-row identities and non-Eloquent data sources need a different resolver.
        if (! $table->hasQuery() || $table->hasPivotRecordKeys() || $table->allowsDuplicates()) {
            throw UnsafeSelection::unsupported();
        }

        $scope = $livewire->getFilteredTableQuery();

        if (! $scope instanceof Builder) {
            throw UnsafeSelection::unsupported();
        }

        if ($state['isTrackingDeselectedTableRecords']) {
            return $this->inspectAllMatching($livewire, $scope, $mayAct, $limit, $state['deselectedTableRecords']);
        }

        $keys = $state['selectedTableRecords'];

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
    private function inspectAllMatching(HasTable $livewire, Builder $scope, callable $mayAct, int $limit, array $deselectedKeys): SelectionInspection
    {
        $table = $livewire->getTable();

        if ($table->selectsCurrentPageOnly() || $limit < 1) {
            throw UnsafeSelection::unsupported();
        }

        // A bounded scan prevents inverse selection from unexpectedly enumerating
        // the entire table. A future streaming resolver will handle larger sets.
        $excludedKeys = $deselectedKeys === []
            ? []
            : ExplicitKeys::forModel($scope->getModel(), $deselectedKeys, $limit)->keys;

        $excluded = array_fill_keys($excludedKeys, true);

        // A caller-defined offset or limit would silently change what "all" means.
        if ($scope->getQuery()->offset !== null || $scope->getQuery()->limit !== null) {
            throw UnsafeSelection::unsupported();
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

    /**
     * Filament's HasTable interface doesn't declare Livewire selection state.
     * Inspect public component state explicitly rather than assuming the trait
     * and its public properties are present on every HasTable implementation.
     *
     * @return array{
     *     selectedTableRecords: array<array-key, mixed>,
     *     deselectedTableRecords: array<array-key, mixed>,
     *     isTrackingDeselectedTableRecords: bool
     * }
     */
    private function readSelectionState(HasTable $livewire): array
    {
        $state = get_object_vars($livewire);

        if (
            ! isset($state['selectedTableRecords']) ||
            ! is_array($state['selectedTableRecords']) ||
            ! isset($state['deselectedTableRecords']) ||
            ! is_array($state['deselectedTableRecords']) ||
            ! isset($state['isTrackingDeselectedTableRecords']) ||
            ! is_bool($state['isTrackingDeselectedTableRecords'])
        ) {
            throw UnsafeSelection::unsupported();
        }

        return [
            'selectedTableRecords' => $state['selectedTableRecords'],
            'deselectedTableRecords' => $state['deselectedTableRecords'],
            'isTrackingDeselectedTableRecords' => $state['isTrackingDeselectedTableRecords'],
        ];
    }
}
