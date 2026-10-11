# My UNA Translator 0.1.10 RC 1 - unpublished release candidate

Native UNA manifest: 0.1.10. Proposed tag: v0.1.10-rc.1 (NOT CREATED).
Target: UNA 15.0.0-RC2. Vendor: My UNA World.

## Scope and provenance

- Base: published v0.1.9-rc.4 release and GitHub main at fd378dd8ee995aa65d6e445c85a264ac137aa30a.
- The separately reconciled six-card graphical dashboard is carried forward from the tested MyUNAWorld UI. The rendered cards use read-only snapshot data.
- The progress popup now correctly shows No job selected on a fresh site, while saved jobs and active translation work keep their actual status.
- New native manifest version 0.1.10 permits a real native update from 0.1.9. The updater stages ONLY classes/MyUnaTranslatorStudioPage.php, template/css/translator.css, template/js/translator.js, and install/config.php, with no SQL or language migration.
- Unchanged RC4 provider, translation engine, Arabic support, rate-limit behavior, job persistence, language records, operator-issuer exclusion and protected-markup validation are preserved.
- No production provider keys, live translation jobs, or server-operator issuer scripts are distributed.

## Candidate packages

- my-una-translator-0.1.10-rc.1.zip - full native Studio install rooted at my_una_translator/.
- my-una-translator-update-0.1.9-to-0.1.10-rc.1.zip - updater directory rooted at my_una_translator/updates/update_0.1.9_0.1.10/.
- Candidate source and assets are LOCAL ONLY. GitHub tag, release, and asset publishing are NOT AUTHORIZED by the current approval.
- Package output, exact hashes, and frozen source commit must be recorded outside the ZIP after the final builds.

## Verification checkpoint

- Native full install of the prior dashboard prototype (manifest 0.1.9) passed on manofteal.com through authenticated UNA Studio.
- 38 prototype files matched exactly after install; 18 PHP files and runtime JavaScript lint passed. The Studio launcher, graphical cards, workflow controls, and Downloaded Info popup displayed correctly.
- Man of Teal's empty Translator instance preserved unconfigured provider settings, empty keys and zero translation jobs. Preinstall database + UNA core/config backup was verified; unrelated large media were not backed up.
- The 0.1.10 status-popup regression suite verifies empty, ready, and active progress states without provider requests.
- The native 0.1.9 to 0.1.10 upgrade must be tested independently, including checksum preflight and preservation of existing settings and data.
- Fresh native installation of 0.1.10 itself is NOT TESTED at this checkpoint.
- General LibreTranslate linguistic quality still requires broader review. The 45 curated-string fixtures are NOT certification of all model outputs.
- Mobile, alternate theme, Marketplace media and info screens for final RC1: further acceptance required where applicable.

## Release protections

Do not present an existing 0.1.9 manual copy as a verified updater. No automatic checksum re-baselining or force-update setting is permitted to bypass drift. Do not overwrite frozen RC4 tag or ZIP. New GitHub publication requires separate explicit approval.

Historical RC4 release documents and files are preserved in git history. The Studio app store Marketplace listing is not changed by these source preparations.
