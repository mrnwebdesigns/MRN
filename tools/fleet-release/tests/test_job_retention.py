"""Failed source-job retention on isolated roots; no credentials, network or sites."""
import json
import os
from pathlib import Path
import stat
import sys
import tempfile
import time
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import coordinator

NOW = 1_800_000_000.0
OLD = NOW - coordinator.FAILURE_BACKOFF_SECONDS - 60


class FailedJobRetention(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        base = Path(self.temporary.name)
        self.state = base / 'state'
        self.jobs = self.state / 'jobs'
        self.jobs.mkdir(parents=True)
        self.canonical = base / 'MRN'
        self.canonical.mkdir()
        self.outside = base / 'outside.txt'
        self.outside.write_text('not part of any job')
        self.settings = {'state_root': str(self.state), 'canonical_repo': str(self.canonical)}
        self.serial = 0

    def tearDown(self):
        for root, directories, _ in os.walk(self.temporary.name):
            for name in directories:
                path = Path(root) / name
                if not path.is_symlink():
                    path.chmod(0o700)
        self.temporary.cleanup()

    def job(self, failed_at=OLD, vector='a' * 20, failure=True):
        self.serial += 1
        job = self.jobs / (vector + '-' + str(1_790_000_000_000_000_000 + self.serial))
        (job / 'repos/MRN/.git/objects').mkdir(parents=True)
        (job / 'repos/MRN/.git/objects/pack.pack').write_bytes(b'x' * 4096)
        (job / 'repos/MRN/README.md').write_text('cloned source text is not evidence')
        (job / 'repos/MRN-plugins').mkdir()
        (job / 'repos/MRN-plugins/escape').symlink_to(self.outside)
        (job / 'packages').mkdir()
        (job / 'packages/plugin.zip').write_bytes(b'z' * 2048)
        for name in ('bootstrap-a', 'bootstrap-b', 'platform-a', 'fleet-b', 'optional-a'):
            (job / name / 'themes').mkdir(parents=True)
            (job / name / 'themes/theme.zip').write_bytes(b't' * 512)
            (job / name / 'bootstrap-bundle.json').write_text('{}')
        (job / 'bootstrap-a.tar').write_bytes(b'b' * 1024)
        (job / 'site-release-v1-a.tar').write_bytes(b's' * 256)
        (job / 'evidence/native-fixture').mkdir(parents=True)
        (job / 'evidence/native-fixture/native.log').write_text('native log')
        (job / 'evidence/native-fixture/browser.json').write_text('{}')
        (job / 'evidence/native-fixture/wordpress.zip').write_bytes(b'w' * 128)
        (job / 'evidence/screenshot.png').write_bytes(b'png')
        (job / 'selected-source.json').write_text('{}')
        (job / 'metadata-commit-qa.log').write_text('qa')
        (job / 'notes.md').write_text('notes')
        if failure:
            (job / 'failure.json').write_text(json.dumps({'error': 'boom', 'site_writes': False}))
            os.utime(job / 'failure.json', (failed_at, failed_at))
        return job

    def retain(self, **options):
        return coordinator.retain_failed_jobs(self.settings, now=NOW, **options)

    def assertIntact(self, job):
        self.assertTrue((job / 'repos/MRN/.git/objects/pack.pack').is_file())
        self.assertTrue((job / 'packages/plugin.zip').is_file())
        self.assertTrue((job / 'bootstrap-a.tar').is_file())
        self.assertFalse((job / 'pruned.json').exists())

    def test_old_failure_keeps_diagnostics_and_drops_reproducible_bulk(self):
        job = self.job()
        failure_mtime = (job / 'failure.json').stat().st_mtime
        summary = self.retain()
        self.assertEqual(summary['status'], 'pass')
        self.assertEqual(summary['pruned'], [job.name])
        self.assertGreater(summary['bytes_freed'], 4096 + 2048 + 1024)
        for gone in ('repos', 'packages', 'bootstrap-a', 'bootstrap-b', 'platform-a', 'fleet-b',
                     'optional-a', 'bootstrap-a.tar', 'site-release-v1-a.tar',
                     'evidence/native-fixture/wordpress.zip'):
            self.assertFalse((job / gone).exists() or (job / gone).is_symlink(), gone)
        for kept in ('failure.json', 'selected-source.json', 'metadata-commit-qa.log', 'notes.md',
                     'evidence/native-fixture/native.log', 'evidence/native-fixture/browser.json',
                     'evidence/screenshot.png'):
            self.assertTrue((job / kept).is_file(), kept)
        # The retry backoff still reads the untouched failure timestamp.
        self.assertEqual((job / 'failure.json').stat().st_mtime, failure_mtime)
        self.assertEqual(list(self.jobs.glob('a' * 20 + '-*/failure.json')), [job / 'failure.json'])
        # A symlink inside the clone is removed, never followed.
        self.assertEqual(self.outside.read_text(), 'not part of any job')
        record = json.loads((job / 'pruned.json').read_text())
        self.assertIn('repos', record['removed'])
        self.assertFalse(record['site_writes'])
        self.assertEqual(json.loads((self.state / 'retention.json').read_text())['pruned'], [job.name])

    def test_rerun_is_a_no_op(self):
        job = self.job()
        self.retain()
        before = (job / 'pruned.json').read_text()
        summary = self.retain()
        self.assertEqual(summary['pruned'], [])
        self.assertEqual((job / 'pruned.json').read_text(), before)

    def test_failure_inside_backoff_window_stays_intact(self):
        recent = self.job(failed_at=NOW - coordinator.FAILURE_BACKOFF_SECONDS + 60)
        self.assertEqual(self.retain()['pruned'], [])
        self.assertIntact(recent)
        self.assertFalse((self.state / 'retention.json').exists())

    def test_sealed_or_published_jobs_are_never_pruned(self):
        for marker in coordinator.RETAINED_MARKERS:
            with self.subTest(marker=marker):
                job = self.job()
                (job / marker).write_text('{}')
                self.retain()
                self.assertIntact(job)

    def test_jobs_named_by_controller_state_or_fleet_index_are_never_pruned(self):
        active, status, blocked, indexed = self.job(), self.job(), self.job(), self.job()
        pruned = self.job()
        coordinator.write(self.state / 'active.json', {'job': str(active)})
        coordinator.write(self.state / 'status.json', {'status': 'fleet_ready', 'job': str(status)})
        coordinator.write(self.state / 'failure.json', {'status': 'blocked', 'evidence': str(blocked)})
        coordinator.write(self.canonical / 'releases/fleet-ready/current.json',
                          {'catalog_path': str(indexed / 'published-control/component-catalog.json'),
                           'artifact_root': str(indexed / 'repos/MRN')})
        self.assertEqual(self.retain()['pruned'], [pruned.name])
        for job in (active, status, blocked, indexed):
            self.assertIntact(job)

    def test_unreadable_reference_record_prunes_nothing(self):
        job = self.job()
        (self.state / 'status.json').write_text('{not json')
        summary = self.retain()
        self.assertEqual(summary['status'], 'skipped')
        self.assertIntact(job)

    def test_unknown_fleet_index_location_prunes_nothing(self):
        job = self.job()
        summary = coordinator.retain_failed_jobs({'state_root': str(self.state)}, now=NOW)
        self.assertEqual(summary['status'], 'skipped')
        self.assertIntact(job)

    def test_unrelated_entries_and_symlinked_jobs_are_ignored(self):
        real = self.job(failure=True)
        renamed = real.with_name('manual-copy')
        real.rename(renamed)
        (self.jobs / ('b' * 20 + '-1')).symlink_to(renamed, target_is_directory=True)
        no_failure = self.job(failure=False)
        self.assertEqual(self.retain()['pruned'], [])
        self.assertIntact(renamed)
        self.assertIntact(no_failure)

    def test_limit_prunes_oldest_failures_first(self):
        newest = self.job(failed_at=OLD)
        oldest = self.job(failed_at=OLD - 600)
        middle = self.job(failed_at=OLD - 300)
        summary = self.retain(limit=2)
        self.assertEqual(summary['pruned'], [oldest.name, middle.name])
        self.assertEqual(summary['remaining'], 1)
        self.assertIntact(newest)
        self.assertEqual(self.retain(limit=2)['pruned'], [newest.name])

    def test_merged_attempt_links_and_write_protected_clone_folders(self):
        job = self.job()
        attempt = job / 'merged-1'
        attempt.mkdir()
        (attempt / 'packages').symlink_to(job / 'packages', target_is_directory=True)
        (attempt / 'bootstrap-a').mkdir()
        (attempt / 'bootstrap-a/payload.php').write_text('<?php')
        (attempt / 'evidence').mkdir()
        (attempt / 'evidence/qualification.json').write_text('{}')
        locked = job / 'repos/MRN/.git/objects'
        locked.chmod(stat.S_IRUSR | stat.S_IXUSR)
        summary = self.retain()
        self.assertEqual(summary['errors'], [])
        self.assertFalse((job / 'repos').exists())
        self.assertFalse((attempt / 'packages').is_symlink())
        self.assertFalse((attempt / 'bootstrap-a').exists())
        self.assertTrue((attempt / 'evidence/qualification.json').is_file())

    def test_prune_error_is_reported_and_other_jobs_continue(self):
        broken, healthy = self.job(failed_at=OLD - 60), self.job()
        original = coordinator.prune_failed_job

        def prune(job, now):
            if job == broken:
                raise PermissionError('denied')
            return original(job, now)

        coordinator.prune_failed_job = prune
        try:
            summary = self.retain()
        finally:
            coordinator.prune_failed_job = original
        self.assertEqual(summary['status'], 'partial')
        self.assertEqual(summary['pruned'], [healthy.name])
        self.assertEqual(summary['errors'][0]['job'], broken.name)
        self.assertIntact(broken)

    def test_default_clock_keeps_a_just_written_failure(self):
        job = self.job(failed_at=time.time())
        self.assertEqual(coordinator.retain_failed_jobs(self.settings)['pruned'], [])
        self.assertIntact(job)


if __name__ == '__main__':
    unittest.main()
