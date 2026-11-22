"""Failed source-job retention on isolated roots; no credentials, network or sites."""
import json
import os
from pathlib import Path
import stat
import sys
import tempfile
import time
import unittest
from unittest import mock

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

    def published(self, current=False):
        job = self.job(failure=False)
        attempt = job / 'merged-1'
        (attempt / 'platform-a/mu-plugins/agent').mkdir(parents=True)
        (attempt / 'platform-a/mu-plugins/agent/agent.php').write_text('<?php')
        (attempt / 'platform-a/mu-plugins/agent/readme.txt').write_text('cited text')
        (attempt / 'bootstrap-a.tar').write_bytes(b'b' * 1024)
        (attempt / 'packages').symlink_to(job / 'packages', target_is_directory=True)
        (job / 'evidence/node_modules/pkg').mkdir(parents=True)
        (job / 'evidence/node_modules/pkg/package.json').write_text('{}')
        (job / 'evidence/vendor/lib').mkdir(parents=True)
        (job / 'evidence/vendor/lib/readme.md').write_text('vendored')
        (job / 'published-control').mkdir()
        (job / 'published-control/stack-plugin-releases.json').write_text('{}')
        (job / 'SHA256SUMS.txt').write_text('abc  mrn-base-stack.zip\n')
        (job / 'mrn-base-stack-1.0.0.zip').write_bytes(b'p' * 512)
        coordinator.write(job / 'publication.json', {'status': 'pass', 'site_writes': False})
        coordinator.write(job / 'progress.json', {'phase': 'published', 'sealed': {
            str(attempt / 'bootstrap-a.tar'): 'f' * 64}})
        if current:
            coordinator.write(self.canonical / 'releases/fleet-ready/current.json',
                              {'artifact_root': str(job / 'repos/MRN'),
                               'registry_path': str(job / 'published-control/stack-plugin-releases.json')})
        return job

    def retain(self, **options):
        return coordinator.retain_jobs(self.settings, now=NOW, **options)

    def pruned(self, summary, kind=None):
        return [row['job'] for row in summary['pruned'] if kind is None or row['kind'] == kind]

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
        self.assertEqual(self.pruned(summary, 'failed'), [job.name])
        self.assertGreater(summary['bytes_freed'], 4096 + 2048 + 1024)
        for gone in ('repos', 'packages', 'bootstrap-a/themes', 'bootstrap-a.tar', 'site-release-v1-a.tar',
                     'evidence/native-fixture/wordpress.zip', 'evidence/screenshot.png'):
            self.assertFalse((job / gone).exists() or (job / gone).is_symlink(), gone)
        for kept in ('failure.json', 'selected-source.json', 'metadata-commit-qa.log', 'notes.md',
                     'evidence/native-fixture/native.log', 'evidence/native-fixture/browser.json',
                     'bootstrap-a/bootstrap-bundle.json', 'platform-a/bootstrap-bundle.json'):
            self.assertTrue((job / kept).is_file(), kept)
        # The retry backoff still reads the untouched failure timestamp.
        self.assertEqual((job / 'failure.json').stat().st_mtime, failure_mtime)
        self.assertEqual(list(self.jobs.glob('a' * 20 + '-*/failure.json')), [job / 'failure.json'])
        # A symlink inside the clone is removed, never followed.
        self.assertEqual(self.outside.read_text(), 'not part of any job')
        record = json.loads((job / 'pruned.json').read_text())
        self.assertIn('repos', record['removed_trees'])
        self.assertEqual(record['kind'], 'failed')
        self.assertFalse(record['site_writes'])
        self.assertEqual(self.pruned(json.loads((self.state / 'retention.json').read_text())), [job.name])

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
        self.assertEqual(self.pruned(self.retain()), [pruned.name])
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
        summary = coordinator.retain_jobs({'state_root': str(self.state)}, now=NOW)
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
        self.assertEqual(self.pruned(summary), [oldest.name, middle.name])
        self.assertEqual(summary['remaining'], 1)
        self.assertIntact(newest)
        self.assertEqual(self.pruned(self.retain(limit=2)), [newest.name])

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
        self.assertEqual(self.outside.read_text(), 'not part of any job')

    def test_prune_error_is_reported_and_other_jobs_continue(self):
        broken, healthy = self.job(failed_at=OLD - 60), self.job()
        original = coordinator.thin_job

        def thin(job, now, kind):
            if job == broken:
                raise PermissionError('denied')
            return original(job, now, kind)

        coordinator.thin_job = thin
        try:
            summary = self.retain()
        finally:
            coordinator.thin_job = original
        self.assertEqual(summary['status'], 'partial')
        self.assertEqual(self.pruned(summary), [healthy.name])
        self.assertEqual(summary['errors'][0]['job'], broken.name)
        self.assertIntact(broken)

    def test_default_clock_keeps_a_just_written_failure(self):
        job = self.job(failed_at=time.time())
        self.assertEqual(coordinator.retain_jobs(self.settings)['pruned'], [])
        self.assertIntact(job)

    def assertPublishedIntact(self, job):
        self.assertTrue((job / 'repos/MRN/.git/objects/pack.pack').is_file())
        self.assertTrue((job / 'merged-1/bootstrap-a.tar').is_file())
        self.assertTrue((job / 'mrn-base-stack-1.0.0.zip').is_file())
        self.assertFalse((job / 'pruned.json').exists())

    def test_current_and_three_newest_other_published_jobs_stay_intact(self):
        older = [self.published() for _ in range(3)]
        kept = [self.published() for _ in range(coordinator.PUBLISHED_KEEP)]
        current = self.published(current=True)
        coordinator.write(self.state / 'status.json', {'status': 'fleet_ready', 'job': str(current)})
        summary = self.retain()
        self.assertEqual(self.pruned(summary, 'published'), [job.name for job in older])
        for job in kept + [current]:
            self.assertPublishedIntact(job)

    def test_older_published_job_keeps_text_evidence_only(self):
        job = self.published()
        for _ in range(coordinator.PUBLISHED_KEEP):
            self.published()
        self.published(current=True)
        self.assertEqual(self.pruned(self.retain()), [job.name])
        for gone in ('repos', 'packages/plugin.zip', 'merged-1/bootstrap-a.tar', 'merged-1/packages',
                     'merged-1/platform-a/mu-plugins/agent/agent.php', 'mrn-base-stack-1.0.0.zip',
                     'bootstrap-a/themes', 'evidence/node_modules', 'evidence/vendor'):
            self.assertFalse((job / gone).exists() or (job / gone).is_symlink(), gone)
        for kept in ('SHA256SUMS.txt', 'publication.json', 'progress.json', 'selected-source.json',
                     'published-control/stack-plugin-releases.json', 'metadata-commit-qa.log',
                     'merged-1/platform-a/mu-plugins/agent/readme.txt',
                     'bootstrap-a/bootstrap-bundle.json', 'evidence/native-fixture/native.log'):
            self.assertTrue((job / kept).is_file(), kept)
        self.assertEqual(self.outside.read_text(), 'not part of any job')
        # The publication record still reads as published after thinning.
        self.assertTrue(coordinator.published(job))
        self.assertEqual(json.loads((job / 'pruned.json').read_text())['kind'], 'published')

    def test_protected_published_jobs_do_not_count_toward_the_kept_three(self):
        status = self.published()
        oldest = self.published()
        newest = [self.published() for _ in range(coordinator.PUBLISHED_KEEP)]
        current = self.published(current=True)
        coordinator.write(self.state / 'status.json', {'job': str(status)})
        self.assertEqual(self.pruned(self.retain()), [oldest.name])
        for job in [status, current] + newest:
            self.assertPublishedIntact(job)

    def test_published_jobs_stay_intact_without_a_fleet_ready_index(self):
        jobs = [self.published() for _ in range(coordinator.PUBLISHED_KEEP + 2)]
        failed = self.job()
        self.assertEqual(self.pruned(self.retain()), [failed.name])
        for job in jobs:
            self.assertPublishedIntact(job)

    def test_unfinished_or_unreadable_publications_are_never_thinned(self):
        cases = {
            'ready': lambda job: coordinator.write(job / 'progress.json', {'phase': 'ready'}),
            'failed-publication': lambda job: coordinator.write(job / 'publication.json', {'status': 'fail'}),
            'unreadable-progress': lambda job: (job / 'progress.json').write_text('{broken'),
            'no-checksums': lambda job: (job / 'SHA256SUMS.txt').unlink(),
            'no-publication': lambda job: (job / 'publication.json').unlink(),
        }
        unfinished = {}
        for name, change in cases.items():
            job = self.published()
            change(job)
            unfinished[name] = job
        for _ in range(coordinator.PUBLISHED_KEEP):
            self.published()
        self.published(current=True)
        self.assertEqual(self.retain()['pruned'], [])
        for name, job in unfinished.items():
            with self.subTest(case=name):
                self.assertPublishedIntact(job)

    def test_failed_jobs_are_thinned_before_published_jobs_within_the_limit(self):
        published = self.published()
        for _ in range(coordinator.PUBLISHED_KEEP):
            self.published()
        self.published(current=True)
        failed = self.job()
        summary = self.retain(limit=1)
        self.assertEqual(summary['pruned'], [{'job': failed.name, 'kind': 'failed'}])
        self.assertEqual(summary['remaining'], 1)
        self.assertEqual(self.pruned(self.retain(limit=1)), [published.name])


    def checkout(self, created_at=OLD):
        job = self.jobs / ('c' * 20 + '-' + str(int(created_at * 1e9) + self.serial))
        self.serial += 1
        (job / 'repos/MRN/.git/objects').mkdir(parents=True)
        (job / 'repos/MRN/.git/objects/pack.pack').write_bytes(b'x' * 4096)
        (job / 'repos/MRN-plugins').mkdir()
        (job / 'repos/MRN-plugins/escape').symlink_to(self.outside)
        return job

    def test_old_checkout_only_jobs_are_removed_without_following_links(self):
        old = self.checkout()
        (old / 'repos/MRN/.git/objects').chmod(0o500)
        summary = self.retain()
        self.assertEqual(summary['pruned'], [{'job': old.name, 'kind': 'checkout'}])
        self.assertFalse(old.exists())
        self.assertGreaterEqual(summary['bytes_freed'], 4096)
        self.assertEqual(self.outside.read_text(), 'not part of any job')
        self.assertEqual(self.retain()['pruned'], [])

    def test_fresh_referenced_or_started_checkouts_are_kept(self):
        fresh = self.checkout(created_at=NOW - 60)
        referenced = self.checkout()
        coordinator.write(self.state / 'status.json', {'status': 'blocked', 'evidence': str(referenced)})
        started = self.checkout()
        (started / 'selected-source.json').write_text('{}')
        linked = self.checkout()
        (linked / 'repos').rename(linked / 'repos-real')
        (linked / 'repos').symlink_to(linked / 'repos-real', target_is_directory=True)
        self.assertEqual(self.retain()['pruned'], [])
        for job in (fresh, referenced, started):
            self.assertTrue((job / 'repos/MRN/.git/objects/pack.pack').is_file())
        self.assertTrue((linked / 'repos-real/MRN/.git/objects/pack.pack').is_file())

    def test_checkouts_go_before_failed_jobs_within_the_limit(self):
        failed = self.job()
        checkout = self.checkout()
        summary = self.retain(limit=1)
        self.assertEqual(summary['pruned'], [{'job': checkout.name, 'kind': 'checkout'}])
        self.assertEqual(self.pruned(self.retain(limit=1)), [failed.name])

    def no_change(self, **patches):
        self.state.chmod(0o700)
        refs = {'MRN': 'b' * 40, 'MRN-qa-engine': 'c' * 40}
        coordinator.write(self.state / 'status.json', {
            'status': 'fleet_ready', 'material_sha256': 'm' * 64, 'site_writes': False,
            'observed_refs': {'MRN': 'a' * 40, 'MRN-qa-engine': 'c' * 40}})
        clone = lambda name, destination: Path(destination).mkdir(parents=True)
        with mock.patch.object(coordinator, 'clone', side_effect=clone), \
                mock.patch.object(coordinator, 'roster', return_value=[]), \
                mock.patch.object(coordinator, 'remote_refs', return_value=refs), \
                mock.patch.object(coordinator, 'dependencies'), \
                mock.patch.object(coordinator, 'snapshot',
                                  return_value={'refs': refs, 'material_sha256': 'm' * 64}), \
                mock.patch.object(coordinator, 'retain_jobs'):
            for name, value in patches.items():
                mock.patch.object(coordinator, name, value).start()
            try:
                return coordinator.once({**self.settings, 'qualification': {}, 'publish': True})
            finally:
                mock.patch.stopall()

    def test_no_change_exit_removes_its_fresh_checkout(self):
        result = self.no_change()
        self.assertEqual(result['status'], 'no_change')
        self.assertEqual(list(self.jobs.iterdir()), [])
        self.assertEqual(coordinator.read(self.state / 'status.json')['status'], 'no_change')

    def test_no_change_exit_survives_a_failed_removal(self):
        result = self.no_change(discard_checkout=mock.Mock(side_effect=PermissionError('denied')))
        self.assertEqual(result['status'], 'no_change')
        (job,) = self.jobs.iterdir()
        self.assertTrue(coordinator.checkout_only(job))

if __name__ == '__main__':
    unittest.main()
