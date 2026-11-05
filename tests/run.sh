#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
php_bin="${PHP_BIN:-php}"
fixture="$(mktemp)"
trap 'rm -f "$fixture"' EXIT
export MUT_RENDER_FIXTURE="$fixture"
export MUT_FIXTURE="$fixture"
for test in tests/*.php; do "$php_bin" "$test"; done
node --test tests/*.cjs
python3 -m unittest discover -s tests -p 'test_*.py'
while IFS= read -r -d '' file; do "$php_bin" -l "$file" >/dev/null; done < <(find classes install updates tests -name '*.php' -print0)
node --check template/js/translator.js
git diff --check
