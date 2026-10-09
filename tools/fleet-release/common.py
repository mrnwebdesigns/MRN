"""Shared source-release utilities. No WordPress/site mutation interfaces."""
from __future__ import annotations

import hashlib
import importlib.util
import json
import os
from pathlib import Path, PurePosixPath
import re
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
SLUG = re.compile(r"^[a-z][a-z0-9-]{1,100}$")
COMMIT = re.compile(r"^[a-f0-9]{40}$")
SHA256 = re.compile(r"^[a-f0-9]{64}$")


class ReleaseError(RuntimeError):
    """An actionable source, qualification or publication failure."""


def module(name, filename):
    spec = importlib.util.spec_from_file_location(name, ROOT / 'stack/scripts' / filename)
    value = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(value)
    return value


lock_tool = module('fleet_release_lock', 'generate-stack-release-lock.py')
assembly = module('fleet_release_assembly', 'assemble-stack-release.py')
fleet = module('fleet_release_package', 'build-mainwp-stack-release.py')
distribution = module('fleet_release_distribution', 'verify-release-distribution.py')
promotion = module('fleet_release_promotion', 'qa-stack-promotion.py')


def run(arguments, *, cwd=None, env=None, timeout=300):
    result = subprocess.run([str(v) for v in arguments], cwd=cwd, env=env,
                            capture_output=True, text=True, timeout=timeout, check=False)
    if result.returncode:
        # Commands can carry private paths/status. Raw output belongs in the
        # private job evidence, never in a public workflow or notification.
        raise ReleaseError(f'{Path(str(arguments[0])).name} failed ({result.returncode})')
    return result.stdout.strip()


def git(repo, *arguments, **kwargs):
    return run(['git', '-C', repo, *arguments], **kwargs)


def read(path):
    return json.loads(Path(path).read_text())


def digest(data):
    return hashlib.sha256(data).hexdigest()


def file_hash(path):
    return lock_tool.file_sha256(Path(path))


def canonical(value):
    return json.dumps(value, sort_keys=True, separators=(',', ':')).encode()


def write(path, value):
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    data = json.dumps(value, indent=2, sort_keys=True) + '\n'
    with tempfile.NamedTemporaryFile(dir=path.parent, delete=False) as stream:
        temporary = Path(stream.name)
        try:
            stream.write(data.encode())
            stream.flush()
            os.fsync(stream.fileno())
            temporary.chmod(0o600)
            os.replace(temporary, path)
            directory = os.open(path.parent, os.O_RDONLY)
            try:
                os.fsync(directory)
            finally:
                os.close(directory)
        finally:
            temporary.unlink(missing_ok=True)


def relative(value):
    path = PurePosixPath(value)
    if (not value or value != path.as_posix() or path.is_absolute()
            or any(p in ('..', '.', 'secrets', '.git') for p in path.parts)):
        raise ReleaseError('Unsafe source-distribution path')
    return path


def clean_main(repo):
    if git(repo, 'status', '--porcelain=v1', '--untracked-files=all'):
        raise ReleaseError('Source repository must be clean')
    head = git(repo, 'rev-parse', 'HEAD')
    default = git(repo, 'symbolic-ref', 'refs/remotes/origin/HEAD')
    if head != git(repo, 'rev-parse', default):
        raise ReleaseError('Source must be the fetched merged default branch')
    if not COMMIT.fullmatch(head):
        raise ReleaseError('Invalid source commit')
    return head


def source_files(repo, path):
    # MRN deployable digests have historically used the source tree; external
    # components use Git archive/export-ignore. Preserve both lock contracts.
    source = Path(repo) / path
    return {name: item.read_bytes() for name, item in lock_tool.iter_digest_files(source)}


def repository_name(value):
    name = str(value).removeprefix('mrnwebdesigns/')
    if name != 'MRN' and not SLUG.fullmatch(name):
        raise ReleaseError('Repository outside the MRN source namespace')
    return name


def roster(repo):
    catalog = read(Path(repo) / 'stack/manifests/component-catalog.json')
    retired = {v['slug'] for v in read(Path(repo) /
               'stack/manifests/retired-capabilities.json')['capabilities']}
    rows = []
    for item in catalog['components']:
        source = item.get('source') or {}
        if (item['slug'] in retired or item.get('target_tier') in ('archived', 'review')
                or not source.get('repository') or not source.get('path')):
            continue
        if item.get('runtime_type') not in ('standard-plugin', 'mu-component',
                                           'mu-loader', 'shared-runtime'):
            continue
        if not SLUG.fullmatch(str(item.get('slug', ''))):
            raise ReleaseError('Invalid catalog component slug')
        row = dict(item)
        row['repository'] = repository_name(source['repository'])
        # Catalog absolute workspace paths are documentary ownership references.
        # A service checkout must never fall back to that mutable workspace.
        if row['repository'] == 'MRN':
            row['relative_source'] = str(relative(source['path']))
        else:
            suffix = str(source['path']).split('/' + row['repository'], 1)
            row['relative_source'] = (suffix[1].strip('/') if len(suffix) == 2 else '') or '.'
            if row['relative_source'] != '.':
                relative(row['relative_source'])
        rows.append(row)
    return rows
