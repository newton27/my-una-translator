# Translation Memory Studio Administrator Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a six-card, administrator-only Translation Memory review dashboard in My UNA World Studio without exposing admin access through the customer translation API or disturbing the Key Issuer.

**Architecture:** A new UNA Studio PHP page delegates strictly validated requests to a separate, peer-UID-checked Unix-socket administration service. That service reads Translation Memory SQLite tables and uses the existing atomic review path to approve or reject only eligible pending corrections; the customer `127.0.0.1:5051` API and key-manager socket remain unchanged.

**Tech Stack:** UNA 15 Studio, PHP 8.3, vanilla JavaScript/CSS, Python 3.12, stdlib `socket`/`sqlite3`/`unittest`, systemd on Ubuntu 24.04.

**Spec:** `docs/superpowers/specs/2026-10-14-translation-memory-studio-admin-design.md`

## Global Constraints

- Existing route: `/studio/libretranslate_keys.php`; new route: `/studio/libretranslate_memory.php`.
- Existing socket: `/run/my-una-translator/key-manager.sock` MUST stay unchanged; new dedicated socket: `/run/my-una-translator/translation-memory-admin.sock`.
- Existing customer API: `127.0.0.1:5051`; do not add admin actions there or expose a public admin TCP endpoint.
- Require UNA logged-in administrator, session CSRF for POST actions, authenticated session actor, and exact `SO_PEERCRED` caller UID.
- New private socket file mode `0660`, parent runtime directory `0750`; no web-accessible secret or new customer API credential.
- Local production source paths are `/opt/my-una-translator/services/translation_memory/` and `/home/myunaworld/public_html/studio/`; these are **not automatically the same checkout** as the module repository containing this plan. Identify authoritative deploy source and back it up before changing anything.
- Python service account on Meetsee: `libretranslate-manager`; current key-manager group `myunaworld`. Confirm PHP worker's actual UID and virtual-host process isolation before starting the new socket.
- Require `NoNewPrivileges=yes`, `ProtectSystem=strict`, `ProtectHome=yes`, `PrivateTmp=yes` in the **effective** systemd unit.
- Separate admin operations: `summary`, `pending`, `audit`, `review`. Customer bearer keys never confer admin rights.
- Consent stays opt-in: only explicitly consented **approved** corrections are eligible for reuse; customer-side module integration and cross-site rollout are out of scope.
- New dashboards show actual DB-derived values, not demo statistics; escape every contributed string; requests and responses bounded.
- Production activation, automatic startup, navigation exposure, publishing GitHub/Marketplace releases require separate verification and user approval.

## Review Focus

1. **Rapid repeated approval clicks:** second click must fail without changing decision or duplicating an audit event. Task 2 test `test_repeat_review_does_not_rewrite_status`.
2. **Unknown malicious keys or extra JSON fields:** admin socket must fail closed without revealing state. Task 3 test `test_unknown_field_and_customer_bearer_rejected`.
3. **Very long search/filter or negative pagination:** bounded query inputs must reject unsafe values and never scan unbounded rows. Tasks 1 and 3 tests `test_page_input_limits` and `test_bad_pagination_rejected`.
4. **HTML markup/script in translation text:** must render as plain text and never execute. Task 5 test `test_translation_markup_is_text`.
5. **IPC temporarily unavailable mid-review:** browser must display a recoverable error and prevent falsely marking a correction approved. Task 4 test `test_unavailable_socket_returns_generic_error` and Task 5 test `test_review_failure_preserves_pending_state`.

---

### Task 1: Baseline Inspection and Read-only Memory Metrics

**Files:**
- Inspect: `/opt/my-una-translator/services/translation_memory/storage.py`, `admin_server.py` if present, and current SQLite schema.
- Modify: `/opt/my-una-translator/services/translation_memory/storage.py`.
- Test: `/opt/my-una-translator/tests/translation_memory/test_admin_queries.py`.

**Interfaces:**
- Consumes: current `storage.pending(limit)` and SQLite `corrections`, `audit_log`, `usage_log`.
- Produces: `storage.summary() -> dict` with exactly `pending`, `approved`, `rejected`, `sites`, `reuse`; `storage.pending_page(limit: int, offset: int, query: str) -> dict` with `items`, `total`; `storage.audit_page(limit: int, offset: int) -> dict` with `items`, `total`. No secrets included.

