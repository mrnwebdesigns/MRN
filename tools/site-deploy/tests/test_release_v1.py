"""Contract, phase and recovery tests with a disposable target and real artifacts."""
from dataclasses import replace
from datetime import datetime, timezone
from email.utils import format_datetime
import hashlib
from io import BytesIO
import json
import os
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile
import unittest
from unittest.mock import patch

from PIL import Image

TOOLS = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(TOOLS))

from release_v1.artifact import Limits, SCHEMA, inspect, pack
from release_v1.controller import Controller, Request, Target
from release_v1.journal import Journal
from release_v1.proof import Blocked, Throttled, retry_delay, runtime_health


BASELINE = '4' * 64
ROW = {'id': 'hero-v1', 'script': 'hero-v1.php', 'assets': ['media/hero.png'],
       'environments': ['staging', 'local'], 'paths': ['/about/']}


def git(root, *args):
    return subprocess.check_output(['git', '-C', str(root), *args], stderr=subprocess.PIPE, text=True).strip()


def stored_fixture(root):
    git(root, 'init', '-q')
    content = root / 'scripts/content'
    (content / 'media').mkdir(parents=True)
    (content / 'migrations.json').write_text(json.dumps({'schema': SCHEMA, 'migrations': [ROW]}))
    (content / ROW['script']).write_text('<?php return static fn( $media ) => true;')
    Image.new('RGB', (32, 16), 'red').save(content / ROW['assets'][0])
    return content


def commit(root):
    git(root, 'add', '--all')
    git(root, '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.invalid', '-c',
        'core.hooksPath=/dev/null', 'commit', '-qm', 'Fixture')
    return git(root, 'rev-parse', 'HEAD')


