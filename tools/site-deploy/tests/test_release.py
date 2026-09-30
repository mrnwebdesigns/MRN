import copy
import hashlib
import io
import json
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).parents[1]))
from deploy import digest
from verify_release import verify

TOOLS = Path(__file__).parents[1]


class ReleaseArtifactContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory()
        cls.root = Path(cls.temp.name)
        source = cls.root / 'repo'
        source.mkdir()
        (source / 'functions.php').write_text("<?php require __DIR__ . '/mrn-release-assets.php';\n")
        (source / 'style.css').write_text('/* Theme Name: fixture */\nbody { color: #123456; }\n')
        (source / 'app.js').write_text('window.releaseFixture = 1;\n')
        subprocess.run(['git', 'init', '-q', str(source)], check=True)
        subprocess.run(['git', '-C', str(source), 'add', '.'], check=True)
        subprocess.run(['git', '-C', str(source), '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.invalid',
                        '-c', 'commit.gpgsign=false', 'commit', '-qm', 'fixture'], check=True)
        cls.sha = subprocess.check_output(['git', '-C', str(source), 'rev-parse', 'HEAD'], text=True).strip()
        cls.archive = cls.root / 'release.tar'
        command = [sys.executable, str(TOOLS / 'build_release.py'), '--sha', cls.sha, '--source', '.',
                   '--slug', 'child', '--output', str(cls.archive)]
        subprocess.run(command, cwd=source, check=True, capture_output=True)
        cls.expected = hashlib.sha256(cls.archive.read_bytes()).hexdigest()
        with tarfile.open(cls.archive) as archive:
            cls.files = {member.name: archive.extractfile(member).read() for member in archive}
        repeated = cls.root / 'repeated.tar'
        subprocess.run(command[:-1] + [str(repeated)], cwd=source, check=True, capture_output=True)
        cls.repeated = repeated.read_bytes()

    @classmethod
    def tearDownClass(cls):
        cls.temp.cleanup()

    def package(self, files, extra=None, refresh_inventory=False):
        files = copy.deepcopy(files)
        if refresh_inventory:
            release = json.loads(files['release.json'])
            release['files'] = {name: hashlib.sha256(body).hexdigest() for name, body in files.items() if name != 'release.json'}
            release['tree'] = digest(release['files'])
            release['manifest_sha256'] = release['files'].get('theme/mrn-assets.json')
            files['release.json'] = json.dumps(release).encode()
        path = self.root / 'mutated.tar'
        with tarfile.open(path, 'w', format=tarfile.USTAR_FORMAT) as archive:
            for name, body in files.items():
                member = tarfile.TarInfo(name)
                member.size = len(body)
                archive.addfile(member, io.BytesIO(body))
            if extra:
                archive.addfile(extra, io.BytesIO(b''))
        return path, hashlib.sha256(path.read_bytes()).hexdigest()

    def check(self, path=None, expected=None, **identity):
        return verify(path or self.archive, expected or self.expected, identity.get('sha', self.sha),
                      identity.get('source', '.'), identity.get('slug', 'child'))

    def test_real_build_is_reproducible_and_verified_without_runtime_qualification(self):
        self.assertEqual(self.archive.read_bytes(), self.repeated)
        result = self.check()
        self.assertEqual('artifact-verified', result['status'])
        self.assertFalse(result['runtime_qualified'])
        self.assertEqual(self.expected, result['artifact_sha256'])
        self.assertIn('style.min.css', result['theme_files'])

    def test_wrong_archive_and_source_identity_are_rejected(self):
        for values in [{'expected': '0' * 64}, {'sha': 'b' * 40}, {'source': 'other-theme'}, {'slug': 'other'}]:
            with self.subTest(values=values), self.assertRaises(ValueError):
                self.check(**values)

    def test_modified_or_missing_payload_fails_even_with_new_outer_checksum(self):
        for name in ['theme/functions.php', 'theme/mrn-assets.json']:
            files = dict(self.files)
            files.pop(name)
            with self.subTest(name=name), self.assertRaises(ValueError):
                self.check(*self.package(files))
        files = dict(self.files, **{'theme/functions.php': b'<?php /* unexpected change */'})
        with self.assertRaisesRegex(ValueError, 'inventory'):
            self.check(*self.package(files))

    def test_stale_minified_sibling_and_bad_mapping_fail_with_consistent_archive_inventory(self):
        files = dict(self.files, **{'theme/style.min.css': b'body{color:red}'})
        with self.assertRaisesRegex(ValueError, 'synchronized theme bytes'):
            self.check(*self.package(files, refresh_inventory=True))
        files = dict(self.files)
        manifest = json.loads(files['theme/mrn-assets.json'])
        manifest['assets']['style.css']['sha256'] = '0' * 64
        files['theme/mrn-assets.json'] = json.dumps(manifest).encode()
        with self.assertRaisesRegex(ValueError, 'mapping'):
            self.check(*self.package(files, refresh_inventory=True))

    def test_unsafe_paths_links_and_duplicate_entries_never_extract_files(self):
        for name in ['../escape.php', '/tmp/escape.php', 'theme/../escape.php', 'theme//a.php', 'theme/.hidden']:
            files = dict(self.files)
            files[name] = b'bad'
            with self.subTest(name=name), self.assertRaises(ValueError):
                self.check(*self.package(files))
        for kind in [tarfile.SYMTYPE, tarfile.LNKTYPE, tarfile.DIRTYPE, tarfile.CHRTYPE, tarfile.REGTYPE]:
            member = tarfile.TarInfo('theme/style.css')
            member.type = kind
            if kind in [tarfile.SYMTYPE, tarfile.LNKTYPE]:
                member.linkname = '../../outside'
            with self.subTest(kind=kind), self.assertRaises(ValueError):
                self.check(*self.package(self.files, extra=member))
        self.assertFalse((self.root / 'escape.php').exists())
        self.assertFalse((self.root / 'outside').exists())

    def test_unlisted_static_file_wrong_generation_and_duplicate_json_are_rejected(self):
        files = dict(self.files)
        files['assets/mrn-assets/child/wrong/extra.css'] = b'body{}'
        with self.assertRaisesRegex(ValueError, 'Unlisted'):
            self.check(*self.package(files, refresh_inventory=True))
        files = dict(self.files)
        manifest = json.loads(files['theme/mrn-assets.json'])
        manifest['generation'] = '0' * 64
        files['theme/mrn-assets.json'] = json.dumps(manifest).encode()
        with self.assertRaisesRegex(ValueError, 'identity'):
            self.check(*self.package(files, refresh_inventory=True))
        files = dict(self.files)
        files['release.json'] = b'{"schema": 1, "schema": 1}'
        with self.assertRaisesRegex(ValueError, 'Duplicate JSON'):
            self.check(*self.package(files))


if __name__ == '__main__':
    unittest.main()
