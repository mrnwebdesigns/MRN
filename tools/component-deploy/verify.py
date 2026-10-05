#!/usr/bin/env python3
"""Verify a component artifact against a separately trusted checksum/identity."""
import argparse
import hashlib
import json
from pathlib import Path, PurePosixPath
import re
import stat
import sys
import zipfile

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / 'site-deploy'))
from deploy import check, digest
from verify_release import (safe_path, parse_json, sha256_file, MAX_ARCHIVE_BYTES,
                            MAX_FILE_BYTES, MAX_FILES, STATIC_EXTENSIONS)


def verify(archive_path, expected_sha256, source_sha, source_path, slug, kind, entrypoint):
    check(expected_sha256, r'[a-f0-9]{64}', 'artifact checksum')
    check(source_sha, r'[a-f0-9]{40}', 'source commit')
    check(slug, r'mrn-[a-z0-9-]+', 'component slug')
    check(entrypoint, r'[A-Za-z0-9_-]+\.php', 'entrypoint')
    if (kind not in ('parent-theme', 'standard-plugin') or slug == 'mrn-stack-deployment-agent'
            or (kind == 'parent-theme' and (slug != 'mrn-base-stack' or entrypoint != 'functions.php'))):
        raise ValueError('Unsupported component identity')
    if source_path != '.':
        safe_path(source_path)
    archive_path = Path(archive_path)
    if archive_path.is_symlink() or not archive_path.is_file() or archive_path.stat().st_size > MAX_ARCHIVE_BYTES:
        raise ValueError('Archive must be a bounded regular file')
    files, sizes, metadata = {}, {}, {}
    prefix = f'component/{slug}/'
    manifest_path = prefix + 'mrn-assets.json'
    total = 0
    with archive_path.open('rb') as stream:
        if sha256_file(stream) != expected_sha256:
            raise ValueError('Artifact differs from trusted checksum')
        stream.seek(0)
        with zipfile.ZipFile(stream) as archive:
            for member in archive.infolist():
                name = safe_path(member.filename)
                if (name in files or member.create_system != 3 or not stat.S_ISREG(member.external_attr >> 16)
                        or member.flag_bits & 1 or member.compress_type != zipfile.ZIP_DEFLATED
                        or member.file_size > MAX_FILE_BYTES or member.file_size < 0):
                    raise ValueError('Unsupported or duplicate archive entry')
                total += member.file_size
                if total > MAX_ARCHIVE_BYTES or len(files) >= MAX_FILES:
                    raise ValueError('Archive exceeds inventory limit')
                if name != 'release.json' and not name.startswith((prefix, 'assets/')):
                    raise ValueError('Unexpected payload root')
                body = archive.read(member)
                if len(body) != member.file_size:
                    raise ValueError('Truncated archive entry')
                files[name] = hashlib.sha256(body).hexdigest()
                sizes[name] = len(body)
                if name in ('release.json', manifest_path):
                    metadata[name] = parse_json(body)
    for name in files:
        if any(str(parent) in files for parent in PurePosixPath(name).parents):
            raise ValueError('Conflicting file and directory paths')
    release, manifest = metadata.get('release.json'), metadata.get(manifest_path)
    if not isinstance(release, dict) or not isinstance(manifest, dict):
        raise ValueError('Missing release metadata')
    identity = dict(schema=1, kind=kind, slug=slug, entrypoint=entrypoint, source_sha=source_sha, source_path=source_path)
    if any(release.get(key) != value for key, value in identity.items()):
        raise ValueError('Source identity mismatch')
    payload = {key: value for key, value in files.items() if key != 'release.json'}
    if release.get('files') != payload or release.get('tree_sha256') != digest(payload):
        raise ValueError('Release tree mismatch')
    if release.get('manifest_sha256') != files[manifest_path]:
        raise ValueError('Manifest checksum mismatch')
    generation = check(manifest.get('generation', ''), r'[a-f0-9]{64}', 'generation')
    public_path = f'mrn-assets/{slug}/{generation}'
    expected = dict(schema=1, scope=kind, slug=slug, source_sha=source_sha, public_path=public_path)
    if any(manifest.get(key) != value for key, value in expected.items()):
        raise ValueError('Manifest identity mismatch')
    static, assets = manifest.get('static_files'), manifest.get('assets')
    if not isinstance(static, dict) or not isinstance(assets, dict):
        raise ValueError('Invalid asset inventory')
    canonical = {}
    for name in sorted(static, key=lambda value: value.encode('utf-16-be')):
        safe_path(name)
        entry = static[name]
        if (PurePosixPath(name).suffix not in STATIC_EXTENSIONS or not isinstance(entry, dict)
                or set(entry) != {'sha256', 'bytes'} or type(entry['bytes']) is not int or entry['bytes'] < 0):
            raise ValueError('Invalid static record')
        check(entry['sha256'], r'[a-f0-9]{64}', 'static checksum')
        asset_name = f'assets/{public_path}/{name}'
        if (files.get(asset_name) != entry['sha256'] or sizes.get(asset_name) != entry['bytes']
                or files.get(prefix + name) != entry['sha256']):
            raise ValueError('Asset and code-tree bytes differ')
        canonical[name] = {'sha256': entry['sha256'], 'bytes': entry['bytes']}
    calculated = hashlib.sha256(json.dumps(canonical, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
    if calculated != generation:
        raise ValueError('Generation checksum mismatch')
    if {name for name in files if name.startswith('assets/')} != {f'assets/{public_path}/{name}' for name in static}:
        raise ValueError('Unlisted public asset')
    if {name[len(prefix):] for name in files if name.startswith(prefix) and PurePosixPath(name).suffix in STATIC_EXTENSIONS} != set(static):
        raise ValueError('Unmapped component asset')
    if set(assets) != {name for name in static if name.endswith(('.css', '.js', '.mjs'))}:
        raise ValueError('Incomplete code asset mappings')
    for name, entry in assets.items():
        if not isinstance(entry, dict) or set(entry) != {'file', 'sha256', 'bytes'}:
            raise ValueError('Invalid asset mapping')
        extension = PurePosixPath(name).suffix
        destination = name if re.search(r'\.min\.(css|js|mjs)$', name) else name[:-len(extension)] + '.min' + extension
        if (entry['file'] != destination or destination not in static
                or {key: entry[key] for key in ('sha256', 'bytes')} != static[destination]):
            raise ValueError('Incorrect emitted asset mapping')
    dependencies = manifest.get('dependencies')
    if not isinstance(dependencies, dict) or set(dependencies) != set(static):
        raise ValueError('Incomplete dependency inventory')
    for refs in dependencies.values():
        if (not isinstance(refs, list) or any(not isinstance(ref, str) or ref not in static for ref in refs)
                or len(refs) != len(set(refs))):
            raise ValueError('Unlisted dependency')
    required = {prefix + entrypoint, manifest_path}
    if kind == 'parent-theme':
        required.add(prefix + 'style.css')
    if not required.issubset(files) or not isinstance(release.get('version'), str) or not release['version']:
        raise ValueError('Missing component integration')
    return dict(status='artifact-verified', **identity, artifact_sha256=expected_sha256,
                tree_sha256=release['tree_sha256'], manifest_sha256=release['manifest_sha256'],
                generation=generation, public_path=public_path, runtime_qualified=False)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('archive', type=Path)
    parser.add_argument('--sha256', required=True)
    parser.add_argument('--commit', required=True)
    parser.add_argument('--source', default='.')
    parser.add_argument('--slug', required=True)
    parser.add_argument('--kind', required=True)
    parser.add_argument('--entrypoint', required=True)
    args = parser.parse_args()
    print(json.dumps(verify(args.archive, args.sha256, args.commit, args.source, args.slug, args.kind, args.entrypoint), indent=2))
