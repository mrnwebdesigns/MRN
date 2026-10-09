"""Bounded source-host publication; no WordPress, provider or site commands.

The CLI has fixed owner/roots. The class accepts isolated roots for recovery
tests; production callers cannot supply an arbitrary remote destination.
"""
from __future__ import annotations

import fcntl
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import pwd
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile

OWNER = 'mrndev-stack-manager'
ROOT = Path('/home/mrndev-stack-manager/stack')
DEPOT = Path('/home/mrndev-stack-manager/stack-release-artifacts')
ID = re.compile(r'^\d{4}\.\d{2}\.\d{2}-fleet-auto-[a-f0-9]{12}$')
HASH = re.compile(r'^[a-f0-9]{64}$')
COVERAGE = {'source', 'contracts', 'installed-default-packages', 'no-woocommerce',
            'native-editor', 'native-wpforms', 'api', 'browser', 'accessibility',
            'performance', 'core-web-vitals', 'distribution'}
ALLOWED_FILES = {'BOOTSTRAP_RELEASE.md', 'STACK_VERSION.md',
                 'RESTORING-RETIRED-CAPABILITIES.md', 'bootstrap-bundle.json'}
ALLOWED_CONFIGS = {'configs/importers/stack-export-importer.sh',
                   'configs/site-owner-authorized-key.pub',
                   'configs/exports/ame-config-container.json',
                   'configs/exports/ame-toolbar-editor.settings.json',
                   'configs/exports/platform-Advanced-Editor-Tools-settings.json',
                   'configs/exports/MRN-Login.png', 'configs/exports/mrn-logo-png.png',
                   'configs/exports/mrn-logo.svg'}
SCRIPTS = {'scripts/site-bootstrap.sh', 'scripts/bootstrap-new-sites.sh',
           'scripts/bootstrap-dev-enrollment.sh', 'scripts/source-distribution-lease.sh'}
MANIFESTS = {'plugins.txt', 'themes.txt', 'licenses.txt', 'importers.txt',
             'credential-files.txt', 'license-exemptions.txt', 'stack-release.lock.json',
             'bootstrap-packages.lock.json', 'component-catalog.json', 'retired-capabilities.json'}


class PublicationError(RuntimeError):
    pass


class Busy(PublicationError):
    pass


def sha(path):
    value = hashlib.sha256()
    with Path(path).open('rb') as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b''):
            value.update(chunk)
    return value.hexdigest()


def allowed(name):
    path = PurePosixPath(name)
    metadata = {'.gitignore', '.mrn-qa.env', '.eslintrc', '.stylelintrc.json'}
    if (name != path.as_posix() or path.is_absolute() or '..' in path.parts
            or any((v.startswith('.') and v not in metadata) or v == 'secrets' for v in path.parts)):
        raise PublicationError('Unsafe source path')
    if name in ALLOWED_FILES or name in ALLOWED_CONFIGS or name in SCRIPTS:
        return path
    if len(path.parts) == 2 and path.parts[0] == 'manifests' and path.parts[1] in MANIFESTS:
        return path
    if len(path.parts) == 2 and path.parts[0] == 'packages' and name.endswith('.zip'):
        return path
    if path.parts[0] in ('shared', 'mu-plugins') and len(path.parts) >= 2:
        return path
    if (len(path.parts) >= 2 and path.parts[0] == 'themes'
            and path.parts[1] in ('mrn-base-stack', 'mrn-base-stack-child',
                                  'mrn-base-stack.zip', 'mrn-base-stack-child.zip')):
        return path
    raise PublicationError('Path outside static source publication scope')


def safe(base, name):
    path = base
    for part in allowed(name).parts:
        path /= part
        if path.is_symlink():
            raise PublicationError('Source path contains a symlink')
        if path.exists() and path.stat().st_uid != os.geteuid():
            raise PublicationError('Source path has a different owner')
    if not path.resolve().is_relative_to(base.resolve()):
        raise PublicationError('Source path escaped root')
    if path.exists() and not path.is_file():
        raise PublicationError('Source target is not a regular file')
    return path


