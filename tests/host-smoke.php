<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mavvix\SafeActions\Exceptions\UnsafeExecution;
use Mavvix\SafeActions\Execution\GuardedExecutor;
use Mavvix\SafeActions\Preflight\PreflightContext;
use Mavvix\SafeActions\Preflight\PreflightStore;
use Mavvix\SafeActions\Selection\SelectionInspector;

$hostPath = getenv('SAFE_ACTIONS_HOST_PATH');

if (! is_string($hostPath) || ! is_file($hostPath.'/bootstrap/app.php')) {
    throw new RuntimeException('SAFE_ACTIONS_HOST_PATH must point to an installed Laravel application.');
}

require $hostPath.'/vendor/autoload.php';

$app = require $hostPath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! Schema::hasTable('safe_action_preflights')) {
    throw new RuntimeException('The package migration was not published or applied.');
}

Schema::create('safe_action_host_smoke_orders', static function (Blueprint $table): void {
    $table->id();
    $table->unsignedBigInteger('tenant_id');
    $table->string('status');
    $table->timestamps();
});

$model = new class extends Model
{
    protected $table = 'safe_action_host_smoke_orders';

    protected $guarded = [];
};

$targetId = DB::table('safe_action_host_smoke_orders')->insertGetId([
    'tenant_id' => 1, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
]);
$otherTenantId = DB::table('safe_action_host_smoke_orders')->insertGetId([
    'tenant_id' => 2, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
]);

$scope = $model->newQuery()->where('tenant_id', 1)->where('status', 'pending');
$canArchive = static fn (Model $order): bool => $order->status === 'pending';
$inspected = $app->make(SelectionInspector::class)->inspect($scope, [$targetId], $canArchive);
$context = new PreflightContext('user', '42', 'web', 'archive', 'tenant', '1', 'admin');
$ticket = $app->make(PreflightStore::class)->issue($inspected, $context, $scope);

$result = $app->make(GuardedExecutor::class)->execute(
    $ticket->id,
    $context,
    $model->newQuery()->where('tenant_id', 1)->where('status', 'pending'),
    $canArchive,
    static function (Model $order): void {
        $order->status = 'archived';
        $order->save();
    },
);

if ($result->affectedRecords !== 1) {
    throw new RuntimeException('The guarded action did not change exactly one record.');
}

if (DB::table('safe_action_host_smoke_orders')->where('id', $targetId)->value('status') !== 'archived') {
    throw new RuntimeException('The selected row was not changed.');
}

if (DB::table('safe_action_host_smoke_orders')->where('id', $otherTenantId)->value('status') !== 'pending') {
    throw new RuntimeException('An unrelated tenant record was modified.');
}

try {
    $app->make(GuardedExecutor::class)->execute(
        $ticket->id,
        $context,
        $model->newQuery()->where('tenant_id', 1),
        static fn (): bool => true,
        static fn (): null => null,
    );
} catch (UnsafeExecution) {
    echo "PASS: fresh host installs package, publishes migrations, enforces scope, executes once, and rejects replay.\n";

    exit(0);
}

throw new RuntimeException('A previously consumed preflight was accepted.');
