# Filament 5 selection compatibility — discovery log

Status: **Pre-alpha. A bounded Filament selection adapter, query-bound preflight and guarded synchronous execution are tested in Laravel 12/13 CI. Additional driver and security testing is required.**

## Verified public APIs in Filament 5 documentation

- `Filament\Actions\BulkAction::make(...)` can receive selected Eloquent records through an action callback.
- `->authorizeIndividualRecords('update')` removes unauthorized rows from the injected records; this filtering behavior makes it insufficient to prove the **original** selection matched the intended set.
- `Table::checkIfRecordIsSelectableUsing(...)` controls checkbox selectability. Treat it as a UI hint rather than the sole authorization boundary.
- `Table::selectCurrentPageOnly()` prevents convenient all-page selection (not a security boundary).
- `BulkAction::chunkSelectedRecords(250)` and `fetchSelectedRecords(false)` change the collection shape.
- Filament's test helpers include `selectTableRecords()` and `TestAction::make(...)->table()->bulk()`.

Reference: https://filamentphp.com/docs/5.x/tables/actions and https://filamentphp.com/docs/5.x/testing/testing-actions

## Important known incident

GitHub issue filamentphp/filament#20050 described records excluded by `checkIfRecordIsSelectableUsing()` entering all-matching bulk execution (101 processed including 18 non-selectable); it was closed by upstream in June 2026. We must independently reproduce the **class** of scope/eligibility discrepancy against each supported Filament version, not assume an issue remains unfixed.

## Support matrix

| Mode | Initial support claim | Test status |
| --- | --- | --- |
| Explicit key list with a host-supplied scoped Eloquent query | Headless inspector only | Package tests pass in Laravel 12/13 CI |
| Filament manually checked rows | Inspection only | Livewire adapter validates filtered query and per-row checks |
| Filament current-page select-all | Inspection only | Rejects explicit IDs outside the current displayed page; inverse selection refused |
| Filtered/select-all-matching | Inspection only, bounded to 500 matching rows | Inverse mode inspects filtered query; larger selections fail closed |
| Selection with row selectability closure | Inspection only | Re-evaluates closure/eligibility server-side |
| Cross-tenant selection | Inspector fails if host passes scoped query | Real panel-tenancy tests needed |

## Immediate engineering work

1. Create a Testbench + Filament host Livewire table that exposes the selected IDs through **public APIs**, without inspecting Livewire's private state.
2. Confirm what data modal hooks see **before** `authorizeIndividualRecords()` removes records.
3. Prove that the selected-key scope exactly equals the table's filters, search, global scopes and tenant boundary.
4. Extend the existing inspector regression cases (828/842 and 101/18) with Filament selectability and filtered all-matching cases, UUID keys and permission changes.
5. If exact target identity cannot be proven, disable that mode and display a clear unsupported-selection error.

A preview-and-confirmation action is available on the development branch only. Do not publish a stable release until the remaining selection, browser, concurrency and security gates pass.
