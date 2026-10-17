<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Exceptions;

use RuntimeException;

final class UnsafeExecution extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self(LocalizableError::message('unavailable', 'The preflight is missing, expired, already used or belongs to another context.'));
    }

    public static function unbound(): self
    {
        return new self(LocalizableError::message('unbound', 'This preflight was not bound to a resource query and cannot be executed.'));
    }

    public static function scopeChanged(): self
    {
        return new self(LocalizableError::message('scope_changed', 'The permitted query changed after the preflight. Start a new preview.'));
    }

    public static function stateUnbound(): self
    {
        return new self(LocalizableError::message('state_unbound', 'This older preflight has no record-state snapshot. Start a new preview.'));
    }

    public static function recordChanged(): self
    {
        return new self(LocalizableError::message('record_changed', 'One or more selected records changed since preview. Start a new preview.'));
    }

    public static function selectionChanged(): self
    {
        return new self(LocalizableError::message('selection_changed', 'The selected records changed after the preflight. Start a new preview.'));
    }

    public static function connectionMismatch(): self
    {
        return new self(LocalizableError::message('connection_mismatch', 'Preflight and selected records must use the same database connection.'));
    }

    public static function unsupportedScope(): self
    {
        return new self(LocalizableError::message('unsupported_scope', 'A paginated or limited query cannot safely execute this selection.'));
    }
}
