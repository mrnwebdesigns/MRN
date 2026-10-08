#!/usr/bin/env python3
"""Parent-only Dev adapter and explicit Nexcess Live qualification candidate.

MainWP has no parent-only install ability. This explicitly selected operator
adapter is not a silent fallback or a new Dashboard API. Preflight is read-only;
confirmed adoption verifies the MainWP-verified backup before the first upload.
"""
import argparse
import base64
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
import re
import shlex
import subprocess
import sys
import time
from urllib.parse import urlsplit

from verify import verify
from parent_target import validate_route

TOOLS = Path(__file__).resolve().parent
SHARED = TOOLS.parent / 'site-deploy'
FILES = {'component-deploy/' + name: TOOLS / name for name in
         ('parent_host.py', 'parent_target.py', 'parent_store.py', 'runtime.php', 'bootstrap.php', 'verify.py', 'verify_backup.php')}
FILES.update({'site-deploy/' + name: SHARED / name for name in
              ('atomic_store.py', 'cache_policy.py', 'deploy.py', 'verify_release.py', 'verify_public_assets.py', 'html_cache.php')})


def ssh(login, command, body=None, allow_result=False):
    if not re.fullmatch(r'[a-zA-Z0-9_-]+@[a-zA-Z0-9.-]+', login):
        raise ValueError('Use one explicit site-owner SSH login')
    result = subprocess.run(['ssh', '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes', login, command],
                            input=body, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    if result.returncode and not (allow_result and result.stdout.strip().startswith(b'{')):
        raise RuntimeError('Site-owner operation failed; private diagnostics withheld')
    return result.stdout


INSPECT = '''
import hashlib,json,os,pathlib,subprocess,sys
p=json.loads(sys.stdin.readline());root=pathlib.Path(p['root']);content=root/'wp-content'
def tree(root):
 out={}
 for path in sorted(root.rglob('*')):
  if path.is_symlink():raise ValueError('Unexpected alias')
  if path.is_file():out[path.relative_to(root).as_posix()]=hashlib.sha256(path.read_bytes()).hexdigest()
 return out
program="echo 'MRN_RESULT='.wp_json_encode(array('url'=>untrailingslashit(get_option('home')),'template'=>get_template(),'child'=>get_stylesheet(),'root'=>realpath(ABSPATH),'environment_type'=>wp_get_environment_type()));"
r=subprocess.run(['wp','--path='+p['root'],'--skip-themes','eval',program],stdout=subprocess.PIPE,stderr=subprocess.PIPE,env=dict(os.environ,MRN_COMPONENT_RECOVERY='1'),universal_newlines=True)
lines=[x[11:] for x in r.stdout.splitlines() if x.startswith('MRN_RESULT=')]
if r.returncode or len(lines)!=1:raise ValueError('Identity unavailable')
identity=json.loads(lines[0])
if any(identity[k]!=p[k] for k in ['url','root','child']) or identity['template']!='mrn-base-stack':raise ValueError('Exact target differs')
if p['environment']=='live' and identity['environment_type']!='production':raise ValueError('Live WordPress environment is not production')
state=pathlib.Path(p['state']);childstate=pathlib.Path(p['child_state']) if p.get('child_state') else None
for path in [root,content,content/'themes/mrn-base-stack',content/'themes'/p['child']]+([childstate] if childstate else []):
 if path.resolve()!=path or not path.is_dir():raise ValueError('Physical target required')
if state.resolve()!=state or state==root or root in state.parents:raise ValueError('Private parent storage required')
parent=tree(content/'themes/mrn-base-stack');child=tree(content/'themes'/p['child'])
pointer=json.loads((childstate/'current.json').read_text()) if childstate else None
others={}
for directory in [content/'mu-plugins']+[x for x in (content/'plugins').iterdir() if x.name.startswith('mrn-')]:
 for name,sha in tree(directory).items():
  key=(directory/name).relative_to(content).as_posix()
  if key!='mu-plugins/000-mrn-parent-release.php':others[key]=sha
current=json.loads((state/'current.json').read_text()) if (state/'current.json').is_file() else None
intent=json.loads((state/'intent.json').read_text()) if (state/'intent.json').is_file() else None
runtime=state/'control/runtime.php'
html_scope=None
if p['environment']=='live':
 request={'url':p['url'],'provider':'nexcess','action':'inspect','urls':p['pages']}
 cache=subprocess.run(['wp','--path='+p['root'],'eval',p['html_cache_code']],input=json.dumps(request),stdout=subprocess.PIPE,stderr=subprocess.PIPE,env=dict(os.environ,MRN_HTML_CACHE_STDIN='1'),universal_newlines=True)
 results=[x[11:] for x in cache.stdout.splitlines() if x.startswith('MRN_RESULT=')]
 if cache.returncode or len(results)!=1:raise ValueError('Live HTML scope unavailable')
 html_scope=json.loads(results[0])['urls']
print(json.dumps({'preserved':{'parent':parent,'child':child,'child_pointer':pointer},'other_stack':others,'expected_current':current,'intent':intent,'html_scope':html_scope,'control_runtime_sha256':hashlib.sha256(runtime.read_bytes()).hexdigest() if runtime.is_file() else None}))
'''


def validate_target(plan):
    url = urlsplit(plan['url'])
    provider = validate_route(plan)
    login = plan['ssh_login']
    user = login.split('@')[0]
    if provider == 'cloudpanel' and plan['root'] != '/home/' + user + '/htdocs/' + url.hostname:
        raise ValueError('CloudPanel owner, hostname and WordPress root must agree')
    for key in ['state'] + (['child_state'] if plan.get('child_state') else []):
        if not re.fullmatch(r'/[a-zA-Z0-9_./-]+', plan.get(key, '')) or '/..' in plan[key]:
            raise ValueError('Explicit physical private storage required')
    mainwp = plan.get('mainwp', {})
    if (mainwp.get('connected') is not True or mainwp.get('dashboardHost') != 'wpcontrol.mrndev.io'
            or not mainwp.get('abilitiesCount') or mainwp.get('site_url', '').rstrip('/') != plan['url']
            or not isinstance(mainwp.get('site_id'), int) or not mainwp.get('synced_at')):
        raise ValueError('Fresh exact-site MainWP resolution and sync evidence required')
    synced = datetime.fromisoformat(mainwp['synced_at'].replace('Z', '+00:00'))
    age = (datetime.now(timezone.utc) - synced).total_seconds()
    if not 0 <= age < 900:
        raise ValueError('MainWP sync evidence must be less than 15 minutes old')


def inspect(plan):
    validate_target(plan)
    # A bound plan can contain thousands of inventory records. Only these
    # identity fields are needed remotely; keep it below per-argument limits.
    target = {key: plan[key] for key in ('root', 'url', 'state', 'child', 'child_state', 'environment')}
    if plan['environment'] == 'live':
        target.update(pages=plan['pages'], html_cache_code=(SHARED / 'html_cache.php').read_text()[5:])
    encoded = base64.b64encode((json.dumps(target) + '\n').encode()).decode()
    # The program and plan are literals passed to Python; no request strings are
    # interpolated into executable shell syntax without shlex quoting.
    program = 'import base64,io,sys;sys.stdin=io.StringIO(base64.b64decode(' + repr(encoded) + ').decode());exec(' + repr(INSPECT) + ')'
    return json.loads(ssh(plan['ssh_login'], shlex.join(['python3', '-c', program])))


def transfer_program(state, job, payload, hashes):
    """Explicit modes defeat a provider's inherited directory ACL mask."""
    program = 'import base64,hashlib,pathlib,os;os.umask(0o077);state=pathlib.Path(' + repr(state) + ');root=pathlib.Path(' + repr(job) + ')\n'
    program += "if state.resolve()!=state:raise ValueError('Private state is aliased')\n"
    program += "if not state.exists():state.mkdir(parents=True,mode=0o700)\n"
    program += "if state.stat().st_uid!=os.geteuid():raise ValueError('Private state has another owner')\n"
    # First-transfer retries may leave only this adapter's verified private jobs.
    # Never repair permissions on an unknown or already adopted state tree.
    program += "if state.stat().st_mode&0o077:\n if set(p.name for p in state.iterdir())-{'jobs'}:raise ValueError('Unknown private state permissions')\n state.chmod(0o700)\n"
    program += "root.mkdir(parents=True,mode=0o700);root.chmod(0o700)\nfiles=" + repr(payload) + ';hashes=' + repr(hashes) + '\n'
    program += "for name,value in files.items():\n body=base64.b64decode(value);assert hashlib.sha256(body).hexdigest()==hashes[name];path=root/name;path.parent.mkdir(parents=True,exist_ok=True);path.open('xb').write(body)\n"
    return program


def run(plan, confirm=False):
    if not confirm:
        return {'status': 'read-only-preflight', **inspect(plan)}
    validate_target(plan)
    before = inspect(plan)
    for name in ('preserved', 'other_stack', 'expected_current'):
        if before[name] != plan.get(name):
            raise ValueError('Target changed after the reviewed preflight')
    if plan['environment'] == 'live' and before.get('html_scope') != plan.get('html_urls'):
        raise ValueError('Live HTML scope changed after the reviewed preflight')
    if (plan['environment'] == 'live' and before['expected_current'] is not None
            and before.get('control_runtime_sha256') != hashlib.sha256((TOOLS / 'runtime.php').read_bytes()).hexdigest()):
        raise ValueError('Existing Live parent control requires separate runtime qualification')
    if (before.get('intent') or {}).get('status') == 'in-progress' and not plan.get('recover_verified_outcome'):
        raise ValueError('An unfinished transaction requires explicit outcome inspection')
    if before['expected_current'] is None and plan['environment'] == 'dev' and (not plan.get('qualify_dev') or not plan.get('exercise_rollback')):
        raise ValueError('First adoption requires explicit Dev qualification and rollback exercise')
    if not plan.get('disable'):
        verify(plan['archive'], plan['artifact_sha256'], plan['source_sha'], plan['source_path'],
               'mrn-base-stack', 'parent-theme', 'functions.php')
    nonce = plan.get('backup_nonce', '')
    if not re.fullmatch(r'[a-f0-9]{12}', nonce):
        raise ValueError('A fresh MainWP backup nonce is required')
    body = (TOOLS / 'verify_backup.php').read_text()[5:]
    command = shlex.join(['env', 'MRN_COMPONENT_RECOVERY=1', 'MRN_PARENT_BACKUP_NONCE=' + nonce,
                          'wp', '--path=' + plan['root'], '--skip-themes', 'eval', body])
    evidence = ssh(plan['ssh_login'], command).decode()
    lines = [line[11:] for line in evidence.splitlines() if line.startswith('MRN_RESULT=')]
    if len(lines) != 1 or json.loads(lines[0]).get('valid') is not True:
        raise ValueError('Verified remote backup required before tool transfer')
    job = plan['state'] + '/jobs/' + str(time.time_ns())
    payload = {name: base64.b64encode(path.read_bytes()).decode() for name, path in FILES.items()}
    if not plan.get('disable'):
        payload['component-deploy/release.zip'] = base64.b64encode(Path(plan['archive']).read_bytes()).decode()
    hashes = {name: hashlib.sha256(base64.b64decode(body)).hexdigest() for name, body in payload.items()}
    transfer = transfer_program(plan['state'], job, payload, hashes)
    ssh(plan['ssh_login'], 'python3 -', transfer.encode())
    remote = {**plan, 'archive': job + '/component-deploy/release.zip'}
    command = shlex.join(['python3', job + '/component-deploy/parent_host.py'])
    return json.loads(ssh(plan['ssh_login'], command, json.dumps(remote).encode(), allow_result=True))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('plan', type=Path)
    parser.add_argument('--confirm', action='store_true')
    parser.add_argument('--receipt', type=Path, required=True)
    args = parser.parse_args()
    result = run(json.loads(args.plan.read_text()), confirm=args.confirm)
    args.receipt.write_text(json.dumps(result, indent=2) + '\n')
    print(json.dumps({key: result.get(key) for key in ('status', 'url', 'source_sha', 'artifact_sha256', 'other_stack_preserved', 'error', 'recovery_error')}))
