import importlib.util
from pathlib import Path
import unittest
import time
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('kinsta', Path(__file__).parents[1] / 'kinsta.py')
kinsta = importlib.util.module_from_spec(spec)
spec.loader.exec_module(kinsta)


class NativeBackupIdentity(unittest.TestCase):
    def test_wrong_environment_never_resolves_ssh_password(self):
        adapter = kinsta.Kinsta('test-token', 'a'*36, 'b'*36)
        with patch.object(adapter, 'api', return_value={'site': {'environments': []}}) as api:
            with self.assertRaises(ValueError): adapter.connect({'url': 'https://example.org', 'root': '/www/example/public'})
            self.assertEqual(1, api.call_count)

    def test_pending_or_missing_receipt_is_not_success(self):
        adapter = kinsta.Kinsta('test-token', 'a'*36, 'b'*36)
        with patch.object(adapter, 'api', side_effect=[{'operation_id': 'backup:123'}, {'status': 200}, {'environment': {'backups': []}}]):
            with self.assertRaisesRegex(RuntimeError, 'could not be verified'): adapter.backup('pre-release')

    def test_completed_operation_and_matching_manual_backup_are_both_required(self):
        adapter = kinsta.Kinsta('test-token', 'a'*36, 'b'*36)
        responses = [{'operation_id': 'backup:123'}, {'status': 202}, {'status': 200},
                     {'environment': {'backups': [{'id': 42, 'type': 'manual', 'note': 'pre-release'}]}}]
        with patch.object(adapter, 'api', side_effect=responses), patch.object(kinsta.time, 'sleep'):
            receipt = adapter.backup('pre-release')
            self.assertTrue(receipt['valid'])
            self.assertEqual(42, receipt['backup_id'])

    def test_each_native_step_rechecks_transaction_snapshot_and_rejects_stale_or_missing(self):
        adapter = kinsta.Kinsta('test-token', 'a'*36, 'b'*36)
        receipt = dict(valid=True, provider='kinsta', environment_id='b'*36,
                       scope='deployment-transaction', created_at=int(time.time()), backup_id=42, label='deployment')
        data = {'environment': {'backups': [{'id':42,'type':'manual','note':'deployment'}]}}
        with patch.object(adapter, 'api', return_value=data) as api:
            self.assertEqual('activate', adapter.verify_transaction_backup(receipt, 'activate')['operation'])
            adapter.verify_transaction_backup(receipt, 'rollback')
            self.assertEqual(2, api.call_count)
            with self.assertRaises(ValueError):
                adapter.verify_transaction_backup(dict(receipt, created_at=0), 'activate')
            with self.assertRaises(ValueError):
                adapter.verify_transaction_backup(dict(receipt, environment_id='c'*36), 'activate')
        with patch.object(adapter, 'api', return_value={'environment':{'backups':[]}}):
            with self.assertRaises(RuntimeError):
                adapter.verify_transaction_backup(receipt, 'activate')

    def test_staging_cannot_resolve_live_or_a_custom_domain_before_ssh_access(self):
        adapter = kinsta.Kinsta('test-token', 'a'*36, 'b'*36)
        config = dict(environment='staging', url='https://stg-example.kinsta.cloud',
                      root='/www/example/public', host='host', port='22', user='owner')
        for name, domain_type in [('live', 'live'), ('staging', 'live')]:
            response = {'site': {'environments': [dict(id='b'*36, name=name,
                        primaryDomain=dict(name='stg-example.kinsta.cloud', type=domain_type),
                        web_root=config['root'])]}}
            with patch.object(adapter, 'api', return_value=response) as api:
                with self.assertRaisesRegex(ValueError, 'native staging'): adapter.connect(config)
                self.assertEqual(1, api.call_count)

    def test_explicit_agent_identity_validates_native_target_without_requesting_a_password(self):
        from deploy import Target
        from unittest.mock import Mock
        config = dict(backup_provider='kinsta', key_file='/private/selector.pub', port='22',
                      known_hosts_file='/private/known_hosts', user='owner', host='host', environment='staging')
        adapter = Mock()
        with patch('kinsta.Kinsta', return_value=adapter):
            target = Target(config)
        adapter.identity.assert_called_once_with(config)
        adapter.connect.assert_not_called()
        self.assertIsNone(target.password)
        self.assertIn('/private/selector.pub', target.options)

    def test_live_cannot_resolve_staging_or_a_staging_primary_domain(self):
        adapter = kinsta.Kinsta('test-token', 'a'*36, 'b'*36)
        config = dict(environment='live', url='https://example.org', root='/www/example/public',
                      host='host', port='22', user='owner')
        for name,domain_type in [('staging','staging'),('live','staging')]:
            response={'site':{'environments':[dict(id='b'*36,name=name,primaryDomain=dict(name='example.org',type=domain_type),web_root=config['root'])]}}
            with patch.object(adapter,'api',return_value=response) as api:
                with self.assertRaisesRegex(ValueError,'native Live'):
                    adapter.connect(config)
                self.assertEqual(1,api.call_count)


