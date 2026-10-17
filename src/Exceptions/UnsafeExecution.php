<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Exceptions;

use RuntimeException;

final class UnsafeExecution extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self('The preflight is missing, expired, already used or belongs to another context.');
    }

    public static function unbound(): self
    {
        return new self('This preflight was not bound to a resource query and cannot be executed.');
    }

    public static function scopeChanged(): self
    {
        return new self('The permitted query changed after the preflight. Start a new preview.');
    }

    public static function selectionChanged(): self
    {
        return new self('The selected records changed after the preflight. Start a new preview.');
    }

    public static function connectionMismatch(): self
    {
        return new self('Preflight and selected records must use the same database connection.');
    }

    public static function unsupportedScope(): self
    {
        return new self('A paginated or limited query cannot safely execute this selection.');
    }
}
