"""CloudPanel Dev controller and manual Nexcess Live qualification candidate.

Consumes one trusted, source/checksum/target-bound plan. Its operator must have
verified the exact fresh MainWP backup before transferring this control code.
The host independently verifies that same backup before any release mutation.
"""
import hashlib
from html.parser import HTMLParser
import json
import os
from pathlib import Path
import subprocess
import sys
from urllib.parse import urlsplit

from parent_store import ParentStore, BOOTSTRAP, inventory, physical
from parent_target import validate_route
from atomic_store import durable_replace
from cache_policy import canonical_pages, verify_cloudpanel_origin, verify_uncached_html, head
from deploy import http_check, check
from verify_public_assets import verify as verify_public, fetch

TOOLS = Path(__file__).resolve().parent


def rendered_tab_effects(html):
    class Roots(HTMLParser):
        def __init__(self):
            super().__init__()
            self.effects = []

        def handle_starttag(self, tag, attributes):
            attributes = dict(attributes)
            if 'data-mrn-tabbed-layout' in attributes:
                prefix = 'mrn-tabbed-layout--transition-'
                self.effects.extend(value[len(prefix):] for value in attributes.get('class', '').split() if value.startswith(prefix))
    parser = Roots()
    parser.feed(html)
    return parser.effects


def wp(root, body, nonce=None, skip_themes=True, stdin=None):
    environment = {**os.environ, 'WP_CLI_PHP_ARGS': '-d memory_limit=512M', 'MRN_COMPONENT_RECOVERY': '1'}
    if nonce:
        environment['MRN_PARENT_BACKUP_NONCE'] = nonce
    if stdin is not None:
        environment['MRN_HTML_CACHE_STDIN'] = '1'
    result = subprocess.run(['wp', '--path=' + root] + (['--skip-themes'] if skip_themes else []) + ['eval', body],
                            input=stdin, stdout=subprocess.PIPE, stderr=subprocess.PIPE, env=environment, universal_newlines=True)
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


def html_cache(plan, action):
    request = {'url': plan['url'], 'provider': 'nexcess', 'action': action,
               'urls': plan.get('_html_scope', plan['pages'])}
    return wp(plan['root'], (TOOLS.parent / 'site-deploy/html_cache.php').read_text()[5:],
              skip_themes=action != 'inspect', stdin=json.dumps(request))


def live_html_scope(plan):
    scope = html_cache(plan, 'inspect')
    urls = scope.get('urls')
    if not isinstance(urls, list) or not urls or len(urls) > 20000 or len(set(urls)) != len(urls):
        raise ValueError('Live HTML inventory must be bounded, nonempty and unique')
    for start in range(0, len(urls), 500):
        canonical_pages(plan['url'], urls[start:start + 500])
    if scope['urls'] != plan.get('html_urls'):
        raise ValueError('Live HTML inventory differs from the reviewed scope')
    plan['_html_scope'] = scope['urls']
    return scope


def live_public_cache(plan, selected):
    receipt = html_cache(plan, 'refresh')
    if receipt.get('refreshed_urls') != plan['_html_scope']:
        raise ValueError('Live HTML refresh did not cover the reviewed scope')
    expected = selected['components']['mrn-base-stack']['artifact_sha256'] if selected else None
    samples = []
    for page in plan['pages']:
        for phase in ('first', 'warm'):
            headers = head(page)
            if 'text/html' not in headers.get('content-type', '').lower():
                raise ValueError('Live public verification expected HTML')
            html = fetch(page, {'text/html'}).decode('utf-8')
            if parent_attestations(html) != ([expected] if expected else []):
                raise ValueError('Live public HTML did not select the expected parent')
            if headers.get('x-mrn-parent-release') not in (None, expected):
                raise ValueError('Live response header and HTML disagree about the selected parent')
            samples.append({'url': page, 'phase': phase, 'headers': headers})
    return {**receipt, 'samples': samples}


