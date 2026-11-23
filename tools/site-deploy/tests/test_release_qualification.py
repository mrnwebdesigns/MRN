import argparse
import hashlib
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).parents[1]))
import qualify_local_release as local
import release_qualification as hosted


class HostedQualification(unittest.TestCase):
    def setUp(self):
        temp = tempfile.TemporaryDirectory()
        self.addCleanup(temp.cleanup)
        self.receipt = Path(temp.name) / 'site-release-qualification.json'
        self.report = self.receipt.with_name('site-source-qa.md')
        self.report.write_text('Exact source QA report\n')
        self.env = {'GITHUB_REPOSITORY': 'mrnwebdesigns/example', 'GITHUB_WORKFLOW_REF': 'trusted-main',
                    'GITHUB_RUN_ID': '263', 'GITHUB_RUN_ATTEMPT': '2', 'BUILD_ATTEMPT': '1',
                    'TOOLING_REF': 'a' * 40, 'SOURCE_SHA': 'b' * 40, 'SOURCE_PATH': '.', 'SLUG': 'child',
                    'QA_ARTIFACT_ID': '45', 'QA_REPORT_SHA256': hashlib.sha256(self.report.read_bytes()).hexdigest(),
                    'ARTIFACT_SHA256': 'c' * 64}
        self.receipt.write_text(json.dumps(hosted.identity(self.env)))

    def test_reuse_is_allowed_only_from_the_same_run_and_same_qualified_bytes(self):
        self.assertEqual(hosted.identity(self.env), hosted.verify(self.receipt, self.env))
        for name, value in {'GITHUB_REPOSITORY': 'mrnwebdesigns/other', 'GITHUB_RUN_ID': '264',
                            'GITHUB_WORKFLOW_REF': 'other-workflow', 'SOURCE_SHA': 'c' * 40,
                            'TOOLING_REF': 'd' * 40, 'ARTIFACT_SHA256': 'e' * 64,
                            'QA_ARTIFACT_ID': '46', 'QA_REPORT_SHA256': 'f' * 64, 'SLUG': 'other'}.items():
            with self.subTest(name=name), self.assertRaisesRegex(ValueError, 'does not match'):
                hosted.verify(self.receipt, {**self.env, name: value})

    def test_missing_receipt_or_modified_report_fails_closed(self):
        self.report.write_text('Unrelated QA result\n')
        with self.assertRaisesRegex(ValueError, 'does not match the successful QA job'):
            hosted.verify(self.receipt, self.env)
        self.receipt.unlink()
        with self.assertRaisesRegex(ValueError, 'qualification receipt'):
            hosted.verify(self.receipt, self.env)


class LocalQualification(unittest.TestCase):
    def setUp(self):
        temp = tempfile.TemporaryDirectory()
        self.addCleanup(temp.cleanup)
        root = Path(temp.name)
        site = root / 'public'
        (site / 'wp-content/themes/child').mkdir(parents=True)
        (site / 'wp-load.php').touch()
        self.args = argparse.Namespace(repo='mrnwebdesigns/example', sha='a' * 40, site_path=site,
                                       site_url='https://example.localhost', stylesheet='child',
                                       evidence_dir=root / 'evidence', publish=True)

    def test_failure_replaces_pending_without_publishing_success(self):
        with patch.object(local, 'require_source'), patch.object(local, 'publish') as publish, \
             patch.object(local.subprocess, 'run', side_effect=subprocess.CalledProcessError(1, 'mrn-qa')):
            with self.assertRaises(subprocess.CalledProcessError):
                local.qualify(self.args)
        self.assertEqual(['pending', 'failure'], [call.args[1] for call in publish.call_args_list])
        self.assertFalse((self.args.evidence_dir / 'qualification.json').exists())

    def test_success_binds_report_after_rechecking_exact_source(self):
        def run(command, **kwargs):
            Path(command[-1]).write_text('**Release QA Result: 100% SUCCESS**\n')

        with patch.object(local, 'require_source') as exact, patch.object(local, 'publish', return_value=98) as publish, \
             patch.object(local.subprocess, 'run', side_effect=run):
            local.qualify(self.args)
        self.assertEqual(2, exact.call_count)
        receipt = json.loads((self.args.evidence_dir / 'qualification.json').read_text())
        self.assertEqual(self.args.sha, receipt['source_sha'])
        self.assertEqual(98, receipt['github_status_id'])
        self.assertIn(receipt['report_sha256'], publish.call_args.args[2])


if __name__ == '__main__':
    unittest.main()
