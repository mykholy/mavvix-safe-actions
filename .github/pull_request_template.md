## Summary

What changed, and which problem does it solve?

## Security / affected behaviors

- [ ] Exact record selection and scope are preserved.
- [ ] Actor, guard, tenant, panel and per-record authorization cannot be bypassed.
- [ ] No new irreversible work occurs in the preview or inside database transaction callbacks.
- [ ] Unsupported selections fail closed rather than silently truncating.
- [ ] Changes are documentation-only; no runtime/security behavior changed. *(Check instead of the above only when true.)*

## Evidence

- [ ] Regression coverage added/updated for changed behavior.
- [ ] `composer validate --strict`
- [ ] `vendor/bin/phpunit`
- [ ] `vendor/bin/pint --test`
- [ ] Static analysis and relevant host/browser/MySQL/PostgreSQL CI jobs

Include actual test results or link to green GitHub Actions. Mark anything not run and explain why.

## Docs / compatibility

- [ ] README, changelog and support matrix updated where necessary.
- [ ] No unsupported security, accessibility or performance assurances introduced.
- [ ] Tagged releases were not force-moved.
