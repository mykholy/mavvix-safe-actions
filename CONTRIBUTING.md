# Contributing

The project favors clear, narrowly scoped changes over abstractions that cannot be justified by a production safety requirement.

## Development

Use PHP 8.3+ with the Laravel/Filament versions in the GitHub Actions matrix.

~~~bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
~~~

Keep one behavior change per pull request where practical. Add a regression test for every reported scope or authorization bug. Use Laravel's conventions, strict types and descriptive domain names. Avoid suppressing static-analysis issues without explanation.

## Safety review checklist

- Is the target record set reconstructed on the server?
- Are the exact IDs checked against the filtered, tenant-scoped query?
- Does the operation re-check eligibility **inside** its transaction?
- Do missing or unauthorized rows stop the **entire** operation?
- Is the ticket actor/action/tenant-bound, short-lived and single-use?
- Do exceptions roll back the ticket and all database mutations?
- Could a closure cause external, cross-connection or irreversible side effects?
- Are any selection modes unsupported? Document them and fail closed.
- Do both Laravel CI matrix jobs pass?

Do not change the advertised version matrix or tag stable without working integration tests for the claim. Use a Draft PR for changes that still have unverified invariants.

For security reports, follow [SECURITY.md](SECURITY.md).
