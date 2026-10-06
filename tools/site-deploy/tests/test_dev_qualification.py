import argparse
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import textwrap
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))
import deploy

SHA = 'a' * 40


class DevQualification(unittest.TestCase):
    def setUp(self):
        self.args = argparse.Namespace(sha=SHA, mode='deploy', environment='dev')
        self.c = dict(ready=False, enrollment_sha=SHA, baseline='b'*64, host_provider='cloudpanel', backup_provider='updraft')
        self.env = dict(QUALIFY_DEV='true', GITHUB_ACTIONS='true', GITHUB_EVENT_NAME='workflow_dispatch',
                        GITHUB_REF='refs/heads/main', SOURCE_BRANCH='main', MRN_SOURCE_QA_SHA=SHA)

    def test_exact_authorized_qa_commit_is_required(self):
        with patch('release_request.api', return_value={'object': {'sha': SHA}}):
            self.assertTrue(deploy.qualification_request(self.args, self.c, self.env))
            for changes in ({'GITHUB_EVENT_NAME':'push'}, {'GITHUB_EVENT_NAME':'workflow_run'},
                            {'GITHUB_EVENT_NAME':'pull_request'}, {'SOURCE_BRANCH':'feature'},
                            {'MRN_SOURCE_QA_SHA':'b'*40}, {'GITHUB_REF':'refs/heads/feature'}, {'GITHUB_ACTIONS':'false'}):
                with self.subTest(changes=changes), self.assertRaises(ValueError):
                    deploy.qualification_request(self.args, self.c, {**self.env, **changes})
            for changes in ({'ready':True}, {'enrollment_sha':''}, {'enrollment_sha':'b'*40},
                            {'host_provider':'wpengine'}, {'backup_provider':'kinsta'}, {'baseline':''}):
                with self.subTest(changes=changes), self.assertRaises(ValueError):
                    deploy.qualification_request(self.args, {**self.c, **changes}, self.env)
            self.args.environment='live'
            with self.assertRaises(ValueError):
                deploy.qualification_request(self.args, self.c, self.env)

    def test_changed_main_rejected_and_routine_deploy_does_not_use_exception(self):
        with patch('release_request.api', return_value={'object': {'sha':'b'*40}}) as api:
            with self.assertRaises(ValueError):
                deploy.qualification_request(self.args, self.c, self.env)
            api.reset_mock()
            self.assertFalse(deploy.qualification_request(self.args, self.c, {}))
            api.assert_not_called()

    def test_both_and_automatic_requests_cannot_enter_qualification(self):
        workflow=(Path(__file__).parents[3]/'.github/workflows/site-deploy.yml').read_text()
        section=workflow.split('      - name: Validate trigger',1)[1].split('  source-qa:',1)[0]
        script=textwrap.dedent(section.split('        run: |\n',1)[1]).split('python3 ',1)[0]
        for event,target,want in [('workflow_dispatch','dev',0),('workflow_dispatch','both',1),('workflow_dispatch','live',1),('push','dev',1),('workflow_run','dev',1)]:
            result=subprocess.run(['bash','-c',script], env={**os.environ,'QUALIFY_DEV':'true','GITHUB_EVENT_NAME':event,'TARGET':target,'MODE':'deploy','SOURCE_BRANCH':'main'}, capture_output=True)
            self.assertEqual(result.returncode==0,want==0,(event,target))

    def test_qualification_runtime_is_strict_and_rollback_enabled(self):
        workflow=(Path(__file__).parents[3]/'.github/workflows/site-deploy-target.yml').read_text()
        section=workflow.split('      - name: Run runtime QA\n',1)[1].split('      - name:',1)[0]
        self.assertIn("!inputs.qualify_dev",section)
        self.assertIn("(inputs.target == 'live' || inputs.qualify_dev)",workflow)
        script=textwrap.dedent(section.split('        run: |\n',1)[1])
        with tempfile.TemporaryDirectory() as temp:
            engine=Path(temp)/'mrn-qa-engine/bin/mrn-qa'; engine.parent.mkdir(parents=True)
            engine.write_text('#!/bin/sh\nprintf "%s\\n" "$@"\n');engine.chmod(0o755)
            result=subprocess.check_output(['bash','-c',script],env={**os.environ,'RUNNER_TEMP':temp,'GITHUB_WORKSPACE':temp,'TARGET':'dev','QUALIFY_DEV':'true','DEPLOY_URL':'https://example.mrndev.io'},text=True)
            self.assertIn('--mode\nrelease\n--smoke-strict\n1',result)
            self.assertIn('--run-performance\nalways',result)

    def test_first_deploy_uses_existing_atomic_adoption_and_rollback(self):
        from types import SimpleNamespace
        from contextlib import ExitStack
        files={'style.css':'a'*64,'functions.php':'b'*64}
        c={**self.c,'url':'https://example.mrndev.io','template':'parent','root':'/home/site/htdocs/example.mrndev.io',
           'host':'ssh.example.invalid','state_dir':'/home/site/.mrn-site-deploy/dev','transport':'rsync','baseline':deploy.digest(files)}
        before={'home':c['url'],'stylesheet':'child','template':'parent','theme':c['root']+'/wp-content/themes/child',
                'files':files,'state':None,'state_ready':True,'writable':True,'backup_ready':True,'git':False}
        with tempfile.TemporaryDirectory() as temp,ExitStack() as stack:
            args=argparse.Namespace(sha=SHA,mode='deploy',environment='dev',source='public/wp-content/themes/child',slug='child',
                artifact=temp+'/release.tar',artifact_sha256='c'*64,receipt=temp+'/receipt.json')
            stack.enter_context(patch.dict(os.environ,{**self.env,'GITHUB_REPOSITORY':'mrnwebdesigns/example-site','DEPLOY_VERIFY_PAGES':'["https://example.mrndev.io/"]'},clear=True))
            stack.enter_context(patch('deployment_request.github_order',return_value=None))
            stack.enter_context(patch('release_request.api',return_value={'object':{'sha':SHA}}))
            stack.enter_context(patch('verify_release.verify',return_value={'theme_files':files}))
            stack.enter_context(patch.object(deploy,'Target',return_value=SimpleNamespace(inspect=lambda *a,**k:before)))
            activate=stack.enter_context(patch('atomic_runner.run',return_value={'status':'public-verified','current':{},'source_sha':SHA}))
            deploy.deploy(args,c)
            plan=activate.call_args.args[0]
            self.assertTrue(plan['adopt']);self.assertTrue(plan['exercise_rollback'])
            self.assertEqual(c['baseline'],plan['baseline']);self.assertEqual(SHA,plan['source_sha'])
            self.assertIsNone(plan['expected_current'])
            before['state']={'schema':1}
            activate.reset_mock()
            with self.assertRaises(ValueError):deploy.deploy(args,c)
            activate.assert_not_called()

    def test_runtime_receipt_preserves_actual_advisory_outcome(self):
        workflow=(Path(__file__).parents[3]/'.github/workflows/site-deploy-target.yml').read_text()
        section=workflow.split('      - name: Report deployment and runtime acceptance\n',1)[1].split('      - name:',1)[0]
        script=textwrap.dedent(section.split('        run: |\n',1)[1])
        with tempfile.TemporaryDirectory() as temp:
            for outcome in ('success','failure','skipped'):
                subprocess.run(['bash','-e','-c',script],env={**os.environ,'RUNNER_TEMP':temp,'SOURCE_SHA':SHA,
                    'TARGET':'dev','DEPLOY_URL':'https://example.mrndev.io','QA_RESULT':outcome,
                    'GITHUB_STEP_SUMMARY':temp+'/summary'},check=True,capture_output=True)
                result=json.loads((Path(temp)/'site-runtime-result.json').read_text())
                self.assertEqual({'outcome':outcome,'source_sha':SHA,'environment':'dev','url':'https://example.mrndev.io'},result)
