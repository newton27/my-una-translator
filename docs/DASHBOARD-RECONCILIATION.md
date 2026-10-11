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

- PASS: 2026-10-11 native Studio fresh install on manofteal.com from the exact 38-file smoke-test ZIP; module installed and enabled, dashboard and controls visually accepted.
- NOT TESTED: native Studio upgrade from installed 0.1.9. The RC4 updater is 0.1.8-to-0.1.9 and is NOT a new same-version updater.
- PASS: Studio Downloaded info popup title, metadata and screenshot gallery rendered on manofteal.com. NOT TESTED: cross-theme/mobile appearance or broad native-speaker translation quality.
- RC4 BUILD.json and docs/RELEASE.md are historical records, not provenance for a new release. Update only after a future release build and frozen hashes.
- GitHub push, tag, release and asset publication require separate explicit approval.

## Smoke-package boundary verification (not for distribution)

- Smoke installer candidate on the server: /root/my-una-translator-dashboard-smoke-20261011/my-una-translator-dashboard-smoke-20261011.zip
- SHA-256: b42ae3a33f744796f95287417e0e86dd206aec1dd4dfd5a5dfc089394712ebbd
- Installer ZIP contains exactly 38 files under a single my_una_translator root, including its UNA manifest, installer and Studio icon.
- The originally published RC4 ZIP had 45 files, including seven fixture/test scripts. The isolated smoke test ZIP excludes that entire tests folder because installed runtime has no dependency on it. The tests remain in the source Git branch.
- Smoke ZIP integrity and the matching .sha256 check PASS. No issuer/operator files, Git files, backup files, or tests are included.
- The old RC4 BUILD.json and RELEASE.md remain inside the smoke package as historical metadata: THIS IS NOT A PUBLISHABLE RELEASE OR A VALIDATED UPDATER.
- A future released package requires version/release metadata, independent native Studio installation and updater validation as applicable, and verified final asset hashes.


## Native Studio fresh installation - Man of Teal, 2026-10-11

- Approved scope: fresh-install only when absent; protect the site with backups; inspect launcher, six cards, status popup and workflow; do not configure providers, issue API keys, start jobs or publish release assets.
- Preconditions: no directory modules/newton/my_una_translator, no sys_modules entry and no Translator-specific tables on manofteal.com.
- Storage consideration: public_html is approximately 23 GiB, including 17 GiB of other modules and 4.2 GiB of storage. To avoid exhausting the 35 GiB of remaining disk space, the preinstall checkpoint covers the complete MariaDB site database, UNA inc, studio, template, existing modules/newton, .htaccess and index.php, but deliberately excludes large unrelated media and other module trees. NOT A FULL FILESYSTEM BACKUP.
- Root-only backup directory: /var/backups/manofteal-translator-preinstall-20261011-1740
- database.sql.gz SHA-256: 183aa337fafc0470f030d0a088036fdcb75d02f44406c8976f5523a259990de0
- una-code-config.tar.gz SHA-256: 6720188ad4e8da3c170a5173390f24312a36c0711d0f02d061811c4272fd460f
- Both files passed checksum checks; the SQL gzip and site code TAR passed format/integrity checks. They were not restore-tested. Existing media were not modified.
- Tested customer ZIP SHA-256: b42ae3a33f744796f95287417e0e86dd206aec1dd4dfd5a5dfc089394712ebbd. Source paths were preflight-checked and staged in modules/newton/ as manofteal without overwriting another module.
- The first native backend command attempt without a logged-in admin session correctly returned authorization code 102. It did not register the module. The subsequent authenticated UNA Studio > Apps > Downloaded > My UNA Translator > Install action completed and Studio displayed Installed.
- Database verified: one enabled sys_modules record, version 0.1.9, vendor My UNA World, module path newton/my_una_translator/; one Studio page and one Studio widget; 155 native language keys; four module tables and one settings row.
- Untouched settings: provider defaults to DeepL, provider URL and both API keys blank; zero jobs, zero job items, zero native Translator language registrations after UI checks.
- Package-to-installed file hashes: all 38 files matched exactly; 18 installed PHP files and three JavaScript runtime files passed syntax checks.
- Authenticated Chrome browser: icon/launcher and Studio dashboard display, six pale-color cards render correctly in a three-column desktop layout. The provider indicates Setup needed; one site language English and no translation jobs are shown.
- Workflow: step 1 language checkboxes/search/plan present, step 2 job selector and refresh present, step 3 apply control remains disabled until preview. Site-language area shows English and a link to Polyglot. No create/generate/apply/pause/resume actions were submitted.
- The read-only Show translation status popup opened and the Hide popup action worked. Minor visual issue observed: when no job exists, it displays No job selected while the status body says Working...; this is not evidence of an actual translation job and needs a separate UI fix before stable release.
- Studio Downloaded info popup opened, displayed title/vendor/version/description and loaded marketplace screenshot gallery after a brief delay.
- Website root, authenticated Studio page, CSS, and runtime Studio SVG icon all returned HTTP 200. No fatal screen observed.
- The module remains installed and enabled for test purposes. User's provider settings and translation jobs were not changed. No production upgrade or customer module release was performed.
- Release gates remaining: validate the supported prior-to-target native updater before offering upgrades, resolve the empty-job status label, determine version/tag/manifest and refreshed release metadata, and do mobile/theme and broader linguistic review as necessary.


