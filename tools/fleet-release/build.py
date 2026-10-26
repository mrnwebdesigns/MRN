"""Deterministic source metadata and matching platform/Fleet/bootstrap builds."""
from __future__ import annotations

import copy
import datetime as dt
import io
import json
from pathlib import Path
import re
import shutil
import tempfile
import tarfile
import urllib.request
import zipfile

from common import (ReleaseError, assembly, canonical, digest, distribution,
                    file_hash, fleet, git, lock_tool, promotion, read, relative, roster,
                    source_files, write, run, clean_main)

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
            if not name.startswith(slug + '/') or name[len(slug) + 1:] in files:
                raise ReleaseError('Invalid plugin package member')
            if item.external_attr >> 16 & 0o170000 == 0o120000:
                raise ReleaseError('Plugin package contains a symlink')
            files[name[len(slug) + 1:]] = archive.read(item)
        return files


def plugin_payload(row, repo, standalone):
    """Use the component-owned recipe when committed files need generated assets."""
    slug = row['slug']
    if slug == 'mrn-mainwp-operations-api':
        root, path = plugin_source(row, Path(repo), Path(standalone))
        if row['repository'] != slug or path not in ('', '.'):
            raise ReleaseError('MainWP runtime recipe requires its standalone repository')
        if git(root, 'status', '--porcelain=v1', '--untracked-files=all'):
            raise ReleaseError('MainWP release source must be clean')
        commit = git(root, 'rev-parse', 'HEAD')
        version = lock_tool.read_header_version(root / (slug + '.php'))
        run(['python3', root / 'tools/package-release.py', '--ref', commit], cwd=root, timeout=300)
        archive = root / 'artifacts' / (slug + '-' + version + '-' + commit[:12] + '.zip')
        if archive.is_symlink() or not archive.is_file():
            raise ReleaseError('MainWP runtime archive is missing or unsafe')
        receipt = read(archive.with_suffix('.manifest.json'))
        files = package_files(archive, slug)
        required = {slug + '.php', 'child/code-files.php', 'child/code-storage.php',
                    'child/code-storage-qualify.php', 'vendor/mrn-jwt/JWT.php', 'vendor/mrn-jwt/LICENSE'}
        if (receipt.get('source_commit') != commit or receipt.get('version') != version
                or receipt.get('package') != archive.name
                or receipt.get('plugin_main_file') != slug + '/' + slug + '.php'
                or receipt.get('sha256') != file_hash(archive)
                or receipt.get('files') != {name: digest(data) for name, data in files.items()}
                or not required <= files.keys()
                or any(name not in {slug + '.php', 'README.md', 'LICENSE', 'LICENSE.md'}
                       and not name.startswith(('includes/', 'assets/', 'child/', 'vendor/mrn-jwt/'))
                       for name in files)
                or any((root / str(relative(name))).read_bytes() != data for name, data in files.items())):
            raise ReleaseError('MainWP runtime package differs from its committed recipe or source')
        if git(root, 'rev-parse', 'HEAD') != commit or git(
                root, 'status', '--porcelain=v1', '--untracked-files=all'):
            raise ReleaseError('MainWP release source changed during packaging')
        return files
    if slug == 'category-visibility':
        root, path = plugin_source(row, Path(repo), Path(standalone))
        if row['repository'] != slug or path not in ('', '.'):
            raise ReleaseError('Category Visibility recipe requires its standalone repository')
        if git(root, 'status', '--porcelain=v1', '--untracked-files=all'):
            raise ReleaseError('Category Visibility release source must be clean')
        commit = git(root, 'rev-parse', 'HEAD')
        version = lock_tool.read_header_version(root / 'category-visibility.php')
        run(['python3', root / 'scripts/package.py'], cwd=root, timeout=300)
        archive = root / 'build' / ('category-visibility-' + version + '.zip')
        if archive.is_symlink() or not archive.is_file():
            raise ReleaseError('Category Visibility runtime archive is missing or unsafe')
        files = package_files(archive, slug)
        source = git_files(row, Path(repo), Path(standalone))
        runtime_roots = {'category-visibility.php', 'uninstall.php', 'readme.txt',
                         'README.md', 'CHANGELOG.md', 'LICENSE'}
        if (not {'category-visibility.php', 'uninstall.php', 'readme.txt'} <= files.keys()
                or any(name not in runtime_roots and not name.startswith(
                    ('includes/', 'assets/', 'languages/', 'docs/')) for name in files)
                or any(source.get(name) != value for name, value in files.items())):
            raise ReleaseError('Category Visibility runtime payload differs from committed source')
        if git(root, 'rev-parse', 'HEAD') != commit or git(
                root, 'status', '--porcelain=v1', '--untracked-files=all'):
            raise ReleaseError('Category Visibility release source changed during packaging')
        return files
    if slug != 'mrn-recaptcha-enterprise-manager':
        return git_files(row, repo, standalone)
    root, path = plugin_source(row, Path(repo), Path(standalone))
    if row['repository'] != slug or path not in ('', '.'):
        raise ReleaseError('CAPTCHA release recipe requires its standalone repository')
    if git(root, 'status', '--porcelain=v1', '--untracked-files=all'):
        raise ReleaseError('CAPTCHA release source must be clean')
    commit = git(root, 'rev-parse', 'HEAD')
    # Install only the committed lock into this dedicated source mirror. The
    # component recipe verifies Node/esbuild and exports the exact Git commit;
    # ignored output in the owner's checkout never becomes a release input.
    run(['npm', 'ci', '--ignore-scripts', '--no-audit', '--no-fund'], cwd=root, timeout=900)
    with tempfile.TemporaryDirectory(prefix='mrn-captcha-package-') as temporary:
        run(['python3', root / 'tools/build-release.py', temporary], cwd=root, timeout=300)
        receipt = read(Path(temporary) / 'release-receipt.json')
        archive = Path(temporary) / str(relative(receipt['archive']))
        if (receipt['source_commit'] != commit
                or receipt['source_repository'] != 'mrnwebdesigns/' + slug
                or receipt['version'] != lock_tool.read_header_version(root / (slug + '.php'))
                or archive.is_symlink()
                or file_hash(archive) != receipt['sha256']
                or archive.stat().st_size != receipt['size_bytes']):
            raise ReleaseError('CAPTCHA generated package identity differs')
        files = package_files(archive, slug)
        if {name: digest(data) for name, data in files.items()} != receipt['files']:
            raise ReleaseError('CAPTCHA generated package receipt differs')
        manifest = read_payload_manifest(files, commit)
        if digest(files['assets/manifest.json']) != receipt['asset_manifest_sha256']:
            raise ReleaseError('CAPTCHA asset manifest receipt differs')
        for asset in manifest['assets'].values():
            for variant in ('source', 'minified'):
                entry = asset[variant]
                name = str(relative(entry['path']))
                if (not name.startswith('assets/generated/') or name not in files
                        or digest(files[name]) != entry['sha256']
                        or len(files[name]) != entry['bytes']):
                    raise ReleaseError('CAPTCHA generated asset differs')
        if git(root, 'rev-parse', 'HEAD') != commit or git(root, 'status', '--porcelain=v1', '--untracked-files=all'):
            raise ReleaseError('CAPTCHA release source changed during generation')
        return files


