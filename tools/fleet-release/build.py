"""Deterministic source metadata and matching platform/Fleet/bootstrap builds."""
from __future__ import annotations

import copy
import datetime as dt
import io
from pathlib import Path
import re
import shutil
import tarfile
import zipfile

from common import (ReleaseError, assembly, canonical, digest, distribution,
                    file_hash, fleet, git, lock_tool, read, relative, roster,
                    source_files, write)

BOOTSTRAP_FILES = (
    'scripts/site-bootstrap.sh', 'scripts/bootstrap-new-sites.sh',
    'scripts/bootstrap-dev-enrollment.sh',
    'scripts/source-distribution-lease.sh',
    'configs/importers/stack-export-importer.sh', 'configs/site-owner-authorized-key.pub',
    'BOOTSTRAP_RELEASE.md', 'STACK_VERSION.md', 'RESTORING-RETIRED-CAPABILITIES.md',
    'manifests/plugins.txt', 'manifests/themes.txt', 'manifests/licenses.txt',
    'manifests/importers.txt', 'manifests/credential-files.txt',
    'manifests/license-exemptions.txt', 'manifests/stack-release.lock.json',
    'manifests/bootstrap-packages.lock.json', 'manifests/component-catalog.json',
    'manifests/retired-capabilities.json',
    'configs/exports/ame-config-container.json',
    'configs/exports/ame-toolbar-editor.settings.json',
    'configs/exports/platform-Advanced-Editor-Tools-settings.json',
    'configs/exports/MRN-Login.png', 'configs/exports/mrn-logo-png.png',
    'configs/exports/mrn-logo.svg',
)


def plugin_source(row, repo, standalone):
    root = repo if row['repository'] == 'MRN' else standalone / row['repository']
    return root, row['relative_source']


def main_file(files, slug, expected=None):
    candidate = expected.split('/', 1)[1] if expected else slug + '.php'
    if candidate in files:
        return candidate
    choices = [name for name, data in files.items()
               if '/' not in name and name.endswith('.php') and b'Plugin Name:' in data[:16384]]
    if len(choices) != 1:
        raise ReleaseError('Ambiguous plugin main file: ' + slug)
    return choices[0]


def git_files(row, repo, standalone):
    root, path = plugin_source(row, repo, standalone)
    if row['repository'] == 'MRN':
        return source_files(root, path)
    return dict(lock_tool.git_archive_files(root, git(root, 'rev-parse', 'HEAD'), path))


def package_files(path, slug):
    """Compare actual distributable bytes, independently of the ZIP envelope."""
    with zipfile.ZipFile(path) as archive:
        files = {}
        for item in archive.infolist():
            if item.is_dir():
                continue
            name = str(relative(item.filename))
            if not name.startswith(slug + '/') or name in files:
                raise ReleaseError('Invalid plugin package member')
            if item.external_attr >> 16 & 0o170000 == 0o120000:
                raise ReleaseError('Plugin package contains a symlink')
            files[name[len(slug) + 1:]] = archive.read(item)
        return files


def snapshot(repo, standalone, policy):
    """Record all participating merged refs plus the exact distributable vector."""
    repo, standalone = Path(repo), Path(standalone)
    rows = roster(repo)
    refs = {'MRN': git(repo, 'rev-parse', 'HEAD')}
    units = []
    for row in rows:
        root, path = plugin_source(row, repo, standalone)
        refs[row['repository']] = git(root, 'rev-parse', 'HEAD')
        files = git_files(row, repo, standalone)
        checksum, count = lock_tool.bytes_tree_sha256(files.items())
        units.append({'slug': row['slug'], 'repository': row['repository'],
                      'source_path': path, 'sha256': checksum, 'file_count': count,
                      'target_tier': row['target_tier'],
                      'held_default': row['slug'] in policy['held_defaults']})
    for theme in lock_tool.parse_theme_manifest(repo / 'stack/manifests/themes.txt'):
        path = 'stack/themes/' + theme['slug']
        checksum, count = lock_tool.tree_sha256(repo / path)
        units.append({'slug': theme['slug'], 'repository': 'MRN', 'source_path': path,
                      'sha256': checksum, 'file_count': count, 'target_tier': 'platform-required'})
    # Deployment/bootstrap contracts are release inputs even when no plugin
    # code changed. Never fingerprint the lock/version files generated below.
    generated = {'STACK_VERSION.md', 'BOOTSTRAP_RELEASE.md',
                 'manifests/stack-release.lock.json', 'manifests/component-catalog.json',
                 'manifests/bootstrap-packages.lock.json'}
    contracts = {name: file_hash(repo / 'stack' / name)
                 for name in BOOTSTRAP_FILES if name not in generated}
    # Accepted deployment/release helpers are also distributed source inputs.
    # Read tracked files only; caches and another task's work never participate.
    for name in git(repo, 'ls-files', 'stack/scripts', 'tools/fleet-release').splitlines():
        contracts[name] = file_hash(repo / name)
    vendor = read(repo / 'stack/manifests/bootstrap-packages.lock.json')
    packages = [{key: item.get(key) for key in ('slug', 'version', 'sha256', 'package', 'source')}
                for item in vendor['plugins']
                if item['source']['type'] != 'git' or item['slug'] in policy['held_defaults']]
    material = {'units': units, 'contracts': contracts, 'pinned_packages': packages}
    return {'refs': refs, 'material': material, 'material_sha256': digest(canonical(material)),
            'source_vector_sha256': digest(canonical(refs))}


