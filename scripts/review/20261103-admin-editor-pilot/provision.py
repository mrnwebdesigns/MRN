"""One-site CloudPanel provisioning; root via the documented MRN SSH workflow.

Read review HTTP username/password JSON from stdin. Never log credential values.
Creates only a NEW empty PHP site/database; WordPress restoration is a later,
backup-gated step. Refuses any existing site, Linux user, web root or database.
"""
import json, os, pathlib, pwd, secrets, sqlite3, subprocess, sys, time
HOST = 'admin-editor-pilot.mrndev.io'
USER = 'mrndev-admin-editor'
DATABASE = 'mrneditorpilot'
DATABASE_USER = 'mrneditorpilot'
ROOT = pathlib.Path('/home') / USER / 'htdocs' / HOST
if os.geteuid() != 0:
    raise SystemExit('Run through the authorized administrator workflow')
credentials = json.load(sys.stdin)
if credentials.get('username') != 'mrn-review' or not credentials.get('password'):
    raise SystemExit('Review access credential unavailable')
cloudpanel = sqlite3.connect('file:/home/clp/htdocs/app/data/db.sq3?mode=ro', uri=True)
database_only = sys.argv[1:] == ['--database-only']
if not database_only and (cloudpanel.execute('SELECT domain_name FROM site WHERE domain_name=?', (HOST,)).fetchone() or ROOT.parent.parent.exists()):
    raise SystemExit('Existing target: refusing to overwrite or reprovision')
try:
    pwd.getpwnam(USER)
except KeyError:
    pass
else:
    if not database_only: raise SystemExit('Site account already exists')

def command(args):
    result = subprocess.run(args, capture_output=True, text=True, timeout=90)
    if result.returncode:
        # CloudPanel can echo secret arguments on failure. Do not print its output.
        raise SystemExit('Provisioning command failed: ' + args[0] + ' ' + args[1])
    return result.stdout

if not database_only:
    command(['/usr/bin/clpctl', 'site:add:php', '--domainName=' + HOST,
             '--phpVersion=8.4', '--vhostTemplate=WordPress', '--siteUser=' + USER,
             '--siteUserPassword=' + secrets.token_urlsafe(40)])
    for attempt in range(30):
        if ROOT.exists(): break
        time.sleep(1)
    if not ROOT.exists(): raise SystemExit('Site creation still pending; inspect before retry')
    account = pwd.getpwnam(USER)
    command(['/usr/bin/passwd', '-l', USER])
    ssh = pathlib.Path('/home') / USER / '.ssh'
    ssh.mkdir(mode=0o700, exist_ok=True)
    key = pathlib.Path('/home/mrndev-stack-manager/stack/configs/site-owner-authorized-key.pub').read_text().strip()
    if not key.startswith(('ssh-ed25519 ', 'ssh-rsa ')): raise SystemExit('Canonical site-owner key unavailable')
    (ssh / 'authorized_keys').write_text(key + '\n')
    for path in (ssh, ssh / 'authorized_keys'):
        os.chown(path, account.pw_uid, account.pw_gid)
    os.chmod(ssh / 'authorized_keys', 0o600)
    private = pathlib.Path('/home') / USER / 'review-private'
    private.mkdir(mode=0o700)
    os.chown(private, account.pw_uid, account.pw_gid)
    password_hash = subprocess.run(['openssl', 'passwd', '-6', '-stdin'], input=credentials['password'] + '\n', capture_output=True, text=True, check=True).stdout.strip()
    auth = pathlib.Path('/etc/nginx') / (HOST + '.htpasswd')
    auth.write_text(credentials['username'] + ':' + password_hash + '\n')
    nginx = pwd.getpwnam('www-data')
    os.chown(auth, 0, nginx.pw_gid); os.chmod(auth, 0o640)
    vhost = pathlib.Path('/etc/nginx/sites-enabled') / (HOST + '.conf')
    original = vhost.read_text()
    (private / 'initial-vhost.conf').write_text(original)
    needle = 'server_name ' + HOST + ';'
    if original.count(needle) != 2: raise SystemExit('Unexpected vhost layout; no content transferred')
    updated = original.replace(needle, needle + '\n  auth_basic "MRN editor review";\n  auth_basic_user_file ' + str(auth) + ';\n  add_header X-Robots-Tag "noindex, nofollow, noarchive" always;')
    updated = updated.replace('max_input_vars=1000;', 'max_input_vars=10000;')
    vhost.write_text(updated)
    try:
        command(['/usr/sbin/nginx', '-t'])
    except SystemExit:
        vhost.write_text(original)
        raise
    command(['systemctl', 'reload', 'nginx'])
else:
    account = pwd.getpwnam(USER)
    private = pathlib.Path('/home') / USER / 'review-private'
    if not ROOT.is_dir() or (ROOT / 'wp-config.php').exists() or not private.is_dir():
        raise SystemExit('Not the expected empty, protected pilot; refusing resume')
    row = cloudpanel.execute('SELECT domain_name FROM site WHERE domain_name=?', (HOST,)).fetchone()
    if not row or ROOT.stat().st_uid != account.pw_uid:
        raise SystemExit('Target identity mismatch')
# Empty database only. The password is placed in the site-owned private bootstrap
# file, never returned. WordPress configuration will consume it during bootstrap.
if cloudpanel.execute('SELECT name FROM "database" WHERE name=?', (DATABASE,)).fetchone():
    raise SystemExit('Database already exists; inspect before resuming')
db_password = secrets.token_urlsafe(24)
command(['/usr/bin/clpctl', 'db:add', '--domainName=' + HOST,
         '--databaseName=' + DATABASE, '--databaseUserName=' + DATABASE_USER,
         '--databaseUserPassword=' + db_password])
bootstrap = private / 'bootstrap.json'
bootstrap.write_text(json.dumps({'database': DATABASE, 'user': DATABASE_USER, 'password': db_password}))
os.chown(bootstrap, account.pw_uid, account.pw_gid); os.chmod(bootstrap, 0o600)
(ROOT / '.mrn-pilot-review').write_text('Authorized isolated admin/editor review. No automatic Stack or Fleet enrollment.\n')
print(json.dumps({'host': HOST, 'site_user': USER, 'root': str(ROOT), 'database': DATABASE,
                  'http_auth': True, 'wordpress_installed': False, 'source_content_transferred': False}))
