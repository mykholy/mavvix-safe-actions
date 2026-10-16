# Filament 5 selection compatibility — discovery log

Status: **PR-00 in progress. Not yet validated in a host Filament app.**

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
| Explicit key list with a host-supplied scoped Eloquent query | Headless inspector only | Package regression tests authored; CI pending |
| Filament manually checked rows | Not yet supported end-to-end | Host Livewire test needed |
| Filament current-page select-all | Not yet supported end-to-end | Host Livewire test needed |
| Filtered/select-all-matching | Not supported | Inverse selection adapter and integration tests needed |
| Selection with row selectability closure | Not yet supported end-to-end | Must re-evaluate closure/eligibility server-side |
| Cross-tenant selection | Inspector fails if host passes scoped query | Real panel-tenancy tests needed |

## Immediate engineering work

1. Create a Testbench + Filament host Livewire table that exposes the selected IDs through **public APIs**, without inspecting Livewire's private state.
2. Confirm what data modal hooks see **before** `authorizeIndividualRecords()` removes records.
3. Prove that the selected-key scope exactly equals the table's filters, search, global scopes and tenant boundary.
4. Add a regression suite for mismatched counts (828/842), nonselectable rows (101/18), missing keys, UUID keys and permission changes.
5. If exact target identity cannot be proven, disable that mode and display a clear unsupported-selection error.

No actionable `SafeBulkAction` should be released before these gates pass.
