# Security policy

Mavvix Safe Actions v1.0.0 is a **scoped stable SemVer API**, not an independently audited security product. It has passed the published automated CI matrix, including MySQL/PostgreSQL concurrency regressions. Its [first-party automated review](docs/security-review-v1.0.0.md) is public. **An independent security audit and human assistive-technology review have not been completed.** Use it only with a verified tenant scope, correct Laravel policy callbacks and database-only mutations. Practical safety depends on the host application.

Please report suspected scope bypasses, tenant boundary violations, unauthorized execution, replay vulnerabilities or data-corruption issues privately through GitHub's **Report a vulnerability** / Security Advisories feature, if enabled. If that feature is unavailable, contact the repository maintainer through a private channel rather than opening a public exploit report.

Include the package commit, Filament and Laravel versions, database driver, reproduction steps and the expected vs. observed record set. Never attach real customer data, access tokens or credentials.

## Scope

The package is intended to protect **bounded, Eloquent-backed, single-database bulk mutations**. The host application remains responsible for the authenticated actor, tenant-scoped query, authorization callback and database-only mutation callback.

External API calls, emails, files, queues and other side effects are outside the transaction rollback guarantee. Correctness on SQLite does not constitute a concurrency guarantee for MySQL or PostgreSQL.

No independent security certification, guaranteed exploit-free status, or production latency SLO is claimed. The maintainer must triage reports by impact and release patches for confirmed vulnerabilities. Before making a production deployment decision, review the [security scope and residual risks](docs/security-review-v1.0.0.md).
