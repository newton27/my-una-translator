"""Checks for the unpublished, complete 0.1.9 native updater artifact."""
import re
import subprocess
import sys
import tempfile
import unittest
import zipfile
from pathlib import Path
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
PREFIX = 'my_una_translator/updates/update_0.1.9_0.1.12/'


class DirectNativePackage(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temporary = tempfile.TemporaryDirectory(prefix='translator-native-package-')
        cls.addClassCleanup(cls.temporary.cleanup)
        cls.package = Path(cls.temporary.name) / 'candidate.zip'
        subprocess.run([sys.executable, str(ROOT / 'scripts/build_native_019_candidate.py'),
                        '--output', str(cls.package)], check=True, capture_output=True, text=True)

    def archive(self):
        self.assertTrue(self.package.is_file(), 'direct 0.1.9-to-0.1.12 native updater artifact is missing')
        return zipfile.ZipFile(self.package)

    def test_complete_native_overlay_and_cleanup(self):
        with self.archive() as archive:
            self.assertIsNone(archive.testzip())
            names = archive.namelist()
            self.assertEqual(len(names), len(set(names)))
            for name in names:
                self.assertTrue(name.startswith(PREFIX), name)
                self.assertNotIn('..', Path(name).parts)
            runtime = {p.relative_to(ROOT).as_posix(): p.read_bytes()
                       for directory in ('classes', 'install', 'template')
                       for p in (ROOT / directory).rglob('*') if p.is_file()}
            runtime['request.php'] = (ROOT / 'request.php').read_bytes()
            payload = {name[len(PREFIX + 'source/'):]: archive.read(name)
                       for name in names if name.startswith(PREFIX + 'source/')}
            self.assertEqual(payload, runtime)
            self.assertEqual(len(payload), 35)
            self.assertEqual(payload['install/sql/uninstall.sql'],
                             (ROOT / 'install/sql/uninstall.sql').read_bytes())
            self.assertIn(b'my_una_translator_support_receipts', payload['install/sql/uninstall.sql'])
            ET.fromstring(payload['template/images/icons/std-icon.svg'])

    def test_native_versions_and_order(self):
        with self.archive() as archive:
            config = archive.read(PREFIX + 'install/config.php').decode()
            for field, expected in [('version_from', '0.1.9'), ('version_to', '0.1.12'),
                                    ('module_dir', 'newton/my_una_translator/'),
                                    ('home_dir', 'newton/my_una_translator/updates/update_0.1.9_0.1.12/')]:
                self.assertRegex(config, repr(field) + r'\s*=>\s*' + re.escape(repr(expected)))
            self.assertIn("'compatible_with' => ['15.0.x']", config)
            self.assertLess(config.index("'execute_sql'"), config.index("'update_files'"))
            self.assertLess(config.index("'update_files'"), config.index("'update_languages'"))
            self.assertIn("'version' => '0.1.12'", archive.read(PREFIX + 'source/install/config.php').decode())

    def test_additive_migrations_and_language_delta(self):
        with self.archive() as archive:
            self.assertEqual(archive.read(PREFIX + 'install/sql/preview.sql'),
                             (ROOT / 'updates/update_0.1.10_0.1.11/install/sql/install.sql').read_bytes())
            self.assertEqual(archive.read(PREFIX + 'install/sql/install.sql'),
                             (ROOT / 'updates/update_0.1.11_0.1.12/install/sql/install.sql').read_bytes())
            xml = ET.fromstring(archive.read(PREFIX + 'install/langs/en.xml'))
            self.assertEqual(len(xml.findall('string_add')), 6)
            self.assertEqual(xml.findall('string_del'), [])
            self.assertEqual(xml.findall('string_upd'), [])
            self.assertEqual(archive.read(PREFIX + 'install/langs/en.xml'),
                             (ROOT / 'updates/update_0.1.10_0.1.11/install/langs/en.xml').read_bytes())


if __name__ == '__main__':
    unittest.main()
