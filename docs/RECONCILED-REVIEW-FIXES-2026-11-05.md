# Reconciled Translator review fixes — 2026-11-05

Unpublished 0.1.12 candidate, package revision r4. Source base: `27fc7f389ba25ec069241e833ba72d7f17b00f6e`; preceding runtime checkpoint: `9adfa2350fe20db94cb184530e2f872caec3c736`.

## Selecting a saved job

Choosing another job now clears the active job, preview rows, row lookup, totals and details before reading its status. Status and preview must both match the requested job before actions become available. Either read failing clears the incomplete selection and leaves Apply disabled. The direct job-ID lookup follows the same rule. Sequence checks continue to reject older status, refresh and preview responses.

A refresh of the same selected job retains its displayed rows while controls are disabled. This preserves the existing correction-editor race behavior; a failed refresh clears the view before actions can become available again.

The navigation regressions exercise both status and preview failures through both selection controls, immediate clearing while a new preview is pending, and out-of-order status, refresh and preview responses. Apply is observed at its outgoing job-ID boundary.

## LibreTranslate partial-batch rate limits

One source key can contain more than 20 translatable fragments, so a provider batch can make several POST requests. A 429 on the first POST keeps the existing pending-row restoration and Retry-After countdown. A 429 after an earlier POST succeeded instead pauses generation through the existing ordinary-failure path. Processing rows and the committed whole-batch reservation remain saved, and the response contains no automatic retry instruction.

The reservation is conservative **used or reserved** accounting, not a claim about the provider's actual invoice. Partial translated fragments are not persisted by this correction. An explicit Resume can submit them again, so the saved error warns that resuming may process the same characters again and tells the administrator to check provider usage first. Persisting per-fragment progress would require a separate design and migration; automatic paid resubmission is avoided here without that change.

The accounting regression uses exactly 21 `Word` fragments. Its first POST accepts 20 fragments (80 characters), and its second POST receives 429. The job pauses with the previous 11 characters plus its 505-character XML reservation still recorded as 516, one processing row retained, and no `retry_after`. A further ordinary batch dispatch makes no POST. Existing saved manual previews and live language strings are unchanged.

## Verification

Current `tests/run.sh` passes with the supplied PHP 8.3 runtime and ctype, iconv, mbstring and cURL extensions:

- 14 PHP scripts; 83 named PASS lines and the 45-case curated translation gate.
- 39 Node tests, including the Retry-After countdown, countdown cancellation, editor cancellation and review-queue guards.
- 31 Python tests.
- Syntax checks for all 94 PHP files under classes, install, updates and tests; runtime JavaScript syntax and whitespace checks.

The new navigation tests fail against the source base (five failing cases); the partial-rate-limit regression fails there because it schedules another automatic batch. Both pass with this candidate. Existing `needs_review` Apply guards, safe sibling generation, `original_result` and `manually_edited` provenance, three Albanian terminology entries and the language category remain covered by the complete suite.

## Packaging and rollout

The full installer and both 0.1.12 updater source trees contain the same 35 runtime files byte-for-byte. Target package builders use fresh `reconciled-r4` names. The historical 0.1.10 → 0.1.11 updater source is unchanged; its existing package identity must remain `5d63663a35b5314bca25a157eb377f1d8e86842322fbe9ff359dac09b109c0d3`. Earlier r3 and frozen artifacts are preserved.

New-byte native fresh-install and update acceptance remains **NOT TESTED**. The r4 filenames and digests identify new unpublished candidates and do not inherit earlier native PASS claims. Client reporting remains on an operational rollout hold; no source feature flag is added by this correction. GitHub release/tag changes, Marketplace publication and production deployment are outside this local correction.
