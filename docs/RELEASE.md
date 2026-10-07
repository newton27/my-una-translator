# My UNA Translator 0.1.9 RC 4 candidate

Native manifest: 0.1.9. Proposed tag: v0.1.9-rc.4. This candidate separates service-operator tooling from the customer Translator package.

The module retains provider URL/key settings, language discovery, Arabic support, the guided Choose → Generate and review → Apply workflow, and native language visibility. The operator's Studio key issuer, PHP socket client, JavaScript, backend manager, deployment unit, tests and operational documentation are not included.

Source and package-boundary verification are recorded in BUILD.json and the frozen asset checksum. RC 3 remains a historical immutable release; this candidate does not rewrite it.

Validation includes PHP syntax, workflow JavaScript, isolated language/provider fixtures and explicit package-boundary checks. The separate operator deployment is tested independently. Native fresh install/update and broader human linguistic review remain NOT TESTED; no updater package or stable-quality certification is claimed.

This is a prepared candidate until publication is explicitly approved.
