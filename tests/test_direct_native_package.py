"""Checks for the unpublished, complete 0.1.9 native updater artifact."""
import re
import importlib.util
import hashlib
import json
import subprocess
import sys
import tempfile
import unittest
from unittest import mock
import zipfile
from pathlib import Path
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
PREFIX = 'my_una_translator/updates/update_0.1.9_0.1.12/'


class DirectNativePackage(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        artifacts = ROOT.parent / 'packages'
        artifacts.mkdir(parents=True, exist_ok=True)
        cls.temporary = tempfile.TemporaryDirectory(prefix='translator-native-package-', dir=artifacts)
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

    def test_manifest_explicitly_identifies_file_digests(self):
        manifest = json.loads(self.package.with_suffix('.json').read_text())
        self.assertIsInstance(manifest['files'], list)
        with self.archive() as archive:
            self.assertEqual(len(manifest['files']), len(archive.namelist()))
            for entry in manifest['files']:
                self.assertEqual(set(entry), {'path', 'sha256'})
                self.assertEqual(entry['sha256'], hashlib.sha256(archive.read(PREFIX + entry['path'])).hexdigest())


class DirectNativeBuilderSafety(unittest.TestCase):
    def invoke(self, path):
        return subprocess.run([sys.executable, str(ROOT / 'scripts/build_native_019_candidate.py'),
                               '--output', str(path)], capture_output=True, text=True)

    def test_output_outside_artifact_directory_is_rejected_without_writes(self):
        with tempfile.TemporaryDirectory(prefix='native-outside-') as directory:
            output = Path(directory) / 'escaped.zip'
            result = self.invoke(output)
            self.assertNotEqual(result.returncode, 0)
            self.assertEqual(list(Path(directory).iterdir()), [])

    def test_symlink_escape_is_rejected_without_writes(self):
        artifacts = ROOT.parent / 'packages'
        artifacts.mkdir(parents=True, exist_ok=True)
        with tempfile.TemporaryDirectory(dir=artifacts) as inside, tempfile.TemporaryDirectory() as outside:
            (Path(inside) / 'escape').symlink_to(outside, target_is_directory=True)
            result = self.invoke(Path(inside) / 'escape/candidate.zip')
            self.assertNotEqual(result.returncode, 0)
            self.assertEqual(list(Path(outside).iterdir()), [])

    def test_non_zip_output_is_rejected_without_writes(self):
        artifacts = ROOT.parent / 'packages'
        artifacts.mkdir(parents=True, exist_ok=True)
        with tempfile.TemporaryDirectory(dir=artifacts) as directory:
            result = self.invoke(Path(directory) / 'candidate.json')
            self.assertNotEqual(result.returncode, 0)
            self.assertEqual(list(Path(directory).iterdir()), [])

    def test_sidecar_symlinks_are_rejected_without_overwriting_other_files(self):
        artifacts = ROOT.parent / 'packages'
        artifacts.mkdir(parents=True, exist_ok=True)
        for suffix in ('.json', '.zip.sha256'):
            with self.subTest(suffix=suffix), tempfile.TemporaryDirectory(dir=artifacts) as inside, \
                    tempfile.TemporaryDirectory(prefix='native-outside-') as outside:
                sentinel = Path(outside) / 'preserved'
                sentinel.write_bytes(b'preserve existing evidence')
                output = Path(inside) / 'candidate.zip'
                output.with_suffix(suffix).symlink_to(sentinel)
                result = self.invoke(output)
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual(sentinel.read_bytes(), b'preserve existing evidence')
                self.assertFalse(output.exists())

    def test_existing_different_archive_is_preserved(self):
        artifacts = ROOT.parent / 'packages'
        artifacts.mkdir(parents=True, exist_ok=True)
        with tempfile.TemporaryDirectory(dir=artifacts) as directory:
            output = Path(directory) / 'candidate.zip'
            output.write_bytes(b'preserve earlier package')
            result = self.invoke(output)
            self.assertNotEqual(result.returncode, 0)
            self.assertEqual(output.read_bytes(), b'preserve earlier package')

    def test_late_sidecar_symlinks_cannot_redirect_a_write(self):
        spec = importlib.util.spec_from_file_location('direct_native_builder', ROOT / 'scripts/build_native_019_candidate.py')
        native = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(native)
        artifacts = ROOT.parent / 'packages'
        artifacts.mkdir(parents=True, exist_ok=True)
        for suffix in ('.json', '.zip.sha256'):
            with self.subTest(suffix=suffix), tempfile.TemporaryDirectory(dir=artifacts) as inside, \
                    tempfile.TemporaryDirectory(prefix='native-outside-') as outside:
                sentinel = Path(outside) / 'preserved'
                sentinel.write_bytes(b'preserve evidence')
                output = Path(inside) / 'candidate.zip'
                serialize = native.json.dumps
                def add_late_link(value, *args, **kwargs):
                    if isinstance(value, dict) and value.get('module') == 'my_una_translator' and 'files' in value:
                        output.with_suffix(suffix).symlink_to(sentinel)
                    return serialize(value, *args, **kwargs)
                with mock.patch.object(native.json, 'dumps', side_effect=add_late_link), self.assertRaises(ValueError):
                    native.build(output, 'fixture')
                self.assertEqual(sentinel.read_bytes(), b'preserve evidence')

    def test_late_parent_directory_symlink_cannot_redirect_evidence(self):
        spec = importlib.util.spec_from_file_location('direct_native_builder', ROOT / 'scripts/build_native_019_candidate.py')
        native = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(native)
        artifacts = ROOT.parent / 'packages'
        artifacts.mkdir(parents=True, exist_ok=True)
        with tempfile.TemporaryDirectory(dir=artifacts) as inside, tempfile.TemporaryDirectory(prefix='native-outside-') as outside:
            parent = Path(inside) / 'package'
            parent.mkdir()
            output = parent / 'candidate.zip'
            serialize = native.json.dumps
            def replace_late_directory(value, *args, **kwargs):
                if isinstance(value, dict) and value.get('module') == 'my_una_translator' and 'files' in value:
                    parent.rename(Path(inside) / 'original-package')
                    parent.symlink_to(outside, target_is_directory=True)
                return serialize(value, *args, **kwargs)
            with mock.patch.object(native.json, 'dumps', side_effect=replace_late_directory), self.assertRaises(ValueError):
                native.build(output, 'fixture')
            self.assertEqual(list(Path(outside).iterdir()), [])


if __name__ == '__main__':
    unittest.main()
