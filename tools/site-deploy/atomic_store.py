#!/usr/bin/env python3
"""Host-local release storage primitives; callers own backup and runtime QA gates.

No CLI: the deployment controller must establish identity, fresh backup and
cache qualification before invoking these writes. Nothing is enabled by this
module. Use one exclusive Store lock for a complete activation/verification.
"""
from contextlib import contextmanager
import fcntl
import hashlib
import json
import os
from pathlib import Path
import shutil
import tarfile
import tempfile

from deploy import allowed, check, digest
from verify_release import verify, safe_path, MAX_FILE_BYTES, MAX_FILES, MAX_ARCHIVE_BYTES


def inventory(root):
    result = {}
    for path in sorted(root.rglob('*')):
        if path.is_symlink():
            raise ValueError('Symlinks are not allowed in a release tree')
        name = path.relative_to(root).as_posix()
        if path.is_file() and allowed(name):
            result[name] = hashlib.sha256(path.read_bytes()).hexdigest()
    return result


def durable_replace(path, body, mode=0o600):
    """Publish a complete file atomically, on its destination filesystem."""
    descriptor, temporary = tempfile.mkstemp(prefix='.mrn-next-', dir=path.parent)
    try:
        with os.fdopen(descriptor, 'wb') as stream:
            stream.write(body)
            stream.flush()
            os.fchmod(stream.fileno(), mode)
            os.fsync(stream.fileno())
        os.replace(temporary, path)
        parent = os.open(path.parent, os.O_RDONLY)
        try:
            os.fsync(parent)
        finally:
            os.close(parent)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


