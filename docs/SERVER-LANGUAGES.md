# Server-language support

LibreTranslate's `/languages` response supplies installed destinations reachable from English. English remains the source and is excluded from the destination picker. The My UNA World server reported 50 languages on October 6, 2026, yielding 49 destination choices including Arabic. Studio searches both native names and English names/codes, and retains the existing limit of one to five destinations per job. No language is selected automatically.

Studio presents three numbered steps: choose languages and create a plan; generate and review the preview; apply the reviewed translations. Saved jobs are selected explicitly. Generate, Resume and Apply follow the saved job state, and successful planning/settings notices stay inline. Progress and errors still use the status popup. Scope, character limits, older-job lookup and rollback remain available in expandable controls.

The site-language panel reads UNA's installed languages independently of the translation provider, shows visitor availability and links to available translated views. It distinguishes translation targets from the site default, which visitors may override in the footer selector. Changing the site default is an optional native Polyglot action. Removing saved job history preserves language ownership metadata and applied text. The selector includes enabled native languages while respecting separately installed language modules' visibility rules.

Portuguese (Brazil) and both Chinese scripts map API routes to UNA locale codes; existing `pt-br` and `zh-cn` jobs remain compatible. Applying saved previews uses local metadata and does not require a working translation provider. Newly created Arabic, Hebrew, Persian and Urdu language records use RTL. Existing native language records are preserved. DeepL retains its existing five targets.

Discovery has a five-second total timeout and three-second connection timeout. Translation requests retain their separate 90-second total timeout. Failed discovery leaves Studio status/settings accessible, reports the provider error and offers no unavailable destinations.

Validation commands (PHP requires DOM, mbstring and OpenSSL; the last test also requires cURL and proc_open):

```
php tests/server_languages.php
php tests/libretranslate_quality_gate.php
php tests/curl_language_timeout.php
node --check template/js/translator.js
node --test tests/workflow.cjs
git diff --check
```

The 50-language fixture was taken from the configured live endpoint. Tests cover available English routes, Arabic job creation, RTL registration, aliases, immediate provider outage, DeepL compatibility and Studio HTML. The cURL test uses an ephemeral loopback HTTP server to verify stalled discovery times out while slower translations still succeed. The existing quality suite checks 45 curated outputs for the original five languages; it does not certify linguistic quality in all newly installed models.

This change is a live file repair and source checkpoint. Version 0.1.9 and its existing release/build records are unchanged. No new installer, native Studio update, marketplace package, GitHub tag or release is certified by this branch. A future controlled release must reconcile the runtime changes with its updater and build identity.