class NativeEdgeInvalidation(unittest.TestCase):
    def setUp(self):
        self.adapter = kinsta.Kinsta('test-token', 'a'*36, 'b'*36)
        self.operation = 'edgeCache:clear-' + 'b'*36

    def test_exact_url_completion_is_required_without_descendants_or_full_cache(self):
        responses = [{'status':202,'operation_id':self.operation}, {'status':202}, {'status':200},
                     {'status':202,'operation_id':self.operation}, {'status':200}]
        pages = ['https://example.org/', 'https://example.org/page/']
        with patch.object(self.adapter, 'api', side_effect=responses) as api, patch.object(kinsta.time, 'sleep'):
            receipt = self.adapter.refresh_edge_html('https://example.org', pages)
        self.assertEqual('exact-html-edge-purge-verified', receipt['status'])
        self.assertEqual(pages, [row['url'] for row in receipt['operations']])
        requests = [call.args for call in api.call_args_list if len(call.args) == 2]
        self.assertEqual([('/sites/edge-caching/clear', {'environment_id':'b'*36,
                          'clear_subdirectories':False, 'url':page}) for page in pages], requests)
        self.assertNotIn('test-token', str(receipt))

    def test_all_paths_are_validated_before_any_purge(self):
        for path in ['//other.org/', '/wp-admin/', '/wp-content/x.css', '/file.js',
                     '/page/?q=1', '/page/#part', '/wild*', '/encoded%2f', '/a/../b']:
            page = 'https://other.org/' if path.startswith('//') else 'https://example.org'+path
            with self.subTest(path=path), patch.object(self.adapter, 'api') as api:
                with self.assertRaises(ValueError):
                    self.adapter.refresh_edge_html('https://example.org', ['https://example.org/',page])
                api.assert_not_called()

    def test_missing_mismatched_or_failed_operation_is_not_success(self):
        for response in [{}, {'status':200}, {'status':202,'operation_id':'wrong'},
                         {'status':202,'operation_id':'edgeCache:clear-'+'c'*36}]:
            with patch.object(self.adapter,'api',return_value=response):
                with self.assertRaises(RuntimeError):self.adapter.refresh_edge_html('https://example.org',['https://example.org/'])
        with patch.object(self.adapter,'api',side_effect=[{'status':202,'operation_id':self.operation},{'status':500}]):
            with self.assertRaisesRegex(RuntimeError,'failed'):self.adapter.refresh_edge_html('https://example.org',['https://example.org/'])

    def test_pending_operation_is_polled_without_a_second_post(self):
        with patch.object(self.adapter,'api',side_effect=[{'status':202,'operation_id':self.operation}]+[{'status':202}]*60) as api, patch.object(kinsta.time,'sleep'):
            with self.assertRaisesRegex(RuntimeError,'still pending'):
                self.adapter.refresh_edge_html('https://example.org',['https://example.org/'])
            self.assertEqual(1,sum(len(call.args)==2 for call in api.call_args_list))
