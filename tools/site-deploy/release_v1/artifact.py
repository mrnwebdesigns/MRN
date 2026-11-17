"""Build and inspect MRN site-release v1 envelopes without contacting WordPress."""
from contextlib import contextmanager
from dataclasses import asdict, dataclass
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import re
import stat
import subprocess
import tarfile
import tempfile

from verify_release import parse_json, sha256_file

SCHEMA = 'mrn.site-release/v1'
CONTENT = 'scripts/content'
ENVIRONMENTS = ('local', 'dev', 'staging', 'production')
MIGRATION_ID = r'[a-z0-9][a-z0-9-]{0,63}'
FORMATS = {'.png': 'PNG', '.jpg': 'JPEG', '.jpeg': 'JPEG', '.webp': 'WEBP'}
MAX_CODE = 512 * 1024 * 1024
MAX_MANIFEST = 512 * 1024
MAX_SCRIPT = 256 * 1024
MAX_FILES = 512


def canonical(data):
    return json.dumps(data, sort_keys=True, separators=(',', ':'), allow_nan=False).encode()


def sha256(data):
    return hashlib.sha256(data).hexdigest()


def match(value, pattern, label):
    if not isinstance(value, str) or not re.fullmatch(pattern, value):
        raise ValueError(f'Invalid {label}')
    return value


def path_name(value):
    return match(value, r'(?:[a-zA-Z0-9][a-zA-Z0-9_.-]*/)*[a-zA-Z0-9][a-zA-Z0-9_.-]*', 'artifact path')


def public_paths(values):
    if not isinstance(values, list) or len(values) > 100:
        raise ValueError('Expected a bounded list of public paths')
    paths = []
    for value in values:
        match(value, r'/(?:[A-Za-z0-9_~-]+/)*[A-Za-z0-9_~.-]*/*', 'public path')
        if '//' in value or any(part in ('.', '..') for part in value.split('/')):
            raise ValueError('Public paths must be exact and canonical')
        paths.append(value)
    if len(set(paths)) != len(paths):
        raise ValueError('Duplicate public path')
    return paths


@dataclass(frozen=True)
class Limits:
    file_bytes: int = 8 * 1024 * 1024
    bundle_bytes: int = 32 * 1024 * 1024
    width: int = 4096
    height: int = 4096
    pixels: int = 16_000_000
    recommend_bytes: int = 500_000
    recommend_edge: int = 2000
    formats: tuple = ('JPEG', 'PNG', 'WEBP')

    def __post_init__(self):
        hard = (32 * 1024 * 1024, 128 * 1024 * 1024, 8192, 8192, 32_000_000)
        configured = (self.file_bytes, self.bundle_bytes, self.width, self.height, self.pixels)
        if any(type(value) is not int or not 0 < value <= cap for value, cap in zip(configured, hard)):
            raise ValueError('Media limits must be positive and within the v1 safety ceilings')
        if (type(self.recommend_bytes) is not int or self.recommend_bytes <= 0
                or type(self.recommend_edge) is not int or self.recommend_edge <= 0
                or not self.formats or any(fmt not in FORMATS.values() for fmt in self.formats)):
            raise ValueError('Invalid media recommendations or format allowlist')


def image_report(path, data, limits):
    from PIL import Image

    if len(data) > limits.file_bytes:
        raise ValueError(f'{path}: {len(data)} bytes exceeds file limit {limits.file_bytes}')
    try:
        with Image.open(io.BytesIO(data), formats=list(limits.formats)) as image:
            width, height = image.size
            fmt = image.format
            if fmt != FORMATS.get(PurePosixPath(path).suffix.lower()) or getattr(image, 'n_frames', 1) != 1:
                raise ValueError(f'{path}: unsupported format, extension mismatch, or animated media')
            if width > limits.width or height > limits.height or width * height > limits.pixels:
                raise ValueError(f'{path}: {width}x{height} exceeds media dimension limits')
            image.verify()
        with Image.open(io.BytesIO(data), formats=[fmt]) as image:
            image.load()
    except (OSError, Image.DecompressionBombError, SyntaxError) as error:
        raise ValueError(f'{path}: image could not be verified') from error
    return {'bytes': len(data), 'width': width, 'height': height, 'format': fmt,
            'sha256': sha256(data),
            'optimize': len(data) > limits.recommend_bytes or max(width, height) > limits.recommend_edge}


