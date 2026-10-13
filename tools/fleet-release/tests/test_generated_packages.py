"""Generated runtime assets participate in immutable Fleet package comparisons."""
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import build
import common


class CaptchaPackages(unittest.TestCase):
    slug = 'mrn-recaptcha-enterprise-manager'
    commit = 'a' * 40

    def payload(self):
        files = {self.slug + '.php': b'<?php /* Plugin Name: CAPTCHA\nVersion: 0.2.3 */'}
        assets = {}
        for handle in ('mrn-recaptcha-form-loader', 'mrn-recaptcha-comments'):
            variants = {}
            for variant in ('source', 'minified'):
                data = (handle + variant).encode()
                name = 'assets/generated/' + common.digest(data) + '.js'
                files[name] = data
                variants[variant] = {'path': name, 'sha256': common.digest(data), 'bytes': len(data)}
            assets[handle] = variants
        manifest = {'schema': 1, 'component': self.slug, 'source_commit': self.commit, 'assets': assets}
        files['assets/manifest.json'] = json.dumps(manifest).encode()
        return files

    def run_recipe(self, files, *, mutate_receipt=None, change_source=False):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            row = {'slug': self.slug, 'repository': self.slug, 'relative_source': '.'}
            calls = []

            def command(arguments, **kwargs):
                calls.append(arguments)
                if arguments[0] == 'python3':
                    output = Path(arguments[-1])
                    package = output / 'candidate.zip'
                    build.fleet.deterministic_zip(package, {self.slug + '/' + k: v for k, v in files.items()})
                    receipt = {'source_commit': self.commit, 'source_repository': 'mrnwebdesigns/' + self.slug,
                               'version': '0.2.3', 'archive': package.name,
                               'sha256': common.file_hash(package), 'size_bytes': package.stat().st_size,
                               'files': {k: common.digest(v) for k, v in files.items()},
                               'asset_manifest_sha256': common.digest(files['assets/manifest.json'])}
                    if mutate_receipt:
                        mutate_receipt(receipt)
                    common.write(output / 'release-receipt.json', receipt)

            revisions = iter([self.commit, 'b' * 40 if change_source else self.commit])

            def git(_root, *arguments):
                return '' if arguments[0] == 'status' else next(revisions)

            with patch.object(build, 'git', side_effect=git), patch.object(build, 'run', side_effect=command), \
                 patch.object(build.lock_tool, 'read_header_version', return_value='0.2.3'):
                result = build.plugin_payload(row, root, root)
            self.assertEqual(calls[0], ['npm', 'ci', '--ignore-scripts', '--no-audit', '--no-fund'])
            self.assertEqual(Path(calls[1][1]), root / self.slug / 'tools/build-release.py')
            return result

    def test_generated_assets_and_manifest_are_distribution_inputs(self):
        files = self.payload()
        self.assertEqual(self.run_recipe(files), files)
        # A raw Git export omits these five files; it cannot equal this package.
        self.assertIn('assets/manifest.json', files)
        self.assertEqual(len([p for p in files if p.startswith('assets/generated/')]), 4)

    def test_manifest_from_another_commit_is_rejected(self):
        files = self.payload()
        manifest = json.loads(files['assets/manifest.json'])
        manifest['source_commit'] = 'b' * 40
        files['assets/manifest.json'] = json.dumps(manifest).encode()
        with self.assertRaisesRegex(common.ReleaseError, 'manifest identity'):
            self.run_recipe(files)

    def test_corrupt_generated_asset_is_rejected_even_with_matching_zip_receipt(self):
        files = self.payload()
        files[next(p for p in files if p.startswith('assets/generated/'))] = b'tampered'
        with self.assertRaisesRegex(common.ReleaseError, 'generated asset differs'):
            self.run_recipe(files)

    def test_wrong_archive_receipt_is_rejected(self):
        with self.assertRaisesRegex(common.ReleaseError, 'package identity'):
            self.run_recipe(self.payload(), mutate_receipt=lambda r: r.update(sha256='b' * 64))

    def test_source_change_during_build_is_rejected(self):
        with self.assertRaisesRegex(common.ReleaseError, 'source changed'):
            self.run_recipe(self.payload(), change_source=True)

    def test_other_plugins_keep_their_git_export_recipe(self):
        row = {'slug': 'example', 'repository': 'MRN', 'relative_source': 'plugins/example'}
        with patch.object(build, 'git_files', return_value={'example.php': b'code'}) as export:
            self.assertEqual(build.plugin_payload(row, Path('/unused'), Path('/unused')), {'example.php': b'code'})
            export.assert_called_once()


if __name__ == '__main__':
    unittest.main()
