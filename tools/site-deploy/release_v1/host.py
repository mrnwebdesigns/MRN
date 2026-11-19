"""Reuse the installed MRN atomic code store and WordPress WP-CLI runner."""
import json
import os
from pathlib import Path
import subprocess

from atomic_store import inventory
from verify_release import verify
from .artifact import canonical
from .proof import Blocked


class AtomicCode:
    """For an already enrolled child theme. Provider adapter verifies WP identity."""

    def __init__(self, store, source_path):
        self.store, self.source_path = store, source_path

    def current(self):
        pointer = self.store.pointer()
        if not pointer:
            raise Blocked('Atomic release enrollment required')
        self.store.verify_public_snapshot()
        metadata = self.store.release(pointer['release_id'])
        public_path = metadata.get('public_path')
        if public_path != pointer.get('public_path'):
            raise Blocked('Installed code metadata differs from its active pointer')
        if public_path is not None:
            content = self.store.content_root / public_path
            if content.resolve() != content or inventory(content) != metadata.get('static_files'):
                raise Blocked('Active immutable asset bytes differ from their published manifest')
        return pointer['release_id']

    def check(self, code, manifest):
        return verify(code, manifest['code_sha256'], manifest['source_sha'], self.source_path, self.store.slug)

    def activate(self, code, manifest, expected):
        with self.store.locked_store():
            if self.current() != expected:
                raise Blocked('Atomic pointer changed before staging')
            before = self.store.pointer()
            installed = self.store.stage(code, manifest['code_sha256'], manifest['source_sha'], self.source_path)
            self.store.select(installed['release_id'], before)

    def rollback(self, prior, expected):
        with self.store.locked_store():
            if self.current() != expected:
                raise Blocked('Atomic pointer changed before rollback')
            self.store.select(prior, self.store.pointer())


class WordPressMigrations:
    """Invoke the contract's migration runner on the WordPress owner host."""

    def __init__(self, wp_root, identity, *, wp='wp'):
        self.wp_root, self.identity, self.wp = Path(wp_root), identity, wp

    def apply(self, request, row, root, files, fingerprint, backup, fence):
        target = request.target
        if (self.identity() != target or backup.get('target') != vars(target)
                or backup.get('database_verified') is not True
                or (target.environment != 'local' and backup.get('remote_verified') is not True)):
            raise Blocked('WordPress identity and backup target must agree')
        if (self.wp_root.resolve() != self.wp_root or root.resolve() != root
                or root == self.wp_root or self.wp_root in root.parents):
            raise Blocked('Migrations must execute from a physical private directory')
        context = {'target': vars(target), 'row': row, 'root': str(root), 'files': files,
                   'fingerprint': fingerprint, 'backup_nonce': backup['nonce']}
        # Site/provider code is trusted and pinned. Remove the journal signing
        # key from WP-CLI's environment and keep context out of arguments.
        env = {key: value for key, value in os.environ.items() if key != 'MRN_RELEASE_RECEIPT_KEY'}
        env['MRN_RELEASE_MIGRATION'] = canonical(context).decode()
        runner = Path(__file__).with_name('wordpress.php').resolve()
        result = subprocess.run([self.wp, f'--path={self.wp_root}', f'--url={target.origin}', 'eval-file', str(runner)],
                                cwd=self.wp_root, env=env, pass_fds=(fence,), capture_output=True, text=True,
                                timeout=300)
        receipts = [line.removeprefix('MRN_MIGRATION=') for line in result.stdout.splitlines()
                    if line.startswith('MRN_MIGRATION=')]
        if result.returncode or len(receipts) != 1:
            raise Blocked('WP-CLI migration failed; inspect the WordPress logs and reconcile the migration ID')
        return json.loads(receipts[0])
