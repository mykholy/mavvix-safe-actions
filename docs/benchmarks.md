# Real-driver microbenchmarks

These measurements time the bounded synchronous Eloquent workflow, not an HTTP browser request, production workload or guaranteed latency SLA.

The benchmark uses a fresh test database and probes 1, 100 and 500 records:

1. Insert the requested tenant-scoped rows outside the measurement.
2. Measure the exact-ID inspector plus preflight ticket insertion.
3. Measure guarded execution, per-row policy checks, Eloquent updates and ticket consumption inside one transaction.
4. Verify all requested rows changed before appending one JSONL result. The repeated test resets its fixture outside the timing window for each iteration.

Run in a test database only. The MySQL 8.4 and PostgreSQL 16 GitHub Actions jobs produce safe-actions-bench-mysql and safe-actions-bench-postgres JSONL artifacts.

The CI also runs `RepeatedPerformanceBenchmarkTest` on each database: three unmeasured warm-up iterations followed by 20 separate measured runs for each 1/100/500-record workload. Each run has a new scoped selection, newly issued ticket and fresh rows; data setup and final assertions are excluded from the timed windows. Empirical **nearest-rank** P50/P95/P99, minimum/maximum and SQL query-count range are written as separate JSONL records with `mode=warmed-repeated`.

These percentiles describe **20 sequential, single-process samples** on ephemeral CI runners. For 20 samples, P99 is simply the maximum observed result; it does not establish a production tail-latency guarantee. The legacy single-run `PerformanceBenchmarkTest` remains for historical comparison. No throughput or memory delta can be inferred. A separate concurrent load-testing rig remains necessary before publishing production performance targets or raising the 500-record ceiling.

The php_process_peak_memory_bytes metric reports the PHP process's cumulative peak, not memory consumed solely by one action. sql_query_count includes package queries and is not a latency proxy.

For reproducibility, use GitHub Actions with the recorded commit SHA and database driver. Do not interpret green CI as proof of a production latency SLO.
