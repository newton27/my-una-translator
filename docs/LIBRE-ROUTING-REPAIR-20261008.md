# LibreTranslate routing source checkpoint

This source checkpoint builds on issuer-free RC4 commit `fd378dd8ee995aa65d6e445c85a264ac137aa30a`. It imports the existing rate-limit retry changes in Engine, LibreProvider and translator.js and repairs their native HTTP routing. It is not a certified new release or native updater.

## Correction

The provider passed its base URL to the native cURL helper, which already prepends the base URL. Both languages and translation requests therefore used a doubled base URL. The request line now passes a complete URL to injected transport and a relative path to native cURL. The regression test covers GET with and without a trailing slash, POST translation and the 429 exception's Retry-After value.

## Known retry limitation

LibreProvider may send multiple POST chunks for one batch. If a later chunk returns 429, Engine resets the whole batch to pending and restores its prior character accounting. Retrying repeats earlier successful chunks. A restrictive quota may prevent such a batch from completing, and character accounting omits successful partial requests. The routing correction does not resolve this existing retry defect. Persisting successful fragment progress and accounting it across retries remains required before certifying this retry behavior for release.

The unreachable 429 error-map entry also mentions two retries although the browser loop has no such retry limit.

## Validation scope

Four regression checks passed on PHP, including the 17-second Retry-After assertion. Provider PHP syntax and JavaScript syntax passed. Native cURL GET languages and protected-placeholder translation probes passed. No native install/update validation or new release packaging is claimed. Machine-generated previews require review before applying.

This checkpoint contains source and technical notes only; credentials, deployment paths, private smoke helpers, backups and database evidence are excluded.