def default_packages(repo, standalone, policy, inputs, output, registry_inputs=None):
    """Refresh MRN packages while retaining exact qualified vendor/held inputs."""
    rows = {r['slug']: r for r in roster(repo)}
    packages = copy.deepcopy(read(repo / 'stack/manifests/bootstrap-packages.lock.json'))
    output = Path(output)
    output.mkdir(parents=True, exist_ok=True)
    for item in packages['plugins']:
        slug = item['slug']
        destination = output / str(relative(item['package']))
        destination.parent.mkdir(parents=True, exist_ok=True)
        hold = policy['held_defaults'].get(slug)
        if hold and item['version'] != hold['version']:
            raise ReleaseError('Held bootstrap version changed without qualification: ' + slug)
        if item['source']['type'] == 'git' and not hold:
            if slug not in rows:
                raise ReleaseError('Bootstrap source is retired, unregistered or missing: ' + slug)
            row = rows[slug]
            files = git_files(row, Path(repo), Path(standalone))
            entry = main_file(files, slug, item['main_file'])
            root, path = plugin_source(row, Path(repo), Path(standalone))
            version = lock_tool.read_header_version(root / path / entry)
            if not version:
                raise ReleaseError('Package source has no version: ' + slug)
            registry = read(Path(repo) / 'stack/manifests/stack-plugin-releases.json')
            registered = [r for r in registry['releases'] if r['slug'] == slug and r['version'] == version]
            if registered and registry_inputs:
                if len(registered) != 1:
                    raise ReleaseError('Ambiguous immutable plugin version: ' + slug)
                record = registered[0]
                archive = Path(registry_inputs) / str(relative(record['package']['path']))
                if (archive.is_file() and not archive.is_symlink()
                        and file_hash(archive) == record['package']['sha256']
                        and package_files(archive, slug) == files):
                    shutil.copyfile(archive, destination)
                    item.update(version=version, main_file=slug + '/' + entry,
                                sha256=file_hash(destination), size_bytes=destination.stat().st_size,
                                source={'type': 'git', 'repository': row['repository'],
                                        'commit': record['source']['git_commit']})
                    continue
            prior = Path(inputs) / str(relative(item['package']))
            known = prior.is_file() and file_hash(prior) == item['sha256']
            same = known and package_files(prior, slug) == files
            if item['version'] == version and not same:
                raise ReleaseError('Changed package source requires a version bump: ' + slug)
            if same and item['version'] == version:
                # Preserve the existing immutable version, provenance and ZIP.
                shutil.copyfile(prior, destination)
                continue
            fleet.deterministic_zip(destination, {slug + '/' + k: v for k, v in files.items()})
            item['version'] = version
            item['main_file'] = slug + '/' + entry
            item['source'] = {'type': 'git', 'repository': row['repository'],
                              'commit': git(root, 'rev-parse', 'HEAD')}
            item['sha256'], item['size_bytes'] = file_hash(destination), destination.stat().st_size
        else:
            source = Path(inputs) / str(relative(item['package']))
            if (file_hash(source) != item['sha256']
                    or source.stat().st_size != item['size_bytes']):
                raise ReleaseError('Qualified vendor/held input checksum differs: ' + slug)
            shutil.copyfile(source, destination)
    return packages


