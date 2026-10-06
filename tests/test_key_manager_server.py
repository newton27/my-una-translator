import json
import unittest
from unittest.mock import Mock

from services.key_manager.manager import ManagerError
from services.key_manager.server import handle_request, serve_client


class KeyManagerServerTests(unittest.TestCase):
    def setUp(self):
        self.manager = Mock()

    def test_list_returns_safe_metadata(self):
        self.manager.list_metadata.return_value = [{'id': 'id-1', 'status': 'active'}]
        self.assertEqual(handle_request(b'{"operation":"list"}', self.manager), {'items': [{'id': 'id-1', 'status': 'active'}]})
        self.manager.list_metadata.assert_called_once_with()

    def test_create_dispatches_only_expected_fields(self):
        payload = b'{"operation":"create","site_label":"Customer","hostname":"customer.example","requests_per_minute":60,"characters_per_request":4000,"request_id":"request-000000001","actor":"admin-1"}'
        handle_request(payload, self.manager)
        self.manager.create.assert_called_once()
        self.assertEqual(self.manager.create.call_args.args[0]['hostname'], 'customer.example')

    def test_rejects_unknown_operation_and_extra_fields(self):
        for raw in (b'{"operation":"shell"}', b'{"operation":"list","secret":"x"}'):
            with self.subTest(raw=raw), self.assertRaises(ManagerError):
                handle_request(raw, self.manager)

    def test_rejects_oversized_and_malformed_request(self):
        with self.assertRaises(ManagerError):
            handle_request(b'x' * 16385, self.manager)
        with self.assertRaises(ManagerError):
            handle_request(b'{', self.manager)


    def test_socket_client_rejects_wrong_peer_uid(self):
        from unittest.mock import patch
        connection = Mock()
        with patch('services.key_manager.server.peer_uid', return_value=2000):
            serve_client(connection, self.manager, 1000)
        connection.recv.assert_not_called()
        response = json.loads(connection.sendall.call_args.args[0].decode('utf-8'))
        self.assertFalse(response['ok'])
        self.manager.list_metadata.assert_not_called()


if __name__ == '__main__':
    unittest.main()
