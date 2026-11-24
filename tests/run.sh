#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
task_fixture="$(mktemp)"
trap 'rm -f "$task_fixture"' EXIT
export MUT_RENDER_FIXTURE="$task_fixture" MUT_FIXTURE="$task_fixture"
for file in classes/*.php classes/support/*.php install/*.php; do php -l "$file" >/dev/null; done
for file in tests/*.php; do
  case "$file" in *preview_test_db.php) continue;; esac
  php "$file"
done
node --test tests/*.cjs
python3 scripts/build.py
python3 -m unittest discover -s tests -p 'test_*.py'
