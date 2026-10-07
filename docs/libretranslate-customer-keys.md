# LibreTranslate customer keys for My UNA Translator

The Studio key console lets a Myunaworld administrator issue one LibreTranslate API key per customer site, set request and character ceilings, inspect safe metadata, rotate a key, and revoke it. It never displays the customer's key again after the one-time issue or rotation response. Rotation leaves the old key active so the customer can switch endpoints before revocation.

## Request path and data handling

- Studio accepts only authenticated administrator POST requests protected by the module CSRF token.
- PHP sends a bounded JSON request over /run/my-una-translator/key-manager.sock; the manager accepts connections only from the configured PHP worker UID using Unix peer credentials. It does not listen on TCP.
- The manager validates a fixed operation allowlist and sends bounded argument JSON through private stdin to the configured management adapter, without a shell or secrets in OS command lines.
- LibreTranslate stores the active key and limits in its API-key database. The manager stores a Fernet-encrypted copy only so it can revoke the key later. The Fernet key must be readable only by the manager account.
- The manager list response contains key ID, site label, hostname, limits, state, timestamps, and a short fingerprint only. It never returns the secret.

## Deployment prerequisites

This repository does not install or configure a LibreTranslate service on Myunaworld. Before enabling the console, the site administrator must confirm the Myunaworld LibreTranslate instance, its supported ltmanage command, API-key database path, Python cryptography package, and PHP worker UID. Do not point these settings at another host's or another account's test server.

1. Install the repository code and cryptography for the same Python runtime as the service account.
2. Create a dedicated libretranslate-manager account and grant it only the permissions needed to run ltmanage and update the active LibreTranslate API-key database. Keep the existing DB backed up before first use.
3. Create /etc/my-una-translator/key-manager.env owned by root and mode 0600. Review and replace the example paths in ExecStart with the actual socket, store DB, LibreTranslate key DB, ltmanage, and Fernet key paths; replace REPLACE_WITH_PHP_UID with the PHP worker numeric UID. Set conservative request and character ceilings.
4. Generate a new Fernet key for this manager only, store it at the configured path with owner libretranslate-manager and mode 0600, and initialize STORE_DB in a manager-owned directory with mode 0600.
5. Review install/my-una-translator-key-manager.service.example against the host's actual PHP and LibreTranslate users and paths before installing it. Enable the service only after verifying the socket owner/group/mode and local-only reachability.
6. Verify in a non-production test site that issue creates a limited key, the list hides secrets, rotation leaves the old key usable, and revocation removes it. Confirm the PHP worker can reach the socket but other users cannot.

The example service unit is a template. It must be adapted to the actual Myunaworld installation and is not a deploy instruction. No service or live API key is changed by this repository work.

## Required private management adapter

Install the root-owned /usr/local/bin/my-una-ltmanage launcher which imports services.key_manager.ltmanage_stdin.main from /opt/my-una-translator and calls it. This manager requires the stdin adapter rather than a direct ltmanage executable. The adapter loads the official management-only LibreTranslate runtime from /opt/my-una-translator/ltmanage-runtime; pin it to the active engine version, use a package initializer which does not import the translation engine, and install requests and expiringdict. Never put customer secrets in OS arguments. See KEY-ISSUER-DEPLOYMENT.md for the deployed topology and revocation delay.
