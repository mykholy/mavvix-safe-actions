<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade pre-alpha installs that applied the first migration before scope
 * fingerprinting was introduced. Fresh installs already have this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('safe_action_preflights') || Schema::hasColumn('safe_action_preflights', 'scope_digest')) {
            return;
        }

        Schema::table('safe_action_preflights', function (Blueprint $table): void {
            $table->char('scope_digest', 64)->nullable();
        });
    }

    public function down(): void
    {
        // The base migration also owns the column on fresh installations.
        // Removing it here would break an existing preflight table.
    }
};
