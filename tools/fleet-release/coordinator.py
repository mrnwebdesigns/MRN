#!/usr/bin/env python3
"""Serialize accepted-source qualification/publication; never deploy a site."""
from __future__ import annotations

import argparse
import copy
import datetime as dt
import fcntl
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
import time

from build import (build_all, default_packages, generate_lock, git_files, main_file,
                   prepare_metadata, snapshot, package_files)
from common import (COMMIT, SHA256, ReleaseError, canonical, clean_main, digest,
                    file_hash, fleet, git, lock_tool, promotion, read,
                    repository_name, roster, run, write)
from qualify import qualify
from launcher import service_environment

REPOSITORY = 'mrnwebdesigns/MRN'
HELPER = '!gh auth git-credential'
CONFIG_KEYS = {'schema_version', 'state_root', 'qualified_packages', 'canonical_repo',
               'source_host', 'qualification', 'publish', 'report_failures'}


def config(path):
    path = Path(path)
    if path.is_symlink() or path.stat().st_uid != os.geteuid() or path.stat().st_mode & 0o022:
        raise ReleaseError('Controller configuration must be owner-controlled')
    value = read(path)
    if set(value) - CONFIG_KEYS or value.get('schema_version') != 1:
        raise ReleaseError('Unsupported source controller configuration')
    if value.get('source_host') != 'mrndev-stack-manager':
        raise ReleaseError('Source host must be the canonical Stack source owner')
    if not isinstance(value.get('publish'), bool) or not isinstance(value.get('report_failures'), bool):
        raise ReleaseError('Controller activation flags must be explicit booleans')
    for key in ('state_root', 'qualified_packages', 'canonical_repo'):
        directory = Path(value[key])
        if not directory.is_absolute() or directory.is_symlink():
            raise ReleaseError('Controller paths must be absolute regular directories')
    return value


def git_network(*arguments, cwd=None, timeout=600):
    # Use the same approved GitHub CLI account for discovery, source transfer,
    # source PRs and public release publication. No token extraction/fallback.
    return run(['git', '-c', 'credential.helper=', '-c', 'credential.helper=' + HELPER,
                *arguments], cwd=cwd, env={**os.environ, 'GIT_TERMINAL_PROMPT': '0'}, timeout=timeout)


def clone(name, destination):
    name = repository_name(name)
    git_network('clone', '--no-tags',
                'https://github.com/mrnwebdesigns/' + name + '.git', destination)
    git(destination, 'config', 'user.name', 'MRN Fleet Source Publisher')
    git(destination, 'config', 'user.email', 'fleet-source@mrnwebdesigns.com')
    return clean_main(destination)


def remote_refs(names):
    result = {}
    for name in names:
        output = git_network('ls-remote', '--symref', 'https://github.com/mrnwebdesigns/' + name + '.git', 'HEAD')
        lines = output.splitlines()
        if (len(lines) != 2 or not lines[0].startswith('ref: refs/heads/')
                or not COMMIT.fullmatch(lines[1].split()[0])):
            raise ReleaseError('Cannot resolve accepted default branch: ' + name)
        if name == 'MRN' and lines[0] != 'ref: refs/heads/main\tHEAD':
            raise ReleaseError('MRN default branch differs from the accepted main contract')
        result[name] = lines[1].split()[0]
    return result


def assert_refs(selected):
    if remote_refs(selected) != selected:
        raise ReleaseError('superseded: accepted source changed; queue a fresh qualification')


def staged_gate(repo, settings, evidence):
    command = str(Path(settings['qualification']['qa_engine']).with_name('mrn-qa-commit'))
    with Path(evidence).open('w') as output:
        result = subprocess.run([command, 'run', '--project-root', str(repo)],
                                stdout=output, stderr=subprocess.STDOUT, timeout=1800,
                                check=False)
    if result.returncode:
        raise ReleaseError('Derived metadata staged-snapshot QA failed')


def commit(repo, message, epoch, settings, evidence):
    env = {**os.environ, 'GIT_AUTHOR_DATE': str(epoch) + ' +0000',
           'GIT_COMMITTER_DATE': str(epoch) + ' +0000'}
    git(repo, 'add', '--', 'stack/manifests/component-catalog.json',
        'stack/manifests/bootstrap-packages.lock.json', 'stack/manifests/stack-plugin-releases.json',
        'stack/manifests/optional-plugin-releases.json',
        'stack/manifests/release-locks', 'stack/BOOTSTRAP_RELEASE.md', 'stack/STACK_VERSION.md', 'stack/CHANGELOG.md')
    if git(repo, 'diff', '--cached', '--name-only'):
        staged_gate(repo, settings, evidence)
        # Full source/runtime qualification and normal PR checks still precede
        # publication. The staged proof above is the normal commit gate.
        git(repo, 'commit', '-m', message, env=env)
    return git(repo, 'rev-parse', 'HEAD')


