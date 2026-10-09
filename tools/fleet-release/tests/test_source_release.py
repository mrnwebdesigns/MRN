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
        optional = self.job / 'optional-plugins.tar'
        optional.write_bytes(b'private optional fixture')
        self.proof = {'status': 'pass', 'release_id': self.release,
                      'lock_sha256': self.new['release_lock_sha256'],
                      'bootstrap_sha256': self.archive, 'fleet_sha256': host.sha(fleet),
                      'optional_sha256': host.sha(optional),
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

    def test_existing_qualified_source_metadata_is_allowed_but_credentials_are_not(self):
        self.assertEqual(str(host.allowed('mu-plugins/component/.mrn-qa.env')),
                         'mu-plugins/component/.mrn-qa.env')
        self.assertEqual(str(host.allowed('themes/mrn-base-stack/.stylelintrc.json')),
                         'themes/mrn-base-stack/.stylelintrc.json')
        for name in ('mu-plugins/component/.env','themes/mrn-base-stack/.git/config',
                     'shared/.npmrc','secrets/settings.json'):
            with self.assertRaises(host.PublicationError): host.allowed(name)

    def test_private_transfer_cannot_rebind_immutable_destination(self):
        filename = 'source.tar'; target = self.job / filename; target.write_bytes(b'old')
        checksum = hashlib.sha256(b'new').hexdigest()
        (self.job / (filename + '.incoming-' + checksum)).write_bytes(b'new')
        with self.assertRaises(host.PublicationError): self.store.accept_artifact(self.release, filename, checksum)
        self.assertEqual(target.read_bytes(), b'old')


class QualificationContracts(unittest.TestCase):
    def test_qualification_runs_in_its_own_project_directory(self):
        with tempfile.TemporaryDirectory() as temporary:
            root=Path(temporary)
            qualify.execute([sys.executable,'-c',"from pathlib import Path; Path('marker').write_text('owned')"],
                            root/'check.log',cwd=root)
            self.assertEqual((root/'marker').read_text(),'owned')

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

    def test_duplicate_plugin_archive_members_are_rejected(self):
        import warnings
        with tempfile.TemporaryDirectory() as temporary:
            path=Path(temporary)/'duplicate.zip'
            with warnings.catch_warnings(record=True) as observed:
                with zipfile.ZipFile(path,'w') as archive:
                    archive.writestr('example/plugin.php',b'first')
                    archive.writestr('example/plugin.php',b'second')
                self.assertTrue(observed)
            with self.assertRaises(common.ReleaseError): build.package_files(path,'example')

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
    def test_same_source_with_different_zip_envelopes_preserves_registered_optional_bytes(self):
        with tempfile.TemporaryDirectory() as temporary:
            root=Path(temporary);repo=root/'repo';job=root/'job';manifests=repo/'stack/manifests'
            manifests.mkdir(parents=True);(job/'packages').mkdir(parents=True)
            files={'mrn-example.php':b'<?php /* Plugin Name: Fixture\nVersion: 1.0.0 */'}
            legacy=root/'legacy.zip';current=job/'packages/default.zip'
            with zipfile.ZipFile(legacy,'w',compression=zipfile.ZIP_DEFLATED) as archive:
                archive.writestr('mrn-example/mrn-example.php',files['mrn-example.php'])
            build.fleet.deterministic_zip(current,{'mrn-example/'+k:v for k,v in files.items()})
            self.assertNotEqual(common.file_hash(current),common.file_hash(legacy))
            entry={'slug':'mrn-example','version':'1.0.0','runtime_type':'standard-plugin',
                   'target_tier':'optional-shared','current_distribution':'standard-bootstrap',
                   'source':{'path':'plugins/mrn-example'}}
            common.write(manifests/'component-catalog.json',{'catalog_updated':'2026-10-09','components':[entry]})
            common.write(manifests/'stack-plugin-releases.json',{'releases':[]})
            common.write(manifests/'optional-plugin-releases.json',{'releases':[
                {'slug':'mrn-example','version':'1.0.0','package':{'path':str(legacy),'sha256':common.file_hash(legacy)}}]})
            common.write(manifests/'bootstrap-packages.lock.json',{'plugins':[
                {'slug':'mrn-example','version':'1.0.0','source':{'type':'git'},'package':'default.zip'}]})
            with patch.object(coordinator,'roster',return_value=[{**entry,'repository':'MRN'}]), \
                 patch.object(coordinator,'git',return_value='a'*40),patch.object(coordinator,'git_files',return_value=files):
                coordinator.prepare_registry(repo,root,job)
            record=common.read(manifests/'stack-plugin-releases.json')['releases'][0]
            self.assertEqual((repo/record['package']['path']).read_bytes(),legacy.read_bytes())
            self.assertEqual(build.package_files(current,'mrn-example'),files)

    def test_nonbootstrap_source_is_packaged_registered_and_cannot_rebind_a_version(self):
        with tempfile.TemporaryDirectory() as temporary:
            repo=Path(temporary)/'repo';job=Path(temporary)/'job'
            manifests=repo/'stack/manifests';manifests.mkdir(parents=True);job.mkdir()
            common.write(manifests/'stack-plugin-releases.json',{'releases':[]})
            common.write(manifests/'optional-plugin-releases.json',{'releases':[]})
            common.write(manifests/'bootstrap-packages.lock.json',{'plugins':[]})
            entry={'slug':'mrn-example','version':'1.0.0','runtime_type':'standard-plugin',
                   'target_tier':'optional-shared','current_distribution':'catalog-only',
                   'source':{'path':'plugins/mrn-example'}}
            common.write(manifests/'component-catalog.json',{'catalog_updated':'2026-10-09','components':[entry]})
            row={**entry,'repository':'MRN','relative_source':'plugins/mrn-example'}
            files={'mrn-example.php':b'<?php /* Plugin Name: Fixture\nVersion: 1.0.0 */'}
            with patch.object(coordinator,'roster',return_value=[row]), \
                 patch.object(coordinator,'git',return_value='a'*40), \
                 patch.object(coordinator,'git_files',return_value=files):
                coordinator.prepare_registry(repo,Path(temporary),job)
                registry=common.read(manifests/'stack-plugin-releases.json')
                record=registry['releases'][0]
                self.assertEqual(record['current_distribution'],'catalog-only')
                self.assertEqual(build.package_files(repo/record['package']['path'],'mrn-example'),files)
                self.assertEqual(common.read(manifests/'optional-plugin-releases.json')['releases'],[record])
                # Re-run on the same bytes preserves the immutable envelope.
                coordinator.prepare_registry(repo,Path(temporary),job,repo)
                self.assertEqual(common.read(manifests/'stack-plugin-releases.json'),registry)
                files['mrn-example.php']+=b' changed'
                with self.assertRaises(common.ReleaseError):coordinator.prepare_registry(repo,Path(temporary),job,repo)

    def test_missing_optional_distribution_cannot_be_published(self):
        # A matching Fleet/default bundle cannot substitute for omitted
        # independently released plugin bytes.
        with tempfile.TemporaryDirectory() as temporary:
            first=Path(temporary)/'a';second=Path(temporary)/'b'
            first.write_bytes(b'same');second.write_bytes(b'different')
            with self.assertRaises(common.ReleaseError):
                coordinator.deterministic({'fleet':first,'bootstrap_archive':first,'optional_archive':first},
                    {'fleet':first,'bootstrap_archive':first,'optional_archive':second})

    def test_superseded_worker_proposal_is_closed_but_foreign_proposal_is_preserved(self):
        for own in (True,False):
            with tempfile.TemporaryDirectory() as temporary:
                state=Path(temporary);job=state/'jobs/test';job.mkdir(parents=True)
                common.write(state/'active.json',{'job':str(job)})
                progress={'phase':'proposed','repo':'fixture','pr':123,
                          'metadata':{'snapshot':{'source_vector_sha256':'a'*64}}}
                common.write(job/'progress.json',progress)
                proposal={'state':'OPEN','headRefOid':('b'*40 if own else 'c'*40),
                          'body':'MRN_FLEET_SOURCE_VECTOR='+'a'*64}
                with patch.object(coordinator,'verify_seal'),patch.object(coordinator,'git',return_value='b'*40), \
                     patch.object(coordinator,'run',return_value=json.dumps(proposal)) as calls:
                    if own:
                        coordinator.failure({'state_root':str(state),'report_failures':False},
                            common.ReleaseError('superseded: newer accepted source'))
                        self.assertFalse((state/'active.json').exists())
                        self.assertEqual(calls.call_args_list[-1].args[0][:3],['gh','pr','close'])
                        self.assertTrue((job/'superseded.json').is_file())
                    else:
                        with self.assertRaises(common.ReleaseError):
                            coordinator.failure({'state_root':str(state),'report_failures':False},
                                common.ReleaseError('superseded: newer accepted source'))
                        self.assertTrue((state/'active.json').exists());self.assertEqual(calls.call_count,1)

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
            for path in (job/'fleet.zip',job/'bootstrap.tar',job/'optional.tar',bootstrap/'bootstrap-bundle.json',repo/'stack/manifests/stack-release.lock.json'):
                path.write_bytes(b'fixture')
            checksum=common.file_hash(job/'fleet.zip')
            progress={'phase':'qualified','repo':str(repo),'standalone':str(job/'repos/MRN-plugins'),
                      'metadata':{'snapshot':{'refs':{}},'release_id':'fixture'},
                      'built':{'fleet':str(job/'fleet.zip'),'bootstrap_archive':str(job/'bootstrap.tar'),
                               'optional_archive':str(job/'optional.tar'),
                               'bootstrap':str(bootstrap),'proof':{'lock_sha256':checksum}},
                      'qualified':{'status':'pass','fleet_sha256':checksum,'bootstrap_sha256':checksum,
                                   'optional_sha256':checksum,'lock_sha256':checksum}}
            coordinator.seal(job,progress);common.write(state/'active.json',{'job':str(job)})
            with patch.object(coordinator,'publish') as publish, patch.object(coordinator,'build_all') as build:
                result=coordinator.once({'state_root':str(state),'qualification':{},'publish':False})
                self.assertEqual(result['status'],'qualified_rehearsal');publish.assert_not_called();build.assert_not_called()
            (job/'fleet.zip').write_bytes(b'changed')
            with self.assertRaises(common.ReleaseError): coordinator.once({'state_root':str(state),'qualification':{},'publish':True})

    def test_known_mirror_links_are_local_state_without_hiding_source_changes(self):
        with tempfile.TemporaryDirectory() as temporary:
            repo=Path(temporary)/'repo';repo.mkdir()
            subprocess=__import__('subprocess')
            subprocess.run(['git','init','-q',str(repo)],check=True)
            standalone=Path(temporary)/'plugins';(standalone/'example').mkdir(parents=True)
            rows=[{'repository':'example','slug':'example','relative_source':'.'}]
            with patch.object(coordinator,'roster',return_value=rows):
                coordinator.link_mirrors(repo,standalone)
            self.assertEqual(common.git(repo,'status','--porcelain'),'')
            (repo/'unrelated.txt').write_text('preserve')
            self.assertIn('unrelated.txt',common.git(repo,'status','--porcelain'))
            with patch.object(coordinator,'roster',return_value=rows):
                coordinator.link_mirrors(repo,standalone)
            self.assertEqual((repo/'unrelated.txt').read_text(),'preserve')

    def test_service_does_not_inherit_app_credentials_or_site_overrides(self):
        import importlib.util
        spec=importlib.util.spec_from_file_location('fleet_launcher',Path(__file__).resolve().parents[1]/'launcher.py')
        launcher=importlib.util.module_from_spec(spec);spec.loader.exec_module(launcher)
        env=launcher.service_environment({'HOME':'/owner','PATH':'/bin','CONNECTOR_KEY':'fixture',
                 'MRN_QA_SITE_URL':'https://example.invalid','GH_TOKEN':'fixture'})
        self.assertEqual(env,{'HOME':'/owner','PATH':'/bin'})

    def test_launchagent_runs_only_the_source_launcher(self):
        import importlib.util
        spec=importlib.util.spec_from_file_location('fleet_install',Path(__file__).resolve().parents[1]/'install.py')
        install=importlib.util.module_from_spec(spec);spec.loader.exec_module(install)
        value=install.definition('/python','/launcher','/config',Path('/state'),'/bin')
        self.assertEqual(value['StartInterval'],300)
        self.assertEqual(value['ProgramArguments'],['/python','/launcher','/config'])
        self.assertNotIn('site',json.dumps(value))


if __name__ == '__main__': unittest.main()
