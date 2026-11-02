# Translator 0.1.9 → 0.1.12 native acceptance — November 2, 2026

**Status: BLOCKED at final verification. The Studio update action was submitted; native acceptance is not yet complete.** Reporting rollout and publication remain disabled/unapproved.

## Exact source and package

- Reviewed reporting payload: `cb08b5224a6f555cfcbb64c541cd087727e726bd`; inspected payload snapshot `ed3c314a68149eaf1d5799dc92a6479252505c76`.
- Direct native updater introduced at `66e81d05908accc49b67ce5656d782173239c9f3`.
- Additional migration tests: `698f1fa1a6896d47f13b3a7dcacae6e89cd010db`; see [local validation](REPORTING-LOCAL-VALIDATION-2026-11-02.md).
- Build-tool hardening and explicit checksum records: `1f8c2144d473bd106146347a936ae83624734f9d`. This does not change native ZIP bytes.

| Artifact | Identity |
| --- | --- |
| Direct candidate | `translator-0.1.9-to-0.1.12-candidate.zip` |
| Size / entries | 66,571 bytes / 40 files |
| SHA-256 | `53c4ae72bc71692396443f751de72b62a05f651b58c598698f2a5ec059c50a0a` |
| Archive root | `my_una_translator/updates/update_0.1.9_0.1.12/` |
| Staging target | `modules/newton/my_una_translator/updates/update_0.1.9_0.1.12/` |

The package contains 35 exact reviewed runtime files, current uninstall SQL, the native Studio icon and six additive language keys. It conditionally adds the preview edit column and always executes the idempotent reporting-table migration, preserving retry after a partial SQL failure. Existing updater directories and published packages are unchanged. Automatic Marketplace download/unarchive is not certified.

## Genuine original baseline — PASS

The original `v0.1.9-rc.4` tree is `fd378dd8ee995aa65d6e445c85a264ac137aa30a`; BUILD.json identifies runtime checkpoint `723e0d9ce05a8ef91e32cb6831a66f8355ffb86f`. Original installation uses `newton/my_una_translator/`, four module tables and 28 runtime files. Provider credentials reside in the settings table; operator key issuance is external to this module.

The exact published `my-una-translator-0.1.9-rc.4.zip` was downloaded and independently matched to SHA-256 `171f0d328e40cf91275e0ae7d3b60eb4ad518bfba59fe6dce1c1575e3e1993c0` (78,818 bytes).

A separate UNA fixture was created from the authorized gayjax.life test installation, with its own database and operator/loopback access restriction. Copied My UNA World lifecycle hooks were disabled only in the clone to avoid touching origin backup records. The cloned 0.1.11 module was uninstalled through Studio; the original 0.1.9 package was then staged and installed through Studio Apps → Downloaded. All 45 archived baseline files subsequently matched installed bytes. No compatibility patch or `sys_modules.version` edit was used. Native module ID is 39.

The origin Translator was observed at ID 32 / 0.1.11 / enabled, with zero jobs/items/languages and no provider credentials. No origin module or customer data was modified by this work.

## Nonempty preservation fixtures and rollback — PASS before update

The 0.1.9 fixture has:

- One settings row with non-default batch size 7 and character ceiling 1,234,567, plus two encrypted synthetic provider values.
- Two jobs: one generated/applied through Studio, and one constructed ready preview for preservation testing.
- Four saved items, four registered UNA language keys, and one module-owned Spanish language record.
- A successful authenticated round trip to a temporary loopback-only LibreTranslate-compatible fixture. Studio generated and applied Spanish text, preserving `<b>…</b>` markup and `{0}`. This is not live translation-service or linguistic-quality certification.

Before applying the updater, complete module/localization/registration rows and 1,187 core-file digests were captured. Private evidence includes `before-019.json`, `rollback-checkpoint.json`, `baseline-0.1.9-database.sql` and `baseline-0.1.9-files.tar.gz`. The checkpoint records actual paths, sizes and SHA-256 values; the compressed file backup passed its integrity check. A fresh origin database dump was also retained, and existing full backup ID 14 was independently checked against its stored digest and ZIP integrity. Restoration has not yet been tested.

