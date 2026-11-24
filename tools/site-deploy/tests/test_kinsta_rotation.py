"""Signed ownership, capacity races, provider interruption and phase integration."""
import copy
from dataclasses import replace
from pathlib import Path
import sys
import tempfile
import time
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))
from kinsta import Kinsta
from release_v1.artifact import SCHEMA, canonical, sha256
from release_v1.controller import Controller, Request, Target
from release_v1.journal import Journal
from release_v1.kinsta_backup import KinstaBackups, KinstaBackupAdapter
from release_v1.proof import Blocked


class Native(Kinsta):
    """Provider service fixture: writes complete only when their job is polled."""
    def __init__(self, clock=time.time):
        super().__init__('test-token-never-record', 'a' * 36, 'b' * 36)
        self.clock, self.rows, self.jobs, self.calls = clock, [], {}, []
        self.pending, self.failed, self.unknown = set(), set(), set()
        self.wrong_identity = False

    def identity(self, config):
        if self.wrong_identity or config['url'] != 'https://staging.example.org':
            raise ValueError('Wrong provider identity')
        return {'id': self.environment_id}

    def api(self, path, body=None, *, method=None):
        self.calls.append((method or ('POST' if body is not None else 'GET'), path, body))
        if path == '/sites/environments/' + self.environment_id + '/backups':
            return {'environment': {'backups': copy.deepcopy(self.rows)}}
        if method == 'DELETE':
            kind, value = 'delete', path.rsplit('/', 1)[1]
        elif body is not None:
            if path != '/sites/environments/' + self.environment_id + '/manual-backups':
                raise AssertionError(path)
            kind, value = 'create', body['tag']
        elif path.startswith('/operations/'):
            kind, value, done = self.jobs[path.removeprefix('/operations/')]
            if kind in self.failed:
                return {'status': 500}
            if kind in self.pending:
                return {'status': 202}
            if not done:
                if kind == 'delete':
                    self.rows = [row for row in self.rows if str(row['id']) != value]
                else:
                    self.rows.append({'id': 100 + len(self.jobs), 'type': 'manual',
                                      'note': value, 'created_at': self.clock()})
                self.jobs[path.removeprefix('/operations/')] = (kind, value, True)
            return {'status': 200}
        else:
            raise AssertionError(path)
        operation = 'backups:' + kind + '-op-' + str(len(self.jobs) + 1)
        self.jobs[operation] = (kind, value, False)
        if kind in self.unknown:
            raise TimeoutError('Ambiguous provider response')
        return {'status': 202, 'operation_id': operation}

    def verify_transaction_backup(self, receipt, operation):
        with patch('kinsta.time.time', return_value=self.clock()):
            return super().verify_transaction_backup(receipt, operation)


