"""Pending source qualification is distinct from a failed immutable release audit."""
import fnmatch
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
import yaml

ROOT=Path(__file__).resolve().parents[3]

class PromotionDriftTrigger(unittest.TestCase):
    def setUp(self):
        self.workflow=yaml.load((ROOT/'.github/workflows/stack-promotion-drift.yml').read_text(),Loader=yaml.BaseLoader)

    def test_source_merges_wait_for_promotion_but_metadata_changes_are_audited(self):
        paths=self.workflow['on']['push']['paths']
        self.assertEqual(['main'],self.workflow['on']['push']['branches'])
        for source in ('tools/site-deploy/release_v1/cli.py','stack/themes/mrn-base-stack/functions.php',
                       'stack/mu-plugins/mrn-example.php','docs/MRN-DEPLOYMENT-QUICKSTART.md'):
            self.assertFalse(any(fnmatch.fnmatchcase(source,pattern) for pattern in paths),source)
        for contract in ('stack/STACK_VERSION.md','stack/manifests/stack-release.lock.json',
                         'stack/manifests/bootstrap-packages.lock.json',
                         'stack/manifests/release-locks/2026.11.23-example.json'):
            self.assertTrue(any(fnmatch.fnmatchcase(contract,pattern) for pattern in paths),contract)

    def test_daily_and_manual_audits_and_report_retention_remain_enabled(self):
        self.assertTrue(self.workflow['on']['schedule'])
        self.assertIn('workflow_dispatch',self.workflow['on'])
        job=self.workflow['jobs']['audit']
        self.assertNotIn('continue-on-error',job)
        artifact=next(step for step in job['steps'] if step.get('name')=='Preserve promotion inventory')
        self.assertEqual('always()',artifact['if'])
        self.assertEqual('error',artifact['with']['if-no-files-found'])

    def test_actual_audit_failure_keeps_nonzero_exit_and_recorded_status(self):
        step=next(step for step in self.workflow['jobs']['audit']['steps'] if step.get('id')=='promotion-audit')
        self.assertNotIn('continue-on-error',step)
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);stub=root/'bin';stub.mkdir()
            executable=stub/'python3';executable.write_text('#!/bin/sh\nexit "$MRN_AUDIT_FIXTURE_EXIT"\n');executable.chmod(0o755)
            for status in (0,7):
                output=root/('output-'+str(status))
                result=subprocess.run(['bash','-c',step['run']],cwd=root,capture_output=True,
                    env={**os.environ,'PATH':str(stub)+os.pathsep+os.environ['PATH'],
                         'MRN_AUDIT_FIXTURE_EXIT':str(status),'GITHUB_OUTPUT':str(output)})
                self.assertEqual(status,result.returncode)
                self.assertIn('exit_code='+str(status),output.read_text())

if __name__=='__main__':unittest.main()