def prepare_registry(repo, standalone, job, registry_inputs=None):
    """Build every eligible standard plugin; retain immutable version records."""
    repo, standalone, job = Path(repo), Path(standalone), Path(job)
    path = repo / 'stack/manifests/stack-plugin-releases.json'
    registry = read(path)
    optional_path = repo / 'stack/manifests/optional-plugin-releases.json'
    optional = read(optional_path)
    packages = {row['slug']: row for row in read(
        repo / 'stack/manifests/bootstrap-packages.lock.json')['plugins']}
    source_rows = {row['slug']: row for row in roster(repo)}
    catalog = read(repo / 'stack/manifests/component-catalog.json')
    holds = read(Path(__file__).with_name('policy.json'))['held_defaults']
    for entry in catalog['components']:
        slug = entry['slug']
        if entry['runtime_type'] != 'standard-plugin' or slug not in source_rows or slug in holds:
            continue
        row = source_rows[slug]
        source = repo if row['repository'] == 'MRN' else standalone / row['repository']
        sha = git(source, 'rev-parse', 'HEAD')
        files = git_files(row, repo, standalone)
        tree, count = lock_tool.bytes_tree_sha256(files.items())
        version = entry['version']
        main = slug + '/' + main_file(files, slug)
        matches = [r for r in registry['releases'] if r['slug'] == slug and r['version'] == version]
        if len(matches) > 1:
            raise ReleaseError('Ambiguous immutable plugin release version: ' + slug)
        if matches and matches[0]['tree']['sha256'] != tree:
            raise ReleaseError('An immutable plugin version cannot be rebound to new source: ' + slug)
        # Old optional records predate the cumulative registry. Preserve their
        # qualified ZIP envelope only after comparing it with accepted source.
        old = matches[0] if matches else next((r for r in optional['releases']
            if r['slug'] == slug and r['version'] == version), None)
        package = packages.get(slug)
        if package:
            if package['source']['type'] != 'git' or package['version'] != version:
                raise ReleaseError('Default package does not match accepted plugin source: ' + slug)
            origin = job / 'packages' / package['package']
        if old:
            legacy = Path(old['package']['path'])
            choices = [legacy] if legacy.is_absolute() else [repo / legacy, Path(registry_inputs or repo) / legacy]
            if package:
                choices.append(origin)
            origin = next((p for p in choices if p.is_file() and not p.is_symlink()
                           and file_hash(p) == old['package']['sha256']), choices[0])
        elif not package:
            origin = job / 'additional-packages' / (slug + '.zip')
            origin.parent.mkdir(parents=True, exist_ok=True)
            fleet.deterministic_zip(origin, {slug + '/' + k: v for k, v in files.items()})
        if (not origin.is_file() or origin.is_symlink()
                or package_files(origin, slug) != files
                or (old and file_hash(origin) != old['package']['sha256'])):
            raise ReleaseError('Qualified immutable plugin input is missing or differs: ' + slug)
        filename = (Path(old['package']['path']).name if matches else
                    slug + '-' + version + '-' + sha[:12] + '.zip')
        relative_artifact = (old['package']['path'] if matches else 'releases/stack-plugins/' + filename)
        artifact = repo / relative_artifact
        artifact.parent.mkdir(parents=True, exist_ok=True)
        if artifact.exists() and file_hash(artifact) != file_hash(origin):
            raise ReleaseError('Historical plugin artifact changed: ' + slug)
        if not artifact.exists():
            shutil.copyfile(origin, artifact)
        record = old if matches else {
            'slug': slug, 'version': version, 'runtime_type': 'standard-plugin',
            'target_tier': entry['target_tier'], 'current_distribution': entry['current_distribution'],
            'source': {'repository': 'mrnwebdesigns/' + row['repository'],
                       'path': entry['source']['path'], 'git_commit': sha},
            'package': {'path': relative_artifact, 'filename': filename,
                        'main_file': main, 'size_bytes': artifact.stat().st_size,
                        'sha256': file_hash(artifact)},
            'tree': {'hash_algorithm': 'sha256-tree-v1', 'sha256': tree, 'file_count': count},
            'update_policy': {'mode': 'upgrade-only', 'preserve_active_state': True,
                              'new_install': 'requires-separate-owner-authorization-and-plan'},
        }
        if not matches:
            registry['releases'].append(record)
        if entry['target_tier'] != 'platform-required':
            optional['releases'] = [r for r in optional['releases'] if r['slug'] != slug] + [record]
    for value, target in ((registry, path), (optional, optional_path)):
        value['catalog_updated'] = catalog['catalog_updated']
        write(target, value)


