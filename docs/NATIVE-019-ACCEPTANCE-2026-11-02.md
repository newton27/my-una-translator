# Translator 0.1.9 to 0.1.12 — preparation and acceptance record

Status: **UNPUBLISHED CANDIDATE; native acceptance NOT TESTED; release BLOCKED.**

## Original baseline and missing package

The original `v0.1.9-rc.4` Git tree is `fd378dd8ee995aa65d6e445c85a264ac137aa30a`. Its BUILD.json identifies runtime checkpoint `723e0d9ce05a8ef91e32cb6831a66f8355ffb86f`. Its module manifest declares version `0.1.9`, module URI `my_una_translator`, directory `newton/my_una_translator/`, and the historical `15.0.0-A1` compatibility value.

That baseline contains 28 runtime files and four module tables: settings, jobs, items and languages. Settings hold the provider/API-key fields; items have no `manually_edited` column. Native installation registers a Studio page, widget and placement using `template/images/icons/std-icon.svg`. There is no separate customer-key registry in this module schema; service-side key issuance must be treated as an external, read-only preservation boundary.

GitHub release metadata lists `my-una-translator-0.1.9-rc.4.zip` as 78,818 bytes with digest `171f0d328e40cf91275e0ae7d3b60eb4ad518bfba59fe6dce1c1575e3e1993c0`. These are **release-metadata values, not a newly downloaded and verified baseline ZIP**. Before fixture installation, download that exact asset and match the digest. Do not manufacture a baseline by changing `sys_modules.version`.

The inspected reporting branch at `ed3c314a68149eaf1d5799dc92a6479252505c76` provided only 0.1.9→0.1.10, 0.1.10→0.1.11 and 0.1.11→0.1.12 updaters. The first still declared the historical compatibility value. No direct 0.1.9→0.1.12 updater existed in that tree or the published release inventory.

## New direct candidate

The new `updates/update_0.1.9_0.1.12/` candidate declares exact native versions, UNA `15.0.x`, and the normal module path. It adds `manually_edited` only when absent, then always executes the idempotent support-receipt schema step, including after an interrupted migration. It does not run fresh-install SQL against existing customer tables. Native SQL failure is returned before runtime files can be copied.

Its 35-file source payload is byte-identical to the existing reviewed 0.1.12 runtime in the inspected source snapshot. It includes the current uninstall cleanup, full language source and native Studio icon. The update language delta adds only six keys; it does not update or delete existing localization keys. It does not recreate Studio registration. Existing update directories and published assets are preserved.

| Artifact | Value |
| --- | --- |
| Filename | `translator-0.1.9-to-0.1.12-candidate.zip` |
| Size | 66,571 bytes |
| File count | 40 |
| SHA-256 | `53c4ae72bc71692396443f751de72b62a05f651b58c598698f2a5ec059c50a0a` |
| Archive root | `my_una_translator/updates/update_0.1.9_0.1.12/` |
| Manual staging target for future native test | `modules/newton/my_una_translator/updates/update_0.1.9_0.1.12/` |
| Reproduce | `python3 scripts/build_native_019_candidate.py` |

This layout requires staging the inner updater directory. Automatic Marketplace delivery/unarchive has **not** been tested. Do not use the package as a client release or install it on an existing client site.

## Actual checks performed

| Gate | Status | Evidence / next action |
| --- | --- | --- |
| Tickets CI | PASS | Re-inspected run 41366816640: all five jobs succeeded, including strict Composer validation/install on PHP 8.2–8.4, MariaDB regressions and package integrity. |
| Original 0.1.9 structure | PASS — source inspection | Original tagged manifest, SQL and runtime tree inspected. |
| Direct package preparation | PASS — artifact checks only | ZIP integrity, exact 35-file payload equality, complete cleanup/icon, native versions/paths/action order, additive migration-byte and six-key language-delta checks. |
| Python package checks | PASS | `python3 -m unittest discover -s tests -p 'test_*.py'`: 14 tests passed; three new package checks failed first while the artifact was absent. The tests build their own temporary ZIP and require no pre-existing sibling artifact. |
| Repeat build | PASS | Two deterministic builds produced identical bytes and the digest above. |
| Existing source preservation | PASS | 114 locally materialized pre-existing files verified against original GitHub blob hashes; no existing runtime or updater was edited. |
| Independent source/package review | PASS — draft scope only | Reviewer verified package hash/payload and migration override against UNA 15.0.0-RC3. A clean-checkout test dependency was reproduced, fixed with temporary builds, and all 14 Python tests passed again. PHP/native gates remain outside this result. |
| PHP syntax / PHP regression execution for this candidate | NOT TESTED | PHP is unavailable in the current local runtime. Run the full `bash tests/run.sh` and the new migration's runtime tests before acceptance. |
| Genuine 0.1.9 UNA fixture | NOT TESTED | Verify exact released ZIP and install through native Studio in an isolated UNA 15 fixture. |
| Fresh full backup / rollback checkpoint | BLOCKED | No new test-site backup or rollback checkpoint was created in this task. Historical checkpoints are not substituted. |
| Native Studio 0.1.9→0.1.12 | NOT TESTED | No updater was applied. |
| Settings, keys and complete database preservation | NOT TESTED | Compare full before/after records, encrypted key bytes, localization and module ID/enabled state after native execution. |
| Working translation functions | NOT TESTED | Perform controlled provider/previews/apply/rollback acceptance with approved fixture credentials and test-only keys. |
| Integrated reporting compatibility / disabled rollout | NOT TESTED | Compare with central Tickets; no report was submitted and no reporting setting was changed. The reviewed runtime exposes Contact support when the receipt table exists; this is not a default-off runtime feature flag. The fixture must keep external reporting disabled/isolated before this package is applied. |
| Native uninstall cleanup / registration / navigation / icons | NOT TESTED | Current cleanup and icon bytes are packaged; actual Studio behavior needs a disposable post-update snapshot and restore. |
| Final review / publication | BLOCKED | Native evidence, PHP runtime validation and explicit publication approval remain required. |

## Access and operational boundary

The authorized test-site URL is gayjax.life. Chrome reached its Studio launcher URL, but inspection and interaction are blocked because another extension UI is open. SSH public-key authentication from the connected iMac to `root@server.michaelandbrian.com` was rejected. No server command succeeded, and the isolation, baseline and backup state of any proposed fixture remain unverified.

No site data, credentials, provider settings, service-side keys, module registration or reporting rollout was modified. No translation request, report submission, native update or uninstall was executed. Both reporting PRs remain draft. No main-branch merge, tag, GitHub release, Marketplace publication or certified asset replacement is included.

## Required native evidence on resumption

1. Prove the fixture is isolated, running UNA 15, and has a genuine 0.1.9 install with original package provenance. Record the exact baseline ZIP digest and any compatibility adjustment separately.
2. Create and verify a complete file/database backup. Record its actual path, timestamp, size, digest and tested restoration method; snapshot complete settings/key ciphertext, all four module tables, affected localization/registration rows and core file hashes without exposing secrets in public evidence.
3. Keep reporting externally disabled/isolated. Stage the exact candidate inner directory with site-account ownership, then apply through authenticated Studio Apps → Downloaded → Updates. Preserve the native result and any checksum/version protection state; never bypass a native rejection silently.
4. Compare all preserved data and registration, verify 0.1.12 and the new column/table, exact payload hashes, translation functions, module page/navigation/launcher and icons. Test reporting against an isolated intake without client rollout or real mail.
5. Snapshot the post-update fixture, run native uninstall there, verify complete module-owned cleanup while site translations and registered languages survive, and restore the accepted fixture state. Record all actual results and unresolved gates.
