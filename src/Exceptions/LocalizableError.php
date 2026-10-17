<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Exceptions;

use Illuminate\Container\Container;

/**
 * Exception factories must work before Laravel bootstraps its translator,
 * including in CLI contexts and framework-independent unit tests.
 */
final class LocalizableError
{
    public static function message(string $key, string $fallback): string
    {
        if (! Container::getInstance()->bound('translator')) {
            return $fallback;
        }

        $lookup = "safe-actions::messages.errors.{$key}";
        $translated = __($lookup);

        return is_string($translated) && $translated !== $lookup
            ? $translated
            : $fallback;
    }
}
