# Security policy

Mavvix Safe Actions is **pre-alpha** and does not have a supported production release.

Please report suspected scope bypasses, tenant boundary violations, unauthorized execution, replay vulnerabilities or data-corruption issues privately through GitHub's **Report a vulnerability** / Security Advisories feature, if enabled. If that feature is unavailable, contact the repository maintainer through a private channel rather than opening a public exploit report.

Include the package commit, Filament and Laravel versions, database driver, reproduction steps and the expected vs. observed record set. Never attach real customer data, access tokens or credentials.

## Scope

The package is intended to protect **bounded, Eloquent-backed, single-database bulk mutations**. The host application remains responsible for the authenticated actor, tenant-scoped query, authorization callback and database-only mutation callback.

External API calls, emails, files, queues and other side effects are outside the transaction rollback guarantee. Correctness on SQLite does not constitute a concurrency guarantee for MySQL or PostgreSQL.

No security-review completion, response-time SLA, or stable-version support is claimed before v1.0.0.