def replace(path, data, mode):
    path.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile(dir=path.parent, prefix='.fleet-publish-', delete=False) as output:
        temporary = Path(output.name)
        try:
            output.write(data)
            output.flush()
            os.fsync(output.fileno())
            temporary.chmod(mode)
            os.replace(temporary, path)
            directory = os.open(path.parent, os.O_RDONLY)
            try:
                os.fsync(directory)
            finally:
                os.close(directory)
        finally:
            temporary.unlink(missing_ok=True)


def record(path, value):
    replace(path, (json.dumps(value, sort_keys=True, indent=2) + '\n').encode(), 0o600)


def verify(base, manifest):
    files = manifest.get('files')
    if not isinstance(files, list) or not 1 <= len(files) <= 20000:
        raise PublicationError('Invalid source manifest file count')
    seen = set()
    for row in files:
        name = row['path']
        if name in seen or name == 'bootstrap-bundle.json' or not HASH.fullmatch(row['sha256']):
            raise PublicationError('Invalid/duplicate source file record')
        seen.add(name)
        path = safe(base, name)
        if (not path.is_file() or sha(path) != row['sha256'] or path.stat().st_size != row['size']
                or row['mode'] not in (0o644, 0o750)):
            raise PublicationError('Source file differs from qualification')
    if sha(safe(base, 'manifests/stack-release.lock.json')) != manifest['release_lock_sha256']:
        raise PublicationError('Published source lock differs')
    return len(files)