def prepare_metadata(repo, standalone, policy, inputs, job, selected, registry_inputs=None):
    """Generate metadata; runtime source stays byte-identical to accepted refs."""
    repo, standalone, job = Path(repo), Path(standalone), Path(job)
    catalog_path = repo / 'stack/manifests/component-catalog.json'
    catalog = read(catalog_path)
    old_lock = read(repo / 'stack/manifests/stack-release.lock.json')
    history = repo / 'stack/manifests/release-locks' / (old_lock['release_id'] + '.json')
    old_bytes = (repo / 'stack/manifests/stack-release.lock.json').read_bytes()
    if history.exists() and history.read_bytes() != old_bytes:
        raise ReleaseError('Historical release lock may not be replaced')
    if not history.exists():
        history.parent.mkdir(parents=True, exist_ok=True)
        history.write_bytes(old_bytes)
    epoch = max(int(git(repo if name == 'MRN' else standalone / name,
                        'show', '-s', '--format=%ct', sha))
                for name, sha in selected['refs'].items())
    date = dt.datetime.fromtimestamp(epoch, dt.timezone.utc).date().isoformat()
    release_id = date.replace('-', '.') + '-fleet-auto-' + selected['material_sha256'][:12]
    previous = {r['slug']: r for r in old_lock['components'] + old_lock['themes']}
    rows = {r['slug']: r for r in roster(repo)}
    for entry in catalog['components']:
        if entry['slug'] not in rows:
            continue
        row = rows[entry['slug']]
        if row['target_tier'] != 'platform-required' and row['slug'] in policy['held_defaults']:
            continue
        root, path = plugin_source(row, repo, standalone)
        source = root / path
        if row['runtime_type'] == 'mu-loader':
            version_path = source
        elif row['runtime_type'] == 'shared-runtime':
            version_path = source / (row['slug'] + '.php')
        else:
            files = git_files(row, repo, standalone)
            version_path = source / main_file(files, row['slug'])
        version = lock_tool.read_header_version(version_path)
        if not version:
            raise ReleaseError('Missing source version: ' + row['slug'])
        before = previous.get(row['slug'])
        current = next(u for u in selected['material']['units'] if u['slug'] == row['slug'])
        if (before and before['sha256'] != current['sha256']
                and before['version'] == version):
            raise ReleaseError('Changed source requires a version bump: ' + row['slug'])
        entry['version'] = version
    for theme in old_lock['themes']:
        current = next(u for u in selected['material']['units'] if u['slug'] == theme['slug'])
        version = lock_tool.read_header_version(repo / current['source_path'] / 'style.css')
        if current['sha256'] != theme['sha256'] and version == theme['version']:
            raise ReleaseError('Changed source requires a version bump: ' + theme['slug'])
    catalog['catalog_updated'] = date
    write(catalog_path, catalog)
    packages = default_packages(repo, standalone, policy, inputs, job / 'packages', registry_inputs)
    packages['release_id'] = release_id
    write(repo / 'stack/manifests/bootstrap-packages.lock.json', packages)
    version_path = repo / 'stack/STACK_VERSION.md'
    version_text = version_path.read_text()
    version_text = re.sub(r'^- Stack release: `[^`]+`',
                          f'- Stack release: `{release_id}`', version_text, flags=re.M)
    version_text = re.sub(r'^- Release date: `[^`]+`',
                          f'- Release date: `{date}`', version_text, flags=re.M)
    version_text = re.sub(r'^- Status: .*',
                          '- Status: `source snapshot; qualification/publication evidence establishes '
                          'Fleet readiness; site rollout separate`', version_text, flags=re.M)
    # Preserve the release history/runbook prose; regenerate only the current
    # included-component table instead of leaving old version text behind.
    versions = [(theme['slug'], lock_tool.read_header_version(
        repo / 'stack/themes' / theme['slug'] / 'style.css')) for theme in old_lock['themes']]
    versions += [(entry['slug'], entry['version']) for entry in catalog['components']
                 if entry.get('target_tier') == 'platform-required']
    versions += [(item['slug'], item['version']) for item in packages['plugins']
                 if item['source']['type'] == 'git'
                 and item['slug'] not in {name for name, _ in versions}]
    table = ('## Included MRN-Owned Components\n\n| Component | Version |\n| --- | --- |\n'
             + ''.join(f'| `{slug}` | `{version}` |\n' for slug, version in sorted(versions))
             + '\n')
    version_text = re.sub(r'## Included MRN-Owned Components\n.*?(?=## Stack Manifests)',
                          lambda _match: table, version_text, flags=re.S)
    version_path.write_text(version_text)
    changelog = repo / 'stack/CHANGELOG.md'
    text = changelog.read_text()
    entry = (f'## {release_id}\n\n- Automatic cumulative release from accepted source vector '
             f'`{selected["source_vector_sha256"]}`.\n'
             '- Matching platform, Fleet and bootstrap distributions; site rollout remains separate.\n\n')
    heading, separator, body = text.partition('\n')
    changelog.write_text(heading + separator + '\n' + entry + body.lstrip('\n'))
    return {'release_id': release_id, 'date': date, 'epoch': epoch, 'previous_lock': old_lock,
            'snapshot': selected}


