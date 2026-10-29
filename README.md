# My UNA Translator 0.1.11 development candidate

Preview editing is implemented and locally tested. Native fresh-install and 0.1.10-to-0.1.11 update validation remain pending; no release has been published.

## Correct a preview

In Generate and review, choose **Edit translation**, correct the text, then **Save translation** or **Cancel edit**. Saved corrections show **Manually edited** and remain in the job until Apply. Keep placeholders, links and HTML unchanged. Unsaved edits keep Apply and page controls disabled; validation errors retain your draft. Only ready or paused jobs with completed preview rows can be edited. Applied history is read-only. Existing Polyglot corrections remain protected by Apply conflict checks.

The native 0.1.10-to-0.1.11 updater adds only the module-owned `manually_edited` flag before copying runtime files. It preserves saved jobs, provider settings and language strings. A failed later step can retry the column migration safely. Sites on another version need a verified upgrade path before deployment. No UNA core files are changed.

Development tests: `npm ci` then `npm test` (PHP 8.1+ with mbstring, DOM, SQLite and cURL). The new DOM suite uses jsdom; it does not certify visual browser layout.

## Previous candidate reference (0.1.10)


Native UNA 15 Studio module by My UNA World. Translates English interface language keys using a configured LibreTranslate service or DeepL API Free/Pro. Version 0.1.10 retains the server-language picker, guided workflow and Arabic support. Server-side key issuance is managed separately by the service operator. The native manifest is 0.1.10; this is an unpublished release candidate.

## Install and package scope

Extract `my-una-translator-0.1.10-rc.1.zip` into `modules/newton/`. Its single root folder is `my_una_translator/`; the manifest must be at `modules/newton/my_una_translator/install/config.php`. Open **Studio → App Store → Downloaded**, then install and enable **My UNA Translator**. A hosting ZIP uploader may place the folder for you; Module Doctor's reference upload audits a package and does not deploy its files.

Requires PHP 8.1 or newer with cURL, DOM, mbstring and OpenSSL, MySQL/MariaDB advisory locks, writable UNA language caches and connectivity to the selected provider. LibreTranslate accepts an HTTPS base URL or exact loopback HTTP at `127.0.0.1`.

This is an unpublished full installer candidate and a separate native 0.1.9-to-0.1.10 updater candidate. A native Studio fresh install of the 0.1.9 dashboard prototype passed on Man of Teal; a native Studio 0.1.9-to-0.1.10 upgrade also passed on Man of Teal, preserving its saved job and settings. Publication remains separately approval-gated. The update replaces only the Studio dashboard, its stylesheet, the popup JavaScript and the version manifest; it deliberately leaves provider settings, saved jobs, translations and the other module source files intact. Do not uninstall an existing Translator to apply the update. Retain a normal site backup before testing.

## Native update from 0.1.9

Use the separately versioned `my-una-translator-update-0.1.9-to-0.1.10-rc.1.zip` to stage `my_una_translator/updates/update_0.1.9_0.1.10/` under the already installed module. On tested UNA 15.0.0-RC2, the **Apps → Updates** listing shows Marketplace updates only: the manually staged package was discoverable through UNA's installer API but was not offered as an on-screen update button. The tested upgrade was run through UNA's authenticated native Studio update action. Until a supported one-click updater is provided or Marketplace distribution is verified, clients should request administrator deployment help rather than attempt a manual database update. The installed 0.1.9 package should match the RC4 runtime or be reviewed for tracked checksum drift first. This updater is NOT a complete reinstall and has no database schema or job-data migration.

The status popup shows **No job selected** on an empty installation rather than incorrectly reporting **Working...**. Actual running, ready and paused jobs retain their status display. The six-card dashboard remains read-only.

## Translate

Configure the provider once in **Provider settings**. For LibreTranslate, enter the service base URL and any required API key. For DeepL, choose its API Free or Pro endpoint and enter the API key; a normal DeepL website subscription is separate. Existing saved provider settings are preserved.

1. **Choose languages.** Search by native name, English name or code. LibreTranslate shows installed destinations reachable from English (49 on the checked server). DeepL retains six presets, including Arabic. Select one to five languages and create a plan. Scope and character limits are available in the expandable controls.
2. **Generate and review.** Generate the preview, or explicitly open a saved job and resume it. Review English and translated text side by side. Progress opens while work runs; hiding it keeps the work running while the Studio page stays open. Saved pause reasons remain beside the recovery action. Generation does not apply translations.
3. **Apply to your site.** Apply the completed, reviewed preview. Newly created native language records become available after their caches compile when the availability checkbox is selected. Check the resulting language links in **Languages on your site**.

The site-language panel and visitor selector use UNA's native records. Translating another language does not change the site default. Visitors can choose an enabled language in the footer; changing the default is an optional native Polyglot action. Existing native language-module visibility rules are retained.

Missing or blank destination text is eligible. Existing English copies are eligible when selected in plan options. Existing non-English text is preserved. Member posts, messages, uploaded files, hardcoded theme text and external services are outside the scope. Coverage measures text different from English, not linguistic quality.

Placeholders, HTML, links, email addresses and supported product names stay local and are validated after reconstruction. Generation runs in small sequential batches. Pause takes effect after the current request; limits are checked before each request. Apply and rollback use saved data. Later manual edits survive rollback and appear as conflicts. Removing saved job history keeps applied text and native language registration.

## Connecting to the shared service

For the My UNA World LibreTranslate service, request a site-specific key from the service operator. Save that key with the HTTPS base URL in Provider settings. Issuing, rotating and revoking service keys is performed in the operator’s separate administrator page; those tools are not part of this customer module.

## Credentials and data

Provider keys are encrypted with UNA's site secret and never returned by snapshots. Blank key inputs retain saved keys; their removal controls clear them. Back up the site secret with the database when restoring settings.

Disabling retains module data and restores the standard selector. Uninstall removes module-owned tables and Studio registration, while keeping native languages and generated translations. Roll back wanted jobs before uninstalling to restore their previous text.

## Validation

See [RELEASE.md](docs/RELEASE.md) for 0.1.10 RC 1 provenance and gates, and [server-language support](docs/SERVER-LANGUAGES.md) for discovery, aliases, RTL handling and timeout behavior.

```sh
php tests/server_languages.php
php tests/language_targets.php
php tests/libretranslate_quality_gate.php
php tests/curl_language_timeout.php
node --test tests/workflow.cjs tests/status_popup.cjs
python3 -m unittest discover -s tests -p test_release_boundary.py
```

The server-language suite uses an isolated SQLite fixture. The timeout test uses an ephemeral loopback server and requires cURL and proc_open.

The curated quality test verifies 45 exact local terminology outputs in five languages. It does not certify general model output, all 49 language models, or a stable provider default. Historical model samples and earlier baseline checks remain in [VALIDATION.md](docs/VALIDATION.md); they are separate from the RC 4 checks. A native 0.1.9-to-0.1.10 upgrade passed on Man of Teal without data changes. Wider human linguistic review, a fresh native 0.1.10 install and a client-facing on-screen update path remain unverified.
