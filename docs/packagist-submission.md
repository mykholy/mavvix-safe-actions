# Publish Mavvix Safe Actions on Packagist

Use these exact project identifiers:

| Field | Value |
| --- | --- |
| GitHub repository | `https://github.com/mykholy/mavvix-safe-actions` |
| Composer package name | `mykholy/mavvix-safe-actions` |
| Latest stable tag | [GitHub Releases](https://github.com/mykholy/mavvix-safe-actions/releases/latest) |
| PHP / framework | PHP ^8.2, Filament ^5.0, Laravel ^12.0 or ^13.0 |
| License | MIT |
| Maintainer | [mykholy](https://github.com/mykholy) |

## Publish

1. Sign in to [Packagist](https://packagist.org/) with the account that will own the package.
2. Visit [Submit package](https://packagist.org/packages/submit) and submit the **HTTPS repository URL** above, not a tag URL or a PHP package name.
3. Verify that [the package page](https://packagist.org/packages/mykholy/mavvix-safe-actions) actually exists and lists the most recent `v1.0.x` tag and metadata. **Do not call it published until this check passes.**
4. Enable Packagist's GitHub synchronization, preferably by signing in with GitHub and authorizing Packagist for this repository. Check the package page for synchronization warnings. If needed, follow [Packagist's official webhook guide](https://packagist.org/about#how-to-update-packages).
5. In a disposable Laravel 12/13 + Filament 5 application, test a Packagist-only install **without** a `repositories.mavvix` override:

```bash
composer require 'mykholy/mavvix-safe-actions:^1.0'
composer show mykholy/mavvix-safe-actions --all
php artisan vendor:publish --tag=safe-actions-migrations
php artisan migrate
```

6. Confirm the installed Composer version is the advertised **stable tag**, not `dev-main`. Verify the sample native bulk action and explicit tenant query in [README](../README.md).

## Versioning rules

- Packagist derives package versions from real Git tags; **do not add a `version` field** to `composer.json`.
- Git tags are immutable. A README or Composer metadata change on `main` does **not** change any previously released tag. Publish a new patch tag, e.g. `v1.0.1`, to make the updated metadata available to consumers.
- Run all CI checks on the final release commit before tagging.
- No Packagist API keys are required in the GitHub repository; never commit credentials.

## If installation fails

Check `composer validate --strict`, the Packagist package page's version list, and compatibility with PHP, Filament and Laravel. For a deliberate VCS-only installation **before indexing**, the documented [VCS fallback](../README.md#installation) remains available.

**Official reference:** https://packagist.org/about
