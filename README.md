# My UNA Translator 0.1.9

Native UNA 15 Studio module by My UNA World. Translates English interface language keys using DeepL API Free/Pro or a self-hosted LibreTranslate service. This branch discovers LibreTranslate destinations from the server's installed English routes; DeepL retains its five existing destinations. Studio supports searching languages and selecting one to five per job. Existing provider settings are preserved. See [server-language support](docs/SERVER-LANGUAGES.md) for deployment and validation scope.

## Install

Upload `my-una-translator-0.1.9.zip` through your server file manager or SFTP. Extract its single root folder, `my_una_translator/`, into `modules/newton/` so the manifest is at `modules/newton/my_una_translator/install/config.php`. Then open **Studio → App Store → Downloaded**, install and enable **My UNA Translator**. If your hosting setup provides a native module ZIP uploader, it can place the folder using the manifest. The current myunaworld.com App Store page does not expose an upload control; Module Doctor's reference upload only audits a package and does not deploy its files.

Requires PHP 8.1 or newer with cURL, DOM, mbstring and OpenSSL, a MySQL/MariaDB server with advisory locks, writable UNA language cache directories and connectivity to the selected provider. LibreTranslate supports a public HTTPS base URL or exact loopback HTTP at `127.0.0.1`. This is a development candidate; retain a normal site database backup before applying a full translation job.

## Update from 0.1.8

Extract `my-una-translator-update-0.1.8-to-0.1.9.zip` into `modules/newton/`. It stages only `my_una_translator/updates/update_0.1.8_0.1.9/`; the installed runtime changes when native Studio applies the update. The updater replaces runtime files, updates the English provider note and clears caches. It performs no schema or job-data migration and preserves the selected provider, encrypted credentials, saved jobs/previews, translations and selector registrations. Keep the previous package and normal site backup as the rollback checkpoint. Version 0.1.9 has not been installed or verified natively on production. Sites on 0.1.7 must first use the existing preserving 0.1.7-to-0.1.8 update.

## LibreTranslate quality gate

Version 0.1.9 keeps leading separator punctuation around placeholders local and uses explicitly curated common interface fragments, including login labels and the unambiguous action “Resume generation”. It does not reinterpret bare “Resume”, which may mean a CV. These corrections are not improved model output. The audit tool records model-only output separately from corrected/mixed/protected output. The glossary is not a native-speaker certification of the interface.

The expanded local LibreTranslate 1.8.4 check used 51 sources in five languages: all 255 production outputs preserved protected content. Broader wording still fails: Spanish rollback text, German bare Resume, and a Chinese payment warning have serious errors. A separate 50-output raw test through the VPS tunnel also found unsuitable action labels. **Keep the default-switch gate withheld.** See `docs/VALIDATION.md`, `docs/LIBRE-CORRECTED-SAMPLES.json` and `docs/LIBRE-VPS-SAMPLES.json`. The original uncorrected 70-output evidence remains in `docs/LIBRE-SAMPLES.json`.

To use the optional provider, load English routes to `es`, `fr`, `de`, `pt-BR` and `zh-Hans`. Model packages may use `pb` and `zh` while API codes differ. Set `ORT_DISABLE_TELEMETRY=1` before starting ONNX runtime. The live setup record reports a private LibreTranslate 1.9.6 service on the UNA host; the site module connects directly to it without a desktop tunnel. This record is separate from this candidate's local tests. No new VPS is needed to prove connectivity, and capacity for multiple clients has not been measured.

Clients can use the same host by entering its public HTTPS base URL and their own API key in Provider settings. The module already supports this arrangement and keeps keys encrypted on each client's UNA site. The shared endpoint is not deployed yet. See `docs/SHARED-SERVICE.md` for the prepared setup and verification gates.

### Curated wording regression update (2026-10-06)

Exact local terminology now covers nine reviewed interface strings that were ambiguous or misleading in LibreTranslate output: **Sign in**, **Resume generation**, **Resume** (the job-control action), **Create a new post**, **Roll back changes**, the payment failure notice, the character-limit pause notice, **Character limit**, and **Translation job**. The regression test verifies all 45 expected outputs across Spanish, French, German, Brazilian Portuguese, and Simplified Chinese through the provider batch path; these exact entries bypass the translation service. Unreviewed text continues to LibreTranslate. This resolves the recorded sample failures for those strings, but it does not certify open-ended model quality or complete native 0.1.9 install/update validation. The general default-switch/release gate remains withheld. Run `php tests/libretranslate_quality_gate.php` to repeat this check.

## Translate

1. Open **My UNA Translator** from the Studio launcher. Choose your provider in Provider settings. For DeepL, select Free or Pro and enter its API key. For LibreTranslate, enter the service base URL and its optional API key. Save settings. A regular DeepL website subscription is different from an API account.
2. Check provider availability or usage. Select languages, a Polyglot category (or all categories), and a job character ceiling. Create the plan to inspect its size without making a provider request.
3. Generate the preview. The status popup opens immediately and shows saved previews, total strings, percentage, used/reserved characters, and a waiting indicator during each request. Hide popup keeps work running; Show progress reopens it. Keep the Studio page open. Errors and pause reasons remain visible, including when the final refresh also fails. The popup distinguishes previews from applied translations.  This sends translatable fragments to the saved provider. DeepL uses your API allowance; a self-hosted LibreTranslate service consumes your server resources and has no DeepL quota. Requests run in small, sequential batches while the page remains open. Pause takes effect after the current request. The ceiling is checked before each request; provider usage remains the authority for billing.
4. Review previews. Apply only when the job is ready. Newly created language records remain disabled until their cache compiles; enable them with the checkbox if desired. English remains the site's default.
5. Use Polyglot to refine translations. Roll back a job if needed; later manual changes survive rollback and are listed as conflicts.

