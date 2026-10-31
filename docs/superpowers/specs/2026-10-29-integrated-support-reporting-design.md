# Integrated module error reporting to My UNA World

Date: 2026-10-29
Status: Proposed design; integrated approach selected, implementation pending design approval.

## Intent and decisions

Clients encountering My UNA module errors must be able to send a useful private report to My UNA World without copying screenshots or logs manually. Michael selected integrated reporting. Start with Translator, reuse the reporting contract and transport in other My UNA modules, and use the existing My UNA Tickets system as the support destination.

Previous requirements remain: guests can submit with an email address; sign-in at My UNA World is optional; private access and conversations use existing Tickets authorization. UNA core files must not change. Reporting does not apply translations, retry translation jobs, change translation validation, or grant remote access to a client site.

## Approaches

1. Integrated review-and-send reporting inside the module — selected. Requires a bounded HTTPS intake endpoint and client-side integration, but gives clients an immediate ticket reference without leaving the failing operation.
2. Prefilled support-page handoff — considered. Reuses the ordinary form more directly but adds a context switch and manual submission step. Retain the ordinary support page as a fallback when intake cannot be reached.

## Client experience

- Show **Report this problem** beside a saved error and in its status popup. Also expose a general **Contact support** action for failures that occur before a job is created.
- Clicking opens a report form inside the module. It contains contact email, an editable summary and description, and an expandable preview of exactly the diagnostic fields to be sent. Do not collect or transmit a report merely because an error occurs or the form opens.
- The final action is **Send report to My UNA World**. Require a valid contact email and nonempty description. Prevent double submission while the request is active.
- On success show the ticket number, **Saved — pending support review**, and a link to the existing ticket-access page. Retain the ticket reference with the local error so the user can recognize a previously reported incident.
- Never imply a report was saved after a timeout. Show **Delivery not confirmed**, preserve the report draft and submission identifier, and offer an explicit retry using that same identifier. No background retry loop or automatic sending.
- Distinguish an accepted ticket with delayed receipt email from a report that was not accepted. A mail failure must not discard an accepted ticket or create a duplicate.
- Keep the current job and saved previews untouched. Close/submit reporting without starting, resuming, deleting, or applying translation work.

## Diagnostic contract v1

Common fields: schema version, client-generated random submission UUID, module slug/title/version, UNA version, PHP version, claimed site hostname, UTC incident time, operation name, safe error code and sanitized display message. Strip site URL paths, query strings, fragments, user information, and arbitrary ports. Hostname is a user-reviewed claim, not proof of site ownership.

Translator fields: local job number, target language code, job state, total/ready/applied/conflict counts, selected provider name, and failing language-key identifier when recorded. Do not send translation-service URL, keys, source strings, generated translations, arbitrary exception dumps, traces, database records, or account/member identifiers. Use known diagnostic message codes rather than serializing raw exceptions. If a key was not captured when the error occurred, report it as unknown; never guess from progress counts.

The editable email, summary, description, and diagnostics are the entire outbound payload. Clearly ask users to omit secrets from free text. No automatic screenshot, log, cookie, API key, site secret, mailbox, payment, member-post, or private-message capture. Retain a local incident identifier and sent ticket reference, not an additional local copy of the submitted contact email or free-text report. A report draft may remain in the current browser session until success or dismissal.

## Central intake and existing Tickets integration

Add a module-owned JSON POST entry point within My UNA Tickets, with UNA bootstrap and an isolated HTTP adapter. It is a public support-intake endpoint, consistent with existing guest ticket submission. It does not trust incoming site names, headers, member IDs, or email addresses as authenticated identities. Never accept a client-supplied central member ID or grant private-ticket access through the intake response.

The source site's Studio handler requires its existing administrator authorization and CSRF check. It builds an allowlisted diagnostic object server-side and sends JSON over HTTPS to the fixed My UNA World intake URL. Do not accept arbitrary outbound URLs, follow redirects, or disable certificate checks. Use bounded connection/total timeouts and response-size limits. No universal embedded secret and no use of translation API keys for support access.

