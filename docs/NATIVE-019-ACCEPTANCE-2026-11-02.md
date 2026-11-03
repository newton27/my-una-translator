# Translator 0.1.9 → 0.1.12 native acceptance — November 2, 2026

**Native acceptance: PASS for the exact candidate below. Reporting enablement and publication remain BLOCKED.**

## Exact baseline and updater

No direct 0.1.9→0.1.12 updater existed initially; only sequential updaters were present. A correctly versioned direct native candidate was prepared without changing published packages or the existing updater directories.

| Item | Exact identity |
| --- | --- |
| Original package | `my-una-translator-0.1.9-rc.4.zip`, 78,818 bytes |
| Original SHA-256 | `171f0d328e40cf91275e0ae7d3b60eb4ad518bfba59fe6dce1c1575e3e1993c0` |
| Original release tree / BUILD runtime | `fd378dd8ee995aa65d6e445c85a264ac137aa30a` / `723e0d9ce05a8ef91e32cb6831a66f8355ffb86f` |
| Direct updater source | `66e81d05908accc49b67ce5656d782173239c9f3` |
| Reviewed runtime payload | `cb08b5224a6f555cfcbb64c541cd087727e726bd`, inspected snapshot `ed3c314a68149eaf1d5799dc92a6479252505c76` |
| Build hardening | `1f8c2144d473bd106146347a936ae83624734f9d`; no ZIP byte change |
| Candidate ZIP | `translator-0.1.9-to-0.1.12-candidate.zip`, 66,571 bytes, 40 files |
| Candidate SHA-256 | `53c4ae72bc71692396443f751de72b62a05f651b58c598698f2a5ec059c50a0a` |
| Native archive root | `my_una_translator/updates/update_0.1.9_0.1.12/` |
| Staging target | `modules/newton/my_una_translator/updates/update_0.1.9_0.1.12/` |

The updater has version_from 0.1.9, version_to 0.1.12 and UNA 15.0.x compatibility. It carries 35 exact reviewed runtime files, current uninstall SQL, the native icon and six additive language keys. Its conditional preview-column migration preserves replay of the reporting-table migration after a partial failure.

## Authorized isolated installation

The fixture was cloned from gayjax.life into a separate directory and database, restricted to the operator and loopback. Copied My UNA World lifecycle hooks were disabled only in the clone. The cloned 0.1.11 installation was natively uninstalled; the exact original 0.1.9 ZIP was then installed through Studio Apps → Downloaded. Registration was genuinely 0.1.9 / module ID 39, and all 45 original archived files matched. No compatibility patch or database version edit manufactured the baseline.

Environment observed from the running fixture: UNA 15.0.0-RC3, PHP 8.3.6, MariaDB 10.11.14. The original 0.1.9 manifest retained its original compatibility declaration and still installed natively in this fixture.

The nonempty baseline contained one settings row, two jobs, four items, one module language row, four registered UNA keys and seven registered value rows. Batch size was 7 and character ceiling 1,234,567; two encrypted synthetic provider values exercised credential preservation. One job was generated/applied natively on 0.1.9; the second was a constructed saved-preview preservation fixture.

Provider generation used an authenticated loopback-only LibreTranslate-compatible fixture, not a live translation service or a linguistic-quality assessment. Markup and the `{0}` placeholder survived the original 0.1.9 generation/apply case.

## Rollback checkpoint

Private operational evidence is retained under `/var/backups/my-una-translator/native-019-20261102/`. No credentials or database contents are committed here.

| Checkpoint | Bytes | SHA-256 |
| --- | ---: | --- |
| `baseline-0.1.9-database.sql` | 158455094 | `d9b8a5fcecad3e7fa631c82f94d1abda5e2fda17e08e26457fb36f3db67fd73a` |
| `baseline-0.1.9-files.tar.gz` | 12996823443 | `a10ea6446c35243f3aabf626529712a4f90f545e7f4324677eb6040637dc0e1c` |

`before-019.json` contains complete baseline rows, localization/registration and 1,187 core-file digests. `rollback-checkpoint.json` indexes the exact checkpoint paths and digests. The full compressed archive passed integrity validation. A fresh origin database checkpoint and the verified pre-existing full backup ID 14 were also retained.

## Actual native results

The exact candidate was staged with the site account as owner. The local Updates block at the bottom of Studio Apps → Downloaded displayed My UNA Translator 0.1.9 - 0.1.12. Its observed native control was:

