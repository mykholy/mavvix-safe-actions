# Stable release playbook — Mavvix Safe Actions

**October 18, 2026 decision:** The owner explicitly authorized a narrowly scoped v1.0.0 stable SemVer release with an accurately disclosed **first-party (non-independent) assessment**, rather than waiting for an external auditor. The [first-party review](security-review-v1.0.0.md) records known residual risks; [issue #6](https://github.com/mykholy/mavvix-safe-actions/issues/6) and [issue #5](https://github.com/mykholy/mavvix-safe-actions/issues/5) remain open for external and human verification. No human or third-party signature is claimed.

The v1.0.0 publishing workflow creates an annotated tag and a GitHub release only when the same final `main` commit has green Tests, Static analysis, Dependency security audit, Fresh host installation and Browser end-to-end runs. It must refuse failures, incomplete checks and a moved `main` branch. A passed release workflow is a reproducible release gate, **not** a security certification.

## 1. Freeze the release candidate

- Create a release candidate commit from `main` with no unmerged code PRs.
- Record commit SHA, PHP/Filament/Laravel versions, GitHub Actions URLs and dependency audit result.
- Confirm `composer validate --strict`, `phpunit`, `pint --test` and Larastan passed on the **same SHA**.
- Install the package via a clean Laravel 12 and Laravel 13 host; publish migrations, migrate, inspect the modal and run guarded execution smoke tests.
- Test upgrade from a pre-alpha schema: publish current migrations; historical tickets lacking state fingerprints **must be rejected** and re-previewed.

## 2. Security review by someone independent of the implementation

Provide the reviewer `docs/security-model.md`, `docs/selection-compatibility.md`, and code in `src/Selection/`, `src/Preflight/`, `src/Execution/`, `src/Filament/`.

- Try forged Livewire selection keys, inverse selection, unauthorized rows, tampered ticket IDs, actor/panel/tenant changes and permission revocation.
- Validate the query fingerprint, SQL scope-shape checks (including global scopes), ticket replay/expiry, locking and stale selected-row value detection.
- Check what model observers, mutators, listeners, transaction after-commit hooks and external I/O may do. The package **does not** guarantee rollback for external effects.
- Verify where the host must supply its own tenant scope, contextual identity and pure authorization callbacks.
- Run an actual multi-connection lock-timeout/deadlock test with documented failure behavior and no inadvertent partial commit.
- Capture findings, severity, code patches, regression test evidence, reviewer identity and sign-off in a private or public review record as appropriate. **The maintainer cannot self-certify an independent review.**

## 3. Accessibility review

Automated Chromium/axe checks cover EN desktop and AR dark/mobile. The browser test also probes modal keyboard focus and Escape cancellation. A **human** reviewer must still verify keyboard-only operation, focus trapping and restoration, live region announcements and error readability with a real screen reader (NVDA/JAWS/VoiceOver), in both languages and at narrow viewport widths.

## 4. Concurrency and workload qualification

- Use production-representative MySQL/PostgreSQL settings, indexes, realistic host observers and background workers.
- Repeat 1/100/500-row operations with multiple concurrent admins and distinct/shared tickets.
- Capture P50/P95/P99, lock wait, deadlock and timeout counts, query load, memory, hardware specification and workload configuration.
- Never increase `max_explicit_selection` merely because a single-process benchmark is fast.
- The current 500-record ceiling and one-connection/database-only restriction remain deliberate.

## 5. Release only after the above gates pass

1. Agree on the compatibility table, migration path, security findings and documentation of unsupported behavior.
2. Tag a reviewed release candidate, perform an independent clean-room install, and record any fixes in new commits and rerun all gates.
3. Publish an annotated stable tag only once the final candidate is green and the maintainer has authorized publication. v1.0.0 has the explicit owner-directed exception disclosed above; subsequent releases should aim to close external review gates.
4. Submit to Packagist, verify exact version installation and installation metadata, and prepare a Filament Plugins listing with authentic screenshots and no unproven performance/safety claims.
5. Add a clear changelog entry, upgrade instructions, compatibility support policy and vulnerability reporting process.

Do **not** silently replace missing external reviews with green CI. The user-authorized v1.0.0 exception above is explicitly documented and signed as **first-party only**; that exception does not close independent security, accessibility or production load assurance issues, which remain recommended before high-stakes production deployments.
