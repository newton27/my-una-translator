import unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
class ReleaseBoundary(unittest.TestCase):
 def test_no_operator_files_in_customer_source(self):
  for item in ['services','classes/MyUnaTranslatorKeyClient.php','template/js/key-console.js','tests/key_console.cjs','tests/test_key_manager.py','tests/test_key_manager_server.py','docs/libretranslate-customer-keys.md','docs/KEY-ISSUER-DEPLOYMENT.md','install/my-una-translator-key-manager.service.example']:
   self.assertFalse((ROOT/item).exists(),item)
 def test_customer_studio_has_no_issuer_hook_or_panel(self):
  source=(ROOT/'classes/MyUnaTranslatorStudioPage.php').read_text()
  for token in ['MyUnaTranslatorKeyClient','mut-key-console','key-console.js',"strpos($action, 'key_')"]:
   self.assertNotIn(token,source)
 def test_customer_readme_does_not_offer_operator_console(self):
  self.assertNotIn('customer API keys** panel',(ROOT/'README.md').read_text())
if __name__=='__main__':unittest.main()
