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
use RuntimeException;
use Throwable;

final class ConcurrentExecutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'pgsql'], true)) {
            self::markTestSkipped('Concurrency locking must be verified on MySQL or PostgreSQL.');
        }

        if (! function_exists('pcntl_fork') || ! function_exists('pcntl_waitpid')) {
            self::markTestSkipped('The pcntl extension is required for independent database sessions.');
        }
    }

    private function context(): PreflightContext
    {
        return new PreflightContext('user', '42', 'web', 'archive', 'tenant', '1', 'admin');
    }

    private function preview(ProbeOrder $record): string
    {
        $scope = ProbeOrder::query()->where('tenant_id', 1)->where('status', 'pending');
        $inspection = (new SelectionInspector)->inspect(
            $scope,
            [$record->getKey()],
            fn (Model $row): bool => $row->status === 'pending',
        );

        return (new PreflightStore)->issue($inspection, $this->context(), $scope)->id;
    }

    /**
     * @param  array{first: string, second: string}  $tickets
     * @return array{first: string, second: string}
     */
    private function runCompetingConfirmations(array $tickets): array
    {
        $dir = sys_get_temp_dir().'/mavvix-safe-actions-'.bin2hex(random_bytes(12));

        if (! mkdir($dir, 0700) && ! is_dir($dir)) {
            throw new RuntimeException('Unable to create process synchronization directory.');
        }

        $children = [];

        try {
            foreach (['first', 'second'] as $name) {
                $pid = pcntl_fork();

                if ($pid === -1) {
                    throw new RuntimeException('Could not fork an independent database session.');
                }

                if ($pid === 0) {
                    $this->runChild($name, $tickets[$name], $dir);
                }

                $children[$name] = $pid;
            }

            foreach ($children as $pid) {
                $this->waitForChild($pid);
            }

            $outcomes = [];

            foreach (array_keys($children) as $name) {
                $file = $dir.'/'.$name.'.result';
                self::assertFileExists($file, 'Child failed without returning an outcome.');
                $outcomes[$name] = trim((string) file_get_contents($file));
            }

            return $outcomes;
        } finally {
            foreach ($children as $pid) {
                $status = 0;

                if (pcntl_waitpid($pid, $status, WNOHANG) === 0 && function_exists('posix_kill')) {
                    posix_kill($pid, SIGTERM);
                    pcntl_waitpid($pid, $status);
                }
            }

            foreach (glob($dir.'/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($dir);
        }
    }

    private function runChild(string $name, string $ticket, string $dir): never
    {
        // The fork inherits the parent's PDO socket. Never share it between
        // processes; force a separate physical connection before any queries.
        DB::purge();

        try {
            if ($name === 'second') {
                self::waitForFile($dir.'/first.inside');
                file_put_contents($dir.'/second.started', '1');
            }

            app(GuardedExecutor::class)->execute(
                $ticket,
                $this->context(),
                ProbeOrder::query()->where('tenant_id', 1)->where('status', 'pending'),
                fn (Model $record): bool => $record->status === 'pending',
                function (Model $record) use ($dir, $name): void {
                    if ($name === 'first') {
                        // First transaction holds both its ticket and row locks
                        // while the competing connection starts a confirmation.
                        file_put_contents($dir.'/first.inside', '1');
                        self::waitForFile($dir.'/second.started');
                        usleep(400_000);
                    }

                    $record->status = 'archived';
                    $record->save();
                },
            );

            file_put_contents($dir.'/'.$name.'.result', 'success');
        } catch (Throwable $exception) {
            file_put_contents($dir.'/'.$name.'.result', $exception::class);
        }

        exit(0);
    }

    private static function waitForFile(string $path): void
    {
        $deadline = microtime(true) + 15;

        while (! is_file($path)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException("Timed out waiting for synchronization marker [{$path}].");
            }

            usleep(10_000);
        }
    }

    private function waitForChild(int $pid): void
    {
        $deadline = microtime(true) + 20;

        while (true) {
            $waited = pcntl_waitpid($pid, $status, WNOHANG);

            if ($waited === $pid) {
                self::assertTrue(pcntl_wifexited($status), 'Child was terminated before completing.');
                self::assertSame(0, pcntl_wexitstatus($status), 'Child exited with a nonzero status.');

                return;
            }

            if ($waited === -1 || microtime(true) > $deadline) {
                throw new RuntimeException('Independent database session timed out.');
            }

            usleep(10_000);
        }
    }

    public function test_same_ticket_can_commit_once_only_across_independent_connections(): void
    {
        $order = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $ticket = $this->preview($order);

        $results = $this->runCompetingConfirmations(['first' => $ticket, 'second' => $ticket]);

        self::assertSame('success', $results['first']);
        self::assertSame(UnsafeExecution::class, $results['second']);
        self::assertSame('archived', $order->fresh()->status);
        self::assertNotNull(SafeActionPreflight::findOrFail($ticket)->consumed_at);
    }

    public function test_concurrent_stale_scope_aborts_second_ticket_without_mutation(): void
    {
        $order = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $firstTicket = $this->preview($order);
        $secondTicket = $this->preview($order);

        $results = $this->runCompetingConfirmations([
            'first' => $firstTicket,
            'second' => $secondTicket,
        ]);

        self::assertSame('success', $results['first']);
        self::assertSame(UnsafeSelection::class, $results['second']);
        self::assertSame('archived', $order->fresh()->status);
        self::assertNotNull(SafeActionPreflight::findOrFail($firstTicket)->consumed_at);
        self::assertNull(SafeActionPreflight::findOrFail($secondTicket)->consumed_at);
    }
    public function test_abrupt_process_exit_rolls_back_record_and_preflight_consumption(): void
    {
        $order = ProbeOrder::create(['tenant_id' => 1, 'status' => 'pending']);
        $ticket = $this->preview($order);
        $marker = tempnam(sys_get_temp_dir(), 'mavvix-crash-');

        if ($marker === false) {
            throw new RuntimeException('Unable to create transaction crash marker.');
        }

        unlink($marker);

        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Unable to fork crash recovery test.');
        }

        if ($pid === 0) {
            DB::purge();

            app(GuardedExecutor::class)->execute(
                $ticket,
                $this->context(),
                ProbeOrder::query()->where('tenant_id', 1)->where('status', 'pending'),
                fn (Model $record): bool => $record->status === 'pending',
                function (Model $record) use ($marker): void {
                    $record->status = 'archived';
                    $record->save();

                    // Terminate the PHP process after the database UPDATE,
                    // but before the executor can consume the ticket or COMMIT.
                    file_put_contents($marker, 'inside-transaction');
                    exit(73);
                },
            );

            exit(74);
        }

        try {
            $this->waitForChild($pid);

            self::fail('The child should exit with a deliberate non-zero code.');
        } catch (RuntimeException $exception) {
            // A non-zero process exit is expected; waitForChild reports it.
            self::assertStringContainsString('Child exited with a nonzero status', $exception->getMessage());
        } finally {
            clearstatcache(true, $marker);
        }

        self::assertFileExists($marker, 'The child must reach the mutation before terminating.');
        unlink($marker);

        self::assertSame('pending', $order->fresh()->status);
        self::assertNull(SafeActionPreflight::findOrFail($ticket)->consumed_at);

        $result = app(GuardedExecutor::class)->execute(
            $ticket,
            $this->context(),
            ProbeOrder::query()->where('tenant_id', 1)->where('status', 'pending'),
            fn (Model $record): bool => $record->status === 'pending',
            function (Model $record): void {
                $record->status = 'archived';
                $record->save();
            },
        );

        self::assertSame(1, $result->affectedRecords);
        self::assertSame('archived', $order->fresh()->status);
        self::assertNotNull(SafeActionPreflight::findOrFail($ticket)->consumed_at);
    }

}