def declarations(rows):
    if not isinstance(rows, list) or len(rows) > MAX_FILES:
        raise ValueError('Invalid migration list')
    ids, scripts, assets = set(), set(), set()
    for row in rows:
        if not isinstance(row, dict) or set(row) != {'id', 'script', 'assets', 'environments', 'paths'}:
            raise ValueError('Invalid migration declaration')
        name = match(row['id'], MIGRATION_ID, 'migration ID')
        script = path_name(row['script'])
        if name in ids or script in scripts or script != f'{name}.php':
            raise ValueError('Migration ID and script must be unique and match')
        ids.add(name)
        scripts.add(script)
        if not isinstance(row['assets'], list) or len(row['assets']) > MAX_FILES:
            raise ValueError('Expected an explicit migration asset list')
        for asset in row['assets']:
            path_name(asset)
            if not asset.startswith('media/') or PurePosixPath(asset).suffix.lower() not in FORMATS:
                raise ValueError(f'{asset}: unsupported migration asset')
            assets.add(asset)
        if len(set(row['assets'])) != len(row['assets']):
            raise ValueError('Duplicate migration asset reference')
        environments = row['environments']
        if (not isinstance(environments, list) or not environments
                or any(item not in ENVIRONMENTS for item in environments)
                or len(set(environments)) != len(environments)):
            raise ValueError('Migration requires an explicit environment allowlist')
        if not public_paths(row['paths']):
            raise ValueError('Migration requires affected public paths')
    return scripts, assets


def report(rows, entries, read, limits):
    scripts, assets = declarations(rows)
    missing, extra = (scripts | assets) - set(entries), set(entries) - (scripts | assets)
    if missing or extra:
        raise ValueError(f'Migration inventory differs (missing {sorted(missing)}, unreferenced {sorted(extra)})')
    if len(entries) > MAX_FILES or len({name.casefold() for name in entries}) != len(entries):
        raise ValueError('Migration file limit exceeded or path names differ only by case')
    media_bytes = sum(entries[name] for name in assets)
    total = sum(entries.values())
    if total > limits.bundle_bytes:
        raise ValueError(f'{total} bytes exceeds migration bundle limit {limits.bundle_bytes}')
    files = {}
    for name in sorted(entries):
        size = entries[name]
        cap = MAX_SCRIPT if name in scripts else limits.file_bytes
        if not 0 < size <= cap:
            raise ValueError(f'{name}: {size} bytes outside file limits (maximum {cap})')
        data = read(name)
        if len(data) != size:
            raise ValueError(f'{name}: file size differs from inventory')
        if name in scripts:
            if not data.startswith(b'<?php') or b'\x00' in data:
                raise ValueError(f'{name}: expected a PHP migration script')
            files[name] = {'bytes': size, 'sha256': sha256(data)}
        else:
            files[name] = image_report(name, data, limits)
    return {'files': files, 'media_bytes': media_bytes, 'bundle_bytes': total, 'limits': asdict(limits)}


def git_bytes(repo, *args):
    return subprocess.check_output(['git', '-C', str(repo), *args])


def snapshot(repo, source, migration_prefix=None):
    if git_bytes(repo, 'rev-parse', source + '^{commit}').strip().decode() != source:
        raise ValueError('Expected exact Git commit')
    entries = {}
    for row in git_bytes(repo, 'ls-tree', '-rzl', source, '--', CONTENT).split(b'\x00'):
        if not row:
            continue
        metadata, path = row.split(b'\t', 1)
        name = path.decode().removeprefix(CONTENT + '/')
        if migration_prefix is not None and not (
                name == 'migrations.json' or name.startswith('media/')
                or ('/' not in name and name.startswith(migration_prefix))):
            continue
        mode, kind, oid, size = metadata.decode().split()
        if mode not in ('100644', '100755') or kind != 'blob':
            raise ValueError('Migration source must contain regular Git blobs')
        entries[path_name(name)] = (oid, int(size))
    return entries


def add(archive, name, body, size):
    info = tarfile.TarInfo(name)
    info.mode, info.size = 0o644, size
    archive.addfile(info, body)


def pack(repo, source, code, output, limits=Limits(), *, migration_prefix=None):
    match(source, r'[a-f0-9]{40}', 'source commit')
    if migration_prefix is not None:
        match(migration_prefix, r'v1-[a-z0-9-]{0,32}', 'mixed-layout migration prefix')
    with regular_file(code, MAX_CODE) as code_stream:
        return pack_stream(repo, source, code_stream, output, limits, migration_prefix)


def pack_stream(repo, source, code, output, limits, migration_prefix):
    entries = snapshot(repo, source, migration_prefix)
    if migration_prefix is not None and 'migrations.json' not in entries:
        raise ValueError('Mixed-layout packaging requires scripts/content/migrations.json')
    if len(entries) > MAX_FILES + 1:
        raise ValueError('Migration bundle contains too many files')

    def read_blob(name):
        return git_bytes(repo, 'cat-file', 'blob', entries[name][0])

    rows = []
    if 'migrations.json' in entries:
        if entries['migrations.json'][1] > MAX_MANIFEST:
            raise ValueError('Migration declaration exceeds size limit')
        declaration = parse_json(read_blob('migrations.json'))
        if not isinstance(declaration, dict) or set(declaration) != {'schema', 'migrations'} or declaration['schema'] != SCHEMA:
            raise ValueError('Unsupported migration declaration')
        rows = declaration['migrations']
    sizes = {name: size for name, (_, size) in entries.items() if name != 'migrations.json'}
    data = report(rows, sizes, read_blob, limits)
    # Store content facts; optimization recommendations are recomputed under the
    # receiving target's own policy.
    files = {name: {key: value for key, value in facts.items() if key != 'optimize'}
             for name, facts in data['files'].items()}
    checksum = sha256_file(code)
    code.seek(0)
    manifest = {'schema': SCHEMA, 'source_sha': source,
                'code_sha256': checksum, 'migrations': rows, 'files': files}
    body = canonical(manifest)
    if len(body) > MAX_MANIFEST:
        raise ValueError('Release manifest exceeds size limit')
    output = Path(output)
    with tempfile.TemporaryDirectory(prefix='.mrn-pack-', dir=output.parent) as temporary:
        candidate = Path(temporary) / 'release.tar'
        with tarfile.open(candidate, 'w', format=tarfile.USTAR_FORMAT) as archive:
            add(archive, 'manifest.json', io.BytesIO(body), len(body))
            add(archive, 'code.tar', code, os.fstat(code.fileno()).st_size)
            for name in sorted(sizes):
                add(archive, 'migrations/' + name, io.BytesIO(read_blob(name)), sizes[name])
        with candidate.open('rb') as stream:
            artifact_sha256 = sha256_file(stream)
        os.link(candidate, output)
    return {'source_sha': source, 'artifact_sha256': artifact_sha256, 'code_sha256': checksum, **data}