def parent_attestations(html):
    class Metadata(HTMLParser):
        def __init__(self):
            super().__init__()
            self.releases = []

        def handle_starttag(self, tag, attributes):
            values = dict(attributes)
            if tag == 'meta' and values.get('name') == 'mrn-parent-release':
                self.releases.append(values.get('content'))
    metadata = Metadata()
    metadata.feed(html)
    return metadata.releases


def preflight(plan):
    provider = validate_route(plan)
    canonical_pages(plan['url'], plan['pages'])
    root = physical(plan['root'])
    content = physical(root / 'wp-content')
    identity = wp(str(root), '''
$plugins=(array)get_option('active_plugins',array());
echo 'MRN_RESULT='.wp_json_encode(array('url'=>untrailingslashit(get_option('home')),
'template'=>get_template(),'child'=>get_stylesheet(),'root'=>realpath(ABSPATH),
'environment_type'=>wp_get_environment_type(),
'wp_cache'=>defined('WP_CACHE')&&WP_CACHE,'advanced_cache'=>file_exists(WP_CONTENT_DIR.'/advanced-cache.php'),
'cache_plugins'=>array_values(array_filter($plugins,static function($p){return (bool)preg_match('/cache|rocket|autoptimize|perfmatters|nitropack|breeze|hummingbird/i',$p);} ))));
''')
    if any(identity.get(key) != plan[key] for key in ['url', 'root', 'child']) or identity['template'] != 'mrn-base-stack':
        raise ValueError('Exact WordPress identity changed')
    if provider == 'cloudpanel':
        verify_cloudpanel_origin(Path.home() / '.varnish-cache/settings.json', identity)
        verify_uncached_html(plan['url'], plan['pages'])
    else:
        if identity.get('environment_type') != 'production':
            raise ValueError('Live requires the exact production WordPress environment')
        live_html_scope(plan)
    parent = inventory(content / 'themes/mrn-base-stack')
    child = inventory(content / 'themes' / plan['child'])
    child_pointer = json.loads((physical(plan['child_state'], private=True) / 'current.json').read_text()) if plan.get('child_state') else None
    preserved = {'parent': parent, 'child': child, 'child_pointer': child_pointer}
    if preserved != plan['preserved'] or other_stack(content) != plan['other_stack']:
        raise ValueError('Reviewed baseline or another Stack component changed')
    return root, content, preserved


def public_check(plan, store, selected):
    cache = live_public_cache(plan, selected) if plan.get('environment') == 'live' else verify_uncached_html(plan['url'], plan['pages'])
    http_check(plan['url'] + '/wp-json/', rest=True)
    if selected:
        identity = selected['components']['mrn-base-stack']['artifact_sha256']
        store.release(identity)
        manifest = store.read(store.state / 'releases' / identity / 'component/mrn-base-stack/mrn-assets.json')
        assets = verify_public(manifest, plan['pages'])
        if identity == plan['artifact_sha256'] and plan.get('required_rendered_effect'):
            effect = check(plan['required_rendered_effect'], r'[a-z-]+', 'rendered tab effect')
            for page in canonical_pages(plan['url'], plan['effect_pages']):
                html = fetch(page, {'text/html'}).decode('utf-8')
                if rendered_tab_effects(html) != [effect]:
                    raise ValueError('The saved tab animation did not reach the public renderer')
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
    receipt = {'schema': 1, 'url': plan['url'], 'environment': plan['environment'], 'backup': backup,
               'host_provider': plan.get('host_provider', 'cloudpanel'),
               'runtime_qualification': 'target-specific; separate browser/release signoff required',
               'status': 'in-progress', 'source_sha': plan.get('source_sha'), 'artifact_sha256': plan.get('artifact_sha256')}
    with store.lock():
        before = store.pointer()
        active_before = store.bootstrap.is_file()
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
                    if active_before:
                        previous_id = before['components']['mrn-base-stack']['artifact_sha256']
                        restored = store.activate(previous_id, selected, preserved)
                        receipt['rollback'] = public_check(plan, store, restored)
                        selected = store.activate(plan['artifact_sha256'], restored, preserved)
                    else:
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
                    if before is None or not active_before:
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
