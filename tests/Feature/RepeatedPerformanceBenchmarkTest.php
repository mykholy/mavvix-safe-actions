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

/**
 * Repeat real-driver bounded workloads after warm-up. Not a load test, SLO or
 * statistical guarantee under concurrency or production application hooks.
 */
final class RepeatedPerformanceBenchmarkTest extends TestCase
{
    private const WARMUPS = 3;

    private const SAMPLES = 20;

    /** @return array<string, array{int}> */
    public static function recordCounts(): array
    {
        return [
            'single record' => [1],
            'hundred records' => [100],
            'five hundred records' => [500],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! getenv('SAFE_ACTIONS_BENCHMARK_REPORT')) {
            self::markTestSkipped('Repeated benchmarks only run in dedicated database CI.');
        }

        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'pgsql'], true)) {
            self::markTestSkipped('Repeated benchmarks require MySQL or PostgreSQL.');
        }
    }

    #[DataProvider('recordCounts')]
    public function test_repeated_bounded_preview_and_execution(int $count): void
    {
        $previews = [];
        $executions = [];
        $queryCounts = [];
        $queries = 0;

        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        for ($run = 0; $run < self::WARMUPS + self::SAMPLES; $run++) {
            DB::table('safe_action_preflights')->delete();
            DB::table('probe_orders')->delete();

            $now = now();
            $rows = array_fill(0, $count, [
                'tenant_id' => 1,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('probe_orders')->insert($rows);

            $ids = ProbeOrder::query()->where('tenant_id', 1)->pluck('id')->all();
            self::assertCount($count, $ids);

            $scope = ProbeOrder::query()->where('tenant_id', 1)->where('status', 'pending');
            $context = new PreflightContext('user', '42', 'web', 'archive', 'tenant', '1', 'admin');
            $mayAct = static fn (Model $record): bool => $record->status === 'pending';

            $queries = 0;
            $start = hrtime(true);
            $inspection = (new SelectionInspector)->inspect($scope, $ids, $mayAct);
            $ticket = (new PreflightStore)->issue($inspection, $context, $scope);
            $previewMs = (hrtime(true) - $start) / 1_000_000;

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
            $executionMs = (hrtime(true) - $start) / 1_000_000;
            $operationQueries = $queries;

            self::assertSame($count, $result->affectedRecords);
            self::assertSame($count, ProbeOrder::query()->where('tenant_id', 1)->where('status', 'archived')->count());

            if ($run >= self::WARMUPS) {
                $previews[] = $previewMs;
                $executions[] = $executionMs;
                $queryCounts[] = $operationQueries;
            }
        }

        $report = [
            'mode' => 'warmed-repeated',
            'driver' => DB::connection()->getDriverName(),
            'records' => $count,
            'warmups' => self::WARMUPS,
            'samples' => self::SAMPLES,
            'preview_ms' => self::quantiles($previews),
            'transaction_ms' => self::quantiles($executions),
            'sql_query_count_min' => min($queryCounts),
            'sql_query_count_max' => max($queryCounts),
            'php_process_peak_memory_bytes' => memory_get_peak_usage(true),
        ];

        $reportPath = getenv('SAFE_ACTIONS_BENCHMARK_REPORT');
        self::assertIsString($reportPath);

        file_put_contents($reportPath, json_encode($report, JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
        fwrite(STDERR, PHP_EOL.'REPEATED BENCHMARK '.json_encode($report, JSON_THROW_ON_ERROR).PHP_EOL);
    }

    /**
     * Nearest-rank empirical quantiles, deliberately not a fitted distribution.
     *
     * @param list<float> $values
     * @return array{p50: float, p95: float, p99: float, min: float, max: float}
     */
    private static function quantiles(array $values): array
    {
        sort($values, SORT_NUMERIC);

        $at = static fn (float $percentile): float => round(
            $values[max(0, (int) ceil(count($values) * $percentile) - 1)],
            3,
        );

        return [
            'p50' => $at(0.5),
            'p95' => $at(0.95),
            'p99' => $at(0.99),
            'min' => round($values[0], 3),
            'max' => round($values[count($values) - 1], 3),
        ];
    }
}
