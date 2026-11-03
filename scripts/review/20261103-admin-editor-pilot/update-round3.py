"""Install only the approved round-3 component files on the exact review host.

A committed candidate manifest supplies before/after hashes. All source and
installed files are checked before a fresh remote Updraft backup. Preserve an
entire rollback set outside the web root and restore all files on any failure.
"""
import hashlib
import json
import os
from pathlib import Path
import pwd
import shutil
import subprocess
import time

HOST = 'admin-editor-pilot.mrndev.io'
USER = 'mrndev-admin-editor'
ROOT = Path('/home') / USER / 'htdocs' / HOST
PRIVATE = Path('/home') / USER / 'review-private'
PATCH = PRIVATE / 'round3-patch'
FILES = [
    'mrn-admin-ui-css/appearance.php', 'mrn-admin-ui-css/appearance.js',
    'mrn-admin-ui-css/mrn-admin-shell.css', 'mrn-admin-ui-css/mrn-admin-ui-css.php',
    'mrn-admin-ui-css/stack.lock', 'mrn-editor-lockdown/assets/section-navigator.js',
    'mrn-editor-lockdown/mrn-editor-lockdown.php', 'mrn-editor-lockdown/stack.lock',
]
if pwd.getpwuid(os.getuid()).pw_name != USER or not (ROOT / '.mrn-pilot-review').is_file():
    raise SystemExit('Wrong target or site owner')
manifest = json.loads((PATCH / 'manifest.json').read_text())
if manifest.get('host') != HOST or set(manifest['files']) != set(FILES):
    raise SystemExit('Unexpected candidate scope')
sha = lambda path: hashlib.sha256(path.read_bytes()).hexdigest()
for name in FILES:
    target = ROOT / 'wp-content/mu-plugins' / name
    if target.stat().st_uid != os.getuid() or sha(target) != manifest['files'][name]['before']:
        raise SystemExit('Installed owner/hash drift: ' + name)
    if sha(PATCH / name) != manifest['files'][name]['after']:
        raise SystemExit('Candidate mismatch: ' + name)
    if name.endswith('.php'):
        subprocess.run(['php', '-l', str(PATCH / name)], check=True, timeout=30)
rollback = PRIVATE / ('before-round3-' + manifest['commit'][:12])
if rollback.exists():
    raise SystemExit('Rollback already exists; inspect previous installation before retry')
subprocess.run(['wp', '--path=' + str(ROOT), 'eval-file', str(PRIVATE / 'backup.php'),
                'pre-admin-pilot-round3-install-20261103'], check=True, timeout=180)
receipt = json.loads((PRIVATE / 'verified-backup.json').read_text())
if not receipt.get('valid') or receipt.get('host') != HOST or not 0 <= time.time() - receipt.get('checked_at', 0) < 300:
    raise SystemExit('Fresh verified remote backup required')
rollback.mkdir(mode=0o700)
for name in FILES:
    saved = rollback / name
    saved.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(ROOT / 'wp-content/mu-plugins' / name, saved)

def install(source, target):
    temporary = target.with_name(target.name + '.round3-tmp')
    with temporary.open('xb') as stream:
        os.fchmod(stream.fileno(), target.stat().st_mode & 0o777)
        stream.write(source.read_bytes())
        stream.flush()
        os.fsync(stream.fileno())
    os.replace(temporary, target)

try:
    for name in FILES:
        target = ROOT / 'wp-content/mu-plugins' / name
        install(PATCH / name, target)
        if sha(target) != manifest['files'][name]['after']:
            raise RuntimeError('Installed checksum mismatch: ' + name)
except Exception:
    for name in FILES:
        install(rollback / name, ROOT / 'wp-content/mu-plugins' / name)
    raise
result = {'host': HOST, 'commit': manifest['commit'], 'backup': receipt,
          'rollback': str(rollback), 'files': manifest['files'], 'status': 'installed'}
(PRIVATE / 'round3-install.json').write_text(json.dumps(result, indent=2) + '\n')
print(json.dumps(result, indent=2))
