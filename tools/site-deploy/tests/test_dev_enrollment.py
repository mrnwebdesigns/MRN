"""Exercise source capture, resumable enrollment and evidence rejection locally."""
import argparse
import base64
from copy import deepcopy
import hashlib
import json
import os
from pathlib import Path
import sys
import subprocess
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0,str(Path(__file__).parents[1]))
import enroll_dev as enrollment
from enrollment_github import GitHub
import enrollment_credentials

SHA='a'*40
PILOT='b'*40


def site(root):
    return {'root':str(root),'url':'https://example.mrndev.io','domain':'example.mrndev.io',
            'source_path':'public/wp-content/themes/example-child','slug':'example-child','template':'parent',
            'repository':'mrnwebdesigns/example-site','user':'example','state_dir':'/home/example/.mrn-site-deploy/dev'}


class FakeGitHub:
    def __init__(self):
        self.current='c'*40;self.calls=[];self.variables={};self.signals={};self.deployments=[]
        self.dispatch_count=0;self.proof_ready=False;self.proof_error=None;self.publish_timeout=False
    def ensure_repository(self,binding): self.calls.append('repository')
    def head(self): return self.current
    def make_commit(self,base,files,message):
        self.calls.append(('commit',base,set(files)))
        return SHA if base=='c'*40 else PILOT
    def publish(self,base,commit):
        if self.current not in (base,commit): raise ValueError('advanced')
        self.current=commit;self.calls.append(('publish',commit))
        if self.publish_timeout:
            self.publish_timeout=False;raise RuntimeError('unknown outcome')
    def configure(self,variables,secrets):
        self.calls.append('configure');self.variables.update(variables)
        assert variables['DEPLOY_READY']=='0'
    def variable(self,name,value,environment=None):
        self.calls.append(('variable',name,value,environment));self.variables[name]=value
    def dispatch(self,enrollment_id): self.dispatch_count+=1;self.calls.append('dispatch')
    def runs(self,workflow,sha=None):
        return self.signals.get(sha,[]) if workflow=='site-push.yml' else self.deployments
    def proof(self,run,sha,url,slug,**kwargs):
        if self.proof_error: raise ValueError(self.proof_error)
        if not self.proof_ready: return None
        return {'run_url':'https://github.com/mrnwebdesigns/example-site/actions/runs/99','source_sha':sha}
    def grant_access(self,teams,collaborators):
        self.calls.append(('access',teams,collaborators));return True


