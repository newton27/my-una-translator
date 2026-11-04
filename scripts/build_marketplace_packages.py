#!/usr/bin/env python3
"""Build unpublished, explicit-root UNA transport ZIPs; preserve existing assets."""
import argparse
import hashlib
import io
import json
from pathlib import Path, PurePosixPath
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
ARTIFACT_ROOT = ROOT.parent / 'packages'
STAMP = (2026, 11, 4, 0, 0, 0)
RUNTIME_CHECKPOINT = 'cb08b5224a6f555cfcbb64c541cd087727e726bd'


def source_files(directory):
    result = {}
    for path in sorted(directory.rglob('*')):
        if path.is_symlink():
            raise ValueError('Source symlink: ' + str(path))
        if path.is_file():
            result[path.relative_to(directory).as_posix()] = path.read_bytes()
    return result


def zip_bytes(prefix, files):
    directories = {prefix}
    for name in files:
        if name.startswith('/') or '..' in PurePosixPath(name).parts or '\\' in name:
            raise ValueError('Unsafe archive path: ' + name)
        parent = PurePosixPath(name).parent
        while str(parent) != '.':
            directories.add(prefix + str(parent) + '/')
            parent = parent.parent
    buffer = io.BytesIO()
    with zipfile.ZipFile(buffer, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        # UNA consumes getNameIndex(0) as the package directory.
        for name in [prefix] + sorted(directories - {prefix}):
            info = zipfile.ZipInfo(name, STAMP)
            info.create_system = 3
            info.external_attr = (0o40755 << 16) | 0x10
            info.compress_type = zipfile.ZIP_STORED
            archive.writestr(info, b'')
        for name, body in sorted(files.items()):
            info = zipfile.ZipInfo(prefix + name, STAMP)
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, body, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    return buffer.getvalue()


def write_package(output, filename, prefix, files, source_commit, baseline, target):
    body = zip_bytes(prefix, files)
    if body != zip_bytes(prefix, files):
        raise ValueError('Repeat build changed bytes: ' + filename)
    path = output / filename
    if path.exists() and path.read_bytes() != body:
        raise ValueError('Refusing to replace existing artifact: ' + filename)
    with zipfile.ZipFile(io.BytesIO(body)) as archive:
        if archive.testzip() is not None:
            raise ValueError('ZIP integrity failed: ' + filename)
        first = archive.infolist()[0]
        if first.filename != prefix or not first.is_dir():
            raise ValueError('Missing explicit native ZIP root: ' + filename)
        names = archive.namelist()
        actual = {name[len(prefix):]: archive.read(name) for name in names if not name.endswith('/')}
        if len(names) != len(set(names)) or actual != files:
            raise ValueError('ZIP source inventory/bytes differ: ' + filename)
    path.write_bytes(body)
    digest = hashlib.sha256(body).hexdigest()
    record = {'filename': filename, 'bytes': len(body), 'files': len(files), 'sha256': digest,
              'native_root': prefix, 'source_commit': source_commit,
              'runtime_checkpoint': RUNTIME_CHECKPOINT, 'version_from': baseline, 'version_to': target,
              'status': 'UNPUBLISHED; explicit-root transport acceptance required',
              'file_manifest': [{'path': name, 'bytes': len(data), 'sha256': hashlib.sha256(data).hexdigest()}
                                for name, data in sorted(files.items())]}
    path.with_suffix('.json').write_text(json.dumps(record, indent=2) + '\n')
    path.with_suffix('.zip.sha256').write_text(digest + '  ' + filename + '\n')
    return record


def build_packages(output, source_commit):
    output = Path(output).resolve()
    if ARTIFACT_ROOT.is_symlink() or not output.is_relative_to(ARTIFACT_ROOT.resolve()):
        raise ValueError('Output must remain inside the package artifact directory')
    output.mkdir(parents=True, exist_ok=True)
    full = {}
    for directory in ('classes', 'install', 'template'):
        full.update({directory + '/' + name: body for name, body in source_files(ROOT / directory).items()})
    full['request.php'] = (ROOT / 'request.php').read_bytes()
    if len(full) != 35:
        raise ValueError('Expected the reviewed 35-file runtime')
    records = [write_package(output, 'My-UNA-Translator-0.1.12.zip', 'my_una_translator/',
                             full, source_commit, None, '0.1.12')]
    for baseline, target, count in [('0.1.9', '0.1.12', 40), ('0.1.10', '0.1.11', 12),
                                    ('0.1.11', '0.1.12', 32)]:
        directory = 'update_' + baseline + '_' + target
        payload = source_files(ROOT / 'updates' / directory)
        if len(payload) != count:
            raise ValueError('Unexpected native updater inventory: ' + directory)
        filename = 'My-UNA-Translator-Update-' + baseline + '-to-' + target + '.zip'
        records.append(write_package(output, filename, directory + '/', payload,
                                     source_commit, baseline, target))
    (output / 'manifest.json').write_text(json.dumps(records, indent=2) + '\n')
    (output / 'SHA256SUMS').write_text(''.join(r['sha256'] + '  ' + r['filename'] + '\n' for r in records))
    return records


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=ARTIFACT_ROOT / 'marketplace')
    parser.add_argument('--source-commit', default=None)
    args = parser.parse_args()
    commit = args.source_commit or subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
    print(json.dumps([{k: v for k, v in r.items() if k != 'file_manifest'}
                      for r in build_packages(args.output, commit)], indent=2))
