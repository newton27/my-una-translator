# Translator and native acceptance implementation plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Produce a reconciled Translator candidate and exact-package native acceptance for Weather and Finances.

**Architecture:** Preserve frozen artifacts and use isolated candidates. Combine Translator's reviewed retry/reporting baseline with the current preview-review queue, then build new uniquely identified packages. Exercise actual native UNA installers in disposable fixtures.

**Tech Stack:** PHP, JavaScript, Python packaging, UNA 15, MariaDB.

**Spec:** User request of November 5, 2026; Translator PRs 7 and 9; Weather release verification; Finances PR 1.

## Global constraints

- No UNA core edits, forced modified-file updates, manual installed-version changes, or checksum rebaselining.
- Keep client reporting disabled; do not publish GitHub releases or Marketplace packages during reconciliation.
- Preserve historical updater bytes and earlier ZIPs. New bytes receive new filenames and SHA-256 values.
- Production settings, jobs, translations and payment records must remain intact.

## Review focus

- Review flags must prevent Apply on both server and UI while safe siblings continue generation.
- Retry countdown cancellation and saved accounting must survive the reconciliation.
- Existing preview baselines and manual corrections must survive native migration.
- Installed runtime and native update source payloads must have identical target bytes.
- Weather/Finances tests must use genuine predecessors, not manually relabeled installations.

### Task 1: Reconcile Translator source and packaging

**Files:** classes/MyUnaTranslatorEngine.php, MyUnaTranslatorStore.php, MyUnaTranslatorStudioPage.php; install/langs/en.xml; install/sql/install.sql; template/js/translator.js; package.json; package-lock.json; tests/run.sh; tests/server_languages.php; tests/test_update_package.py. Review cleanly merged provider/text/progress/workflow changes too. Update supported native payloads and migrations only after determining the final release version.

**Interfaces:** Consume PR 7 checkpoint d305b9708ecc3e9fef975ee9573e9093e8b4767e and PR 9 checkpoint 6d6d165f21f889352f15c02b6d3432a58bd963af. Produce one candidate retaining retry, editor, reporting-disabled and review-queue behavior.

- [ ] Establish an isolated branch and reproduce both baseline suites in a PHP runtime.
- [ ] Add integration regressions to tests/review_queue.php and tests/review_queue.cjs: flagged sibling plus safe siblings persists safely; direct Apply rejects flags; correction clears only the selected flag; cancellation during a retry countdown stops work; old baseline and manual provenance survive correction.
- [ ] Verify those combined-behavior assertions fail on the incomplete candidate.
- [ ] Resolve the 11 observed conflicts, retaining both behaviors rather than selecting either whole side. Preserve original_result and manually_edited schema requirements and the reporting-disabled default.
- [ ] Run bash tests/run.sh with all PHP, Node and Python suites from both branches included; run syntax and whitespace checks. Review historical updater hashes unchanged.
- [ ] Build new full/native packages, record source commit and hashes, and verify every source payload byte against the final runtime. Do not reuse prior native PASS claims.

### Task 2: Exact-package Weather and Finances native acceptance

**Files:** Weather's newer auto-updater kit and native acceptance receipt; Finances PR 1 candidate and docs/READINESS-0.1.1-2026-11-02.md.

**Interfaces:** Consume immutable downloaded packages, genuine predecessor source and database checkpoints. Produce explicit per-package fresh/update/lifecycle evidence.

- [ ] Inspect the November 5 Weather kit and compare its inner archives with the October 9 accepted identities; classify unchanged bytes versus new candidate bytes.
- [ ] Use an isolated UNA fixture within authorized test scope. Establish a genuine Finances 0.1.0 baseline with native tracked hashes matching; do not change the drifted setup-stuff installation to fake that baseline.
- [ ] Record baseline IDs, settings, data fingerprints, language values, icon and registration state, and backup/recovery checkpoint.
- [ ] Run each actual native Studio update and fresh installation. Verify target metadata, exact runtime bytes, data/configuration preservation, language additions, launcher icon, Downloaded Info popup and module behavior.
- [ ] Exercise disable/enable/uninstall and fixture recovery; verify UNA core and source financial records unchanged.
- [ ] Record each exact artifact hash, environment and observed result; keep untested Marketplace transport explicitly separate.

### Task 3: Listing completion

**Files:** Tickets and Stream Doctor listing drafts in UNA-release-reconciliation-2026-11-05.html.

**Interfaces:** Consume current candidate features and verified release evidence. Produce version-matched descriptions, requirements and media checklist.

- [ ] Review the attached copy. Confirm price, support destination and final release version before creating a new product record.
- [ ] Reconcile Tickets receipt bootstrap fix with distributable packages and repeat affected native/mail checks. Do not promise email delivery until verified.
- [ ] Verify Stream Doctor native OME control acceptance and final artwork; replace its development-build designation only after acceptance.
- [ ] Prepare actual accepted-interface screenshots, cover/icon and native product-information content; save listing details without claiming package publication or accepting Marketplace terms.

## Current evidence

Read-only merge-tree reports 11 conflicts. Weather historical 0.1.1 native lifecycle passed, but the November 5 kit has not yet been byte-compared. Finances native acceptance remains NOT TESTED due to predecessor drift. Neither Tickets nor Stream Doctor appears in the signed-in Marketplace Manage Products list observed November 5. SSH from the connected MacBook stopped at host-key verification; no host-key protection was bypassed.