class Rotation(unittest.TestCase):
    def setUp(self):
        temporary = tempfile.TemporaryDirectory(prefix='mrn-kinsta-rotation-')
        self.addCleanup(temporary.cleanup)
        self.root = Path(temporary.name).resolve()
        self.root.chmod(0o700)
        self.journal = Journal(self.root, b'r' * 32)
        self.target = Target('example/site', 'staging', 'a' * 36, 'https://staging.example.org')
        self.request = Request('new', 'owner', self.target, 'c' * 40, 'd' * 64, ['/'])
        self.provider = Native()
        self.config = {'environment': 'staging', 'url': self.target.origin, 'root': '/www/site/public',
                       'host': 'host', 'port': '22', 'user': 'owner'}
        self.service = KinstaBackups(self.provider, self.target, self.config, self.journal,
                                    rotate=True, attempts=2, sleep=lambda seconds: None)
        self.activate()

    def activate(self, request=None, **changes):
        request = request or self.request
        data = {'schema': SCHEMA, **vars(request), 'target': vars(request.target), 'finished': False,
                'phases': {'backup': {'result': 'running'}}, **changes}
        self.journal.save(data)
        return data

    def tracked(self, count=5):
        ledger = self.service.load()
        for index in range(1, count + 1):
            request = replace(self.request, deployment_id='old-' + str(index))
            binding = self.service.binding(request, 'deploy')
            label = 'mrn-' + sha256(canonical(binding))[:32]
            receipt = {'valid': True, 'provider': 'kinsta', 'environment_id': self.provider.environment_id,
                       'label': label, 'operation_id': 'backups:create-old-' + str(index),
                       'backup_id': index, 'created_at': index, 'scope': 'deployment-transaction'}
            ledger['records'][sha256(canonical(binding))] = {'binding': binding, 'stage': 'complete',
                'receipt': receipt, 'snapshot_created_at': index, 'label': label}
            self.provider.rows.append({'id': index, 'type': 'manual', 'note': label, 'created_at': index})
            data = {'schema': SCHEMA, **vars(request), 'target': vars(request.target), 'finished': True,
                    'backup': {'nonce': 'kinsta:' + str(index)}, 'phases': {'verify': {'result': 'passed'}}}
            self.journal.save(data, self.root / ('run-' + request.deployment_id + '.json'))
        self.service.save(ledger)

    def backup(self, request=None):
        with self.journal.writer() as fence:
            return self.service.backup(request or self.request, 'e' * 32, 'deploy', fence)

    def writes(self, method):
        return [row for row in self.provider.calls if row[0] == method]

    def test_available_slot_creates_and_verifies_without_deleting(self):
        receipt = self.backup()
        self.assertTrue(self.service.verify_backup(self.request, receipt))
        self.assertEqual([], self.writes('DELETE'))
        self.assertEqual(1, len(self.writes('POST')))
        self.assertNotIn(self.provider.token, self.service.path.read_text())

    def test_full_capacity_rotates_exactly_oldest_tracked_manual_snapshot(self):
        self.tracked()
        self.provider.rows.append({'id': 999, 'type': 'daily', 'note': 'daily', 'created_at': 0})
        receipt = self.backup()
        self.assertEqual('/sites/environments/backups/1', self.writes('DELETE')[0][1])
        self.assertEqual(1, len(self.writes('DELETE')))
        self.assertEqual(5, sum(row['type'] == 'manual' for row in self.provider.rows))
        self.assertIn(999, [row['id'] for row in self.provider.rows])
        self.assertTrue(self.service.verify_backup(self.request, receipt))

    def test_milestones_and_unresolved_or_stale_controller_runs_are_protected(self):
        self.tracked()
        self.service.protected_ids.add('1')
        path = self.root / 'run-old-2.json'
        state = self.journal.read(path)
        state.update(finished=False, updated_at=0)
        self.journal.save(state, path)
        self.backup()
        self.assertEqual('/sites/environments/backups/3', self.writes('DELETE')[0][1])

    def test_untracked_client_backup_and_mrn_looking_tag_are_never_adopted(self):
        self.tracked()
        self.service.path.unlink()
        with self.assertRaisesRegex(Blocked, 'no authorized tracked'):
            self.backup()
        self.assertEqual([], self.writes('DELETE') + self.writes('POST'))

    def test_rotation_requires_protected_opt_in_and_status_never_writes(self):
        self.tracked()
        self.service.rotate = False
        self.assertFalse(self.service.status()['ready'])
        with self.assertRaises(Blocked):
            self.service.preflight()
        with self.assertRaises(Blocked):
            self.backup()
        self.assertEqual([], self.writes('DELETE') + self.writes('POST'))

    def test_signed_ledger_and_target_and_writer_and_request_are_required(self):
        for change in ('target', 'writer', 'active', 'signature'):
            with self.subTest(change=change):
                if change == 'target':
                    self.provider.wrong_identity = True
                    with self.assertRaises(ValueError): self.backup()
                    self.provider.wrong_identity = False
                elif change == 'writer':
                    with self.assertRaises(Blocked):
                        self.service.backup(self.request, 'e' * 32, 'deploy', -1)
                elif change == 'active':
                    self.activate(owner='somebody-else')
                    with self.assertRaises(Blocked): self.backup()
                    self.activate()
                else:
                    self.tracked()
                    self.service.path.write_text(self.service.path.read_text().replace('old-1', 'evil1'))
                    with self.assertRaises(Blocked): self.backup()
                self.assertEqual([], self.writes('DELETE') + self.writes('POST'))

    def test_pending_delete_resumes_its_job_without_deleting_another_snapshot(self):
        self.tracked()
        self.provider.pending.add('delete')
        with self.assertRaisesRegex(Blocked, 'remains pending'): self.backup()
        self.assertEqual([], self.writes('POST'))
        self.provider.pending.clear()
        self.assertTrue(self.backup()['database_verified'])
        self.assertEqual(1, len(self.writes('DELETE')))

    def test_pending_create_survives_process_reconstruction_and_does_not_repeat_post(self):
        self.provider.pending.add('create')
        with self.assertRaisesRegex(Blocked, 'remains pending'): self.backup()
        self.service = KinstaBackups(self.provider, self.target, self.config,
            Journal(self.root, b'r' * 32), rotate=True, attempts=2, sleep=lambda seconds: None)
        self.provider.pending.clear()
        receipt = self.backup()
        self.assertTrue(receipt['remote_verified'])
        self.assertEqual(receipt['nonce'], self.backup()['nonce'])
        self.assertEqual(1, len(self.writes('POST')))

    def test_failed_delete_cannot_start_backup_and_unknown_write_is_not_retried(self):
        self.tracked()
        self.provider.failed.add('delete')
        with self.assertRaisesRegex(Blocked, 'operation failed'): self.backup()
        self.assertEqual([], self.writes('POST'))
        self.provider.failed.clear()
        self.provider.pending.add('create')
        with self.assertRaises(Blocked): self.backup()
        self.assertEqual(1, len(self.writes('DELETE')))

    def test_lost_create_response_blocks_replay_and_new_requests(self):
        self.provider.unknown.add('create')
        with self.assertRaises(TimeoutError): self.backup()
        with self.assertRaisesRegex(Blocked, 'outcome is unknown'): self.backup()
        request = replace(self.request, deployment_id='different')
        self.activate(request)
        with self.assertRaisesRegex(Blocked, 'earlier Kinsta backup'): self.backup(request)
        self.assertEqual(1, len(self.writes('POST')))

    def test_lost_delete_response_never_repeats_delete_or_creates_replacement(self):
        self.tracked()
        self.provider.unknown.add('delete')
        with self.assertRaises(TimeoutError): self.backup()
        with self.assertRaisesRegex(Blocked, 'outcome is unknown'): self.backup()
        self.assertEqual(1, len(self.writes('DELETE')))
        self.assertEqual([], self.writes('POST'))

    def test_another_writer_cannot_run_rotation(self):
        with self.journal.writer():
            with self.assertRaises(Blocked):
                with Journal(self.root, b'r' * 32).writer():
                    self.fail('Acquired another controller owner lease')
        self.assertEqual([], self.writes('DELETE') + self.writes('POST'))

    def test_terminal_receipt_without_matching_nonce_or_target_does_not_release_backup(self):
        self.tracked()
        for index in range(1, 6):
            path = self.root / ('run-old-' + str(index) + '.json')
            state = self.journal.read(path)
            if index % 2:
                state['backup']['nonce'] = 'kinsta:999'
            else:
                state['target'] = {**state['target'], 'environment': 'production'}
            self.journal.save(state, path)
        with self.assertRaisesRegex(Blocked, 'no authorized tracked'): self.backup()
        self.assertEqual([], self.writes('DELETE') + self.writes('POST'))

    def test_creation_time_controls_selection(self):
        self.tracked()
        ledger = self.service.load()
        for record in ledger['records'].values():
            key = record['receipt']['backup_id']
            record['snapshot_created_at'] = 100 - key
            self.provider.rows[key - 1]['created_at'] = 100 - key
        self.service.save(ledger)
        self.backup()
        self.assertEqual('/sites/environments/backups/5', self.writes('DELETE')[0][1])

    def test_changed_or_missing_creation_time_prevents_rotation(self):
        self.tracked()
        self.provider.rows[0]['created_at'] = 'unknown'
        with self.assertRaisesRegex(Blocked, 'creation time'): self.backup()
        self.provider.rows[0]['created_at'] = 99
        with self.assertRaisesRegex(Blocked, 'identity changed'): self.backup()
        self.assertEqual([], self.writes('DELETE') + self.writes('POST'))

    def test_backup_disappearing_after_completion_is_not_replayed_as_success(self):
        receipt = self.backup()
        self.provider.rows.clear()
        self.assertFalse(self.service.verify_backup(self.request, receipt))
        with self.assertRaises(RuntimeError): self.backup()
        self.assertEqual(1, len(self.writes('POST')))

    def test_unavailable_slot_after_retirement_cannot_trigger_second_delete(self):
        self.tracked()
        original = self.provider.api
        def competing_writer(path, body=None, **kwargs):
            result = original(path, body, **kwargs)
            if path.startswith('/operations/') and ':delete-' in path and result['status'] == 200:
                self.provider.rows.append({'id': 987, 'note': 'customer milestone',
                                           'type': 'manual', 'created_at': time.time()})
            return result
        with patch.object(self.provider, 'api', side_effect=competing_writer):
            with self.assertRaisesRegex(Blocked, 'will not delete another'): self.backup()
        with self.assertRaisesRegex(Blocked, 'will not delete another'): self.backup()
        self.assertEqual(1, len(self.writes('DELETE')))
        self.assertEqual(0, len(self.writes('POST')))

    def test_duplicate_inventory_ids_block_mutation(self):
        self.tracked()
        self.provider.rows.append(copy.deepcopy(self.provider.rows[0]))
        with self.assertRaisesRegex(Blocked, 'duplicate'): self.backup()
        self.assertEqual([], self.writes('DELETE') + self.writes('POST'))

    def test_inventory_race_stops_before_deletion(self):
        self.tracked()
        original = self.service.inventory
        count = 0
        def inventory():
            nonlocal count
            count += 1
            if count == 2: self.provider.rows[0]['note'] = 'owner changed this'
            return original()
        with patch.object(self.service, 'inventory', side_effect=inventory):
            with self.assertRaisesRegex(Blocked, 'inventory changed'): self.backup()
        self.assertEqual([], self.writes('DELETE') + self.writes('POST'))

    def test_explicit_abort_releases_only_reservation_with_no_outstanding_provider_write(self):
        binding = self.service.binding(self.request, 'deploy')
        key = sha256(canonical(binding))
        ledger = self.service.load()
        ledger['records'][key] = {'stage': 'slot-ready', 'binding': binding}
        self.service.save(ledger)
        state = self.activate(finished=True, phases={'abort': {'result': 'passed'}})
        self.journal.save(state, self.root / 'run-new.json')
        request = replace(self.request, deployment_id='after-abort')
        self.activate(request)
        self.assertTrue(self.backup(request)['database_verified'])
        self.assertEqual([], self.writes('DELETE'))

    def test_abort_does_not_release_unknown_provider_write(self):
        self.provider.unknown.add('create')
        with self.assertRaises(TimeoutError): self.backup()
        state = self.activate(finished=True, phases={'abort': {'result': 'passed'}})
        self.journal.save(state, self.root / 'run-new.json')
        request = replace(self.request, deployment_id='after-abort')
        self.activate(request)
        with self.assertRaisesRegex(Blocked, 'earlier Kinsta backup'): self.backup(request)
        self.assertEqual(1, len(self.writes('POST')))


