#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
php_bin="${PHP_BIN:-php}"
for test in server_languages language_targets libretranslate_quality_gate curl_language_timeout preview_editing preview_migration; do
  "$php_bin" "tests/$test.php"
done
fixture="$(mktemp)"
trap 'rm -f "$fixture"' EXIT
MUT_RENDER_FIXTURE="$fixture" "$php_bin" tests/server_languages.php >/dev/null
MUT_FIXTURE="$fixture" node --test tests/workflow.cjs tests/status_popup.cjs tests/preview_editor.cjs
python3 -m unittest discover -s tests -p 'test_*.py'
while IFS= read -r -d '' file; do "$php_bin" -l "$file" >/dev/null; done < <(find classes install updates tests -name '*.php' -print0)
node --check template/js/translator.js
git diff --check
