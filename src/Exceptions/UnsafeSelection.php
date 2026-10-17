<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Exceptions;

use RuntimeException;

final class UnsafeSelection extends RuntimeException
{
    public static function invalid(): self
    {
        return new self((string) __('safe-actions::messages.errors.invalid'));
    }

    public static function limitExceeded(): self
    {
        return new self((string) __('safe-actions::messages.errors.limit_exceeded'));
    }

    public static function scopeMismatch(): self
    {
        return new self((string) __('safe-actions::messages.errors.scope_mismatch'));
    }

    public static function unsupported(): self
    {
        return new self((string) __('safe-actions::messages.errors.unsupported'));
    }

    public static function unauthorized(): self
    {
        return new self((string) __('safe-actions::messages.errors.unauthorized'));
    }
}
