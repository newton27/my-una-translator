# My UNA World key issuer deployment — 2026-10-07

Source baseline: v0.1.9-rc.3 (0a46cac). Isolated branch: codex/key-issuer-deployment.
This is a direct deployment, not a native module install/update test or a new GitHub release.

## Live integration

- Studio: https://myunaworld.com/studio/module.php?name=my_una_translator
- Customer endpoint: https://myunaworld.com/translator-api
- The existing rootless LibreTranslate container remains on loopback port 5001, with all previously installed languages.
- Only StudioPage, KeyClient and key-console.js were placed in the live module. Existing provider, engine, translations, jobs and language workflow were retained.
- Dedicated system account libretranslate-manager; socket group myunaworld, allowed PHP UID 1022; Unix socket only.
- Socket /run/my-una-translator/key-manager.sock mode 0660; metadata /var/lib/my-una-translator mode 0700 with database 0600; Fernet key 0600.
- ProtectHome and ProtectSystem remain enabled. The existing container DB directory is bound into /var/lib/libretranslate-keys in the manager's private mount namespace. Targeted ACLs allow the manager to update that database, without granting PHP direct DB or encryption-key access.
- Default service ceiling: 60 requests/minute, 4000 characters/request. These are per-key request ceilings, not capacity guarantees.
- Host management-only runtime uses manage.py, api_keys.py and default_values.py from the pinned running LibreTranslate image. Empty package init avoids importing the translation engine; host dependencies are cryptography, requests and expiringdict.
- /usr/local/bin/my-una-ltmanage delegates to services.key_manager.ltmanage_stdin. Key arguments are sent through bounded private stdin JSON; OS command lines contain no customer secret.
- Encryption key, metadata DB and LibreTranslate key DB require separate restricted backups. The root-only pre-deployment backup is /root/my-una-translator-key-issuer-20261007.

## Correctness and validation

Two lifecycle defects were fixed with failing-first regression tests: repeated revocation is idempotent without another CLI/audit operation, and rotations record operation=rotate.
A third regression proves CLI secrets are delivered through stdin, with no OS arguments, and actual add/remove database behavior.

Fresh automated and live outcomes are recorded in the adjacent deployment directory. Live checks issue temporary keys, translate through public HTTPS, enforce character/request limits, verify independent customer allowances, rotate without prematurely disabling the old key, and reject both revoked keys after cache expiry. All verification keys are revoked in finally cleanup.

LibreTranslate caches successful key validation for up to about 30 seconds. Revocation is therefore not instantaneous.
A hostname is an administrative label, not a cryptographic domain restriction; the bearer key must be kept private.

Browser testing in a signed-in Studio session and native module installer/updater certification are separate from these server/PHP checks. No provider quality certification or default switch is implied.