## Native UNA 0.1.9 to 0.1.10 upgrade - Man of Teal, 2026-10-11

- Candidate source checkpoint: 45d508b5719848d26385dbacab522356b524c85f; provenance-only checkpoint before native testing: 8e3211f9a1ac5cb14eab6ab04f436149e56b7278.
- Full candidate originally had 38 module files; native updater was a separate six-file archive, not a full reinstall. The updater's tested SHA-256 was 44835d7cd3f63da5e1271cc337f1c5896746cc52a65413aacd8d7185b49f7df4.
- Immediately before testing, Translator on Man of Teal was installed and enabled at 0.1.9 with 31 tracked files and zero drift.
- One user-created saved job (#3) was paused with 14,763 items and 1,677 spent characters. No job was started, resumed, paused, applied or deleted during this upgrade test.
- Backup checkpoint: /var/backups/manofteal-translator-preupgrade-20261011-1819 (site MariaDB SQL gzip and the installed module code TAR, both checksum-verified, not restore-tested). Prior fresh-install checkpoint is separately recorded above.
- Before applying the update, SHA-256 fingerprints of each ordered full data table (settings, jobs, items, languages) and the job status were saved in a root-only fixture: /root/my-una-translator-0.1.10-rc1-candidate-20261011/preupgrade-fingerprints.json.
- The exact six-file updater was staged under modules/newton/my_una_translator/updates/update_0.1.9_0.1.10/. UNA's getUpdates() returned version_from 0.1.9, version_to 0.1.10 and module version 0.1.9.
- The Studio Apps > Updates screen was empty because the local update was not shown in the Marketplace update listing. An authenticated administrator executed UNA's existing native Studio update AJAX action for the single staged updater, returning code 0. This was not a manual file deployment, force upgrade, checksum rebaseline or direct SQL change.
- Post-update: sys_modules version 0.1.10, enabled=1; all four updater runtime files byte-matched the candidate source. All 31 UNA tracked files matched their updated expected hashes.
- SHA-256 of ALL ordered rows in provider settings, saved jobs, 14,763 items, and native language registrations matched exactly before and after. Root-only check output: /root/my-una-translator-0.1.10-rc1-candidate-20261011/postupgrade-fingerprints.json.
- Authenticated browser displayed the six graphical cards with job #3 paused, and the Show translation status popup displayed No job selected (rather than Working...) when nothing was selected; HTTP 200 for Studio and cache-busted 0.1.10 JS.
- UNA removed updater contents but left empty directories; verified empty directories were removed. Existing data tables and user settings were not altered.
- Remaining: fresh install of the final 0.1.10 package NOT TESTED; end-user GUI update discovery/activation not available for the manually staged package; Marketplace 0.1.10 media/listing not updated; broad translation linguistic quality remains outside the scope.
