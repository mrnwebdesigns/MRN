"""Build the owner-side runner from tracked Fleet release source."""
from pathlib import Path
import subprocess
import sys
import tempfile

from common import ReleaseError, SHA256, file_hash, git, write

PREFIX = 'tools/site-deploy/'
FILENAME = 'mrn-site-release-v1.tar'
RUNTIME = (
    'mrn-site-release', 'fleet_release.py', 'atomic_store.py', 'deploy.py',
    'host_paths.py', 'public_http.py', 'verify_public_assets.py', 'verify_release.py',
    'release_v1/README.md', 'release_v1/__init__.py', 'release_v1/artifact.py',
    'release_v1/browser.mjs', 'release_v1/cli.py', 'release_v1/controller.py', 'release_v1/host.py',
    'release_v1/journal.py', 'release_v1/proof.py', 'release_v1/requirements.txt',
    'release_v1/wordpress.php',
)
EXAMPLES = ('adapter.py', 'header-proof.mjs', 'hero-v1.php', 'migrations.json')
SUPPORT = ('backup.php', 'cache_policy.py', 'source_snapshot.py', 'wpengine.py')
MEMBERS = (*RUNTIME, *SUPPORT, *('release_v1/examples/' + name for name in EXAMPLES))


def build_runner(repo, output, archive_tree):
    repo, output = Path(repo), Path(output)
    commit = git(repo, 'rev-parse', 'HEAD')
    with tempfile.TemporaryDirectory(prefix='mrn-fleet-site-release-') as temporary:
        root = Path(temporary)
        files = {}
        for name in sorted(MEMBERS):
            member = PREFIX + name
            entry = git(repo, 'ls-tree', commit, '--', member)
            if not entry.startswith(('100644 blob ', '100755 blob ')) or not entry.endswith('\t' + member):
                raise ReleaseError('Fleet runner member must be a tracked regular file: ' + member)
            body = subprocess.check_output(['git', '-C', str(repo), 'show', commit + ':' + member])
            path = root / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(body)
            path.chmod(0o755 if name == 'mrn-site-release' else 0o644)
            files[name] = file_hash(path)
        write(root / 'runner-manifest.json', {'schema': 'mrn.fleet-site-release/v1',
                                             'source_sha': commit, 'files': files})
        (root / 'runner-manifest.json').chmod(0o644)
        for entry, phase in (('mrn-site-release', 'inspect'), ('fleet_release.py', 'status')):
            result = subprocess.run([sys.executable, '-B', root / entry, '--help'],
                                    capture_output=True, text=True, timeout=15)
            if result.returncode or phase not in result.stdout:
                raise ReleaseError('Packaged Fleet runner failed its isolated CLI check')
        archive_tree(root, output)
    checksum = file_hash(output)
    if not SHA256.fullmatch(checksum):
        raise ReleaseError('Runner checksum is invalid')
    return checksum
