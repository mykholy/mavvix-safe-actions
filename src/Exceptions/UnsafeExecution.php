<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Exceptions;

use RuntimeException;

final class UnsafeExecution extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self((string) __('safe-actions::messages.errors.unavailable'));
    }

    public static function unbound(): self
    {
        return new self((string) __('safe-actions::messages.errors.unbound'));
    }

    public static function scopeChanged(): self
    {
        return new self((string) __('safe-actions::messages.errors.scope_changed'));
    }

    public static function selectionChanged(): self
    {
        return new self((string) __('safe-actions::messages.errors.selection_changed'));
    }

    public static function connectionMismatch(): self
    {
        return new self((string) __('safe-actions::messages.errors.connection_mismatch'));
    }

    public static function unsupportedScope(): self
    {
        return new self((string) __('safe-actions::messages.errors.unsupported_scope'));
    }
}
