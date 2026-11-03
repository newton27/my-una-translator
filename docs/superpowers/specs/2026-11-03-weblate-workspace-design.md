# Optional Weblate workspace for My UNA Translator

Status: proposed design; implementation and hosted acceptance pending.

## Intent and agreed scope

Use Hosted Weblate as an optional workspace where My UNA Translator's existing engine generates interface translations and people improve them. Verify UNA language-package conversion and import/export, preserving language keys and placeholders. The module's normal Studio preview, Apply, conflict checks and rollback remain the final path to a website.

The user selected this optional-workspace approach on 2026-11-03. The detailed implementation choices below are proposals, not previously approved product behavior.

## Current source and verified findings

- Repository: `newton27/my-una-translator`; default `main` at `9d4f5a2a61442fc51b2228a1f759ea812d52d6d7`.
- More recent functionality is on draft PR #5, `feature/integrated-support-reporting`, at `21f276872e0658af101c4ffc723c91e77ec7a561`, manifest 0.1.12. Do not silently merge that separate feature. Choose the reviewed integration base before implementation.
- The module already supports LibreTranslate and DeepL. It translates UNA interface keys, not member posts or messages.
- `MyUnaTranslatorEngine::editItem()` validates edits, compares an expected result hash, preserves source/before-value, and only edits staged results in ready/paused jobs. `apply()` checks current English source and destination values; `rollback()` preserves later edits.
- `MyUnaTranslatorText::validate()` checks supported UNA substitutions, printf arguments, markup, links, addresses, entities and protected product names. Reuse it rather than introduce a weaker parallel validator.
- UNA module language files use `<resources ...><string name="key"><![CDATA[text]]></string></resources>`. `BxDolStudioLanguagesUtils::readLanguage()` reads resources metadata and string values. Native update files can separately contain `string_add`, `string_upd` and `string_del`; these are outside this pilot's input contract.
- A local lxml parse/serialize/reparse of PR #5's `install/langs/en.xml` preserved all 161 unique key/value pairs, order and root metadata. This is an XML baseline check only: it does not certify Weblate serialization, translation or native UNA import.
- Hosted Weblate is reachable in the connected Chrome browser, but the observed session is signed out. Its sign-in page is ready for the user. No project, subscription, account, provider credential or live site was changed.

## Approaches

| Approach | Tradeoff | Decision |
| --- | --- | --- |
| Native UNA XML with Weblate Flat XML parameters | Fewer conversions; lacks an approval state in the UNA file itself and needs round-trip verification of UNA metadata/CDATA. | Consider later for repository language maintenance. |
| Bilingual XLIFF 1.2 exchange | Adds a small converter; carries exact source/key identity, whitespace and human approval state. | Recommended for the pilot. |
| Automatic bidirectional Weblate API synchronization | More convenient at scale; requires credential storage, retries and conflict coordination. | Defer until the file workflow passes acceptance. |

The recommended pilot uses the module to generate translations first, so its current provider limits and protected-content handling remain in effect. It needs no translation-engine credentials in Weblate. Direct Weblate-to-LibreTranslate generation can be tested later with a dedicated limited key; it is not required for this workflow.

## Proposed user workflow

1. Generate a complete preview with My UNA Translator.
2. Choose **Export for Weblate** for one target language in a ready job. Export does not apply text or incur provider requests.
3. Upload the XLIFF into a Weblate component configured for XLIFF 1.2, preserved whitespace and dedicated reviewers. Machine results start awaiting human review.
4. Translators improve text; an authorized reviewer approves it. Download the reviewed XLIFF.
5. Choose **Import Weblate review** in the same module job. Show a dry-run comparison of eligible changes, unreviewed rows and conflicts. Every exported unit for that language must be approved before import can proceed. Import changes only the saved preview after explicit confirmation.
6. Use the existing **Apply reviewed translations** action after inspecting the preview. Apply and rollback retain their existing conflict protection.

This is a voluntary branch of the current workflow. Weblate approval gates the exchange import; it does not add a mandatory Weblate approval gate to the existing Apply action. A user can continue through Studio's existing local review and Apply flow. If a job is applied while external review is in progress, its returned exchange cannot be imported into that applied job. A partial external review is not a completed exchange and makes no preview changes.

## Exchange contract

