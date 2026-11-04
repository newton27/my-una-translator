"""Exercise the ZIP contract consumed by UNA's native Marketplace unarchiver."""
import importlib.util
from pathlib import Path
import tempfile
import unittest
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / 'scripts/build_marketplace_packages.py'
builder = None
if SCRIPT.is_file():
    spec = importlib.util.spec_from_file_location('marketplace_packages', SCRIPT)
    builder = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(builder)


class MarketplacePackages(unittest.TestCase):
    def setUp(self):
        self.assertIsNotNone(builder, 'explicit native-root package builder is missing')
        artifacts = ROOT.parent / 'packages'
        artifacts.mkdir(exist_ok=True)
        self.temp = tempfile.TemporaryDirectory(prefix='marketplace-test-', dir=artifacts)
        self.addCleanup(self.temp.cleanup)
        self.output = Path(self.temp.name)

    def test_first_entry_is_native_root_and_every_payload_byte_is_preserved(self):
        records = builder.build_packages(self.output, 'fixture-source')
        self.assertEqual(len(records), 4)
        roots = ['my_una_translator/', 'update_0.1.9_0.1.12/',
                 'update_0.1.10_0.1.11/', 'update_0.1.11_0.1.12/']
        self.assertEqual([r['files'] for r in records], [35, 40, 12, 32])
        for record, prefix in zip(records, roots):
            with self.subTest(root=prefix), zipfile.ZipFile(self.output / record['filename']) as archive:
                self.assertIsNone(archive.testzip())
                self.assertEqual(archive.infolist()[0].filename, prefix)
                self.assertTrue(archive.infolist()[0].is_dir())
                self.assertEqual(len(archive.namelist()), len(set(archive.namelist())))
                expected = {}
                if prefix == 'my_una_translator/':
                    for directory in ('classes', 'install', 'template'):
                        expected.update({p.relative_to(ROOT).as_posix(): p.read_bytes()
                                         for p in (ROOT / directory).rglob('*') if p.is_file()})
                    expected['request.php'] = (ROOT / 'request.php').read_bytes()
                else:
                    source = ROOT / 'updates' / prefix.rstrip('/')
                    expected = {p.relative_to(source).as_posix(): p.read_bytes()
                                for p in source.rglob('*') if p.is_file()}
                actual = {n[len(prefix):]: archive.read(n) for n in archive.namelist()
                          if not n.endswith('/')}
                self.assertEqual(actual, expected)
                self.assertTrue(all(n.startswith(prefix) and '..' not in Path(n).parts
                                    for n in archive.namelist()))
                self.assertFalse(any('/tests/' in n or '/docs/' in n for n in archive.namelist()))
                if prefix in ('my_una_translator/', 'update_0.1.9_0.1.12/'):
                    icon = ('source/' if prefix.startswith('update_') else '') + 'template/images/icons/std-icon.svg'
                    self.assertEqual(actual[icon], (ROOT / 'template/images/icons/std-icon.svg').read_bytes())

    def test_repeat_build_is_identical(self):
        records = builder.build_packages(self.output, 'fixture-source')
        original = {r['filename']: (self.output / r['filename']).read_bytes() for r in records}
        builder.build_packages(self.output, 'fixture-source')
        self.assertEqual(original, {name: (self.output / name).read_bytes() for name in original})

    def test_changed_existing_archive_is_preserved_and_rejected(self):
        records = builder.build_packages(self.output, 'fixture-source')
        archive = self.output / records[0]['filename']
        archive.write_bytes(b'preserve existing bytes')
        with self.assertRaisesRegex(ValueError, 'existing artifact'):
            builder.build_packages(self.output, 'fixture-source')
        self.assertEqual(archive.read_bytes(), b'preserve existing bytes')


if __name__ == '__main__':
    unittest.main()
