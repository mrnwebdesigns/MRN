"""Failure-oriented tests on isolated roots; no credentials, network or sites."""
import fcntl
import hashlib
import io
import json
import os
from pathlib import Path
import sys
import tarfile
import tempfile
import unittest
from unittest.mock import patch
import zipfile

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import build
import common
import coordinator
import fixture
import host
import qualify


class SourcePublication(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.base = Path(self.temporary.name)
        self.root = self.base / 'stack'; self.root.mkdir()
        self.depot = self.base / 'depot'; self.depot.mkdir()
        self.release = '2026.10.09-fleet-auto-123456abcdef'
        self.old = self.bundle(self.root, 'previous', b'old')
        self.previous = host.sha(self.root / 'bootstrap-bundle.json')
        self.job = self.depot / self.release; self.job.mkdir()
        source = self.base / 'candidate'; source.mkdir()
        self.new = self.bundle(source, self.release, b'new')
        build.archive_tree(source, self.job / 'bootstrap.tar')
        self.archive = host.sha(self.job / 'bootstrap.tar')
        self.manifest = host.sha(source / 'bootstrap-bundle.json')
        fleet = self.job / ('mrn-stack-release-' + self.release + '.zip')
        fleet.write_bytes(b'private fleet fixture')
        self.proof = {'status': 'pass', 'release_id': self.release,
                      'lock_sha256': self.new['release_lock_sha256'],
                      'bootstrap_sha256': self.archive, 'fleet_sha256': host.sha(fleet),
                      'source_vector_sha256': 'a' * 64, 'coverage': sorted(host.COVERAGE),
                      'site_writes': False}
        common.write(self.job / 'qualification.json', self.proof)
        common.write(self.base / '.mrn-source-lease-v1.json', {'schema_version': 1,
                     'source_root': str(self.root), 'legacy_consumers_paused': True})
        self.store = host.SourceStore(self.root, self.depot)

    def tearDown(self):
        self.temporary.cleanup()

    @staticmethod
    def bundle(root, release, value):
        lock = root / 'manifests/stack-release.lock.json'; lock.parent.mkdir()
        lock.write_bytes(value)
        plugin = root / 'shared/runtime.php'; plugin.parent.mkdir(); plugin.write_bytes(value)
        lock.chmod(0o644); plugin.chmod(0o644)
        records = [{'path': p.relative_to(root).as_posix(), 'sha256': host.sha(p),
                    'size': p.stat().st_size, 'mode': 0o644} for p in (lock, plugin)]
        manifest = {'release_id': release, 'release_lock_sha256': host.sha(lock), 'files': records}
        common.write(root / 'bootstrap-bundle.json', manifest)
        (root / 'bootstrap-bundle.json').chmod(0o644)
        return manifest

    def publish(self, fault=None):
        return self.store.publish(self.release, self.archive, self.manifest, self.previous, fault=fault)

    def test_publication_readback_idempotence_and_retained_recovery(self):
        receipt = self.publish()
        self.assertEqual(receipt['status'], 'published_verified')
        self.assertEqual(self.publish(), receipt)
        self.assertTrue((self.job / 'rollback.tar').is_file())
        self.assertEqual((self.root / 'shared/runtime.php').read_bytes(), b'new')

    def test_missing_required_coverage_keeps_previous_current(self):
        self.proof['coverage'].remove('no-woocommerce')
        common.write(self.job / 'qualification.json', self.proof)
        with self.assertRaises(host.PublicationError): self.publish()
        self.assertEqual(host.sha(self.root / 'bootstrap-bundle.json'), self.previous)

    def test_wrong_artifact_receipt_blocks_before_write(self):
        self.proof['bootstrap_sha256'] = 'b' * 64
        common.write(self.job / 'qualification.json', self.proof)
        with self.assertRaises(host.PublicationError): self.publish()
        self.assertFalse((self.job / 'rollback.tar').exists())

    def test_source_lease_defers_publication(self):
        with self.store.lock_path.open('a') as lease:
            fcntl.flock(lease, fcntl.LOCK_SH)
            with self.assertRaises(host.Busy): self.publish()
        self.assertEqual(host.sha(self.root / 'bootstrap-bundle.json'), self.previous)

    def test_unenrolled_legacy_consumers_block(self):
        (self.base / '.mrn-source-lease-v1.json').unlink()
        with self.assertRaises(host.PublicationError): self.publish()

    def test_midwrite_failure_restores_exact_previous_files(self):
        def fail(index, name):
            if index == 0: raise RuntimeError('injected')
        with self.assertRaises(RuntimeError): self.publish(fail)
        self.assertEqual(self.store.preflight()['manifest_sha256'], self.previous)
        self.assertEqual(common.read(self.job / 'publication-receipt.json')['status'], 'rolled_back_verified')

    def test_verified_rollback_retries_without_replacing_recovery_archive(self):
        def fail(index, name):
            if index == 0: raise RuntimeError('injected')
        with self.assertRaises(RuntimeError): self.publish(fail)
        checksum = host.sha(self.job / 'rollback.tar')
        self.assertEqual(self.publish()['status'], 'published_verified')
        self.assertEqual(host.sha(self.job / 'rollback.tar'), checksum)

    def test_crash_recovers_before_any_retry(self):
        class Crash(BaseException): pass
        def crash(index, name):
            if index == 0: raise Crash()
        with self.assertRaises(Crash): self.publish(crash)
        receipt = self.publish()
        self.assertEqual(receipt['status'], 'rolled_back_verified')
        self.assertEqual(self.store.preflight()['manifest_sha256'], self.previous)

    def test_unknown_interrupted_bytes_are_never_overwritten(self):
        class Crash(BaseException): pass
        def crash(index, name):
            if index == 0: raise Crash()
        with self.assertRaises(Crash): self.publish(crash)
        (self.root / 'shared/runtime.php').write_bytes(b'unknown external write')
        with self.assertRaises(host.PublicationError): self.publish()
        self.assertEqual(common.read(self.job / 'publication-receipt.json')['status'], 'uncertain')
        self.assertEqual((self.root / 'shared/runtime.php').read_bytes(), b'unknown external write')

    def test_path_traversal_symlinks_and_site_paths_rejected(self):
        for name in ('../other', 'secrets/key', '/home/site/wp-config.php', 'runtime/state', 'themes/client/style.css'):
            with self.assertRaises(host.PublicationError): host.allowed(name)
        (self.root / 'shared/escape.php').symlink_to(self.base / 'outside')
        with self.assertRaises(host.PublicationError): host.safe(self.root, 'shared/escape.php')

    def test_private_transfer_cannot_rebind_immutable_destination(self):
        filename = 'source.tar'; target = self.job / filename; target.write_bytes(b'old')
        checksum = hashlib.sha256(b'new').hexdigest()
        (self.job / (filename + '.incoming-' + checksum)).write_bytes(b'new')
        with self.assertRaises(host.PublicationError): self.store.accept_artifact(self.release, filename, checksum)
        self.assertEqual(target.read_bytes(), b'old')


class QualificationContracts(unittest.TestCase):
    def test_required_runtime_rows_cannot_be_skipped(self):
        with tempfile.TemporaryDirectory() as temporary:
            report = Path(temporary) / 'qa.md'
            report.write_text('**Release QA Result: 100% SUCCESS**\n| Accessibility smoke | Skipped | Pass |\n')
            with self.assertRaises(common.ReleaseError): qualify.require_engine_pass(report, runtime=True)

    def test_deterministic_build_mismatch_blocks(self):
        with tempfile.TemporaryDirectory() as temporary:
            first = Path(temporary) / 'first'; first.write_bytes(b'first')
            second = Path(temporary) / 'second'; second.write_bytes(b'second')
            with self.assertRaises(common.ReleaseError):
                coordinator.deterministic({'fleet': first, 'bootstrap_archive': first},
                                          {'fleet': second, 'bootstrap_archive': second})

    def test_fixture_zip_cannot_escape_or_contain_symlinks(self):
        with tempfile.TemporaryDirectory() as temporary:
            archive = Path(temporary) / 'unsafe.zip'
            for name, mode in (('../wp-config.php', 0o644), ('wordpress/link', 0o120777)):
                with zipfile.ZipFile(archive, 'w') as stream:
                    item = zipfile.ZipInfo(name); item.external_attr = mode << 16; stream.writestr(item, b'x')
                with self.assertRaises(common.ReleaseError): fixture.extract(archive, Path(temporary) / 'output')

    def test_immutable_zip_envelope_preserved_when_source_matches(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary); repo = root / 'repo'; repo.mkdir()
            packages = root / 'inputs'; packages.mkdir()
            source = packages / 'example.zip'
            files = {'example.php': b'<?php /* Plugin Name: Example\nVersion: 1.0.0 */'}
            with zipfile.ZipFile(source, 'w') as archive:
                archive.writestr('example/example.php', files['example.php'])
            lock = {'plugins': [{'slug': 'example', 'version': '1.0.0', 'package': 'example.zip',
                                 'main_file': 'example/example.php', 'source': {'type': 'git', 'commit': 'a'*40},
                                 'sha256': common.file_hash(source), 'size_bytes': source.stat().st_size}]}
            source_row = {'slug': 'example', 'repository': 'MRN', 'relative_source': 'example'}
            def read(path): return lock if Path(path).name == 'bootstrap-packages.lock.json' else {'releases': []}
            with patch.object(build, 'read', side_effect=read), patch.object(build, 'roster', return_value=[source_row]), \
                 patch.object(build, 'git_files', return_value=files), \
                 patch.object(build.lock_tool, 'read_header_version', return_value='1.0.0'):
                result = build.default_packages(repo, root, {'held_defaults': {}}, packages, root / 'out')
            self.assertEqual(result, lock)
            self.assertEqual((root / 'out/example.zip').read_bytes(), source.read_bytes())

    def test_changed_same_version_package_is_rejected(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary); inputs = root / 'inputs'; inputs.mkdir()
            package = inputs / 'example.zip'
            with zipfile.ZipFile(package, 'w') as archive: archive.writestr('example/example.php', b'old')
            lock = {'plugins': [{'slug':'example','version':'1.0.0','package':'example.zip',
                 'main_file':'example/example.php','source':{'type':'git'},
                 'sha256':common.file_hash(package),'size_bytes':package.stat().st_size}]}
            def read(path): return lock if Path(path).name == 'bootstrap-packages.lock.json' else {'releases': []}
            with patch.object(build, 'read', side_effect=read), \
                 patch.object(build, 'roster', return_value=[{'slug':'example','repository':'MRN','relative_source':'example'}]), \
                 patch.object(build, 'git_files', return_value={'example.php':b'new'}), \
                 patch.object(build.lock_tool, 'read_header_version', return_value='1.0.0'):
                with self.assertRaises(common.ReleaseError):
                    build.default_packages(root, root, {'held_defaults':{}}, inputs, root/'out')



class CoordinatorBoundaries(unittest.TestCase):
    def test_remote_default_ref_is_resolved_and_mrn_main_is_enforced(self):
        with patch.object(coordinator, 'git_network', return_value='ref: refs/heads/main\tHEAD\n'+'a'*40+'\tHEAD'):
            self.assertEqual(coordinator.remote_refs(['MRN']), {'MRN':'a'*40})
        with patch.object(coordinator, 'git_network', return_value='ref: refs/heads/feature\tHEAD\n'+'a'*40+'\tHEAD'):
            with self.assertRaises(common.ReleaseError): coordinator.remote_refs(['MRN'])

    def test_normal_pr_gates_cannot_be_skipped_or_bypassed(self):
        payload={'state':'OPEN','headRefOid':'a'*40,'statusCheckRollup':[
                 {'name':'Code gate','status':'COMPLETED','conclusion':'FAILURE'}]}
        with patch.object(coordinator,'run',return_value=json.dumps(payload)) as run, \
             patch.object(coordinator,'assert_refs'):
            with self.assertRaises(common.ReleaseError): coordinator.accept_proposal(Path('.'), 1, {})
            self.assertEqual(run.call_count,1)
        payload['statusCheckRollup'][0]['name']='Unrelated gate';payload['statusCheckRollup'][0]['conclusion']='SUCCESS'
        with patch.object(coordinator,'run',return_value=json.dumps(payload)) as run, \
             patch.object(coordinator,'assert_refs'):
            with self.assertRaises(common.ReleaseError): coordinator.accept_proposal(Path('.'),1,{})
            self.assertEqual(run.call_count,1)

    def test_completed_rehearsal_resumes_without_rebuilding_or_publishing(self):
        with tempfile.TemporaryDirectory() as temporary:
            state=Path(temporary);state.chmod(0o700)
            job=state/'jobs/example';job.mkdir(parents=True)
            repo=job/'repos/MRN';(repo/'stack/manifests').mkdir(parents=True)
            bootstrap=job/'bootstrap';bootstrap.mkdir()
            for path in (job/'fleet.zip',job/'bootstrap.tar',bootstrap/'bootstrap-bundle.json',repo/'stack/manifests/stack-release.lock.json'):
                path.write_bytes(b'fixture')
            checksum=common.file_hash(job/'fleet.zip')
            progress={'phase':'qualified','repo':str(repo),'standalone':str(job/'repos/MRN-plugins'),
                      'metadata':{'snapshot':{'refs':{}},'release_id':'fixture'},
                      'built':{'fleet':str(job/'fleet.zip'),'bootstrap_archive':str(job/'bootstrap.tar'),
                               'bootstrap':str(bootstrap),'proof':{'lock_sha256':checksum}},
                      'qualified':{'status':'pass','fleet_sha256':checksum,'bootstrap_sha256':checksum,'lock_sha256':checksum}}
            coordinator.seal(job,progress);common.write(state/'active.json',{'job':str(job)})
            with patch.object(coordinator,'publish') as publish, patch.object(coordinator,'build_all') as build:
                result=coordinator.once({'state_root':str(state),'qualification':{},'publish':False})
                self.assertEqual(result['status'],'qualified_rehearsal');publish.assert_not_called();build.assert_not_called()
            (job/'fleet.zip').write_bytes(b'changed')
            with self.assertRaises(common.ReleaseError): coordinator.once({'state_root':str(state),'qualification':{},'publish':True})

    def test_launchagent_runs_only_the_source_launcher(self):
        import importlib.util
        spec=importlib.util.spec_from_file_location('fleet_install',Path(__file__).resolve().parents[1]/'install.py')
        install=importlib.util.module_from_spec(spec);spec.loader.exec_module(install)
        value=install.definition('/python','/launcher','/config',Path('/state'),'/bin')
        self.assertEqual(value['StartInterval'],300)
        self.assertEqual(value['ProgramArguments'],['/python','/launcher','/config'])
        self.assertNotIn('site',json.dumps(value))


if __name__ == '__main__': unittest.main()