def propose(repo, job, metadata):
    branch = 'codex/fleet-promotion-' + metadata['snapshot']['material_sha256'][:12]
    marker = 'MRN_FLEET_SOURCE_VECTOR=' + metadata['snapshot']['source_vector_sha256']
    head = git(repo, 'rev-parse', 'HEAD')
    existing = json.loads(run(['gh', 'pr', 'list', '--repo', REPOSITORY, '--head', branch,
                              '--state', 'all', '--json', 'number,state,body,headRefOid']))
    if existing:
        if len(existing) != 1 or marker not in existing[0]['body'] or existing[0]['headRefOid'] != head:
            raise ReleaseError('Promotion branch ownership/source identity differs')
        return existing[0]['number']
    git_network('push', 'origin', head + ':refs/heads/' + branch, cwd=repo)
    body = Path(job) / 'promotion-pr.md'
    body.write_text('Automatic cumulative Fleet source promotion from accepted merged source.\n\n'
                    + 'Qualification, deterministic rebuilds and distribution parity passed. '
                    + 'Publication and installed site adoption are separate states; this PR authorizes no site write.\n\n'
                    + '`' + marker + '`\n\n'
                    + 'Release: `' + metadata['release_id'] + '`. Evidence is retained in the private source release store.\n')
    url = run(['gh', 'pr', 'create', '--repo', REPOSITORY, '--base', 'main', '--head', branch,
               '--title', 'Automatic Fleet source promotion ' + metadata['release_id'], '--body-file', body])
    return int(url.rsplit('/', 1)[1])


def accept_proposal(repo, number, selected, *, timeout=1800):
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline:
        payload = json.loads(run(['gh', 'pr', 'view', str(number), '--repo', REPOSITORY,
                                  '--json', 'state,mergeCommit,headRefOid,mergeStateStatus,statusCheckRollup']))
        if payload['state'] == 'MERGED':
            return payload['mergeCommit']['oid']
        if payload['state'] != 'OPEN':
            raise ReleaseError('Promotion PR closed before acceptance')
        assert_refs(selected)
        checks = payload['statusCheckRollup']
        if checks and all(v.get('status') == 'COMPLETED' for v in checks):
            if any(v.get('conclusion') not in ('SUCCESS', 'SKIPPED', 'NEUTRAL') for v in checks):
                raise ReleaseError('Normal promotion PR checks failed')
            if not any(v.get('name') == 'Code gate' and v.get('conclusion') == 'SUCCESS' for v in checks):
                raise ReleaseError('Required Code gate proof is missing')
            run(['gh', 'pr', 'merge', str(number), '--repo', REPOSITORY, '--merge',
                 '--match-head-commit', payload['headRefOid']])
            continue
        time.sleep(20)
    raise ReleaseError('Promotion PR gates remain pending')


def remote_host(settings, operation, *arguments):
    command = ['ssh', '-o', 'BatchMode=yes', settings['source_host'], 'python3', '-', operation]
    # Every remote argument has a fixed lexical form; no shell text or secrets.
    for value in arguments:
        if not re.fullmatch(r'[a-zA-Z0-9.-]+', str(value)):
            raise ReleaseError('Unsafe source-host argument')
        command.append(str(value))
    result = subprocess.run(command, input=Path(__file__).with_name('host.py').read_text(),
                            text=True, capture_output=True, timeout=600, check=False)
    try:
        payload = json.loads(result.stdout)
    except json.JSONDecodeError as error:
        raise ReleaseError('Source-host outcome unknown; re-read receipt before retry') from error
    if result.returncode:
        raise ReleaseError(payload.get('error', 'Source-host publication blocked'))
    return payload