`oBxDolStudioStore.update('newton/my_una_translator/updates/update_0.1.9_0.1.12/', this)`

That control was applied once. After the earlier browser interruption cleared, independent database/file comparisons established completion; the vanished update card alone was never used as proof.

| Gate | Actual result |
| --- | --- |
| Native target and preservation comparison | **27/27 PASS** — registration 0.1.12 / ID 39; unchanged complete settings/jobs/items/language rows; old key/value and language registrations preserved; saved credential decrypted unchanged; new column defaults correct; reporting table exists and is empty; six added keys; all 35 payload files exact; native updater directory removed; Studio/navigation registration preserved; all 1,187 core files unchanged; origin module unchanged. |
| Post-update functions | **9/9 PASS** — old saved preview opened, applied and rolled back through Studio; a fresh authenticated job generated and applied its expected Spanish result; original translations and settings still exact; no report receipts; no core changes. Jobs ended as #1 applied, #2 rolled_back and #3 applied. |
| Studio navigation and icon | **PASS** — module page, launcher and dock navigation opened; native SVG loaded completely at 150×150 pixels. |
| Reporting compatibility and hold | **PASS within stated scope** — direct payload and Tickets source share exact contract blob `c170be3c9bcc21bab9e5ca603d9dcb2a62a1bb2a`; report_send returned disabled JSON / HTTP 403 before and after upgrade; receipt table remained empty. Existing isolated reporting regressions passed. No live diagnostic submission or email delivery is certified. |
| Native target uninstall | **12/12 PASS** — Studio launcher Uninstall removed all five module tables, registration, Studio pages/widgets/placements and owned language category/keys; owned footer overrides were reset; existing UNA languages and registered translations remained; no core changes. |
| Checkpoint recovery | **10/10 PASS** — restored the complete cloned database checkpoint plus all 45 byte-verified original module files; genuine 0.1.9 registration, all four prior tables and registered keys/values matched; reporting table absent; core files unchanged. Restored Studio page and icon loaded. Full-site TAR extraction was not exercised. |
| Disposable fixture cleanup | **PASS independently verified** — cloned directory/database/grant removed, temporary provider stopped and its port closed, recovery files retained; origin Translator remains ID 32 / 0.1.11 / enabled. |

The first cleanup helper removed its own loaded cloned application and subsequently emitted a shutdown-autoload error. A separate check without that application bootstrap independently confirmed directory/database/grant absence and the closed provider port. This operational cleanup error is recorded; it was not a native install/update/uninstall failure.

Private result files: `after-native-update.json`, `after-functions.json`, `after-uninstall.json`, `rollback-verification.json` and `cleanup-verification.json`. The four acceptance comparisons contain 58 passing assertions in total. The original installation and customer data were outside mutation scope; no UNA core file was edited.

## Automated checks and remaining release gates

- Tickets' former Composer failure is resolved: [run 41366816640](https://github.com/newton27/my-una-tickets/actions/runs/41366816640) passed all five jobs. The later receipt-bootstrap fix at `9a425c6c0512e261c841dfdef86aae914ac3a455` also passed all five jobs in [run 41547883259](https://github.com/newton27/my-una-tickets/actions/runs/41547883259).
- Translator package repeat-build identity remains unchanged; 18 Python checks pass. The recorded PHP 8.3.6 suite, 21 Node tests and eight adapted migration/retry tests passed; see [local validation](REPORTING-LOCAL-VALIDATION-2026-11-02.md).
- SonarCloud at `1f8c2144d473bd106146347a936ae83624734f9d` passed with zero new issues/hotspots.
- Central Tickets' public request page needed template/language initialization before receipt mail. The narrow fix is tested and deployed independently; SMTP credentials on authenticated port 587 were rejected by a connection-only probe. Actual receipt delivery remains unverified.
- Previously frozen Tickets packages are preserved; the new receipt fix requires deliberate candidate/package reconciliation and acceptance before publication. It is not silently inserted into those artifacts.
- Online Marketplace download/unarchive remains uncertified; the separate online Updates page reported an expired access token. This does not invalidate the tested local native Studio updater path.

Both PRs remain **draft**. Client integrated reporting remains **disabled**. Final integrated service/email acceptance, release review and explicit publication approval are still required. No main merge, tag, GitHub release, Marketplace publication or published/certified asset replacement occurred.
