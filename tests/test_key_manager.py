import sqlite3
import tempfile
import unittest
from pathlib import Path
from cryptography.fernet import Fernet
from services.key_manager.manager import KeyManager, ManagerError

FAKE_LTmanage = '''#!/usr/bin/env python3
import sqlite3, sys, uuid
args = sys.argv[1:]
db = args[args.index('--api-keys-db-path') + 1]
conn = sqlite3.connect(db)
conn.execute('CREATE TABLE IF NOT EXISTS api_keys (api_key TEXT PRIMARY KEY, req_limit INTEGER NOT NULL, char_limit INTEGER)')
if 'add' in args:
    req = int(args[args.index('add') + 1])
    char = int(args[args.index('--char-limit') + 1])
    key = args[args.index('--key') + 1] if '--key' in args else str(uuid.uuid4())
    conn.execute('INSERT INTO api_keys VALUES (?, ?, ?)', (key, req, char))
    conn.commit()
    print(key)
elif 'remove' in args:
    key = args[args.index('remove') + 1]
    conn.execute('DELETE FROM api_keys WHERE api_key = ?', (key,))
    conn.commit()
    print('removed')
else:
    sys.exit(2)
'''

class KeyManagerTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.lt_db = self.root / 'lt-test.db'
        self.store_db = self.root / 'manager-test.db'
        self.key_file = self.root / 'fernet.key'
        self.key_file.write_bytes(Fernet.generate_key())
        self.key_file.chmod(0o600)
        self.cli = self.root / 'ltmanage-test'
        self.cli.write_text(FAKE_LTmanage)
        self.cli.chmod(0o700)
        self.manager = KeyManager(str(self.store_db), str(self.lt_db), str(self.cli), str(self.key_file), 120, 10000)

    def tearDown(self):
        self.temp.cleanup()

    def create(self, **overrides):
        data = {'site_label': 'Customer UNA', 'hostname': 'customer.example', 'requests_per_minute': 60,
                'characters_per_request': 4000, 'request_id': 'request-0000000001', 'actor': 'admin-7'}
        data.update(overrides)
        return self.manager.create(data)

    def test_issue_persists_only_encrypted_secret_and_returns_once(self):
        first = self.create()
        self.assertIn('secret', first)
        self.assertNotEqual(first['secret_fingerprint'], first['secret'])
        row = self.manager.list_metadata()[0]
        self.assertNotIn('secret', row)
        self.assertNotIn(first['secret'], repr(row))
        with self.assertRaises(ManagerError):
            self.create()
        self.assertEqual(1, len(self.manager.list_metadata()))

    def test_rotation_keeps_old_key_active_until_explicit_revoke(self):
        old = self.create()
        new = self.manager.create({'site_label':'Customer UNA', 'hostname':'customer.example',
            'requests_per_minute':60, 'characters_per_request':4000, 'request_id':'request-0000000002',
            'actor':'admin-7', 'operation':'rotate'})
        rows = self.manager.list_metadata()
        self.assertEqual(2, len(rows))
        self.assertTrue(all(row['status'] == 'active' for row in rows))
        self.manager.revoke({'key_id': old['key_id'], 'actor':'admin-7'})
        rows = {row['id']: row for row in self.manager.list_metadata()}
        self.assertEqual('revoked', rows[old['key_id']]['status'])
        self.assertEqual('active', rows[new['key_id']]['status'])

    def test_rotation_issues_new_key_and_leaves_old_active(self):
        old = self.create()
        replacement = self.manager.rotate({
            'key_id': old['key_id'], 'site_label': 'Customer UNA', 'hostname': 'customer.example',
            'requests_per_minute': 60, 'characters_per_request': 4000,
            'request_id': 'request-0000000002', 'actor': 'admin-7',
        })
        self.assertEqual(replacement['replaces_key_id'], old['key_id'])
        self.assertNotEqual(replacement['secret'], old['secret'])
        self.assertEqual([row['status'] for row in self.manager.list_metadata()], ['active', 'active'])

    def test_over_ceiling_is_rejected_before_cli_or_record_creation(self):
        with self.assertRaises(ManagerError):
            self.create(requests_per_minute=121)
        self.assertEqual([], self.manager.list_metadata())
        self.assertFalse(self.lt_db.exists())

    def test_hostname_is_canonical_and_validated(self):
        self.create(hostname='WWW.Customer.Example.')
        self.assertEqual('www.customer.example', self.manager.list_metadata()[0]['hostname'])
        with self.assertRaises(ManagerError):
            self.create(hostname='https://customer.example', request_id='request-0000000002')

    def test_revoke_does_not_return_secret(self):
        issued = self.create()
        result = self.manager.revoke({'key_id':issued['key_id'], 'actor':'admin-7'})
        self.assertNotIn('secret', result)
        self.assertEqual('revoked', self.manager.list_metadata()[0]['status'])

if __name__ == '__main__':
    unittest.main()
