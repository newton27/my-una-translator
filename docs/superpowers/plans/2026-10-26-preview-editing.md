# Editable translation previews Implementation Plan

> Execute inline, with test-first verification.

**Goal:** Allow administrators to correct saved previews before Apply.
**Architecture:** Add an authenticated edit_item dispatch action under the existing advisory lock. Preserve the destination baseline; use a result hash for stale-preview rejection. Store manual provenance in a module-owned column. Render explicit row editing with draft protection.
**Tech Stack:** PHP 8.1+, UNA, MySQL/MariaDB, browser JavaScript, Node tests, SQLite PHP fixtures.
**Spec:** docs/superpowers/specs/2026-10-26-preview-editing-design.md

## Global Constraints
- No UNA core modifications.
- Save updates only staged job results, never Polyglot text.
- Preserve source placeholders, markup and existing destination baseline.
- Do not overwrite newer browser edits or later Polyglot changes.

## Review Focus
- Invalid/missing item and job IDs, cross-job requests.
- Stale browser edits and simultaneous apply.
- Validation failure retains unsaved draft.
- Applied/processing jobs remain immutable.
- Existing installed jobs and native updater compatibility.

### Task 1: Validated preview persistence
- [x] Write failing engine tests for save, manual flag, stale hashes, invalid placeholders, cross-job IDs and immutable states.
- [x] Implement Store lookup and edit_item under the existing lock; preserve before_value and source.
- [x] Verify updated preview and apply/rollback conflict handling.

### Task 2: Preview editor
- [x] Write failing UI tests for Edit/Save/Cancel, escaped values, draft protection and error retention.
- [x] Implement row editor and localized labels/styles; bump script cache token.
- [x] Run Node and PHP tests.

### Task 3: Native migration and delivery
- [x] Add fresh-install column and native updater; document feature and scope.
- [x] Run complete existing suite, lint and new tests; review diff.
- [x] Preserve source on a feature branch; live deployment remains blocked by automatic browser approval review, pending specific server authorization.