def publish(settings, repo, job, built, qualified, metadata, merged):
    saved = remote_host(settings, 'receipt', metadata['release_id'])
    if saved['status'] == 'not_started':
        assert_refs({**metadata['snapshot']['refs'], 'MRN': merged})
    preflight = remote_host(settings, 'preflight')
    release = metadata['release_id']
    # Immutable depot paths are fixed under the exact source-owner home. No
    # WordPress site directories, secrets, provider settings or sudo routes.
    base = '/home/mrndev-stack-manager/stack-release-artifacts/' + release
    run(['ssh', '-o', 'BatchMode=yes', settings['source_host'],
         'mkdir', '-m', '700', '-p', base])
    transfer(settings, base, release, built['bootstrap_archive'], 'bootstrap.tar')
    manifest_sha = file_hash(built['bootstrap'] / 'bootstrap-bundle.json')
    archive_sha = file_hash(built['bootstrap_archive'])
    remote_host(settings, 'stage', release, archive_sha, manifest_sha)
    # Private artifact distribution includes Fleet bytes and qualification. No
    # licensed bootstrap/Fleet payload is uploaded to the public GitHub repo.
    write(Path(job) / 'qualification.json', qualified)
    transfer(settings, base, release, built['fleet'], Path(built['fleet']).name)
    transfer(settings, base, release, built['optional_archive'], 'optional-plugins.tar')
    transfer(settings, base, release, Path(job) / 'qualification.json', 'qualification.json')
    remote_host(settings, 'verify-artifact', release, Path(built['fleet']).name, file_hash(built['fleet']))
    remote_host(settings, 'verify-artifact', release, 'optional-plugins.tar', file_hash(built['optional_archive']))
    remote_host(settings, 'verify-artifact', release, 'qualification.json', file_hash(Path(job) / 'qualification.json'))
    parent = next(v for v in read(Path(repo) / 'stack/manifests/stack-release.lock.json')['themes']
                  if v['slug'] == 'mrn-base-stack')
    public_zip = Path(job) / ('mrn-base-stack-' + parent['version'] + '.zip')
    shutil.copyfile(built['bootstrap'] / 'themes/mrn-base-stack.zip', public_zip)
    public_lock = Path(job) / 'stack-release.lock.json'
    shutil.copyfile(Path(repo) / 'stack/manifests/stack-release.lock.json', public_lock)
    public_files = [public_zip, public_lock]
    checksums = Path(job) / 'SHA256SUMS.txt'
    checksums.write_text(''.join(file_hash(v) + '  ' + v.name + '\n' for v in public_files))
    public_files.append(checksums)
    notes = Path(job) / 'release-notes.md'
    notes.write_text('Qualified automatic Fleet source release. Matching private Fleet/bootstrap '
                     'distributions passed readback. Site rollout remains explicitly initiated.\n\n'
                     'Parent: ' + parent['version'] + '. Source: ' + merged + '.\n')
    existing = subprocess.run(['gh', 'release', 'view', release, '--repo', REPOSITORY,
                               '--json', 'tagName,assets,isDraft'], text=True,
                              capture_output=True, check=False)
    if existing.returncode:
        run(['gh', 'release', 'create', release, *public_files, '--repo', REPOSITORY,
             '--target', merged, '--title', release, '--notes-file', notes, '--latest=false'])
    else:
        record = json.loads(existing.stdout)
        if record['isDraft'] or {v['name'] for v in record['assets']} != {p.name for p in public_files}:
            raise ReleaseError('Existing public release asset set differs')
    tag = run(['gh', 'api', 'repos/' + REPOSITORY + '/git/ref/tags/' + release])
    if json.loads(tag)['object']['sha'] != merged:
        raise ReleaseError('Public release tag differs from qualified merged source')
    destination = Path(job) / 'public-readback'
    destination.mkdir(exist_ok=True)
    run(['gh', 'release', 'download', release, '--repo', REPOSITORY, '--dir', destination,
         '--pattern', public_zip.name, '--pattern', public_lock.name, '--pattern', checksums.name,
         '--clobber'], timeout=600)
    for path in public_files:
        if file_hash(path) != file_hash(destination / path.name):
            raise ReleaseError('Public source artifact readback differs')
    if saved['status'] == 'not_started':
        assert_refs({**metadata['snapshot']['refs'], 'MRN': merged})
    receipt = remote_host(settings, 'publish', release, archive_sha, manifest_sha,
                          preflight['manifest_sha256'])
    if receipt['status'] != 'published_verified' or receipt['lock_sha256'] != built['proof']['lock_sha256']:
        raise ReleaseError('Hosted publication readback differs from qualified lock')
    run(['gh', 'release', 'edit', release, '--repo', REPOSITORY, '--latest'])
    write(Path(job) / 'publication.json', {'status': 'pass', 'hosted': receipt,
                                        'github_release': release,
                                        'public_checksums': {v.name: file_hash(v) for v in public_files},
                                        'site_writes': False})
    return receipt


def transfer(settings, base, release, source, filename):
    checksum = file_hash(source)
    # Repeating an interrupted transfer can change only its temporary file.
    # The host refuses replacing an immutable destination even after a crash.
    run(['scp', '-q', source, settings['source_host'] + ':' + base + '/' + filename
         + '.incoming-' + checksum], timeout=900)
    remote_host(settings, 'accept-artifact', release, filename, checksum)


