"""A dependency audit must fail on findings, not on an empty runtime lock."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

class SecurityExit(unittest.TestCase):
    def invoke(self, packages, audit_exit):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);scripts=root/'stack/scripts';scripts.mkdir(parents=True)
            source=Path(__file__).resolve().parents[3]/'stack/scripts'
            for name in ('qa-security.sh','qa-risk-scan.sh'):
                shutil.copyfile(source/name,scripts/name);(scripts/name).chmod(0o755)
            theme=root/'theme';theme.mkdir()
            (theme/'composer.lock').write_text(json.dumps({'packages':packages,'packages-dev':[]}))
            tools=root/'bin';tools.mkdir();marker=root/'audited'
            composer=tools/'composer';composer.write_text('#!/bin/sh\ntouch "$AUDIT_MARKER"\nexit "$AUDIT_EXIT"\n');composer.chmod(0o755)
            result=subprocess.run(['bash',str(scripts/'qa-security.sh'),str(theme)],capture_output=True,text=True,
                env={**os.environ,'PATH':str(tools)+os.pathsep+os.environ['PATH'],'AUDIT_MARKER':str(marker),
                     'AUDIT_EXIT':str(audit_exit),'MRN_QA_INCLUDE_DEV_AUDIT':'0'})
            return result,marker.exists()

    def test_empty_runtime_set_is_explicitly_inapplicable(self):
        result,audited=self.invoke([],1)
        self.assertEqual(0,result.returncode,result.stdout+result.stderr)
        self.assertFalse(audited);self.assertIn('No Composer runtime dependencies',result.stdout)

    def test_real_dependencies_are_audited_and_failure_remains_blocking(self):
        for audit_exit in (0,9):
            result,audited=self.invoke([{'name':'fixture/runtime','version':'1.0.0'}],audit_exit)
            self.assertTrue(audited)
            self.assertEqual(audit_exit==0,result.returncode==0,result.stdout+result.stderr)

if __name__=='__main__':unittest.main()
