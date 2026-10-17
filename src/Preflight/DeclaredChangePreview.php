<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Preflight;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;
use Mavvix\SafeActions\Selection\ScopeShapeGuard;
use Mavvix\SafeActions\Selection\SelectionInspection;

/**
 * Describes only developer-declared scalar column updates.
 *
 * No mutation callbacks are evaluated while preparing this preview.
 */
final class DeclaredChangePreview
{
    /**
     * @param  array<array-key, mixed>  $changes
     */
    public static function validate(array $changes): void
    {
        if ($changes === [] || count($changes) > 8) {
            throw new InvalidArgumentException('Declare between one and eight scalar fields to preview.');
        }

        foreach ($changes as $field => $value) {
            if (
                ! is_string($field) ||
                ! preg_match('/^[a-z][a-z0-9_]*$/D', $field) ||
                in_array($field, ['id', 'tenant_id', 'created_at', 'updated_at', 'deleted_at'], true) ||
                (! is_null($value) && ! is_scalar($value)) ||
                (is_string($value) && strlen($value) > 2000)
            ) {
                throw new InvalidArgumentException('Declared update contains an unsupported field or value.');
            }
        }
    }

    /**
     * @param  array<string, scalar|null>  $changes
     */
    public function summarize(Builder $scope, SelectionInspection $inspection, array $changes): string
    {
        self::validate($changes);
        ScopeShapeGuard::assertSupported($scope);

        $model = $scope->getModel();

        if (array_key_exists($model->getKeyName(), $changes)) {
            throw new InvalidArgumentException('Primary keys cannot be changed by a declared bulk update.');
        }

        if ($scope->getQuery()->limit !== null || $scope->getQuery()->offset !== null) {
            throw UnsafeSelection::unsupported();
        }

        $records = Model::withoutEvents(
            fn () => (clone $scope)->whereKey($inspection->recordKeys)->get(),
        );

        if ($records->count() !== $inspection->recordCount) {
            throw UnsafeSelection::scopeMismatch();
        }

        $lines = [];

        foreach ($changes as $field => $newValue) {
            $before = [];

            foreach ($records as $record) {
                if (! array_key_exists($field, $record->getAttributes()) ||
                    in_array($field, $record->getHidden(), true)) {
                    throw new InvalidArgumentException('A declared preview field must be a visible existing model column.');
                }

                $before[$this->formatValue($record->getRawOriginal($field))] = true;
            }

            $oldValue = count($before) === 1
                ? (string) array_key_first($before)
                : (string) __('safe-actions::messages.modal.mixed_values');

            $lines[] = Str::headline($field).': '.$oldValue.' → '.$this->formatValue($newValue);
        }

        return implode(PHP_EOL, $lines);
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return (string) __('safe-actions::messages.modal.null_value');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (! is_scalar($value)) {
            return (string) __('safe-actions::messages.modal.unavailable_value');
        }

        return Str::limit((string) $value, 80);
    }
}
