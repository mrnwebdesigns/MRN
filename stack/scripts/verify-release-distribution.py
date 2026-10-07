#!/usr/bin/env python3
"""Read-only parity proof for platform, Fleet and new-site bootstrap inputs."""
import argparse
import hashlib
import importlib.util
import json
from pathlib import Path, PurePosixPath
import zipfile

spec = importlib.util.spec_from_file_location(
    'distribution_lock', Path(__file__).with_name('generate-stack-release-lock.py'))
release_lock = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release_lock)


def sha(data):
    return hashlib.sha256(data).hexdigest()


def archive_files(path, prefix=''):
    result = {}
    seen = set()
    with zipfile.ZipFile(path) as archive:
        for item in archive.infolist():
            name = PurePosixPath(item.filename)
            if name.is_absolute() or '..' in name.parts or item.filename in seen:
                raise ValueError('Unsafe or duplicate archive member: ' + item.filename)
            seen.add(item.filename)
            if (item.external_attr >> 16) & 0o170000 == 0o120000:
                raise ValueError('Archive symlinks are unsupported')
            if item.is_dir():
                continue
            if prefix and not item.filename.startswith(prefix + '/'):
                raise ValueError('Unexpected archive root: ' + item.filename)
            key = item.filename[len(prefix) + 1:] if prefix else item.filename
            result[key] = archive.read(item)
    return result


def tree_files(path):
    return {name: source.read_bytes() for name, source in release_lock.iter_digest_files(path)}


def verify(platform, bootstrap, fleet):
    platform, bootstrap = Path(platform), Path(bootstrap)
    lock_bytes = (platform / 'mu-plugins/mrn-stack-release.lock.json').read_bytes()
    lock = json.loads(lock_bytes)
    if (bootstrap / 'manifests/stack-release.lock.json').read_bytes() != lock_bytes:
        raise ValueError('Bootstrap and platform locks differ')
    plugins = json.loads((bootstrap / 'manifests/bootstrap-packages.lock.json').read_text())
    packages = {}
    for entry in plugins['plugins']:
        package = bootstrap / 'packages' / entry['package']
        data = package.read_bytes()
        if sha(data) != entry['sha256'] or len(data) != entry['size_bytes']:
            raise ValueError('Bootstrap package mismatch: ' + entry['slug'])
        packages[entry['slug']] = archive_files(package, entry['slug'])
        if entry['main_file'].split('/', 1)[1] not in packages[entry['slug']]:
            raise ValueError('Plugin identity absent: ' + entry['slug'])
    sources = {line.split('|')[0].strip() for line in
               (bootstrap / 'manifests/plugins.txt').read_text().splitlines()
               if line.strip() and not line.lstrip().startswith('#')}
    if sources != {entry['manifest_source'] for entry in plugins['plugins']}:
        raise ValueError('Default installer paths differ from plugin lock')
    for entry in lock['components']:
        deployed = tree_files(platform / entry['deployed_path'])
        if release_lock.bytes_tree_sha256(deployed.items()) != (entry['sha256'], entry['file_count']):
            raise ValueError('Platform component differs from lock: ' + entry['slug'])
        if entry['runtime_type'] == 'standard-plugin':
            distributed = packages[entry['slug']]
        else:
            distributed = tree_files(bootstrap / entry['deployed_path'])
        if distributed != deployed:
            raise ValueError('Bootstrap component differs from platform: ' + entry['slug'])
    for entry in lock['themes']:
        slug = entry['slug']
        deployed = tree_files(platform / 'themes' / slug)
        if release_lock.bytes_tree_sha256(deployed.items()) != (entry['sha256'], entry['file_count']):
            raise ValueError('Platform theme differs from lock: ' + slug)
        if tree_files(bootstrap / 'themes' / slug) != deployed:
            raise ValueError('Bootstrap theme tree differs: ' + slug)
        if archive_files(bootstrap / 'themes' / (slug + '.zip'), slug) != deployed:
            raise ValueError('Bootstrap theme archive differs: ' + slug)
    archived = archive_files(fleet)
    plan = json.loads(archived.pop('plan.json'))
    if plan['release_id'] != lock['release_id'] or plan['lock_sha256'] != sha(lock_bytes):
        raise ValueError('Fleet plan identity differs from lock')
    if any(not key.startswith('payload/') for key in archived):
        raise ValueError('Unexpected Fleet archive root')
    fleet_files = {key[len('payload/'):]: value for key, value in archived.items()}
    expected = {}
    for directory in ['mu-plugins', 'shared', 'plugins', 'themes']:
        for path in (platform / directory).rglob('*'):
            if path.is_file():
                expected[path.relative_to(platform).as_posix()] = path.read_bytes()
    # Exact-site Fleet plans preserve the active child and do not transport it.
    for theme in lock['themes']:
        if theme['slug'] != 'mrn-base-stack':
            prefix = 'themes/' + theme['slug'] + '/'
            expected = {key: value for key, value in expected.items() if not key.startswith(prefix)}
    prerequisites = {entry['slug']: entry for entry in plan['prerequisites']}
    for entry in lock['components']:
        if entry['slug'] in prerequisites:
            prerequisite = prerequisites[entry['slug']]
            if any(prerequisite[key] != entry[key] for key in ['sha256', 'file_count', 'version']):
                raise ValueError('Fleet prerequisite differs from lock: ' + entry['slug'])
            prefix = entry['deployed_path'] + '/'
            expected = {key: value for key, value in expected.items() if not key.startswith(prefix)}
    if fleet_files != expected:
        raise ValueError('Fleet payload differs from locked platform')
    return {'status': 'pass', 'release_id': lock['release_id'],
            'lock_sha256': sha(lock_bytes), 'fleet_sha256': sha(Path(fleet).read_bytes()),
            'components_verified': len(lock['components']), 'themes_verified': len(lock['themes']),
            'plugin_packages_verified': len(packages), 'default_paths_verified': len(sources),
            'hosted_publication_verified': False, 'site_adoption_verified': False}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--platform-root', type=Path, required=True)
    parser.add_argument('--bootstrap-root', type=Path, required=True)
    parser.add_argument('--fleet-package', type=Path, required=True)
    parser.add_argument('--report', type=Path, required=True)
    args = parser.parse_args()
    try:
        result = verify(args.platform_root, args.bootstrap_root, args.fleet_package)
    except (ValueError, KeyError, OSError, zipfile.BadZipFile) as error:
        result = {'status': 'blocked', 'error': str(error)}
    args.report.write_text(json.dumps(result, indent=2) + '\n')
    print(json.dumps(result))
    raise SystemExit(0 if result['status'] == 'pass' else 1)
