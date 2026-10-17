# Verified single-run CI performance measurements — 2026-10-17

[GitHub Actions run 38991844183](https://github.com/mykholy/mavvix-safe-actions/actions/runs/38991844183) performed the first measured, reproducible bounded Eloquent preview-and-execution checks on **Laravel 12 / PHP 8.3**, **MySQL 8.4** and **PostgreSQL 16**.

These are individual CI container measurements, **not** SLOs, percentile benchmarks, capacity tests, concurrency throughput or production guarantees. The actual raw JSONL artifacts are attached to that workflow run. Fixture data, runner load and production integrations can significantly change the results.

## Preview: selection verification and preflight storage

| Selected records | MySQL 8.4 | PostgreSQL 16 |
|---:|---:|---:|
| 1 | 10.960 ms | 9.474 ms |
| 100 | 3.091 ms | 2.883 ms |
| 500 | 7.205 ms | 6.658 ms |

Initial run includes startup overhead; the measurements are not a monotonically increasing latency curve.

## Transactional execution: locked reread, authorization, row updates and ticket consumption

| Selected records | MySQL 8.4 | PostgreSQL 16 | SQL query count |
|---:|---:|---:|---:|
| 1 | 5.613 ms | 4.503 ms | 8 |
| 100 | 65.410 ms | 53.296 ms | 107 |
| 500 | 327.167 ms | 267.449 ms | 507 |

The SQL query count covers the preview and execution operations together (not the fixture inserts or final assertions). The Eloquent mutation deliberately saves each record independently so per-record policy checks remain meaningful. This is not a bulk SQL update benchmark.

Peak PHP process memory recorded by the test process was 42,467,328, 44,564,480 and 46,661,632 bytes respectively for 1, 100 and 500 records. That metric is the **cumulative peak of the PHPUnit PHP process**, not incremental package memory use.

## Evidence and release implications

- Original test: [CI run 38991844183](https://github.com/mykholy/mavvix-safe-actions/actions/runs/38991844183)
- Reproduction harness: [PerformanceBenchmarkTest.php](../tests/Feature/PerformanceBenchmarkTest.php)
- Measurement methodology: [benchmarks.md](benchmarks.md)
- Default operation ceiling remains 500 records.
- Before increasing the ceiling or publishing performance targets, run repeated warmed-up samples, concurrency pressure, P50/P95/P99, lock-contention, database-level tracing and application-specific workload tests.
