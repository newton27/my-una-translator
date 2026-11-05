# Local reporting and direct-updater validation — November 2, 2026

Source base: `66e81d05908accc49b67ce5656d782173239c9f3` on the existing draft reporting branch. Root runtime and native package payloads are unchanged.

The full suite was executed with PHP 8.3.6 and the required extensions:

| Check | Result |
| --- | --- |
| PHP behavioral checks | PASS — 41 checks |
| Node tests | PASS — 21 tests |
| Python tests | PASS — 14 tests |
| PHP syntax | PASS — 86 files |
| JavaScript syntax and whitespace checks | PASS |

Eight additional checks in `tests/native_019_migration.php` execute the actual direct-updater override and additive SQL using an isolated SQLite fixture and a native-parent boundary adapter. They verify:

- Successful execution of both migrations in the required order.
- Addition of the edit flag to original preview rows.
- Preservation of synthetic encrypted provider values and complete saved fixture rows.
- Replaying the migration without repeating the ALTER, while still executing the support-schema step.
- Preservation of existing receipt rows on replay.
- Immediate native failure when the first SQL step fails.
- Failure propagation when the later SQL step fails after the column was committed.
- Successful retry of that partially completed migration without altering saved data.

A mutation challenge in a disposable copy incorrectly skipped the support migration when the edit column already existed. The replay assertion rejected that mutation. The checked-in implementation passed.

The adapter removes MySQL-only character-set, engine and secondary-index syntax. These checks do not certify MySQL execution, the official UNA action chain, file copying, installed version advancement, native uninstall or a real 0.1.9 fixture. They supersede only the earlier **local PHP execution unavailable** limitation.

The unchanged direct-updater candidate remains 40 files and 66,571 bytes, SHA-256 `53c4ae72bc71692396443f751de72b62a05f651b58c598698f2a5ec059c50a0a`. Rebuilding after these test-only changes reproduced that identity.

Native 0.1.9 acceptance remains **NOT TESTED**. Wider client reporting remains **disabled**, and the draft PR must remain unmerged and unpublished until the remaining acceptance and explicit approval gates are satisfied. Private operational evidence is retained separately; this record contains no private ticket conversation, screenshot, reporter address, access token or hosting configuration.
