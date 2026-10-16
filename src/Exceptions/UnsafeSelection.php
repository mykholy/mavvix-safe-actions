<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Exceptions;

use RuntimeException;

final class UnsafeSelection extends RuntimeException
{
    public static function invalid(): self
    {
        return new self('The selection contains invalid or duplicate record keys.');
    }

    public static function limitExceeded(): self
    {
        return new self('The selection exceeds the configured safe limit.');
    }

    public static function scopeMismatch(): self
    {
        return new self('Some selected records are not available within the permitted scope.');
    }

    public static function unauthorized(): self
    {
        return new self('One or more selected records are not authorized.');
    }
}
