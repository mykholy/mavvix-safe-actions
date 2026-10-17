# Real-driver microbenchmarks

These measurements time the bounded synchronous Eloquent workflow, not an HTTP browser request, production workload or guaranteed latency SLA.

The benchmark uses a fresh test database and probes 1, 100 and 500 records:

1. Insert the requested tenant-scoped rows outside the measurement.
2. Measure the exact-ID inspector plus preflight ticket insertion.
3. Measure guarded execution, per-row policy checks, Eloquent updates and ticket consumption inside one transaction.
4. Verify all requested rows changed before appending one JSONL result.

Run in a test database only. The MySQL 8.4 and PostgreSQL 16 GitHub Actions jobs produce safe-actions-bench-mysql and safe-actions-bench-postgres JSONL artifacts.

Values are single-run diagnostics, subject to ephemeral runner load, CI contention and container variability. No percentile, throughput or memory delta is inferred from three single-run measurements. A separate load-testing rig is required before stating public performance targets or lifting the 500-record ceiling.

The php_process_peak_memory_bytes metric reports the PHP process's cumulative peak, not memory consumed solely by one action. sql_query_count includes package queries and is not a latency proxy.

For reproducibility, use GitHub Actions with the recorded commit SHA and database driver. Do not interpret green CI as proof of a production latency SLO.
