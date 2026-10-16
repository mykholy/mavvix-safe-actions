# Development Instructions — Mavvix Safe Actions

This repository is for a high-trust, open-source Filament plugin. Read `IMPLEMENTATION_PLAN.md` before modifying code. The plan is a design target; validate public Filament APIs against the installed version before writing the integration.

## Operating rules

1. Work in small, reviewable increments in PR order PR-00 to PR-06. Do not declare the package production-ready or release a stable tag until all gates pass.
2. The most important property is **no unintended mutations**. When the selected target set cannot be proven, fail closed. An apparently successful action that reaches an unauthorized record is a critical defect.
3. Implement a real host-app integration test before committing to a Filament selection adapter. Never rely on DOM hacks, undocumented Livewire state or reflection unless specifically justified and isolated behind tests; prefer to reject unsupported modes.
4. Preserve Laravel authorization, global scopes, soft-delete behavior, Filament tenant boundaries and authenticated guard/panel context. Never bypass scoping to make counts match.
5. A preflight is pure/read-only; it must never call mutation callbacks, model save methods, events with side effects or external services.
6. Persist server-side selected target identities and bind confirmation to the actor, guard, panel, tenant, action, snapshot and expiry. Reject a stale, replayed or mismatched confirmation.
7. Document the transaction guarantee exactly. Never advertise atomicity, arbitrary rollback or safety for external API calls unless the implementation proves it.
8. Keep code idiomatic, concise, typed and consistent with modern Laravel conventions. Apply SOLID where it clarifies boundaries, not by adding unnecessary interfaces, factories, traits or layers.
9. Comments explain non-obvious invariants. Do not write generic comments paraphrasing code, decorative banners, over-engineered naming, or placeholder classes.
10. Write tests for every security claim and regression. Failing tests first when practical. No skipping, muting or weakening assertions to satisfy CI.
11. Run formatting, static analysis, Composer validation and the relevant tests for every meaningful change. If something cannot run, report exactly what was not tested.
12. Keep changelog, README, examples and migration instructions synchronized with behavior. No fabricated benchmarks, screenshots, results, contributor identities or human-authorship claims.

## Required first deliverable (PR-00)

- Scaffold a minimal Filament 5 host app and local Composer path package.
- Verify action extension points and explicit/current-page/filtered/all-matching selection behavior with automated Livewire tests.
- Reproduce the 842/828 and 101/18 mismatch classes; record expected and actual behavior.
- Write `docs/selection-compatibility.md` explaining validated supported modes and limitations.
- Show exact commands and their output summaries. If a mode cannot be implemented with reliable public APIs, document it as unsupported for v1.

## Reporting format for each PR

- Behavior added/changed and why it matters.
- Tests introduced or modified, including critical security cases.
- Commands run and observed results; no claimed green checks without execution.
- Tradeoffs, unsupported cases and next PR.

**Do not optimize to hide tool/AI assistance. Optimize for maintainable, accurate, reviewable code and do not misrepresent provenance.**
