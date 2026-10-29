# Editable translation previews

The user approved editing each translation during review, saving into the job, a Manually edited label, placeholder validation, and preserving later Polyglot edits. Implement inside My UNA Translator only; UNA core files must remain untouched.

Use explicit Edit, Save and Cancel controls per staged row. Persist the edited value and a manually_edited flag; keep the original source and destination baseline immutable. Save checks a hash of the displayed result so a stale browser cannot overwrite a newer edit. Only ready or paused jobs with staged items are editable. Apply and rollback retain their existing compare-and-swap behavior. Unsaved row edits disable navigation and Apply until saved or cancelled. Validation errors retain the draft.

Alternatives: editing Polyglot remains available but does not correct saved job previews; automatic blur saves risk accidental changes. Explicit row saves fit the existing separate review/apply workflow. Schema change is one module-owned flag, included in fresh installs and the native 0.1.10-to-0.1.11 updater. No automatic apply, provider call or change to existing language strings occurs on save.
