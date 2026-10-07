# My UNA Translator 0.1.9 RC 4

Native UNA 15 Studio module by My UNA World. Translates English interface language keys using a configured LibreTranslate service or DeepL API Free/Pro. RC 4 retains the server-language picker, guided workflow and Arabic support. Server-side key issuance is managed separately by the service operator. UNA's manifest version remains 0.1.9.

## Install and package scope

Extract `my-una-translator-0.1.9-rc.4.zip` into `modules/newton/`. Its single root folder is `my_una_translator/`; the manifest must be at `modules/newton/my_una_translator/install/config.php`. Open **Studio → App Store → Downloaded**, then install and enable **My UNA Translator**. A hosting ZIP uploader may place the folder for you; Module Doctor's reference upload audits a package and does not deploy its files.

Requires PHP 8.1 or newer with cURL, DOM, mbstring and OpenSSL, MySQL/MariaDB advisory locks, writable UNA language caches and connectivity to the selected provider. LibreTranslate accepts an HTTPS base URL or exact loopback HTTP at `127.0.0.1`.

This is a full module prerelease. Native fresh-install and version-update validation is incomplete. No 0.1.8-to-0.1.9 updater is included. The same-version workflow files were verified on an existing 0.1.9 site while preserving settings, saved jobs, native language records and translations; that file repair is not native update certification. Retain a normal site backup before testing.

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

See [RELEASE.md](docs/RELEASE.md) for RC 4 provenance and gates, and [server-language support](docs/SERVER-LANGUAGES.md) for discovery, aliases, RTL handling and timeout behavior.

```sh
php tests/server_languages.php
php tests/language_targets.php
php tests/libretranslate_quality_gate.php
php tests/curl_language_timeout.php
node --test tests/workflow.cjs
python3 -m unittest discover -s tests -p test_release_boundary.py
```

The server-language suite uses an isolated SQLite fixture. The timeout test uses an ephemeral loopback server and requires cURL and proc_open.

The curated quality test verifies 45 exact local terminology outputs in five languages. It does not certify general model output, all 49 language models, or a stable provider default. Historical model samples and earlier baseline checks remain in [VALIDATION.md](docs/VALIDATION.md); they are separate from the RC 4 checks. Wider human linguistic review and native install/update validation remain outstanding.
