<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mavvix\SafeActions\Execution\GuardedExecutor;
use Mavvix\SafeActions\Preflight\PreflightContext;
use Mavvix\SafeActions\Preflight\PreflightStore;
use Mavvix\SafeActions\Selection\SelectionInspector;
use Mavvix\SafeActions\Tests\Fixtures\ProbeOrder;
use Mavvix\SafeActions\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PerformanceBenchmarkTest extends TestCase
{
    /** @return array<string, array{int}> */
    public static function recordCounts(): array
    {
        return [
            'one record' => [1],
            'one hundred records' => [100],
            'five hundred records' => [500],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! getenv('SAFE_ACTIONS_BENCHMARK_REPORT')) {
            self::markTestSkipped('Benchmarks run only in the dedicated database-driver CI step.');
        }

        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'pgsql'], true)) {
            self::markTestSkipped('Benchmarks require a real MySQL or PostgreSQL driver.');
        }
    }

    #[DataProvider('recordCounts')]
    public function test_bounded_preview_and_transaction(int $count): void
    {
        $now = now();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'tenant_id' => 1,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('probe_orders')->insert($rows);

        $ids = ProbeOrder::query()->where('tenant_id', 1)->pluck('id')->all();
        self::assertCount($count, $ids);

        $scope = ProbeOrder::query()->where('tenant_id', 1)->where('status', 'pending');
        $context = new PreflightContext('user', '42', 'web', 'archive', 'tenant', '1', 'admin');
        $mayAct = static fn (Model $record): bool => $record->status === 'pending';
        $queries = 0;

        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $start = hrtime(true);
        $inspection = (new SelectionInspector)->inspect($scope, $ids, $mayAct);
        $ticket = (new PreflightStore)->issue($inspection, $context, $scope);
        $previewMs = round((hrtime(true) - $start) / 1_000_000, 3);

        $start = hrtime(true);
        $result = app(GuardedExecutor::class)->execute(
            $ticket->id,
            $context,
            ProbeOrder::query()->where('tenant_id', 1)->where('status', 'pending'),
            $mayAct,
            static function (Model $record): void {
                $record->status = 'archived';
                $record->save();
            },
        );
        $executeMs = round((hrtime(true) - $start) / 1_000_000, 3);

        self::assertSame($count, $result->affectedRecords);
        self::assertSame($count, ProbeOrder::query()->where('status', 'archived')->count());

        $report = [
            'driver' => DB::connection()->getDriverName(),
            'records' => $count,
            'preview_ms' => $previewMs,
            'transaction_ms' => $executeMs,
            'sql_query_count' => $queries,
            'php_process_peak_memory_bytes' => memory_get_peak_usage(true),
        ];

        $reportPath = getenv('SAFE_ACTIONS_BENCHMARK_REPORT');
        self::assertIsString($reportPath);

        file_put_contents($reportPath, json_encode($report, JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
        fwrite(STDERR, PHP_EOL.'BENCHMARK '.json_encode($report, JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
