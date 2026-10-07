"""Dev-only parent release transaction. Callers establish backup and target QA.

The public parent and child remain byte-identical. One early MU loader selects
private parent code; a separate child release loader is captured before either
theme loads. No plugin, Stack lock, database or legacy asset is overwritten.
"""
from contextlib import contextmanager
import fcntl
import hashlib
import io
import json
import os
from pathlib import Path
import shutil
import tempfile
import zipfile

from verify import verify
from atomic_store import durable_replace
from deploy import digest, check

TOOLS = Path(__file__).resolve().parent
BOOTSTRAP = '000-mrn-parent-release.php'


def inventory(root):
    result = {}
    for path in sorted(Path(root).rglob('*')):
        if path.is_symlink():
            raise ValueError('Unexpected alias in preserved or released tree')
        if path.is_file():
            result[path.relative_to(root).as_posix()] = hashlib.sha256(path.read_bytes()).hexdigest()
    return result


def physical(path, private=False):
    path = Path(path)
    if not path.is_absolute() or path.resolve() != path or not path.is_dir():
        raise ValueError('Expected an existing physical directory')
    if private and (path.stat().st_mode & 0o077 or path.stat().st_uid != os.geteuid()):
        raise ValueError('State storage must be owner-only')
    return path


class ParentStore:
    def __init__(self, root, state, child, child_state=None):
        self.root = physical(root)
        self.content = physical(self.root / 'wp-content')
        self.state = physical(state, private=True)
        if self.state == self.root or self.root in self.state.parents:
            raise ValueError('Parent state must be outside WordPress')
        self.child = check(child, r'[a-zA-Z0-9_-]+', 'child stylesheet')
        if self.child == 'mrn-base-stack':
            raise ValueError('A distinct preserved child is required')
        self.public_parent = physical(self.content / 'themes/mrn-base-stack')
        self.public_child = physical(self.content / 'themes' / child)
        self.child_state = physical(child_state, private=True) if child_state else None
        if self.child_state and (self.child_state == self.root or self.root in self.child_state.parents):
            raise ValueError('Child state must be outside WordPress')
        if self.state.stat().st_dev != self.content.stat().st_dev:
            raise ValueError('Immutable publication requires a shared filesystem')
        self.bootstrap = self.content / 'mu-plugins' / BOOTSTRAP
        physical(self.bootstrap.parent)
        self.locked = False

    @contextmanager
    def lock(self):
        handles = []
        try:
            # Share the existing child's writer lock. Acquire in stable order.
            paths = [self.state / 'deployment.lock']
            if self.child_state:
                paths.append(self.child_state / 'deployment.lock')
            for path in sorted(paths):
                descriptor = os.open(path, os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
                handles.append(descriptor)
                fcntl.flock(descriptor, fcntl.LOCK_EX | fcntl.LOCK_NB)
            self.locked = True
            yield self
        finally:
            self.locked = False
            for descriptor in handles:
                os.close(descriptor)

    def require_lock(self):
        if not self.locked:
            raise ValueError('Exclusive parent/child writer locks required')

    def read(self, path):
        if path.is_symlink() or path.resolve() != path or not path.is_file() or path.stat().st_size > 4194304:
            raise ValueError('State record is unavailable, oversized or aliased')
        return json.loads(path.read_text())

    def pointer(self):
        path = self.state / 'current.json'
        return self.read(path) if path.exists() else None

    def preserved(self):
        pointer = self.read(self.child_state / 'current.json') if self.child_state else None
        return {'parent': inventory(self.public_parent), 'child': inventory(self.public_child), 'child_pointer': pointer}

    def unchanged(self, expected):
        if self.preserved() != expected:
            raise ValueError('Preserved parent, child or child selection changed')

    def journal(self, operation, expected, selected=None):
        self.require_lock()
        record = {'schema': 1, 'operation': operation, 'previous': expected,
                  'selected': selected, 'preserved': self.preserved(), 'status': 'in-progress'}
        durable_replace(self.state / 'intent.json', (json.dumps(record, sort_keys=True) + '\n').encode())

    def stage(self, archive, checksum, commit, source):
        self.require_lock()
        receipt = verify(archive, checksum, commit, source, 'mrn-base-stack', 'parent-theme', 'functions.php')
        releases = self.state / 'releases'
        releases.mkdir(mode=0o700, exist_ok=True)
        physical(releases, private=True)
        destination = releases / checksum
        if destination.exists():
            self.release(checksum)
            return receipt
        with tempfile.TemporaryDirectory(prefix='.stage-', dir=self.state) as temporary:
            staged = Path(temporary) / 'release'
            staged.mkdir(mode=0o700)
            body = Path(archive).read_bytes()
            if hashlib.sha256(body).hexdigest() != checksum:
                raise ValueError('Archive changed after verification')
            with zipfile.ZipFile(io.BytesIO(body)) as package:
                for member in package.infolist():
                    # The separately trusted verifier rejects links/traversal/duplicates.
                    target = staged / member.filename
                    target.parent.mkdir(parents=True, exist_ok=True)
                    with target.open('xb') as stream:
                        stream.write(package.read(member))
                    target.chmod(0o644)
            metadata = self.read(staged / 'release.json')
            actual = inventory(staged)
            actual.pop('release.json')
            if actual != metadata['files'] or digest(actual) != metadata['tree_sha256']:
                raise ValueError('Extracted code and assets differ from verified artifact')
            assets = staged / 'assets' / receipt['public_path']
            public = self.content / receipt['public_path']
            if public.resolve() != public:
                raise ValueError('Immutable public destination is aliased')
            for directory in (self.content / 'mrn-assets', public.parent):
                if not directory.exists():
                    directory.mkdir(mode=0o755)
                physical(directory)
            if public.exists():
                if inventory(public) != inventory(assets):
                    raise ValueError('Never overwrite old immutable asset bytes')
            else:
                for directory in [assets, *(p for p in assets.rglob('*') if p.is_dir())]:
                    directory.chmod(0o755)
                os.rename(assets, public)
            os.rename(staged, destination)
        self.release(checksum)
        return receipt

    def release(self, identity):
        check(identity, r'[a-f0-9]{64}', 'parent artifact')
        directory = physical(self.state / 'releases' / identity)
        metadata = self.read(directory / 'release.json')
        component = directory / 'component/mrn-base-stack'
        prefix = 'component/mrn-base-stack/'
        expected = {name[len(prefix):]: value for name, value in metadata['files'].items() if name.startswith(prefix)}
        if inventory(component) != expected:
            raise ValueError('Immutable parent code drift blocks selection')
        manifest = self.read(component / 'mrn-assets.json')
        public = physical(self.content / manifest['public_path'])
        if inventory(public) != {name: entry['sha256'] for name, entry in manifest['static_files'].items()}:
            raise ValueError('Immutable public asset drift blocks selection')
        return metadata

    def selection(self, identity):
        self.require_lock()
        metadata = self.release(identity)
        descriptor = {'schema': 1, 'parent_artifact_sha256': identity, 'child_stylesheet': self.child}
        if self.child_state:
            descriptor['child_release_root'] = str(self.child_state)
        body = (json.dumps(descriptor, sort_keys=True) + '\n').encode()
        view_id = hashlib.sha256(body).hexdigest()
        views = self.state / 'theme-views'
        views.mkdir(mode=0o700, exist_ok=True)
        physical(views, private=True)
        target = views / view_id
        parent = self.state / 'releases' / identity / 'component/mrn-base-stack'
        if not target.exists():
            with tempfile.TemporaryDirectory(prefix='.view-', dir=self.state) as temporary:
                staged = Path(temporary) / 'view'
                (staged / 'themes').mkdir(parents=True)
                (staged / 'view.json').write_bytes(body)
                (staged / 'themes/mrn-base-stack').symlink_to(parent, target_is_directory=True)
                (staged / 'themes' / self.child).symlink_to(self.public_child, target_is_directory=True)
                os.rename(staged, target)
        physical(target)
        if ((target / 'view.json').read_bytes() != body
                or (target / 'themes/mrn-base-stack').resolve() != parent
                or (target / 'themes' / self.child).resolve() != self.public_child
                or sorted(p.name for p in (target / 'themes').iterdir()) != sorted(['mrn-base-stack', self.child])):
            raise ValueError('Immutable discovery view changed')
        return {'schema': 1, 'components': {'mrn-base-stack': {'artifact_sha256': identity,
                'manifest_sha256': metadata['manifest_sha256']}}, 'theme_view': view_id}

    def activate(self, identity, expected, preserved, restore_managed=False):
        self.require_lock()
        if self.pointer() != expected:
            raise ValueError('Parent selection changed since preflight')
        self.unchanged(preserved)
        selected = self.selection(identity)
        self.journal('activate', expected, selected)
        durable_replace(self.state / 'current.json', (json.dumps(selected, sort_keys=True) + '\n').encode())
        if expected is None:
            self.enable()
        elif not self.bootstrap.is_file():
            if not restore_managed:
                raise ValueError('Managed parent loader disappeared')
            self.enable()
        return selected

    def enable(self):
        self.require_lock()
        control = self.state / 'control'
        control.mkdir(mode=0o700, exist_ok=True)
        physical(control, private=True)
        runtime = (TOOLS / 'runtime.php').read_bytes()
        path = control / 'runtime.php'
        if path.exists() and path.read_bytes() != runtime:
            raise ValueError('Runtime replacement requires a separate qualification')
        if not path.exists():
            durable_replace(path, runtime)
        template = (TOOLS / 'bootstrap.php').read_text()
        body = template.replace('__MRN_COMPONENT_STATE_RELATIVE__', os.path.relpath(self.state, self.root))
        body = body.replace('__MRN_COMPONENT_RUNTIME_SHA256__', hashlib.sha256(runtime).hexdigest()).encode()
        if self.bootstrap.exists() and self.bootstrap.read_bytes() != body:
            raise ValueError('An unknown MU loader owns this public path')
        durable_replace(self.state / 'bootstrap.json', (json.dumps({'sha256': hashlib.sha256(body).hexdigest()}) + '\n').encode())
        durable_replace(self.bootstrap, body, 0o644)

    def disable(self, expected, preserved):
        """Atomic return to the untouched public parent; retain all recovery data."""
        self.require_lock()
        if self.pointer() != expected:
            raise ValueError('Selection changed before returning to the legacy parent')
        self.unchanged(preserved)
        record = self.read(self.state / 'bootstrap.json')
        if not self.bootstrap.is_file() or hashlib.sha256(self.bootstrap.read_bytes()).hexdigest() != record['sha256']:
            raise ValueError('Cannot remove an unknown or changed bootstrap')
        self.journal('disable', expected)
        destination = self.state / ('disabled-bootstrap-' + hashlib.sha256(self.bootstrap.read_bytes()).hexdigest() + '.php')
        if destination.exists():
            if destination.read_bytes() != self.bootstrap.read_bytes():
                raise ValueError('Retained recovery bootstrap changed')
            self.bootstrap.unlink()
        else:
            os.rename(self.bootstrap, destination)
        return {'status': 'legacy-restored', 'retained_selection': self.pointer()}
