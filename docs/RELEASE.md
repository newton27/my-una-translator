# My UNA Translator 0.1.9 RC 3

Release tag: `v0.1.9-rc.3`. Native manifest: `0.1.9`. Full module package: `my-una-translator-0.1.9-rc.3.zip`, with a separate SHA-256 file.

## Source identity

The authoritative runtime source checkpoint is `dbde948ff289f9716830c4f486a2c44bd062344f`, tree `579608725cd48f54922a12aa47a09eecb7294a96`. It reconciles the workflow branch `c2706596cef8eb6cf8585c3bd0ca1ece040377c7` with published RC 2 source `60a2b201b9fdcf54071ef7276ad58148ea275a6f`.

The release tag points to the following provenance-only commit, which changes BUILD.json and adds this record. Its runtime files are identical to the source checkpoint. The ZIP contains that tagged tree under the single root `my_una_translator/`, excluding only the repository's .gitignore. Git metadata, generated Python caches, credentials and live database exports are excluded. Earlier RC 1 and RC 2 assets remain unchanged.

## Changes

- Discover installed LibreTranslate destinations reachable from English, search native/English names and codes, and select one to five destinations per job. The checked inventory offers 49 destinations.
- Guide webmasters through Choose, Generate and review, then Apply. Saved jobs are selected explicitly; their available actions follow the saved state. Planning notices and historical errors remain inline.
- Show UNA site-language availability separately from translation targets and the site default. Removing job history preserves native registration and applied translations. Enabled native languages remain available in the visitor selector.
- Preserve RC 2 Arabic and customer-key functionality. Repair Studio list requests with administrator identity, asynchronous issuance form handling and writable paths in the key-manager service example.
- Preserve configured provider settings, encrypted credentials, jobs and reviewed previews.

## Verification gates

| Gate | Result | Evidence and scope |
| --- | --- | --- |
| Source reconciliation | PASS | All 53 checkpoint files match their GitHub blob hashes; RC 2 source is a merge parent. |
| PHP and asset syntax | PASS | PHP 8.3.6 lint: 23 files. Four JavaScript assets, JSON evidence, English XML and SVG icon parse. Manifest, vendor and package root checked. |
| Server-language behavior | PASS | 12 isolated fixture checks plus Arabic provider routing; includes outage, aliases, RTL, native language visibility and applied-job history preservation. |
| Request timeouts | PASS | Real loopback discovery stops at five seconds; a six-second translation succeeds within its separate request budget. |
| Workflow and key-console behavior | PASS | Eight workflow tests and the asynchronous issuance regression pass. |
| Key manager | PASS | 13 tests pass, including the exact Studio list payload, actor validation, independent issuance/rotation/revocation and wrong-peer rejection. Service example parses and grants its configured socket/database paths. These are isolated tests, not live service startup. |
| Curated terminology | PASS | 45 exact local outputs pass through the provider path. These do not certify model output in 49 languages. |
| Native fresh installation / version update | NOT TESTED | The workflow was checked as a file repair on an existing 0.1.9 site. No fresh native installation or Studio version update was performed for this candidate. No 0.1.8-to-0.1.9 updater is included. |
| General linguistic quality | OPEN | Wider human review remains outstanding. Historical sample records are separate from curated regression checks. |
| Marketplace Info page and media | NOT TESTED | Icon and module metadata are present; native Info-page rendering and Marketplace submission are outside this candidate's verified scope. |

ZIP integrity, member inventory, manifest version, source bytes and SHA-256 are verified when freezing and again against the published download. The published checksum file is the authority for the immutable release asset.

This is a prerelease for testing. It is not stable-release, native-update or Marketplace certification.
