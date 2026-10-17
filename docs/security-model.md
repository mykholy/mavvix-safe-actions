# Execution security model

The design goal is **no unexpected record mutations**, not to replace Laravel policies, tenant scoping or database transactions.

## Trust boundaries

**Trusted input:** authenticated server user, active guard, resolved tenant and panel, operation name, an Eloquent query built by server code, authorization and database mutation callbacks.

**Untrusted input:** selected checkbox IDs, inverse-selection exclusions, Livewire state, preflight UUID, and any other client-submitted action data.

## Preview invariant

The inspector rejects unsafe SQL query shapes (including global-scope joins, aliases, grouping, and unions) before it validates a canonical key list and resolves it against the live, filtered Eloquent query. All package-owned Eloquent hydration during inspection, fingerprinting, declared preview and execution is wrapped in `Model::withoutEvents` so `retrieved` observers do not introduce side effects. Mutation-time `saving`/`updated` observers are intentionally **not** suppressed and must be reviewed for external effects. Filament's table itself may hydrate models outside the package, and host-provided policy callbacks must be pure. It rejects missing, foreign-tenant and unauthorized rows. The Filament adapter also checks row selectability and denies unsupported pivot/non-Eloquent modes.

The resulting preflight ticket persists the exact IDs, digest, actor, guard, tenant, panel and action. A *query-bound* ticket additionally persists a SHA-256 fingerprint of the model, database connection, SQL and bindings, plus a digest of the selected model's persisted attributes. Tickets without either fingerprint are **not executable**. The state digest is an optimistic concurrency check, not an immutable audit trail or an HMAC.

## Confirmation invariant

A single database transaction:

1. Locks the preflight ticket and rejects missing, expired or previously consumed tickets.
2. Verifies the current server context and query fingerprint.
3. Reads and locks every requested row using the **fresh** server-scoped query.
4. Requires the exact same set of IDs and re-evaluates authorization.
5. Rejects selected records whose loaded persisted model attributes changed after preview, even if their IDs and query scope did not.
6. Applies the mutation callback, then marks the ticket consumed.
7. Commits both the business changes and ticket consumption together.

Any exception before commit rolls back the transaction. The executor performs **no automatic transaction retries** because a custom mutation callback might not be safe to repeat.

## Explicit limits

| Case | Expected outcome |
| --- | --- |
| ID absent from permitted query | Entire action rejected |
| Policy revoked or row made unselectable | Entire action rejected |
| Different actor, tenant, guard, panel or action | Ticket unavailable |
| Changed SQL or query bindings | New preview required |
| Changed selected record IDs without changing filters | New preview required |
| Changed persisted selected record attributes without changing IDs or query | New preview required |
| Older ticket issued without a model-state digest | New preview required |
| Expired / already used ticket | Ticket unavailable |
| More than the configured record limit | Entire action rejected |
| Pivot identity / unbounded selection / foreign DB connection | Unsupported |
| Failure during database callback | Transaction rolled back |
| External I/O during callback | **Not protected; forbidden by contract** |

## Remaining verification work

- Functional and independent-process duplicate-ticket/stale-scope tests pass on MySQL 8.4 and PostgreSQL 16.
- Abrupt process exit after an UPDATE but before COMMIT has passed rollback/retry tests on both real database drivers.
- Additional lock contention, deadlock, cancellation and scale benchmarks still need verification.
- Browser and accessibility tests for modal error, cancel and success states.
- Larastan/PHPStan level 5 passes without a suppression baseline; independent security review remains required.
- Defensive handling of model hooks that dispatch external effects, preferably via transactional outbox.
- Explicit support-policy decision for complex grouped and relationship-backed tables.

The state digest detects changes between two observed database states; it cannot prevent an intervening change that is reverted before confirmation, nor protect external side effects or records modified by a callback through another connection. The project must not claim an immutable field diff, guaranteed rollback of third-party side effects, or generic multi-database atomicity.
