"""A stale bootstrap ZIP or mismatched Fleet prerequisite must block parity."""
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
import zipfile

spec = importlib.util.spec_from_file_location(
    'distribution', Path(__file__).parents[1] / 'scripts/verify-release-distribution.py')
distribution = importlib.util.module_from_spec(spec)
spec.loader.exec_module(distribution)


def write(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(data)


def zip_files(path, files):
    path.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(path, 'w') as archive:
        for name, data in files.items():
            archive.writestr(name, data)


class Distribution(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        root = Path(self.temporary.name)
        self.platform, self.bootstrap, self.fleet = root/'platform', root/'bootstrap', root/'fleet.zip'
        agent = {'agent.php': b'<?php /* Version: 1.0 */'}
        self.agent = {'slug': 'agent', 'version': '1.0', 'runtime_type': 'standard-plugin',
                      'deployed_path': 'plugins/agent',
                      'sha256': distribution.release_lock.bytes_tree_sha256(agent.items())[0], 'file_count': 1}
        self.themes = []
        for slug in ['mrn-base-stack', 'fixture-child']:
            files = {'style.css': b'/* Theme Name: fixture */'}
            digest, count = distribution.release_lock.bytes_tree_sha256(files.items())
            self.themes.append({'slug': slug, 'sha256': digest, 'file_count': count})
            for name, data in files.items():
                write(self.platform/'themes'/slug/name, data)
                write(self.bootstrap/'themes'/slug/name, data)
            zip_files(self.bootstrap/'themes'/(slug+'.zip'), {slug+'/'+k:v for k,v in files.items()})
        for name, data in agent.items():
            write(self.platform/'plugins/agent'/name, data)
        self.lock = {'release_id': 'fixture-r1', 'components': [self.agent], 'themes': self.themes}
        self.lock_bytes = json.dumps(self.lock).encode()
        write(self.platform/'mu-plugins/mrn-stack-release.lock.json', self.lock_bytes)
        write(self.bootstrap/'manifests/stack-release.lock.json', self.lock_bytes)
        package = self.bootstrap/'packages/agent.zip'
        zip_files(package, {'agent/'+k:v for k,v in agent.items()})
        self.plugins = {'plugins': [{'slug':'agent','package':'agent.zip','main_file':'agent/agent.php',
                                    'manifest_source':'/source/packages/agent.zip',
                                    'sha256':distribution.sha(package.read_bytes()),'size_bytes':package.stat().st_size}]}
        write(self.bootstrap/'manifests/bootstrap-packages.lock.json', json.dumps(self.plugins).encode())
        write(self.bootstrap/'manifests/plugins.txt', b'/source/packages/agent.zip\n')
        self.plan = {'release_id':'fixture-r1','lock_sha256':distribution.sha(self.lock_bytes),
                     'prerequisites':[self.agent]}
        self.fleet_files = {'payload/mu-plugins/mrn-stack-release.lock.json':self.lock_bytes,
                            'payload/themes/mrn-base-stack/style.css':b'/* Theme Name: fixture */'}
        self.build_fleet()

    def build_fleet(self):
        zip_files(self.fleet, {**self.fleet_files, 'plan.json':json.dumps(self.plan).encode()})

    def verify(self):
        return distribution.verify(self.platform,self.bootstrap,self.fleet)

    def test_complete_distribution_passes_without_claiming_publication(self):
        result = self.verify()
        self.assertEqual('pass', result['status'])
        self.assertFalse(result['hosted_publication_verified'])
        self.assertFalse(result['site_adoption_verified'])

    def test_stale_parent_archive_is_not_hidden_by_correct_extracted_tree(self):
        zip_files(self.bootstrap/'themes/mrn-base-stack.zip', {'mrn-base-stack/style.css':b'old'})
        with self.assertRaisesRegex(ValueError, 'theme archive'):
            self.verify()

    def test_default_installer_cannot_select_an_unlocked_path(self):
        write(self.bootstrap/'manifests/plugins.txt', b'/source/packages/stale.zip\n')
        with self.assertRaisesRegex(ValueError, 'installer paths'):
            self.verify()

    def test_seeded_agent_prerequisite_must_match_the_platform(self):
        self.plan['prerequisites'] = [{**self.agent, 'version':'0.9'}]
        self.build_fleet()
        with self.assertRaisesRegex(ValueError, 'prerequisite'):
            self.verify()

    def test_fleet_payload_cannot_reintroduce_the_site_child(self):
        self.fleet_files['payload/themes/fixture-child/style.css'] = b'/* Theme Name: fixture */'
        self.build_fleet()
        with self.assertRaisesRegex(ValueError, 'Fleet payload'):
            self.verify()

    def test_duplicate_archive_members_are_rejected(self):
        with zipfile.ZipFile(self.fleet,'w') as archive:
            archive.writestr('payload/a',b'old')
            archive.writestr('payload/a',b'new')
        with self.assertRaisesRegex(ValueError, 'duplicate'):
            distribution.archive_files(self.fleet)
