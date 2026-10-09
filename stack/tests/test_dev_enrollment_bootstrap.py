"""Execute the real scanner against filesystem fixtures; no WordPress/server writes."""
from pathlib import Path
import os
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).parents[1]


class BootstrapEnrollment(unittest.TestCase):
    def scan(self, marked=False, enrollment_failure=False, bootstrap_failure=False, dry=False):
        with tempfile.TemporaryDirectory() as temporary:
            root=Path(temporary); stack=root/'stack'; scripts=stack/'scripts'; scripts.mkdir(parents=True)
            site=root/'home/owner/htdocs/example.mrndev.io';site.mkdir(parents=True)
            (site/'wp-config.php').touch()
            if marked: (site/'.mrn_bootstrapped').touch()
            # Lease contention/protocol are covered by source-controller tests.
            (scripts/'source-distribution-lease.sh').write_text('mrn_source_distribution_lease() { return 0; }\n')
            log=root/'calls'
            bootstrap=scripts/'site-bootstrap.sh'
            bootstrap.write_text('#!/bin/bash\nprintf "bootstrap\\n" >> "$CALL_LOG"\n'+('exit 1\n' if bootstrap_failure else 'touch "$2/.mrn_bootstrapped"\n'))
            bootstrap.chmod(0o755)
            (scripts/'bootstrap-dev-enrollment.sh').write_text('#!/bin/bash\nprintf "enrollment %s\\n" "$*" >> "$CALL_LOG"\n'+('exit 1\n' if enrollment_failure else 'exit 0\n'))
            args=['bash',str(ROOT/'scripts/bootstrap-new-sites.sh'),'--stack-root',str(stack),'--sites-root',str(root/'home')]
            if dry: args.append('--dry-run')
            result=subprocess.run(args,env={**os.environ,'CALL_LOG':str(log)},text=True,capture_output=True)
            return result,log.read_text() if log.exists() else ''

    def test_marked_site_resumes_only_enrollment_even_without_new_sites(self):
        result,calls=self.scan(marked=True)
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertIn('enrollment --resume --site-path',calls)
        self.assertNotIn('bootstrap\n',calls)

    def test_enrollment_failure_never_reboots_site(self):
        result,calls=self.scan(marked=True,enrollment_failure=True)
        self.assertNotEqual(result.returncode,0)
        self.assertNotIn('bootstrap\n',calls)

    def test_dry_run_does_not_bootstrap_or_enroll(self):
        for marked in (True,False):
            result,calls=self.scan(marked=marked,dry=True)
            self.assertEqual(result.returncode,0,result.stderr)
            self.assertEqual(calls,'')

    def test_bootstrap_failure_does_not_enroll(self):
        result,calls=self.scan(bootstrap_failure=True)
        self.assertNotEqual(result.returncode,0)
        self.assertEqual(calls,'bootstrap\n')

    def test_enqueue_is_after_license_gate_and_uses_separate_state(self):
        source=(ROOT/'scripts/site-bootstrap.sh').read_text()
        main=source.split('main() {',1)[1]
        self.assertLess(main.index('return 1'),main.index('bootstrap-dev-enrollment.sh'))
        self.assertIn('--enqueue --site-path "${WP_PATH}"',main)
        self.assertIn('add_warning "Dev deployment enrollment is pending',main)

    def test_missing_host_configuration_has_no_effect(self):
        with tempfile.TemporaryDirectory() as temp:
            result=subprocess.run(['bash',str(ROOT/'scripts/bootstrap-dev-enrollment.sh'),'--resume','--site-path','/home/example/htdocs/example.mrndev.io'],env={**os.environ,'MRN_DEV_ENROLLMENT_CONFIG':temp+'/missing'},capture_output=True,text=True)
            self.assertEqual(result.returncode,0)
            self.assertIn('not configured',result.stdout)

    def test_controller_outage_keeps_queue_and_retry_does_not_register_old_sites(self):
        with tempfile.TemporaryDirectory() as temporary:
            root=Path(temporary);config=root/'config.json';config.write_text('{}')
            log=root/'calls';runner=root/'runner.py'
            env={**os.environ,'MRN_DEV_ENROLLMENT_CONFIG':str(config),'MRN_DEV_ENROLLMENT_QUEUE':str(root/'queue'),
                 'MRN_DEV_ENROLLMENT_RUNNER':str(runner),'MRN_DEV_ENROLLMENT_PYTHON':sys.executable,'CALL_LOG':str(log)}
            script=['bash',str(ROOT/'scripts/bootstrap-dev-enrollment.sh')]
            first=subprocess.run(script+['--enqueue','--site-path','/home/new/htdocs/new.mrndev.io'],env=env,capture_output=True,text=True)
            self.assertNotEqual(first.returncode,0)
            self.assertEqual(1,len(list((root/'queue').glob('*.json'))))
            runner.write_text('import os,sys\nwith open(os.environ["CALL_LOG"],"a") as f:f.write(" ".join(sys.argv[1:])+"\\n")\n')
            resumed=subprocess.run(script+['--resume','--site-path','/home/new/htdocs/new.mrndev.io'],env=env,capture_output=True,text=True)
            self.assertEqual(resumed.returncode,0,resumed.stderr)
            self.assertEqual(2,len(log.read_text().splitlines()))
            prior=log.read_text()
            old=subprocess.run(script+['--resume','--site-path','/home/old/htdocs/old.mrndev.io'],env=env,capture_output=True,text=True)
            self.assertEqual(old.returncode,0,old.stderr);self.assertEqual(prior,log.read_text())