- [ ] **Step 1: Inspect storage signatures/schema and establish a disposable SQLite fixture.** Check exact existing database path constant and row shapes first; use disposable DB only, not production records.
- [ ] **Step 2: Write failing `unittest` cases:** `test_summary_counts_approved_consented_only`, `test_pending_page_filters_and_orders`, `test_audit_page_no_secret_columns`, `test_page_input_limits` (noninteger/negative/oversized offset, limit > 50, query > 200 chars), `test_empty_database_counts_zero`.
- [ ] **Step 3: Run** `cd /opt/my-una-translator && .venv/bin/python -m unittest discover -s tests/translation_memory -p 'test_admin_queries.py' -v`; expected failures for missing APIs, not import/setup errors.
- [ ] **Step 4: Implement only named storage APIs.** Clamp/validate page size to 1–50 and bounded offset/search; parameterize SQLite queries, return newest-first stable ordering; count only consented contributor sites and approved usage entries.
- [ ] **Step 5: Run the targeted command again, then existing memory storage regression tests; expected all PASS. Commit or checkpoint the tests and storage change in the confirmed authoritative service source, not the UNA module ZIP.**

### Task 2: Atomic, Consent-Aware Approval Conflict Handling

**Files:**
- Modify: `/opt/my-una-translator/services/translation_memory/storage.py`.
- Test: `/opt/my-una-translator/tests/translation_memory/test_review_guards.py`.

**Interfaces:**
- Consumes: current `storage.review(correction_id, approved, administrator)`.
- Produces: same signature and response contract (inspect existing implementation first), but only pending consented rows can be approved, conflicting exact-language/source/context keys cannot both become approved, and each valid state change creates exactly one audit event.

- [ ] **Step 1: Write failing isolated-DB tests:** `test_missing_consent_never_approved`, `test_repeat_review_does_not_rewrite_status`, `test_exact_conflicting_approval_rejected`, `test_rejected_review_audited_once`, `test_review_failure_rolls_back_audit_and_status`, `test_approved_exact_lookup_still_works`.
- [ ] **Step 2: Run** `cd /opt/my-una-translator && .venv/bin/python -m unittest discover -s tests/translation_memory -p 'test_review_guards.py' -v`; verify expected behavioral failures.
- [ ] **Step 3: Within a single SQLite write transaction**, validate pending status/consent, atomically reject duplicate approved exact-match conflicts, and write status/reviewer/time/audit. Prefer a tested partial unique index for approved consented `(source_language,target_language,context,source_text)` after checking the current DB for conflicting historical rows; never auto-delete or silently rewrite conflicts.
- [ ] **Step 4: Rerun review tests, query tests and existing lookup/submission tests; expected PASS; ensure any historic conflicts are reported for manual resolution, not modified.**
- [ ] **Step 5: Commit/checkpoint the guarded review change and tests after passing them.**

### Task 3: Dedicated Restricted Translation Memory Admin Socket

**Files:**
- Create: `/opt/my-una-translator/services/translation_memory/admin_server.py`.
- Test: `/opt/my-una-translator/tests/translation_memory/test_admin_socket.py`.
- Stage unit: `/etc/systemd/system/my-una-translator-memory-admin.service` (do not enable yet).

**Interfaces:**
- Consumes: Task 1 `summary`, `pending_page`, `audit_page`; Task 2 `review`.
- Produces: newline-delimited JSON request/response protocol with only `operation` and approved fields; `handle_request(raw: bytes) -> dict`, `peer_uid(conn) -> int`, `serve_client(conn, allowed_uid: int) -> None`. `review` accepts `correction_id: int`, `approved: bool`, `actor: str`; `summary` has no fields; `pending` permits bounded `limit`, `offset`, `query`; `audit` permits bounded `limit`, `offset`.

