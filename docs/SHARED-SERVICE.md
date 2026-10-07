# Connecting a customer UNA site to the shared service

Configure My UNA Translator in Studio with the operator-provided HTTPS base URL and a separate API key for your site. The My UNA World endpoint is https://myunaworld.com/translator-api.

Requests are sent by your site's PHP backend. Keys remain encrypted in your site's settings and are not returned in status snapshots. Your server needs outbound HTTPS access. The operator's key issuer and translation engine are separate services; installing this module does not install or expose either one.

Ask the operator to issue or rotate a key. Copy the one-time key into Provider settings and test language discovery and a small translation preview. Keep the previous key until the replacement works, then ask the operator to revoke it. Revocation may take about 30 seconds because the translation service caches key validation.

Your request and character allowances belong to that key. Keep batches within those limits; the module can pause and resume saved work. A rate or quota limit is separate from UNA membership. General model quality still requires review before applying a translation.

Do not place keys in URLs, distributable packages, browser scripts, screenshots or public documentation.
