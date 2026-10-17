<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

$path = getenv('SAFE_ACTIONS_HOST_PATH');

if (! is_string($path)) {
    throw new RuntimeException('A Laravel host path is required.');
}

require $path.'/vendor/autoload.php';

$app = require $path.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

User::query()->create([
    'name' => 'Safe Actions Test User',
    'email' => 'safe-actions-test@example.invalid',
    'password' => Hash::make('test-only-password'),
]);

Schema::create('browser_orders', static function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->unsignedBigInteger('tenant_id');
    $table->string('status');
    $table->timestamps();
});

$rows = [
    ['name' => 'Pending One', 'tenant_id' => 1, 'status' => 'pending'],
    ['name' => 'Pending Two', 'tenant_id' => 1, 'status' => 'pending'],
    ['name' => 'Forbidden Row', 'tenant_id' => 1, 'status' => 'forbidden'],
    ['name' => 'Tenant Two', 'tenant_id' => 2, 'status' => 'pending'],
];

foreach ($rows as $row) {
    DB::table('browser_orders')->insert([
        ...$row,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

echo "Browser fixtures created.\n";