Existing non-English destination text is preserved. Missing and blank destinations are eligible; copies of English are eligible when the plan checkbox is selected. Member posts, private messages, uploaded files, hardcoded theme text and external services are outside the scope. Coverage reports text different from English, not a judgment of translation quality.

Placeholders, HTML, links, email addresses and supported product names stay local. DeepL receives indexed XML text fragments and LibreTranslate receives arrays of plain-text fragments; the module rebuilds the exact protected skeleton and validates it. Resuming an older job rechecks staged previews and regenerates previews with damaged links. Plan and next-batch estimates are conservative and may exceed actual billed text. Malformed provider responses pause the job. Interrupted requests are never automatically repeated: check the provider, then explicitly resume. Reserved characters remain counted because an interrupted response may still have been billed. A job can contain at most 50,000 strings; use categories or fewer target languages for larger sites.

## Credentials and retention

Only Studio administrators can use the module. API settings and every action require a session token and POST request. Both provider keys are encrypted using the site's secret and are never included in the dashboard response. A blank DeepL key field preserves its saved key. A blank LibreTranslate key field preserves its saved key only when the URL is unchanged; changing the URL clears it unless a replacement is entered. The Remove saved LibreTranslate key checkbox also clears it. Selecting LibreTranslate preserves the DeepL key for a later provider change. Back up the site's secret with its database if you need to restore encrypted settings.

Job snapshots and before/after values are stored in four module-owned tables. Disabling the module retains them and restores the standard UNA language selector. Uninstall removes its tables and Studio registration; it keeps the native language records and generated translations. Roll back wanted jobs **before uninstalling** if you need their old values restored.

## Development and validation

`php tests/run.php` and `php tests/libre.php` run the standalone protection, provider-protocol and access tests with a fake provider. `php tests/integration.php` runs real database tests in an isolated database named `translator_test`, dropping that database's existing tables. It refuses any other database name. Set `TRANSLATOR_TEST_DSN`, `TRANSLATOR_TEST_USER` and `TRANSLATOR_TEST_PASSWORD` to configure it. Never point integration tests at a live UNA database. `node --check template/js/translator.js` checks dashboard JavaScript syntax.

The database suite covers preview-only staging, apply/rollback, source and destination edits, character ceilings, interrupted requests, locks, language cache failures and uninstall preservation. No real API key is included and fake-provider tests do not prove a live DeepL request. See `docs/VALIDATION.md` for this candidate's verified scope. `php tools/libre-smoke.php http://127.0.0.1:5000` checks an actual LibreTranslate service without reading the site database; set `LIBRETRANSLATE_TEST_KEY` only if your service requires it.

Provider documentation: https://developers.deepl.com/api-reference/translate/request-translation and https://developers.deepl.com/api-reference/usage-and-quota/check-usage-and-limits

Version 0.1.2 supplies source-language software context to DeepL so short labels such as resume, job, character and apply are interpreted as interface actions. Context is not included in billed text. Existing previews remain available; create a new plan to regenerate wording with the current provider.

Version 0.1.3 compares native language codes byte for byte when enabling or disabling newly created languages. This supports native and module tables with different collations without changing their schemas. An interrupted apply can be retried using the saved translations, without generating another preview.

Version 0.1.4 keeps the full UNA.IO domain label local, avoiding a truncated .IO fragment that can be mistranslated. Existing safe previews remain saved when generation resumes.

Version 0.1.5 keeps translated words separate from protected names when translation removes an adjacent possessive or hyphen. Original punctuation and existing spacing are retained whenever the name already has a valid boundary. The same strict markup, link and placeholder validation still runs after reconstruction.

Version 0.1.6 exposes enabled native language records created by Translator in UNA’s existing popup and inline selectors. Native language modules keep their own visibility rules. Only the standard selector registration is extended; existing custom menu classes are preserved. Disabling or uninstalling the translator restores its own menu registration without altering later customizations.

Version 0.1.7 adds the translation status popup with batch progress, phase-aware apply/rollback counts, a waiting heartbeat, persistent error reasons, and Pause/hide/reopen controls. Counts change only after saved server responses. Paused jobs with a saved error open the popup automatically. The progress display does not resume generation or alter DeepL allowance.

Dashboard tests: run `npm install` and `npm test` with Node 18 or newer. The pure progress suite also runs without dependencies: `node --test tests/progress.cjs`. Native PHP tests remain available through `npm run test:php`; database tests require the isolated database described above.

Version 0.1.8 adds the optional LibreTranslate provider, provider-aware waiting and service checks, separate encrypted credentials and a preserving native updater. The current quality gate withholds a default switch. Changing provider affects future generation requests, including explicitly resumed jobs; saved previews are retained and never automatically regenerated. Pause generation before changing provider.

Version 0.1.9 adds explicit interface terminology and retains separator punctuation for LibreTranslate only. Raw audit output remains available with `php tools/libre-smoke.php http://127.0.0.1:5001 --expanded`. Existing saved previews are retained and do not acquire the corrections automatically; review them independently. The shared-client service requires HTTPS, required per-client keys, measured limits and a live two-client check before onboarding clients.
