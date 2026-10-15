"""A cloned private release can enroll only after identity, checksums and backup."""
import json
from pathlib import Path
import sys
import unittest
import re
from unittest.mock import Mock
sys.path.insert(0, str(Path(__file__).parents[1]))
import host_controller as host
import test_atomic_store as fixture


class ImportedStaging(unittest.TestCase):
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
        with f.store.locked_store():
            legacy = f.adopt()
            f.store.stage(str(release.archive), release.expected, release.sha, '.')
            current = f.store.select(release.expected, legacy)
        self.plan = dict(environment='staging', host_provider='kinsta', backup_provider='kinsta',
                         adopt=False, expected_current=current, bind_imported_source=release.sha,
                         bind_imported_public_tree=host.digest(fixture.inventory(f.theme)), exercise_rollback=True)
        self.binding = dict(repository='mrn/site', environment='staging', url='https://stg-example.kinsta.cloud',
                            root=str(f.content.parent), template='parent', slug='child')
        self.guard = Mock(return_value={'valid': True, 'provider': 'kinsta'})

    def tearDown(self):
        self.fixture.tearDown()

    def bind(self, **changes):
        with self.fixture.store.locked_store():
            return host.bind_imported_staging(dict(self.plan, **changes), self.fixture.store, self.binding, self.guard)

    def test_exact_import_is_bound_after_backup_without_changing_the_active_release(self):
        before = self.fixture.store.pointer()
        self.guard.side_effect = lambda operation: self.assertFalse((self.fixture.state/'site.json').exists()) or {'valid':True}
        self.assertEqual('bind-imported-staging', self.bind()['operation'])
        self.assertEqual(self.binding, json.loads((self.fixture.state/'site.json').read_text()))
        self.assertEqual(before, self.fixture.store.pointer())
        self.guard.assert_called_once_with('bind-imported-staging')
        self.fixture.store.verify_public_snapshot()

    def test_live_non_native_and_ordered_imports_are_rejected_without_backup_or_binding(self):
        for change in [dict(environment='live'), dict(host_provider='cloudpanel'), dict(backup_provider='updraft'),
                       dict(deployment_order={'run_id':'123'}), dict(adopt=True), dict(bind_imported_source='0'*40),
                       dict(exercise_rollback=False)]:
            with self.assertRaises(ValueError): self.bind(**change)
        self.guard.assert_not_called()
        self.assertFalse((self.fixture.state/'site.json').exists())

    def test_changed_pointer_and_public_asset_drift_are_rejected_before_backup(self):
        pointer = dict(self.plan['expected_current'], release_id='0'*64)
        with self.assertRaisesRegex(ValueError, 'pointer changed'): self.bind(expected_current=pointer)
        asset = next(p for p in (self.fixture.content/self.plan['expected_current']['public_path']).rglob('*') if p.is_file())
        asset.write_text('drift')
        with self.assertRaisesRegex(ValueError, 'assets have drifted'): self.bind()
        self.guard.assert_not_called()
        self.assertFalse((self.fixture.state/'site.json').exists())

    def test_backup_failure_never_creates_a_target_binding(self):
        self.guard.side_effect = ValueError('snapshot unavailable')
        with self.assertRaisesRegex(ValueError, 'snapshot unavailable'): self.bind()
        self.assertFalse((self.fixture.state/'site.json').exists())

    def test_missing_adoption_record_requires_the_exact_bootstrap_and_creates_a_snapshot_after_backup(self):
        path = self.fixture.state/'adoption.json'
        path.unlink()
        self.guard.side_effect = lambda operation: self.assertFalse(path.exists()) or {'valid':True}
        self.bind()
        self.assertTrue(json.loads(path.read_text())['imported'])
        self.fixture.store.verify_public_snapshot()

    def test_unreviewed_tree_or_unrecognized_loader_cannot_be_enrolled(self):
        with self.assertRaisesRegex(ValueError, 'reviewed tree'):
            self.bind(bind_imported_public_tree='0'*64)
        (self.fixture.state/'adoption.json').unlink()
        (self.fixture.theme/'functions.php').write_text('<?php // unreviewed loader')
        tree = host.digest(fixture.inventory(self.fixture.theme))
        with self.assertRaisesRegex(ValueError, 'verified bootstrap'):
            self.bind(bind_imported_public_tree=tree)
        self.guard.assert_not_called()

    def test_historical_absolute_loader_is_bound_only_to_its_actual_store(self):
        (self.fixture.state/'adoption.json').unlink()
        path = self.fixture.theme/'functions.php'
        text = path.read_text()
        pattern = r"\$mrn_release_state_root = realpath\(.*?\$mrn_release_pointer = \$mrn_release_state_root \. '/current.json';"
        replacement = "$mrn_release_state_root = '"+str(self.fixture.state)+"';\n$mrn_release_pointer    = $mrn_release_state_root . '/current.json';"
        text, count = re.subn(pattern, lambda match: replacement, text, flags=re.S)
        self.assertEqual(1, count)
        path.write_text(text.replace(str(self.fixture.state), '/different/store'))
        with self.assertRaisesRegex(ValueError, 'verified bootstrap'):
            self.bind(bind_imported_public_tree=host.digest(fixture.inventory(self.fixture.theme)))
        self.guard.assert_not_called()
        path.write_text(text)
        self.bind(bind_imported_public_tree=host.digest(fixture.inventory(self.fixture.theme)))
        self.fixture.store.verify_public_snapshot()

    def test_existing_binding_is_never_overwritten(self):
        path = self.fixture.state/'site.json'
        path.write_text('{"repository":"another/site"}')
        with self.assertRaisesRegex(ValueError, 'already has'): self.bind()
        self.assertEqual('{"repository":"another/site"}', path.read_text())
        self.guard.assert_not_called()
