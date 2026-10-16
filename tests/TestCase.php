<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mavvix\SafeActions\SafeActionsServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [SafeActionsServiceProvider::class, LivewireServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('probe_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('status');
            $table->timestamps();
        });
    }
}
