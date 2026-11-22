import hashlib
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))
import live_promotion as promotion
from verify_release import verify

SHA = 'a' * 40
PIN = 'b' * 40
ARCHIVE = 'f' * 64
REPO = 'mrnwebdesigns/example'
WRAPPER = '.github/workflows/site-deploy.yml'
RUN = '123'


class LivePromotionContract(unittest.TestCase):
    def setUp(self):
        temporary = tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        self.directory = Path(temporary.name)
        self.path = self.directory / 'site-promotion.json'
        self.report = self.directory / 'site-runtime-qa.md'
        self.report.write_text(promotion.PASSING_REPORT + '\n')
        self.environment = {'GITHUB_WORKFLOW_REF': REPO + '/' + WRAPPER + '@refs/heads/main',
                            'GITHUB_REPOSITORY': REPO, 'GITHUB_RUN_ID': '999', 'GITHUB_RUN_ATTEMPT': '2',
                            'TOOLING_REF': PIN, 'SOURCE_SHA': SHA, 'SOURCE_PATH': '.', 'SLUG': 'child'}
        self.producer = {**self.environment, 'GITHUB_RUN_ID': RUN, 'GITHUB_RUN_ATTEMPT': '1',
                         'SOURCE_BRANCH': 'main', 'TARGET': 'dev', 'ARTIFACT_ID': '78',
                         'QA_ARTIFACT_ID': '77', 'QA_REPORT_SHA256': 'd' * 64, 'ARTIFACT_SHA256': ARCHIVE,
                         'BUILD_ATTEMPT': '1'}
        self.deployment = {'repository': REPO, 'environment': 'dev', 'source_sha': SHA,
                           'status': 'public-verified', 'current': {'release_id': ARCHIVE},
                           'transfer_backup': {'valid': True}}
        self.write_deployment()
        self.receipt = promotion.write_receipt(self.directory, self.producer, self.path)
        paths = ('site-deploy.yml', 'site-deploy-target.yml', 'site-source-qa.yml', 'site-release-build.yml')
        self.run = {'id': int(RUN), 'path': WRAPPER, 'event': 'workflow_dispatch', 'head_branch': 'main',
                    'head_sha': SHA, 'repository': {'full_name': REPO}, 'head_repository': {'full_name': REPO},
                    'referenced_workflows': [{'path': promotion.WORKFLOW + path + '@' + PIN, 'sha': PIN}
                                             for path in paths],
                    'status': 'completed', 'conclusion': 'success', 'run_attempt': 1}
        self.steps = {name: 'success' for name in ('Preflight or deploy exact child-theme payload',
                      'Verify browser-loaded assets and responsive layout contracts', 'Run runtime QA',
                      'Publish passing pre-production promotion evidence')}
        self.artifact = {'id': 79, 'name': 'site-promotion-' + SHA, 'expired': False, 'workflow_run': {'id': int(RUN)}}
        self.archive = {'id': 78, 'name': 'site-release-' + SHA + '-attempt-1',
                        'expired': False, 'workflow_run': {'id': int(RUN)}}

    def write_deployment(self):
        (self.directory / 'site-deployment.json').write_text(json.dumps(self.deployment))

    def api(self, env, endpoint):
        if endpoint == f'actions/artifacts?name=site-promotion-{SHA}&per_page=100':
            unrelated = {**self.artifact, 'id': 80, 'workflow_run': {'id': 124}}
            return {'total_count': 2, 'artifacts': [unrelated, self.artifact]}
        if endpoint == 'actions/runs/124':
            return {**self.run, 'id': 124, 'head_branch': 'feature'}
        if endpoint == 'actions/artifacts/79':
            return self.artifact
        if endpoint == 'actions/artifacts/78':
            return self.archive
        if endpoint in (f'actions/runs/{RUN}', f'actions/runs/{RUN}/attempts/1'):
            return self.run
        if endpoint == f'actions/runs/{RUN}/attempts/1/jobs?per_page=100':
            return {'total_count': 1, 'jobs': [{'name': f"deploy / dev / Site target ({self.receipt['environment']})",
                    'conclusion': 'success',
                    'steps': [{'name': name, 'conclusion': result} for name, result in self.steps.items()]}]}
        raise AssertionError(endpoint)

    def test_dev_and_staging_promote_the_original_artifact(self):
        for target in ('dev', 'staging'):
            self.deployment['environment'] = target
            self.write_deployment()
            self.receipt = promotion.write_receipt(self.directory, {**self.producer, 'TARGET': target}, self.path)
            with self.subTest(target=target), patch.object(promotion, 'api', side_effect=self.api):
                self.assertEqual({'evidence_id': '79', 'run_id': RUN}, promotion.select(self.environment))
                authorization = promotion.verify_receipt(self.path, self.environment, RUN)
                self.assertEqual(('78', ARCHIVE, RUN), tuple(authorization[k]
                                 for k in ('archive_id', 'archive_sha256', 'run_id')))

    def test_both_requires_current_run_and_actual_dev_qa_success(self):
        env = {**self.environment, 'GITHUB_RUN_ID': RUN, 'PROMOTION_SAME_RUN': 'true', 'PROMOTION_EVIDENCE_ID': '79'}
        self.run.update(status='in_progress', conclusion=None)
        with patch.object(promotion, 'api', side_effect=self.api):
            self.assertEqual(RUN, promotion.select(env)['run_id'])
            promotion.verify_receipt(self.path, env, RUN)
            self.steps['Run runtime QA'] = 'failure'
            with self.assertRaisesRegex(ValueError, 'successful pre-production'):
                promotion.verify_receipt(self.path, env, RUN)
            with self.assertRaisesRegex(ValueError, 'from this run'):
                promotion.verify_receipt(self.path, {**env, 'GITHUB_RUN_ID': '999'}, RUN)

    def test_mismatched_source_pin_archive_or_runtime_report_is_rejected(self):
        with patch.object(promotion, 'api', side_effect=self.api):
            for key, value in (('source_sha', 'c' * 40), ('tooling_sha', 'd' * 40),
                               ('runtime_qa_sha256', 'b' * 64), ('repository', 'mrnwebdesigns/other')):
                self.path.write_text(json.dumps({**self.receipt, key: value}))
                with self.subTest(key=key), self.assertRaisesRegex(ValueError, 'does not match'):
                    promotion.verify_receipt(self.path, self.environment, RUN)
            self.path.write_text(json.dumps(self.receipt))
            self.archive['workflow_run']['id'] = 124
            with self.assertRaisesRegex(ValueError, 'producer run'):
                promotion.verify_receipt(self.path, self.environment, RUN)

    def test_expired_or_unpublished_evidence_and_wrong_producer_ref_are_rejected(self):
        with patch.object(promotion, 'api', side_effect=self.api):
            self.artifact['expired'] = True
            with self.assertRaisesRegex(ValueError, 'No passing Dev or staging'):
                promotion.select(self.environment)
            self.artifact['expired'] = False
            self.run['referenced_workflows'][1]['sha'] = 'f' * 40
            with self.assertRaisesRegex(ValueError, 'No passing Dev or staging'):
                promotion.select(self.environment)

    def test_failed_or_unverified_runtime_never_creates_promotion(self):
        self.path.unlink()
        self.report.write_text('**Release QA Result: NOT 100%**\n')
        with self.assertRaisesRegex(ValueError, 'did not pass'):
            promotion.write_receipt(self.directory, self.producer, self.path)
        self.assertFalse(self.path.exists())
        self.report.write_text(promotion.PASSING_REPORT)
        self.deployment['current']['release_id'] = 'e' * 64
        self.write_deployment()
        with self.assertRaisesRegex(ValueError, 'this exact protected'):
            promotion.write_receipt(self.directory, self.producer, self.path)

    def test_live_checks_original_archive_digest_independently_of_the_receipt(self):
        # A new label on different bytes cannot replace the verified release ID.
        new_bytes = b'another file from the same commit'
        self.assertNotEqual(ARCHIVE, hashlib.sha256(new_bytes).hexdigest())
        self.deployment['current']['release_id'] = hashlib.sha256(new_bytes).hexdigest()
        self.write_deployment()
        with self.assertRaisesRegex(ValueError, 'this exact protected'):
            promotion.write_receipt(self.directory, self.producer, self.path)
        tar = self.directory / 'site-release.tar'
        tar.write_bytes(new_bytes)
        with self.assertRaisesRegex(ValueError, 'checksum differs'):
            verify(tar, ARCHIVE, SHA, '.', 'child')
