<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Mavvix\SafeActions\Preflight\PreflightContext;
use Mavvix\SafeActions\Preflight\PreflightStore;
use Mavvix\SafeActions\Preflight\SafeActionPreflight;
use Mavvix\SafeActions\Selection\SelectionInspection;
use Mavvix\SafeActions\Tests\TestCase;

final class PrunePreflightsCommandTest extends TestCase
{
    private function ticket(): string
    {
        $keys = ['1'];

        return (new PreflightStore)->issue(
            new SelectionInspection(1, hash('sha256', json_encode($keys, JSON_THROW_ON_ERROR)), $keys),
            new PreflightContext('user', '42', 'web', 'archive'),
        )->id;
    }

    public function test_prunes_only_tickets_beyond_retention_window(): void
    {
        $oldExpired = $this->ticket();
        $oldConsumed = $this->ticket();
        $recentExpired = $this->ticket();
        $active = $this->ticket();

        SafeActionPreflight::query()->whereKey($oldExpired)->update([
            'expires_at' => now()->subHours(48),
        ]);
        SafeActionPreflight::query()->whereKey($oldConsumed)->update([
            'consumed_at' => now()->subHours(48),
        ]);
        SafeActionPreflight::query()->whereKey($recentExpired)->update([
            'expires_at' => now()->subMinutes(15),
        ]);

        self::assertSame(0, Artisan::call('safe-actions:prune-preflights', ['--hours' => '24']));
        self::assertNull(SafeActionPreflight::find($oldExpired));
        self::assertNull(SafeActionPreflight::find($oldConsumed));
        self::assertNotNull(SafeActionPreflight::find($recentExpired));
        self::assertNotNull(SafeActionPreflight::find($active));
        $this->assertDatabaseCount('safe_action_preflights', 2);
    }

    public function test_invalid_retention_is_rejected_without_deleting_tickets(): void
    {
        $id = $this->ticket();

        self::assertSame(1, Artisan::call('safe-actions:prune-preflights', ['--hours' => '0']));
        self::assertNotNull(SafeActionPreflight::find($id));
        $this->assertDatabaseCount('safe_action_preflights', 1);
    }
}
