"""Private, local-only LibreTranslate key lifecycle manager."""
from __future__ import annotations
import hashlib
import json
import os
import re
import sqlite3
import subprocess
import uuid
from datetime import datetime, timezone
from pathlib import Path
from cryptography.fernet import Fernet, InvalidToken

class ManagerError(Exception):
    """Safe-to-display manager error; never includes key material."""

class KeyManager:
    def __init__(self, metadata_db: str, api_keys_db: str, ltmanage: str, encryption_key_file: str,
                 max_requests: int, max_characters: int):
        self.metadata_db = str(metadata_db)
        self.api_keys_db = str(api_keys_db)
        self.ltmanage = str(ltmanage)
        self.encryption_key_file = str(encryption_key_file)
        self.max_requests = int(max_requests)
        self.max_characters = int(max_characters)
        if not os.path.isabs(self.ltmanage) or not os.path.isabs(self.api_keys_db):
            raise ManagerError('Key manager paths must be absolute.')
        if self.max_requests < 1 or self.max_characters < 1:
            raise ManagerError('Key manager ceilings must be configured.')
        self._cipher()
        self._initialize()

    def _cipher(self):
        try:
            mode = Path(self.encryption_key_file).stat().st_mode & 0o777
            if mode & 0o077:
                raise ManagerError('Encryption key file permissions must be 0600 or stricter.')
            return Fernet(Path(self.encryption_key_file).read_bytes().strip())
        except ManagerError:
            raise
        except Exception as exc:
            raise ManagerError('Key manager encryption key is unavailable.') from exc

    def _connect(self):
        db = sqlite3.connect(self.metadata_db, timeout=10)
        db.row_factory = sqlite3.Row
        db.execute('PRAGMA foreign_keys=ON')
        return db

    def _initialize(self):
        Path(self.metadata_db).parent.mkdir(parents=True, exist_ok=True)
        with self._connect() as db:
            db.executescript('''
                CREATE TABLE IF NOT EXISTS customer_keys (
                    id TEXT PRIMARY KEY, request_id TEXT UNIQUE NOT NULL, operation TEXT NOT NULL,
                    site_label TEXT NOT NULL, hostname TEXT NOT NULL, requests_per_minute INTEGER NOT NULL,
                    characters_per_request INTEGER NOT NULL, status TEXT NOT NULL, secret_ciphertext BLOB NOT NULL,
                    fingerprint TEXT NOT NULL, actor TEXT NOT NULL, created_at TEXT NOT NULL, revoked_at TEXT,
                    disclosed_at TEXT
                );
                CREATE TABLE IF NOT EXISTS audit (
                    id INTEGER PRIMARY KEY AUTOINCREMENT, key_id TEXT NOT NULL, event TEXT NOT NULL,
                    actor TEXT NOT NULL, at TEXT NOT NULL, details TEXT NOT NULL,
                    FOREIGN KEY(key_id) REFERENCES customer_keys(id)
                );
            ''')

    @staticmethod
    def _now():
        return datetime.now(timezone.utc).isoformat(timespec='seconds')

    @staticmethod
    def _actor(value):
        value = str(value or '').strip()
        if not value or len(value) > 80 or any(ord(c) < 32 for c in value):
            raise ManagerError('Administrator identity is invalid.')
        return value

    @staticmethod
    def _host(value):
        value = str(value or '').strip().rstrip('.')
        if not value or '://' in value or '/' in value or ':' in value or len(value) > 253:
            raise ManagerError('Enter a hostname without a scheme or path.')
        try:
            host = value.encode('idna').decode('ascii').lower()
        except UnicodeError as exc:
            raise ManagerError('Hostname is invalid.') from exc
        labels = host.split('.')
        if len(labels) < 2 or any(not re.fullmatch(r'[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?', x) for x in labels):
            raise ManagerError('Hostname is invalid.')
        return host

    @staticmethod
    def _positive_int(value, label):
        if isinstance(value, bool):
            raise ManagerError(f'{label} must be a positive whole number.')
        try:
            result = int(value)
        except (TypeError, ValueError) as exc:
            raise ManagerError(f'{label} must be a positive whole number.') from exc
        if result < 1 or str(result) != str(value).strip():
            raise ManagerError(f'{label} must be a positive whole number.')
        return result

    def _audit(self, db, key_id, event, actor, details):
        db.execute('INSERT INTO audit(key_id,event,actor,at,details) VALUES(?,?,?,?,?)',
                   (key_id, event, actor, self._now(), json.dumps(details, sort_keys=True)))

    def _run(self, args):
        try:
            proc = subprocess.run([self.ltmanage], input=json.dumps(args),
                stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True, timeout=20,
                check=False, env={'PATH':'/usr/bin:/bin', 'LANG':'C.UTF-8'})
        except (OSError, subprocess.TimeoutExpired) as exc:
            raise ManagerError('LibreTranslate key manager command is unavailable or timed out.') from exc
        if proc.returncode != 0:
            raise ManagerError('LibreTranslate rejected the key operation.')
        return proc.stdout.strip()

    def _add(self, secret, requests, characters):
        output = self._run(['keys', '--api-keys-db-path', self.api_keys_db, 'add', str(requests),
                            '--key', secret, '--char-limit', str(characters)])
        if output != secret:
            raise ManagerError('LibreTranslate returned an unexpected key-operation result.')

    def _remove(self, secret):
        self._run(['keys', '--api-keys-db-path', self.api_keys_db, 'remove', secret])

    def create(self, payload):
        if not isinstance(payload, dict) or set(payload) - {'site_label','hostname','requests_per_minute',
              'characters_per_request','request_id','actor','operation'}:
            raise ManagerError('Key request contains unsupported fields.')
        label = str(payload.get('site_label') or '').strip()
        if not label or len(label) > 120 or any(ord(c) < 32 for c in label):
            raise ManagerError('Site label is required and must be at most 120 characters.')
        hostname = self._host(payload.get('hostname'))
        requests = self._positive_int(payload.get('requests_per_minute'), 'Requests per minute')
        characters = self._positive_int(payload.get('characters_per_request'), 'Characters per request')
        if requests > self.max_requests or characters > self.max_characters:
            raise ManagerError('Requested limits exceed the configured service ceiling.')
        request_id = str(payload.get('request_id') or '')
        if not re.fullmatch(r'[A-Za-z0-9_-]{16,80}', request_id):
            raise ManagerError('Request identifier is invalid.')
        actor = self._actor(payload.get('actor'))
        operation = str(payload.get('operation') or 'create')
        if operation not in ('create','rotate'):
            raise ManagerError('Key operation is invalid.')
        cipher = self._cipher()
        with self._connect() as db:
            row = db.execute('SELECT * FROM customer_keys WHERE request_id=?', (request_id,)).fetchone()
            if row:
                same = (row['site_label'], row['hostname'], row['requests_per_minute'], row['characters_per_request'], row['operation']) == (label, hostname, requests, characters, operation)
                if not same:
                    raise ManagerError('Request identifier was already used for different key details.')
                if row['disclosed_at'] is not None:
                    raise ManagerError('This key secret was already revealed. Rotate it to issue a new secret.')
                if row['status'] == 'revoked':
                    raise ManagerError('This key request was already revoked.')
                key_id, secret = row['id'], cipher.decrypt(row['secret_ciphertext']).decode()
            else:
                key_id, secret = str(uuid.uuid4()), str(uuid.uuid4())
                db.execute('INSERT INTO customer_keys(id,request_id,operation,site_label,hostname,requests_per_minute,characters_per_request,status,secret_ciphertext,fingerprint,actor,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
                    (key_id,request_id,operation,label,hostname,requests,characters,'pending',cipher.encrypt(secret.encode()),hashlib.sha256(secret.encode()).hexdigest()[:12],actor,self._now()))
                self._audit(db,key_id,'issue_pending',actor,{'operation':operation,'requests_per_minute':requests,'characters_per_request':characters})
        # LibreTranslate accepts an explicit key; a retry of the pending request idempotently replaces the same UUID.
        self._add(secret, requests, characters)
        with self._connect() as db:
            row = db.execute('SELECT status,disclosed_at FROM customer_keys WHERE id=?', (key_id,)).fetchone()
            if row['status'] == 'pending':
                db.execute("UPDATE customer_keys SET status='active' WHERE id=?", (key_id,))
                self._audit(db,key_id,'issued',actor,{'operation':operation,'requests_per_minute':requests,'characters_per_request':characters})
                if row['disclosed_at'] is not None:
                    raise ManagerError('This key secret was already revealed. Rotate it to issue a new secret.')
            db.execute('UPDATE customer_keys SET disclosed_at=? WHERE id=?', (self._now(),key_id))
        return {'key_id':key_id,'site_label':label,'hostname':hostname,'requests_per_minute':requests,
                'characters_per_request':characters,'secret':secret,'secret_fingerprint':hashlib.sha256(secret.encode()).hexdigest()[:12]}

    def rotate(self, payload):
        required = {'key_id', 'site_label', 'hostname', 'requests_per_minute', 'characters_per_request', 'request_id', 'actor'}
        if not isinstance(payload, dict) or set(payload) != required:
            raise ManagerError('Rotate request is invalid.')
        key_id = str(payload.get('key_id') or '')
        try:
            uuid.UUID(key_id)
        except ValueError as exc:
            raise ManagerError('Key reference is invalid.') from exc
        with self._connect() as db:
            current = db.execute('SELECT status FROM customer_keys WHERE id=?', (key_id,)).fetchone()
        if current is None or current['status'] != 'active':
            raise ManagerError('Only an active key can be rotated.')
        replacement = {name: value for name, value in payload.items() if name != 'key_id'}
        replacement['operation'] = 'rotate'
        result = self.create(replacement)
        result['replaces_key_id'] = key_id
        return result

    def list_metadata(self):
        with self._connect() as db:
            rows = db.execute('SELECT id,operation,site_label,hostname,requests_per_minute,characters_per_request,status,fingerprint,actor,created_at,revoked_at FROM customer_keys ORDER BY created_at DESC,id').fetchall()
            return [dict(row) for row in rows]

    def revoke(self, payload):
        if not isinstance(payload, dict) or set(payload) != {'key_id','actor'}:
            raise ManagerError('Revoke request is invalid.')
        key_id = str(payload.get('key_id') or '')
        try:
            uuid.UUID(key_id)
        except ValueError as exc:
            raise ManagerError('Key reference is invalid.') from exc
        actor = self._actor(payload.get('actor'))
        with self._connect() as db:
            row = db.execute('SELECT * FROM customer_keys WHERE id=?', (key_id,)).fetchone()
            if row is None:
                raise ManagerError('Key record was not found.')
            if row['status'] == 'revoked':
                return {'key_id':key_id,'status':'revoked'}
            secret = self._cipher().decrypt(row['secret_ciphertext']).decode()
            self._remove(secret)
            db.execute("UPDATE customer_keys SET status='revoked',revoked_at=? WHERE id=?", (self._now(),key_id))
            self._audit(db,key_id,'revoked',actor,{'previous_status':row['status']})
        return {'key_id':key_id,'status':'revoked'}
