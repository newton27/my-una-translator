# My UNA Translator 0.1.15

Native UNA 15 Studio module by My UNA World. Translate English interface language keys through LibreTranslate or DeepL, review saved previews, then apply reviewed text through UNA.

## English-only Page Builder editing

1. Open **Studio → My UNA Translator → Edit pages in English** and click **Start English-only editing**. The module saves original language flags and existing Page Builder translations, then pauses enabled non-English languages. English remains the default; paused languages are temporarily hidden from visitors.
2. Click **Open Pages**, reopen the native page/block editor and save your English changes. Keep the Translator page available. Native permissions, validation and CSRF remain active.
3. Return and click **Finish editing and restore languages**. Wait until restoration and language compilation finish. If restoration needs attention, keep the archive and use **Retry restoration**; do not overwrite translations manually.
4. If English keys changed, optionally **Create plan for affected keys**, generate previews, review and apply them as separate actions. **Skip this translation plan** keeps saved English and preserved translations and allows another editing session without provider usage.

English must be the enabled default and another language must be installed. Originally disabled languages remain disabled. Conflicting changes are retained for review. Disabling/uninstalling first attempts to restore an active editing session and refuses while restoration conflicts remain. This feature uses owned native form hooks and does not patch UNA or NEO core.

## Install or update

Back up the site first. The full ZIP has one root, `my_una_translator/`: place it in `modules/newton/`, then install and enable it through **Studio → Apps → Downloaded**. The manifest belongs at `modules/newton/my_una_translator/install/config.php`.

Existing 0.1.11 or 0.1.12 sites use the matching `My-UNA-Translator-Update-VERSION-to-0.1.15.zip` through Marketplace/native Studio updating. For administrator-assisted staging, its `my_una_translator/updates/update_VERSION_0.1.15/` directory belongs under `modules/newton/`; invoke UNA's native Studio updater. Do not uninstall or overlay the full installer to update. Other predecessor versions require a separately verified updater.

The update adds editing archives and absent preview-review schema, preserving provider settings/keys, saved jobs, preview values and native languages. Older previews without a recorded original machine result retain their saved text but cannot offer a machine-result reset.

## Provider settings and translation

Configure a verified LibreTranslate HTTPS service and site-specific key, or DeepL API Free/Pro credentials. New installations require provider configuration; module purchase does not provision a provider account or include provider usage. Blank saved-key inputs retain existing keys; keys are encrypted with the site secret and omitted from dashboard snapshots.

Choose one to five supported destinations, scope and character ceiling. Create a plan, generate previews, review source/target text, then apply the reviewed job. Generation does not change live translations. Protected markup/placeholders/links are validated. Machine translations require human review; later destination edits become conflicts. Saved rollback restores only unchanged text applied by that job. Translation does not change the site default.

Requires UNA 15.0.x, PHP 8.1+ with cURL, DOM, mbstring and OpenSSL, MySQL/MariaDB advisory locks and writable language caches. Provider connectivity is needed for translation; English editing does not generate provider translations. Member posts, messages, uploads and hardcoded theme text are outside the module's scope.

## Source and checks

The 40 implementation files match the tested November 10–12 0.1.15 checkpoint. README/build metadata are updated for this distribution. See `docs/ENGLISH-EDITING-RELEASE.md` for exact package identities, current acceptance and remaining gates. Historical acceptance is scoped to its original site and bytes.

Run `bash tests/run.sh`. Build with `python3 scripts/build.py`. Historical migration fixtures under `tests/fixtures/legacy-updates` are not installable packages. Service key issuance and operator consoles are separate from this customer module.