The fixture's request guard returned the expected disabled-reporting JSON and HTTP 403 for `report_send`, before any report could be sent. The inherited reporting runtime exposes Contact support when its receipt table exists, so this environment hold must remain in place; it is not a default-off module feature flag.

## Native Studio action — submitted; final verification BLOCKED

After the checkpoint, the exact 40-file updater was staged with site-account ownership. Studio Apps → Downloaded displayed a local **Updates** section with **My UNA Translator 0.1.9 - 0.1.12** and its native Install control. The observed control called:

`oBxDolStudioStore.update('newton/my_una_translator/updates/update_0.1.9_0.1.12/', this)`

That control was clicked in the isolated fixture. The local update card disappeared. **Registered target version, SQL/file completion and data preservation have not yet been independently observed after this action.** Chrome then blocked inspection because another extension UI was open. No native-success code is claimed from the vanished card alone.

The separate online Marketplace Updates page reports an expired access token. It was not used to submit this local updater; this remains an online delivery/authorization limitation.

## Current gate table

| Gate | Status | Actual evidence / remaining work |
| --- | --- | --- |
| Tickets CI | PASS | Run 41366816640: all five jobs successful, including strict Composer validation/install, PHP 8.2–8.4, MariaDB and package checks. |
| Native original 0.1.9 baseline | PASS | Exact published ZIP, native Studio installation, genuine registration, 45/45 original files. |
| Pre-update data and rollback checkpoint | PASS | Nonempty fixture, complete private snapshots, SQL/file archives, digests and compression verification. Restore still pending. |
| Package integrity / repeat build | PASS | 40 files, 35 exact runtime copies, native paths/versions, additive migrations/languages and identical repeat-build digest. |
| PHP / Node checks | PASS at recorded checkpoints | Server PHP 8.3.6 suite and 21 Node tests passed. The supplement records eight direct migration boundary/failure/retry checks; these are not native acceptance. |
| Current Python checks | PASS | 18 tests, including outside-path, symlink escape and non-ZIP output rejection; checksum records verified against archive bytes. |
| Current SonarCloud | PASS | Check for 1f8c214 completed successfully: zero new issues, accepted issues and security hotspots. |
| Native 0.1.9→0.1.12 completion | BLOCKED | Studio action submitted; independently verify target registration, native SQL and exact installed payload. |
| Complete settings/key/row preservation | BLOCKED | Run prepared comparison against the pre-update snapshot before changing any fixture data. |
| Post-update translation functions | NOT TESTED | Verify saved preview use, authenticated provider generation, Apply and rollback in the clone. |
| Reporting compatibility / hold | BLOCKED | Pre-update HTTP hold verified; recheck after update and compare shared contract with central Tickets without live submission. |
| Registration / navigation / rendered icons after update | NOT TESTED | Compare native registrations and inspect actual Studio launcher/sidebar. |
| Native target uninstall / restore | NOT TESTED | Preserve accepted snapshot, uninstall 0.1.12 through Studio, verify five-table/registration cleanup and retained UNA translations/languages, then restore. |
| Publication / wider reporting rollout | BLOCKED | Complete the above, final review and explicit approval. |

## Resume without repeating completed work

Close the blocking Chrome extension panel, then continue in the dedicated isolated fixture tab. The native update action has already been submitted: **do not click it again blindly**.

1. Inspect current registration and run the prepared `verify_native_update.php` comparison against `before-019.json`. It checks all four prior tables, credential decryption, registered translation keys/values, language/Studio registration, 1,187 core hashes, all 35 payload files and the origin module.
2. If those checks pass, test post-update functions and rendered icons while keeping the report guard active.
3. Save a post-update checkpoint, test native uninstall/cleanup and restoration, and record actual results.
4. Stop the temporary loopback provider and remove the disposable clone/database after evidence is secured, retaining an organized rollback/evidence checkpoint.

Both PRs remain draft. No main-branch merge, tag, release, Marketplace publication or certified asset replacement is included.
