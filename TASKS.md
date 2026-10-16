# Engineering checklist

## PR-00 — Foundation / selection discovery

- [x] Initialize Composer package and service provider
- [x] Add bounded explicit-selection core and regression fixtures
- [x] Add CI test matrix configuration
- [x] Document limitations and public Filament APIs
- [x] Install dependencies and obtain passing CI evidence (Laravel 12 / PHP 8.3, Laravel 13 / PHP 8.4)
- [x] Run the explicit-record Filament v5 Livewire selection harness in both CI environments
- [ ] Confirm filtered/all-matching selection behavior experimentally
- [ ] Obtain an independently reviewable selection adapter contract

## PR-01 onward

- [ ] Persistent preflight, actor/tenant binding and expiry
- [ ] Action modal with read-only preview
- [ ] Atomic bounded mutation and revalidation
- [ ] Concurrency, duplicate-submission and failure-mode tests
- [ ] Driver test matrix, benchmarks, translation and release documentation

**Release status: pre-alpha.** Do not tag v1.0.0 until all gates in `IMPLEMENTATION_PLAN.md` are satisfied.
