# Filament Plugins directory submission

The package is a **free, MIT-licensed community package** for **Filament 5**.

**Directory:** https://filamentphp.com/plugins  
**Author access:** https://filamentphp.com/author  
**GitHub:** https://github.com/mykholy/mavvix-safe-actions  
**Packagist:** https://packagist.org/packages/mykholy/mavvix-safe-actions (verify indexing before using)

## Suggested listing content

**Name:** Mavvix Safe Actions

**Short description:**

> Fail-closed Filament 5 bulk actions with verified record selection, tenant-aware preflight tickets, permission rechecks, and transaction-bound execution.

**Overview:**

> Mavvix Safe Actions protects consequential, database-only bulk operations from stale or mismatched selections. It resolves exact Eloquent record identities against the current filtered query, rechecks authorization and persisted row values at confirmation, and uses one-time expiring tickets with a single database transaction. Supports a native confirmation modal, read-only declared-change preview, English/Arabic translations and a bounded 500-record default. Unsupported joins, groupings, pivot identities and cross-database operations are rejected rather than approximated.

**Supported:** Laravel 12/13; PHP ^8.2; Filament 5; MySQL 8.4 and PostgreSQL 16 tested.

**Cost / license:** Free / MIT.

**Features (only if the directory offers these fields):** multilingual support; modal-based actions; table actions. Do not mark an unverified feature as supported.

## Assets and evidence

Use genuine screenshots from the tested Filament host fixture showing:

1. A table with a visible selected record count.
2. The native confirmation modal with the verified selection.
3. The declared change preview in English.
4. Arabic RTL modal and dark mode where possible.

Capture these from a **real browser** on a reproducible fixture. The automated browser test covers these interactions, but **an image has not been added to this repository or claimed here**. Do not use a fabricated admin interface or third-party screenshots as product proof.

## Compliance and limitations

- The plugin must never be described as **official** or endorsed by the Filament team.
- Do not claim an independent security audit, full rollback for external effects, or proven production-load SLOs.
- Manual screen-reader accessibility evaluation and external security sign-off remain open; see [SECURITY.md](../SECURITY.md).
- Submit the package after confirming its versioned Composer installation via Packagist. If the directory requires additional fields or an author account action, complete those in the Filament author dashboard.

See https://filamentphp.com/plugins for the directory and submission entry point.
