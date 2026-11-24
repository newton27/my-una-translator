#!/usr/bin/env python3
"""Reproducible customer installer and matching native update packages."""
import hashlib
import json
from pathlib import Path
import zipfile

ROOT = Path(__file__).resolve().parents[1]
VERSION = '0.1.15'

def digest(data):
    return hashlib.sha256(data).hexdigest()

def archive(path, entries):
    with zipfile.ZipFile(path, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for name, data in sorted(entries.items()):
            assert name and not name.startswith('/') and '..' not in Path(name).parts
            info = zipfile.ZipInfo(name, (2026, 11, 24, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            z.writestr(info, data)
    with zipfile.ZipFile(path) as z:
        assert z.testzip() is None and len(z.namelist()) == len(entries)
    return {'file': path.name, 'bytes': path.stat().st_size, 'sha256': digest(path.read_bytes()),
            'members': [{'path': p, 'bytes': len(b), 'sha256': digest(b)} for p, b in sorted(entries.items())]}

def main():
    out = ROOT / 'dist'
    out.mkdir(exist_ok=True)
    files = json.loads((ROOT / 'BUILD.json').read_text())['runtime_files']
    assert len(files) == len(set(files))
    full = {'my_una_translator/' + p: (ROOT / p).read_bytes() for p in files}
    results = [archive(out / f'My-UNA-Translator-{VERSION}.zip', full)]
    for previous in ['0.1.11', '0.1.12']:
        base = ROOT / f'updates/update_{previous}_{VERSION}'
        entries = {'my_una_translator/updates/' + base.name + '/' + p.relative_to(base).as_posix(): p.read_bytes()
                   for p in base.rglob('*') if p.is_file()}
        assert all((base / 'source' / p).read_bytes() == (ROOT / p).read_bytes() for p in files)
        assert len([p for p in entries if '/source/' in p]) == len(files)
        results.append(archive(out / f'My-UNA-Translator-Update-{previous}-to-{VERSION}.zip', entries))
    (out / 'SHA256SUMS.txt').write_text(''.join(f"{r['sha256']}  {r['file']}\n" for r in results))
    (out / 'package-manifest.json').write_text(json.dumps({'version': VERSION, 'artifacts': results}, indent=2) + '\n')
    print(json.dumps([{k: v for k, v in r.items() if k != 'members'} for r in results], indent=2))

if __name__ == '__main__':
    main()
