# My UNA World Translation Memory — Studio Administration Design
Date: 2026-10-14
Status: Proposed for user review
Owner: My UNA World
Scope: Administrator interface and private administration transport only

## Purpose and success criteria
Add a native-looking, administrator-only Translation Memory dashboard to the existing My UNA World Studio, alongside (not replacing) the LibreTranslate API Key Issuer at `/studio/libretranslate_keys.php`. An administrator can see truthful system metrics, review consented translation corrections, approve/reject individual submissions, and inspect the resulting audit trail. All six approved graphical cards render correctly in desktop and mobile layouts.

A successful deployment leaves existing customer key issuance/rotation/revocation and the localhost-only customer Translation Memory API unchanged. Customer API keys never authorize administrator operations. The dashboard must not silently share translations or change the My UNA Translator module's translation behavior.

## Verified current architecture
- The existing Studio page `/studio/libretranslate_keys.php` uses the native UNA Studio template, an authentication requirement, an `isAdmin()` check, session CSRF token, controller, client, and asset files under `studio/libretranslate-keys/`.
- Studio `Client.php` communicates with `unix:///run/my-una-translator/key-manager.sock` through a newline-delimited JSON protocol, with bounded request and response sizes.
- The key manager's Unix socket accepts only a configured peer UID verified by `SO_PEERCRED`; the key-manager service uses `User=libretranslate-manager`, `Group=myunaworld`, and runtime directory mode 0750.
- Translation Memory runs separately on loopback `127.0.0.1:5051`, serving customer `/health`, `/lookup` and `/submit` endpoints. Its production authentication/lookup paths and 30/minute in-memory customer request-limit tests have passed. Its unit remains disabled for automatic startup.
- Translation Memory storage already implements `pending(limit)`, `review(correction_id, approved, administrator)`, `lookup(customer,source,target,context)`. SQLite tracks corrections, audit_log and usage_log. Submission requires `sharing_consent=true`; only approved, consented corrections are eligible for lookup. Customer API has no administrator HTTP endpoint.
- Current PHP CLI memory configuration was corrected to 1024M; no change to site-specific CGI settings is part of this project.

## User experience
A dedicated UNA Studio route `/studio/libretranslate_memory.php` is linked from the existing Key Issuer page's menu, and links back to it. It uses `BxTemplStudioPage` and the site's Studio look and feel, with responsive layout, restrained color, appropriate icons, and accessible button labels. The six summary cards, based only on real database/query results, are:
1. Pending Corrections — count pending.
2. Approved Translations — count approved, consented corrections.
3. Rejected Corrections — count rejected.
4. Participating Websites — distinct consenting contributor site IDs/hostnames, with no hidden secrets.
5. Translation Reuse — count of recorded approved-memory lookups/usage events.
6. System Health — status of administration socket and readable memory database; customer API health only if explicitly probed and labeled as such.

Below the cards: a paginated pending-correction table showing source text, proposed translation, source/target language, context, contributing site, submitted timestamp, and an explicit Approve, Reject, and View Details action. Search/filter controls should cap page size and never read all rows at once. A separate paginated audit-history table shows decisions, actor and timestamps. Empty, loading and error states are explicit, including when the administration service is unavailable. User-supplied translation text is escaped or inserted via `textContent`, never trusted HTML. No raw API keys, encryption keys, or session tokens appear in logs, tables or response messages.

## Architecture and trust boundaries
**Browser → Studio PHP:** New `libretranslate_memory.php` entrypoint requires a logged-in UNA Studio administrator using the same server-side checks already observed in `libretranslate_keys.php`. Mutating and data-fetch actions use POST with a session-bound CSRF token, server-side allowlisted action names, validated input types/lengths and no-store responses. Unauthenticated or nonadmin requests fail before access to the IPC client. `actor` is derived from the authenticated UNA session, not a browser-supplied field.

**Studio PHP → new private admin socket:** Add an isolated memory-administration Unix socket (e.g., `/run/my-una-translator/translation-memory-admin.sock`) and a dedicated `Client.php` with bounded JSON-line requests, timeouts, strict JSON response checking and generic errors on internal failures. It must not use the customer API key or add admin actions to `127.0.0.1:5051`. Retain the existing key-manager socket and operation set without changes. Do not add an external Apache ProxyPass or public TCP listener for the admin service.

