import hashlib,json,unittest,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
class NativeUpdaterPackages(unittest.TestCase):
 def test_tested_implementation_is_unchanged(self):
  manifest=json.loads((ROOT/'docs/evidence/accepted-0.1.15.json').read_text())
  files=[x for x in manifest['file_manifest'] if x['path'].startswith('source/')]
  self.assertEqual(len(files),40)
  for f in files:
   p=ROOT/f['path'].removeprefix('source/')
   self.assertEqual(hashlib.sha256(p.read_bytes()).hexdigest(),f['sha256'],f['path'])
 def test_all_updater_payloads_match_customer_runtime(self):
  runtime=json.loads((ROOT/'BUILD.json').read_text())['runtime_files']
  for previous in ['0.1.11','0.1.12']:
   up=ROOT/f'updates/update_{previous}_0.1.15'
   found={p.relative_to(up/'source').as_posix() for p in (up/'source').rglob('*') if p.is_file()}
   self.assertEqual(found,set(runtime))
   for rel in runtime:self.assertEqual((up/'source'/rel).read_bytes(),(ROOT/rel).read_bytes(),rel)
   cfg=(up/'install/config.php').read_text()
   self.assertIn("'version_from' => '"+previous+"'",cfg)
   self.assertIn("'version_to' => '0.1.15'",cfg)
   self.assertIn("'execute_sql' => 1",cfg)
   self.assertIn("'update_files' => 1",cfg)
   self.assertIn("'update_languages' => 1",cfg)
 def test_built_archives_have_exact_hashes_and_no_core(self):
  manifest=json.loads((ROOT/'dist/package-manifest.json').read_text())
  self.assertEqual(len(manifest['artifacts']),3)
  for a in manifest['artifacts']:
   p=ROOT/'dist'/a['file'];self.assertEqual(hashlib.sha256(p.read_bytes()).hexdigest(),a['sha256'])
   with zipfile.ZipFile(p) as z:
    self.assertIsNone(z.testzip())
    self.assertEqual(set(z.namelist()),{m['path'] for m in a['members']})
    for n in z.namelist():
     self.assertTrue(n.startswith('my_una_translator/'));self.assertNotIn('..',Path(n).parts)
     self.assertFalse(any(x in Path(n).parts for x in ['node_modules','services','studio','inc','.git','dist','tests']))
 def test_native_icon_is_packaged(self):
  icon='template/images/icons/std-icon.svg'
  self.assertIn(icon,json.loads((ROOT/'BUILD.json').read_text())['runtime_files'])
  self.assertIn('<svg',(ROOT/icon).read_text())
if __name__=='__main__':unittest.main()
