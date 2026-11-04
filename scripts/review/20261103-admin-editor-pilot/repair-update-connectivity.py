"""Repair the exact review clone's update checks without installing updates.

Dry-run by default. --apply requires a fresh labeled remote Updraft backup,
keeps a private configuration rollback, and changes only wp-config.php.
Other outbound destinations, mail, cron and indexing remain isolated.
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import pwd
import re
import subprocess
import tempfile
import time

HOST = 'admin-editor-pilot.mrndev.io'
USER = 'mrndev-admin-editor'
ROOT = Path('/home') / USER / 'htdocs' / HOST
PRIVATE = Path('/home') / USER / 'review-private'
BACKUP_HOSTS = ('mrn-webdesigns-backups.s3.amazonaws.com,'
                'mrn-webdesigns-backups.s3.us-east-2.amazonaws.com,'
                's3.amazonaws.com,s3.us-east-2.amazonaws.com')
UPDATE_HOSTS = ('api.wordpress.org', 'adminmenueditor.com', 'updraftplus.com')
LOG_PATH = str(PRIVATE / 'wordpress-debug.log')


def candidate(source):
    """Refuse drift; accept only the migration's exact isolation configuration."""
    old = 'define("WP_ACCESSIBLE_HOSTS", ' + json.dumps(BACKUP_HOSTS) + ');'
    new = 'define("WP_ACCESSIBLE_HOSTS", ' + json.dumps(
        BACKUP_HOSTS + ',' + ','.join(UPDATE_HOSTS)) + ');'
    debug = ('define("WP_DEBUG_DISPLAY", false);\n'
             'define("WP_DEBUG_LOG", ' + json.dumps(LOG_PATH) + ');')
    required = {
        'WP_HOME': 'https://' + HOST, 'WP_SITEURL': 'https://' + HOST,
        'WP_ENVIRONMENT_TYPE': 'development', 'WP_HTTP_BLOCK_EXTERNAL': True,
        'DISABLE_WP_CRON': True,
    }
    for name, value in required.items():
        if source.count('define(' + json.dumps(name) + ', ' + json.dumps(value) + ');') != 1:
            raise RuntimeError('Unexpected target or isolation constant: ' + name)
    if len(re.findall(r'define\s*\(\s*[\'"]WP_ACCESSIBLE_HOSTS[\'"]', source)) != 1:
        raise RuntimeError('Unexpected accessible-host definitions')
    # A completed rerun is a no-op. Any partial or independently changed state
    # must be inspected instead of being silently overwritten.
    if source.count(new) == 1 and source.count(debug) == 1:
        return source
    if source.count(old) != 1 or re.search(r'define\s*\(\s*[\'"]WP_DEBUG(?:_DISPLAY|_LOG)?[\'"]', source):
        raise RuntimeError('Unexpected HTTP or debug configuration; inspect drift')
    return source.replace(old, new + '\n' + debug, 1)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    config = ROOT / 'wp-config.php'
    if pwd.getpwuid(os.getuid()).pw_name != USER or not (ROOT / '.mrn-pilot-review').is_file():
        raise SystemExit('Wrong site owner or review marker')
    if config.is_symlink() or config.stat().st_uid != os.getuid() or PRIVATE.is_symlink():
        raise SystemExit('Unexpected configuration owner or symlink')
    source = config.read_text()
    updated = candidate(source)
    sha = lambda text: hashlib.sha256(text.encode()).hexdigest()
    result = {'host': HOST, 'file': 'wp-config.php', 'before': sha(source),
              'after': sha(updated), 'allowed_update_hosts': UPDATE_HOSTS,
              'debug_display': False, 'debug_log': LOG_PATH,
              'external_block_retained': True, 'updates_installed': False}
    if updated == source or not args.apply:
        result['status'] = 'already-applied' if updated == source else 'dry-run'
        print(json.dumps(result, indent=2))
        return
    rollback = PRIVATE / 'before-update-connectivity-20261104'
    if rollback.exists():
        raise SystemExit('Rollback exists; inspect previous attempt before retry')
    subprocess.run(['wp', '--path=' + str(ROOT), 'eval-file', str(PRIVATE / 'backup.php'),
                    'pre-admin-pilot-update-connectivity-20261104'], check=True, timeout=240)
    receipt = json.loads((PRIVATE / 'verified-backup.json').read_text())
    if (not receipt.get('valid') or receipt.get('host') != HOST
            or receipt.get('label') != 'pre-admin-pilot-update-connectivity-20261104'
            or not 0 <= time.time() - receipt.get('checked_at', 0) < 300):
        raise SystemExit('Fresh verified remote Updraft backup required')
    if config.read_text() != source:
        raise SystemExit('Configuration changed during backup; stop before write')
    rollback.mkdir(mode=0o700)
    saved = rollback / 'wp-config.php'
    with saved.open('x') as stream:
        os.fchmod(stream.fileno(), 0o600)
        stream.write(source)
    log = Path(LOG_PATH)
    if log.is_symlink():
        raise SystemExit('Unexpected debug log symlink')
    log.touch(mode=0o600, exist_ok=True)
    os.chmod(log, 0o600)
    stat = config.stat()
    # Stage secrets outside the web root; never expose a .tmp config over HTTP.
    fd, temporary = tempfile.mkstemp(prefix='update-config-', suffix='.php', dir=PRIVATE)
    try:
        with os.fdopen(fd, 'w') as stream:
            stream.write(updated)
            stream.flush()
            os.fsync(stream.fileno())
        lint = subprocess.run(['php', '-l', temporary], capture_output=True, timeout=30)
        if lint.returncode:
            raise RuntimeError('Candidate PHP lint failed')
        os.chmod(temporary, stat.st_mode & 0o777)
        os.chown(temporary, stat.st_uid, stat.st_gid)
        os.replace(temporary, config)
    finally:
        Path(temporary).unlink(missing_ok=True)
    if config.read_text() != updated:
        raise RuntimeError('Installed configuration verification failed; rollback retained')
    result.update(status='applied', backup=receipt, rollback=str(saved))
    record = PRIVATE / 'update-connectivity-receipt.json'
    record.write_text(json.dumps(result, indent=2) + '\n')
    os.chmod(record, 0o600)
    print(json.dumps(result, indent=2))


if __name__ == '__main__':
    main()
