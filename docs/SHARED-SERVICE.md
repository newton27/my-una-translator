# Shared LibreTranslate service for client modules

Prepared 2026-10-05; public HTTPS deployment and client onboarding are pending.

Each client's UNA server connects to your translation hostname over HTTPS, using a separate revocable API key. Translation requests come from the module's PHP backend. The client's browser displays its own saved progress and errors. No shared UNA database or client Studio access is required. Plain text fragments are sent to your server; the client's protected markup, placeholders, links and supported names remain local.

## Existing host and proposed public entry point

The saved live setup record reports LibreTranslate 1.9.6 in a pinned rootless Podman container on the UNA VPS, bound privately at `127.0.0.1:5001`, with five English target routes, a 20-fragment batch limit, and 3 GiB/1.5 CPU resource limits. The iMac SSH tunnel reaches it. The tunnel key rejects remote shell commands; it cannot configure the server or install the 0.1.9 updater.

Use a dedicated translation hostname under your domain after checking DNS and certificate ownership. No hostname, DNS record, TLS certificate or public listener was created by this work. Keep port 5001 private. Add an HTTPS reverse proxy to that loopback service; proxy only `/languages` (GET) and `/translate` (POST). Use a request body ceiling at least as large as the module's 120,000-byte JSON limit, such as 128 KiB, and upstream timeouts above the module's 90-second request timeout. Do not log request bodies or API keys. Reject query-string credentials; the module sends `api_key` in the JSON body. Leave the test web UI and file translation disabled for the client endpoint.

Before changing the existing container, inspect its current user service, pinned image, model mounts and data paths. Back up that configuration and the API-key database. Retain the loaded models and current resource limits; do not replace the working service with an uninspected sample command.

## Required service controls

Apply these settings to the existing pinned service, using its actual persistent mounts:

```text
ORT_DISABLE_TELEMETRY=1
LT_API_KEYS=true
LT_UNDER_ATTACK=true
LT_API_KEYS_DB_PATH=<persistent container path>/api_keys.db
LT_REQ_LIMIT=600
LT_CHAR_LIMIT=5000
LT_BATCH_LIMIT=20
LT_DISABLE_WEB_UI=true
LT_DISABLE_FILES_TRANSLATION=true
```

These numbers are starting settings for a capacity test, not proven customer capacity. Enabling API keys alone still permits anonymous translation; `LT_UNDER_ATTACK=true` requires a valid key. Create a key for the owner's module before enabling required keys, and configure it on the owner site while generation is paused. Retain DeepL as the selected provider until the wording gate passes.

Use `ltmanage keys --api-keys-db-path <same database path> add 600 --char-limit 5000` inside the service's runtime to issue a separate key for each client. Store the owner/client mapping privately. Do not put keys in URLs, distributable ZIPs or this document. Keep the SQLite key database persistent across container restarts, writable only by the service's owner. Remove a client's key to revoke it; restart/reload the service when immediate revocation is needed because workers can cache key lookups.

The local 1.8.4 test confirmed independent key buckets, rejection of missing/invalid keys and the per-key character ceiling. Array elements count toward request cost in that version, and the character check is per fragment; a batch is not necessarily one quota unit. Verify the deployed 1.9.6 behavior before setting client allowances. Module job ceilings are client-side planning controls; they are not a server-enforced subscription quota. Monthly metering, billing and an account portal are outside this prepared setup.

Confirm the existing container's worker count before exposing it. In-memory rate limits are per process. Use shared Redis rate-limit/storage settings for multiple workers/replicas, or explicitly operate one worker for the initial capacity test. Do not assume a rate setting controls aggregate use across independent workers. Benchmark CPU/RAM and concurrent latency before adding another VPS or raising concurrency.

## Client setup

Give each client the verified HTTPS base URL and their own key through an appropriate private channel. In their module's Provider settings, they enter that URL/key, save settings, check the service, and generate a small preview. Clients do not need their own LibreTranslate installation. The provider check verifies language routes; it does not validate the key because `/languages` is public. A bounded translation preview is the authentication check. Access rejection, rate limiting and connection errors pause generation and remain visible in the progress popup; retries require explicit resume.

Keep generation paused when changing providers. Changes affect future requests, including resumed jobs. Previously saved previews remain saved and are not retranslated automatically. Do not apply live test job #46 simply because its progress is complete; its displayed wording contains errors.

## Deployment acceptance checks

1. Verify the chosen hostname resolves to the intended VPS and serves a valid certificate from a client UNA server; keep the private port unreachable publicly.
2. Check `/languages` exposes all five English routes. Confirm missing keys and unknown keys cannot translate.
3. Issue two disposable client keys with different low limits; prove one key's limit does not block the other. Check the character and batch ceilings. Revoke test keys and prove revoked access fails after cache/reload handling.
4. Restart the service and verify persistent client keys and limits survive. Verify owner-site credentials, saved jobs and translations remain intact.
5. Generate a bounded preview from each of two independent UNA modules. Verify their progress/error popups and successful reconstruction of placeholders/markup.
6. Review broader wording for all five languages, including ambiguous actions, account permissions and payment warnings. The current sample fails this step; do not switch the default or present linguistic quality as approved.
7. Measure simultaneous-client CPU/RAM/latency and set allowances from those measurements before onboarding customers.

Official references: https://docs.libretranslate.com/guides/manage_api_keys/ and https://docs.libretranslate.com/guides/installation/ . Local protocol evidence: `LIBRE-CLIENT-TESTS.json`. The public setup and the deployed image's controls remain to be verified on the VPS.
