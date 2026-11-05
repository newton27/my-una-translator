#!/usr/bin/env python3
"""Build unpublished, explicit-root UNA transport ZIPs; preserve existing assets."""
import argparse
import hashlib
import io
import json
from pathlib import Path, PurePosixPath
import re
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


def artifact_directory(output):
    output = Path(output).resolve()
    if ARTIFACT_ROOT.is_symlink() or not output.is_relative_to(ARTIFACT_ROOT.resolve()):
        raise ValueError('Output must remain inside the package artifact directory')
    return output


def checked_destination(path, directory):
    if path.is_symlink() or path.resolve().parent != directory:
        raise ValueError('Artifact destination must be a confined regular file: ' + path.name)
    if path.exists() and not path.is_file():
        raise ValueError('Artifact destination must be a regular file: ' + path.name)
    return path


def write_package(output, filename, prefix, files, source_commit, baseline, target):
    output = artifact_directory(output)
    if not isinstance(filename, str) or not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9._-]*\.zip', filename):
        raise ValueError('Output must have a safe ZIP filename')
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9._-]*/', prefix):
        raise ValueError('Expected one safe native root directory')
    path = checked_destination(output / filename, output)
    metadata = checked_destination(path.with_suffix('.json'), output)
    checksum = checked_destination(path.with_suffix('.zip.sha256'), output)
    body = zip_bytes(prefix, files)
    if body != zip_bytes(prefix, files):
        raise ValueError('Repeat build changed bytes: ' + filename)
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
    if not path.exists():
        path.write_bytes(body)
    digest = hashlib.sha256(body).hexdigest()
    record = {'filename': filename, 'bytes': len(body), 'files': len(files), 'sha256': digest,
              'native_root': prefix, 'source_commit': source_commit,
              'runtime_base_commit': RUNTIME_CHECKPOINT, 'version_from': baseline, 'version_to': target,
              'status': 'UNPUBLISHED; explicit-root transport acceptance required',
              'file_manifest': [{'path': name, 'bytes': len(data), 'sha256': hashlib.sha256(data).hexdigest()}
                                for name, data in sorted(files.items())]}
    metadata.write_text(json.dumps(record, indent=2) + '\n')
    checksum.write_text(digest + '  ' + filename + '\n')
    return record


def build_packages(output, source_commit):
    output = artifact_directory(output)
    output.mkdir(parents=True, exist_ok=True)
    manifest = checked_destination(output / 'manifest.json', output)
    sums = checked_destination(output / 'SHA256SUMS', output)
    full = {}
    for directory in ('classes', 'install', 'template'):
        full.update({directory + '/' + name: body for name, body in source_files(ROOT / directory).items()})
    full['request.php'] = (ROOT / 'request.php').read_bytes()
    if len(full) != 35:
        raise ValueError('Expected the reviewed 35-file runtime')
    records = [write_package(output, 'My-UNA-Translator-0.1.12-reconciled-r3.zip', 'my_una_translator/',
                             full, source_commit, None, '0.1.12')]
    for baseline, target, count in [('0.1.9', '0.1.12', 41), ('0.1.10', '0.1.11', 12),
                                    ('0.1.11', '0.1.12', 40)]:
        directory = 'update_' + baseline + '_' + target
        payload = source_files(ROOT / 'updates' / directory)
        if len(payload) != count:
            raise ValueError('Unexpected native updater inventory: ' + directory)
        filename = 'My-UNA-Translator-Update-' + baseline + '-to-' + target + ('-reconciled-r3' if target == '0.1.12' else '') + '.zip'
        records.append(write_package(output, filename, directory + '/', payload,
                                     source_commit, baseline, target))
    manifest.write_text(json.dumps(records, indent=2) + '\n')
    sums.write_text(''.join(r['sha256'] + '  ' + r['filename'] + '\n' for r in records))
    return records


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=ARTIFACT_ROOT / 'translator-reconciled-20261105-r3')
    parser.add_argument('--source-commit', default=None)
    args = parser.parse_args()
    commit = args.source_commit or subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
    print(json.dumps([{k: v for k, v in r.items() if k != 'file_manifest'}
                      for r in build_packages(args.output, commit)], indent=2))
