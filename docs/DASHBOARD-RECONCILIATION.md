# Translator Studio dashboard source reconciliation

Status: LOCAL CANDIDATE ONLY - NOT A PUBLISHED RELEASE.

## Provenance and scope

- Base: upstream newton27/my-una-translator main commit fd378dd8ee995aa65d6e445c85a264ac137aa30a (v0.1.9-rc.4).
- The native UNA manifest remains 0.1.9. Neither an updater version nor a new tag has been assigned.
- Two runtime files were copied byte-for-byte from MyUNAWorld's already deployed dashboard: classes/MyUnaTranslatorStudioPage.php and template/css/translator.css.
- The published RC4 full installer ZIP was downloaded and checksum-verified. Comparing its extracted 45-file module root with this candidate shows only those two runtime file differences.
- The branch adds a standalone render assertion in tests/server_languages.php. This test is not part of the installed module.
- The live server and GitHub remote were not modified by this source-reconciliation stage.
- The live 0.1.9 installation differs from current RC4 in engine, provider, terminology, text handling, and other files. These were NOT copied. Preserve RC4 Arabic support, protected-content corrections, server-language support, and the customer/operator package boundary.
- No API key, active translation job, provider configuration, service issuer, or production database contents were copied.

## Dashboard semantics

Six read-only graphical tiles summarize configured provider, installed and enabled site languages, recent jobs (maximum 20), latest job, character use of that job, and the sum of missing or English-copy entries across supported target languages. They do not change translation job state.

## Isolated source checks

- PHP lint: 22 PHP files PASS.
- JavaScript syntax: 3 runtime files PASS; 8 workflow tests PASS.
- Curated LibreTranslate terminology: 45 expected translations PASS.
- Arabic and server-language inventory including native Studio render and added six-card/no-mutation render check: PASS, zero failures.
- Locale discovery timeout and separate translation request budget: PASS.
- Customer/operator release boundary: 3 tests PASS.
- English language XML parser and git diff --check: PASS.
- Live UI: prior screenshot confirmed six cards and desktop layout. Subsequent contrast/coverage correction passed PHP and served-stylesheet HTTP checks, but a new authenticated screenshot is pending.

## Remaining release gates

- NOT TESTED: fresh native UNA Studio installation of this new dashboard candidate.
- NOT TESTED: native Studio upgrade from installed 0.1.9. The RC4 updater is 0.1.8-to-0.1.9 and is NOT a new same-version updater.
- NOT TESTED: customer Downloaded info popup, cross-theme/mobile screen, broad native-speaker translation-quality acceptance.
- RC4 BUILD.json and docs/RELEASE.md are historical records, not provenance for a new release. Update only after a future release build and frozen hashes.
- GitHub push, tag, release and asset publication require separate explicit approval.
