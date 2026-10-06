"""Disposable parent transaction failures, preserved child, CAS and rollback."""
import copy
import fcntl
import json
import os
from pathlib import Path
import tempfile
import unittest

import test_components as fixtures
from parent_store import ParentStore, inventory
from parent_deploy import validate_target


class ParentTransactions(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        fixtures.Components.setUpClass()

    @classmethod
    def tearDownClass(cls):
        fixtures.Components.tearDownClass()

    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix='mrn-parent-transaction-')
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name).resolve()
        self.public = self.root / 'public'
        self.parent = self.public / 'wp-content/themes/mrn-base-stack'
        self.child = self.public / 'wp-content/themes/fixture-child'
        self.mu = self.public / 'wp-content/mu-plugins'
        for path in (self.parent, self.child, self.mu):
            path.mkdir(parents=True)
        for path in (self.parent, self.child):
            (path / 'functions.php').write_text('<?php // Preserved code.')
            (path / 'style.css').write_text('/* Preserved style. */')
        self.state = self.root / 'parent-state'
        self.state.mkdir(mode=0o700)
        self.child_state = self.root / 'child-state'
        self.child_state.mkdir(mode=0o700)
        (self.child_state / 'current.json').write_text(json.dumps({'schema': 1, 'release_id': 'a' * 64}))
        self.store = ParentStore(self.public, self.state, 'fixture-child', self.child_state)
        self.preserved = self.store.preserved()
        self.receipt = fixtures.Components.parent

    def stage(self):
        return self.store.stage(fixtures.Components.root / 'parent.zip', self.receipt['artifact_sha256'],
                                self.receipt['source_sha'], 'parent')

    def activate(self, expected=None):
        return self.store.activate(self.receipt['artifact_sha256'], expected, self.preserved)

    def test_stage_never_changes_public_themes_or_pointer(self):
        with self.store.lock():
            self.stage()
            self.assertIsNone(self.store.pointer())
            self.store.unchanged(self.preserved)

    def test_activation_disable_reactivation_preserves_old_urls_and_code(self):
        with self.store.lock():
            self.stage()
            selected = self.activate()
            self.assertTrue(self.store.bootstrap.is_file())
            assets = inventory(self.public / 'wp-content' / ('mrn-assets/' + self.receipt['generation'] + '/mrn-base-stack'))
            self.store.disable(selected, self.preserved)
            self.assertFalse(self.store.bootstrap.exists())
            self.assertEqual(selected, self.store.pointer())
            self.assertEqual(assets, inventory(self.public / 'wp-content' / ('mrn-assets/' + self.receipt['generation'] + '/mrn-base-stack')))
            self.store.activate(self.receipt['artifact_sha256'], selected, self.preserved, restore_managed=True)
            self.assertTrue(self.store.bootstrap.is_file())
            self.store.unchanged(self.preserved)

    def test_selection_conflict_changes_nothing(self):
        with self.store.lock():
            self.stage()
            selected = self.activate()
            with self.assertRaisesRegex(ValueError, 'selection changed'):
                self.activate()
            self.assertEqual(selected, self.store.pointer())

    def test_child_pointer_or_code_change_blocks_activation(self):
        for change in ('pointer', 'code'):
            with self.subTest(change=change), self.store.lock():
                self.stage()
                if change == 'pointer':
                    (self.child_state / 'current.json').write_text('{"schema":1,"release_id":"changed"}')
                else:
                    (self.child / 'functions.php').write_text('<?php // Changed.')
                with self.assertRaisesRegex(ValueError, 'changed'):
                    self.activate()
                self.assertIsNone(self.store.pointer())

    def test_existing_immutable_assets_cannot_be_overwritten(self):
        with self.store.lock():
            self.stage()
            (self.public / 'wp-content' / ('mrn-assets/' + self.receipt['generation'] + '/mrn-base-stack') / 'style.css').write_text('drift')
            with self.assertRaisesRegex(ValueError, 'asset drift'):
                self.activate()

    def test_private_code_drift_blocks_rollback_and_activation(self):
        with self.store.lock():
            self.stage()
            path = self.state / 'releases' / self.receipt['artifact_sha256'] / 'component/mrn-base-stack/functions.php'
            path.write_text('<?php // Drift.')
            with self.assertRaisesRegex(ValueError, 'code drift'):
                self.activate()

    def test_wrong_package_checksum_cannot_stage(self):
        with self.store.lock(), self.assertRaises(ValueError):
            self.store.stage(fixtures.Components.root / 'parent.zip', '0' * 64, self.receipt['source_sha'], 'parent')
        self.assertIsNone(self.store.pointer())

    def test_unknown_loader_is_never_overwritten(self):
        self.store.bootstrap.write_text('<?php // Someone else owns this.')
        with self.store.lock():
            self.stage()
            with self.assertRaisesRegex(ValueError, 'unknown MU loader'):
                self.activate()
        self.assertEqual('<?php // Someone else owns this.', self.store.bootstrap.read_text())

    def test_shared_child_writer_lock_prevents_concurrent_activation(self):
        descriptor = os.open(self.child_state / 'deployment.lock', os.O_CREAT | os.O_RDWR, 0o600)
        try:
            fcntl.flock(descriptor, fcntl.LOCK_EX | fcntl.LOCK_NB)
            with self.assertRaises(BlockingIOError), self.store.lock():
                pass
        finally:
            os.close(descriptor)

    def test_interrupted_stage_is_retained_and_verified_before_reuse(self):
        with self.store.lock():
            self.store.journal('stage', None)
            self.stage()
        resumed = ParentStore(self.public, self.state, 'fixture-child', self.child_state)
        self.assertIsNone(resumed.pointer())
        self.assertEqual('in-progress', json.loads((self.state / 'intent.json').read_text())['status'])
        with resumed.lock():
            resumed.release(self.receipt['artifact_sha256'])
            resumed.unchanged(self.preserved)

    def test_changed_discovery_view_refuses_reuse(self):
        with self.store.lock():
            self.stage()
            selection = self.store.selection(self.receipt['artifact_sha256'])
            path = self.state / 'theme-views' / selection['theme_view'] / 'themes/unknown'
            path.write_text('unexpected')
            with self.assertRaisesRegex(ValueError, 'view changed'):
                self.activate()

    def test_state_alias_and_public_storage_are_refused(self):
        alias = self.root / 'alias'
        alias.symlink_to(self.state, target_is_directory=True)
        for path in (alias, self.public):
            with self.subTest(path=path), self.assertRaises(ValueError):
                ParentStore(self.public, path, 'fixture-child')

    def test_writes_without_lock_are_refused(self):
        with self.assertRaisesRegex(ValueError, 'locks required'):
            self.stage()

    def test_target_contract_refuses_live_cross_dashboard_and_stale_sync(self):
        from datetime import datetime, timezone
        plan = {'environment': 'dev', 'url': 'https://fixture.mrndev.io', 'ssh_login': 'fixture@mrndev-site-owner',
                'root': '/home/fixture/htdocs/fixture.mrndev.io', 'state': '/home/fixture/.local/parent',
                'child_state': '/home/fixture/.local/child', 'mainwp': {'connected': True,
                'dashboardHost': 'wpcontrol.mrndev.io', 'abilitiesCount': 84, 'site_id': 7,
                'site_url': 'https://fixture.mrndev.io/', 'synced_at': datetime.now(timezone.utc).isoformat()}}
        validate_target(plan)
        for key, value in [('environment', 'live'), ('url', 'https://elsewhere.example'), ('root', '/home/other/site')]:
            changed = {**plan, key: value}
            with self.subTest(key=key), self.assertRaises(ValueError):
                validate_target(changed)

    def test_large_bound_plan_does_not_enter_ssh_arguments(self):
        from datetime import datetime, timezone
        from unittest.mock import patch
        from parent_deploy import inspect
        plan = {'environment': 'dev', 'url': 'https://fixture.mrndev.io', 'ssh_login': 'fixture@mrndev-site-owner',
                'root': '/home/fixture/htdocs/fixture.mrndev.io', 'state': '/home/fixture/.local/parent',
                'child_state': '/home/fixture/.local/child', 'child': 'fixture-child',
                'other_stack': {str(i): 'a' * 64 for i in range(10000)},
                'mainwp': {'connected': True, 'dashboardHost': 'wpcontrol.mrndev.io', 'abilitiesCount': 84,
                'site_id': 7, 'site_url': 'https://fixture.mrndev.io/', 'synced_at': datetime.now(timezone.utc).isoformat()}}
        with patch('parent_deploy.ssh', return_value=b'{}') as remote:
            inspect(plan)
        self.assertLess(len(remote.call_args.args[1]), 10000)
        for key, value in [('dashboardHost', 'wrong.example'), ('synced_at', '2020-01-01T00:00:00+00:00')]:
            changed = copy.deepcopy(plan)
            changed['mainwp'][key] = value
            with self.subTest(key=key), self.assertRaises(ValueError):
                validate_target(changed)

    def test_inherited_state_permissions_are_restricted_only_for_new_jobs(self):
        from parent_deploy import transfer_program
        state = self.root / 'transfer-state'
        state.mkdir(mode=0o750)
        (state / 'jobs').mkdir()
        program = transfer_program(str(state), str(state / 'jobs/first'), {}, {})
        subprocess = __import__('subprocess')
        result = subprocess.run(['python3', '-c', program], capture_output=True)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(0, state.stat().st_mode & 0o077)
        state.chmod(0o750)
        (state / 'unknown.json').write_text('{}')
        result = subprocess.run(['python3', '-c', transfer_program(str(state), str(state / 'jobs/second'), {}, {})], capture_output=True)
        self.assertNotEqual(0, result.returncode)
        self.assertEqual(0o750, state.stat().st_mode & 0o777)