class SourceStore:
    def __init__(self, root, depot):
        self.root, self.depot = Path(root), Path(depot)
        for path in (self.root, self.depot):
            if path.is_symlink() or not path.is_dir() or path.stat().st_uid != os.geteuid():
                raise PublicationError('Source root/depot ownership is invalid')
        self.lock_path = self.root.parent / '.mrn-source-publication.lock'

    def preflight(self):
        path = safe(self.root, 'bootstrap-bundle.json')
        manifest = json.loads(path.read_text())
        count = verify(self.root, manifest)
        return {'status': 'pass', 'release_id': manifest['release_id'],
                'manifest_sha256': sha(path), 'lock_sha256': manifest['release_lock_sha256'],
                'files_checked': count, 'scope': 'static source only'}

    def receipt(self, release):
        if not ID.fullmatch(release):
            raise PublicationError('Invalid private release identity')
        path = self.depot / release / 'publication-receipt.json'
        if path.is_symlink() or path.parent.is_symlink():
            raise PublicationError('Invalid publication receipt path')
        if not path.exists():
            return {'status': 'not_started', 'release_id': release}
        value = json.loads(path.read_text())
        return {k: v for k, v in value.items() if k != 'before'}

    def verify_artifact(self, release, filename, checksum):
        if (not ID.fullmatch(release) or not HASH.fullmatch(checksum)
                or not re.fullmatch(r'(mrn-stack-release-[A-Za-z0-9.-]+\.zip|qualification\.json|bootstrap\.tar|source\.tar)', filename)):
            raise PublicationError('Artifact outside private source distribution scope')
        path = self.depot / release / filename
        if (path.is_symlink() or not path.is_file() or path.stat().st_uid != os.geteuid()
                or sha(path) != checksum):
            raise PublicationError('Private source artifact readback differs')
        return {'status': 'pass', 'release_id': release, 'filename': filename, 'sha256': checksum}

    def accept_artifact(self, release, filename, checksum):
        """Commit a unique temporary transfer without replacing immutable bytes."""
        if not ID.fullmatch(release) or not HASH.fullmatch(checksum):
            raise PublicationError('Invalid private source artifact identity')
        job = self.depot / release
        temporary = job / (filename + '.incoming-' + checksum)
        target = job / filename
        if target.exists():
            result = self.verify_artifact(release, filename, checksum)
            temporary.unlink(missing_ok=True)
            return result
        # Validate the lexical name before touching any received bytes.
        if not re.fullmatch(r'(mrn-stack-release-[A-Za-z0-9.-]+\.zip|qualification\.json|bootstrap\.tar|source\.tar)', filename):
            raise PublicationError('Invalid private artifact name')
        if (temporary.is_symlink() or not temporary.is_file()
                or temporary.stat().st_uid != os.geteuid() or sha(temporary) != checksum
                or job.is_symlink() or not job.is_dir()):
            raise PublicationError('Temporary artifact transfer differs')
        # link is atomic and refuses a concurrently created destination.
        os.link(temporary, target)
        target.chmod(0o600)
        temporary.unlink()
        return self.verify_artifact(release, filename, checksum)

    def qualification(self, job, manifest, archive_sha):
        path = job / 'qualification.json'
        if path.is_symlink() or not path.is_file() or path.stat().st_uid != os.geteuid():
            raise PublicationError('Private qualification receipt is missing')
        proof = json.loads(path.read_text())
        if (proof.get('status') != 'pass' or proof.get('release_id') != manifest['release_id']
                or proof.get('lock_sha256') != manifest['release_lock_sha256']
                or proof.get('bootstrap_sha256') != archive_sha
                or not COVERAGE.issubset(proof.get('coverage', []))
                or proof.get('site_writes') is not False
                or not HASH.fullmatch(proof.get('source_vector_sha256', ''))):
            raise PublicationError('Qualification does not bind the candidate source')
        fleet = list(job.glob('mrn-stack-release-*.zip'))
        if len(fleet) != 1:
            raise PublicationError('Private Fleet artifact is ambiguous/missing')
        self.verify_artifact(manifest['release_id'], fleet[0].name, proof['fleet_sha256'])
        return proof

    def enrollment(self):
        marker = self.root.parent / '.mrn-source-lease-v1.json'
        if (marker.is_symlink() or not marker.is_file()
                or marker.stat().st_uid != os.geteuid() or marker.stat().st_mode & 0o022):
            raise PublicationError('Bootstrap source lease enrollment is required')
        value = json.loads(marker.read_text())
        if value.get('schema_version') != 1 or value.get('source_root') != str(self.root):
            raise PublicationError('Source lease enrollment target differs')
        # One-time enrollment may pause legacy consumers while the first guarded
        # bundle is installed. Later calls verify the actual guarded entry points.
        if value.get('legacy_consumers_paused') is True:
            return
        for name in SCRIPTS - {'scripts/bootstrap-dev-enrollment.sh'}:
            path = safe(self.root, name)
            if not path.is_file() or 'mrn_source_distribution_lease' not in path.read_text():
                raise PublicationError('Bootstrap entry point is not source-lease guarded')
        if 'MRN_SOURCE_LEASE_PROTOCOL=1' not in safe(self.root, 'scripts/source-distribution-lease.sh').read_text():
            raise PublicationError('Source lease protocol differs')

    def stage(self, release, archive_sha, manifest_sha):
        if not ID.fullmatch(release) or not HASH.fullmatch(archive_sha) or not HASH.fullmatch(manifest_sha):
            raise PublicationError('Invalid immutable source identity')
        job = self.depot / release
        if job.is_symlink() or not job.is_dir() or job.stat().st_uid != os.geteuid():
            raise PublicationError('Invalid private source stage')
        archive = job / 'bootstrap.tar'
        if archive.is_symlink() or not archive.is_file() or sha(archive) != archive_sha:
            raise PublicationError('Immutable source archive differs')
        candidate = job / 'candidate'
        if not candidate.exists():
            temporary = job / 'extracting'
            if temporary.exists():
                if temporary.is_symlink():
                    raise PublicationError('Invalid interrupted source extraction')
                shutil.rmtree(temporary)
            temporary.mkdir(mode=0o700)
            with tarfile.open(archive) as stream:
                members = stream.getmembers()
                if len(members) > 20001 or sum(v.size for v in members) > 512 * 1024**2:
                    raise PublicationError('Source archive exceeds limits')
                seen = set()
                for item in members:
                    if not item.isfile() or item.name in seen or item.mode not in (0o644, 0o750):
                        raise PublicationError('Unsafe source archive member')
                    seen.add(item.name)
                    target = safe(temporary, item.name)
                    target.parent.mkdir(parents=True, exist_ok=True)
                    target.write_bytes(stream.extractfile(item).read())
                    target.chmod(item.mode)
            os.replace(temporary, candidate)
        if candidate.is_symlink() or sha(safe(candidate, 'bootstrap-bundle.json')) != manifest_sha:
            raise PublicationError('Source stage manifest differs')
        manifest = json.loads((candidate / 'bootstrap-bundle.json').read_text())
        if manifest['release_id'] != release:
            raise PublicationError('Source stage release differs')
        verify(candidate, manifest)
        expected = {row['path'] for row in manifest['files']} | {'bootstrap-bundle.json'}
        actual = {p.relative_to(candidate).as_posix() for p in candidate.rglob('*') if p.is_file()}
        if actual != expected:
            raise PublicationError('Undeclared source stage file')
        return job, candidate, manifest

    def recover(self, job, receipt):
        backup = job / 'rollback.tar'
        if sha(backup) != receipt['rollback_sha256']:
            raise PublicationError('Recovery archive differs')
        previous = json.loads((job / 'previous-manifest.json').read_text())
        # Validate the entire recovery boundary before restoring even one file.
        for row in receipt['before']:
            path = safe(self.root, row['path'])
            current = sha(path) if path.exists() else None
            if current not in (row['sha256'], row['candidate_sha256']):
                receipt['status'] = 'uncertain'
                record(job / 'publication-receipt.json', receipt)
                raise PublicationError('Unknown source state; operator reconciliation required')
        with tarfile.open(backup) as archive:
            for row in receipt['before']:
                path = safe(self.root, row['path'])
                if row['sha256'] is None:
                    path.unlink(missing_ok=True)
                else:
                    item = archive.getmember(row['path'])
                    replace(path, archive.extractfile(item).read(), row['mode'])
        verify(self.root, previous)
        receipt['status'] = 'rolled_back_verified'
        record(job / 'publication-receipt.json', receipt)
        return receipt

    def publish(self, release, archive_sha, manifest_sha, previous_sha, *, fault=None):
        job, candidate, manifest = self.stage(release, archive_sha, manifest_sha)
        if self.lock_path.is_symlink():
            raise PublicationError('Invalid source publication lock')
        with self.lock_path.open('a') as lease:
            os.chmod(self.lock_path, 0o600)
            try:
                fcntl.flock(lease, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except BlockingIOError as error:
                raise Busy('An installer/source publisher holds the source lease') from error
            receipt_path = job / 'publication-receipt.json'
            retry = None
            if receipt_path.exists():
                receipt = json.loads(receipt_path.read_text())
                if receipt['status'] == 'started':
                    self.recover(job, receipt)
                    return receipt
                if receipt['status'] == 'published_verified':
                    verify(self.root, manifest)
                    if sha(safe(self.root, 'bootstrap-bundle.json')) != manifest_sha:
                        raise PublicationError('Current source pointer moved after this publication')
                    return receipt
                if receipt['status'] != 'rolled_back_verified':
                    raise PublicationError('Prior failed/uncertain publication requires reconciliation')
                if (receipt['archive_sha256'] != archive_sha or receipt['manifest_sha256'] != manifest_sha
                        or receipt['previous_manifest_sha256'] != previous_sha):
                    raise PublicationError('Retry source identity differs')
                retry = receipt
            old = self.preflight()
            if old['manifest_sha256'] != previous_sha:
                raise PublicationError('Previous qualified source pointer changed')
            # Existing installations must first receive the shared-lease guard.
            # Until then, do not infer that a single ps snapshot closes the race.
            self.enrollment()
            proof = self.qualification(job, manifest, archive_sha)
            old_manifest = json.loads((self.root / 'bootstrap-bundle.json').read_text())
            old_rows = {row['path']: row for row in old_manifest['files']}
            new_rows = {row['path']: row for row in manifest['files']}
            selected = sorted(set(old_rows) | set(new_rows)) + ['bootstrap-bundle.json']
            before = []
            backup = job / 'rollback.tar'
            if backup.exists() and not retry:
                raise PublicationError('Immutable source recovery archive already exists')
            if retry:
                if sha(backup) != retry['rollback_sha256']:
                    raise PublicationError('Retry recovery archive differs')
                before = retry['before']
                for row in before:
                    path = safe(self.root, row['path'])
                    if (sha(path) if path.exists() else None) != row['sha256']:
                        raise PublicationError('Retry previous source differs')
            else:
                with tarfile.open(backup, 'w') as archive:
                    self.backup(selected, candidate, new_rows, old_rows, archive, before)
            backup.chmod(0o600)
            record(job / 'previous-manifest.json', old_manifest)
            receipt = {'schema_version': 1, 'status': 'started', 'release_id': release,
                       'previous_release_id': old['release_id'], 'previous_manifest_sha256': previous_sha,
                       'archive_sha256': archive_sha, 'manifest_sha256': manifest_sha,
                       'rollback_sha256': sha(backup), 'before': before,
                       'source_vector_sha256': proof['source_vector_sha256'],
                       'scope': 'static source only; no WordPress/provider/site writes'}
            record(receipt_path, receipt)
            try:
                for index, name in enumerate(selected):
                    path = safe(self.root, name)
                    if name in new_rows or name == 'bootstrap-bundle.json':
                        source = safe(candidate, name)
                        replace(path, source.read_bytes(), source.stat().st_mode & 0o777)
                    else:
                        path.unlink()
                    if fault:
                        fault(index, name)
                count = verify(self.root, manifest)
                if sha(self.root / 'bootstrap-bundle.json') != manifest_sha:
                    raise PublicationError('Published source manifest differs')
                receipt.update(status='published_verified', files_checked=count,
                               lock_sha256=manifest['release_lock_sha256'])
                record(receipt_path, receipt)
                return receipt
            except Exception:
                self.recover(job, receipt)
                raise

    def backup(self, selected, candidate, new_rows, old_rows, archive, before):
        for name in selected:
            path = safe(self.root, name)
            target = safe(candidate, name) if name in new_rows or name == 'bootstrap-bundle.json' else None
            if name not in old_rows and name != 'bootstrap-bundle.json' and path.exists():
                raise PublicationError('New managed source collides with an unrelated file')
            checksum = sha(path) if path.exists() else None
            mode = path.stat().st_mode & 0o777 if path.exists() else None
            before.append({'path': name, 'sha256': checksum, 'mode': mode,
                           'candidate_sha256': sha(target) if target else None})
            if path.exists():
                data = path.read_bytes()
                item = tarfile.TarInfo(name)
                item.size, item.mode = len(data), mode
                archive.addfile(item, io.BytesIO(data))


def main(argv):
    if pwd.getpwuid(os.geteuid()).pw_name != OWNER:
        raise PublicationError('Wrong source owner')
    store = SourceStore(ROOT, DEPOT)
    if argv == ['preflight']:
        result = store.preflight()
    elif len(argv) == 2 and argv[0] == 'receipt':
        result = store.receipt(argv[1])
    elif len(argv) == 4 and argv[0] == 'stage':
        job, candidate, manifest = store.stage(*argv[1:])
        result = {'status': 'staged_verified', 'release_id': manifest['release_id'],
                  'files_checked': len(manifest['files'])}
    elif len(argv) == 5 and argv[0] == 'publish':
        result = store.publish(*argv[1:])
        result = {k: v for k, v in result.items() if k != 'before'}
    elif len(argv) == 4 and argv[0] == 'verify-artifact':
        result = store.verify_artifact(*argv[1:])
    elif len(argv) == 4 and argv[0] == 'accept-artifact':
        result = store.accept_artifact(*argv[1:])
    else:
        raise PublicationError('Unsupported source-host operation')
    print(json.dumps(result, sort_keys=True))


if __name__ == '__main__':
    try:
        main(sys.argv[1:])
    except Busy as error:
        print(json.dumps({'status': 'busy', 'error': str(error)}))
        raise SystemExit(75)
    except (PublicationError, OSError, KeyError, ValueError, tarfile.TarError) as error:
        print(json.dumps({'status': 'blocked', 'error': str(error)}))
        raise SystemExit(1)
