# Translator 0.1.9 native acceptance plan

> Execute the user's supplied acceptance scope inline. Keep the draft PR, reporting rollout and published artifacts unchanged.

**Goal:** Prepare and natively validate a genuine 0.1.9 installation upgrading to 0.1.12.

**Architecture:** Add a direct native updater, copying the 35 runtime files from the existing reviewed 0.1.12 checkpoint. Run the preview column migration only when absent, then always run the idempotent receipt-table migration. Preserve original updater directories and published ZIPs.

**Tech stack:** UNA 15 native Studio updater, PHP 8.2+, MySQL/MariaDB, Python standard-library package checks.

**Spec:** The user's November 2 acceptance request; existing integrated reporting design in `docs/superpowers/specs/2026-10-29-integrated-support-reporting-design.md`.

## Constraints and review focus

- Use a genuine 0.1.9 source/registration, never change `sys_modules.version` to create a baseline.
- Keep provider secrets, registered translation keys, job/item/language data and UNA localization intact.
- An interrupted migration must be retryable: an existing preview column must not skip the reporting-table migration.
- Include the current uninstall SQL and native Studio icon bytes.
- Update only the six newly introduced language keys; preserve existing customer edits.
- A package or harness pass does not constitute native Studio acceptance.
- Do not enable reporting, merge, tag, publish or replace existing certified assets.

## Tasks

- [x] Inspect current PRs, Tickets CI jobs, original 0.1.9 source tree, runtime differences and existing updater directories.
- [x] Correct the stale Tickets CI gate in Translator PR #5 and its validation record.
- [x] Add direct updater with exact complete reviewed runtime payload and two retry-safe migrations; verify package structure, payload equality and repeat-build checksum.
- [ ] On an authorized isolated UNA 15 fixture, verify the original released baseline ZIP and a full backup/rollback checkpoint before any mutation.
- [ ] Apply the exact updater through Studio; compare settings/key ciphertext, complete table rows, localization, module ID/enabled state, navigation and icons before/after.
- [ ] Exercise translations with controlled test data and reporting compatibility without submitting live reports; test uninstall on a second disposable snapshot and restore baseline data.
- [ ] Record actual test evidence, hashes and rollback location. Leave unavailable gates NOT TESTED and publication BLOCKED.

## Access status

Chrome tab for gayjax.life reached Studio, but the browser reports another extension UI is blocking automation. An SSH public-key attempt from the connected iMac was rejected. No test-site backup, fixture setup, update, uninstall or translation request has been executed in this task.