The intake endpoint accepts JSON only, enforces a 32 KiB body limit, validates strict field types/lengths and schema version, resolves an allowed module slug to an active Tickets product on the server, and rejects unexpected credential/private-data fields. Unknown or disabled products return a clear error, not a mismatched ticket. The Translator product must be present and active before activation.

Use a dedicated intake service with the existing Tickets submission/repository boundary. Submitted diagnostics are private support details. Existing moderation may publish separately reviewed public fields, never the original diagnostic report automatically. Staff see the module, claimed hostname, incident, structured diagnostics, and description in the existing queue/detail view; private replies continue through Tickets.

At intake, a contact email is an unverified guest identity. Existing email-link verification governs later private access. A matching email or domain must not automatically link a new report to a central member account. Users may optionally sign in on the My UNA World support site through its ordinary flow. V1 does not add cross-site SSO or reply synchronization inside the client module.

## Idempotency, abuse controls, and persistence

Add a module-owned intake ledger keyed uniquely by submission UUID plus normalized-email hash. Persist a payload digest and ticket reference atomically with ticket creation. An exact replay returns the original acceptance; the same identifier with changed report content returns 409. Concurrent submissions must create exactly one ticket. Do not return any private ticket text or access tokens. Keep deduplication records for the life of their ticket; define any future purge separately.

Use the existing atomic rate-limit mechanism, with a separate intake namespace. Initial limits are five new reports per ten minutes per email and twenty per ten minutes per source server IP; exact idempotent replays do not create another ticket, send another receipt, or consume the new-report allowance. Return 429 and a bounded retry interval when exceeded. A shared-host limit must yield an understandable message and the ordinary support-page fallback, not silent loss. These thresholds are implementation defaults, not previously selected user policy.

Commit the ticket and idempotency ledger before attempting a receipt. Attempt receipt delivery once; report a saved ticket even if email fails. Preserve the existing staff delivery-failure visibility. Do not add SMTP configuration changes, direct Postfix delivery, new mail cron jobs, or automatic mail retries as part of reporting. The current separate mail-delivery investigation remains relevant to testing receipt/access delivery.

Extend fresh-install SQL and a version-matched native updater for the Tickets ledger. Translator local receipt/error metadata must likewise have a supported install/update path if persisted columns/tables are needed. Prefer one module-owned incident table over embedding sensitive content in existing settings. No core SQL or files are changed. Freeze package versions only after the actual deployed baselines are verified; do not overwrite frozen release assets.

## Rollout and acceptance

1. Verify repository and installed-source differences before touching live modules; preserve existing rate-limit/retry and UI changes that are absent from main. Build in isolated source checkouts and back up module files and affected tables.
2. Test the intake and Translator reporting integration on gayjax.life with a separate disposable test ticket/product namespace or marked test reports. Validate Tickets migrations on a disposable baseline before production intake deployment.
3. Verify success end to end: a controlled Translator error opens a report preview, explicit submission creates one private ticket, staff can inspect it, the client receives the reference, and verified ticket access/reply works.
4. Test invalid payloads, inactive products, non-admin/CSRF rejection at the source, secrets excluded by construction, no raw exception leakage, HTTPS/redirect/timeouts, server outage, email failure, duplicate clicks, timeout retry, and concurrent identical requests. Confirm job progress/content and UNA core files are unchanged.
5. Deploy the central Tickets update before enabling client reporting. Enable first on the backed-up test/client site, then package native updates for other clients. Wider module adoption is a later module-by-module rollout using the same contract, not an untested fleet-wide change.

## Boundaries and builder choices

Required: a working integrated submission and acknowledgment, private staff handling, existing verified ticket access, reusable contract, and client-safe failure behavior. Excluded: automatic telemetry, automatic reporting, general translation fault isolation, remote repair/access, screenshots/attachments, public bug publication, new SSO, and changing mail infrastructure.

Builder may choose class/file organization, exact form styling matching current module cards, namespaced packaging of reusable reporting code, and test fixture architecture. The fixed intake URL, schema, privacy boundary, explicit-send behavior, product mapping, idempotency semantics, and access authorization above are design requirements. No claim of client readiness until the complete acceptance flow passes.
