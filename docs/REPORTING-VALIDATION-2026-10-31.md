# Reporting candidate validation — 2026-10-31

## Source identities
- Tickets updater: e05a5d21b62584cacadc232a038bb70cc06e8358.
- Tickets full installer: 0fdfd40cba0a448632f5f041f0ef3afb342f2170.
- Translator updater: cb08b5224a6f555cfcbb64c541cd087727e726bd.
- The updater corrections include the current uninstall SQL so added reporting tables are removed during native uninstall.

## Package manifest
| File | Files | Bytes | SHA-256 |
| --- | ---: | ---: | --- |
| tickets-0.1.0-to-0.1.1-e05a5d2-candidate.zip | 33 | 45622 | 835f5e88a5599c75af93a4c7afbaf280ccfc56b0e0d46c44f23348c0171a764c |
| tickets-0.1.1-0fdfd40-full-candidate.zip | 39 | 58288 | 4f387cf7c51a15e2977030bb817fd1a6ae3c73e6482a7a4851d86e2e467ea1f4 |
| translator-0.1.11-to-0.1.12-cb08b52-candidate.zip | 32 | 62806 | d5e7ff33f1284079ce9574f02aad95a9ccb1842fe8df9a89dc3c583676f0285a |

Earlier candidate packages are superseded and retain their original byte identities.

## Gate summary
| Gate | Status | Next action |
| --- | --- | --- |
| Tickets native fresh installation | PASS | Preserve package identity |
| Tickets native 0.1.0 to 0.1.1 update and uninstall | PASS | Preserve package identity |
| Translator native 0.1.11 to 0.1.12 update and uninstall | PASS | Preserve package identity |
| Translator client acceptance | PASS | Retain controlled rollout |
| Translator SonarCloud at cb08b52 | PASS | Final review |
| Tickets Composer validation workflow | PASS | Run 41366816640 resolved the old failure; receipt fix 9a425c6 also passed all five jobs in run 41547883259 |
| Translator 0.1.9 upgrade path | PASS | Exact direct candidate applied through Studio; 27 preservation, 9 function, 12 uninstall and 10 checkpoint-recovery assertions passed. See the [native record](NATIVE-019-ACCEPTANCE-2026-11-02.md). |
| Publication | NOT TESTED | Final review and explicit approval |

Detailed operational evidence is retained separately. These results do not authorize client enablement or publication. Both PRs remain draft; no tag, release or release asset was changed.

## 0.1.9 direct updater preparation — 2026-11-02

An additional unpublished direct candidate is prepared in `updates/update_0.1.9_0.1.12/`. Its 40-file ZIP is 66,571 bytes, SHA-256 `53c4ae72bc71692396443f751de72b62a05f651b58c598698f2a5ec059c50a0a`. All 14 Python package tests and repeat-build byte comparison pass. This is additional preparation, not replacement of any artifact listed above. Native acceptance of this exact ZIP subsequently passed as recorded below. Full baseline, gate and access details are recorded in [NATIVE-019-ACCEPTANCE-2026-11-02.md](NATIVE-019-ACCEPTANCE-2026-11-02.md).

## Native fixture progress and current source checks — 2026-11-02

The isolated test fixture now has a genuine, byte-verified 0.1.9 Studio installation, two jobs/four items, four registered test keys, encrypted test provider values and a verified pre-update SQL/file checkpoint. The exact direct candidate was submitted using the local Updates block in Studio Apps → Downloaded. After browser access recovered, target/version/data/function/uninstall/recovery verification passed against the saved nonempty baseline. The four comparison stages passed 58 assertions; the disposable fixture/database/grant/provider were then removed, retaining recovery evidence.

Build hardening at `1f8c2144d473bd106146347a936ae83624734f9d` passed 18 Python tests and SonarCloud with zero new issues/hotspots. The previously flagged `MyUnaTranslatorSecret.php` value was a file digest; the manifest now labels path and SHA-256 separately. Build output paths are confined to the artifact directory, including symlink checks. These changes leave the native ZIP digest `53c4ae72bc71692396443f751de72b62a05f651b58c598698f2a5ec059c50a0a` unchanged. This current source result supplements the historical Sonar checkpoint above.

## Receipt delivery follow-up — 2026-11-02

Tickets commit `9a425c6c0512e261c841dfdef86aae914ac3a455` initializes UNA's template/language helpers before the public ticket request can send mail. A regression was observed failing before the fix and passing after it; the actual UNA preflight was reproduced without mail delivery. All five jobs in [run 41547883259](https://github.com/newton27/my-una-tickets/actions/runs/41547883259) passed. The narrow live fix preserves the existing page styling. No failed receipt was resent, SMTP settings were not changed, and actual inbox delivery is unverified. A connection-only authenticated port 587 probe rejected the saved credentials. The historical Tickets packages above are unchanged and do not acquire this fix retroactively; package reconciliation and acceptance remain required before publication.

Reporting rollout and publication remain disabled/unapproved. The exact Translator 0.1.9 native package passed; that result does not certify live integrated reporting or email.
