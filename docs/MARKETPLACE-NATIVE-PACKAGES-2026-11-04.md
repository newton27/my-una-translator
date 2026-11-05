# Translator native Marketplace transport candidates — November 4, 2026

The original accepted updater ZIPs were staged inside the module before UNA's native Studio update action. The direct 0.1.9 archive starts with a file under `my_una_translator/updates/`; it has no explicit top-level directory member. That archive is preserved with its accepted SHA-256 `53c4ae72bc71692396443f751de72b62a05f651b58c598698f2a5ec059c50a0a`.

`scripts/build_marketplace_packages.py` prepares separate transport containers. The first ZIP member is the explicit native package directory: `my_una_translator/` for a full install, or `update_FROM_TO/` for an updater. Every original file byte is retained. Repeated builds must be identical, and an existing archive with different bytes is rejected. The builder writes SHA-256 sums and a per-file manifest, and includes no tests, documentation or operator-service tools in the module ZIP.

| Artifact | Files | Bytes | SHA-256 |
| --- | ---: | ---: | --- |
| My-UNA-Translator-0.1.12.zip | 35 | 63089 | df1668eea33d824688ba24695c28b392087a031ad114f0e05b66867db899a782 |
| My-UNA-Translator-Update-0.1.9-to-0.1.12.zip | 40 | 66713 | 3fcd372bec9170830c17aa693e90d619e93dc991740a9553a150828d3902bb85 |
| My-UNA-Translator-Update-0.1.10-to-0.1.11.zip | 12 | 34097 | 5d63663a35b5314bca25a157eb377f1d8e86842322fbe9ff359dac09b109c0d3 |
| My-UNA-Translator-Update-0.1.11-to-0.1.12.zip | 32 | 62816 | 9721cd04056c3013be9fb2ee1907dbe86b0cefbf0adba10f151f29eedae5ae53 |

The runtime checkpoint remains `cb08b5224a6f555cfcbb64c541cd087727e726bd`; preparation starts from PR #5 HEAD `21f276872e0658af101c4ffc723c91e77ec7a561`. This work adds build tooling, tests and documentation only. Existing runtime files, updater files and historical release assets are unchanged.

The 0.1.9 direct package retains all 40 accepted files, including all 35 runtime files, current uninstall SQL, six additive language keys and the native Studio icon. Native acceptance of its original container is documented in `NATIVE-019-ACCEPTANCE-2026-11-02.md`. The 0.1.11 updater retains its 32 accepted files unchanged since the runtime checkpoint, as documented in `REPORTING-VALIDATION-2026-10-31.md`. Its existing 0.1.11 baseline supplies the unchanged Studio icon; its exact icon blob `c626f30dcce1c9f2e03d945cf086310fe1cebb4c` also matches the current full/direct packages. The 0.1.10 route uses the recorded native 0.1.10→0.1.11 update followed by 0.1.11→0.1.12.

The new container hashes do not acquire online download/unarchive acceptance from the older manually staged packages. Native transport acceptance on a backed-up site remains required for these exact ZIPs. Fresh installation of the exact 0.1.12 full ZIP remains untested. Client reporting remains disabled; integrated reporting and email delivery remain separate unverified gates. Do not enable reporting as part of packaging or compatibility acceptance.

The branch's `Native Marketplace packages` workflow tests package contracts and uploads the four ZIPs, manifests and checksums as a downloadable CI artifact. It does not publish a release, move a tag, edit Marketplace metadata or deploy a site.
