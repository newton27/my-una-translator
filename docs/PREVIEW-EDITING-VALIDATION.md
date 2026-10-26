# Preview editing — development validation

2026-10-26. Base: d0a0ae59ece73bea91ad1360cb9e4cceeac305ba. Target: 0.1.11, updater from 0.1.10.

Implemented: explicit Edit / Save / Cancel per staged row, persistent manual label, immutable source and destination baseline, SHA-256 stale-preview check under the existing advisory lock, server validation of protected content, unsaved-draft navigation protection, module-owned schema migration and native updater source.

Validation: `npm ci` and `PHP_BIN=/tmp/translator-php/php npm test` passed. PHP 8.3 CLI was extracted locally for the tests. The suite reports 26 passing PHP test groups (including the existing 45-output curated provider quality gate), 16 Node tests, 10 Python tests, PHP syntax checks and JavaScript syntax validation. SQLite fixtures execute persistence/migration behavior; they do not certify MySQL concurrency or a native UNA installation. UI behavior uses jsdom, not a rendered browser.

Independent code review identified one P2 delayed-refresh race. A regression test reproduced draft loss before the fix. Refresh, pagination and job navigation now disable editing while reading. The new regression and full suite pass after the correction. No other important defects were reported.

Native 0.1.10-to-0.1.11 update, fresh install and visual browser validation: NOT TESTED. Live source/version comparison and deployment: BLOCKED. Browser automatic approval review rejected opening the signed-in hosting control panel as lacking specific authorization. No live runtime, language strings, core files, release tags, release assets or Marketplace listings were changed.

Deployment must compare the target site's installed module version and local customizations before staging this updater, retain a normal rollback checkpoint, run the native update, then verify editing, validation, Apply conflicts and language caches using an authenticated Studio session. Sites earlier than 0.1.10 require a verified prior update path. The historic 0.1.9-to-0.1.10 updater is unchanged.
