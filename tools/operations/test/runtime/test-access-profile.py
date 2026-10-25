#!/usr/bin/env python3
import contextlib
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

HERE = Path(__file__).resolve().parent


def module(name):
    spec = importlib.util.spec_from_file_location(name, HERE / (name + '.py'))
    result = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(result)
    return result


parser = module('access-profile-parser')
hook = module('access-profile-hook')
HEADER = b'version: 1\ncreator: xdebug 3.5.3 (PHP 8.3.30)\ncmd: /private/secret-script.php\npart: 1\npositions: line\n\nevents: Time_(10ns) Memory_(bytes)\n\n'


def sample():
    # Exact 3.5.3 grammar from src/profiler/profiler.c at the pinned source.
    functions = [next(name for name, label in parser.LABELS.items() if label == required) for required in parser.REQUIRED]
    functions += ['php::hash_file', '{closure:/private/token.php:3-7}']
    blocks = []
    for i, name in enumerate(functions, 1):
        blocks.append(f'fl=(1){" /private/source.php" if i == 1 else ""}\nfn=({i}) {name}\n1 10 20\n\n')
    blocks.append('fl=(1)\nfn=(1)\n1 7 1\ncfl=(1)\ncfn=(5)\ncalls=1 0 0\n2 9 0\n\n')
    return HEADER + ''.join(blocks).encode() + b'summary: 100 200\n\n'


class ProfileTests(unittest.TestCase):
    def test_real_format_aggregation_redaction(self):
        result = parser.parse_profile(io.BytesIO(sample()))
        self.assertTrue(result['representative_path'])
        first = next(row for row in result['functions'] if row['label'] == parser.REQUIRED[0])
        self.assertEqual(first, {'label': parser.REQUIRED[0], 'calls': 2, 'self_10ns': 17, 'inclusive_10ns': 26})
        encoded = json.dumps(result)
        for private in ('/private', 'token', 'closure', 'source.php', 'secret-script'):
            self.assertNotIn(private, encoded)

    def test_representative_path_is_required(self):
        raw = sample().replace(b'MRN_MainWP_Content_Access::management_existing_readback', b'unknown_function')
        self.assertFalse(parser.parse_profile(io.BytesIO(raw))['representative_path'])

    def test_malformed_truncated_and_oversized(self):
        invalid = [sample()[:-20], sample().replace(b'3.5.3', b'3.4.0'),
                   sample().replace(b'Time_(10ns)', b'Other'), sample() + b'extra\n',
                   sample().replace(b'fn=(1) MRN', b'fn=(999) MRN', 1),
                   sample().replace(b'1 10 20', b'1 -2 20', 1),
                   sample().replace(b'1 10 20', b'1 99999999999999999 20', 1),
                   HEADER + b'x' * 16385 + b'\n', sample().replace(b'calls=1 0 0', b'calls=2 0 0')]
        for raw in invalid:
            with self.subTest(length=len(raw)), self.assertRaises(ValueError):
                parser.parse_profile(io.BytesIO(raw))
        with patch.object(parser, 'MAXIMUM', 100), self.assertRaises(ValueError):
            parser.parse_profile(io.BytesIO(sample()))

    def test_hook_cleanup_and_incomplete_failures(self):
        for failure in (None, 'reload', 'capture', 'parse', 'cleanup'):
            with self.subTest(failure=failure), tempfile.TemporaryDirectory() as temporary:
                root = Path(temporary).resolve()
                binding = root / 'existing-binding-v2'; binding.mkdir()
                cli = binding / 'wp-fixture-cli'; cli.write_text('exec docker exec mrn-api-123-456-existing-v2-cli wp core version\n')
                context = binding / 'context.json'; context.write_text(json.dumps({'pins': {'installed_manifest_sha256': 'a' * 64}}))
                build = root / 'build'; build.mkdir()
                def save(path, data):
                    path.write_bytes(data); path.chmod(0o600)
                save(build / 'xdebug.so', b'fixture-only')
                save(build / 'toolchain.json', json.dumps({'schema': 1, 'image': hook.IMAGE, 'xdebug_commit': hook.PIN,
                     'xdebug_tree': 'b' * 40, 'extension_sha256': hashlib.sha256(b'fixture-only').hexdigest(),
                     'php': '8.3.30', 'extension': '3.5.3', 'php_api': 'nts'}).encode())
                work = root / 'actual-gateway-existing-binding-v2'; work.mkdir()
                save(work / 'access-profile-claimed', b'PROFILE_ONLY\n')
                save(work / 'access-profile-response.json', b'{"profile_header":true,"status":200}')
                report = root / 'report.json'; calls = []
                def command(*args, **kwargs):
                    calls.append(args)
                    if args[:2] == ('docker', 'inspect'):
                        return json.dumps([{'Image': 'sha256:fixture', 'Name': '/mrn-api-123-456-existing-v2-wp',
                            'State': {'Running': True}, 'Config': {'Env': ['GITHUB_ACTIONS=true', 'MRN_DISPOSABLE_API_FIXTURE=1', 'MRN_DISPOSABLE_EXISTING_BINDING_TEST=1']},
                            'HostConfig': {'ShmSize': 67108864}, 'Mounts': []}]).encode()
                    if args[:3] == ('docker', 'image', 'inspect'):
                        return b'sha256:fixture\n'
                    if hook.VERIFY in args:
                        return b'a' * 64
                    if 'apache2ctl -k graceful' in str(args) and failure == 'reload':
                        raise ValueError('synthetic secret')
                    if args[:2] == ('docker', 'pause') and failure == 'capture':
                        raise ValueError('synthetic secret')
                    if args[:2] == ('docker', 'cp') and args[2].endswith(':/dev/shm/mrn-access-profile'):
                        raw = Path(args[3]); raw.mkdir()
                        save(raw / 'cachegrind.out.10', b'truncated' if failure == 'parse' else sample())
                    if args[:3] == ('docker', 'rm', '-f') and failure == 'cleanup':
                        raise ValueError('synthetic secret')
                    return b''
                env = {'GITHUB_ACTIONS': 'true', 'MRN_EXISTING_ACCESS_PROFILE_ONLY': '1',
                       'MRN_ACCESS_PROFILER_BUILD': str(build), 'MRN_ACCESS_PROFILE_REPORT': str(report)}
                with patch.dict(os.environ, env), patch.object(hook.sys, 'argv', ['hook', str(root), str(root / 'tls.crt'), str(cli), str(context)]), \
                        patch.object(hook, 'command', command), patch.object(hook.subprocess, 'run') as run, \
                        patch.object(hook.signal, 'signal'), contextlib.redirect_stdout(io.StringIO()):
                    run.return_value.returncode = 1
                    self.assertEqual(hook.main(), 1)
                result = json.loads(report.read_text())
                self.assertEqual(result['result'], 'PROFILE_ONLY' if failure is None else 'PROFILE_INCOMPLETE')
                self.assertEqual(result['cleanup'], failure != 'cleanup')
                self.assertTrue(any(c[:3] == ('docker', 'rm', '-f') for c in calls))
                self.assertNotIn('synthetic secret', report.read_text())
                self.assertFalse(result['acceptance'])


if __name__ == '__main__':
    unittest.main()
