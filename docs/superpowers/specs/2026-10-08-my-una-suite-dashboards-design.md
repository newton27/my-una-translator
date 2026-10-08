# My UNA suite dashboard design

Date: 2026-10-08  
Status: Proposed design; awaiting review of this written specification.  
First implementation: My UNA Translator.  
Owner: Michael Newton / My UNA World.

## Intended result

The My UNA series will have functional dashboards that match the graphical interfaces pictured in its blue and cyan marketing covers. Michael specifically requested the graphical cards shown across the bottom of those covers and recognizes that applying this across the modules is a substantial change. The same visual identity should appear in each module, with controls and information appropriate to that module.

Success means an administrator can immediately understand the module's current state, identify the next useful action, and open its existing workflows from a polished dashboard. The installed interface and future cover screenshots must describe the same capabilities.

This specification covers the branded My UNA modules listed below. Albums LightGallery and the Facebook importer appear in the older suite media inventory; their inclusion in this dashboard rollout is a scope assumption to resolve separately, because their product names and workflows differ. Modzzz modules and the separate LibreTranslate key-issuer service are outside this change.

## Selected approach and alternatives

Use a shared, versioned dashboard design, bundled locally into each independent UNA module. Share colors, spacing, card anatomy, status patterns, buttons and responsive behavior. Keep each module's data adapter and workflows within its own repository.

This approach prevents visual drift while allowing a customer to install a single module. It avoids a mandatory new suite framework module. The first reusable implementation belongs in Translator; later modules receive the same small, versioned style assets with a documented design revision.

Two alternatives were considered:

- Rebuild every interface independently: simpler initially, but produces inconsistent cards and makes each later style adjustment repetitive.
- Change UNA's global Studio theme: broad reach, but affects unrelated modules and cannot safely express each module's workflow or state.

## Shared appearance

- Navy and cyan product header, matching the supplied Mail Doctor cover and the new Translator cover. Use a crisp module icon and readable product name rather than placing the entire cover image inside the application.
- Light opaque work surfaces, blue headings and cyan accents. Status colors also have explicit text or icons.
- Compact summary cards show observed values and their scope. Four functional work cards form the main overview. Use two columns at ordinary desktop widths, four only when enough space remains, and a single column on small screens.
- Each work card has a labeled icon, title, concise graphical summary, relevant information, and a clear action area. The blue footer treatment echoes the cover's bottom panels without replacing functional controls with an image.
- Use UNA's existing module menu and breadcrumbs. Do not add a competing nested navigation menu.
- Preserve the site's typography and native input behavior. Support keyboard operation, visible focus, reduced motion, RTL text and native language names. Maintain readable contrast and usable touch targets.
- Scope styles beneath a module root. No global resets or styling of unrelated Studio elements. Bundle icons and assets; do not require third-party CDNs at runtime.

## Translator pilot

Translator's overview will present four work cards:

1. **Choose languages:** selected destinations, English source, supported-language selection and search. The expanded form retains the current one-to-five-language limit, scope and character ceiling.
2. **Translation preview:** saved job state, preview counts and a short English/translation comparison. Open the full existing paginated review table for detailed work.
3. **Review and apply:** show conflicts and preserve existing edits, markup and placeholders. Open the existing review/apply controls, with Apply available only in the states already permitted by the current workflow.
4. **Progress and recovery:** show the current phase, actual completed/total items, saved status and applicable pause/resume/recovery controls. Retain the repaired status popup as a second view of the same state.

The top summary row shows provider configuration, site-language availability, the selected job and conflicts for that selected job. A configured provider is labeled **Configured**, not **Connected**, unless an actual successful check establishes connectivity.

Data comes from the existing snapshot and job responses. Reuse `MyUnaTranslatorProgress` and `MyUnaTranslatorWorkflow`; do not introduce a second state machine. Generation, apply and rollback have different progress denominators. Conflicts are resolved outcomes but are never labeled successful translations. An absent job is **No job selected**; a missing provider is **Setup needed**. A timeout or failed refresh keeps the last observed data with a clear stale/error message rather than pretending completion.

Rendering the dashboard must not generate translations, create plans, apply a preview, alter a language default, publish languages or resume a job. Existing explicit controls initiate those actions. Provider settings, API credentials, limits, saved jobs, translation data and the current preview-before-apply workflow remain authoritative.

## Module-specific dashboard mapping

These cards are based on inspected current repository interfaces and READMEs. Exact numeric fields are enabled only after their source queries are verified during the corresponding module implementation.

