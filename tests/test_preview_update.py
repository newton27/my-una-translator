import unittest
import hashlib
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
UP=ROOT/'updates/update_0.1.10_0.1.11'
class PreviewUpdate(unittest.TestCase):
 def test_module_owned_migration_and_source(self):
  sql=(UP/'install/sql/install.sql').read_text()
  self.assertEqual(sql.strip(),"ALTER TABLE `my_una_translator_items` ADD COLUMN `manually_edited` tinyint unsigned NOT NULL DEFAULT 0;")
  self.assertIn('`manually_edited` tinyint unsigned NOT NULL DEFAULT 0',(ROOT/'install/sql/install.sql').read_text())
  files={p.relative_to(UP/'source').as_posix() for p in (UP/'source').rglob('*') if p.is_file()}
  self.assertEqual(files,{'classes/MyUnaTranslatorEngine.php','classes/MyUnaTranslatorStore.php','classes/MyUnaTranslatorStudioPage.php','template/js/translator.js','template/css/translator.css','install/config.php','install/sql/install.sql','install/langs/en.xml'})
  frozen={'install/config.php': '1db69aa34b16f68e0e4addbb23ba8ebc2c864f0b12ab8ef30f6f00d18b7b6530', 'classes/MyUnaTranslatorStudioPage.php': 'e288a9a07a413f757d5f923c62c141fc6f9b0bd8b8a9d62802cae163e5f10ac0', 'classes/MyUnaTranslatorEngine.php': '88342b6d9521eb59dd670b4403efb516ac79c01a3f274ad6a189a0b19923fcfb', 'classes/MyUnaTranslatorStore.php': '02a7a946d50deb9f44cc9d27b3609e393aa356db5e2ccd417e9e492a9de15b8e', 'install/langs/en.xml': '5297dbbe3c7fbbcf5bd3e47514dcd9273443ca7a5c53b6817b38834f64bd0377', 'install/sql/install.sql': 'f4adcae485915494f05889935e69160defc1e89e20462bb556e8c82b82deeea0', 'template/css/translator.css': 'b144435604c71fdf1841ec6465712f8205d5b4e094759d8badc7a2cb03d8ab86', 'template/js/translator.js': 'd139ddf2d02a86cddaa46e01a503b09cbaa0b1a100dae9acf00dd1e5ee3926d0'}
  for f in files:self.assertEqual(hashlib.sha256((UP/'source'/f).read_bytes()).hexdigest(),frozen[f],f)
 def test_migration_precedes_runtime_and_only_new_language_keys_added(self):
  config=(UP/'install/config.php').read_text()
  self.assertIn("'version_from' => '0.1.10'",config)
  self.assertIn("'version_to' => '0.1.11'",config)
  self.assertLess(config.index("'execute_sql'"),config.index("'update_files'"))
  self.assertIn("'update_languages' => 1",config)
  xml=(UP/'install/langs/en.xml').read_text()
  self.assertEqual(xml.count('<string_add '),6)
  self.assertNotIn('<string_upd ',xml)
  self.assertIn("'version' => '0.1.11'",(UP/'source/install/config.php').read_text())
 def test_current_candidate_uses_una_15_compatibility(self):
  import re
  for path in [ROOT/'install/config.php', UP/'install/config.php', UP/'source/install/config.php']:
   with self.subTest(path=str(path.relative_to(ROOT))):
    declaration=re.search(r"'compatible_with'\s*=>\s*\[([^]]+)\]",path.read_text())
    self.assertIsNotNone(declaration)
    self.assertEqual(declaration.group(1).strip(), "'15.0.x'")
 def test_editor_cache_version(self):
  s=(UP/'source/classes/MyUnaTranslatorStudioPage.php').read_text()
  self.assertIn('translator.js?v=0.1.11-edit1',s)
  self.assertIn('translator.css?v=0.1.11-edit1',s)
if __name__=='__main__':unittest.main()