def install_local_index(settings, repo, standalone, job, built, qualified, publication):
    """Owner-local immutable source map for the existing explicit Fleet command."""
    repo, standalone, job = Path(repo), Path(standalone), Path(job)
    catalog = read(repo / 'stack/manifests/component-catalog.json')
    registry = read(repo / 'stack/manifests/stack-plugin-releases.json')
    optional = read(repo / 'stack/manifests/optional-plugin-releases.json')
    eligible = {row['slug'] for row in roster(repo)}
    holds = qualified.get('held_defaults', {})
    # The tracked registry/history is retained exactly. The current qualified
    # input map must not expose a newer held version as an upgrade target.
    registry['releases'] = [row for row in registry['releases']
                            if row['slug'] not in holds or row['version'] == holds[row['slug']]['version']]
    for row in registry['releases']:
        name = repository_name(row['source']['repository'])
        mirror = standalone / name
        if mirror.is_dir():
            row['source']['path'] = str(mirror)
        artifact = repo / row['package']['path'] if not Path(row['package']['path']).is_absolute() else Path(row['package']['path'])
        if not artifact.is_file() and not Path(row['package']['path']).is_absolute():
            historical = Path(settings['canonical_repo']) / row['package']['path']
            if historical.is_file() and not historical.is_symlink() and file_hash(historical) == row['package']['sha256']:
                artifact.parent.mkdir(parents=True, exist_ok=True)
                shutil.copyfile(historical, artifact)
        if artifact.is_file():
            if file_hash(artifact) != row['package']['sha256']:
                raise ReleaseError('Published plugin artifact differs from its immutable registration')
            registered_path = Path(row['package']['path'])
            if not registered_path.is_absolute():
                if registered_path.parts[:2] not in (('releases', 'stack-plugins'), ('releases', 'plugins')):
                    raise ReleaseError('Registered plugin cache must stay in the ignored release store')
                cached = Path(settings['canonical_repo']) / registered_path
                cached.parent.mkdir(parents=True, exist_ok=True)
                if cached.exists() and (cached.is_symlink() or file_hash(cached) != row['package']['sha256']):
                    raise ReleaseError('Immutable local plugin cache differs; preserve and inspect it')
                if not cached.exists():
                    shutil.copyfile(artifact, cached)
            row['package']['path'] = str(artifact)
    records = {(row['slug'], row['version']): row for row in registry['releases']}
    optional['releases'] = [copy.deepcopy(records[(row['slug'], row['version'])])
        for row in optional['releases'] if row['slug'] in eligible
        and (row['slug'], row['version']) in records]
    for row in optional['releases']:
        # The current proof was run on these clean accepted mirrors. Preserve
        # immutable packaging provenance separately from the current source
        # checkpoint required by the existing optional-plan validator.
        mirror = standalone / repository_name(row['source']['repository'])
        row['packaging_source'] = copy.deepcopy(row['source'])
        row['source']['path'] = str(mirror)
        row['source']['git_commit'] = clean_main(mirror)
    control = job / 'published-control'
    write(control / 'component-catalog.json', catalog)
    write(control / 'stack-plugin-releases.json', registry)
    write(control / 'optional-plugin-releases.json', optional)
    write(control / 'qualification.json', qualified)
    write(control / 'publication.json', publication)
    index = {'schema_version': 1, 'status': 'fleet_ready', 'release_id': built['proof']['release_id'],
             'lock_sha256': built['proof']['lock_sha256'],
             'source_vector_sha256': qualified['source_vector_sha256'],
             'catalog_path': str(control / 'component-catalog.json'),
             'catalog_sha256': file_hash(control / 'component-catalog.json'),
             'registry_path': str(control / 'stack-plugin-releases.json'),
             'registry_sha256': file_hash(control / 'stack-plugin-releases.json'),
             'optional_registry_path': str(control / 'optional-plugin-releases.json'),
             'optional_registry_sha256': file_hash(control / 'optional-plugin-releases.json'),
             'qualification_path': str(control / 'qualification.json'),
             'qualification_sha256': file_hash(control / 'qualification.json'),
             'publication_path': str(control / 'publication.json'),
             'publication_sha256': file_hash(control / 'publication.json'),
             'artifact_root': str(repo), 'site_adoption_verified': False, 'site_writes': False}
    index['held_defaults'] = holds
    write(Path(settings['canonical_repo']) / 'releases/fleet-ready/current.json', index)
    return index


def paths(value):
    if isinstance(value, Path):
        return str(value)
    if isinstance(value, dict):
        return {k: paths(v) for k, v in value.items()}
    if isinstance(value, list):
        return [paths(v) for v in value]
    return value


