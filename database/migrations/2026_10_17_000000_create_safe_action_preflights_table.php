<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safe_action_preflights', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('actor_type');
            $table->string('actor_id');
            $table->string('guard');
            $table->string('tenant_type')->nullable();
            $table->string('tenant_id')->nullable();
            $table->string('panel_id')->nullable();
            $table->string('action_key');
            $table->json('record_keys');
            $table->unsignedInteger('record_count');
            $table->char('selection_digest', 64);
            $table->char('scope_digest', 64)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['actor_type', 'actor_id', 'expires_at'], 'safe_action_preflights_actor_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safe_action_preflights');
    }
};
