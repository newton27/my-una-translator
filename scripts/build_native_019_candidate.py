#!/usr/bin/env python3
"""Build the unpublished direct updater without changing existing release assets."""
import argparse
import hashlib
import json
import re
from pathlib import Path
import zipfile

ROOT = Path(__file__).resolve().parents[1]
DIRECTORY = 'update_0.1.9_0.1.12'
PREFIX = 'my_una_translator/updates/' + DIRECTORY + '/'
ARTIFACT_ROOT = ROOT.parent / 'packages'


def build(output):
    # CLI callers may choose a name, never a destination outside this workspace's artifacts.
    if ARTIFACT_ROOT.is_symlink():
        raise ValueError('The package artifact directory must not be a symlink')
    artifact_root = ARTIFACT_ROOT.resolve()
    output = Path(output).resolve()
    if not output.is_relative_to(artifact_root):
        raise ValueError('Output must remain inside the package artifact directory')
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9._-]*\.zip', output.name):
        raise ValueError('Output must have a safe ZIP filename')
    source = ROOT / 'updates' / DIRECTORY
    files = sorted(p for p in source.rglob('*') if p.is_file())
    if len(files) != 40 or any(p.is_symlink() for p in source.rglob('*')):
        raise ValueError('Expected exactly 40 regular native updater files')
    output.parent.mkdir(parents=True, exist_ok=True)
    manifest = []
    with zipfile.ZipFile(output, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path in files:
            relative = path.relative_to(source).as_posix()
            data = path.read_bytes()
            entry = zipfile.ZipInfo(PREFIX + relative, date_time=(1980, 1, 1, 0, 0, 0))
            entry.compress_type = zipfile.ZIP_DEFLATED
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            archive.writestr(entry, data, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
            manifest.append({'path': relative, 'sha256': hashlib.sha256(data).hexdigest()})
    with zipfile.ZipFile(output) as archive:
        if archive.testzip() is not None:
            raise ValueError('ZIP integrity failed')
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    evidence = {'status': 'UNPUBLISHED; native Studio acceptance NOT TESTED',
                'module': 'my_una_translator', 'version_from': '0.1.9', 'version_to': '0.1.12',
                'payload_source_commit': 'cb08b5224a6f555cfcbb64c541cd087727e726bd',
                'payload_source_snapshot': 'ed3c314a68149eaf1d5799dc92a6479252505c76',
                'updater_source_commit': '66e81d05908accc49b67ce5656d782173239c9f3',
                'package': output.name, 'bytes': output.stat().st_size,
                'file_count': len(files), 'sha256': digest, 'files': manifest}
    output.with_suffix('.json').write_text(json.dumps(evidence, indent=2) + '\n')
    output.with_suffix('.zip.sha256').write_text(digest + '  ' + output.name + '\n')
    print(json.dumps({k: v for k, v in evidence.items() if k != 'files'}))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=ARTIFACT_ROOT / 'translator-0.1.9-to-0.1.12-candidate.zip')
    build(parser.parse_args().output)
