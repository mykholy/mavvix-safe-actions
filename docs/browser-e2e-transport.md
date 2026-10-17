# Browser CI transport reliability

The Filament demonstration uses Laravel's single-process development server. On GitHub-hosted CI, an inspection GET can sporadically experience a TCP reset while Livewire is completing an action.

The browser harness retries **only transport resets/refused connections** on its isolated read-only test-state endpoint, at most four attempts with bounded backoff. HTTP errors are not retried, and state assertions still require exact target mutation, no foreign-tenant changes and correct ticket semantics. The confirm flow waits for modal closure instead of assuming an arbitrary 500ms delay.

This is infrastructure test stabilization, not retry behavior in the production package. GuardedExecutor never automatically retries developer mutation callbacks.

A repeatable application exception, incorrect response, state mismatch, or accessibility failure still fails CI.
