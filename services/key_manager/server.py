#!/usr/bin/env python3
"""Private newline-delimited JSON API for the Studio key console."""
import json
import os
import socket
import struct
import sys
from pathlib import Path

from .manager import KeyManager, ManagerError

MAX_REQUEST = 16384
SOCKET_MODE = 0o660


def handle_request(raw, manager):
    if not isinstance(raw, bytes) or len(raw) > MAX_REQUEST:
        raise ManagerError('Request is too large.')
    try:
        payload = json.loads(raw.decode('utf-8'))
    except (UnicodeDecodeError, json.JSONDecodeError) as exc:
        raise ManagerError('Request is invalid.') from exc
    if not isinstance(payload, dict):
        raise ManagerError('Request is invalid.')
    operation = payload.get('operation')
    if operation == 'list' and set(payload) in ({'operation'}, {'operation', 'actor'}):
        if 'actor' in payload:
            if not isinstance(payload['actor'], str):
                raise ManagerError('Request is invalid.')
            KeyManager._actor(payload['actor'])
        return {'items': manager.list_metadata()}
    if operation in ('create', 'rotate'):
        expected = {'operation', 'site_label', 'hostname', 'requests_per_minute', 'characters_per_request', 'request_id', 'actor'}
        required = expected | ({'key_id'} if operation == 'rotate' else set())
        if set(payload) != required:
            raise ManagerError('Request is invalid.')
        return getattr(manager, operation)({key: payload[key] for key in required - {'operation'}})
    if operation == 'revoke' and set(payload) == {'operation', 'key_id', 'actor'}:
        return manager.revoke({'key_id': payload['key_id'], 'actor': payload['actor']})
    raise ManagerError('Operation is invalid.')


def peer_uid(conn):
    if not hasattr(socket, 'SO_PEERCRED'):
        raise ManagerError('Peer verification is unavailable.')
    creds = conn.getsockopt(socket.SOL_SOCKET, socket.SO_PEERCRED, struct.calcsize('3i'))
    _pid, uid, _gid = struct.unpack('3i', creds)
    return uid


def serve_client(conn, manager, allowed_uid):
    try:
        conn.settimeout(10)
        if peer_uid(conn) != allowed_uid:
            raise ManagerError('Local caller is not authorized.')
        data = bytearray()
        while len(data) <= MAX_REQUEST:
            chunk = conn.recv(min(4096, MAX_REQUEST + 1 - len(data)))
            if not chunk:
                break
            data.extend(chunk)
            if b'\n' in chunk:
                break
        raw = bytes(data).split(b'\n', 1)[0]
        response = {'ok': True, 'data': handle_request(raw, manager)}
    except ManagerError as exc:
        response = {'ok': False, 'message': str(exc)}
    except Exception:
        response = {'ok': False, 'message': 'Key manager request failed.'}
    try:
        conn.sendall(json.dumps(response, separators=(',', ':')).encode('utf-8') + b'\n')
    except OSError:
        pass


def serve(socket_path, manager, allowed_uid):
    path = Path(socket_path)
    path.parent.mkdir(parents=True, exist_ok=True)
    if path.exists() or path.is_symlink():
        if not path.is_socket():
            raise RuntimeError('Refusing to replace a non-socket path.')
        path.unlink()
    server = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    server.bind(str(path))
    os.chmod(path, SOCKET_MODE)
    server.listen(16)
    try:
        while True:
            conn, _ = server.accept()
            with conn:
                serve_client(conn, manager, allowed_uid)
    finally:
        server.close()
        path.unlink(missing_ok=True)


def main():
    if len(sys.argv) != 9:
        raise SystemExit('usage: server SOCKET STORE_DB LT_KEYS_DB LTMANAGE FERNET_KEY ALLOWED_UID REQUEST_LIMIT CHARACTER_LIMIT')
    socket_path, store_db, lt_db, ltmanage, fernet_key = sys.argv[1:6]
    allowed_uid, request_limit, character_limit = map(int, sys.argv[6:9])
    manager = KeyManager(store_db, lt_db, ltmanage, fernet_key, request_limit, character_limit)
    serve(socket_path, manager, allowed_uid)


if __name__ == '__main__':
    main()