@contextmanager
def regular_file(path, ceiling):
    descriptor = os.open(path, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK)
    with os.fdopen(descriptor, 'rb') as stream:
        info = os.fstat(stream.fileno())
        if not stat.S_ISREG(info.st_mode) or not 0 < info.st_size <= ceiling:
            raise ValueError('Artifact input must be a bounded regular file')
        yield stream


@contextmanager
def inspect(path, artifact_sha256, source_sha, limits=Limits()):
    """Extract exact verified bytes to an isolated, private phase workspace."""
    match(artifact_sha256, r'[a-f0-9]{64}', 'artifact SHA-256')
    match(source_sha, r'[a-f0-9]{40}', 'source commit')
    ceiling = MAX_CODE + limits.bundle_bytes + MAX_MANIFEST + (MAX_FILES + 32) * 1024
    with regular_file(path, ceiling) as stream, tempfile.TemporaryDirectory(prefix='mrn-release-v1-') as temp:
        if sha256_file(stream) != artifact_sha256:
            raise ValueError('Artifact differs from the trusted SHA-256')
        stream.seek(0)
        with tarfile.open(fileobj=stream, mode='r:') as archive:
            files, folded = {}, set()
            migration_bytes = 0
            for member in archive:
                name = path_name(member.name)
                root_file = name in ('manifest.json', 'code.tar')
                size_cap = {'manifest.json': MAX_MANIFEST, 'code.tar': MAX_CODE}.get(name, max(limits.file_bytes, MAX_SCRIPT))
                if (name.casefold() in folded or len(files) >= MAX_FILES + 2 or member.type != tarfile.REGTYPE
                        or member.offset_data != member.offset + tarfile.BLOCKSIZE
                        or member.pax_headers or member.linkname or not 0 < member.size <= size_cap
                        or (not root_file and not name.startswith('migrations/'))):
                    raise ValueError(f'{name}: unsupported, over-limit or duplicate artifact member')
                if not root_file:
                    migration_bytes += member.size
                    if migration_bytes > limits.bundle_bytes:
                        raise ValueError('Migration bundle exceeds total size limit')
                files[name] = member
                folded.add(name.casefold())
            if not {'manifest.json', 'code.tar'} <= files.keys():
                raise ValueError('Artifact requires a manifest and code payload')
            manifest = parse_json(archive.extractfile(files['manifest.json']).read())
            if (not isinstance(manifest, dict) or set(manifest) != {'schema', 'source_sha', 'code_sha256', 'migrations', 'files'}
                    or manifest['schema'] != SCHEMA or manifest['source_sha'] != source_sha):
                raise ValueError('Invalid manifest or mismatched source commit')
            sizes = {name[11:]: member.size for name, member in files.items() if name.startswith('migrations/')}
            data = report(manifest['migrations'], sizes,
                          lambda name: archive.extractfile(files['migrations/' + name]).read(), limits)
            expected = {name: {k: v for k, v in facts.items() if k != 'optimize'} for name, facts in data['files'].items()}
            if canonical(expected) != canonical(manifest['files']):
                raise ValueError('Migration manifest differs from verified bytes')
            root = Path(temp).resolve()
            for name in ('code.tar', *('migrations/' + name for name in sizes)):
                target = root / name
                target.parent.mkdir(parents=True, exist_ok=True)
                checksum = hashlib.sha256()
                with archive.extractfile(files[name]) as content, target.open('xb') as output:
                    for chunk in iter(lambda: content.read(1024 * 1024), b''):
                        checksum.update(chunk)
                        output.write(chunk)
                if checksum.hexdigest() != (manifest['code_sha256'] if name == 'code.tar' else expected[name[11:]]['sha256']):
                    raise ValueError(f'{name}: bytes changed during extraction')
                target.chmod(0o400)
        stream.seek(0)
        if sha256_file(stream) != artifact_sha256:
            raise ValueError('Artifact changed during inspection')
        yield root, manifest, data
