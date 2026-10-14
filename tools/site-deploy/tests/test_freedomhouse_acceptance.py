import copy
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import MagicMock, patch

sys.path.insert(0, str(Path(__file__).parents[1]))
import freedomhouse_acceptance as acceptance


def now():
    return datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')


class LocalQualification(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.before = Path.cwd()
        os.chdir(self.temp.name)
        self.env = {'GITHUB_REPOSITORY': acceptance.REPOSITORY, 'GITHUB_EVENT_NAME': 'workflow_dispatch',
                    'GITHUB_REF': 'refs/heads/main', 'TARGET': 'live', 'MODE': 'deploy',
                    'SOURCE_BRANCH': 'main', 'SOURCE_PATH': acceptance.SOURCE,
                    'STYLESHEET': 'freedomhouse-child02', 'DEPLOY_URL': acceptance.URL,
                    'LOCAL_QA_ACCEPTANCE': acceptance.PROOF, 'GITHUB_ACTOR': 'mrnwebdesigns',
                    'GITHUB_RUN_ID': '123', 'ARTIFACT_SHA256': 'a' * 64}
        subprocess.run(['git', 'init', '-q'], check=True)
        for name in acceptance.QA_INPUTS:
            file = Path(name) / 'fixture.php' if name in (acceptance.SOURCE, 'qa/phpstan') else Path(name)
            file.parent.mkdir(parents=True, exist_ok=True)
            file.write_text('qualified bytes\n')
        self.commit()
        sha = acceptance.git('rev-parse', 'HEAD')
        report = f'commit: {sha}\n- Mode: release\n**Release QA Result: 100% SUCCESS**\n'
        report += '\n'.join(f'| {row} | Ran | Pass | fixture | passed |' for row in acceptance.REQUIRED_ROWS)
        self.report = Path('qa/release-acceptance/local-release.md')
        self.report.parent.mkdir(parents=True, exist_ok=True)
        self.report.write_text(report)
        self.proof = {'schema': 1, 'repository': acceptance.REPOSITORY, 'url': acceptance.URL,
                      'operator': 'mrnwebdesigns', 'qualified_at': now(), 'qualified_source_sha': sha,
                      'source_tree': acceptance.git('rev-parse', f'{sha}:{acceptance.SOURCE}'),
                      'report': str(self.report), 'report_sha256': hashlib.sha256(self.report.read_bytes()).hexdigest(),
                      'php_version': '8.4.26', 'parent_version': '1.2.106', 'searchwp_version': '4.6.1.1'}
        self.write_proof()
        self.commit()
        self.env['SOURCE_SHA'] = acceptance.git('rev-parse', 'HEAD')

    def commit(self):
        subprocess.run(['git', 'add', '.'], check=True)
        subprocess.run(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', 'commit', '-qm', 'fixture'], check=True)

    def write_proof(self):
        Path(acceptance.PROOF).write_text(json.dumps(self.proof))

    def tearDown(self):
        os.chdir(self.before)
        self.temp.cleanup()

    def test_workflow_only_commit_accepts_identical_qa_inputs(self):
        self.assertEqual(acceptance.qualify(self.env)['status'], 'locally-qualified-awaiting-live-readback')

    def test_other_sites_automatic_runs_dev_and_wrong_operator_fail_closed(self):
        for key, value in [('GITHUB_REPOSITORY', 'mrnwebdesigns/other-site'), ('GITHUB_EVENT_NAME', 'workflow_run'),
                           ('GITHUB_REF', 'refs/heads/feature'), ('TARGET', 'dev'), ('MODE', 'preflight'),
                           ('SOURCE_BRANCH', 'feature'), ('GITHUB_ACTOR', 'someone'),
                           ('DEPLOY_URL', 'https://other.example'), ('LOCAL_QA_ACCEPTANCE', '../proof.json')]:
            with self.subTest(key=key), self.assertRaises(ValueError):
                acceptance.qualify({**self.env, key: value})

    def test_theme_and_qa_config_changes_invalidate_qualification(self):
        for name in [acceptance.SOURCE + '/fixture.php', 'composer.lock']:
            original = Path(name).read_text()
            Path(name).write_text('unqualified bytes')
            self.commit()
            self.env['SOURCE_SHA'] = acceptance.git('rev-parse', 'HEAD')
            with self.assertRaises(subprocess.CalledProcessError):
                acceptance.qualify(self.env)
            Path(name).write_text(original)
            self.commit()
            self.env['SOURCE_SHA'] = acceptance.git('rev-parse', 'HEAD')

    def test_tampered_report_stale_proof_and_missing_required_coverage_fail(self):
        self.report.write_text(self.report.read_text() + 'tampered')
        with self.assertRaises(ValueError):
            acceptance.qualify(self.env)
        self.proof['report_sha256'] = hashlib.sha256(self.report.read_bytes()).hexdigest()
        self.proof['qualified_at'] = '2020-01-01T00:00:00Z'
        self.write_proof()
        with self.assertRaises(ValueError):
            acceptance.qualify(self.env)
        self.proof['qualified_at'] = now()
        self.report.write_text(self.report.read_text().replace('| PHPStan | Ran | Pass |', '| PHPStan | Skipped | Pass |'))
        self.proof['report_sha256'] = hashlib.sha256(self.report.read_bytes()).hexdigest()
        self.write_proof()
        with self.assertRaises(ValueError):
            acceptance.qualify(self.env)


class LiveReadback(unittest.TestCase):
    def setUp(self):
        self.qualification = {'repository': acceptance.REPOSITORY, 'url': acceptance.URL,
                              'operator': 'mrnwebdesigns', 'source_sha': 'b' * 40,
                              'artifact_sha256': 'a' * 64, 'deployment_run_id': '123'}
        prefix = 'mrn-assets/freedomhouse-child02/' + 'c' * 64
        self.deployment = {'status': 'public-verified', 'current': {'public_path': prefix}}
        self.receipt = {**self.qualification, 'verified_at': now(), 'asset_prefix': prefix,
                        'live_rest': {'url': acceptance.URL + '/wp-json/', 'http_status': 200,
                                      'route_count': 100, 'namespaces': ['wp/v2'], 'body_sha256': 'e' * 64,
                                      'verified_at': now()},
                        'search': {'query': 'mobile crisis', 'results': 17},
                        'admin': {'selected_form': '2', 'repair_notices': []},
                        'services': {'learn_more_count': 5, 'contrast_ratio': 5.84},
                        'pages': [{'path': path, 'viewport': viewport, 'width': width,
                                   'content_present': True, 'horizontal_overflow': False, 'errors': [],
                                   'screenshot_sha256': 'd' * 64,
                                   'applied_stylesheets': [acceptance.URL + '/wp-content/' + prefix + '/style.min.css']}
                                  for path in acceptance.PATHS for viewport, width in acceptance.VIEWPORTS.items()]}

    def test_release_bound_readback_accepts(self):
        self.assertEqual(acceptance.validate_readback(self.receipt, self.qualification, self.deployment)['status'],
                         'local-qa-and-live-readback-accepted')

    def test_incomplete_stale_failed_or_wrong_release_evidence_fails(self):
        bad = []
        for key in ('source_sha', 'artifact_sha256', 'deployment_run_id', 'asset_prefix', 'operator'):
            row = copy.deepcopy(self.receipt); row[key] = 'wrong'; bad.append(row)
        row = copy.deepcopy(self.receipt); row['pages'].pop(); bad.append(row)
        row = copy.deepcopy(self.receipt); row['pages'][0]['errors'] = ['Cloudflare challenge']; bad.append(row)
        row = copy.deepcopy(self.receipt); row['pages'][0]['horizontal_overflow'] = True; bad.append(row)
        row = copy.deepcopy(self.receipt); row['pages'][0]['applied_stylesheets'] = []; bad.append(row)
        row = copy.deepcopy(self.receipt); row['services']['contrast_ratio'] = 2.6; bad.append(row)
        row = copy.deepcopy(self.receipt); row['admin']['repair_notices'] = ['Missing form']; bad.append(row)
        row = copy.deepcopy(self.receipt); row['verified_at'] = '2020-01-01T00:00:00Z'; bad.append(row)
        row = copy.deepcopy(self.receipt); del row['live_rest']; bad.append(row)
        row = copy.deepcopy(self.receipt); row['live_rest']['http_status'] = 403; bad.append(row)
        row = copy.deepcopy(self.receipt); row['live_rest']['route_count'] = 0; bad.append(row)
        for index, receipt in enumerate(bad):
            with self.subTest(index=index), self.assertRaises(ValueError):
                acceptance.validate_readback(receipt, self.qualification, self.deployment)

    def test_timeout_requires_rollback(self):
        with self.assertRaisesRegex(ValueError, 'rollback required'):
            acceptance.wait_readback(self.qualification, self.deployment, timeout=0)

    def test_artifact_redirect_drops_authentication(self):
        import urllib.request
        request = urllib.request.Request('https://api.github.com/artifact', headers={'Authorization': 'Bearer private'})
        redirected = acceptance.ArtifactRedirect().redirect_request(request, None, 302, 'Found', {}, 'https://storage.example/artifact')
        self.assertIsNone(redirected.get_header('Authorization'))

    def test_workflow_defaults_remain_hosted_and_failures_keep_rollback(self):
        workflow = (Path(__file__).parents[3] / '.github/workflows/site-deploy-target.yml').read_text()
        for section in ['Verify browser-loaded assets and responsive layout contracts', 'Run runtime QA']:
            step = workflow.split('      - name: ' + section + '\n')[1].split('      - name:')[0]
            self.assertIn("inputs.local_qa_acceptance == ''", step)
        self.assertIn("steps.local_readback.outcome == 'failure'", workflow)
        self.assertIn('--rollback-receipt "$RUNNER_TEMP/site-deployment.json"', workflow)


class ControllerRest(unittest.TestCase):
    def test_controller_rest_failure_prevents_public_verified_activation(self):
        import host_controller
        store = MagicMock()
        plan = {'host_provider': 'wpengine', 'url': acceptance.URL, 'pages': [acceptance.URL + '/']}
        pointer = {'release_id': 'a' * 64, 'public_path': 'mrn-assets/child/' + 'b' * 64}
        with patch.object(host_controller, 'html_cache', return_value={}), \
                patch.object(host_controller, 'http_check', side_effect=ValueError('REST failed')) as check:
            with self.assertRaisesRegex(ValueError, 'REST failed'):
                host_controller.public_check(plan, store, pointer)
            check.assert_called_once_with(acceptance.URL + '/wp-json/', rest=True)
        store.release.assert_not_called()
        store.verify_public_snapshot.assert_not_called()


if __name__ == '__main__':
    unittest.main()
