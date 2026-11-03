"""Restore the authorized pilot once, only after verified remote Updraft backup.

Run as the dedicated site owner. Runtime artifacts live outside the web root.
The initial database is backed up; source and target hashes are checked before
replacement. This script intentionally refuses to rerun over an activated pilot.
"""
import hashlib,json,os,pathlib,pwd,subprocess,tarfile,time
HOST='admin-editor-pilot.mrndev.io'; USER='mrndev-admin-editor'
ROOT=pathlib.Path('/home')/USER/'htdocs'/HOST
PRIVATE=pathlib.Path('/home')/USER/'review-private'
if pwd.getpwuid(os.getuid()).pw_name!=USER or not (ROOT/'.mrn-pilot-review').exists(): raise SystemExit('Wrong site owner or target')
if (PRIVATE/'activated.json').exists(): raise SystemExit('Pilot already restored; use a separately backed-up update')
receipt=json.loads((PRIVATE/'verified-backup.json').read_text())
if not receipt.get('valid') or receipt.get('host')!=HOST or time.time()-receipt.get('checked_at',0)>3600 or not receipt.get('remote_object_verified'):
    raise SystemExit('Fresh, remotely verified Updraft receipt required')
expected={'source.sql':'8edc0d29de44b4bdd73a7161788d43cd2882c0fc48cc6f93e6047e4f42df5883','runtime.tar.gz':'1579b11aff2426d4c551278ba7d4f991246a0c259d40d4c4839a9f87caca6886'}
for name,sha in expected.items():
    if hashlib.sha256((PRIVATE/name).read_bytes()).hexdigest()!=sha: raise SystemExit('Artifact checksum mismatch: '+name)
manifest=json.loads((PRIVATE/'runtime-manifest.json').read_text())
def wp(*args):
    r=subprocess.run(['wp','--path='+str(ROOT),*args],capture_output=True,text=True,timeout=180)
    if r.returncode:
        raise SystemExit('Migration WP command failed: '+args[0]+' '+args[1]+'; inspect before resuming')
    return r.stdout
if wp('option','get','home').strip()!='https://'+HOST: raise SystemExit('Destination identity mismatch')
if wp('user','list','--field=user_login').strip()!='mrn-bootstrap': raise SystemExit('Destination is not the expected empty bootstrap')
backup_history=wp('option','get','updraft_backup_history','--format=json')
(PRIVATE/'pre-import-updraft-history.json').write_text(backup_history)
with tarfile.open(PRIVATE/'runtime.tar.gz','r:gz') as tar:
    for member in tar.getmembers():
        p=pathlib.PurePosixPath(member.name)
        if p.is_absolute() or '..' in p.parts or not member.isfile() or len(p.parts)<3 or p.parts[0]!='wp-content' or p.parts[1] not in ['plugins','themes','mu-plugins','uploads']:
            raise SystemExit('Unexpected runtime archive member')
    tar.extractall(ROOT,filter='data')
for item in manifest:
    p=ROOT/item['path']
    if not p.is_file() or hashlib.sha256(p.read_bytes()).hexdigest()!=item['sha256']: raise SystemExit('Runtime verification failed: '+item['path'])
# Preserve the original native save-integrity helper before any full-theme boot.
helper=PRIVATE/'editor-save-integrity-baseline.php'
if hashlib.sha256(helper.read_bytes()).hexdigest()!='4d06679710febf5059a22e3568e81cdad1b085cac8c465725cf244daeb59dd1f': raise SystemExit('Save-integrity helper mismatch')
hooks=ROOT/'wp-content/mu-plugins/000-local-pilot-test-hooks.php'
hooks.write_text("<?php\ndefined('ABSPATH') || exit;\nif ('admin-editor-pilot.mrndev.io' !== wp_parse_url(home_url(), PHP_URL_HOST)) { return; }\nadd_action('after_setup_theme', function () { if (!function_exists('mrn_base_stack_editor_save_guard_start')) { require_once dirname(ABSPATH,2) . '/review-private/editor-save-integrity-baseline.php'; } }, 99);\n")
wp('--skip-plugins','--skip-themes','db','import',str(PRIVATE/'source.sql'))
# Run this first with normal plugins/themes skipped: clone identity must be
# detached before any application hook runs. Mail, HTTP and cron are already off.
wp('--skip-plugins','--skip-themes','eval-file',str(PRIVATE/'normalize.php'))
wp('--skip-plugins','--skip-themes','search-replace','https://admin-editor-pilot.localhost','https://'+HOST,'--all-tables-with-prefix','--precise','--report-changed-only','--skip-columns=guid')
wp('--skip-plugins','--skip-themes','search-replace','http://admin-editor-pilot.localhost','https://'+HOST,'--all-tables-with-prefix','--precise','--report-changed-only','--skip-columns=guid')
wp('eval-file',str(PRIVATE/'apply-backup-settings.php'))
wp('option','update','updraft_backup_history',backup_history,'--format=json')
(PRIVATE/'activated.json').write_text(json.dumps({'host':HOST,'activated_at':int(time.time()),'source_sql':expected['source.sql'],'native_runtime_files':len(manifest),'backup_nonce':receipt['nonce']}))
print((PRIVATE/'activated.json').read_text())
