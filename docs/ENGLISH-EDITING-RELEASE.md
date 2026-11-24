# Translator 0.1.15 English-editing distribution

The implementation matches all 40 files of the tested November 10–12 checkpoint. The existing accepted native updater archive is 90,875 bytes, SHA-256 `4a546867fe1a92b6a63dbe2229eaa36c781fd2983c32f2587c8b4366e2f1ad09`. New README/build metadata and direct native updater paths are the distribution changes. Existing tags/releases/assets remain untouched.

Validation: all 15 PHP suites pass on PHP 8.3.6 (including cURL/SQLite/DOM); all 39 Node tests and seven Python package/boundary tests pass. Native private restore, exact direct updates, fresh installer and real GayJax pause/save/restore are still pending at this source checkpoint. No Marketplace availability is claimed.

| Artifact | Bytes | SHA-256 |
| --- | ---: | --- |
| My-UNA-Translator-0.1.15.zip | 88466 | `ec69c1c42a0ff3791abd9a2a3ef21d984a6ecf75b9805e72ccda9160ff2d88e6` |
| My-UNA-Translator-Update-0.1.11-to-0.1.15.zip | 96266 | `1764566cdd20119e923e666e58a3458fcc809adf8095ea0d593843d4bf2eab29` |
| My-UNA-Translator-Update-0.1.12-to-0.1.15.zip | 96268 | `841810dca5512c1df05a7c47eb42c9a148721de3927428c7aad9db2364f6e1a5` |

Build with `python3 scripts/build.py`; ZIP timestamps, order, permissions and compression are fixed. Runtime whitelist excludes tests, services, operator tooling, dependency directories and UNA core. Installer/updater payload parity is checked before packaging.

Current native acceptance is pending on an isolated UNA 15 installation. Provider settings/jobs/language values must be preserved, and no translation generation or Apply is required for the editing-switch acceptance. Games Marketplace release stays ON HOLD under its separate tracker.