def seal(job, value):
    """Resume only checksum-bound completed phases; never repeat uncertain writes."""
    files = [Path(value['built'][key]) for key in ('fleet', 'bootstrap_archive', 'optional_archive')]
    files += [Path(value['built']['bootstrap']) / 'bootstrap-bundle.json',
              Path(value['repo']) / 'stack/manifests/stack-release.lock.json']
    value['sealed'] = {str(p): file_hash(p) for p in files}
    write(Path(job) / 'progress.json', paths(value))


def verify_seal(value):
    if not value.get('sealed'):
        raise ReleaseError('Completed source phase has no evidence seal')
    for name, checksum in value['sealed'].items():
        if not Path(name).is_file() or file_hash(name) != checksum:
            raise ReleaseError('Completed source phase evidence changed')
    proof, built = value['qualified'], value['built']
    if (proof.get('status') != 'pass' or proof['fleet_sha256'] != file_hash(built['fleet'])
            or proof['bootstrap_sha256'] != file_hash(built['bootstrap_archive'])
            or proof['optional_sha256'] != file_hash(built['optional_archive'])
            or proof['lock_sha256'] != built['proof']['lock_sha256']):
        raise ReleaseError('Completed qualification binding differs')


def link_mirrors(repo, standalone):
    # These links belong only to the clean controller mirrors. They are ignored
    # by MRN; never point a QA run at the owner's mutable plugin checkout.
    for row in roster(repo):
        if row['repository'] != 'MRN':
            link = Path(repo) / 'plugins' / row['slug']
            link.parent.mkdir(exist_ok=True)
            target = standalone / row['repository'] / row['relative_source']
            existing = link.is_symlink() and link.resolve() == target.resolve()
            if (link.exists() or link.is_symlink()) and not existing:
                raise ReleaseError('Existing plugin source link is not the dedicated mirror: ' + row['slug'])
            # Exact tooling links are local clone state. Exclude only the new
            # untracked link; never suppress a tracked change or unknown file.
            exclude = Path(git(repo, 'rev-parse', '--git-path', 'info/exclude'))
            if not exclude.is_absolute():
                exclude = Path(repo) / exclude
            exclude.parent.mkdir(parents=True, exist_ok=True)
            with exclude.open('a') as stream:
                stream.write('\n/plugins/' + row['slug'] + '\n')
            if not existing:
                link.symlink_to(target, target_is_directory=True)


def dependencies(repo, standalone, settings):
    link_mirrors(repo, standalone)
    if (Path(repo) / 'composer.lock').exists():
        run([settings['qualification']['composer'], 'install', '--no-interaction', '--prefer-dist',
             '--no-progress'], cwd=repo, timeout=900)
    engine = Path(repo).parent / 'MRN-qa-engine'
    git_network('clone', '--no-tags', 'https://github.com/mrnwebdesigns/MRN-qa-engine.git', engine)
    clean_main(engine)
    run(['npm', 'ci', '--ignore-scripts', '--no-audit', '--no-fund'], cwd=engine, timeout=900)
    settings['qualification'] = {**settings['qualification'], 'qa_engine': str(engine / 'bin/mrn-qa'),
                                'qa_engine_root': str(engine)}