class EnrollmentFlow(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();self.root=Path(self.temp.name)
        self.site=site(self.root/'site');self.gh=FakeGitHub()
        self.state={'site':self.site,'stage':'queued','binding':'example-binding'}
        self.config={'tooling_ref':SHA,'ssh_host':'ssh.example.invalid','ssh_port':22,
                     'github_owner_type':'User','team_slugs':[],'collaborators':['mrn-developer-collab']}
        self.e=enrollment.Enrollment(self.config,self.root,self.state,self.gh,
            {'deploy_private_key':'private-fixture','known_hosts':'hostkey-fixture','qa_engine_token':'qa-fixture'})
        self.patches=[patch.object(enrollment,'snapshot',return_value=({'public/wp-content/themes/example-child/style.css':b'/*\nVersion: 1.1.0\n*/'},'d'*64)),
                      patch.object(enrollment,'source_qa'),patch.object(enrollment,'runtime_prepare',return_value={'valid':True}),
                      patch.object(enrollment,'timestamp',return_value='2026-10-06T10:00:05Z')]
        self.mocks=[p.start() for p in self.patches]
    def tearDown(self):
        for p in reversed(self.patches):p.stop()
        self.temp.cleanup()
    def advance(self,n):
        for _ in range(n):self.e.tick()
    def signal(self,sha):
        self.gh.signals[sha]=[{'event':'push','head_branch':'main','head_sha':sha,'status':'completed',
                              'conclusion':'success','id':15,'run_number':4,'created_at':'2026-10-06T10:00:00Z'}]
    def test_complete_flow_requires_both_qualification_and_real_push_proof(self):
        self.advance(4)
        self.assertEqual('wait-install-signal',self.state['stage'])
        self.assertEqual(0,self.gh.dispatch_count)
        self.assertNotIn('MRN_AUTO_DEV_AFTER',self.gh.variables)
        self.signal(SHA);self.e.tick()
        self.assertEqual(1,self.gh.dispatch_count)
        self.gh.deployments=[{'event':'workflow_dispatch','display_title':'MRN Dev qualification example-binding'}]
        self.advance(2)
        self.assertEqual('qualification-dispatched',self.state['stage'])
        self.assertEqual('0',self.gh.variables['DEPLOY_READY'])
        self.gh.proof_ready=True;self.e.tick();self.e.tick()
        self.assertEqual('create-pilot',self.state['stage'])
        self.assertEqual('1',self.gh.variables['DEPLOY_READY'])
        self.assertEqual('',self.gh.variables['DEPLOY_ENROLLMENT_SOURCE_SHA'])
        with patch.object(enrollment,'timestamp',return_value='2026-10-06T10:00:06Z'):self.advance(2)
        self.assertEqual('verify-push',self.state['stage'])
        self.assertEqual(PILOT,self.gh.current)
        self.signal(PILOT)
        self.gh.deployments=[{'event':'workflow_run','created_at':'2026-10-06T10:00:07Z'}]
        self.gh.proof_ready=False;self.e.tick();self.assertEqual('verify-push',self.state['stage'])
        self.gh.proof_ready=True;self.e.tick();self.e.tick()
        self.assertEqual('ready',self.state['stage'])
        self.assertEqual(PILOT,self.state['deployed_sha'])
        self.assertEqual(('access',[],['mrn-developer-collab']),self.gh.calls[-1])
        calls=deepcopy(self.gh.calls);self.e.tick();self.assertEqual(calls,self.gh.calls)
        self.assertEqual(1,self.gh.dispatch_count)
        files=json.loads((self.root/'source.json').read_text())
        wrapper=base64.b64decode(files['.github/workflows/site-deploy.yml']).decode()
        self.assertIn('live_enabled: false',wrapper)
        self.assertNotIn('live',json.dumps([v for v in self.gh.calls if isinstance(v,tuple) and v[0]=='variable']))
    def test_source_qa_failure_never_creates_or_publishes_repository(self):
        self.mocks[1].side_effect=ValueError('QA failed')
        with self.assertRaises(ValueError):self.e.tick()
        self.assertEqual('queued',self.state['stage']);self.assertEqual([],self.gh.calls)
    def test_backup_failure_never_configures_or_arms_deploy(self):
        self.advance(3);self.mocks[2].side_effect=ValueError('backup failed')
        with self.assertRaises(ValueError):self.e.tick()
        self.assertEqual('prepare-target',self.state['stage']);self.assertNotIn('configure',self.gh.calls)
    def test_unknown_publish_is_reconciled_without_new_commit(self):
        self.advance(2);self.gh.publish_timeout=True
        with self.assertRaises(RuntimeError):self.e.tick()
        self.assertEqual('publish-source',self.state['stage'])
        self.e.tick();self.assertEqual('prepare-target',self.state['stage'])
        self.assertEqual(1,len([x for x in self.gh.calls if isinstance(x,tuple) and x[0]=='commit']))
    def test_failed_or_unknown_dispatch_does_not_repeat(self):
        self.advance(4);self.signal(SHA)
        with patch.object(self.gh,'dispatch',side_effect=RuntimeError('timeout')):
            with self.assertRaises(RuntimeError):self.e.tick()
        self.assertEqual('qualification-dispatched',self.state['stage'])
        # A recorded run is polled, never dispatched again.
        self.gh.deployments=[{'event':'workflow_dispatch','display_title':'MRN Dev qualification example-binding'}]
        self.gh.proof_error='failed'
        with self.assertRaises(ValueError):self.e.tick()
        self.assertEqual(0,self.gh.dispatch_count)
        self.assertNotIn('MRN_AUTO_DEV_AFTER',self.gh.variables)
    def test_drift_before_enrollment_target_write_is_blocked(self):
        self.advance(3);self.gh.current='e'*40
        with self.assertRaises(ValueError):self.e.tick()
        self.mocks[2].assert_not_called()
    def test_pending_invitation_cannot_finish_handoff(self):
        self.state.update(stage='grant-access',pilot_sha=PILOT)
        self.gh.current=PILOT
        with patch.object(self.gh,'grant_access',return_value=False):self.e.tick()
        self.assertEqual('grant-access',self.state['stage']);self.assertTrue(self.state['access_pending'])
        self.e.tick()
        self.assertEqual('ready',self.state['stage']);self.assertFalse(self.state['access_pending'])


class EvidenceTests(unittest.TestCase):
    def setUp(self):
        self.gh=GitHub('fixture','mrnwebdesigns/example-site')
        self.run={'id':99,'status':'completed','conclusion':'success','html_url':'https://github.com/example/run'}
        self.receipt={'status':'public-verified','source_sha':SHA,'repository':self.gh.repository,
            'environment':'dev','url':'https://example.mrndev.io','slug':'example-child','current':{'release_id':'f'*64},
            'transfer_backup':{'valid':True},'steps':[{'operation':k,'backup':{'valid':True}} for k in ('adopt','stage','activate','rollback-test','reactivate')],
            'deployment_order':{'event':'workflow_run','source_run_number':4,'run_id':'99','source_branch':'main'}}
        self.browser=[{'viewport':v,'errors':[],'assets':[{'sha256':'a'*64}]} for v in ('desktop','tablet','mobile')]
        self.runtime={'outcome':'success','source_sha':SHA,'environment':'dev','url':'https://example.mrndev.io'}
        names=('Bind successful QA to this commit','Verify browser-loaded assets and responsive layout contracts','Run runtime QA')
        self.jobs={'jobs':[{'steps':[{'name':n,'conclusion':'success'} for n in names]}]}
        self.artifacts={'artifacts':[{'id':12,'name':'site-deployment-dev-'+SHA,'expired':False}]}
    def prove(self,qualification=False,prior_release=None):
        with patch.object(self.gh,'request',side_effect=[self.jobs,self.artifacts]),patch.object(self.gh,'evidence',return_value=(self.receipt,self.browser,self.runtime)):
            return self.gh.proof(self.run,SHA,'https://example.mrndev.io','example-child',qualification=qualification,signal_number=None if qualification else 4,prior_release=prior_release)
    def test_passes_exact_receipts(self):self.assertEqual(SHA,self.prove(True)['source_sha']);self.prove()
    def test_green_workflow_with_advisory_runtime_failure_is_not_ready(self):
        self.jobs['jobs'][0]['steps'][-1]['conclusion']='failure'
        with self.assertRaises(ValueError):self.prove()
    def test_green_steps_cannot_hide_failed_or_wrong_commit_runtime_result(self):
        original=deepcopy(self.runtime)
        for change in ({'outcome':'failure'}, {'source_sha':PILOT}, {'environment':'live'}, {'url':'https://elsewhere.mrndev.io'}):
            self.runtime={**original,**change}
            with self.subTest(change=change),self.assertRaises(ValueError):self.prove()
    def test_rejects_wrong_source_site_backup_and_order(self):
        original=deepcopy(self.receipt)
        for key,value in [('source_sha',PILOT),('environment','live'),('url','https://elsewhere.mrndev.io'),
                          ('transfer_backup',{'valid':False}),('deployment_order',{'event':'workflow_dispatch'})]:
            self.receipt={**deepcopy(original),key:value}
            with self.subTest(key=key),self.assertRaises(ValueError):self.prove()
    def test_qualification_requires_rollback_and_all_backups(self):
        self.receipt['steps']=[s for s in self.receipt['steps'] if s['operation']!='rollback-test']
        with self.assertRaises(ValueError):self.prove(True)
    def test_requalification_requires_authorized_prior_and_repeated_rollback(self):
        self.receipt['steps']=[s for s in self.receipt['steps'] if s['operation']!='adopt']
        self.receipt['previous']={'release_id':'e'*64}
        with self.assertRaises(ValueError):self.prove(True)
        self.prove(True,prior_release='e'*64)
        with self.assertRaises(ValueError):self.prove(True,prior_release='d'*64)
        self.receipt['steps']=[s for s in self.receipt['steps'] if s['operation']!='rollback-test']
        with self.assertRaises(ValueError):self.prove(True,prior_release='e'*64)
    def test_rejects_stale_or_incomplete_browser_assets(self):
        self.browser[0]['errors']=['wrong asset hash']
        with self.assertRaises(ValueError):self.prove()
    def test_foreign_existing_repository_is_never_modified(self):
        with patch.object(self.gh,'request',return_value={'full_name':self.gh.repository,'private':True,'description':'Unrelated project'}) as api:
            with self.assertRaises(ValueError):self.gh.ensure_repository('binding')
            self.assertEqual(1,api.call_count)
    def test_resumed_seed_with_unrelated_work_is_never_modified(self):
        repo={'full_name':self.gh.repository,'private':True,'description':'MRN Dev enrollment binding','default_branch':'main','owner':{'type':'User'}}
        tree={'tree':[{'path':'README.md','type':'blob'},{'path':'.github/workflows/unrelated.yml','type':'blob'}]}
        with patch.object(self.gh,'request',side_effect=[repo,{'object':{'sha':SHA}},{'tree':{'sha':PILOT}},tree]) as api:
            with self.assertRaises(ValueError):self.gh.ensure_repository('binding')
            self.assertTrue(all(call.args[0]=='GET' for call in api.call_args_list))
    def test_main_advancing_blocks_publish(self):
        with patch.object(self.gh,'head',return_value=PILOT),patch.object(self.gh,'request') as api:
            with self.assertRaises(ValueError):self.gh.publish('c'*40,SHA)
            api.assert_not_called()


class GitHubOwnerTests(unittest.TestCase):
    def test_creation_uses_verified_owner_type_and_exact_user_token_identity(self):
        for owner_type, endpoint in (('User','/user/repos'),('Organization','/orgs/mrnwebdesigns/repos')):
            gh=GitHub('fixture','mrnwebdesigns/example-site',owner_type)
            owner={'login':'mrnwebdesigns','type':owner_type}
            repo={'full_name':gh.repository,'private':True,'description':'MRN Dev enrollment binding',
                  'default_branch':'main','owner':owner}
            replies=[None,owner]+([owner] if owner_type=='User' else [])+[repo,{'object':{'sha':SHA}},
                {'tree':{'sha':PILOT}},{'tree':[{'path':'README.md','type':'blob'}]}]
            with self.subTest(owner_type=owner_type),patch.object(gh,'request',side_effect=replies) as api:
                gh.ensure_repository('binding')
                writes=[call.args for call in api.call_args_list if call.args[0]!='GET']
                self.assertEqual(1,len(writes));self.assertEqual(endpoint,writes[0][1])
                self.assertIs(writes[0][2]['private'],True)
    def test_wrong_type_or_token_owner_cannot_create_repository(self):
        gh=GitHub('fixture','mrnwebdesigns/example-site','User')
        for replies in ([None,{'login':'mrnwebdesigns','type':'Organization'}],
                        [None,{'login':'mrnwebdesigns','type':'User'},{'login':'some-other-user','type':'User'}]):
            with self.subTest(replies=replies),patch.object(gh,'request',side_effect=replies) as api:
                with self.assertRaises(ValueError):gh.ensure_repository('binding')
                self.assertTrue(all(call.args[0]=='GET' for call in api.call_args_list))
    def test_accepted_collaborator_is_not_reinvited(self):
        gh=GitHub('fixture','mrnwebdesigns/example-site')
        with patch.object(gh,'request',return_value={'permission':'write'}) as api:
            self.assertTrue(gh.grant_access([],['developer']))
            self.assertEqual(1,api.call_count);self.assertEqual('GET',api.call_args.args[0])
    def test_new_invitation_waits_and_retry_does_not_resend(self):
        gh=GitHub('fixture','mrnwebdesigns/example-site')
        invitation={'invitee':{'login':'developer'},'permissions':'write'}
        with patch.object(gh,'request',side_effect=[None,[],invitation,None]) as api:
            self.assertFalse(gh.grant_access([],['developer']))
            writes=[call.args for call in api.call_args_list if call.args[0]=='PUT']
            self.assertEqual([('PUT','/repos/mrnwebdesigns/example-site/collaborators/developer',{'permission':'push'})],writes)
        with patch.object(gh,'request',side_effect=[None,[invitation],None]) as api:
            self.assertFalse(gh.grant_access([],['developer']))
            self.assertTrue(all(call.args[0]=='GET' for call in api.call_args_list))
    def test_user_cannot_grant_teams_and_org_team_access_remains_supported(self):
        gh=GitHub('fixture','mrnwebdesigns/example-site')
        with patch.object(gh,'request') as api:
            with self.assertRaises(ValueError):gh.grant_access(['developers'],[])
            api.assert_not_called()
        gh=GitHub('fixture','mrnwebdesigns/example-site','Organization')
        with patch.object(gh,'request',side_effect=[None,{'permissions':{'push':True}}]) as api:
            self.assertTrue(gh.grant_access(['developers'],[]))
            self.assertEqual('/orgs/mrnwebdesigns/teams/developers/repos/mrnwebdesigns/example-site',api.call_args.args[1])
    def test_config_requires_appropriate_explicit_access(self):
        enrollment.validate_developer_access({'github_owner_type':'User','collaborators':['developer']})
        for config in ({'github_owner_type':'User','team_slugs':['developers']},
                       {'github_owner_type':'User','collaborators':[]},
                       {'github_owner_type':'User','collaborators':['developer','developer']},
                       {'github_owner_type':'User','collaborators':'developer'}):
            with self.subTest(config=config),self.assertRaises(ValueError):enrollment.validate_developer_access(config)


class SourceAndCredentialTests(unittest.TestCase):
    def test_generated_release_metadata_matches_child_version_and_preserves_authored_notes(self):
        s = site(Path('/site'))
        css = s['source_path'] + '/style.css'
        readme = s['source_path'] + '/readme.txt'
        files = {css:b'/*\nVersion: 1.2.3\n*/'}
        self.assertIn(b'Stable tag: 1.2.3\n', enrollment.release_metadata(s, files)[readme])
        self.assertNotIn(readme, files)
        authored = {**files, readme:b'Authored release notes'}
        self.assertEqual(authored, enrollment.release_metadata(s, authored))
        with self.assertRaisesRegex(ValueError, 'Version header'):
            enrollment.release_metadata(s, {css:b'/* no version */'})

    def test_generated_scaffold_keeps_its_wordpress_file_documentation_first(self):
        scaffold = Path(enrollment.__file__).parents[2] / 'stack/themes/mrn-base-stack-child'
        with tempfile.TemporaryDirectory() as temp:
            s = site(Path(temp).resolve())
            theme = Path(s['root']) / 'wp-content/themes/example-child'
            theme.mkdir(parents=True)
            for name in ('functions.php', 'style.css'):
                (theme / name).write_bytes((scaffold / name).read_bytes())
            files, baseline = enrollment.snapshot(s)
            output = theme / 'functions.php'
            generated = files[s['source_path'] + '/functions.php']
            output.write_bytes(generated)
            code = '''
foreach (token_get_all(file_get_contents($argv[1])) as $token) {
    if (is_array($token) && in_array($token[0], [T_OPEN_TAG, T_WHITESPACE], true)) { continue; }
    echo is_array($token) ? token_name($token[0]) . "\\n" . $token[1] : $token;
    break;
}
'''
            result = subprocess.run(['php', '-r', code, str(output)], capture_output=True, text=True, check=True)
            self.assertTrue(result.stdout.startswith('T_DOC_COMMENT\n'))
            self.assertIn('@package mrn-base-stack-child', result.stdout)
            # Retrying source capture must not duplicate the opt-in.
            repeated, _ = enrollment.snapshot(s)
            self.assertEqual(generated, repeated[s['source_path'] + '/functions.php'])

    def test_snapshot_excludes_runtime_data_and_retains_baseline_before_optin(self):
        with tempfile.TemporaryDirectory() as temp:
            temp=str(Path(temp).resolve());s=site(Path(temp));theme=Path(temp)/'wp-content/themes/example-child';theme.mkdir(parents=True)
            (theme/'style.css').write_text('/* CSS */');(theme/'functions.php').write_text('<?php\n// child\n')
            (theme/'.env').write_text('private');(theme/'docs').mkdir();(theme/'docs/private.txt').write_text('ignore')
            files,baseline=enrollment.snapshot(s)
            self.assertEqual(2,len(files));self.assertEqual(64,len(baseline))
            self.assertIn(b'mrn-release-assets.php',files[s['source_path']+'/functions.php'])
            self.assertNotIn('mrn-release-assets.php',(theme/'functions.php').read_text())
            (theme/'leak').symlink_to('/etc/passwd')
            with self.assertRaises(ValueError):enrollment.snapshot(s)
    def test_personal_credential_account_rejected_without_read(self):
        with patch.object(enrollment_credentials.subprocess,'run') as run:
            with self.assertRaises(ValueError):enrollment_credentials.resolve('/unused',{'account':'thehofmeyers'})
            run.assert_not_called()
    def test_pending_state_persists_across_restart(self):
        with tempfile.TemporaryDirectory() as temp:
            p=Path(temp)/'state.json';enrollment.save(p,{'stage':'publish-source','source_sha':SHA})
            self.assertEqual(SHA,json.loads(p.read_text())['source_sha'])
            self.assertEqual(0,p.stat().st_mode & 0o077)

    def test_backup_rejection_prevents_identity_and_filesystem_setup(self):
        with patch.object(enrollment,'wp',return_value={'valid':False}),patch.object(enrollment,'command') as command:
            with self.assertRaises(ValueError):enrollment.runtime_prepare({'root':'/unused'},{})
            command.assert_not_called()

    def test_github_secret_encryption_is_environment_scoped(self):
        from nacl.public import PrivateKey, SealedBox
        key=PrivateKey.generate();gh=GitHub('unused','mrnwebdesigns/example-site')
        with patch.object(gh,'request',side_effect=[{'key':'','key_id':'12'},None]) as api:
            api.side_effect=[{'key':base64.b64encode(bytes(key.public_key)).decode(),'key_id':'12'},None]
            gh.secret('DEPLOY_SSH_PRIVATE_KEY','sensitive-fixture','dev')
            args=api.call_args.args
            self.assertEqual('/repos/mrnwebdesigns/example-site/environments/dev/secrets/DEPLOY_SSH_PRIVATE_KEY',args[1])
            body=args[2]
            self.assertEqual(b'sensitive-fixture',SealedBox(key).decrypt(base64.b64decode(body['encrypted_value'])))
            self.assertNotIn('sensitive-fixture',json.dumps(body))

    def test_website_processes_do_not_inherit_service_credentials(self):
        with patch.dict(os.environ,{'OP_SERVICE_ACCOUNT_TOKEN':'private','GH_TOKEN':'private','GITHUB_TOKEN':'private','PATH':'/usr/bin'},clear=True):
            self.assertEqual({'PATH':'/usr/bin'},enrollment.site_process_env())

    def test_service_account_url_and_uuid_are_verified_before_secret_reads(self):
        from types import SimpleNamespace
        with tempfile.TemporaryDirectory() as temp:
            config=Path(temp)/'credentials.json'
            config.write_text(json.dumps({'account':enrollment.ACCOUNT,'service_account_id':'mrn-service',
                'references':{k:'op://Production Hub/fixture/'+k for k in ('github_token','qa_engine_token','deploy_private_key')}}))
            request={'account':enrollment.ACCOUNT,'domain':'example.mrndev.io','site_user':'example'}
            with patch.dict(os.environ,{'OP_SERVICE_ACCOUNT_TOKEN':'fixture','OP_CONNECT_TOKEN':'wrong-route'}),patch.object(enrollment_credentials.subprocess,'run') as run:
                run.return_value=SimpleNamespace(returncode=0,stdout=json.dumps({'url':'https://thehofmeyers.1password.com','user_uuid':'mrn-service'}))
                with self.assertRaises(ValueError):enrollment_credentials.resolve(config,request)
                self.assertEqual(1,run.call_count)
                self.assertNotIn('OP_CONNECT_TOKEN',run.call_args.kwargs['env'])
                self.assertEqual(enrollment.ACCOUNT,run.call_args.kwargs['env']['OP_ACCOUNT'])
