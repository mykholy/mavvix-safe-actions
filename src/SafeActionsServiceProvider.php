<?php

declare(strict_types=1);

namespace Mavvix\SafeActions;

use Illuminate\Support\ServiceProvider;
use Mavvix\SafeActions\Console\PrunePreflightsCommand;

final class SafeActionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/safe-actions.php', 'safe-actions');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'safe-actions');

        if ($this->app->runningInConsole()) {
            $this->commands([PrunePreflightsCommand::class]);
        }

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/safe-actions'),
        ], 'safe-actions-translations');

        $this->publishes([
            __DIR__.'/../config/safe-actions.php' => config_path('safe-actions.php'),
        ], 'safe-actions-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'safe-actions-migrations');
    }
}
