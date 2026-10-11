import re
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
                self.assertEqual((UP/'source'/rel).read_bytes(),(ROOT/rel).read_bytes())
    def test_native_version_transition_and_no_database_sql(self):
        data=(UP/'install/config.php').read_text()
        self.assertIn("'version_from' => '0.1.9'",data)
        self.assertIn("'version_to' => '0.1.10'",data)
        self.assertIn("'module_uri' => 'my_una_translator'",data)
        self.assertIn("'update_files' => 1",data)
        self.assertNotIn("'execute_sql' => 1",data)
        self.assertFalse(list(UP.rglob('*.sql')))
        self.assertIn("'version' => '0.1.10'",(ROOT/'install/config.php').read_text())
    def test_update_clears_browser_cache_and_preserves_desktop_workflow(self):
        src=(ROOT/'classes/MyUnaTranslatorStudioPage.php').read_text()
        self.assertIn('translator.css?v=0.1.10-rc1',src)
        self.assertIn('translator.js?v=0.1.10-rc1',src)
        self.assertIn('mut-summary-grid',src)
        self.assertIn('mut-status-dialog',src)
    def test_no_operator_artifacts(self):
        for p in UP.rglob('*'):
            self.assertFalse(any(k in str(p).lower() for k in ('key-console','issuer','key-manager')),p)
if __name__=='__main__':unittest.main()
