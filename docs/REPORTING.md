# Integrated reporting — development candidate 0.1.12

My UNA World Tickets belongs only on the actual myunaworld.com server. Client sites install the Translator sender, never a copy of the central ticket service. The sender endpoint is fixed to HTTPS myunaworld.com; redirects are refused.

Reports require an administrator to review diagnostics, enter contact details, and explicitly send. Diagnostics use a strict allowlist and catalogued errors. Raw provider errors, credentials, translation text and member content are excluded. A submitted report becomes a private guest ticket with email verification for subsequent access. Duplicate retries preserve the submission UUID and content; the intake commits a ticket and idempotency ledger together.

Native update: 0.1.11 → 0.1.12. Only module-owned tables and files change. Existing updater snapshots are preserved. Reporting requires central installation and verification before any client enablement.

## Validation status
Standalone contract, repository, source support and UI state tests pass locally. Full native MariaDB migration/concurrency, actual-host deployment, browser layout, receipt mail, verified ticket access and private reply remain acceptance requirements. This is an unpublished development candidate, not a customer-ready release.

## Deployment boundary
No reporting changes have been deployed. Verify the myunaworld.com administration host and document root before any central migration. Do not deploy central Tickets to the client test site or infer authority from a similarly named home directory on another server.
