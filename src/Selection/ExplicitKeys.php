<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Selection;

use Illuminate\Database\Eloquent\Model;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;

/** Immutable, lossless identity set for supported single-column primary keys. */
final readonly class ExplicitKeys
{
    /** @param list<string> $keys */
    private function __construct(public array $keys) {}

    /** @param array<array-key, mixed> $input */
    public static function forModel(Model $model, array $input, int $limit): self
    {
        if ($limit < 1 || $input === []) {
            throw UnsafeSelection::invalid();
        }

        if (count($input) > $limit) {
            throw UnsafeSelection::limitExceeded();
        }

        $normalized = [];
        $seen = [];
        $integerKeys = $model->getKeyType() === 'int';

        foreach ($input as $key) {
            if (! is_int($key) && ! is_string($key)) {
                throw UnsafeSelection::invalid();
            }

            $value = (string) $key;

            if ($value === '' || strlen($value) > 191) {
                throw UnsafeSelection::invalid();
            }

            if ($integerKeys && (! preg_match('/^(0|[1-9][0-9]*)$/D', $value)
                || (string) (int) $value !== $value)) {
                throw UnsafeSelection::invalid();
            }

            // A string key must not collapse to a different database identity.
            if (! $integerKeys && trim($value) !== $value) {
                throw UnsafeSelection::invalid();
            }

            if (isset($seen[$value])) {
                throw UnsafeSelection::invalid();
            }

            $seen[$value] = true;
            $normalized[] = $value;
        }

        sort($normalized, SORT_STRING);

        return new self($normalized);
    }

    public function digest(): string
    {
        return hash('sha256', json_encode($this->keys, JSON_THROW_ON_ERROR));
    }

    public function count(): int
    {
        return count($this->keys);
    }
}
