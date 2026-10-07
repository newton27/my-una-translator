#!/usr/bin/env python3
"""Private stdin transport for LibreTranslate's official management CLI."""
import json
import sys

def main():
    raw = sys.stdin.buffer.read(16385)
    if len(raw) > 16384:
        raise SystemExit(2)
    try:
        args = json.loads(raw)
    except (ValueError, UnicodeDecodeError):
        raise SystemExit(2)
    if not isinstance(args, list) or not all(isinstance(arg, str) for arg in args):
        raise SystemExit(2)
    if len(args) < 4 or args[:2] != ['keys', '--api-keys-db-path'] or args[3] not in ('add', 'remove'):
        raise SystemExit(2)
    # Kept inside Python only: /proc cmdline contains no customer secret.
    sys.argv = ['ltmanage', *args]
    sys.path.insert(0, '/opt/my-una-translator/ltmanage-runtime')
    from libretranslate.manage import manage
    manage()

if __name__ == '__main__':
    main()
