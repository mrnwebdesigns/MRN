"""Credential isolation and idempotent new-site identity regression coverage."""
import json
import os
from pathlib import Path
import sys
import subprocess
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))
import enrollment_credentials as broker

VAULT = 'a' * 26
ITEM = 'b' * 26
REQUEST = {'account': broker.ACCOUNT, 'domain': 'example.mrndev.io', 'site_user': 'example-stack'}
CONFIG = {'vault_id': VAULT, 'new_site_identity': {'mode': 'create-for-new-site', 'host': 'host.example'}}
TITLE = 'MRN Dev Deployment - example.mrndev.io - example-stack'
KEY = '-----BEGIN OPENSSH PRIVATE KEY-----\nfixture\n-----END OPENSSH PRIVATE KEY-----'


def item():
    return {'id': ITEM, 'vault': {'id': VAULT}, 'title': TITLE, 'category': 'SSH_KEY',
            'fields': [{'id': 'private_key', 'label': 'private key', 'value': KEY},
                       *[{'id': k, 'label': k, 'value': v} for k, v in
                         {'mrn_domain': REQUEST['domain'], 'mrn_site_user': REQUEST['site_user'],
                          'mrn_host': 'host.example'}.items()]]}


class NewSiteIdentity(unittest.TestCase):
    def test_first_request_creates_one_vault_backed_key_and_reads_it_by_id(self):
        with patch.object(broker, 'op_json', side_effect=[[], {'id': ITEM}, item()]) as call:
            self.assertEqual(KEY, broker.deployment_identity(CONFIG, REQUEST, {}))
            argv = call.call_args_list[1].args[0]
            self.assertIn('--ssh-generate-key=ed25519', argv)
            self.assertIn(VAULT, argv)
            self.assertNotIn(KEY, json.dumps(argv))
            self.assertEqual(['item', 'get', ITEM, '--vault', VAULT], call.call_args.args[0])

    def test_retry_after_unknown_create_result_reuses_existing_key(self):
        with patch.object(broker, 'op_json', side_effect=[[{'id': ITEM, 'title': TITLE}], item()]) as call:
            self.assertEqual(KEY, broker.deployment_identity(CONFIG, REQUEST, {}))
            self.assertEqual(2, call.call_count)
            self.assertNotIn('create', json.dumps(call.call_args_list))

    def test_failed_inventory_does_not_create_replacement(self):
        with patch.object(broker, 'op_json', side_effect=ValueError('unavailable')) as call:
            with self.assertRaises(ValueError): broker.deployment_identity(CONFIG, REQUEST, {})
            self.assertEqual(1, call.call_count)

    def test_duplicate_titles_block_without_rotation(self):
        with patch.object(broker, 'op_json', return_value=[{'id': ITEM, 'title': TITLE}] * 2) as call:
            with self.assertRaises(ValueError): broker.deployment_identity(CONFIG, REQUEST, {})
            self.assertEqual(1, call.call_count)

    def test_wrong_host_owner_domain_vault_or_category_is_rejected(self):
        for attribute in ['mrn_host', 'mrn_domain', 'mrn_site_user', 'vault', 'category']:
            record = item()
            if attribute == 'vault': record['vault'] = {'id': 'c' * 26}
            elif attribute == 'category': record['category'] = 'LOGIN'
            else:
                for field in record['fields']:
                    if field['label'] == attribute: field['value'] = 'different'
            with self.subTest(attribute=attribute), patch.object(broker, 'op_json', side_effect=[[{'id': ITEM, 'title': TITLE}], record]):
                with self.assertRaises(ValueError): broker.deployment_identity(CONFIG, REQUEST, {})

    def test_missing_private_key_blocks_instead_of_rotating(self):
        record = item(); record['fields'][0]['value'] = ''
        with patch.object(broker, 'op_json', side_effect=[[{'id': ITEM, 'title': TITLE}], record]) as call:
            with self.assertRaises(ValueError): broker.deployment_identity(CONFIG, REQUEST, {})
            self.assertEqual(2, call.call_count)


