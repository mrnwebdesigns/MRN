import copy
import hashlib
import json
from pathlib import Path
import sys
import subprocess
import unittest
from unittest.mock import Mock, patch

sys.path.insert(0, str(Path(__file__).parents[1]))
from atomic_store import Store, inventory
from deploy import digest
import promoted_store as promoted
import host_controller as host
import test_atomic_store as fixture


class PromotedLiveStore(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        fixture.AtomicStoreContract.setUpClass()
        release = fixture.release_fixture.ReleaseArtifactContract
        source = release.root/'repo'
        (source/'app.js').write_text('window.releaseFixture = 2;\n')
        subprocess.run(['git','-C',str(source),'add','app.js'],check=True)
        subprocess.run(['git','-C',str(source),'-c','user.name=Fixture','-c','user.email=fixture@example.invalid',
                        '-c','commit.gpgsign=false','commit','-qm','second immutable release'],check=True)
        cls.next_sha=subprocess.check_output(['git','-C',str(source),'rev-parse','HEAD'],text=True).strip()
        cls.next_archive=release.root/'next-release.tar'
        subprocess.run([sys.executable,str(fixture.TOOLS/'build_release.py'),'--sha',cls.next_sha,
                        '--source','.','--slug','child','--output',str(cls.next_archive)],cwd=source,check=True,capture_output=True)
        cls.next_hash=hashlib.sha256(cls.next_archive.read_bytes()).hexdigest()

    @classmethod
    def tearDownClass(cls):
        fixture.AtomicStoreContract.tearDownClass()

    def setUp(self):
        self.f = fixture.AtomicStoreContract()
        self.f.setUp()
        parent = self.f.root / 'deployments/mrn-site-deploy'
        self.source_path = parent / 'staging'
        self.source_path.mkdir(mode=0o700, parents=True)
        self.live_path = parent / 'live'
        self.source = Store(self.source_path, self.f.theme, self.f.content, 'child')
        with self.source.locked_store():
            old = self.source.adopt(digest(self.f.before), self.f.bootstrap)
            release = fixture.release_fixture.ReleaseArtifactContract
            current = self.source.stage(release.archive, release.expected, release.sha, '.')
            self.pointer = self.source.select(current['release_id'], old)
        self.plan = dict(environment='live', host_provider='kinsta', backup_provider='kinsta',
                         repository='mrn/site', root=str(self.f.content.parent), template='parent',
                         slug='child', state_dir=str(self.live_path), url='https://example.org',
                         exercise_rollback=True, expected_current=None,
                         archive=str(self.next_archive), artifact_sha256=self.next_hash,
                         source_sha=self.next_sha, source_path='.', pages=['https://example.org/'],
                         ssh_host='host', ssh_port='22', ssh_user='owner', native_backup_receipt={})
        self.promotion = dict(schema=1, source_url='https://stg-example.kinsta.cloud',
                              source_environment_id='c'*36, source_sha=release.sha,
                              pointer=self.pointer, public_tree=digest(inventory(self.f.theme)))
        self.plan['promote_kinsta_staging'] = self.promotion
        self.plan['promoted_source_archive'] = str(release.archive)
        (self.source_path / 'site.json').write_text(json.dumps(promoted.source_binding(self.plan, self.promotion)))
        self.original_files = inventory(self.f.theme)
        self.original_store = inventory(self.source_path)
        self.binding = {k:self.plan[k] for k in ('repository', 'environment', 'url', 'root', 'template', 'slug')}
        self.live_path.mkdir(mode=0o700)
        (self.live_path / 'jobs').mkdir(mode=0o700)
        self.store = Store(self.live_path, self.f.theme, self.f.content, 'child')
        self.guard = Mock(return_value={'valid':True})

    def tearDown(self):
        self.f.tearDown()

    def run_import(self):
        return promoted.import_store(self.plan, self.store, self.binding, self.guard, self.f.bootstrap)

    def test_import_rebinds_only_the_loader_and_preserves_original_store(self):
        with self.store.locked_store():
            result = self.run_import()
            self.store.verify_public_snapshot()
        self.assertEqual(self.pointer, self.store.pointer())
        self.assertEqual(self.original_store, inventory(self.source_path))
        changed = [k for k,v in inventory(self.f.theme).items() if self.original_files.get(k) != v]
        self.assertEqual(['functions.php'], changed)
        self.assertEqual(self.binding, json.loads((self.live_path/'site.json').read_text()))
        self.assertEqual(self.pointer['release_id'], result['current']['release_id'])
        self.assertEqual(['import-promoted-live-store', 'rebind-promoted-live-loader'],
                         [call.args[0] for call in self.guard.call_args_list])

    def test_recovery_restores_exact_original_loader_without_erasing_retained_code(self):
        with self.store.locked_store():
            self.run_import()
            result = promoted.restore_import(self.plan, self.store, self.guard)
        self.assertEqual('original-promoted-store-restored', result['status'])
        self.assertEqual(self.original_files, inventory(self.f.theme))
        self.assertEqual(self.original_store, inventory(self.source_path))
        self.assertIsNone(self.store.pointer())
        self.assertTrue((self.live_path/'releases'/self.pointer['release_id']).is_dir())
        self.source.verify_public_snapshot()

    def test_missing_backup_does_not_rebind_loader_or_change_source(self):
        self.guard.side_effect = ValueError('backup unavailable')
        with self.store.locked_store(), self.assertRaisesRegex(ValueError, 'backup unavailable'):
            self.run_import()
        self.assertEqual(self.original_files, inventory(self.f.theme))
        self.assertEqual(self.original_store, inventory(self.source_path))
        self.assertFalse((self.live_path/'promotion.json').exists())

    def test_final_guard_failure_is_recoverable_before_loader_switch(self):
        self.guard.side_effect = [{'valid':True}, ValueError('snapshot vanished'), {'valid':True}]
        with self.store.locked_store():
            with self.assertRaisesRegex(ValueError, 'snapshot vanished'):
                self.run_import()
            promoted.restore_import(self.plan, self.store, self.guard)
        self.assertEqual(self.original_files, inventory(self.f.theme))
        self.assertIsNone(self.store.pointer())

    def test_existing_live_binding_cannot_be_overwritten(self):
        (self.live_path/'site.json').write_text('{}')
        with self.store.locked_store(), self.assertRaisesRegex(ValueError, 'fresh destination'):
            self.run_import()
        self.guard.assert_not_called()
        self.assertEqual(self.original_files, inventory(self.f.theme))

    def test_reviewed_ordered_dev_copy_is_preserved_through_import_and_recovery(self):
        # The literal Doster identity/artifact allowlist has separate negative
        # tests. Exercise the common filesystem/rollback contract in a temp root.
        from atomic_store import durable_replace
        dev_path=self.source_path.with_name('dev')
        self.source_path.rename(dev_path)
        self.source_path=dev_path
        self.source=Store(dev_path,self.f.theme,self.f.content,'child')
        self.promotion.update(schema=2,doster_cutover='doster-live-20261020')
        with patch.object(promoted,'doster_cutover',return_value=True):
            adoption=json.loads((dev_path/'adoption.json').read_text())
            loader=promoted.bootstrap(self.f.bootstrap,self.source)
            durable_replace(self.f.theme/'functions.php',loader,0o644)
            adoption['bootstrap_sha256']=hashlib.sha256(loader).hexdigest()
            (dev_path/'adoption.json').write_text(json.dumps(adoption))
            (dev_path/'site.json').write_text(json.dumps(promoted.source_binding(self.plan,self.promotion)))
            (dev_path/'deployment-order.json').write_text(json.dumps(promoted.DOSTER_CUTOVER_ORDER))
            self.promotion['public_tree']=digest(inventory(self.f.theme))
            before_public=inventory(self.f.theme);before_source=inventory(dev_path)
            with self.store.locked_store():
                self.run_import()
                self.store.verify_public_snapshot()
                promoted.restore_import(self.plan,self.store,self.guard)
            self.assertEqual(before_public,inventory(self.f.theme))
            self.assertEqual(before_source,inventory(dev_path))
            self.assertIsNone(self.store.pointer())

    def test_changed_source_binding_is_rejected_before_backup_guard(self):
        (self.source_path/'site.json').write_text('{}')
        with self.store.locked_store(), self.assertRaisesRegex(ValueError, 'binding, order or pointer'):
            self.run_import()
        self.guard.assert_not_called()

    def test_ordered_staging_history_cannot_be_repurposed(self):
        (self.source_path/'deployment-order.json').write_text('{}')
        with self.store.locked_store(), self.assertRaisesRegex(ValueError, 'binding, order or pointer'):
            self.run_import()
        self.guard.assert_not_called()

    def test_changed_private_code_or_public_assets_are_rejected(self):
        path=self.source.releases/self.pointer['release_id']/'theme/functions.php'
        path.write_text('<?php /* drift */')
        with self.store.locked_store(), self.assertRaisesRegex(ValueError, 'Stored release drift'):
            self.run_import()
        self.guard.assert_not_called()

    def test_public_asset_drift_blocks_import_before_guard_or_loader_write(self):
        (self.f.content/self.pointer['public_path']/'style.min.css').write_text('changed')
        with self.store.locked_store(),self.assertRaisesRegex(ValueError,'public assets differ'):
            self.run_import()
        self.guard.assert_not_called()
        self.assertEqual(self.original_files,inventory(self.f.theme))

    def test_mutable_private_metadata_cannot_override_the_original_artifact(self):
        release=self.source.releases/self.pointer['release_id']
        code=release/'theme/functions.php';code.write_text('<?php /* unexplained replacement */')
        metadata=json.loads((release/'installed.json').read_text())
        metadata['theme_files']['functions.php']=hashlib.sha256(code.read_bytes()).hexdigest()
        (release/'installed.json').write_text(json.dumps(metadata))
        with self.store.locked_store(),self.assertRaisesRegex(ValueError,'reviewed artifact'):
            self.run_import()
        self.guard.assert_not_called()

    def test_changed_public_loader_is_not_trusted_even_with_updated_snapshot(self):
        original=(self.f.theme/'functions.php').read_bytes()
        (self.f.theme/'functions.php').write_bytes(original+b'\n/* unexplained */')
        record=json.loads((self.source_path/'adoption.json').read_text())
        record['bootstrap_sha256']=hashlib.sha256((self.f.theme/'functions.php').read_bytes()).hexdigest()
        (self.source_path/'adoption.json').write_text(json.dumps(record))
        self.promotion['public_tree']=digest(inventory(self.f.theme))
        with self.store.locked_store(), self.assertRaisesRegex(ValueError, 'canonical staging bootstrap'):
            self.run_import()
        self.guard.assert_not_called()

    def test_known_historical_absolute_bootstrap_is_preserved_for_recovery(self):
        code=promoted.recognized_bootstraps(self.f.bootstrap,self.source)[1]
        (self.f.theme/'functions.php').write_bytes(code)
        record=json.loads((self.source_path/'adoption.json').read_text())
        record['bootstrap_sha256']=hashlib.sha256(code).hexdigest()
        (self.source_path/'adoption.json').write_text(json.dumps(record))
        self.promotion['public_tree']=digest(inventory(self.f.theme))
        with self.store.locked_store():
            self.run_import()
            promoted.restore_import(self.plan,self.store,self.guard)
        self.assertEqual(code,(self.f.theme/'functions.php').read_bytes())

    def test_recovery_does_not_overwrite_unexplained_public_changes(self):
        with self.store.locked_store():
            self.run_import()
            (self.f.theme/'functions.php').write_text('<?php /* someone else */')
            with self.assertRaisesRegex(ValueError,'Public drift'):
                promoted.restore_import(self.plan,self.store,self.guard)
        self.assertEqual('<?php /* someone else */',(self.f.theme/'functions.php').read_text())

    def test_host_controller_failure_restores_promoted_source(self):
        adapter=Mock()
        adapter.verify_transaction_backup.return_value={'valid':True}
        with patch.object(host,'identity',return_value=({'content':str(self.f.content),'environment_type':'production'},self.binding,{})), \
                patch.object(promoted,'native_source'), patch.object(host,'html_cache',return_value={}), \
                patch.object(host,'http_check'), patch.object(host,'public_check',side_effect=ValueError('native verification failed')):
            receipt=host.execute(self.plan,adapter)
        self.assertEqual('failed',receipt['status'])
        self.assertEqual('original-promoted-store-restored',receipt['promotion_recovery']['status'])
        self.assertEqual(self.original_files,inventory(self.f.theme))
        self.assertIsNone(self.store.pointer())
        self.assertEqual(self.original_store,inventory(self.source_path))

    def test_host_controller_exercises_native_activation_rollback_and_reactivation(self):
        self.plan.update(archive=str(self.next_archive),artifact_sha256=self.next_hash,source_sha=self.next_sha)
        adapter=Mock()
        adapter.verify_transaction_backup.return_value={'valid':True}
        observed=[]
        def public(plan,store,pointer):
            store.verify_public_snapshot();observed.append(pointer);return {'release_id':pointer['release_id']}
        with patch.object(host,'identity',return_value=({'content':str(self.f.content),'environment_type':'production'},self.binding,{})), \
                patch.object(promoted,'native_source'), patch.object(host,'public_check',side_effect=public):
            receipt=host.execute(self.plan,adapter)
        self.assertEqual('public-verified',receipt['status'])
        self.assertEqual(4,len(observed))
        self.assertEqual([self.pointer['release_id'],self.next_hash,self.pointer['release_id'],self.next_hash],
                         [p['release_id'] for p in observed])
        self.assertEqual(['import-promoted-live-store','rebind-promoted-live-loader','stage','activate','rollback','reactivate'],
                         [c.args[1] for c in adapter.verify_transaction_backup.call_args_list])
        self.assertEqual(self.original_store,inventory(self.source_path))

    def test_preflight_checks_native_source_and_production_without_writes(self):
        target=Mock()
        target.native_backup.site_id='b'*36;target.native_backup.environment_id='a'*36
        environment=dict(id='c'*36,name='staging',primaryDomain=dict(name='stg-example.kinsta.cloud',type='staging'))
        target.native_backup.api.return_value={'site':{'environments':[environment]}}
        result=dict(environment='production',source_physical=True,source_private=True,source_no_aliases=True,destination_exists=False,
                    pointer=self.pointer,binding=promoted.source_binding(self.plan,self.promotion),
                    metadata=self.source.release(self.pointer['release_id']))
        target.php.return_value=result
        before=dict(files=self.original_files,state=None,target_binding_exists=False,deployment_order=None)
        self.assertEqual('promoted-live-import-preflight-verified',promoted.preflight(target,self.plan,self.promotion,before)['status'])
        for changes in [{'environment':'staging'},{'source_private':False},{'source_physical':False},{'source_no_aliases':False},
                        {'destination_exists':True},{'pointer':{}},{'binding':{}},{'metadata':{'source_sha':'a'*40}}]:
            target.php.return_value=dict(result,**changes)
            with self.subTest(changes=changes),self.assertRaisesRegex(ValueError,'preflight differs'):
                promoted.preflight(target,self.plan,self.promotion,before)
        self.assertEqual(self.original_files,inventory(self.f.theme))

    def test_mode_and_manifest_boundaries_reject_automatic_or_cross_provider_imports(self):
        for changes in [{'environment':'staging'},{'host_provider':'cloudpanel'},{'backup_provider':'updraft'},
                        {'deployment_order':{'event':'push'}},{'adopt':True},{'rollback_to':'a'*64}]:
            with self.subTest(changes=changes),self.assertRaises(ValueError):
                promoted.validate(dict(self.plan,**changes),self.promotion)
        for changes in [{'source_url':'https://example.org'}, {'source_url':'https://owner:secret@example.org'},
                        {'source_url':'https://stg-example.kinsta.cloud/'},{'public_tree':'bad'}]:
            with self.subTest(changes=changes),self.assertRaises(ValueError):
                promoted.validate(self.plan,dict(self.promotion,**changes))
        with self.assertRaisesRegex(ValueError,'distinct candidate'):
            promoted.validate(dict(self.plan,artifact_sha256=self.pointer['release_id']),self.promotion)

    def test_native_source_must_be_distinct_staging_environment_on_the_same_site(self):
        adapter=Mock(site_id='b'*36,environment_id='a'*36)
        environment=dict(id='c'*36,name='staging',primaryDomain=dict(name='stg-example.kinsta.cloud',type='staging'))
        adapter.api.return_value={'site':{'environments':[environment]}}
        promoted.native_source(adapter,self.promotion)
        for change in [{'id':'a'*36},{'name':'live'},{'primaryDomain':dict(name='elsewhere.example',type='staging')}]:
            adapter.api.return_value={'site':{'environments':[dict(environment,**change)]}}
            with self.assertRaisesRegex(ValueError,'sibling native'):
                promoted.native_source(adapter,self.promotion)
