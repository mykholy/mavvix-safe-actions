<?php

declare(strict_types=1);

namespace Mavvix\SafeActions;

use Illuminate\Support\ServiceProvider;

final class SafeActionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/safe-actions.php', 'safe-actions');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/safe-actions.php' => config_path('safe-actions.php'),
        ], 'safe-actions-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'safe-actions-migrations');
    }
}