class BrokerIsolation(unittest.TestCase):
    def test_item_commands_do_not_inherit_the_broker_request_pipe(self):
        with tempfile.TemporaryDirectory() as temp:
            executable = Path(temp) / 'op'
            executable.write_text('#!' + sys.executable + '\n' + '''
import json, os, stat, sys
if stat.S_ISFIFO(os.fstat(0).st_mode):
    sys.exit("SSH items cannot be created from piped input")
print(json.dumps({"created": True}))
''')
            executable.chmod(0o700)
            code = '''
import os, enrollment_credentials as broker
assert broker.op_json(['item', 'create', '--category=SSH Key'], os.environ) == {'created': True}
'''
            result = subprocess.run([sys.executable, '-c', code], input='{"domain":"example.mrndev.io"}',
                                    text=True, capture_output=True, env={**os.environ, 'PATH': temp,
                                    'PYTHONPATH': str(Path(broker.__file__).parent)}, timeout=10)
            self.assertEqual(0, result.returncode, result.stderr)

    def config(self, directory, **updates):
        value = {'account': broker.ACCOUNT, 'service_account_id': 'service', 'vault_id': VAULT,
                 'references': {name: 'op://' + VAULT + '/item/' + name for name in
                                ('github_token', 'qa_engine_token', 'deploy_private_key')}}
        value.update(updates)
        path = Path(directory) / 'config.json'; path.write_text(json.dumps(value)); path.chmod(0o600)
        return path

    def test_references_cannot_escape_configured_vault(self):
        with tempfile.TemporaryDirectory() as temp:
            path = self.config(temp, references={'github_token': 'op://Production Hub/item/token'})
            who = SimpleNamespace(returncode=0, stdout=json.dumps({'url': 'https://' + broker.ACCOUNT, 'user_uuid': 'service'}))
            with patch.dict(os.environ, {'OP_SERVICE_ACCOUNT_TOKEN': 'ops_fixture'}), patch.object(broker.subprocess, 'run', return_value=who) as call:
                with self.assertRaises(ValueError): broker.resolve(path, REQUEST)
                self.assertEqual(1, call.call_count)

    def test_encrypted_host_credential_loaded_only_into_broker_child_environment(self):
        with tempfile.TemporaryDirectory() as temp:
            encrypted = Path(temp) / 'token.cred'; encrypted.write_bytes(b'ciphertext'); encrypted.chmod(0o600)
            path = self.config(temp, service_token_credential=str(encrypted))
            responses = [SimpleNamespace(returncode=0, stdout='ops_fixture'),
                         SimpleNamespace(returncode=0, stdout=json.dumps({'url': 'https://' + broker.ACCOUNT, 'user_uuid': 'service'})),
                         *[SimpleNamespace(returncode=0, stdout='secret') for _ in range(3)]]
            with patch.dict(os.environ, {'PATH': '/usr/bin'}, clear=True), patch.object(broker.subprocess, 'run', side_effect=responses) as call:
                result = broker.resolve(path, REQUEST)
                self.assertEqual('secret', result['github_token'])
                self.assertNotIn('OP_SERVICE_ACCOUNT_TOKEN', os.environ)
                self.assertEqual('ops_fixture', call.call_args_list[1].kwargs['env']['OP_SERVICE_ACCOUNT_TOKEN'])
                self.assertNotIn('ops_fixture', json.dumps([c.args for c in call.call_args_list]))

    def test_failed_host_decryption_never_falls_back_to_another_service(self):
        with tempfile.TemporaryDirectory() as temp:
            encrypted = Path(temp) / 'token.cred'; encrypted.write_bytes(b'ciphertext'); encrypted.chmod(0o600)
            path = self.config(temp, service_token_credential=str(encrypted))
            with patch.dict(os.environ, {'OP_SERVICE_ACCOUNT_TOKEN': 'ops_other'}), patch.object(broker.subprocess, 'run', return_value=SimpleNamespace(returncode=1, stdout='')) as call:
                with self.assertRaises(ValueError): broker.resolve(path, REQUEST)
                self.assertEqual(1, call.call_count)

    def test_provider_error_text_is_never_returned(self):
        with patch.object(broker.subprocess, 'run', return_value=SimpleNamespace(returncode=1, stdout=KEY, stderr=KEY)):
            with self.assertRaisesRegex(ValueError, 'output withheld'):
                broker.op_json(['item', 'list'], {})