| Module | Four main work cards | Available evidence and limits |
| --- | --- | --- |
| My UNA Translator | Languages; Preview; Review and apply; Progress and recovery | Existing language coverage, saved jobs, phase-aware progress and conflict counts. |
| My UNA Doctor | Site health; Compatibility; References and integrity; Guarded repair | Latest recorded scan and findings, compatibility evidence and existing reference/repair workflows. A missing scan is not a healthy scan. |
| My UNA Module Doctor | Reference packages; Package inspection; File comparison; Repair workspace | Verified references, inspection/comparison evidence and controlled repair plans. No automatic repair from an overview card. |
| My UNA Mail Doctor | SMTP and configuration; Recipient health; Bounce evidence; Suppression and recovery | Existing settings, health queues, suppression ledger and event evidence. Delivery percentages and sent-email totals require an actual supported data source; the cover's sample values are not adopted. |
| My UNA Stream Doctor | Configuration; Connectivity; Data integrity; Recording control | Existing administrator-triggered diagnostics and explicit OME controls. Show **Not checked** where no check has run. No background network scan on dashboard load. |
| My UNA World | Backups; Archive verification; Recovery; Protection settings | Existing backup inventory, job progress, verification and restore workflows. Restore and maintenance actions retain their current deliberate steps. |
| My UNA Finances | Transaction volume; Subscriptions and plans; Reports; Community health | Existing read-only overview/snapshots. Keep currencies separate. Fees, refunds and net remain **Unavailable** if the current module cannot compute them. |
| My UNA Tickets | Review queue; Ticket conversation; Delivery state; Products | Existing moderation, private conversation, delivery controls and product management. Show only records the current administrator is allowed to access. |
| My UNA Jitsi | Meeting server; Authentication; Meeting defaults; Start/join meeting | Existing configuration and meeting launcher. No invented attendance counts, active-room totals or call history; this module has no meeting telemetry tables. Member meeting controls keep their existing audience. |
| My UNA Weather | Provider setup; Default city; Units; Weather preview | Existing administrator configuration and weather cards. Preserve current location privacy, cache behavior and data attribution. A provider outage does not substitute a different person's location. |

## Source evidence and reconciliation

Inspected on 2026-10-08:

- `newton27/my-una-translator`: main commit `fd378dd8ee995aa65d6e445c85a264ac137aa30a`; StudioPage, snapshot/store, workflow and progress helpers.
- Current main READMEs and StudioPage classes for `my-una-doctor`, `my-una-module-doctor`, `my-una-email-doctor`, `my_una_stream_doctor`, `my-una-world`, `my-una-finances`, `my-una-tickets` and `my-una-jitsi`.
- My UNA Weather local development checkout, HEAD `7395905`, with existing uncommitted integration changes; its README and native module structure.
- Existing suite status and branding reconciliation records. These are an earlier inventory, not evidence that every current module is released or installed identically.

Translator's local repair checkout has protected-boundary changes and tests that are not part of that main checkpoint. A separate monthly-access worktree also exists. Before implementation, reconcile the current installed/canonical source with those authorized repairs. Build from an isolated branch without overwriting open work or losing the recent popup and translation safeguards.

## Implementation boundaries

The first delivery changes Translator presentation and adds a small adapter that derives dashboard summaries from existing public snapshots. Existing authenticated endpoints, CSRF checks and guarded operations are reused. No credential is added to a card payload. Strings use native UNA localization keys and fallbacks as the module already does.

Extract shared design tokens and card styles from the working pilot, then apply them to one module at a time. A module's detailed pages receive compatible typography and controls after its overview works. Adding previously unavailable metrics or new backend capabilities is separate work and must be specified rather than inferred from a marketing mockup.

## Verification and rollout

For the Translator pilot, check empty, unconfigured, provider-error, planned, generating, paused, preview-ready, applying, applied-with-conflicts and rollback states. Verify that progress and action availability match the existing helpers; no dashboard refresh starts a job. Check all existing language-picker, preview, popup, provider, protected-markup and workflow tests appropriate to the modified files.

Visually inspect at 320, 736, 1024 and 1440 pixels and with RTL language text. Verify keyboard navigation, readable errors and reduced-motion behavior. Test the actual native Studio route and lifecycle/update packaging before shipping a module release.

Start on a test installation, then roll out the verified module update through its normal release path. Preserve configuration and business data. Rollback restores the previous presentation files and their compatible source checkpoint. Do not claim cross-host or fresh-install validation without exercising those paths.

After each installed interface and version are frozen, capture real module screenshots, update its header/marketplace media, and then update its manual. This follows the previously requested order and prevents marketing imagery or documentation from describing a moving interface.

## Reviewable deliverables and next stage

The accompanying interactive design preview shows the proposed shared appearance with explicitly labeled example data. It is a design artifact and does not connect to a UNA site. This document is the implementation boundary. After Michael approves it, write the concrete Translator implementation plan and select the execution method before product changes begin. Later module implementations use this approved visual contract with their individually verified data sources.
