"""Bootstrap only the newly provisioned, empty, protected review destination.

Run as root. Requires the exact provision receipt marker and no wp-config.php.
Uses existing MRN backup defaults but assigns this site's isolated S3 prefix.
No source database import or pilot component installation is performed here.
"""
import hashlib,json,os,pathlib,pwd,secrets,subprocess,tarfile
HOST='admin-editor-pilot.mrndev.io'; USER='mrndev-admin-editor'
ROOT=pathlib.Path('/home')/USER/'htdocs'/HOST
PRIVATE=pathlib.Path('/home')/USER/'review-private'
if os.geteuid()!=0 or not (ROOT/'.mrn-pilot-review').exists() or (ROOT/'wp-config.php').exists():
    raise SystemExit('Expected protected empty destination unavailable')
archive=PRIVATE/'bootstrap.tar.gz'
if hashlib.sha256(archive.read_bytes()).hexdigest()!='32fdfe363f0fc5ba8ad3b94b67329dbaf5c27c64453c39cb1fcee338ef0b9a0c':
    raise SystemExit('Bootstrap archive checksum mismatch')
account=pwd.getpwnam(USER)
with tarfile.open(archive,'r:gz') as tar:
    for member in tar.getmembers():
        p=pathlib.PurePosixPath(member.name)
        if p.is_absolute() or '..' in p.parts or not member.isfile(): raise SystemExit('Unexpected archive member')
    tar.extractall(ROOT,filter='data')
credentials=json.loads((PRIVATE/'bootstrap.json').read_text())
if credentials['database']!='mrneditorpilot' or credentials['user']!='mrneditorpilot': raise SystemExit('Database identity mismatch')
config="<?php\n"
constants={'DB_NAME':credentials['database'],'DB_USER':credentials['user'],'DB_PASSWORD':credentials['password'],'DB_HOST':'127.0.0.1','DB_CHARSET':'utf8mb4','DB_COLLATE':'','WP_HOME':'https://'+HOST,'WP_SITEURL':'https://'+HOST,'WP_ENVIRONMENT_TYPE':'development','WP_HTTP_BLOCK_EXTERNAL':True,'WP_ACCESSIBLE_HOSTS':'mrn-webdesigns-backups.s3.amazonaws.com,mrn-webdesigns-backups.s3.us-east-2.amazonaws.com,s3.amazonaws.com,s3.us-east-2.amazonaws.com','DISABLE_WP_CRON':True,'DISALLOW_FILE_EDIT':True,'MRN_EDITOR_EXPERIENCE_ENABLED':True}
for name in ['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT']: constants[name]=secrets.token_urlsafe(48)
for k,v in constants.items(): config+='define('+json.dumps(k)+', '+json.dumps(v)+');\n'
config+="$table_prefix = 'wp_';\nif (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }\nrequire_once ABSPATH . 'wp-settings.php';\n"
(ROOT/'wp-config.php').write_text(config);os.chmod(ROOT/'wp-config.php',0o600)
mu=ROOT/'wp-content/mu-plugins';mu.mkdir(parents=True,exist_ok=True)
(mu/'000-local-pilot-safety.php').write_text("<?php\nadd_filter('pre_wp_mail', '__return_false', PHP_INT_MAX);\nadd_filter('pre_option_blog_public', '__return_zero');\n")
for p in [ROOT,*ROOT.rglob('*')]: os.chown(p,account.pw_uid,account.pw_gid)
def wp(*args,input=None):
    r=subprocess.run(['runuser','-u',USER,'--','wp','--path='+str(ROOT),*args],input=input,capture_output=True,text=True,timeout=120)
    if r.returncode: raise SystemExit('Bootstrap WP command failed: '+args[0]+' '+args[1])
    return r.stdout
wp('core','install','--url=https://'+HOST,'--title=MRN Admin Editor Pilot','--admin_user=mrn-bootstrap','--admin_email=pilot-admin@example.invalid','--skip-email','--prompt=admin_password',input=secrets.token_urlsafe(24)+'\n')
wp('plugin','activate','updraftplus')
settings=json.loads(pathlib.Path('/home/mrndev-stack-manager/stack/secrets/updraftplus-settings-mrn-plugin-stack.json').read_text())['data']
if not settings.get('updraft_s3',{}).get('settings'): raise SystemExit('Canonical S3 defaults unavailable')
for value in settings['updraft_s3']['settings'].values():
    if not value.get('accesskey') or not value.get('secretkey'): raise SystemExit('Canonical S3 credentials missing')
    value['path']='mrn-webdesigns-backups/sites/admin-editor-pilot-mrndev-io'
    value['instance_enabled']=1
settings.update({'updraft_service':['s3'],'updraft_interval':'manual','updraft_interval_database':'manual','updraft_retain':23,'updraft_retain_db':100,'updraft_delete_local':1,'updraft_include_wpcore':0,'updraft_email':[],'updraft_report_warningsonly':[],'updraft_report_wholebackup':[],'updraft_report_dbbackup':[]})
rules=[{'after-howmany':str(days),'after-period':'86400','every-howmany':str(interval),'every-period':'86400'} for days,interval in [(7,7),(36,30)]]
settings['updraft_retain_extrarules']={'db':rules,'files':rules}
settings_file=PRIVATE/'backup-settings.json';settings_file.write_text(json.dumps(settings));os.chown(settings_file,account.pw_uid,account.pw_gid);os.chmod(settings_file,0o600)
php="<?php\nif ('admin-editor-pilot.mrndev.io' !== wp_parse_url(home_url(), PHP_URL_HOST)) { throw new RuntimeException('Wrong target'); }\nforeach(json_decode(file_get_contents(dirname(ABSPATH,2).'/review-private/backup-settings.json'),true) as $key=>$value) { update_option($key,$value); }\nupdate_option('blog_public',0);\necho 'Isolated review backup settings applied.';\n"
# ROOT is /home/user/htdocs/domain; its user home is dirname(ABSPATH,2).
helper=PRIVATE/'apply-backup-settings.php';helper.write_text(php);os.chown(helper,account.pw_uid,account.pw_gid);os.chmod(helper,0o600)
wp('eval-file',str(helper))
(PRIVATE/'bootstrap.json').unlink()
print(json.dumps({'host':HOST,'wordpress':wp('core','version').strip(),'backup_prefix':'sites/admin-editor-pilot-mrndev-io','mail_blocked':True,'cron_disabled':True,'source_imported':False,'backup_gate':'next-required'}))
