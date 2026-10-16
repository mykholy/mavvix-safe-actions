# Execution security model

The design goal is **no unexpected record mutations**, not to replace Laravel policies, tenant scoping or database transactions.

## Trust boundaries

**Trusted input:** authenticated server user, active guard, resolved tenant and panel, operation name, an Eloquent query built by server code, authorization and database mutation callbacks.

**Untrusted input:** selected checkbox IDs, inverse-selection exclusions, Livewire state, preflight UUID, and any other client-submitted action data.

## Preview invariant

The inspector validates a canonical key list and resolves it against the live, filtered Eloquent query. It rejects missing, foreign-tenant and unauthorized rows. The Filament adapter also checks row selectability and denies unsupported pivot/non-Eloquent modes.

The resulting preflight ticket persists the exact IDs, digest, actor, guard, tenant, panel and action. A *query-bound* ticket additionally persists a SHA-256 fingerprint of the model, database connection, SQL and bindings. A ticket with no query fingerprint is **not executable**.

## Confirmation invariant

A single database transaction:

1. Locks the preflight ticket and rejects missing, expired or previously consumed tickets.
2. Verifies the current server context and query fingerprint.
3. Reads and locks every requested row using the **fresh** server-scoped query.
4. Requires the exact same set of IDs and re-evaluates authorization.
5. Applies the mutation callback, then marks the ticket consumed.
6. Commits both the business changes and ticket consumption together.

Any exception before commit rolls back the transaction. The executor performs **no automatic transaction retries** because a custom mutation callback might not be safe to repeat.

## Explicit limits

| Case | Expected outcome |
| --- | --- |
| ID absent from permitted query | Entire action rejected |
| Policy revoked or row made unselectable | Entire action rejected |
| Different actor, tenant, guard, panel or action | Ticket unavailable |
| Changed SQL or query bindings | New preview required |
| Expired / already used ticket | Ticket unavailable |
| More than the configured record limit | Entire action rejected |
| Pivot identity / unbounded selection / foreign DB connection | Unsupported |
| Failure during database callback | Transaction rolled back |
| External I/O during callback | **Not protected; forbidden by contract** |

## Remaining verification work

- MySQL 8.4 and PostgreSQL 16 functional tests pass; still require **two-session parallel submission** tests for locking and idempotency.
- Two-session parallel confirmation of the same ticket.
- Browser and accessibility tests for modal error, cancel and success states.
- Static analysis and independent security review.
- Defensive handling of model hooks that dispatch external effects, preferably via transactional outbox.
- Explicit support-policy decision for complex grouped and relationship-backed tables.

The project must not claim an immutable field diff, guaranteed rollback of third-party side effects, or generic multi-database atomicity.
