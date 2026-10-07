"""Failed first qualification retains evidence and cannot silently arm Dev."""
import base64
from copy import deepcopy
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))
import enroll_dev as enrollment
from test_dev_enrollment import FakeGitHub, site, SHA, PILOT


class QualificationRecovery(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.site = site(self.root / 'site')
        self.gh = FakeGitHub(); self.gh.current = SHA
        self.gh.prefix = '/repos/' + self.site['repository']
        self.state = {'site':self.site,'stage':'qualification-dispatched','binding':'binding',
                      'source_sha':SHA,'tooling_ref':'c'*40,'baseline':'b'*64}
        self.config = {'tooling_ref':'d'*40}
        self.e = enrollment.Enrollment(self.config,self.root,self.state,self.gh,{})
        self.run = {'id':99,'event':'workflow_dispatch','display_title':'MRN Dev qualification binding',
                    'status':'completed','conclusion':'failure'}
        self.gh.deployments = [self.run]
        self.pointer = {'schema':1,'release_id':'e'*64}
        self.receipt = {'status':'public-verified','source_sha':SHA,'repository':self.site['repository'],
                        'environment':'dev','url':self.site['url'],'slug':self.site['slug'],
                        'current':self.pointer,'previous':{'schema':1,'release_id':'f'*64},
                        'transfer_backup':{'valid':True},
                        'deployment_order':{'run_id':'99','event':'workflow_dispatch'},
                        'steps':[{'operation':op,'backup':{'valid':True}} for op in
                                 ('adopt','stage','activate','rollback-test','reactivate')]}
        self.source = {self.site['source_path']+'/style.css':base64.b64encode(b'/*\nVersion: 1.1.0\n*/').decode()}
        enrollment.save(self.root/'source.json',self.source)
        self.original = (self.root/'source.json').read_bytes()
        def request(method,path,**kwargs):
            return {'value':'0'} if path.endswith('/DEPLOY_READY') else {'value':SHA} if path.endswith('/DEPLOY_ENROLLMENT_SOURCE_SHA') else None
        self.patches = [patch.object(self.gh,'request',side_effect=request,create=True),
                        patch.object(self.gh,'deployment_evidence',return_value=(self.receipt,[],{'outcome':'failure'}),create=True),
                        patch.object(enrollment,'command',return_value=json.dumps(self.pointer)),
                        patch.object(enrollment,'source_qa')]
        self.mocks = [p.start() for p in self.patches]
    def tearDown(self):
        for p in reversed(self.patches):p.stop()
        self.temp.cleanup()
    def test_explicit_recovery_keeps_failed_evidence_and_retries_publish_safely(self):
        self.e.recover_qualification()
        self.assertEqual('recover-source',self.state['stage'])
        self.assertEqual(self.original,(self.root/'source.json').read_bytes())
        self.assertEqual('e'*64,self.state['recovery_prior_release'])
        retained = json.loads((self.root/('recovery-'+self.state['recovery_id']+'.json')).read_text())
        self.assertEqual('c'*40,retained['state']['tooling_ref'])
        self.assertEqual(self.receipt,retained['receipt'])
        self.e.tick()
        self.assertEqual('recover-publish',self.state['stage'])
        self.gh.publish_timeout = True
        with self.assertRaises(RuntimeError):self.e.tick()
        self.assertEqual('recover-publish',self.state['stage'])
        self.e.tick()
        self.assertEqual('wait-install-signal',self.state['stage'])
        self.assertEqual(PILOT,self.gh.variables['DEPLOY_ENROLLMENT_SOURCE_SHA'])
        self.assertEqual('e'*64,self.gh.variables['DEPLOY_ENROLLMENT_PRIOR_RELEASE'])
        self.assertNotIn('DEPLOY_READY',self.gh.variables)
        self.assertNotIn('MRN_AUTO_DEV_AFTER',self.gh.variables)
        self.assertEqual(1,len([c for c in self.gh.calls if isinstance(c,tuple) and c[0]=='commit']))
    def test_ordinary_resume_cannot_repair_or_repeat_failed_qualification(self):
        self.gh.proof_error = 'failed'
        with self.assertRaises(ValueError):self.e.tick()
        self.assertEqual('qualification-dispatched',self.state['stage'])
        self.assertEqual(0,self.gh.dispatch_count)
        self.assertFalse(list(self.root.glob('recovery-*.json')))
    def test_recovery_rejects_foreign_receipts_missing_backups_and_runtime_drift(self):
        original = deepcopy(self.receipt)
        for change in ({'source_sha':PILOT},{'url':'https://foreign.mrndev.io'},{'environment':'live'},
                       {'transfer_backup':{'valid':False}},{'steps':[]},
                       {'deployment_order':{'run_id':'98','event':'workflow_dispatch'}}):
            self.receipt.clear();self.receipt.update({**deepcopy(original),**change})
            with self.subTest(change=change),self.assertRaises(ValueError):self.e.recover_qualification()
            self.mocks[3].assert_not_called()
        self.receipt.clear();self.receipt.update(original)
        self.mocks[2].return_value=json.dumps({'schema':1,'release_id':'1'*64})
        with self.assertRaises(ValueError):self.e.recover_qualification()
        self.mocks[3].assert_not_called()
    def test_unknown_running_successful_or_armed_runs_are_not_recoverable(self):
        for status,conclusion in [('in_progress',''),('completed','success'),('completed','cancelled')]:
            self.run.update(status=status,conclusion=conclusion)
            with self.subTest(status=status,conclusion=conclusion),self.assertRaises(ValueError):self.e.recover_qualification()
        self.run.update(status='completed',conclusion='failure')
        self.mocks[0].side_effect=None;self.mocks[0].return_value={'value':'1'}
        with self.assertRaisesRegex(ValueError,'disarmed'):self.e.recover_qualification()
        self.mocks[3].assert_not_called()
    def test_source_qa_failure_keeps_original_state_and_evidence(self):
        self.mocks[3].side_effect=ValueError('source QA failed')
        with self.assertRaises(ValueError):self.e.recover_qualification()
        self.assertEqual('qualification-dispatched',self.state['stage'])
        self.assertEqual(self.original,(self.root/'source.json').read_bytes())
        self.assertEqual([],self.gh.calls)
