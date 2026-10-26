import unittest
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
  for f in files:self.assertEqual((ROOT/f).read_bytes(),(UP/'source'/f).read_bytes(),f)
 def test_migration_precedes_runtime_and_only_new_language_keys_added(self):
  config=(UP/'install/config.php').read_text()
  self.assertIn("'version_from' => '0.1.10'",config)
  self.assertIn("'version_to' => '0.1.11'",config)
  self.assertLess(config.index("'execute_sql'"),config.index("'update_files'"))
  self.assertIn("'update_languages' => 1",config)
  xml=(UP/'install/langs/en.xml').read_text()
  self.assertEqual(xml.count('<string_add '),6)
  self.assertNotIn('<string_upd ',xml)
  self.assertIn("'version' => '0.1.11'",(ROOT/'install/config.php').read_text())
 def test_editor_cache_version(self):
  s=(ROOT/'classes/MyUnaTranslatorStudioPage.php').read_text()
  self.assertIn('translator.js?v=0.1.11-edit1',s)
  self.assertIn('translator.css?v=0.1.11-edit1',s)
if __name__=='__main__':unittest.main()
