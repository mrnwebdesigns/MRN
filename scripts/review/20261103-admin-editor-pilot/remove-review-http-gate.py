"""Apply the owner's exact-site Basic Auth removal approved on 3 November.

The review frontend becomes public; native WordPress authentication, HTTPS,
noindex and Cloudflare settings are unchanged. Keep the password file for rollback.
Run only through the established privileged transport after fresh remote backup.
"""
import hashlib
import json
import os
from pathlib import Path
import subprocess
import time

HOST = 'admin-editor-pilot.mrndev.io'
PRIVATE = Path('/home/mrndev-admin-editor/review-private')
VHOST = Path('/etc/nginx/sites-enabled') / (HOST + '.conf')
BEFORE = 'd80ff4706cd91e5c601988bc4804708ec922bf3e4d20b17a6aa7ce31e0ce683b'
AFTER = 'b4cf2f6c6e717cbc87300116f8a9f6213261cdb097eea4c2e10acf9d8d008068'
LABEL = 'pre-admin-pilot-codex-review-access-20261103'

if os.geteuid() != 0:
    raise SystemExit('Use the authorized administrator workflow')
receipt = json.loads((PRIVATE / 'verified-backup.json').read_text())
age = time.time() - receipt.get('checked_at', 0)
if not (receipt.get('valid') and receipt.get('host') == HOST
        and receipt.get('label') == LABEL and 0 <= age < 300):
    raise SystemExit('Fresh exact-site verified remote backup required')

target = VHOST.resolve(strict=True)
original = target.read_bytes()
sha = lambda data: hashlib.sha256(data).hexdigest()
if sha(original) != BEFORE:
    raise SystemExit('Vhost drift or already applied; inspect before proceeding')
updated = original.replace(b'  auth_basic "MRN editor review";\n', b'').replace(
    ('  auth_basic_user_file /etc/nginx/' + HOST + '.htpasswd;\n').encode(), b'')
if sha(updated) != AFTER:
    raise SystemExit('Proposed change does not match approved four-line diff')

rollback = PRIVATE / 'before-codex-review-http-gate.conf'
with rollback.open('xb') as stream:
    os.chmod(rollback, 0o600)
    stream.write(original)
metadata = target.stat()

def replace(data):
    temporary = target.with_name(target.name + '.mrn-review-tmp')
    with temporary.open('xb') as stream:
        os.fchmod(stream.fileno(), metadata.st_mode & 0o777)
        os.fchown(stream.fileno(), metadata.st_uid, metadata.st_gid)
        stream.write(data)
        stream.flush()
        os.fsync(stream.fileno())
    os.replace(temporary, target)

subprocess.run(['/usr/sbin/nginx', '-t'], check=True, timeout=30)
replace(updated)
try:
    subprocess.run(['/usr/sbin/nginx', '-t'], check=True, timeout=30)
    subprocess.run(['systemctl', 'reload', 'nginx'], check=True, timeout=30)
except Exception:
    replace(original)
    subprocess.run(['/usr/sbin/nginx', '-t'], check=True, timeout=30)
    subprocess.run(['systemctl', 'reload', 'nginx'], check=True, timeout=30)
    raise
if sha(target.read_bytes()) != AFTER:
    raise SystemExit('Installed readback mismatch')
print(json.dumps({'host': HOST, 'status': 'applied', 'before_sha256': BEFORE,
                  'after_sha256': AFTER, 'backup': receipt, 'rollback': str(rollback),
                  'http_auth': False, 'wordpress_auth': 'unchanged',
                  'https_noindex_waf': 'unchanged'}, indent=2))
