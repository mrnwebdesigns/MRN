import copy
from pathlib import Path
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0,str(Path(__file__).parents[1]))
import doster_live_cutover as cutover
import kinsta
from promoted_store import DOSTER_CUTOVER_ORDER, reviewed_doster_promotion


class DosterLiveWorkflow(unittest.TestCase):
    def setUp(self):
        self.env=dict(GITHUB_ACTIONS='true',GITHUB_EVENT_NAME='workflow_dispatch',GITHUB_REF='refs/heads/main',
                      GITHUB_REPOSITORY=kinsta.DOSTER_REPOSITORY,QUALIFY_DOSTER_LIVE='true',TARGET='live',
                      GITHUB_WORKFLOW_REF=kinsta.DOSTER_REPOSITORY+'/.github/workflows/site-deploy.yml@refs/heads/main',
                      SOURCE_BRANCH='main',SOURCE_PATH='.',STYLESHEET='doster-and-brown-child',
                      SOURCE_SHA='a'*40,MRN_SOURCE_QA_SHA='a'*40,MODE='deploy',DEPLOY_READY='0',LIVE_ENABLED='true',
                      DEPLOY_HOST_PROVIDER='kinsta',DEPLOY_BACKUP_PROVIDER='kinsta',
                      DEPLOY_KINSTA_SITE_ID=kinsta.DOSTER_SITE,DEPLOY_KINSTA_ENVIRONMENT_ID=kinsta.DOSTER_LIVE,
                      DEPLOY_KINSTA_MANUAL_BACKUP_APPROVAL=kinsta.DOSTER_MANUAL_APPROVAL,
                      DEPLOY_KINSTA_TRANSACTION_BACKUP_APPROVED='1')
        self.env.update({'DEPLOY_'+k.upper():v for k,v in kinsta.DOSTER_LIVE_TARGET.items() if k!='environment'})
        self.run=dict(id=int(DOSTER_CUTOVER_ORDER['run_id']),event='workflow_dispatch',status='completed',conclusion='success',
                      head_sha=DOSTER_CUTOVER_ORDER['source_sha'],path='.github/workflows/site-deploy.yml',
                      repository={'full_name':kinsta.DOSTER_REPOSITORY},run_number=38,run_attempt=1)
        self.artifact=dict(id=int(cutover.ORIGINAL_ARTIFACT_ID),expired=False,
                           name='site-release-'+DOSTER_CUTOVER_ORDER['source_sha'],
                           workflow_run={'id':self.run['id'],'head_sha':self.run['head_sha']})

    def prepare(self,env=None,run=None,artifact=None,main=None):
        responses={'git/ref/heads/main':{'object':{'sha':main or 'a'*40}},
                   'actions/runs/'+DOSTER_CUTOVER_ORDER['run_id']:run or self.run,
                   'actions/artifacts/'+cutover.ORIGINAL_ARTIFACT_ID:artifact or self.artifact}
        with patch.object(cutover,'api',side_effect=lambda e,p:responses[p]),patch.object(cutover,'verify') as verify:
            result=cutover.prepare(env or self.env,'original.tar')
            verify.assert_called_once_with('original.tar',result['pointer']['release_id'],result['source_sha'],'.','doster-and-brown-child')
            return result

    def test_manual_current_main_qa_and_original_artifact_are_required(self):
        self.assertEqual(reviewed_doster_promotion(),self.prepare())
        with self.assertRaises(ValueError):self.prepare(main='b'*40)
        for key in self.env:
            with self.subTest(key=key),self.assertRaises(ValueError):
                self.prepare(env=dict(self.env,**{key:'different'}))
        with self.assertRaises(ValueError):self.prepare(env=dict(self.env,QUALIFY_DEV='true'))

    def test_readonly_preflight_does_not_claim_source_qa_or_deployment(self):
        self.prepare(env=dict(self.env,MODE='preflight',MRN_SOURCE_QA_SHA=''))
        with self.assertRaises(ValueError):self.prepare(env=dict(self.env,DEPLOY_READY='1'))

    def test_original_run_and_artifact_provenance_cannot_be_replaced(self):
        for key in self.run:
            with self.subTest(run=key),self.assertRaises(ValueError):
                self.prepare(run=dict(self.run,**{key:'different'}))
        for key in self.artifact:
            with self.subTest(artifact=key),self.assertRaises(ValueError):
                self.prepare(artifact=dict(self.artifact,**{key:'different'}))
        for key in self.artifact['workflow_run']:
            item=copy.deepcopy(self.artifact);item['workflow_run'][key]='different'
            with self.subTest(workflow_run=key),self.assertRaises(ValueError):self.prepare(artifact=item)

    def test_workflow_retrieves_and_verifies_original_before_ssh_configuration(self):
        root=Path(__file__).parents[3]
        target=(root/'.github/workflows/site-deploy-target.yml').read_text()
        wrapper=(root/'.github/workflows/site-deploy.yml').read_text()
        self.assertIn("artifact-ids: '"+cutover.ORIGINAL_ARTIFACT_ID+"'",target)
        self.assertIn("run-id: '"+DOSTER_CUTOVER_ORDER['run_id']+"'",target)
        self.assertLess(target.index('Verify the reviewed Doster cutover'),target.index('Configure pinned SSH identity'))
        self.assertIn('"${promotion_args[@]}"',target)
        self.assertIn('qualify_doster_live: ${{ inputs.qualify_doster_live }}',wrapper)
        for workflow in (target,wrapper):
            self.assertIn('"$GITHUB_REPOSITORY" == mrnwebdesigns/doster-and-brown-child-theme',workflow)
            self.assertIn('"$GITHUB_EVENT_NAME" == workflow_dispatch',workflow)
