# Preserved Translator runtime fixes — November 4, 2026

The prior unpublished0.1.12 package omitted reviewed runtime changes present on the Duvallocals0.1.9 installation. This corrected candidate retains the existing preview editor, stale-save/protected-content checks, reporting compatibility, additive migrations and native update routes while carrying those changes forward. The target remains unpublished0.1.12. All earlier ZIPs and release assets retain their own identities.

## Provenance and preservation

The Engine, LibreProvider and translator.js installed blobs match introducing commit `cfdc5b89d724b0ceeb8a6878580a7f8243d3b3a2`, preserved on `checkpoint/libre-routing-repair-20261008`. That commit records the routing/retry checkpoint and its limitations. The installed Text blob is authenticated Git object `4f42d5cf4476f2192ae571a0c76aad2a9198b93b`; an introducing commit was not identified in current reachable refs. Its complete change from the published Text file is the leading percentage/parenthesis boundary rule.

| Installed file | Reviewed installed Git blob | Corrected target behavior |
| --- | --- | --- |
| Engine | 7c88611884de6e60785c4def3735c9b0c0ac88ac | Merge its rate-limit catch into the current engine; pending rows and prior spending survive a first-response429. Current preview editing and other0.1.12 methods remain. |
| LibreProvider | 5a27b6f5d245acce8be3aaace5935c79d5b48e4e | Exact installed file bytes retained, including Retry-After parsing/header capture and correct service routing. |
| Text | 4f42d5cf4476f2192ae571a0c76aad2a9198b93b | Exact installed file bytes retained; leading percent/parenthesis separators stay outside model prose. |
| translator.js | be614f446f08adf16127b124f0b1eb7619283a63 | Merge its bounded countdown and pause/cancel handling into the current preview-editor/reporting UI. |

The four corrected runtime files are mirrored byte-for-byte in both `update_0.1.9_0.1.12/source/` and `update_0.1.11_0.1.12/source/`. The native icon, module settings, provider configuration, database migrations and reporting-disabled boundary are unchanged. Historical0.1.9→0.1.10 and0.1.10→0.1.11 source payloads are unchanged.

## Native guard and acceptance boundary

No hashCheck override, reviewed-baseline recognizer, threshold adjustment or tracking reset is added. Both native updater installers and manifests are byte-identical to the prior candidate. Exact Git identities are recorded in `PRESERVED-RETRY-0.1.12-PACKAGES.json`. Native core remains authoritative for file-change checks. A pre-update drift ratio is not itself a native rejection; actual threshold/force controls belong to the observed site evidence.

These corrected ZIPs have new hashes and require their own native0.1.9/0.1.11 acceptance. Earlier native acceptance of older payload bytes is retained as historical evidence only. No site action, version manipulation, Marketplace change, tag or release mutation is part of this source correction. Client integrated reporting remains disabled.

## Tests and known limitation

Imported route regressions plus new PHP tests cover first-response429 recovery, identical unprocessed-fragment retry, unchanged settings/saved previews/Polyglot, non429 rejection, Retry-After bounds, a real loopback cURL429 response and leading text separators. Browser tests exercise actual countdown waiting, pause before another batch and the300-second delay cap. New behavior regressions were observed failing before correction. Existing preview editing, reporting, migrations and package suites are retained. CI runs the complete PHP/Node/Python suite and syntax checks, then builds distinct explicit-root transport artifacts.

The imported later429 limitation is still open: LibreProvider may issue multiple POST chunks in one batch. If a later chunk is rate limited, the whole batch is requeued, earlier successful requests can be repeated and their spending is omitted from restored accounting. Restrictive quotas can prevent completion. Persisted partial-fragment progress/accounting is not implemented or certified by this correction. The browser has no fixed attempt cap; the legacy unreachable429 error-map wording must not be read as such a cap. Tests certify the first-response429 boundary only.

The builder's default output is a separate `packages/translator-preserved-20261104/` directory, with `-preserved.zip` names for corrected0.1.12 artifacts. Existing different archives are rejected instead of overwritten. Source SHA, per-file hashes, routes and SHA256SUMS are included in the CI artifact. The frozen file identities and exact source-file mapping are recorded in the adjacent JSON manifest.
