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
| Tickets Composer validation workflow | PASS | Superseded by successful run 41366816640 on 2026-11-02; preserve certified package identities |
| Translator 0.1.9 upgrade path | NOT TESTED | Validate supported path |
| Publication | NOT TESTED | Final review and explicit approval |

Detailed operational evidence is retained separately. These results do not authorize client enablement or publication. Both PRs remain draft; no tag, release or release asset was changed.

## CI reconciliation — 2026-11-02

GitHub Actions [run 41366816640](https://github.com/newton27/my-una-tickets/actions/runs/41366816640) was re-inspected: all five jobs completed successfully. The PHP 8.2, 8.3 and 8.4 jobs each passed strict Composer validation, lockfile installation, module checks and native-package validation; the MariaDB regression and aggregate build jobs also passed. Tickets candidate commit is `6f811143d793736bceee84a221d8e90a199925dd`; Actions tested merge ref `08d4ee97389c5d885fa3b13a8fe2d3e5c95e8417`. This supersedes the historical Composer failure, without changing earlier certified packages or claiming a new Studio acceptance run.

Translator 0.1.9 native upgrade acceptance remains NOT TESTED. Reporting stays disabled; both PRs remain draft. No merge, tag, release, Marketplace publication or asset replacement is included.
