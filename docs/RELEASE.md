# My UNA Translator 0.1.9 RC 4 candidate

Native manifest: 0.1.9. Proposed tag: v0.1.9-rc.4. This candidate separates service-operator tooling from the customer Translator package.

The module retains provider URL/key settings, language discovery, Arabic support, the guided Choose → Generate and review → Apply workflow, and native language visibility. The operator's Studio key issuer, PHP socket client, JavaScript, backend manager, deployment unit, tests and operational documentation are not included.

Source and package-boundary verification are recorded in BUILD.json and the frozen asset checksum. RC 3 remains a historical immutable release; this candidate does not rewrite it.

Validation includes PHP syntax, workflow JavaScript, isolated language/provider fixtures and explicit package-boundary checks. The separate operator deployment is tested independently. Native fresh install/update and broader human linguistic review remain NOT TESTED; no updater package or stable-quality certification is claimed.

Publication of this prerelease was explicitly approved on 2026-10-07.

## Frozen source and checks

Authoritative runtime source: 723e0d9ce05a8ef91e32cb6831a66f8355ffb86f, tree 68bf37ed6a4c4efe9e4316e483028130b0d330a1. The following provenance commit updates only BUILD.json and this release record. Native manifest and vendor remain 0.1.9 / My UNA World.

- PHP syntax: 22 files pass.
- Customer language, timeout, protected-content and terminology fixtures: zero failures; 45 curated outputs verified.
- Workflow JavaScript: 8 tests pass.
- Operator exclusion: 3 explicit release-boundary tests pass.
- Separate issuer: 16 Python tests, the asynchronous form test and controller security checks pass. Native Studio rendering and the independent live lifecycle passed; this is operator validation, not customer native-install certification.
- Native fresh install/update: NOT TESTED.
- General linguistic quality and Marketplace native Info/media: NOT TESTED.

No updater is supplied. This archive is a new prerelease candidate and does not replace historical RC3 bytes. Publication is approved; native validation remains outstanding.
