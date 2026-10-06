from pathlib import Path
import unittest

import yaml


class QADependencyContract(unittest.TestCase):
    def test_both_qa_jobs_use_shared_isolated_installer_before_acceptance(self):
        root = Path(__file__).resolve().parents[3]
        for workflow, job in [('site-source-qa.yml', 'qa'), ('site-deploy-target.yml', 'deploy')]:
            with self.subTest(workflow=workflow):
                data = yaml.load((root / '.github/workflows' / workflow).read_text(), Loader=yaml.BaseLoader)
                steps = data['jobs'][job]['steps']
                install = next(i for i, step in enumerate(steps) if step.get('name') == 'Install source QA dependencies')
                accept = next(i for i, step in enumerate(steps) if step.get('name') == 'MRN source acceptance')
                self.assertLess(install, accept)
                self.assertIn('bash "$RUNNER_TEMP/mrn-deploy-tools/tools/site-deploy/install-qa-python.sh"', steps[install]['run'])
                self.assertNotIn('pip install semgrep', steps[install]['run'])
                self.assertTrue(any(step.get('uses') == 'actions/setup-python@v5' for step in steps[:install]))
