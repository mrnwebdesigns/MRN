"""Private, authenticated deployment journal and exclusive per-target writer lock."""
from contextlib import contextmanager
import fcntl
import hmac
import os
from pathlib import Path
import stat

from atomic_store import durable_replace
from verify_release import parse_json
from .artifact import canonical
from .proof import Blocked


class Journal:
    def __init__(self, root, key):
        self.root = Path(root)
        if (not self.root.is_absolute() or self.root.resolve() != self.root or not self.root.is_dir()
                or self.root.stat().st_uid != os.geteuid() or self.root.stat().st_mode & 0o077):
            raise Blocked('Provision an owner-only physical journal directory outside public WordPress files')
        if not isinstance(key, bytes) or len(key) != 32:
            raise Blocked('A 32-byte receipt signing key is required')
        self.key = key
        self.active = self.root / 'active.json'

    def sign(self, data):
        return {'data': data, 'hmac_sha256': hmac.digest(self.key, canonical(data), 'sha256').hex()}

    def read(self, path=None):
        path = path or self.active
        try:
            descriptor = os.open(path, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK)
        except FileNotFoundError:
            return None
        with os.fdopen(descriptor, 'rb') as stream:
            info = os.fstat(stream.fileno())
            if not stat.S_ISREG(info.st_mode) or info.st_mode & 0o077 or info.st_uid != os.geteuid():
                raise Blocked('Journal receipt is not an owner-only regular file')
            try:
                envelope = parse_json(stream.read())
                expected = self.sign(envelope['data'])['hmac_sha256']
                if not hmac.compare_digest(envelope['hmac_sha256'], expected):
                    raise ValueError('signature mismatch')
            except (ValueError, TypeError, KeyError) as error:
                raise Blocked('Journal receipt failed authentication; investigate before deployment') from error
        return envelope['data']

    def save(self, data, path=None):
        durable_replace(path or self.active, canonical(self.sign(data)) + b'\n')

    @contextmanager
    def writer(self):
        descriptor = os.open(self.root / 'writer.lock', os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
        try:
            try:
                fcntl.flock(descriptor, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except BlockingIOError as error:
                raise Blocked('The target already has a deployment command running') from error
            yield descriptor
        finally:
            os.close(descriptor)