def read_payload_manifest(files, commit):
    manifest = json.loads(files['assets/manifest.json'])
    if (manifest.get('schema') != 1 or manifest.get('source_commit') != commit
            or manifest.get('component') != 'mrn-recaptcha-enterprise-manager'
            or set(manifest.get('assets', {})) != {'mrn-recaptcha-form-loader', 'mrn-recaptcha-comments'}):
        raise ReleaseError('CAPTCHA generated asset manifest identity differs')
    return manifest


def snapshot(repo, standalone, policy):
    """Record all participating merged refs plus the exact distributable vector."""
    repo, standalone = Path(repo), Path(standalone)
    rows = roster(repo)
    refs = {'MRN': git(repo, 'rev-parse', 'HEAD'),
            'MRN-qa-engine': clean_main(repo.parent / 'MRN-qa-engine')}
    units = []
    for row in rows:
        root, path = plugin_source(row, repo, standalone)
        refs[row['repository']] = git(root, 'rev-parse', 'HEAD')
        files = (plugin_payload(row, repo, standalone) if row['slug'] == 'mrn-mainwp-operations-api'
                 else git_files(row, repo, standalone))
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
    for name in git(repo, 'ls-files').splitlines():
        if (promotion.is_deployment_contract(name)
                or name.startswith(('stack/tests/', 'tools/fleet-release/'))):
            contracts[name] = file_hash(repo / name)
    vendor = read(repo / 'stack/manifests/bootstrap-packages.lock.json')
    packages = [{key: item.get(key) for key in ('slug', 'version', 'sha256', 'package', 'source')}
                for item in vendor['plugins']
                if item['source']['type'] != 'git' or item['slug'] in policy['held_defaults']]
    material = {'units': units, 'contracts': contracts, 'pinned_packages': packages,
                'qualification_tools': {'MRN-qa-engine': refs['MRN-qa-engine']}}
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
            files = plugin_payload(row, Path(repo), Path(standalone))
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
            if item['version'] == version and not prior.exists() and not prior.is_symlink():
                # A newly enrolled Git package need not exist in the historic
                # vendor input directory. Rebuild only the exact accepted lock;
                # unknown bytes and same-version source changes still fail.
                fleet.deterministic_zip(destination, {slug + '/' + k: v for k, v in files.items()})
                if (file_hash(destination) == item['sha256']
                        and destination.stat().st_size == item['size_bytes']):
                    continue
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
            if (not source.is_file() or file_hash(source) != item['sha256']) and registry_inputs:
                # Accepted licensed-input work stages its immutable ZIP here;
                # the manifest's checksum is the only lookup key. No credential
                # or mutable site/plugin checkout becomes a package input.
                source = Path(registry_inputs) / 'releases/vendor-inputs' / (item['sha256'] + '.zip')
            if not source.is_file() and item['source']['type'] == 'wordpress.org':
                url = item['source'].get('url', '')
                if not re.fullmatch(r'https://downloads\.wordpress\.org/plugin/[a-z0-9.-]+\.zip', url):
                    raise ReleaseError('Unsupported pinned WordPress package URL: ' + slug)
                with urllib.request.urlopen(url, timeout=60) as response:
                    if not response.geturl().startswith('https://downloads.wordpress.org/plugin/'):
                        raise ReleaseError('Pinned package redirected outside WordPress distribution')
                    data = response.read(128 * 1024**2 + 1)
                if len(data) > 128 * 1024**2 or digest(data) != item['sha256']:
                    raise ReleaseError('Downloaded pinned vendor input checksum differs: ' + slug)
                source = output / (slug + '.verified-input.zip')
                source.write_bytes(data)
            if (not source.is_file() or source.is_symlink()
                    or file_hash(source) != item['sha256']
                    or source.stat().st_size != item['size_bytes']):
                raise ReleaseError('Qualified vendor/held input checksum differs: ' + slug)
            shutil.copyfile(source, destination)
            if source.parent == output and source.name.endswith('.verified-input.zip'):
                source.unlink()
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
    epoch = max(int(git(repo if name == 'MRN' else repo.parent / name
                        if name == 'MRN-qa-engine' else standalone / name,
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
    bootstrap_doc = repo / 'stack/BOOTSTRAP_RELEASE.md'
    bootstrap_text = bootstrap_doc.read_text()
    bootstrap_text = re.sub(
        r'The Stack profile selects \*\*\d+ standard plugins\*\*\. The plain profile selects \*\*\d+\*\*',
        f'The Stack profile selects **{packages["stack_plugin_count"]} standard plugins**. '
        f'The plain profile selects **{packages["plain_plugin_count"]}**', bootstrap_text)
    bootstrap_text = re.sub(r'^Platform candidate: .*',
        'Platform candidate: `' + release_id + '`; plugin input bundle: `' + release_id
        + '`. Scope: qualified source installation inputs; site deployment remains separate.',
        bootstrap_text, flags=re.M)
    for item in packages['plugins']:
        pattern = r'^(\|.*`' + re.escape(item['slug']) + r'`.*?\| )[^|]+(\|)'
        bootstrap_text = re.sub(pattern, lambda match, version=item['version']:
                                match[1] + version + ' ' + match[2], bootstrap_text, flags=re.M)
    theme_versions = {item['slug']: lock_tool.read_header_version(
        repo / 'stack/themes' / item['slug'] / 'style.css') for item in old_lock['themes']}
    bootstrap_text = re.sub(r'^- Activate the MRN Base Stack Child .*',
        '- Activate the MRN Base Stack Child ' + theme_versions['mrn-base-stack-child']
        + ' over parent ' + theme_versions['mrn-base-stack'] + '. Child content remains site-owned.',
        bootstrap_text, flags=re.M)
    bootstrap_doc.write_text(bootstrap_text)
    catalog_doc = repo / 'stack/PLUGIN_CATALOG.md'
    catalog_text = catalog_doc.read_text()
    for item in catalog['components']:
        pattern = r'^(\| `' + re.escape(item['slug']) + r'` \| )[^|]+(\|)'
        catalog_text = re.sub(pattern, lambda match, version=item['version']:
                              match[1] + version + ' ' + match[2], catalog_text, flags=re.M)
    catalog_doc.write_text(catalog_text)
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
    # Optional/Dashboard components keep their own installation status. They
    # receive private qualified packages, never an implicit bootstrap install.
    additional = job / ('optional-' + suffix)
    additional.mkdir()
    catalog = read(repo / 'stack/manifests/component-catalog.json')
    registered = read(repo / 'stack/manifests/stack-plugin-releases.json')['releases']
    defaults = {r['slug'] for r in read(repo / 'stack/manifests/bootstrap-packages.lock.json')['plugins']}
    eligible = {r['slug'] for r in roster(repo)}
    records = []
    for entry in catalog['components']:
        if (entry['slug'] not in eligible or entry['runtime_type'] != 'standard-plugin'
                or entry['slug'] in defaults):
            continue
        matches = [r for r in registered if r['slug'] == entry['slug'] and r['version'] == entry['version']]
        if len(matches) != 1:
            raise ReleaseError('Non-bootstrap plugin lacks a unique qualified package: ' + entry['slug'])
        record = copy.deepcopy(matches[0])
        source = repo / str(relative(record['package']['path']))
        if file_hash(source) != record['package']['sha256']:
            raise ReleaseError('Additional plugin package differs: ' + entry['slug'])
        target = additional / record['package']['filename']
        shutil.copyfile(source, target)
        record['package']['path'] = target.name
        records.append(record)
    write(additional / 'manifest.json', {'release_id': lock['release_id'], 'plugins': records})
    archive_tree(additional, job / ('optional-' + suffix + '.tar'))
    return {'platform': platform, 'bootstrap': bootstrap, 'fleet': archive,
            'bootstrap_archive': job / ('bootstrap-' + suffix + '.tar'),
            'optional': additional, 'optional_archive': job / ('optional-' + suffix + '.tar'), 'proof': proof}
