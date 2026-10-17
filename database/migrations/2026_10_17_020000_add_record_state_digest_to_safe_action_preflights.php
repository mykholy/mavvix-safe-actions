<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Existing pre-alpha installations may have tickets issued without row-state
 * binding. They must be re-previewed after upgrading.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('safe_action_preflights') || Schema::hasColumn('safe_action_preflights', 'record_state_digest')) {
            return;
        }

        Schema::table('safe_action_preflights', function (Blueprint $table): void {
            $table->char('record_state_digest', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('safe_action_preflights', function (Blueprint $table): void {
            $table->dropColumn('record_state_digest');
        });
    }
};