- [ ] **Step 1: Write failing tests:** `test_unknown_field_and_customer_bearer_rejected`, `test_oversized_and_malformed_request_rejected`, `test_wrong_peer_uid_denied`, `test_bad_pagination_rejected`, `test_approved_is_boolean_not_string`, `test_newline_protocol_success`, `test_admin_socket_has_no_http_listener`.
- [ ] **Step 2: Run** `cd /opt/my-una-translator && .venv/bin/python -m unittest discover -s tests/translation_memory -p 'test_admin_socket.py' -v`; expected fail on missing implementation.
- [ ] **Step 3: Implement a single-request-per-connection, bounded, timed socket listener and strict operation-specific field whitelists.** Match peer UID via `SO_PEERCRED` before parsing or touching DB. Use socket path above, `0660`, `0750`, generic internal failures; never reuse the key-manager server or publish the socket to HTTP.
- [ ] **Step 4: Run socket unit tests and all memory tests; expected PASS. Before unit activation verify site PHP process effective UID, isolation from other vhosts, target runtime directory ownership, effective sandbox settings and absence of nonlocal admin listeners. Stop rollout if UID separation fails.**
- [ ] **Step 5: Commit/checkpoint socket and tests; stage unit file and verify with `systemd-analyze verify` and `systemctl show -p NoNewPrivileges -p User -p Group -p BindReadOnlyPaths -p ReadWritePaths` before a local-only smoke test.**

### Task 4: Native UNA Studio PHP Controller and IPC Client

**Files:**
- Create: `/home/myunaworld/public_html/studio/libretranslate_memory.php`, `studio/libretranslate-memory/{Client.php,Controller.php}`.
- Test: a staging-only `tests/libretranslate-memory/controller.php`, `tests/libretranslate-memory/client.php`.

**Interfaces:**
- Produces: `MyUnaMemoryClient::request(array $payload): array` and `MyUnaMemoryController::dispatch(array $post, string $method, bool $admin, string $expected, string $actor): array`.
- Consumes: Task 3 JSON-line socket protocol. Front-end actions are `memory_summary`, `memory_pending`, `memory_audit`, `memory_review`; controller maps these to `summary`, `pending`, `audit`, `review`.

- [ ] **Step 1: Add failing PHP tests:** `test_requires_admin`, `test_non_post_rejected`, `test_missing_or_bad_csrf`, `test_actor_comes_from_authenticated_session`, `test_unknown_action_rejected`, `test_unavailable_socket_returns_generic_error`, `test_request_response_limits`.
- [ ] **Step 2: Run staging PHP test scripts** with `php -d memory_limit=1024M tests/libretranslate-memory/controller.php` and `.../client.php`; expected failure because classes do not exist.
- [ ] **Step 3: Implement controller, IPC client and Studio entrypoint**, following `libretranslate_keys.php` admin/auth/session-CSRF/no-store semantics; forbid request-supplied actor, fail closed on socket problems, cap request/response bytes and enforce timeouts. Keep key issuer `Client.php`/`Controller.php` untouched.
- [ ] **Step 4: Run PHP tests and lint the new files:** `php -l studio/libretranslate_memory.php` and lint each new PHP class; expected no syntax errors and tests PASS.
- [ ] **Step 5: Commit/checkpoint only new site files in the confirmed authoritative source; do not wire navigation or expose the page in production until deployment approval.**

### Task 5: Graphical Dashboard, Review Table and Audit Views

**Files:**
- Create: `/home/myunaworld/public_html/studio/libretranslate-memory/{Page.php,memory.css,memory.js}`.
- Test: staging JS tests `tests/libretranslate-memory/dashboard.test.cjs` and Studio browser smoke test.

**Interfaces:**
- Consumes: Task 4 POST route and action names; Task 1 cards/rows shape.
- Produces: `MyUnaMemoryPage::__construct(string $token)`, `getPageMenu(...)`, `getPageCode(...)`; client JS refreshes six cards and paginated pending/audit views, renders translation text via `textContent` and sends explicit CSRF-protected reviews.

