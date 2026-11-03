"""Install the four-file account-menu compatibility fix on the exact review site."""
import hashlib, json, os, pathlib, pwd, shutil, subprocess, time
HOST = 'admin-editor-pilot.mrndev.io'
USER = 'mrndev-admin-editor'
ROOT = pathlib.Path('/home') / USER / 'htdocs' / HOST
PRIVATE = pathlib.Path('/home') / USER / 'review-private'
FILES = ['appearance.php', 'mrn-admin-shell.css', 'mrn-admin-ui-css.php', 'stack.lock']
if pwd.getpwuid(os.getuid()).pw_name != USER or not (ROOT / '.mrn-pilot-review').exists():
    raise SystemExit('Wrong review target')
sha = lambda p: hashlib.sha256(p.read_bytes()).hexdigest()
old = {item['path']: item['sha256'] for item in json.loads((PRIVATE / 'candidate.json').read_text())['files']}
new = json.loads((PRIVATE / 'account-patch' / 'manifest.json').read_text())
if set(new) != set(FILES): raise SystemExit('Unexpected patch files')
for name in FILES:
    target = ROOT / 'wp-content/mu-plugins/mrn-admin-ui-css' / name
    if sha(target) != old['mu-plugins/mrn-admin-ui-css/' + name] or sha(PRIVATE / 'account-patch' / name) != new[name]:
        raise SystemExit('Source or installed drift: ' + name)
subprocess.run(['wp', '--path=' + str(ROOT), 'eval-file', str(PRIVATE / 'backup.php'), 'pre-admin-pilot-account-menu-fix-20261103'], check=True, timeout=180)
receipt = json.loads((PRIVATE / 'verified-backup.json').read_text())
if not receipt.get('valid') or receipt.get('host') != HOST or time.time() - receipt.get('checked_at', 0) > 300:
    raise SystemExit('Fresh verified remote backup required')
rollback = PRIVATE / 'before-account-fix'
rollback.mkdir(exist_ok=False)
for name in FILES:
    target = ROOT / 'wp-content/mu-plugins/mrn-admin-ui-css' / name
    shutil.copy2(target, rollback / name)
    temporary = target.with_name(target.name + '.pilot-tmp')
    shutil.copy2(PRIVATE / 'account-patch' / name, temporary)
    os.replace(temporary, target)
    if sha(target) != new[name]: raise SystemExit('Installed mismatch: ' + name)
print(json.dumps({'host': HOST, 'backup_nonce': receipt['nonce'], 'files': new}))