- One file per job and UNA target language, using XLIFF namespace `urn:oasis:names:tc:xliff:document:1.2`, version `1.2`, source language `en` and a validated target-language mapping. Start the hosted pilot with Spanish; add Arabic and Chinese alias cases to conversion tests.
- Use the exact UNA language key as `resname`, unique within each file; use a stable item identifier as `id`. Duplicate English text must remain separate when keys differ. Database IDs alone are not portable language-package identities.
- Include the English source and generated target as XML-escaped text, each with `xml:space="preserve"`. Treat embedded UNA HTML as text, not executable XLIFF children. The Weblate component uses plain-text placeables handling; actual UNA content is still validated on return.
- Export machine results without human approval. Accept reviewed text only when `approved="yes"` and its target is not marked as needing translation/adaptation/editing. A review flag is workflow metadata; local validation is still mandatory.
- Keep a signed export manifest outside Weblate's editable content, bound to the originating site, job, language, item/key, English source hash and original preview-result hash. The user retains this file for import alongside the reviewed XLIFF. Do not depend on Weblate preserving unknown attributes.
- Validate the full export identity and file membership before making any preview changes. Unknown, missing or duplicate keys/IDs, changed source, wrong target language, unsafe XML or a missing/invalid signature reject the exchange with a readable error. Unreviewed units are reported; any unreviewed unit or conflict prevents the complete language-file import, with no preview writes. The user can finish the Weblate review or continue with Studio's local editor instead.
- Compare each reviewed unit with the locally saved source and original result hash under the module lock. A preview changed since export is a conflict. Also recheck current Polyglot source/destination state for the dry-run report; Apply rechecks again at write time.
- Reuse `MyUnaTranslatorText::validate()` for every changed result. Reject missing/extra/reordered protected content, invalid UTF-8, empty nonempty-source results and introduced markup. Preserve `before_value`, source, keys, language and job ownership. Imported corrections are marked as manually edited using the existing field.
- The import preflight validates the whole package before writes. Transactionally stage the complete approved, conflict-free exchange after confirmation; recheck its baseline within the transaction. Unchanged approved units need not receive new provenance. No direct Polyglot writes, language creation or cache changes occur during import.
- Apply conservative file/row limits; do not extract arbitrary uploaded archives or use uploaded file paths. Disable external entities and network XML access; reject DTDs/entities, unexpected namespaces/elements and oversized documents. Use existing Studio administrator and CSRF controls for export/import.
- Preserve Unicode code points, whitespace, line breaks, entities as literal string content, URLs, HTML and supported substitution bytes. XML syntax may use escaping instead of CDATA, but decoded UNA string values must match. Native output regenerates CDATA safely, including a `]]>` split when necessary.
- Do not add native plural forms that UNA does not model. Inputs with unsupported plural structures or `string_add/upd/del` directives fail explicitly.

## UNA language-package verification

In addition to the live-job exchange, test a pure UNA XML → XLIFF → UNA XML conversion on fixture files without applying them to a website. Reconstruct target resources metadata from trusted input plus an explicit target preset, never from arbitrary uploaded metadata. Preserve category/name identity, key spelling/case, key order and every decoded untouched string. Package generation produces a reviewable XML artifact; installation requires the native acceptance below.

Fixtures must include duplicate English phrases under distinct keys, HTML and attributes, printf positional and nonpositional arguments, `{0}`, named braces, `__TOKEN__`, entities, email/URL text, CDATA boundaries, significant spaces/newlines, empty strings, Arabic/RTL and Chinese variants. Negative cases mutate each protected token, source, key, language, approval state and export baseline.

## Hosted workspace and access

Use the user's Hosted Weblate account. Inspect its available project/plan capabilities after sign-in. Keep the pilot restricted to invited reviewers if supported; do not make customer translations public or start a paid plan to bypass an account limitation. If a project requires payment, public visibility or new repository permissions, present that exact decision to the user first.

The first pilot uploads only public module language fixtures and synthetic reviewed samples. No customer database dumps, private keys, provider secrets, site settings or member content go to Weblate. Optional provider configuration is a separate step after the file workflow succeeds.

## Acceptance and evidence

| Gate | Required evidence | Current status |
| --- | --- | --- |
| Source inventory | Reviewed source hashes and baseline format | Inspected; main and PR #5 differ |
| XML baseline | 161 keys, values, order and root metadata preserved through local lxml parse/save | PASS within this limited scope |
| Converter and importer | Positive and mutation tests, isolated PHP fixture, no Polyglot writes on import | NOT IMPLEMENTED |
| Actual Weblate round trip | Upload, human edit/approval, download, local validation and retained key/placeholder comparison | NOT TESTED; sign-in required |
| Existing module regressions | Existing PHP, Node and package checks on final integration source | NOT RUN for this proposal |
| Native UNA acceptance | Backed-up isolated test site: generate, export/import preview, Apply, language/cache inspection and rollback with later-edit preservation | NOT TESTED |
| Distribution | New version and native updater if runtime changes; frozen artifacts from the reviewed checkpoint | NOT PREPARED; no release change authorized here |

Completion means an actual approved Weblate edit returns to a saved module preview, applies through the existing native path, and passes the native rollback/conflict checks. A parser-only identity round trip is not completion.

## Builder decisions and remaining user actions

The builder can choose class boundaries, manifest canonicalization, configured limits, temporary-file handling and existing UI placement consistent with this contract. The recommended integration base is the current reviewed preview editor implementation, with separate support-reporting changes left independently reviewable. Reconfirm branch heads before coding.

The user must sign in to Hosted Weblate (or create an account themselves), review this proposed workflow, and decide any concrete paid-plan/public-visibility/access grant only if the account requires it. No production deployment, Marketplace publication, GitHub release or frozen artifact replacement is part of this design step.

## References

- Translator PR #5: https://github.com/newton27/my-una-translator/pull/5
- UNA reader: https://github.com/unacms/UNA/blob/master/studio/classes/BxDolStudioLanguagesUtils.php
- XLIFF review states, identities and whitespace: https://docs.weblate.org/en/latest/formats/xliff.html
- Flat XML and configurable parameters: https://docs.weblate.org/en/latest/formats/flatxml.html and https://docs.weblate.org/en/latest/formats.html
- LibreTranslate configuration: https://docs.weblate.org/en/latest/admin/machine.html#libretranslate