class Project(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='mrn-v1-test-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name).resolve()
        self.repo = self.root / 'repo'
        self.repo.mkdir()
        self.content = stored_fixture(self.repo)
        self.source = commit(self.repo)
        self.code = self.root / 'code.tar'
        self.code.write_bytes(b'provider code fixture')
        self.archive = self.root / 'release.tar'

    def package(self, **kwargs):
        return pack(self.repo, self.source, self.code, self.archive, **kwargs)


class Artifacts(Project):
    def test_deterministic_tracked_snapshot_and_media_report(self):
        original = self.content / ROW['assets'][0]
        original.write_bytes(b'untracked dirty working tree bytes')
        built = self.package(limits=Limits(recommend_edge=20))
        with inspect(self.archive, built['artifact_sha256'], self.source, Limits(recommend_edge=20)) as (root, manifest, report):
            image = report['files']['media/hero.png']
            self.assertEqual((32, 16, 'PNG', True), (image['width'], image['height'], image['format'], image['optimize']))
            self.assertEqual(image['sha256'], hashlib.sha256((root / 'migrations/media/hero.png').read_bytes()).hexdigest())
            self.assertEqual(manifest['migrations'], [ROW])
        retry = self.root / 'retry.tar'
        self.assertEqual(built['artifact_sha256'], pack(self.repo, self.source, self.code, retry)['artifact_sha256'])

    def test_missing_asset_is_rejected(self):
        (self.content / ROW['assets'][0]).unlink()
        self.source = commit(self.repo)
        with self.assertRaisesRegex(ValueError, 'missing.*hero.png'):
            self.package()
        self.assertFalse(self.archive.exists())

    def test_missing_script_is_rejected(self):
        (self.content / ROW['script']).unlink()
        self.source = commit(self.repo)
        with self.assertRaisesRegex(ValueError, 'missing.*hero-v1.php'):
            self.package()

    def test_oversized_media_build_and_target_policies(self):
        with self.assertRaisesRegex(ValueError, 'file limits'):
            self.package(limits=Limits(file_bytes=20))
        with self.assertRaisesRegex(ValueError, 'bundle limit'):
            self.package(limits=Limits(bundle_bytes=20))
        with self.assertRaisesRegex(ValueError, 'dimension limits'):
            self.package(limits=Limits(width=20))
        built = self.package()
        with self.assertRaisesRegex(ValueError, 'dimension limits'):
            with inspect(self.archive, built['artifact_sha256'], self.source, Limits(width=20)):
                self.fail()

    def test_unreferenced_and_symlink_files_are_rejected(self):
        extra = self.content / 'media/unreferenced.png'
        extra.write_bytes((self.content / ROW['assets'][0]).read_bytes())
        self.source = commit(self.repo)
        with self.assertRaisesRegex(ValueError, 'unreferenced'):
            self.package()
        extra.unlink()
        extra.symlink_to('/etc/passwd')
        self.source = commit(self.repo)
        with self.assertRaisesRegex(ValueError, 'regular Git blobs'):
            self.package()

    def test_unsafe_archive_member_is_rejected_even_with_valid_outer_digest(self):
        built = self.package()
        with tarfile.open(self.archive, 'a') as archive:
            entry = tarfile.TarInfo('migrations/../unsafe')
            entry.size = 1
            archive.addfile(entry, BytesIO(b'x'))
        new_sha = hashlib.sha256(self.archive.read_bytes()).hexdigest()
        self.assertNotEqual(built['artifact_sha256'], new_sha)
        with self.assertRaisesRegex(ValueError, 'Unsafe|Invalid artifact path'):
            with inspect(self.archive, new_sha, self.source):
                self.fail()


class Adapter:
    def __init__(self, target, clock):
        self.target, self.clock = target, clock
        self.release = BASELINE
        self.calls = []
        self.prior_content = {}
        self.fail_proof = []
        self.fail_before_switch = False
        self.fail_after_switch = False
        self.fail_after_restore = False
        self.unknown_provider_job = False
        self.available_backup = True

    def identify(self):
        return self.target

    def current(self):
        return self.release

    def authorize(self, phase, request):
        return request.owner == 'owner'

    def preflight(self, request, code, manifest, urls):
        self.calls.append('preflight')
        return {'passed': True, 'source_sha': request.source_sha, 'code_sha256': manifest['code_sha256'],
                'target': vars(self.target), 'evidence_id': 'fixture-qa'}

    def backup(self, request, challenge, purpose, fence):
        self.calls.append('backup:' + purpose)
        return {'database_verified': True, 'remote_verified': True, 'target': vars(self.target),
                'verified_at': self.clock(), 'nonce': challenge[:12], 'challenge': challenge}

    def verify_backup(self, request, backup):
        return self.available_backup

    def activate(self, request, code, manifest, current, backup, fence):
        self.calls.append('activate')
        if self.fail_before_switch:
            raise SystemExit(12)
        self.release = manifest['code_sha256']
        if self.fail_after_switch:
            # Simulate an abrupt process exit, leaving the durable intent alone.
            raise SystemExit(12)

    def proof(self, request, release, urls):
        self.calls.append('proof')
        if self.fail_proof:
            raise self.fail_proof.pop(0)
        return {'passed': True, 'release_id': release, 'urls': urls}

    def migrate(self, request, row, root, files, fingerprint, backup, fence):
        self.calls.append('migrate')
        result = 'already-applied' if self.prior_content.get(row['id']) == fingerprint else 'applied'
        self.prior_content[row['id']] = fingerprint
        return {'result': result, 'id': row['id'], 'fingerprint': fingerprint,
                'attachments': {name: index for index, name in enumerate(row['assets'], 1)}}

    def refresh(self, request, urls, fence):
        self.calls.append(('refresh', urls))
        return urls

    def verify(self, request, release, urls, migrations):
        self.calls.append('verify')
        return {'passed': self.release == release, 'release_id': self.release, 'urls': urls}

    def rollback(self, request, prior, current, backup, fence):
        self.calls.append('rollback')
        self.release = prior
        if self.fail_after_restore:
            raise SystemExit(12)

    def quiescent(self, request, intents, current):
        return not self.unknown_provider_job


class Phases(Project):
    def setUp(self):
        super().setUp()
        self.receipt = self.package()
        self.now = 1_800_000_000
        self.target = Target('example/fixture', 'staging', 'site-42', 'https://fixture.example')
        self.request = Request('run-1', 'owner', self.target, self.source, self.receipt['artifact_sha256'], ['/'])
        self.adapter = Adapter(self.target, lambda: self.now)
        state = self.root / 'state'
        state.mkdir(mode=0o700)
        self.journal = Journal(state, b'a' * 32)
        self.controller = Controller(self.adapter, self.target, self.journal, stale_seconds=60, backup_seconds=30,
                                     clock=lambda: self.now, sleep=self.advance)

    def advance(self, seconds):
        self.now += seconds

    def run_phase(self, phase, *, request=None, confirmed=None):
        archive = self.archive if phase in ('preflight', 'activate', 'migrate') else None
        return self.controller.run(phase, request or self.request, archive, confirmed_origin=confirmed)

    def activate(self):
        for phase in ('preflight', 'backup', 'activate'):
            self.assertEqual(self.run_phase(phase)['phases'][phase]['result'], 'passed')

    def test_proof_failure_retries_only_proof_then_scoped_migration(self):
        self.activate()
        self.adapter.fail_proof = [Blocked('Wrong stylesheet')]
        with self.assertRaisesRegex(Blocked, 'retry only proof'):
            self.run_phase('proof')
        self.assertEqual(self.controller.status()['next'], 'proof')
        with self.assertRaisesRegex(Blocked, 'proof phase first'):
            self.run_phase('migrate')
        self.run_phase('proof')
        self.assertEqual(self.adapter.calls, ['preflight', 'backup:deploy', 'activate', 'proof', 'proof'])
        self.run_phase('migrate')
        self.run_phase('migrate')
        self.run_phase('refresh')
        final = self.run_phase('verify')
        self.assertEqual(self.adapter.calls.count('migrate'), 1)
        self.assertIn(('refresh', ['https://fixture.example/', 'https://fixture.example/about/']), self.adapter.calls)
        self.assertEqual((final['lock'], final['migrations']['hero-v1']['result']), ('none', 'applied'))
        for receipt in self.journal.read()['events']:
            self.assertEqual(receipt, self.journal.sign(receipt['data']))

    def test_provider_retry_after_persists_and_keeps_write_counts(self):
        self.activate()
        self.adapter.fail_proof = [Throttled(429, '300')]
        with self.assertRaisesRegex(Blocked, 'rate limited'):
            self.run_phase('proof')
        status = self.controller.status()
        self.assertEqual((status['proof']['result'], status['proof']['retry_not_before']), ('throttled', self.now + 300))
        with self.assertRaisesRegex(Blocked, 'still in effect'):
            self.run_phase('proof')
        self.advance(300)
        self.run_phase('proof')
        self.assertEqual(self.adapter.calls, ['preflight', 'backup:deploy', 'activate', 'proof', 'proof'])

    def test_bounded_provider_retries_and_http_date(self):
        self.activate()
        date = format_datetime(datetime.fromtimestamp(self.now + 2, timezone.utc), usegmt=True)
        self.adapter.fail_proof = [Throttled(429, date), Throttled(503, '1'), Throttled(429, '1')]
        with self.assertRaisesRegex(Blocked, 'rate limited'):
            self.run_phase('proof')
        self.assertEqual(self.adapter.calls.count('proof'), 3)
        self.assertEqual(self.controller.status()['proof']['retry_seconds'], 1)
        self.assertIsNone(retry_delay('garbage', self.now))

    def test_interrupted_activation_is_read_back_without_reinstall(self):
        self.run_phase('preflight')
        self.run_phase('backup')
        self.adapter.fail_after_switch = True
        with self.assertRaises(SystemExit):
            self.run_phase('activate')
        self.assertEqual(self.controller.status()['next'], 'recover')
        self.adapter.unknown_provider_job = True
        with self.assertRaisesRegex(Blocked, 'unknown'):
            self.run_phase('recover', confirmed=self.target.origin)
        self.adapter.unknown_provider_job = False
        self.assertEqual(self.run_phase('recover', confirmed=self.target.origin)['next'], 'proof')
        self.run_phase('proof')
        self.assertEqual(self.adapter.calls, ['preflight', 'backup:deploy', 'activate', 'proof'])

    def test_interrupted_activation_at_baseline_can_replace_lost_backup(self):
        self.run_phase('preflight')
        receipt = self.run_phase('backup')
        original_nonce = receipt['backup']['nonce']
        self.adapter.fail_before_switch = True
        with self.assertRaises(SystemExit):
            self.run_phase('activate')
        self.adapter.available_backup = False
        self.assertEqual(self.controller.status()['next'], 'recover')
        recovered = self.run_phase('recover', confirmed=self.target.origin)
        self.assertEqual((recovered['current_release'], recovered['next']), (BASELINE, 'backup'))
        self.assertEqual(recovered['lock_id'], receipt['lock_id'])
        self.assertEqual(recovered['phases']['backup']['result'], 'rejected')
        self.assertEqual(recovered['phases']['backup']['backup_nonce'], original_nonce)
        with self.assertRaisesRegex(Blocked, 'backup phase first'):
            self.run_phase('activate')
        self.adapter.available_backup = True
        replacement = self.run_phase('backup')
        self.assertNotEqual(replacement['backup']['nonce'], original_nonce)
        self.adapter.fail_before_switch = False
        self.assertEqual(self.run_phase('activate')['next'], 'proof')

    def test_interrupted_activation_after_switch_keeps_original_backup_gate(self):
        self.run_phase('preflight')
        original_nonce = self.run_phase('backup')['backup']['nonce']
        self.adapter.fail_after_switch = True
        with self.assertRaises(SystemExit):
            self.run_phase('activate')
        self.adapter.available_backup = False
        with self.assertRaisesRegex(Blocked, 'Verified backup is unavailable'):
            self.run_phase('recover', confirmed=self.target.origin)
        with self.assertRaisesRegex(Blocked, 'requires recovery'):
            self.run_phase('backup')
        self.assertEqual(self.controller.status()['backup']['nonce'], original_nonce)
        self.adapter.available_backup = True
        self.assertEqual(self.run_phase('recover', confirmed=self.target.origin)['next'], 'proof')
        self.assertEqual(self.adapter.calls.count('backup:deploy'), 1)

    def test_recovery_reads_release_after_provider_finishes_switching(self):
        self.run_phase('preflight')
        self.run_phase('backup')
        self.adapter.fail_before_switch = True
        with self.assertRaises(SystemExit):
            self.run_phase('activate')
        self.adapter.available_backup = False

        def finish_provider_job(request, intents, current):
            self.assertEqual(current, BASELINE)
            self.adapter.release = self.receipt['code_sha256']
            return True

        with patch.object(self.adapter, 'quiescent', side_effect=finish_provider_job):
            with self.assertRaisesRegex(Blocked, 'Verified backup is unavailable'):
                self.run_phase('recover', confirmed=self.target.origin)
        status = self.controller.status()
        self.assertEqual((status['current_release'], status['next']), (self.receipt['code_sha256'], 'recover'))
        self.assertEqual(status['phases']['backup']['result'], 'passed')

    def test_stale_lock_preserves_owner_and_status_is_read_only(self):
        active = self.run_phase('preflight')
        self.advance(61)
        receipt = self.journal.active.read_bytes()
        status = self.controller.status()
        self.assertEqual((status['lock'], status['next']), ('stale', 'recover'))
        self.assertEqual(self.journal.active.read_bytes(), receipt)
        other_run = replace(self.request, deployment_id='other')
        with self.assertRaisesRegex(Blocked, 'Another run'):
            self.run_phase('preflight', request=other_run)
        with self.assertRaisesRegex(Blocked, 'confirmation'):
            self.run_phase('recover')
        self.assertEqual(self.run_phase('recover', confirmed=self.target.origin)['lock_id'], active['lock_id'])

    def test_writer_lock_fences_concurrent_commands(self):
        self.run_phase('preflight')
        with self.journal.writer():
            with self.assertRaisesRegex(Blocked, 'already has a deployment command'):
                self.run_phase('backup')
        self.run_phase('backup')

    def test_new_run_replays_completed_content_without_writes(self):
        self.activate()
        for phase in ('proof', 'migrate', 'refresh', 'verify'):
            self.run_phase(phase)
        other = replace(self.request, deployment_id='run-2')
        for phase in ('preflight', 'backup', 'activate', 'proof', 'migrate'):
            result = self.run_phase(phase, request=other)
        self.assertEqual(result['migrations']['hero-v1']['result'], 'already-applied')
        self.assertEqual(self.adapter.calls.count('activate'), 1)
        self.assertTrue((self.journal.root / 'run-run-1.json').is_file())

    def test_rollback_after_failed_proof_uses_fresh_backup_and_verifies_prior_code(self):
        self.activate()
        self.adapter.fail_proof = [Blocked('Proof failed')]
        with self.assertRaises(Blocked):
            self.run_phase('proof')
        with self.assertRaisesRegex(Blocked, 'confirmation'):
            self.run_phase('rollback')
        status = self.run_phase('rollback', confirmed=self.target.origin)
        self.assertEqual((status['current_release'], status['rollback_state'], status['lock']), (BASELINE, 'verified', 'none'))
        self.assertEqual(self.adapter.calls[-4:], ['backup:rollback', 'rollback', ('refresh', ['https://fixture.example/']), 'verify'])

    def test_interrupted_rollback_resumes_without_reselecting(self):
        self.activate()
        self.adapter.fail_after_restore = True
        with self.assertRaises(SystemExit):
            self.run_phase('rollback', confirmed=self.target.origin)
        self.assertEqual(self.run_phase('recover', confirmed=self.target.origin)['next'], 'rollback')
        status = self.run_phase('rollback', confirmed=self.target.origin)
        self.assertEqual(status['rollback_state'], 'verified')
        self.assertEqual(self.adapter.calls.count('rollback'), 1)

    def test_rollback_does_not_restore_database_after_content(self):
        self.activate()
        self.run_phase('proof')
        self.run_phase('migrate')
        with self.assertRaisesRegex(Blocked, 'zero attempted content writes'):
            self.run_phase('rollback', confirmed=self.target.origin)
        self.assertEqual(self.controller.status()['rollback_state'], 'content-restore-requires-operator')
        self.assertNotIn('rollback', self.adapter.calls)

    def test_wrong_target_missing_backup_and_tampered_receipt_fail_closed(self):
        wrong = replace(self.request, target=replace(self.target, site_id='different'))
        with self.assertRaisesRegex(Blocked, 'Requested target'):
            self.run_phase('preflight', request=wrong)
        self.run_phase('preflight')
        self.run_phase('backup')
        self.adapter.available_backup = False
        with self.assertRaisesRegex(Blocked, 'Verified backup is unavailable'):
            self.run_phase('activate')
        self.assertNotIn('activate', self.adapter.calls)
        self.assertEqual(self.controller.status()['next'], 'backup')
        receipt = json.loads(self.journal.active.read_text())
        receipt['data']['owner'] = 'other'
        self.journal.active.write_text(json.dumps(receipt))
        with self.assertRaisesRegex(Blocked, 'authentication'):
            self.controller.status()

    def test_staging_request_never_activates_production(self):
        production = replace(self.target, environment='production')
        self.adapter.target = production
        live = Controller(self.adapter, production, self.journal, clock=lambda: self.now)
        with self.assertRaisesRegex(Blocked, 'Requested target'):
            live.run('preflight', self.request, self.archive)
        live_request = replace(self.request, target=production)
        with self.assertRaisesRegex(Blocked, 'confirmation'):
            live.run('preflight', live_request, self.archive)
        unapproved = replace(live_request, owner='other')
        with self.assertRaisesRegex(Blocked, 'authorization'):
            live.run('preflight', unapproved, self.archive, confirmed_origin=production.origin)
        self.assertEqual(self.adapter.calls, [])

    def test_expired_backup_can_be_renewed_only_before_first_activation(self):
        self.run_phase('preflight')
        self.run_phase('backup')
        self.advance(31)
        self.assertEqual(self.controller.status()['next'], 'backup')
        self.run_phase('backup')
        self.run_phase('activate')
        self.assertEqual(self.adapter.calls.count('backup:deploy'), 2)

    def test_cli_status_is_read_only_and_reports_actionable_next_command(self):
        script = self.root / 'fixture_adapter.py'
        script.write_text(f"""
import sys
sys.path.insert(0, {str(TOOLS / 'tests')!r})
from test_release_v1 import Adapter, Target
def configure():
    target = Target(**{vars(self.target)!r})
    return dict(target=target, journal={str(self.journal.root)!r}, adapter=Adapter(target, lambda: 0))
""")
        before = sorted(os.listdir(self.journal.root))
        result = subprocess.check_output([sys.executable, str(TOOLS / 'mrn-site-release'), 'status', '--adapter', str(script)],
                                         env={**os.environ, 'MRN_RELEASE_RECEIPT_KEY': (b'a' * 32).hex()}, text=True)
        status = json.loads(result)
        self.assertEqual(status['current_release'], BASELINE)
        self.assertIn('mrn-site-release preflight', status['next_command'])
        self.assertEqual(sorted(os.listdir(self.journal.root)), before)


class Health(unittest.TestCase):
    def test_runtime_diagnostics(self):
        url = 'http://fixture.local/'
        healthy = {'load': lambda: (0, 0, 0), 'sample': lambda _: (200, 0.1)}
        self.assertEqual(runtime_health(url, **healthy)['sample_seconds'], [0.1] * 5)
        for options, message in [
            ({'running': lambda: False}, 'stopped'),
            ({'sample': lambda _: (502, 0)}, '502'),
            ({'load': lambda: (10000, 10000, 10000)}, 'saturation'),
            ({'sample': lambda _: (200, 3)}, 'unstable'),
        ]:
            with self.subTest(message=message), self.assertRaisesRegex(Blocked, message):
                runtime_health(url, **{**healthy, **options})
