<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mavvix\SafeActions\Exceptions\UnsafeExecution;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;
use Mavvix\SafeActions\Execution\GuardedExecutor;
use Mavvix\SafeActions\Preflight\PreflightContext;
use Mavvix\SafeActions\Preflight\PreflightStore;
use Mavvix\SafeActions\Preflight\SafeActionPreflight;
use Mavvix\SafeActions\Selection\SelectionInspector;
use Mavvix\SafeActions\Tests\Fixtures\ProbeOrder;
use Mavvix\SafeActions\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Throwable;

/**
 * Small, reproducible, independent-PDO concurrency qualification.
 *
 * These CI diagnostics are not production load or latency SLO evidence.
 */
final class ConcurrentLoadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! getenv('SAFE_ACTIONS_CONCURRENT_REPORT')) {
            self::markTestSkipped('Concurrent load runs only in dedicated database CI.');
        }

        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'pgsql'], true)) {
            self::markTestSkipped('Concurrent load requires MySQL or PostgreSQL.');
        }

        if (! function_exists('pcntl_fork') || ! function_exists('pcntl_waitpid')) {
            self::markTestSkipped('Concurrent load requires pcntl.');
        }
    }

    /** @return array<string, array{string, int, int}> */
    public static function workloads(): array
    {
        return [
            '8 submissions, 1 ticket, 100 rows' => ['shared-ticket', 8, 100],
            '8 admins, competing tickets, 100 rows' => ['overlapping-tickets', 8, 100],
            '4 submissions, 1 ticket, 500 rows' => ['shared-ticket', 4, 500],
            '4 independent tenants, 100 rows each' => ['isolated-tenants', 4, 100],
        ];
    }

    #[DataProvider('workloads')]
    public function test_concurrent_bounded_actions_do_not_cross_scope_or_double_commit(
        string $mode,
        int $workers,
        int $rows,
    ): void {
        $isolated = $mode === 'isolated-tenants';
        $tasks = [];
        $sharedTicket = null;

        for ($worker = 0; $worker < $workers; $worker++) {
            $tenant = $isolated ? $worker + 1 : 1;
            $actor = $mode === 'shared-ticket' ? '42' : (string) (42 + $worker);

            if ($worker === 0 || $isolated) {
                $this->seedTenant($tenant, $rows);
            }

            if ($mode === 'shared-ticket' && $sharedTicket !== null) {
                $ticket = $sharedTicket;
            } else {
                $ticket = $this->issueTicket($tenant, $actor);

                if ($mode === 'shared-ticket') {
                    $sharedTicket = $ticket;
                }
            }

            $tasks[] = ['tenant' => $tenant, 'actor' => $actor, 'ticket' => $ticket];
        }

        $directory = sys_get_temp_dir().'/safe-actions-load-'.bin2hex(random_bytes(12));

        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('Cannot create process synchronization directory.');
        }

        $children = [];
        $started = hrtime(true);

        try {
            foreach ($tasks as $index => $task) {
                $pid = pcntl_fork();

                if ($pid === -1) {
                    throw new RuntimeException('Cannot start an independent database worker.');
                }

                if ($pid === 0) {
                    $this->runWorker($index, $task, $directory);
                }

                $children[] = $pid;
            }

            $this->waitForMarkers($directory, $workers);
            file_put_contents($directory.'/start', 'go');

            $deadline = microtime(true) + 75;

            foreach ($children as $pid) {
                do {
                    $finished = pcntl_waitpid($pid, $status, WNOHANG);

                    if ($finished === $pid) {
                        self::assertTrue(pcntl_wifexited($status));
                        self::assertSame(0, pcntl_wexitstatus($status));

                        break;
                    }

                    if ($finished === -1 || microtime(true) >= $deadline) {
                        throw new RuntimeException('Concurrent worker exceeded the timeout.');
                    }

                    usleep(10_000);
                } while (true);
            }

            $outcomes = [];

            foreach (array_keys($tasks) as $index) {
                $result = $directory.'/'.$index.'.result';
                self::assertFileExists($result);
                $outcomes[] = json_decode((string) file_get_contents($result), true, 512, JSON_THROW_ON_ERROR);
            }

            $successes = count(array_filter($outcomes, static fn (array $outcome): bool => $outcome['result'] === 'success'));
            $expected = $isolated ? $workers : 1;

            self::assertSame($expected, $successes, 'One winner per independent selection boundary.');

            foreach ($outcomes as $outcome) {
                self::assertContains($outcome['result'], ['success', UnsafeExecution::class, UnsafeSelection::class]);
            }

            self::assertSame($expected * $rows, ProbeOrder::query()->where('status', 'archived')->count());
            self::assertSame(0, ProbeOrder::query()->where('status', 'pending')->count());
            self::assertSame($expected, SafeActionPreflight::query()->whereNotNull('consumed_at')->count());

            $elapsed = (hrtime(true) - $started) / 1_000_000;
            $report = [
                'driver' => DB::connection()->getDriverName(),
                'mode' => $mode,
                'workers' => $workers,
                'rows_per_tenant_or_selection' => $rows,
                'successful_confirmations' => $successes,
                'rejected_confirmations' => $workers - $successes,
                'total_wall_ms' => round($elapsed, 3),
                'worker_duration_ms' => array_column($outcomes, 'duration_ms'),
                'rejection_types' => array_count_values(array_column($outcomes, 'result')),
            ];

            $path = getenv('SAFE_ACTIONS_CONCURRENT_REPORT');
            self::assertIsString($path);
            file_put_contents($path, json_encode($report, JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
            fwrite(STDERR, PHP_EOL.'CONCURRENT LOAD '.json_encode($report, JSON_THROW_ON_ERROR).PHP_EOL);
        } finally {
            foreach ($children as $pid) {
                $status = 0;

                if (pcntl_waitpid($pid, $status, WNOHANG) === 0 && function_exists('posix_kill')) {
                    posix_kill($pid, SIGTERM);
                    pcntl_waitpid($pid, $status);
                }
            }

            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($directory);
        }
    }

    private function seedTenant(int $tenant, int $rows): void
    {
        $now = now();
        DB::table('probe_orders')->insert(array_fill(0, $rows, [
            'tenant_id' => $tenant,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    private function context(int $tenant, string $actor): PreflightContext
    {
        return new PreflightContext('user', $actor, 'web', 'archive', 'tenant', (string) $tenant, 'admin');
    }

    private function issueTicket(int $tenant, string $actor): string
    {
        $scope = ProbeOrder::query()->where('tenant_id', $tenant)->where('status', 'pending');
        $keys = (clone $scope)->pluck('id')->all();
        $authorize = static fn (Model $record): bool => $record->status === 'pending';
        $inspection = (new SelectionInspector)->inspect($scope, $keys, $authorize);

        return (new PreflightStore)->issue($inspection, $this->context($tenant, $actor), $scope)->id;
    }

    /** @param array{tenant:int, actor:string, ticket:string} $task */
    private function runWorker(int $index, array $task, string $directory): never
    {
        // Forked children must never reuse their parent's PDO connection.
        DB::purge();
        $start = hrtime(true);
        $result = 'success';

        try {
            file_put_contents($directory.'/'.$index.'.ready', '1');

            while (! is_file($directory.'/start')) {
                if ((hrtime(true) - $start) > 15_000_000_000) {
                    throw new RuntimeException('Worker start barrier timed out.');
                }

                usleep(5_000);
            }

            app(GuardedExecutor::class)->execute(
                $task['ticket'],
                $this->context($task['tenant'], $task['actor']),
                ProbeOrder::query()->where('tenant_id', $task['tenant'])->where('status', 'pending'),
                static fn (Model $record): bool => $record->status === 'pending',
                static function (Model $record): void {
                    $record->status = 'archived';
                    $record->save();
                },
            );
        } catch (Throwable $exception) {
            $result = $exception::class;
        }

        $outcome = [
            'result' => $result,
            'duration_ms' => round((hrtime(true) - $start) / 1_000_000, 3),
        ];

        file_put_contents($directory.'/'.$index.'.result', json_encode($outcome, JSON_THROW_ON_ERROR));
        exit(0);
    }

    private function waitForMarkers(string $directory, int $workers): void
    {
        $deadline = microtime(true) + 15;

        while (count(glob($directory.'/*.ready') ?: []) < $workers) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Workers did not reach their start barrier.');
            }

            usleep(10_000);
        }
    }
}
