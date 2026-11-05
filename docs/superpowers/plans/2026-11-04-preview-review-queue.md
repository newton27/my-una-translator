# Preview Review Queue Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generate the complete safe preview while isolating invalid strings for correction before Apply.

**Architecture:** Providers keep strict default behavior and offer an optional per-row preview mode. The engine persists safe rows and review flags atomically, and existing Studio controls expose and correct the flags.

**Tech Stack:** PHP 8.3, UNA database store, existing JavaScript, PHP/SQLite and Node behavioral tests.

**Spec:** `docs/superpowers/specs/2026-11-04-preview-review-queue-design.md`

## Global Constraints

- Protected-text validation remains unchanged; no unsafe translation is applied.
- No automatic Apply, core changes, service routing changes, release publication or destructive migration.
- Preserve installed search/editor behavior, provider settings, credentials and saved previews.

## Review Focus

- A bad middle item must not lose valid siblings or trigger another provider request.
- A blank result is a review item; HTTP/protocol/quota failures still pause generation.
- Mixed batches account for rejected work, respect the job ceiling and survive interruption conservatively.
- A stale correction or changed English source must not replace a newer preview.
- A forged Apply request must fail before any native language write while review flags exist.

### Task 1: Per-row validation and persisted review queue

**Files:** `classes/MyUnaTranslatorLibreProvider.php`, `classes/MyUnaTranslatorProvider.php`, `classes/MyUnaTranslatorEngine.php`, `classes/MyUnaTranslatorStore.php`, `install/sql/install.sql`, `tests/review_queue.php`.

**Interfaces:** `translate(array $sources,$target,$allowReview=false)` returns the existing rows; preview mode may return `{text:null,error:string,billed:int}` for an invalid source. The engine writes `needs_review` rows, finishes into `needs_review`, and guards Apply.

- [ ] Write and run failing behavioral tests for a bad middle result, blank result, malformed batch/HTTP pause, unchanged safe siblings, total spending, Apply blocking, correction and stale/source guards.
- [ ] Implement provider preview mode and engine persistence without extra model requests.
- [ ] Reuse the installed original-result column/editor and preserve its schema in fresh source installation. No live migration is required.
- [ ] Run PHP regression tests and commit.

### Task 2: Studio review controls

**Files:** `template/js/workflow.js`, `template/js/progress.js`, `template/js/translator.js`, `classes/MyUnaTranslatorStudioPage.php`, `tests/review_queue.cjs`.

**Interfaces:** The job exposes `counts.needs_review`; `needs_review` is a completed generation state with Apply disabled. The existing correction action sends expected result/state for stale protection.

- [ ] Write and run failing tests for review counts, generation completion, disabled Apply, editable flagged rows and correction preservation.
- [ ] Add visible Needs review counts/filter, completion guidance and guarded correction controls with cache-version changes.
- [ ] Run the complete suite and syntax checks; obtain one fresh read-only code review.

### Task 3: Deployment and acceptance

- [ ] Back up exact live files and job #112 before mutation; verify expected source hashes.
- [ ] Install only changed module files with original ownership/modes and verify hashes/syntax.
- [ ] Verify the actual formerly failing batch produces safe siblings plus a review flag, saved entries remain unchanged, and server Apply is blocked.
- [ ] Resume #112 through Studio, verify it passes the previous stopping point, and save source/evidence in a draft PR.

## Execution ledger

- Approved behavior: user explicitly selected flagging individual bad strings while the remaining preview continues.
- Source baseline: preserve the installed search/editor files and percentage-boundary fix before adding the queue; current main omits those installed changes.
- Deployment is authorized by this request. No release/Marketplace publication is included.

- Task 1: complete — 13 PHP queue behavior checks pass; original malformed cases observed failing before implementation.
- Task 2: implementation complete — six new JSDOM checks and all 17 Node checks pass; fresh review in progress.
- Full suite: PHP checks, 45 terminology outputs, 17 Node tests, seven Python tests, syntax/whitespace checks pass.
- Ruling: retain historical 0.1.9→0.1.10 updater bytes and pin their exact integrity hashes; changing root runtime must not rewrite a historical update payload. Future native packaging remains separate.

- Final review: one Important DeepL empty-result case and one Cancel recovery case identified. Both reproduced RED, fixed in one pass, and full suite passes: 34 PASS-prefixed PHP checks plus 45 terminology outputs, 18 Node tests and seven Python tests.
- Ruling: Cancel recovery is treated as Important because an offline read must not leave the correction editor unusable. It now restores cached content without a server request.
- Review boundaries: live MySQL, ownership/cache and job acceptance are covered in Task 3; native publication remains outside scope.

- Deployment: eight exact module files installed with expected preflight hashes, PHP syntax checks, original modes and ownership; recovery checkpoint retained.
- Live provider acceptance: formerly failing three-source response now yields two valid previews and one review flag. All 1,240 prior staged entries and existing Albanian native strings compare unchanged to the checkpoint.
- Live Studio: job #112 passed the prior stop and reached 1,693 processed rows (1,691 safe previews, two flags, zero applied) while continuing generation.

- Live Studio review acceptance: four flagged rows are filterable with their original source and rejection reason; Correct opens an empty editor, Cancel restores the row without saving, and Apply remains disabled.
- Live server Apply guard: a direct Apply dispatch with review flags was rejected before writes; the entire saved job compared unchanged, and applied count stayed zero.
- Generation resumed after the review/control check.
