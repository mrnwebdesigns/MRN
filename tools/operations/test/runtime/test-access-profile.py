#!/usr/bin/env python3
import contextlib
import hashlib
import importlib.util
import io
import json
import os
import subprocess
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
    functions += ['php::hash_file', '{closure:/private/token.php:3-7}', 'MRN_MainWP_Management_Requester_Coherence->descriptor']
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
        self.assertTrue(any(row['label'] == 'Management_Requester_Coherence.descriptor' for row in result['functions']))
        encoded = json.dumps(result)
        for private in ('/private', 'token', 'closure', 'source.php', 'secret-script'):
            self.assertNotIn(private, encoded)

    def test_representative_path_is_required(self):
        raw = sample().replace(b'MRN_MainWP_Content_Access::management_existing_readback', b'unknown_function')
        self.assertFalse(parser.parse_profile(io.BytesIO(raw))['representative_path'])

    def test_exact_parser_line_boundary_and_one_more(self):
        raw = sample(); lines = raw.count(b'\n')
        with patch.object(parser, 'MAX_LINES', lines):
            self.assertTrue(parser.parse_profile(io.BytesIO(raw))['representative_path'])
            with self.assertRaisesRegex(ValueError, '^Excessive profile lines$'):
                parser.parse_profile(io.BytesIO(raw + b'\n'))
        truncated = raw.rsplit(b'summary:', 1)[0] + b'summary: 100'
        with patch.object(parser, 'MAX_LINES', truncated.count(b'\n') + 1):
            with self.assertRaisesRegex(ValueError, '^Oversized or truncated profile$'):
                parser.parse_profile(io.BytesIO(truncated))

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
        with patch.object(parser, 'MAX_LINES', 5), self.assertRaises(ValueError):
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
                    if hook.FREEZE in args and failure == 'capture':
                        raise ValueError('synthetic secret')
                    raw = b'truncated' if failure == 'parse' else sample()
                    if hook.RAW_INFO in args:
                        return json.dumps({'status': 'ready', 'file_count': 1, 'name': 'cachegrind.out.10', 'bytes': len(raw)}).encode()
                    if hook.RAW_READ in args:
                        return raw
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
                if failure != 'reload':
                    freeze = next(i for i, c in enumerate(calls) if hook.FREEZE in c)
                    stop = next(i for i, c in enumerate(calls) if c[:3] == ('docker', 'kill', '--signal=SIGSTOP'))
                    self.assertLess(stop, freeze)
                self.assertNotIn('synthetic secret', report.read_text())
                self.assertFalse(result['acceptance'])

    def test_actual_php_metadata_reports_refusals_without_exporting_names(self):
        for case in ('ready', 'root-refused', 'entries-refused', 'name-refused', 'kind-refused', 'size-refused'):
            with self.subTest(case=case), tempfile.TemporaryDirectory() as temporary:
                root = Path(temporary).resolve(); root.chmod(0o700)
                file = root / 'cachegrind.out.33'
                if case == 'root-refused':
                    root.chmod(0o755)
                elif case == 'name-refused':
                    (root / 'PRIVATE_NAME_MUST_NOT_LEAVE').write_text('unused')
                elif case == 'kind-refused':
                    file.symlink_to(root / 'PRIVATE_PATH_MUST_NOT_LEAVE')
                elif case == 'size-refused':
                    with file.open('wb') as stream:
                        stream.truncate(parser.MAXIMUM + 1)
                elif case == 'ready':
                    file.write_bytes(sample())
                code = hook.RAW_INFO.replace('/dev/shm/mrn-access-profile', str(root))
                result = subprocess.run(['php', '-n', '-r', code], capture_output=True, check=True, timeout=5)
                info = json.loads(result.stdout)
                self.assertEqual(info['status'], case)
                self.assertEqual(set(info), {'status', 'file_count', 'bytes', 'name'})
                self.assertNotIn('PRIVATE_', result.stdout.decode())
                if case != 'ready':
                    self.assertIsNone(info['name'])
                if case == 'size-refused':
                    self.assertEqual(info['bytes'], parser.MAXIMUM + 1)

    def test_actual_php_measured_trace_and_reader_boundary(self):
        for size in (34092391, 40 * 1024 * 1024, 40 * 1024 * 1024 + 1, 40 * 1024 * 1024 + 2):
            with self.subTest(size=size), tempfile.TemporaryDirectory() as temporary:
                root = Path(temporary).resolve(); root.chmod(0o700)
                file = root / 'cachegrind.out.33'
                with file.open('wb') as stream:
                    stream.truncate(size)
                code = hook.RAW_INFO.replace('/dev/shm/mrn-access-profile', str(root))
                result = subprocess.run(['php', '-n', '-r', code], capture_output=True, check=True, timeout=5)
                info = json.loads(result.stdout)
                self.assertEqual(info['status'], 'ready' if size <= parser.MAXIMUM else 'size-refused')
                self.assertEqual(info['bytes'], size)
                # Exercise the separate fixed reader's one-byte sentinel even
                # for inputs the metadata gate correctly refuses to dispatch.
                code = hook.RAW_READ.replace('/dev/shm/mrn-access-profile/', str(root) + '/')
                result = subprocess.run(['php', '-n', '-r', code, file.name], capture_output=True, check=True, timeout=5)
                self.assertEqual(len(result.stdout), min(size, parser.MAXIMUM + 1))
                self.assertEqual(result.stderr, b'')


if __name__ == '__main__':
    unittest.main()