class ControllerIntegration(unittest.TestCase):
    def fixture(self):
        from test_release_v1 import Project, Adapter
        fixture = Project()
        fixture.setUp()
        self.addCleanup(fixture.doCleanups)
        built = fixture.package()
        target = Target('example/site', 'staging', 'a' * 36, 'https://staging.example.org')
        request = Request('pilot', 'owner', target, fixture.source, built['artifact_sha256'], ['/'])
        state = fixture.root / 'journal'; state.mkdir(mode=0o700)
        journal = Journal(state, b'x' * 32)
        provider = Native()
        backups = KinstaBackups(provider, target, {'environment': 'staging', 'url': target.origin},
                                journal, rotate=True, sleep=lambda seconds: None)
        original = Adapter(target, time.time)
        adapter = KinstaBackupAdapter(original, backups)
        controller = Controller(adapter, target, journal)
        return fixture, request, original, controller, provider, backups

    def test_failed_proof_retry_reuses_backup_and_completion_enables_later_rotation(self):
        fixture, request, adapter, controller, provider, backups = self.fixture()
        for phase in ('preflight', 'backup', 'activate'):
            controller.run(phase, request, fixture.archive if phase != 'backup' else None)
        adapter.fail_proof = [Blocked('Browser proof failed')]
        with self.assertRaises(Blocked): controller.run('proof', request)
        controller.run('proof', request)
        for phase in ('migrate', 'refresh', 'verify'):
            controller.run(phase, request, fixture.archive if phase == 'migrate' else None)
        self.assertEqual(1, sum(call[0] == 'POST' for call in provider.calls))
        self.assertEqual(1, adapter.calls.count('activate'))
        self.assertTrue(controller.status()['phases']['verify']['result'] == 'passed')
        self.assertEqual(str(provider.rows[0]['id']), backups.status()['oldest_eligible'])

    def test_failed_replacement_never_activates_and_status_explains_recovery(self):
        fixture, request, adapter, controller, provider, backups = self.fixture()
        controller.run('preflight', request, fixture.archive)
        provider.failed.add('create')
        with self.assertRaises(Blocked): controller.run('backup', request)
        with self.assertRaises(Blocked): controller.run('activate', request, fixture.archive)
        status = controller.status()
        self.assertEqual('recover', status['next'])
        self.assertEqual('reconcile-backup-operation', status['backup_provider_status']['next'])
        self.assertNotIn('activate', adapter.calls)

    def test_status_and_full_untracked_preflight_are_read_only(self):
        fixture, request, adapter, controller, provider, backups = self.fixture()
        provider.rows = [{'id': index, 'note': 'customer milestone', 'type': 'manual', 'created_at': index}
                         for index in range(1, 6)]
        status = controller.status()
        self.assertEqual('review-backup-retention', status['backup_provider_status']['next'])
        with self.assertRaises(Blocked): controller.run('preflight', request, fixture.archive)
        self.assertFalse(backups.path.exists())
        self.assertFalse(any(call[0] != 'GET' for call in provider.calls))

    def test_verified_code_rollback_has_separate_backup_and_terminal_receipts(self):
        fixture, request, adapter, controller, provider, backups = self.fixture()
        for phase in ('preflight', 'backup', 'activate'):
            controller.run(phase, request, fixture.archive if phase != 'backup' else None)
        controller.run('rollback', request, confirmed_origin=request.target.origin)
        status = controller.status()
        self.assertEqual('verified', status['rollback_state'])
        self.assertEqual(2, sum(call[0] == 'POST' for call in provider.calls))
        ledger = backups.load()
        self.assertEqual(2, len(ledger['records']))
        self.assertTrue(all(backups.terminal(row) for row in ledger['records'].values()))

    def test_status_keeps_release_state_when_provider_is_unavailable_without_leaking_error(self):
        fixture, request, adapter, controller, provider, backups = self.fixture()
        with patch.object(provider, 'identity', side_effect=ValueError(provider.token)):
            status = controller.status()
        self.assertEqual('inspect-backup-provider', status['backup_provider_status']['next'])
        self.assertEqual('ValueError', status['backup_provider_status']['diagnostic'])
        self.assertNotIn(provider.token, repr(status))


if __name__ == '__main__':
    unittest.main()