def generate_lock(repo, standalone):
    repo = Path(repo)
    value = lock_tool.build_lock(repo, repo / 'stack/manifests/component-catalog.json',
                                repo / 'stack/STACK_VERSION.md',
                                repo / 'stack/manifests/themes.txt', Path(standalone))
    lock_tool.validate_lock(value)
    write(repo / 'stack/manifests/stack-release.lock.json', value)
    return value


def assemble_bootstrap(repo, platform, packages, output):
    repo, platform, packages, output = map(Path, (repo, platform, packages, output))
    output.mkdir()
    for name in ('mu-plugins', 'shared', 'themes'):
        shutil.copytree(platform / name, output / name)
    for theme in read(repo / 'stack/manifests/stack-release.lock.json')['themes']:
        slug = theme['slug']
        fleet.deterministic_zip(output / 'themes' / (slug + '.zip'),
                                {slug + '/' + n: p.read_bytes()
                                 for n, p in lock_tool.iter_digest_files(output / 'themes' / slug)})
    inputs = read(repo / 'stack/manifests/bootstrap-packages.lock.json')
    for item in inputs['plugins']:
        path = relative(item['package'])
        source = packages / str(path)
        if file_hash(source) != item['sha256'] or source.stat().st_size != item['size_bytes']:
            raise ReleaseError('Built bootstrap package changed: ' + item['slug'])
        target = output / 'packages' / str(path)
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(source, target)
    for name in BOOTSTRAP_FILES:
        target = output / str(relative(name))
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(repo / 'stack' / name, target)
    files = []
    for path in sorted(output.rglob('*')):
        if path.is_symlink():
            raise ReleaseError('Bootstrap payload contains a symlink')
        if not path.is_file():
            continue
        name = path.relative_to(output).as_posix()
        mode = 0o750 if name.endswith('.sh') else 0o644
        path.chmod(mode)
        files.append({'path': name, 'sha256': file_hash(path), 'size': path.stat().st_size,
                      'mode': mode})
    lock = read(repo / 'stack/manifests/stack-release.lock.json')
    manifest = {'schema_version': 1, 'release_id': lock['release_id'],
                'plugin_input_release_id': inputs['release_id'],
                'git_commit': git(repo, 'rev-parse', 'HEAD'),
                'release_lock_sha256': file_hash(repo / 'stack/manifests/stack-release.lock.json'),
                'files': files}
    write(output / 'bootstrap-bundle.json', manifest)
    (output / 'bootstrap-bundle.json').chmod(0o644)
    return manifest


def archive_tree(root, output):
    if Path(output).exists():
        raise ReleaseError('Immutable archive already exists')
    with tarfile.open(output, 'w', format=tarfile.USTAR_FORMAT) as archive:
        for path in sorted(Path(root).rglob('*')):
            if path.is_symlink():
                raise ReleaseError('Archive contains a symlink')
            if path.is_file():
                data = path.read_bytes()
                item = tarfile.TarInfo(path.relative_to(root).as_posix())
                item.size, item.mode, item.mtime = len(data), path.stat().st_mode & 0o777, 0
                archive.addfile(item, io.BytesIO(data))


def build_all(repo, standalone, job, suffix):
    repo, job = Path(repo), Path(job)
    platform = job / ('platform-' + suffix)
    bootstrap = job / ('bootstrap-' + suffix)
    lock_path = repo / 'stack/manifests/stack-release.lock.json'
    lock = read(lock_path)
    assembly.assemble(lock_path, repo, standalone, platform)
    package = fleet.build_release(lock_path, platform, job / ('fleet-' + suffix),
                                  lock['release_id'] + '-qualification')
    assemble_bootstrap(repo, platform, job / 'packages', bootstrap)
    archive_tree(bootstrap, job / ('bootstrap-' + suffix + '.tar'))
    archive = next((job / ('fleet-' + suffix)).glob('*.zip'))
    proof = distribution.verify(platform, bootstrap, archive)
    write(job / ('distribution-' + suffix + '.json'), proof)
    return {'platform': platform, 'bootstrap': bootstrap, 'fleet': archive,
            'bootstrap_archive': job / ('bootstrap-' + suffix + '.tar'), 'proof': proof}
