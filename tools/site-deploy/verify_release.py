#!/usr/bin/env python3
"""Validate an immutable release without extracting it or contacting a site."""
import argparse
import hashlib
import json
from pathlib import Path, PurePosixPath
import re
import sys
import tarfile

from deploy import check, digest
from verify_public_assets import stylesheet_routes

MAX_ARCHIVE_BYTES = 512 * 1024 * 1024
MAX_FILE_BYTES = 128 * 1024 * 1024
MAX_FILES = 20000
STATIC_EXTENSIONS = {'.css', '.js', '.mjs', '.svg', '.png', '.jpg', '.jpeg', '.gif',
                     '.webp', '.avif', '.ico', '.woff', '.woff2', '.ttf', '.otf', '.eot'}


def json_object(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise ValueError('Duplicate JSON key in release metadata')
        result[key] = value
    return result


def parse_json(body):
    return json.loads(body, object_pairs_hook=json_object)


def safe_path(name):
    if (not isinstance(name, str) or not name or name.startswith('/') or '\\' in name
            or any(ord(ch) < 32 or ord(ch) == 127 for ch in name)
            or any(part in ('', '.', '..') or part.startswith('.') for part in name.split('/'))):
        raise ValueError('Unsafe path in release')
    return name


def sha256_file(stream):
    hasher = hashlib.sha256()
    for chunk in iter(lambda: stream.read(1024 * 1024), b''):
        hasher.update(chunk)
    return hasher.hexdigest()


def verify(archive_path, expected_sha256, source_sha, source_path, slug):
    """Verify trusted build identity, archive inventory, and immutable asset bytes.

    The expected checksum must come from the trusted build job, not a sidecar
    downloaded beside the artifact. This result is not runtime qualification.
    """
    check(expected_sha256, r'[0-9a-f]{64}', 'artifact SHA-256')
    check(source_sha, r'[0-9a-f]{40}', 'source SHA')
    check(slug, r'[a-z0-9_-]+', 'stylesheet')
    if source_path != '.':
        safe_path(source_path)
    archive_path = Path(archive_path)
    if archive_path.is_symlink() or not archive_path.is_file():
        raise ValueError('Release archive must be a regular file')
    if archive_path.stat().st_size > MAX_ARCHIVE_BYTES:
        raise ValueError('Release archive exceeds size limit')
    files, sizes, metadata = {}, {}, {}
    total_bytes = 0
    with archive_path.open('rb') as stream:
        if sha256_file(stream) != expected_sha256:
            raise ValueError('Release archive checksum differs from trusted build')
        stream.seek(0)
        # Only uncompressed, regular-file archives emitted by the builder.
        with tarfile.open(fileobj=stream, mode='r:') as archive:
            for member in archive:
                name = safe_path(member.name)
                if (name in files or member.type != tarfile.REGTYPE or member.pax_headers
                        or member.linkname or member.size < 0 or member.size > MAX_FILE_BYTES):
                    raise ValueError('Unsupported or duplicate archive entry')
                total_bytes += member.size
                if len(files) >= MAX_FILES or total_bytes > MAX_ARCHIVE_BYTES:
                    raise ValueError('Release inventory exceeds size limit')
                if name != 'release.json' and not name.startswith(('theme/', 'assets/')):
                    raise ValueError('Unexpected release payload root')
                body = archive.extractfile(member).read()
                if len(body) != member.size:
                    raise ValueError('Truncated release file')
                files[name] = hashlib.sha256(body).hexdigest()
                sizes[name] = len(body)
                if name in ('release.json', 'theme/mrn-assets.json'):
                    metadata[name] = parse_json(body)
    for name in files:
        if any(str(parent) in files for parent in PurePosixPath(name).parents):
            raise ValueError('Release has conflicting file and directory paths')
    release = metadata.get('release.json')
    manifest = metadata.get('theme/mrn-assets.json')
    if not isinstance(release, dict) or not isinstance(manifest, dict):
        raise ValueError('Missing release or asset manifest')
    identity = {'schema': 1, 'source_sha': source_sha, 'source_path': source_path, 'slug': slug}
    if any(release.get(key) != value for key, value in identity.items()):
        raise ValueError('Release identity differs from selected source')
    payload = {name: value for name, value in files.items() if name != 'release.json'}
    if release.get('files') != payload or release.get('tree') != digest(payload):
        raise ValueError('Release file inventory or tree checksum differs')
    if release.get('manifest_sha256') != files['theme/mrn-assets.json']:
        raise ValueError('Asset manifest checksum differs')
    generation = check(release.get('generation', ''), r'[0-9a-f]{64}', 'asset generation')
    public_path = f'mrn-assets/{slug}/{generation}'
    expected_manifest = {'schema': 1, 'scope': 'child-theme', 'slug': slug, 'source_sha': source_sha,
                         'generation': generation, 'public_path': public_path}
    if any(manifest.get(key) != value for key, value in expected_manifest.items()):
        raise ValueError('Asset manifest identity differs from release')
    static = manifest.get('static_files')
    assets = manifest.get('assets')
    if not isinstance(static, dict) or not static or not isinstance(assets, dict):
        raise ValueError('Missing static inventory or asset mappings')
    # Match the builder's sorted JavaScript object and field order exactly.
    canonical_static = {}
    for name in sorted(static, key=lambda value: value.encode('utf-16-be')):
        safe_path(name)
        entry = static[name]
        if (PurePosixPath(name).suffix not in STATIC_EXTENSIONS or not isinstance(entry, dict)
                or set(entry) != {'sha256', 'bytes'} or type(entry['bytes']) is not int):
            raise ValueError('Invalid static asset metadata')
        check(entry['sha256'], r'[0-9a-f]{64}', 'static asset checksum')
        asset_name = 'assets/' + public_path + '/' + name
        if (files.get(asset_name) != entry['sha256'] or sizes.get(asset_name) != entry['bytes']
                or files.get('theme/' + name) != entry['sha256']):
            raise ValueError('Static asset or synchronized theme bytes differ')
        canonical_static[name] = {'sha256': entry['sha256'], 'bytes': entry['bytes']}
    calculated = hashlib.sha256(json.dumps(canonical_static, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
    if calculated != generation:
        raise ValueError('Content-addressed generation does not match static bytes')
    expected_static_paths = {'assets/' + public_path + '/' + name for name in static}
    if {name for name in files if name.startswith('assets/')} != expected_static_paths:
        raise ValueError('Unlisted or wrong-generation static payload')
    theme_static = {name[6:] for name in files if name.startswith('theme/') and PurePosixPath(name).suffix in STATIC_EXTENSIONS}
    if theme_static != set(static):
        raise ValueError('Theme static inventory differs from immutable generation')
    code_assets = {name for name in static if name.endswith(('.css', '.js', '.mjs'))}
    if set(assets) != code_assets or 'style.css' not in assets:
        raise ValueError('Incomplete CSS/JS URL mappings')
    for name, entry in assets.items():
        if not isinstance(entry, dict) or set(entry) != {'file', 'sha256', 'bytes'}:
            raise ValueError('Invalid asset mapping')
        destination = safe_path(entry['file'])
        extension = PurePosixPath(name).suffix
        expected_destination = name if re.search(r'\.min\.(css|js|mjs)$', name) else name[:-len(extension)] + '.min' + extension
        if (destination != expected_destination or destination not in static
                or {key: entry[key] for key in ('sha256', 'bytes')} != static[destination]):
            raise ValueError('Asset mapping does not match static payload')
    required = {'theme/style.css', 'theme/functions.php', 'theme/mrn-assets.json', 'theme/mrn-release-assets.php'}
    stylesheet_routes(manifest)
    if not required.issubset(files):
        raise ValueError('Missing child-theme release integration')
    return {'status': 'artifact-verified', **identity, 'artifact_sha256': expected_sha256,
            'tree': release['tree'], 'manifest_sha256': release['manifest_sha256'],
            'generation': generation, 'file_count': len(payload),
            'theme_files': {name[6:]: value for name, value in payload.items() if name.startswith('theme/')},
            'runtime_qualified': False}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--archive', required=True)
    parser.add_argument('--sha256', required=True)
    parser.add_argument('--source-sha', required=True)
    parser.add_argument('--source-path', required=True)
    parser.add_argument('--slug', required=True)
    parser.add_argument('--receipt', required=True)
    args = parser.parse_args()
    result = verify(args.archive, args.sha256, args.source_sha, args.source_path, args.slug)
    Path(args.receipt).write_text(json.dumps(result, indent=2) + '\n')
    print(json.dumps({key: value for key, value in result.items() if key != 'theme_files'}, indent=2))


if __name__ == '__main__':
    try:
        main()
    except (ValueError, OSError, KeyError, TypeError, tarfile.TarError) as error:
        print(f'Release verification stopped: {error}', file=sys.stderr)
        sys.exit(1)
