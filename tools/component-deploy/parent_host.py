"""Qualified CloudPanel Dev host controller; no Live or full Stack activation.

Consumes one trusted, source/checksum/target-bound plan. Its operator must have
verified the exact fresh MainWP backup before transferring this control code.
The host independently verifies that same backup before any release mutation.
"""
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
from urllib.parse import urlsplit

from parent_store import ParentStore, BOOTSTRAP, inventory, physical
from atomic_store import durable_replace
from cache_policy import canonical_pages, verify_cloudpanel_origin, verify_uncached_html
from deploy import http_check, check
from verify_public_assets import verify as verify_public

TOOLS = Path(__file__).resolve().parent


def wp(root, body, nonce=None, skip_themes=True):
    environment = {**os.environ, 'WP_CLI_PHP_ARGS': '-d memory_limit=512M', 'MRN_COMPONENT_RECOVERY': '1'}
    if nonce:
        environment['MRN_PARENT_BACKUP_NONCE'] = nonce
    result = subprocess.run(['wp', '--path=' + root] + (['--skip-themes'] if skip_themes else []) + ['eval', body],
                            stdout=subprocess.PIPE, stderr=subprocess.PIPE, env=environment, universal_newlines=True)
    lines = [line[11:] for line in result.stdout.splitlines() if line.startswith('MRN_RESULT=')]
    if result.returncode or len(lines) != 1:
        raise RuntimeError('WordPress identity or backup verification failed; no raw diagnostic output disclosed')
    return json.loads(lines[0])


def other_stack(content):
    files = {}
    for directory in [content / 'mu-plugins', *(p for p in (content / 'plugins').iterdir() if p.name.startswith('mrn-'))]:
        for name, checksum in inventory(directory).items():
            path = (directory / name).relative_to(content).as_posix()
            if path != 'mu-plugins/' + BOOTSTRAP:
                files[path] = checksum
    return files


def preflight(plan):
    host = urlsplit(plan['url'])
    if (plan.get('environment') != 'dev' or host.scheme != 'https' or not host.hostname.endswith('.mrndev.io')
            or host.netloc != host.hostname or host.path or host.query or host.fragment):
        raise ValueError('Only an explicit canonical CloudPanel Dev target is qualified')
    canonical_pages(plan['url'], plan['pages'])
    root = physical(plan['root'])
    content = physical(root / 'wp-content')
    identity = wp(str(root), '''
$plugins=(array)get_option('active_plugins',array());
echo 'MRN_RESULT='.wp_json_encode(array('url'=>untrailingslashit(get_option('home')),
'template'=>get_template(),'child'=>get_stylesheet(),'root'=>realpath(ABSPATH),
'wp_cache'=>defined('WP_CACHE')&&WP_CACHE,'advanced_cache'=>file_exists(WP_CONTENT_DIR.'/advanced-cache.php'),
'cache_plugins'=>array_values(array_filter($plugins,static function($p){return (bool)preg_match('/cache|rocket|autoptimize|perfmatters|nitropack|breeze|hummingbird/i',$p);} ))));
''')
    if any(identity.get(key) != plan[key] for key in ['url', 'root', 'child']) or identity['template'] != 'mrn-base-stack':
        raise ValueError('Exact WordPress identity changed')
    verify_cloudpanel_origin(Path.home() / '.varnish-cache/settings.json', identity)
    verify_uncached_html(plan['url'], plan['pages'])
    parent = inventory(content / 'themes/mrn-base-stack')
    child = inventory(content / 'themes' / plan['child'])
    child_pointer = json.loads((physical(plan['child_state'], private=True) / 'current.json').read_text()) if plan.get('child_state') else None
    preserved = {'parent': parent, 'child': child, 'child_pointer': child_pointer}
    if preserved != plan['preserved'] or other_stack(content) != plan['other_stack']:
        raise ValueError('Reviewed baseline or another Stack component changed')
    return root, content, preserved


def public_check(plan, store, selected):
    cache = verify_uncached_html(plan['url'], plan['pages'])
    http_check(plan['url'] + '/wp-json/', rest=True)
    if selected:
        identity = selected['components']['mrn-base-stack']['artifact_sha256']
        store.release(identity)
        manifest = store.read(store.state / 'releases' / identity / 'component/mrn-base-stack/mrn-assets.json')
        assets = verify_public(manifest, plan['pages'])
        # Theme functions load for this readback; unlike recovery checks, this
        # request must execute the selected parent and original child release.
        environment = dict(os.environ)
        environment.pop('MRN_COMPONENT_RECOVERY', None)
        program = "echo 'MRN_RESULT='.wp_json_encode(array('template'=>get_template_directory(),'child'=>get_stylesheet_directory(),'theme_version'=>wp_get_theme()->parent()->get('Version'),'effects'=>function_exists('mrn_base_stack_get_tab_switch_effect_choices')?mrn_base_stack_get_tab_switch_effect_choices():array()));"
        result = subprocess.run(['wp', '--path=' + str(store.root), 'eval', program], env=environment,
                                stdout=subprocess.PIPE, stderr=subprocess.PIPE, universal_newlines=True)
        lines = [line[11:] for line in result.stdout.splitlines() if line.startswith('MRN_RESULT=')]
        if result.returncode or len(lines) != 1:
            raise ValueError('Selected parent/child bootstrap did not load')
        runtime = json.loads(lines[0])
        expected = str(store.state / 'releases' / identity / 'component/mrn-base-stack')
        if runtime['template'] != expected or (plan.get('required_effect') and plan['required_effect'] not in runtime['effects']):
            raise ValueError('Runtime does not execute the exact selected parent or field choices')
        return {'cache': cache, 'assets': assets, 'runtime': runtime}
    for page in plan['pages']:
        http_check(page)
    return {'cache': cache, 'legacy_parent_restored': True}


