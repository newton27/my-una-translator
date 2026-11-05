# Translation preview review queue

The user approved continuing preview generation while flagging individual unsafe translations for review on myunaworld.com.

- Keep the existing protected-text validator unchanged. Unsafe provider output never becomes an applicable preview.
- Preserve valid siblings from the same provider response; record a `needs_review` item with its reason for each invalid translation. Count all returned/billed work, including rejected translations.
- Continue pending strings without retrying rejected rows automatically. Connection, quota, rate-limit and malformed batch responses retain their existing pause behavior.
- When generation finishes, a job with flagged rows enters `needs_review`. Apply is blocked on both the server and the interface while any flagged rows remain.
- Reuse the installed search and correction editor. Corrections require unchanged English source, exact expected preview state/value, valid UTF-8 and successful protected-text validation. The final correction makes the completed preview ready for explicit Apply.
- Preserve saved previews, destination baselines, existing manual edits, provider settings and credentials. No automatic Apply, core changes, service routing changes, release publication or destructive migration.

The selected approach is per-row validation during response reconstruction. Whole-batch failure loses valid work; deleting invented placeholders would retain mistranslations. Both alternatives are rejected. PHP and the existing Studio JavaScript remain the implementation platform.
