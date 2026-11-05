import re
import hashlib
import unittest
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
UP = ROOT / 'updates/update_0.1.9_0.1.10'

class NativeUpdaterPackage(unittest.TestCase):
    def test_exact_runtime_source_copy(self):
        expected={
            'classes/MyUnaTranslatorStudioPage.php',
            'template/css/translator.css',
            'template/js/translator.js',
            'install/config.php',
        }
        found={p.relative_to(UP/'source').as_posix() for p in (UP/'source').rglob('*') if p.is_file()}
        self.assertEqual(found,expected)
        for rel in expected:
            with self.subTest(rel=rel):
                self.assertEqual(hashlib.sha256((UP/'source'/rel).read_bytes()).hexdigest(),{'install/config.php': 'bf065bebf8f662101c59964766c7981caf2ca74fb61506650f5f656156bef69c', 'classes/MyUnaTranslatorStudioPage.php': '620cc3e9f45e5529a4d8e15c99616208ab5a44b1ab520bd3f41d76ccbde96ca2', 'template/js/translator.js': 'd9949482482ba3737c8bb1af6b934a03d7304d9c5a82639ca545b12df73a7300', 'template/css/translator.css': '740ed809d2210edad3375da2eb761869f7c73e9d2a9211585e877555ffacdb2a'}[rel])
    def test_native_version_transition_and_no_database_sql(self):
        data=(UP/'install/config.php').read_text()
        self.assertIn("'version_from' => '0.1.9'",data)
        self.assertIn("'version_to' => '0.1.10'",data)
        self.assertIn("'module_uri' => 'my_una_translator'",data)
        self.assertIn("'update_files' => 1",data)
        self.assertNotIn("'execute_sql' => 1",data)
        self.assertFalse(list(UP.rglob('*.sql')))
        self.assertIn("'version' => '0.1.10'",(UP/'source/install/config.php').read_text())
    def test_update_clears_browser_cache_and_preserves_desktop_workflow(self):
        src=(UP/'source/classes/MyUnaTranslatorStudioPage.php').read_text()
        self.assertIn('translator.css?v=0.1.10-rc1',src)
        self.assertIn('translator.js?v=0.1.10-rc1',src)
        self.assertIn('mut-summary-grid',src)
        self.assertIn('mut-status-dialog',src)
    def test_no_operator_artifacts(self):
        for p in UP.rglob('*'):
            self.assertFalse(any(k in str(p).lower() for k in ('key-console','issuer','key-manager')),p)
if __name__=='__main__':unittest.main()

