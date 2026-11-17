"""Build from committed runner inputs and verify the private artifact inventory."""
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tarfile
import tempfile
import unittest

TOOLS = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(TOOLS))
from build import archive_tree
from common import file_hash
from site_release_package import MEMBERS, PREFIX, build_runner


class SiteReleasePackage(unittest.TestCase):
    def test_reproducible_package_contains_only_tracked_runner_files(self):
        source = TOOLS.parents[1]
        with tempfile.TemporaryDirectory(prefix='fleet-release-package-') as temporary:
            root = Path(temporary)
            repo = root / 'source'
            repo.mkdir()
            subprocess.run(['git', '-C', repo, 'init', '-q'], check=True)
            for name in MEMBERS:
                destination = repo / PREFIX / name
                destination.parent.mkdir(parents=True, exist_ok=True)
                shutil.copyfile(source / PREFIX / name, destination)
            subprocess.run(['git', '-C', repo, 'add', 'tools'], check=True)
            subprocess.run(['git', '-C', repo, '-c', 'core.hooksPath=/dev/null', '-c', 'commit.gpgsign=false',
                            '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.invalid',
                            'commit', '-qm', 'Fixture'], check=True)
            (repo / PREFIX / 'untracked.txt').write_text('Forbidden outside the allowlist')
            (repo / PREFIX / 'release_v1/controller.py').write_text('Dirty source must be ignored')
            first, second = root / 'first.tar', root / 'second.tar'
            self.assertEqual(build_runner(repo, first, archive_tree), build_runner(repo, second, archive_tree))
            with tarfile.open(first) as bundle:
                self.assertEqual(set(bundle.getnames()), {'runner-manifest.json', *MEMBERS})
                manifest = json.load(bundle.extractfile('runner-manifest.json'))
                self.assertEqual(manifest['schema'], 'mrn.fleet-site-release/v1')
                self.assertEqual(set(manifest['files']), set(MEMBERS))
                path = root / 'controller.py'
                path.write_bytes(bundle.extractfile('release_v1/controller.py').read())
                self.assertEqual(file_hash(path), manifest['files']['release_v1/controller.py'])
                self.assertEqual(bundle.getmember('mrn-site-release').mode, 0o755)