**Admin socket → memory DB:** Add a separate administration service/process with minimal required DB access and a strict whitelist of operations: `summary`, `pending`, `audit`, `review`. Identify each Unix peer through `SO_PEERCRED` and require exactly the configured UNA site service UID, not just filesystem group membership. Bind the socket with restrictive mode 0660 inside a runtime directory mode 0750. Verify effective systemd settings, including `NoNewPrivileges=yes`, `ProtectSystem=strict`, `ProtectHome=yes`, `PrivateTmp=yes`, and access only to the Translation Memory store and runtime socket. No access to LibreTranslate customer keys is required for admin requests. If the current service account remains `libretranslate-manager`, explicitly restrict what paths are visible in this unit and document the residual shared-UID risk. Before startup verify that the PHP worker's effective UID really equals the allowed UID and that no unrelated virtual host runs under it.

The separate Unix peer UID protects the IPC boundary; it is not a replacement for UNA administrator session checks. Both controls are mandatory. Reject unexpected JSON fields, malformed payloads, oversized messages, unauthorized peers and review actions for non-pending entries. All responses contain only the minimum permitted data.

## Review semantics and privacy
The existing `storage.review` remains the authoritative write path. Before approval, validate the correction is pending and has explicit sharing consent; record `reviewed_by` and `reviewed_at` and an audit event atomically. Approval must not create ambiguous concurrently-approved values for the same exact source-language, target-language, context and source-text key; enforce conflict handling inside one transaction, backed by an appropriate database invariant or equivalent tested locking logic. A conflict returns a clear admin-level message without approving the second entry. Reject also writes an audit event. Repeated clicks never silently change a previously-decided correction. Lookup continues to return approved exact matches only, with no automatic fallback to an unreviewed or ambiguous translation.

The page is an administrative review tool only. Connecting the UNA Translator's generation/retry workflows to customer `/submit` and `/lookup`, and enabling any cross-site memory reuse, are separate later releases with privacy/placeholder-validation tests and explicit opt-in. The current end-to-end integration must not be claimed before those releases.

## File boundaries
- Keep `studio/libretranslate_keys.php`, `studio/libretranslate-keys/Controller.php` and `Client.php` behavior unchanged. Add only a navigation link to `Page.php`.
- Add `studio/libretranslate_memory.php` and `studio/libretranslate-memory/{Page.php,Controller.php,Client.php,memory.css,memory.js}` (exact naming may follow existing site conventions).
- Add isolated `services/translation_memory/admin_server.py` (or similarly named bounded module), focused queries/statistics if absent, and a separate locked-down systemd unit. Do not modify `services/key_manager/server.py` or its socket API.
- Keep the customer Memory API on `127.0.0.1:5051` and the existing LibreTranslate installation unchanged.
- Perform all edits with timestamped backups; preserve current ownership and permissions.

## Safety tests and rollout gates
1. Unit tests first: malformed/oversized JSON, forbidden operation, unauthorized peer UID, validation limits, review of non-pending/non-consented rows, duplicate approval conflict, audit atomicity, valid summary and pagination, and HTML injection escaping.
2. Build review fixtures in a disposable SQLite database. Confirm unauthorized HTTP clients and customer Bearer tokens cannot reach admin operations. Verify browser controller tests for admin-only and CSRF rejection.
3. Validate strict systemd effective settings and socket file owner/mode, verify PHP site's actual effective UID and Apache isolation, then smoke-test the admin socket locally. Do not open a public TCP port.
4. Deploy to an isolated test copy first if possible. Back up production Studio PHP/CSS/JS, compare hashes and lint PHP/JS; verify the six-card layout, navigation, CSRF, error states and pending/approved/rejected review flows. Verify the existing Key Issuer still lists, creates, rotates and revokes test keys as permitted without disruption to real customers.
5. Confirm the existing key manager and customer Memory API remain healthy. Restart only the new admin service as required. Enable automatic startup and expose the Studio link only after tests pass and the user approves deployment.
6. Provide rollback steps restoring Studio backups, removing the new navigation link and stopping/disabling only the new admin unit. Leave the customer Memory API and key manager untouched.

## Out of scope
No public administrator REST API; no unauthenticated dashboard; no new customer key issuance path; no automated machine-translation rollout; no bulk approval; no translation data import; no changes to other UNA sites; no Marketplace or GitHub release publication under this design approval.