- [ ] **Step 1: Write failing JS/browser checks:** `test_six_cards_real_values`, `test_translation_markup_is_text`, `test_review_failure_preserves_pending_state`, `test_pagination_and_empty_state`, `test_approve_reject_requires_confirmation`, `test_keyboard_focus_and_mobile_layout`.
- [ ] **Step 2: Run** `node --test tests/libretranslate-memory/dashboard.test.cjs`; expected initial failures for unimplemented UI behavior.
- [ ] **Step 3: Implement native Studio page with exactly six approved metrics/cards, responsive CSS, details modal, bounded search and pagination, explicit review confirmation, audit view, loading/errors/empty states.** Never insert contributed translation HTML or hard-coded metric counts.
- [ ] **Step 4: Run JS tests, PHP lint, and a visual browser review on desktop/mobile; expected PASS with six correctly labeled cards and functional navigation/controls.**
- [ ] **Step 5: Commit/checkpoint UI files and tests. Add the single `Translation Memory` navigation item to existing `studio/libretranslate-keys/Page.php` only after isolated UI tests pass.**

### Task 6: Nonproduction Integration and Security Regression

**Files:**
- Test: `/opt/my-una-translator/tests/translation_memory/test_integration.py`, plus staging PHP/JS tests from Tasks 4–5.
- Stage: new administration unit, site page and assets on an isolated test copy (do not overwrite live first).

**Interfaces:**
- Consumes: Tasks 1–5.
- Produces: pass/fail evidence with command output, browser screenshot notes and unit/socket configuration.

- [ ] **Step 1: Add isolated end-to-end failing tests:** real peer-UID socket request using temporary SQLite DB; unauthorized peer rejection; browser session admin/CSRF; pending→approved→audited lookup; duplicate approval rejection; rejected row unaffected by subsequent clicks.
- [ ] **Step 2: Run isolated suite and observe failures for missing integration; apply only the smallest integration fixes; rerun until PASS.**
- [ ] **Step 3: Verify effective sandbox configuration and `SO_PEERCRED` with the actual PHP worker UID; fail rollout if access is possible from another virtual host or customer bearer token.**
- [ ] **Step 4: Confirm `ss -ltn` shows no new public admin listener, `/health` and protected customer `/lookup` still respond as expected, and existing key manager socket still operates. Test key-manager create/rotate/revoke only with disposable keys, never real customers.**
- [ ] **Step 5: Capture test evidence and a deployment/rollback checklist; checkpoint staged integration.**

### Task 7: Controlled Production Deployment and Rollback Evidence

**Files:**
- Deploy only approved staged files to `/home/myunaworld/public_html/studio/`, `/opt/my-una-translator/services/translation_memory/`, and the new isolated unit; existing key-manager source/socket unchanged.
- Documentation: `docs/translation-memory-studio-deployment.md` in authoritative source checkout.

**Interfaces:** No new protocol; uses Tasks 1–6 artifacts unchanged.

- [ ] **Step 1: Obtain explicit deployment approval after presenting nonproduction test evidence, exact changed file list, backup paths, UID/security checks and rollback command sequence.**
- [ ] **Step 2: Timestamped backup every to-be-modified file and DB/schema where needed; preserve ownership/modes; record SHA-256 hashes. Refuse deployment on unexpected source drift.**
- [ ] **Step 3: Deploy new admin unit and site files; restart only the new admin service; keep existing customer Memory service/key manager untouched. Recheck socket owner/mode, effective `NoNewPrivileges=yes` and no public listener.**
- [ ] **Step 4: Run Studio admin live smoke check: six genuine cards, filtering/pagination, approve/reject on specifically approved test corrections, audit entry, empty/error states, and links to/from Key Issuer. Confirm existing key management and customer API health again.**
- [ ] **Step 5: After user approves exposure/boot persistence, enable only the new admin unit and show the navigation link. Record verification evidence and final source commits; do not publish a GitHub or Marketplace release without separate approval.**
- [ ] **Step 6: Document and smoke-test rollback:** restore backups and file metadata, remove menu link, stop/disable only new admin unit, restore DB schema backup only when required and after carefully preserving newer legitimate correction records; verify original key manager and customer API remain functional.

## Handoff

The approved design and this plan are documentation on a dedicated branch of `newton27/my-una-translator`; that repository is not proven to contain the authoritative production admin service/site source. During execution, first identify proper source control for `/opt/my-una-translator` and the Studio PHP page. Never slip new administration server code into the UNA module release ZIP, and never infer that committing this plan deploys anything.
