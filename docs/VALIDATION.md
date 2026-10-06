# My UNA Translator 0.1.9 wording correction candidate

Prepared and verified locally on 2026-10-05 from frozen 0.1.8 source 698b9837935e31ce5c2fc961487d81e234c97fde. Version 0.1.9 is not installed on production. This turn did not save live provider settings, generate/apply/resume a site job, change native translations, configure the VPS or publish a release.

## Corrections and quality gate

LibreTranslate alone preserves leading comma/semicolon/colon separators next to protected placeholders. Common exact interface fragments use an explicit curated table in all five targets. It includes login/logout, greetings/count wording, contact/visit, saved/delete messages, and the unambiguous action “Resume generation”. Bare “Resume” is deliberately left to the model because it can mean a CV. DeepL's fragment behavior and default remain unchanged. Provider results expose `origin` and `terminology_fragments` for audit; current saved preview schema is unchanged and does not store those fields. The English provider note discloses curated terms. This glossary is not a native-speaker certification.

A fresh local LibreTranslate 1.8.4 CPU run tested 51 source strings across ES/FR/DE/PT-BR/ZH-HANS: 255 corrected production outputs and 255 model-only audit outputs. All 255 production outputs passed protected-content validation. In each language, 9 rows were terminology-only, 2 mixed, 2 protected-only and 38 model-only. Models: English-Spanish 1.0, English-German 1.3, English-French/Brazilian Portuguese/Chinese 1.9 (same installed packages as the original raw sample). ONNX telemetry was disabled before startup. Exact evidence: LIBRE-CORRECTED-SAMPLES.json; original 70-output evidence remains unchanged in LIBRE-SAMPLES.json.

The original login/message wording and separator defects are corrected through curated terms/local punctuation. Broader semantics still FAIL: bare Resume becomes German Lebenslauf and French Résumé; Spanish Roll back changes becomes Cambios en la espalda; Chinese Create a new post refers to an employment position; Chinese “Your card was not charged” becomes text about a confiscated card. Other upload/report/character/job labels also require context review. These are material quality failures, so the conditional default switch remains withheld. Patching every sampled phrase would not establish general model quality.

A separate raw 50-output probe reached the actual VPS through the user's existing iMac tunnel. It reproduced German login/CV confusion, Spanish rollback errors, Portuguese job-label loss, Chinese post/card confusion. The live setup record reports LibreTranslate 1.9.6, unlike the local 1.8.4 pilot. Do not conflate their versions or outputs. Both currently fail the semantic gate. LIBRE-VPS-SAMPLES.json records the VPS outputs. This probe did not run the updated PHP adapter on the VPS.

## Technical verification

- PHP 8.3.6: 37 PHP files linted successfully.
- Standalone core protection/protocol/access tests: 31 passed.
- LibreTranslate/settings/native-update-format tests: 19 passed. Original wording/separator tests failed before correction; the contextual generation test failed before adding its exact term; a native updater XML regression failed before correcting string_upd syntax.
- Dashboard/progress: 16 Node tests passed.
- MariaDB 10.11.14: 25 integration tests each with InnoDB and MyISAM native fixture tables; module tables remained InnoDB. Disposable database translator_test only. Covers preview/apply/rollback, interrupted/partial requests, preserving 0.1.7 provider migration and native selector/data behavior.
- Real key/limit pilot against local LibreTranslate 1.8.4: missing key 400; invalid key 403; client A first 200 then 429; independent client B twice 200; client B over-character-limit 400. All seven expected outcomes passed. No test key appears in packages/evidence; disposable keys were removed and the service stopped.
- Independent code review found native updater resource syntax string versus string_upd; corrected and verified. No other blocking runtime regression found. Syntax/whitespace checks passed.

The preserving 0.1.8-to-0.1.9 updater replaces runtime files, updates one English explanatory note and clears caches. It contains no execute_sql or delete_files work. Selected provider, encrypted keys, jobs/items, native translations and selector registrations are retained. Existing previews do not automatically acquire the corrections; review them separately. The package is a candidate, not a native update certification.

## Live state and shared clients

The latest saved live setup record says 0.1.8 is installed and connected to a private rootless Podman LibreTranslate 1.9.6 service at server loopback port 5001; no desktop tunnel is needed by the module. It records the native source/runtime/data checks performed by that deployment. This turn's browser observations confirm the optional-provider controls, saved DeepL selection and a ready 24-string Spanish preview with zero applied. Displayed job #46 includes poor wording and was preserved; do not apply it as linguistic approval. Server-side runtime hashes/data fingerprints were not repeated through this restricted SSH tunnel.

Client modules already support an HTTPS URL and a separate encrypted Libre key. The prepared shared-server design uses the existing private service behind HTTPS, required per-client keys, independent limits and persistent key storage. Native key enforcement and isolation passed locally. Public DNS/TLS/reverse proxy, deployed 1.9.6 key enforcement/worker limits, two independent client UNA modules and concurrent capacity remain unverified. See SHARED-SERVICE.md. No public endpoint or additional VPS was created.

The iMac tunnel supports HTTP translation tests but rejects shell execution through its restricted key. Administrator server access is needed to install this candidate and configure the shared client endpoint. The current broader wording gate must pass before making LibreTranslate default. Retain the working live 0.1.8 checkpoint and existing immutable packages.


## 2026-10-06 curated wording regression

The candidate now has exact local terminology for nine reviewed interface strings that previously produced ambiguous or misleading model output: Sign in, Resume generation, the Resume job-control button, Create a new post, Roll back changes, the payment failure notice, the character-limit pause notice, Character limit, and Translation job. `php tests/libretranslate_quality_gate.php` verifies 45 expected outputs (nine strings across five supported targets) through `MyUnaTranslatorLibreProvider::translate`. All use the terminology path; a transport stub confirms the test does not call LibreTranslate, and unreviewed text remains eligible for provider translation. PHP lint and `git diff --check` pass.

This closes the known-string regression, not the broad model-quality gate. Direct raw-service requests from the server returned HTTP 400 because the local endpoint requires an API key; no new raw model output was reviewed in this run. The candidate still needs native UNA 0.1.9 install/update validation and wider human review before any stable release or provider-default certification.