class Store:
    def __init__(self, state, public_theme, content_root, slug):
        self.state = Path(state)
        self.public_theme = Path(public_theme)
        self.content_root = Path(content_root)
        self.slug = check(slug, r'[a-z0-9_-]+', 'stylesheet')
        self.releases = self.state / 'releases'
        self.locked = False
        # All paths must already resolve without aliases; no private data under
        # the public WordPress tree. Provisioning is a separate backup-gated step.
        for path in (self.state, self.public_theme, self.content_root):
            if not path.is_absolute() or path.resolve() != path or not path.is_dir():
                raise ValueError('Release paths must be existing physical directories')
        if (self.state == self.content_root.parent or self.content_root.parent in self.state.parents
                or self.state.stat().st_mode & 0o077 or self.state.stat().st_uid != os.geteuid()):
            raise ValueError('Release state must be private and outside public content')
        if self.public_theme != self.content_root / 'themes' / slug:
            raise ValueError('Public child theme identity differs')
        if self.state.stat().st_dev != self.public_theme.stat().st_dev:
            raise ValueError('Release storage and theme must share a filesystem')

    @contextmanager
    def locked_store(self):
        lock = self.state / 'deployment.lock'
        descriptor = os.open(lock, os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
        try:
            fcntl.flock(descriptor, fcntl.LOCK_EX | fcntl.LOCK_NB)
            self.locked = True
            yield self
        finally:
            self.locked = False
            os.close(descriptor)

    def require_lock(self):
        if not self.locked:
            raise ValueError('Exclusive deployment lock required')

    def pointer(self):
        path = self.state / 'current.json'
        if path.is_symlink():
            raise ValueError('Deployment pointer must not be a symlink')
        return json.loads(path.read_text()) if path.is_file() else None

    def release(self, release_id):
        check(release_id, r'[a-f0-9]{64}', 'release ID')
        path = self.releases / release_id
        if path.resolve() != path or not path.is_dir():
            raise ValueError('Release directory is unavailable or aliased')
        metadata = json.loads((path / 'installed.json').read_text())
        if metadata.get('release_id') != release_id or metadata.get('slug') != self.slug:
            raise ValueError('Stored release identity differs')
        if inventory(path / 'theme') != metadata.get('theme_files'):
            raise ValueError('Stored release drift blocks activation or rollback')
        return metadata

    def select(self, release_id, expected_current):
        """Atomic compare-and-switch; rollback uses this identical primitive."""
        self.require_lock()
        if self.pointer() != expected_current:
            raise ValueError('Active release changed during deployment')
        if (self.state / 'adoption.json').exists():
            self.verify_public_snapshot()
        metadata = self.release(release_id)
        public_path = metadata.get('public_path')
        if public_path is not None:
            check(public_path, r'mrn-assets/' + self.slug + r'/[a-f0-9]{64}', 'immutable public path')
            actual = inventory(self.content_root / public_path)
            if actual != metadata.get('static_files'):
                raise ValueError('Published immutable asset drift blocks activation')
        pointer = {'schema': 1, 'slug': self.slug, 'release_id': release_id, 'public_path': public_path}
        durable_replace(self.state / 'current.json', (json.dumps(pointer, sort_keys=True) + '\n').encode())
        return pointer

    def adopt(self, expected_tree, bootstrap_template):
        """Snapshot legacy code and install one stable loader; preserve asset URLs."""
        self.require_lock()
        if self.pointer() is not None or (self.state / 'adoption.json').exists():
            raise ValueError('This child theme already has an adoption record')
        files = inventory(self.public_theme)
        if digest(files) != expected_tree or 'functions.php' not in files or 'style.css' not in files:
            raise ValueError('Public theme differs from the reviewed adoption tree')
        state_path = check(str(self.state), r'/[A-Za-z0-9_./-]+', 'private state path')
        template = Path(bootstrap_template).read_text()
        if template.count('__MRN_STATE_PATH__') != 1:
            raise ValueError('Unexpected bootstrap template')
        bootstrap = template.replace('__MRN_STATE_PATH__', state_path).encode()
        self.releases.mkdir(mode=0o700, exist_ok=True)
        if self.releases.resolve() != self.releases:
            raise ValueError('Private releases directory is aliased')
        release_id = digest({'legacy_tree': expected_tree, 'slug': self.slug})
        destination = self.releases / release_id
        if destination.exists():
            # A failed initial integration may retain a verified legacy snapshot.
            # Reuse it only when the public source is still that exact baseline.
            existing = self.release(release_id)
            if existing.get('theme_files') != files or existing.get('legacy') is not True:
                raise ValueError('Existing legacy snapshot differs from adoption source')
        with tempfile.TemporaryDirectory(prefix='.stage-', dir=self.state) as temporary:
            staged = Path(temporary)
            (staged / 'theme').mkdir()
            for name in files:
                target = staged / 'theme' / name
                target.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(self.public_theme / name, target)
            if inventory(staged / 'theme') != files:
                raise ValueError('Legacy snapshot verification failed')
            metadata = {'schema': 1, 'release_id': release_id, 'slug': self.slug,
                        'public_path': None, 'theme_files': files, 'legacy': True}
            (staged / 'installed.json').write_text(json.dumps(metadata, sort_keys=True) + '\n')
            if not destination.exists():
                os.rename(staged, destination)
        pointer = self.select(release_id, None)
        if inventory(self.public_theme) != files:
            raise ValueError('Public theme changed before loader installation')
        record = {'schema': 1, 'slug': self.slug, 'legacy_release_id': release_id,
                  'public_files': files, 'bootstrap_sha256': hashlib.sha256(bootstrap).hexdigest()}
        durable_replace(self.state / 'adoption.json', (json.dumps(record, sort_keys=True) + '\n').encode())
        durable_replace(self.public_theme / 'functions.php', bootstrap, 0o644)
        return pointer

    def verify_public_snapshot(self):
        record = json.loads((self.state / 'adoption.json').read_text())
        expected = dict(record['public_files'])
        expected['functions.php'] = record['bootstrap_sha256']
        if inventory(self.public_theme) != expected:
            raise ValueError('Legacy public tree or stable loader drifted')

    def stage(self, archive, checksum, source_sha, source_path):
        """Stage exact artifact and publish assets before they can be referenced."""
        self.require_lock()
        self.verify_public_snapshot()
        verified = verify(archive, checksum, source_sha, source_path, self.slug)
        destination = self.releases / checksum
        if destination.exists():
            existing = self.release(checksum)
            if existing.get('artifact_sha256') != checksum:
                raise ValueError('Release ID already belongs to another artifact')
            return existing
        with tempfile.TemporaryDirectory(prefix='.stage-', dir=self.state) as temporary:
            staged = Path(temporary)
            # The verifier has rejected links, traversal, duplicate members and
            # unsupported roots. Still create each file exclusively, without
            # tarfile.extract's ownership, permission or special-file handling.
            with tarfile.open(archive, 'r:') as archive_stream:
                extracted_bytes = 0
                for number, member in enumerate(archive_stream):
                    name = safe_path(member.name)
                    extracted_bytes += member.size
                    if (member.type != tarfile.REGTYPE or member.pax_headers or member.linkname
                            or member.size < 0 or member.size > MAX_FILE_BYTES or number >= MAX_FILES
                            or extracted_bytes > MAX_ARCHIVE_BYTES):
                        raise ValueError('Archive changed after verification')
                    path = staged / name
                    path.parent.mkdir(parents=True, exist_ok=True)
                    with path.open('xb') as output:
                        shutil.copyfileobj(archive_stream.extractfile(member), output)
                    path.chmod(0o644)
            extracted = inventory(staged)
            extracted.pop('release.json', None)
            if digest(extracted) != verified['tree']:
                raise ValueError('Extracted release tree differs from verified artifact')
            if inventory(staged / 'theme') != verified['theme_files']:
                raise ValueError('Extracted theme differs from artifact')
            manifest = json.loads((staged / 'theme/mrn-assets.json').read_text())
            public_path = manifest['public_path']
            asset_source = staged / 'assets' / public_path
            asset_target = self.content_root / public_path
            static_files = {name: entry['sha256'] for name, entry in manifest['static_files'].items()}
            if inventory(asset_source) != static_files:
                raise ValueError('Extracted assets differ from artifact')
            # Reject aliases before walking/creating public asset destinations.
            if asset_target.resolve() != asset_target:
                raise ValueError('Public asset destination is aliased')
            if asset_target.exists():
                if inventory(asset_target) != static_files:
                    raise ValueError('Never overwrite changed bytes at an existing asset URL')
            else:
                for parent in [self.content_root / 'mrn-assets', asset_target.parent]:
                    if not parent.exists():
                        parent.mkdir()
                        parent.chmod(0o755)
                for directory in [asset_source, *(p for p in asset_source.rglob('*') if p.is_dir())]:
                    directory.chmod(0o755)
                if asset_target.parent.stat().st_dev != asset_source.stat().st_dev:
                    raise ValueError('Immutable asset publication requires one filesystem')
                # Assets become visible together. No active HTML references them yet.
                os.rename(asset_source, asset_target)
            metadata = {**verified, 'release_id': checksum, 'public_path': public_path,
                        'static_files': static_files, 'legacy': False}
            (staged / 'installed.json').write_text(json.dumps(metadata, sort_keys=True) + '\n')
            os.rename(staged, destination)
        self.release(checksum)
        return metadata
