# Integrated Support Reporting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Let a Translator administrator review an error report, submit it to My UNA World Tickets, and receive a private ticket reference.

**Architecture:** A versioned allowlisted PHP contract is bundled in both modules. Translator builds diagnostics server-side and sends a reviewed report over a fixed HTTPS transport. Tickets validates anonymous intake, resolves products, and atomically deduplicates ticket creation before attempting one receipt.

**Tech Stack:** Existing UNA 15 PHP 8.2+, MariaDB/InnoDB, vanilla JavaScript, existing Tickets private conversations.

**Spec:** ../specs/2026-10-29-integrated-support-reporting-design.md (approved 2026-10-29).

## Global Constraints
- No UNA core changes; no automatic telemetry or reporting.
- Fixed HTTPS endpoint; redirects and certificate bypass forbidden.
- 32 KiB JSON limit, strict schema, diagnostics built from an allowlist; no keys, raw source, translations, traces or member data.
- Ticket/email identity remains unverified until existing Tickets access verification; never trust client member IDs.
- UUID + email-hash idempotency, changed payload 409, exact replay returns saved reference without another receipt.
- Five new reports per ten minutes per email, twenty per source server IP, separate intake namespace.
- Deploy central intake before client enablement; verify installed differences; include module-owned install/update SQL.

## Review Focus
- Lost acceptance response: same immutable report/UUID must return one saved ticket.
- Shared hosting: IP limit must be separate from per-email quota and return an actionable 429.
- Unknown error text: diagnostic output must never copy raw exception text containing secrets.
- Email failure after commit: ticket remains accepted and a duplicate retry sends no additional receipt.
- Disabled central product or module: return an explicit failure and no orphan ledger/ticket.

### Task 1: Contract and ticket intake
**Files:** Both modules `classes/support/ReportContract.php`; Tickets `classes/MyUnaTicketsIntake.php`, `classes/MyUnaTicketsIntakeRepository.php`, `classes/MyUnaTicketsNativeAdapter.php`, `report.php`; modify `classes/MyUnaTicketsTicketRepository.php` only to expose caller-owned transaction creation; `tests/report_intake.php`.
**Interfaces:** `MyUnaSupportV1\ReportContract::validate(array): array`, `::encode(array): string`; `MyUnaTicketsIntake::submit(array,string): array`; repository `accept(array,string): array`; result includes ticket_id, receipt state, replay flag, fixed access URL. Structured diagnostics are serialized into private environment details.
- [ ] Write failing behavioral tests for strict fields, UTF-8/lengths, private ticket creation, product resolution, replay/409, limits, rollback, and receipt failure/replay.
- [ ] Run tests and observe missing implementation failures.
- [ ] Implement validation, fixed error-code catalog, native SQL adapter and atomic creation/idempotency ledger with bounded advisory locking.
- [ ] Add POST JSON endpoint with bounded body and sanitized errors; reject inactive Tickets/product.
- [ ] Run standalone tests against SQLite fixture and a disposable MariaDB database, including concurrent identical requests.
- [ ] Commit central intake task.

### Task 2: Translator diagnostics, receipts and transport
**Files:** `classes/MyUnaTranslatorSupport.php`, `classes/support/ReportClient.php`, `classes/MyUnaTranslatorStudioPage.php`, `tests/report_support.php`.
**Interfaces:** `MyUnaTranslatorSupport::dispatch(string,array): array` for report_prepare and report_send. Prepare stores an immutable sanitized incident and UUID in the existing administrator session; send uses the same payload on retry, validates contact text, and persists only incident digest/ticket reference. `ReportClient::send(array): array` uses the fixed endpoint and bounded HTTPS transport.
- [ ] Write failing tests for no raw error/source/key leakage, stable retry identity, admin/CSRF boundary, and accepted response vs ambiguous delivery.
- [ ] Run red tests, implement allowlisted diagnostics from Store, safe error catalog and immutable submitted drafts.
- [ ] Implement local receipt persistence, fixed URL transport, no redirects, bounded timeouts/body, and no retry loop.
- [ ] Run tests and commit.

### Task 3: Integrated review-and-send UI
**Files:** `template/js/reporting.js`, `template/css/reporting.css`, `classes/MyUnaTranslatorStudioPage.php`, `template/js/translator.js`, `tests/reporting.cjs`.
**Interfaces:** A separate reporting dialog consumes report_prepare, explicitly sends report_send, and renders safe success/failure text. The existing status code supplies the selected local job ID only, never diagnostics from browser state.
- [ ] Write failing UI behavior tests for review before send, duplicate-click guard, immutable ambiguous retry, ticket acknowledgment and fallback link.
- [ ] Implement Report this problem beside errors and Contact support; use textContent for all returned content and keep translation controls independent.
- [ ] Verify keyboard dialog behavior and narrow layout in the authenticated browser; commit.

### Task 4: Native install/update and source reconciliation
**Files:** Both `install/config.php`, install/uninstall SQL; native `updates/update_<baseline>_<target>/` config, source and SQL; `docs/REPORTING.md`.
- [ ] Verify actual installed versions and file diffs before selecting new candidate identities.
- [ ] Add Tickets intake ledger/products mapping and Translator receipt tables through module-owned SQL; single-line SQL statements for UNA's loader.
- [ ] Add version-matched native updates with required version_from/version_to metadata; preserve frozen release files.
- [ ] Test install and update schema equivalence/data preservation on disposable MariaDB fixtures; commit.

### Task 5: End-to-end validation and delivery
- [ ] Run every existing PHP/Node/Python suite plus new reporting suites, PHP lint and native SQL tests.
- [ ] Exercise test-site admin report preview, explicit submit, one private ticket, staff details, saved ticket reference, verified access and reply.
- [ ] Test production endpoint safely with clearly marked test data, then enable Translator integration after central validation.
- [ ] Request fresh whole-branch security/code review; resolve important findings through RED→GREEN tests.
- [ ] Save evidence, publish reviewable feature branches/draft PRs; no release/tag changes without separate approval.

## Execution record
Implement inline in the current session under the user's approval to build the design. No additional planning approval is needed. Record deviations and acceptance gaps in the ledger; never claim deployment before the full path is verified.
