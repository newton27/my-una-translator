# Translator reconciled r4 evidence

Reviewed, unpublished candidate for My UNA Translator 0.1.12. Native fresh-install/update acceptance of these new bytes remains NOT TESTED.

Canonical source: [3554da2e84c134e8ccd4ee1a055113a1c5725c36](https://github.com/newton27/my-una-translator/commit/3554da2e84c134e8ccd4ee1a055113a1c5725c36). [Draft PR 11](https://github.com/newton27/my-una-translator/pull/11) targets the separate reconciliation branch. The source tree equals the independently reviewed local runtime plus its configuration-only workflow correction. This evidence branch inherits that public source.

Both [push CI](https://github.com/newton27/my-una-translator/actions/runs/42117299305) and [pull-request CI](https://github.com/newton27/my-una-translator/actions/runs/42117385448) passed the complete suite and uploaded the commit-specific output directory. Downloaded push artifact 13455863356 has SHA-256 `f45e6f634c59a611c047618947df487ea6e903efb6df6dbcbfac5febc7d92d46`; every one of its 17 entries matches the canonical local build. All five inner ZIP hashes also match the PR artifact.

`ROOT-REVIEW.json` records independent review, tests, source identities, red/green evidence, payload parity, CI artifact identities and remaining acceptance gates. `ALL-SHA256SUMS` covers every ZIP here. Package manifests identify canonical source; the ZIP bytes match the frozen local r4 artifacts exactly. The historical 0.1.10 to 0.1.11 updater remains unchanged. Earlier r3/r2 artifacts remain preserved.

Partial LibreTranslate chunks are not persisted by this correction. After a later-chunk 429, processing rows and the whole reservation are retained and automatic retry stops. Explicit Resume may submit earlier fragments again; the saved error warns the administrator to check provider usage.

Client reporting is still send-capable source under an operational rollout hold; there is no source feature flag. These artifacts do not authorize or claim a production deployment, release/tag change, Marketplace publication or merge.
