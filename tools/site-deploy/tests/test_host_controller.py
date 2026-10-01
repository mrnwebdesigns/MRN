import json
from pathlib import Path
import sys
import unittest
from unittest.mock import patch
import os
import subprocess
import tempfile

sys.path.insert(0, str(Path(__file__).parents[1]))
import host_controller as host
import test_atomic_store as fixture
from deploy import digest


class HostControllerContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        fixture.AtomicStoreContract.setUpClass()

    @classmethod
    def tearDownClass(cls):
        fixture.AtomicStoreContract.tearDownClass()

    def setUp(self):
        self.fixture = fixture.AtomicStoreContract()
        self.fixture.setUp()
        f = self.fixture
        release = fixture.release_fixture.ReleaseArtifactContract
        self.plan = dict(environment='dev', repository='mrn/site', url='https://test.mrndev.io',
                         root=str(f.content.parent), template='parent', slug='child', state_dir=str(f.state),
                         backup_provider='updraft', pages=['https://test.mrndev.io/'],
                         baseline=digest(f.before), expected_current=None, adopt=True,
                         archive=str(release.archive), artifact_sha256=release.expected,
                         source_sha=release.sha, source_path='.', exercise_rollback=True)
        binding = {key: self.plan[key] for key in ('repository', 'environment', 'url', 'root', 'template', 'slug')}
        self.patches = [
            patch.object(host, 'identity', return_value=({'content': str(f.content)}, binding, {})),
            patch.object(host, 'verify_uncached_html', return_value={}),
            patch.object(host, 'legacy_assets', return_value={'old': {}}),
            patch.object(host, 'verify_legacy_assets'),
            patch.object(host, 'http_check'),
        ]
        for item in self.patches:
            item.start()

    def tearDown(self):
        for item in reversed(self.patches):
            item.stop()
        self.fixture.tearDown()

    def test_backup_precedes_every_activation_and_rollback(self):
        operations = []
        def save(root, operation):
            operations.append(operation)
            return {'valid': True, 'nonce': operation}
        with patch.object(host, 'backup', side_effect=save), patch.object(host, 'public_check', return_value={}):
            receipt = host.execute(self.plan)
        self.assertEqual(['adopt', 'stage', 'activate', 'rollback', 'reactivate'], operations)
        self.assertEqual('public-verified', receipt['status'])
        self.assertEqual(self.plan['artifact_sha256'], self.fixture.store.pointer()['release_id'])
        self.assertTrue(receipt['runtime_qa_required'])

    def test_backup_failure_does_not_install_the_loader(self):
        with patch.object(host, 'backup', side_effect=ValueError('backup unavailable')):
            receipt = host.execute(self.plan)
        self.assertEqual('failed', receipt['status'])
        self.assertIsNone(self.fixture.store.pointer())
        self.assertEqual(self.fixture.before, fixture.inventory(self.fixture.theme))

    def test_failed_public_activation_returns_to_the_verified_legacy_release(self):
        responses = [{}, ValueError('wrong asset bytes'), {}]
        with patch.object(host, 'backup', return_value={'valid': True}), patch.object(host, 'public_check', side_effect=responses):
            receipt = host.execute(self.plan)
        self.assertEqual('failed', receipt['status'])
        self.assertIsNone(self.fixture.store.pointer()['public_path'])
        self.assertIn('recovery', receipt)
        self.fixture.store.verify_public_snapshot()

    def test_initial_loader_incompatibility_restores_original_public_functions(self):
        with patch.object(host, 'backup', return_value={'valid': True}), patch.object(host, 'public_check', side_effect=ValueError('FPM cannot load private code')):
            receipt = host.execute(self.plan)
        self.assertEqual('original-public-bootstrap-restored', receipt['recovery']['status'])
        self.assertIsNone(self.fixture.store.pointer())
        self.assertEqual(self.fixture.before, fixture.inventory(self.fixture.theme))
        # Retained recovery snapshots do not prevent a later corrected adoption.
        with self.fixture.store.locked_store():
            self.fixture.adopt()

    def test_live_is_rejected_before_wordpress_or_backup(self):
        # Bypass only the test's identity stub to exercise the actual boundary.
        self.patches[0].stop()
        with patch.object(host, 'wp') as wp:
            with self.assertRaisesRegex(ValueError, 'Live remains disabled'):
                host.identity({**self.plan, 'environment': 'live'})
            wp.assert_not_called()

    def test_recovery_failure_receipt_preserves_location_without_secret_text(self):
        try:
            try:
                raise PermissionError(13, 'private-token-do-not-log', '/private/site/current.json')
            except PermissionError:
                raise RuntimeError('another-private-token')
        except RuntimeError as error:
            receipt = host.failure_receipt(error)
        self.assertEqual('requires-inspection', receipt['status'])
        self.assertEqual(['RuntimeError','PermissionError'], [row['type'] for row in receipt['diagnostics']])
        self.assertEqual(13, receipt['diagnostics'][1]['errno'])
        self.assertEqual('/private/site/current.json', receipt['diagnostics'][1]['path'])
        self.assertNotIn('private-token', json.dumps(receipt))
        self.assertTrue(receipt['diagnostics'][0]['frames'])


class CacheInputTransport(unittest.TestCase):
    def test_large_scope_reaches_cli_without_large_environment_or_argument(self):
        urls = ['https://example.org/' + 'a' * 80 + str(n) + '/' for n in range(2180)]
        plan = {'root':'/site','url':'https://example.org','host_provider':'nexcess','pages':urls}
        # Run an actual child process; a mocked subprocess misses Linux's
        # per-string exec limit, which broke Gloves before its first activation.
        with tempfile.TemporaryDirectory() as directory:
            cli = Path(directory) / 'wp'
            cli.write_text('#!' + sys.executable + '\nimport sys,json,os\n'
                           'assert "MRN_HTML_CACHE_REQUEST" not in os.environ\n'
                           'assert max(map(len,sys.argv)) < 32768\n'
                           'request=json.load(sys.stdin)\n'
                           'print("MRN_RESULT="+json.dumps({"urls":request["urls"]}))\n')
            cli.chmod(0o700)
            with patch.dict(os.environ, {'PATH':directory + os.pathsep + os.environ['PATH']}):
                receipt = host.html_cache(plan, 'refresh')
        self.assertEqual(urls, receipt['urls'])


if __name__ == '__main__':
    unittest.main()