def once(settings):
    os.umask(0o077)
    state = Path(settings['state_root'])
    state.mkdir(mode=0o700, parents=True, exist_ok=True)
    if state.stat().st_uid != os.geteuid() or state.stat().st_mode & 0o077:
        raise ReleaseError('Source controller state must be private and owner-controlled')
    lock = state / 'coordinator.lock'
    if lock.is_symlink():
        raise ReleaseError('Invalid coordinator lease')
    with lock.open('a') as lease:
        try:
            fcntl.flock(lease, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            return {'status': 'busy', 'site_writes': False}
        active = state / 'active.json'
        if active.exists():
            progress_file = Path(read(active)['job']) / 'progress.json'
            if not progress_file.resolve().is_relative_to((state / 'jobs').resolve()):
                raise ReleaseError('Invalid active source job')
            progress = read(progress_file)
            verify_seal(progress)
            job = progress_file.parent
        else:
            catalog_checkout = state / 'discovery/MRN'
            if not catalog_checkout.exists():
                catalog_checkout.parent.mkdir(parents=True, exist_ok=True)
                clone('MRN', catalog_checkout)
            else:
                if git(catalog_checkout, 'status', '--porcelain'):
                    raise ReleaseError('Discovery mirror is dirty; preserve and inspect it')
                git_network('fetch', 'origin', 'main', cwd=catalog_checkout)
                git(catalog_checkout, 'checkout', '--detach', 'origin/main')
            names = sorted({'MRN'} | {row['repository'] for row in roster(catalog_checkout)})
            refs = remote_refs(names)
            previous = read(state / 'status.json') if (state / 'status.json').exists() else {}
            if previous.get('observed_refs') == refs and previous.get('status') in ('fleet_ready', 'no_change'):
                return previous
            # Failed qualification is safe to retry in a fresh attempt. Preserve
            # diagnostics and every prior candidate; a failed attempt never wrote.
            vector = digest(canonical(refs))
            failures = list((state / 'jobs').glob(vector[:20] + '-*/failure.json'))
            if failures and time.time() - max(p.stat().st_mtime for p in failures) < 1800:
                return {'status': 'qualification_backoff', 'site_writes': False}
            job = state / 'jobs' / (vector[:20] + '-' + str(time.time_ns()))
            job.mkdir(parents=True)
            repo = job / 'repos/MRN'
            standalone = job / 'repos/MRN-plugins'
            standalone.mkdir(parents=True)
            try:
                clone('MRN', repo)
                for name in names:
                    if name != 'MRN':
                        clone(name, standalone / name)
                dependencies(repo, standalone, settings)
                policy = read(Path(__file__).with_name('policy.json'))
                selected = snapshot(repo, standalone, policy)
                if selected['refs'] != refs:
                    raise ReleaseError('superseded: source moved during checkout')
                if previous.get('material_sha256') == selected['material_sha256']:
                    value = {**previous, 'status': 'no_change', 'observed_refs': refs, 'site_writes': False}
                    write(state / 'status.json', value)
                    return value
                write(job / 'selected-source.json', selected)
                metadata = prepare_metadata(repo, standalone, policy, settings['qualified_packages'],
                                            job, selected, settings['canonical_repo'])
                prepare_registry(repo, standalone, job, settings['canonical_repo'])
                commit(repo, 'Reconcile cumulative Fleet source metadata ' + metadata['release_id'],
                       metadata['epoch'] + 1, settings, job / 'metadata-commit-qa.log')
                generate_lock(repo, standalone)
                env = {**os.environ, 'GIT_AUTHOR_DATE': str(metadata['epoch'] + 2) + ' +0000',
                       'GIT_COMMITTER_DATE': str(metadata['epoch'] + 2) + ' +0000'}
                git(repo, 'add', 'stack/manifests/stack-release.lock.json')
                staged_gate(repo, settings, job / 'lock-commit-qa.log')
                git(repo, 'commit', '-m', 'Lock automatic Fleet source ' + metadata['release_id'], env=env)
                built = build_all(repo, standalone, job, 'a')
                rebuilt = build_all(repo, standalone, job, 'b')
                deterministic(built, rebuilt)
                qualified = qualify(repo, standalone, built, settings['qualification'], job / 'evidence', selected)
                assert_refs(refs)
                progress = paths({'phase': 'qualified', 'metadata': metadata, 'qualified': qualified,
                                  'built': built, 'repo': repo, 'standalone': standalone})
                seal(job, progress)
                write(active, {'job': str(job)})
            except Exception as error:
                write(job / 'failure.json', {'error': str(error), 'site_writes': False})
                raise
        repo, standalone = Path(progress['repo']), Path(progress['standalone'])
        engine = repo.parent / 'MRN-qa-engine'
        settings['qualification'] = {**settings['qualification'], 'qa_engine': str(engine / 'bin/mrn-qa'),
                                    'qa_engine_root': str(engine)}
        metadata = progress['metadata']
        refs = metadata['snapshot']['refs']
        if not settings['publish']:
            return {'status': 'qualified_rehearsal', 'job': str(job), 'site_writes': False}
        if progress['phase'] == 'qualified':
            assert_refs(refs)
            progress['pr'] = propose(repo, job, metadata)
            progress['phase'] = 'proposed'
            seal(job, progress)
        if progress['phase'] == 'proposed':
            progress['merged'] = accept_proposal(repo, progress['pr'], refs)
            progress['phase'] = 'merged'
            seal(job, progress)
        if progress['phase'] == 'merged':
            merged = progress['merged']
            assert_refs({**refs, 'MRN': merged})
            git_network('fetch', 'origin', 'main', cwd=repo)
            git(repo, 'checkout', '--detach', merged)
            clean_main(repo)
            report = promotion.evaluate_candidate(repo, metadata['previous_lock'],
                read(repo / 'stack/manifests/stack-release.lock.json'),
                read(repo / 'stack/manifests/component-catalog.json'), standalone)
            write(job / 'promotion.json', report)
            if report['status'] != 'pass':
                raise ReleaseError('Merged candidate reconciliation failed')
            # Final artifacts and evidence come from clean merged main. Retain
            # pre-merge proof; never overwrite a qualified candidate's bytes.
            selected = snapshot(repo, standalone, read(Path(__file__).with_name('policy.json')))
            attempt = job / ('merged-' + str(time.time_ns()))
            attempt.mkdir()
            (attempt / 'packages').symlink_to(job / 'packages', target_is_directory=True)
            built = build_all(repo, standalone, attempt, 'a')
            rebuilt = build_all(repo, standalone, attempt, 'b')
            deterministic(built, rebuilt)
            qualified = qualify(repo, standalone, built, settings['qualification'], attempt / 'evidence', selected)
            progress.update(phase='ready', built=paths(built), qualified=qualified)
            metadata['snapshot'] = selected
            seal(job, progress)
        if progress['phase'] == 'ready':
            publish(settings, repo, job, progress['built'], progress['qualified'], metadata, progress['merged'])
            progress['phase'] = 'published'
            seal(job, progress)
        publication = read(job / 'publication.json')
        install_local_index(settings, repo, standalone, job, progress['built'], progress['qualified'], publication)
        value = {'status': 'fleet_ready', 'release_id': metadata['release_id'],
                 'material_sha256': metadata['snapshot']['material_sha256'],
                 'observed_refs': metadata['snapshot']['refs'], 'pr': progress['pr'],
                 'job': str(job), 'lock_sha256': progress['built']['proof']['lock_sha256'],
                 'site_writes': False, 'site_adoption_verified': False}
        write(state / 'status.json', value)
        active.unlink()
        return value


def deterministic(first, second):
    if any(file_hash(first[key]) != file_hash(second[key])
           for key in ('fleet', 'bootstrap_archive', 'optional_archive')):
        raise ReleaseError('Independent deterministic rebuilds differ')


def failure(settings, error):
    state = Path(settings['state_root'])
    status = {'status': 'blocked', 'error': str(error), 'site_writes': False,
              'observed_at_utc': dt.datetime.now(dt.timezone.utc).isoformat()}
    active = state / 'active.json'
    if active.exists():
        job = Path(read(active)['job'])
        status['evidence'] = str(job)
        if str(error).startswith('superseded:'):
            progress = read(job / 'progress.json')
            verify_seal(progress)
            # A superseded accepted snapshot is read-only. Never abandon an
            # unknown publication; its host receipt must be reconciled first.
            safe = progress['phase'] in ('qualified', 'merged')
            if progress['phase'] == 'proposed':
                proposal = json.loads(run(['gh', 'pr', 'view', str(progress['pr']), '--repo', REPOSITORY,
                    '--json', 'state,headRefOid,body']))
                marker = 'MRN_FLEET_SOURCE_VECTOR=' + progress['metadata']['snapshot']['source_vector_sha256']
                if (proposal['headRefOid'] != git(progress['repo'], 'rev-parse', 'HEAD')
                        or marker not in proposal['body']):
                    raise ReleaseError('Superseded promotion ownership differs; preserve it for review')
                if proposal['state'] == 'OPEN':
                    # Only the worker's exact obsolete proposal is closed. Its
                    # branch, artifacts and evidence remain recovery history.
                    run(['gh', 'pr', 'close', str(progress['pr']), '--repo', REPOSITORY])
                safe = True
            if progress['phase'] == 'ready':
                safe = remote_host(settings, 'receipt', progress['metadata']['release_id'])['status'] == 'not_started'
            if safe:
                write(job / 'superseded.json', status)
                active.unlink()
    write(state / 'failure.json', status)
    signature = digest(canonical({'error': status['error'], 'evidence': status.get('evidence', '')}))
    marker = state / 'last-failure-notification.json'
    previous = read(marker) if marker.exists() else {}
    if settings['report_failures'] and previous.get('signature') != signature:
        if sys.platform == 'darwin':
            message = 'Source release blocked: ' + status['error'] + '. Evidence: ' + str(state / 'failure.json')
            subprocess.run(['osascript', '-e', 'display notification ' + json.dumps(message)
                            + ' with title "MRN Fleet source"'], capture_output=True, check=False, timeout=20)
        write(marker, {'signature': signature, 'status': status})
    return status


def main():
    environment = service_environment(os.environ)
    os.environ.clear()
    os.environ.update(environment)
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config', type=Path, required=True)
    args = parser.parse_args()
    settings = config(args.config)
    try:
        result = once(settings)
        print(json.dumps(result, sort_keys=True))
        return 0
    except (ReleaseError, OSError, ValueError, KeyError, subprocess.TimeoutExpired) as error:
        # Persist actionable status. Logs/evidence remain private; source checks
        # do not generate a site approval request or attempt credential fallback.
        status = failure(settings, error)
        print(json.dumps(status, sort_keys=True))
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
