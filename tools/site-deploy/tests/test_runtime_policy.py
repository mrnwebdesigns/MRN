"""Exercise the workflow's real runtime command without contacting a site."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import textwrap
import unittest


class RuntimePolicy(unittest.TestCase):
    def test_dev_skips_speed_tests_and_live_retains_release_acceptance(self):
        workflow = Path(__file__).parents[3] / '.github/workflows/site-deploy-target.yml'
        section = workflow.read_text().split('      - name: Run runtime QA\n', 1)[1].split('      - name:', 1)[0]
        command = textwrap.dedent(section.split('        run: |\n', 1)[1])
        with tempfile.TemporaryDirectory() as root:
            engine = Path(root) / 'mrn-qa-engine/bin/mrn-qa'
            engine.parent.mkdir(parents=True)
            engine.write_text('#!/usr/bin/env python3\nimport json,sys\nprint(json.dumps(sys.argv[1:]))\n')
            engine.chmod(0o755)
            for target in ['dev', 'live']:
                env = {**os.environ, 'RUNNER_TEMP': root, 'GITHUB_WORKSPACE': root,
                       'TARGET': target, 'DEPLOY_URL': 'https://example.invalid'}
                args = json.loads(subprocess.check_output(['bash', '-c', command], env=env, text=True))
                for flag in ['--run-api', '--run-smoke', '--run-accessibility']:
                    self.assertEqual(args[args.index(flag) + 1], 'always')
                self.assertEqual(args[args.index('--run-performance') + 1], 'never' if target == 'dev' else 'always')
                self.assertEqual(args[args.index('--run-cwv') + 1], 'never' if target == 'dev' else 'auto')
                self.assertEqual('--smoke-strict' in args, target == 'live')
