#!/usr/bin/env python3
"""Build a source-bound parent/plugin artifact without accessing WordPress.

This is packaging, not deployment or runtime qualification. The transport must
verify the trusted artifact checksum before staging and selecting a release.
"""
import argparse
import hashlib
import io
import json
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile
import zipfile

TOOLS = Path(__file__).resolve().parent
sys.path.insert(0, str(TOOLS.parent / 'site-deploy'))
from deploy import digest
from verify_release import safe_path


def allowed(name):
    # A component's scripts/ and vendor/ may contain required runtime code.
    # git archive honors the owner's export-ignore declarations; never reuse
    # the child-theme exporter's broader runtime exclusions here.
    parts = Path(name).parts
    return bool(parts) and not any(p.startswith('.') for p in parts) and parts[0] not in {
        'tests', 'docs', 'qa', 'node_modules', 'AGENTS.md', 'README.md',
        'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
        'phpunit.xml.dist', 'phpcs.xml.dist', 'playwright.config.js',
        'playwright.config.mjs', 'playwright.config.ts',
    }


def export(repo, commit, source, destination):
    if not re.fullmatch(r'[a-f0-9]{40}', commit):
        raise ValueError('An exact source commit is required')
    if source != '.':
        safe_path(source)
    tree = commit if source == '.' else commit + ':' + source
    body = subprocess.check_output(['git', '-C', str(repo), 'archive', '--format=tar', tree])
    with tarfile.open(fileobj=io.BytesIO(body)) as archive:
        for member in archive:
            if member.isdir() or not allowed(member.name):
                continue
            name = safe_path(member.name)
            if not member.isfile() or member.issym() or member.islnk():
                raise ValueError('Source contains an unsupported entry')
            target = destination / name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(archive.extractfile(member).read())


def build(repo, commit, source, slug, kind, entrypoint, output):
    if (not re.fullmatch(r'mrn-[a-z0-9-]+', slug)
            or slug == 'mrn-stack-deployment-agent'
            or kind not in ('parent-theme', 'standard-plugin')):
        raise ValueError('Unsupported component identity')
    if kind == 'parent-theme' and (slug != 'mrn-base-stack' or entrypoint != 'functions.php'):
        raise ValueError('Only the canonical parent theme is supported')
    safe_path(entrypoint)
    if '/' in entrypoint or not entrypoint.endswith('.php'):
        raise ValueError('Component entrypoint must be one root PHP file')
    output = Path(output)
    if output.exists() or output.with_suffix('.json').exists():
        raise ValueError('Refusing to overwrite an immutable artifact or receipt')
    with tempfile.TemporaryDirectory(prefix='mrn-component-build-') as temporary:
        root = Path(temporary)
        exported = root / 'source'
        exported.mkdir()
        export(repo, commit, source, exported)
        if not (exported / entrypoint).is_file():
            raise ValueError('Source entrypoint is missing')
        header = exported / ('style.css' if kind == 'parent-theme' else entrypoint)
        match = re.search(r'^[\s/*#]*Version:\s*([^\r\n*]+)', header.read_text(), re.M)
        if not match:
            raise ValueError('WordPress version header is missing')
        version = match.group(1).strip()
        built = root / 'built'
        # Reuse the locked, tested asset compiler. The child adapter itself is
        # omitted: shared components use the early, request-pinned runtime.
        subprocess.run(['node', str(TOOLS / 'build-assets.mjs'),
                        str(exported), str(built), slug, commit], check=True)
        generated = built / 'theme'
        (generated / 'mrn-release-assets.php').unlink()
        for path in exported.rglob('*'):
            if path.is_file():
                target = generated / path.relative_to(exported)
                if not target.exists():
                    target.parent.mkdir(parents=True, exist_ok=True)
                    shutil.copyfile(path, target)
        manifest_path = generated / 'mrn-assets.json'
        manifest = json.loads(manifest_path.read_text())
        manifest.update(scope=kind)
        if kind == 'parent-theme':
            # WP_Theme appends the stylesheet slug to theme_root_uri itself.
            # Keep that native convention without a mutable public alias.
            old_public = built / 'assets' / manifest['public_path']
            manifest['public_path'] = f"mrn-assets/{manifest['generation']}/{slug}"
            new_public = built / 'assets' / manifest['public_path']
            new_public.parent.mkdir(parents=True, exist_ok=True)
            old_public.rename(new_public)
        manifest_path.write_text(json.dumps(manifest, indent=2) + '\n')
        files = {}
        for directory, prefix in ((generated, f'component/{slug}'), (built / 'assets', 'assets')):
            if not directory.exists():
                continue
            for path in sorted(directory.rglob('*')):
                if path.is_symlink():
                    raise ValueError('Build contains a symlink')
                if path.is_file():
                    files[prefix + '/' + path.relative_to(directory).as_posix()] = path.read_bytes()
        inventory = {name: hashlib.sha256(body).hexdigest() for name, body in sorted(files.items())}
        release = {'schema': 1, 'kind': kind, 'slug': slug, 'entrypoint': entrypoint,
                   'source_sha': commit, 'source_path': source, 'version': version,
                   'files': inventory, 'tree_sha256': digest(inventory),
                   'manifest_sha256': inventory[f'component/{slug}/mrn-assets.json']}
        files['release.json'] = (json.dumps(release, sort_keys=True, indent=2) + '\n').encode()
        output.parent.mkdir(parents=True, exist_ok=True)
        with zipfile.ZipFile(output, 'x', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
            for name, body in sorted(files.items()):
                entry = zipfile.ZipInfo(name, (1980, 1, 1, 0, 0, 0))
                entry.create_system = 3
                entry.compress_type = zipfile.ZIP_DEFLATED
                entry.external_attr = 0o100644 << 16
                archive.writestr(entry, body)
        receipt = {key: value for key, value in release.items() if key != 'files'}
        receipt.update(artifact_sha256=hashlib.sha256(output.read_bytes()).hexdigest(),
                       package_bytes=output.stat().st_size, runtime_qualified=False)
        from verify import verify
        verify(output, receipt['artifact_sha256'], commit, source, slug, kind, entrypoint)
        output.with_suffix('.json').write_text(json.dumps(receipt, indent=2) + '\n')
        return receipt


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', type=Path, required=True)
    parser.add_argument('--commit', required=True)
    parser.add_argument('--source', default='.')
    parser.add_argument('--slug', required=True)
    parser.add_argument('--kind', choices=('parent-theme', 'standard-plugin'), required=True)
    parser.add_argument('--entrypoint', required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    print(json.dumps(build(args.repo, args.commit, args.source, args.slug,
                           args.kind, args.entrypoint, args.output), indent=2))