def execute(plan):
    root, content, preserved = preflight(plan)
    nonce = check(plan.get('backup_nonce', ''), r'[a-f0-9]{12}', 'MainWP backup nonce')
    backup = wp(str(root), (TOOLS / 'verify_backup.php').read_text()[5:], nonce=nonce)
    if backup.get('valid') is not True or backup.get('nonce') != nonce:
        raise ValueError('Fresh MainWP backup is required before state provisioning')
    state = Path(plan['state'])
    check(str(state), r'/[A-Za-z0-9_./-]+', 'private parent state')
    if state == root or root in state.parents or state.resolve() != state:
        raise ValueError('Private parent state is public or aliased')
    if not state.exists():
        state.mkdir(parents=True, mode=0o700)
    store = ParentStore(root, state, plan['child'], plan.get('child_state'))
    receipt = {'schema': 1, 'url': plan['url'], 'environment': 'dev', 'backup': backup,
               'status': 'in-progress', 'source_sha': plan.get('source_sha'), 'artifact_sha256': plan.get('artifact_sha256')}
    with store.lock():
        before = store.pointer()
        if before != plan.get('expected_current'):
            raise ValueError('Parent pointer changed since plan approval')
        binding = {key: plan[key] for key in ['url', 'environment', 'root', 'child', 'child_state']}
        record = state / 'site.json'
        if record.exists() and store.read(record) != binding:
            raise ValueError('Private state belongs to another target')
        if not record.exists():
            durable_replace(record, (json.dumps(binding, sort_keys=True) + '\n').encode())
        selected = None
        try:
            if plan.get('disable'):
                store.disable(before, preserved)
                receipt['verification'] = public_check(plan, store, None)
                receipt['status'] = 'legacy-restored'
            else:
                store.journal('stage', before)
                store.stage(plan['archive'], plan['artifact_sha256'], plan['source_sha'], plan['source_path'])
                selected = store.activate(plan['artifact_sha256'], before, preserved, restore_managed=bool(plan.get('restore_managed')))
                receipt['verification'] = public_check(plan, store, selected)
                if plan.get('exercise_rollback'):
                    # The verified backup protects this bounded code transaction.
                    # The public parent was never overwritten; removing the MU
                    # selection returns to the exact original request behavior.
                    store.disable(selected, preserved)
                    receipt['rollback'] = public_check(plan, store, None)
                    selected = store.activate(plan['artifact_sha256'], selected, preserved, restore_managed=True)
                    receipt['reactivation'] = public_check(plan, store, selected)
                receipt['status'] = 'public-verified'
            store.unchanged(preserved)
            if other_stack(content) != plan['other_stack']:
                raise ValueError('An unrelated Stack component changed during deployment')
        except Exception as error:
            receipt['error'] = type(error).__name__ + ': ' + str(error)
            try:
                if selected and store.bootstrap.exists():
                    if before is None:
                        store.disable(selected, preserved)
                        receipt['recovery'] = public_check(plan, store, None)
                    else:
                        previous_id = before['components']['mrn-base-stack']['artifact_sha256']
                        store.activate(previous_id, selected, preserved)
                        receipt['recovery'] = public_check(plan, store, before)
                receipt['status'] = 'failed-recovered' if selected else 'failed-before-activation'
            except Exception as recovery:
                receipt.update(status='recovery-required', recovery_error=type(recovery).__name__ + ': ' + str(recovery))
        receipt.update(previous=before, current=store.pointer(), preserved_parent_and_child=True,
                       stack_lock_advanced=False, other_stack_preserved=other_stack(content) == plan['other_stack'])
        durable_replace(state / 'intent.json', (json.dumps({'schema': 1, 'status': receipt['status'],
                        'previous': before, 'current': store.pointer()}, sort_keys=True) + '\n').encode())
        durable_replace(state / ('receipt-' + str(__import__('time').time_ns()) + '.json'), (json.dumps(receipt, sort_keys=True) + '\n').encode())
    return receipt


if __name__ == '__main__':
    result = execute(json.load(sys.stdin))
    print(json.dumps(result))
    raise SystemExit(0 if result['status'] in ('public-verified', 'legacy-restored') else 1)
