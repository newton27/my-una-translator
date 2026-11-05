# Native compatibility acceptance — gayjax.life — 2026-10-29

## Scope and exact artifacts

User-designated test site: https://gayjax.life/, UNA 15.0.0-RC3 / PHP 8.3.6. Translator was not previously installed. Rollback checkpoint is My UNA World full backup ID 8, 2026-10-29 05:41:03 UTC, SHA-256 b70e400526aa915ba28829068024a19a901e05fe2e47a9a629449aa86f94a68a, independently inspected.

Runtime/package source: 8f3643467e3e7711bab8488cb3f3d109c2aeca31, before these evidence-only documentation updates. Packages are unpublished acceptance candidates, not released Marketplace downloads. They were constructed from that exact Git tree using BUILD.json exclusions. Server-side reproduction from the same GitHub commit produces identical ZIP hashes.

| Package | SHA-256 |
| --- | --- |
| 0.1.11 full candidate | d5d033dc0a87c569d5aa48ba4bb38116b98a95af03cde9b0fbf27bdae6492b4b |
| 0.1.10-to-0.1.11 updater candidate | 2c610431dc298cfc36cddea8ac747e3951fa075e8cf46d9f09a02b878b337fa6 |
| Published 0.1.10 RC1 full baseline | c554f64a0e080bf959771b7670655dbe09dc0c2ca92b161bd63b6666ee7b283d |

The candidate installer uses my_una_translator/ as its archive root. The updater retains BUILD.json's my_una_translator/updates/update_0.1.10_0.1.11/ archive layout; that inner update directory was manually staged. This tests native Studio execution, not automatic Marketplace download/unarchive.

## Clean Studio installation — PASS

- Staged the exact full candidate into a previously absent module directory, with site-account ownership.
- Installed through authenticated Studio Apps → Downloaded.
- Verified native 0.1.11 registration and the manually_edited field on my_una_translator_items.
- Verified the Studio launcher, then removed this fresh test instance through UNA's native uninstall implementation (result code 0) to prepare an independent updater baseline.

## Native 0.1.10-to-0.1.11 update — PASS

- Downloaded the published 0.1.10 RC1 installer and independently matched its release asset digest.
- Staged into a clean module directory and installed it through Studio. Verified actual 0.1.10 registration and absence of manually_edited. No sys_modules.version edits were used.
- Created one module-owned test job and item, and non-default batch/character-budget settings. These were data-preservation fixtures, not generated translations or Polyglot keys.
- Staged the exact candidate inner update directory and applied it through Studio Apps → Downloaded → Updates.
- Verified registered version 0.1.11, unchanged module ID/enabled state, exact preservation of settings/jobs/items/languages data, and newly added manually_edited=0.
- All eight installed updater payload files match their packaged SHA-256 bytes.
- Removed only the test fixture job/item and reset their test-specific batch/budget changes to fresh module defaults. No translation was applied and no site-language/default-language change was made.
- Updated dashboard opens normally and shows zero jobs and provider setup needed. No API key or paid provider service was configured.

## Boundaries

All 1,186 checked core files (inc/, studio/, root PHP files) retain their original SHA-256 values. No UNA core source or native version/checksum protection setting was changed. The site already had Force modified files ON; checksum-gate OFF acceptance is not claimed.

These results validate the affected native installation/update paths. They do not certify authenticated manual preview editing, live provider generation, Apply/rollback browser behavior, MySQL edit-concurrency behavior or payment/API provisioning. Those remain separate PR #3 feature gates. No release, tag, asset or Marketplace listing was published or replaced.

