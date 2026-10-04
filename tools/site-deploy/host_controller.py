#!/usr/bin/env python3
"""Backup-gated child-theme activation using explicitly selected provider adapters.

Run with a trusted JSON plan on stdin, from a private, checksum-verified tool
directory. The caller must back up before transferring these tools. Every code
activation and rollback then obtains its own fresh verified database backup.
"""
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import time
import traceback
from urllib.parse import urlsplit, urljoin

from atomic_store import Store, durable_replace, inventory
from host_paths import qualify_provider
from cache_policy import canonical_pages, head, verify_cloudpanel_origin, verify_uncached_html
from deploy import check, digest, http_check
from deployment_request import check_order
from verify_public_assets import Assets, fetch, verify as verify_public

TOOLS = Path(__file__).resolve().parent


def wp(root, code, extra_env=None, skip_themes=True, stdin=None):
    # Recovery backups must also work if the selected theme cannot bootstrap.
    result = subprocess.run(['wp', '--path=' + root] + (['--skip-themes'] if skip_themes else []) + ['eval', code],
                            input=stdin, stdout=subprocess.PIPE, stderr=subprocess.PIPE, universal_newlines=True,
                            env={**os.environ, 'WP_CLI_PHP_ARGS': '-d memory_limit=512M', **(extra_env or {})})
    lines = [line[11:] for line in result.stdout.splitlines() if line.startswith('MRN_RESULT=')]
    if result.returncode or len(lines) != 1:
        raise RuntimeError('WP-CLI identity/backup operation failed; inspect the private host log')
    return json.loads(lines[0])


def backup(root, operation):
    label = 'mrn-' + operation + '-' + str(int(time.time()))
    code = (TOOLS / 'backup.php').read_text()[5:]
    receipt = wp(root, code, {'MRN_BACKUP_LABEL': label})
    if receipt.get('valid') is not True or receipt.get('label') != label:
        raise ValueError('Fresh remote database backup was not verified')
    return receipt


def identity(plan):
    if plan.get('environment') == 'live' and plan.get('host_provider', 'cloudpanel') == 'cloudpanel':
        raise ValueError('Live remains disabled without an explicit production adapter')
    canonical_pages(plan['url'], plan['pages'])
    check(plan['repository'], r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', 'repository')
    check(plan['root'], r'/[A-Za-z0-9_./-]+', 'WordPress root')
    result = wp(plan['root'], '''
$plugins = array_values((array) get_option('active_plugins', array()));
$caches = array_values(array_filter($plugins, static function($plugin) {
    return (bool) preg_match('/cache|rocket|autoptimize|perfmatters|nitropack|breeze|hummingbird/i', $plugin);
}));
echo 'MRN_RESULT=' . wp_json_encode(array(
    'url'=>untrailingslashit(get_option('home')), 'slug'=>get_stylesheet(), 'template'=>get_template(),
    'root'=>realpath(ABSPATH), 'content'=>realpath(WP_CONTENT_DIR),
    'wp_cache'=>defined('WP_CACHE') && WP_CACHE,
    'advanced_cache'=>file_exists(WP_CONTENT_DIR . '/advanced-cache.php'), 'cache_plugins'=>$caches
));
''')
    if any(result.get(key) != plan[key] for key in ('url', 'slug', 'template')):
        raise ValueError('Fresh WordPress identity differs from the approved target')
    if plan['slug'] == plan['template']:
        raise ValueError('Only a child theme may be deployed')
    paths = qualify_provider(plan, result)
    plan.update(root=paths['root'], state_dir=paths['state_dir'])
    plan['_protected_state_root'] = paths['protected_state_root']
    if paths['provider'] == 'cloudpanel':
        origin = verify_cloudpanel_origin(Path.home() / '.varnish-cache/settings.json', result)
    else:
        origin = html_cache(plan, 'inspect')
        plan['_html_scope'] = origin['urls']
    binding = {key: plan[key] for key in ('repository', 'environment', 'url', 'root', 'template', 'slug')}
    return result, binding, origin


def html_cache(plan, action):
    request = {'url': plan['url'], 'provider': plan.get('host_provider', 'cloudpanel'),
               'action': action, 'urls': plan.get('_html_scope', plan['pages'])}
    code = (TOOLS / 'kinsta_html_cache.php').read_text()[5:] + '\n' + (TOOLS / 'html_cache.php').read_text()[5:]
    # A large site's exact URL scope can exceed Linux's per-environment-string
    # limit (128 KiB). Keep the bounded request on stdin, not in argv or env.
    return wp(plan['root'], code,
              {'MRN_HTML_CACHE_STDIN': '1'}, skip_themes=action != 'inspect', stdin=json.dumps(request))


def verify_cached_public(plan):
    results = []
    for page in plan['pages']:
        for phase in ('first', 'warm'):
            headers = head(page)
            if 'text/html' not in headers.get('content-type', '').lower():
                raise ValueError('Public cache verification expected HTML')
            results.append({'url': page, 'phase': phase, 'headers': headers})
    return {'status': 'public-html-verified', 'pages': results}


def legacy_assets(plan):
    assets = {}
    prefix = '/wp-content/themes/' + plan['slug'] + '/'
    for page in plan['pages']:
        parser = Assets()
        parser.feed(fetch(page, {'text/html'}).decode())
        for raw in parser.urls:
            url = urljoin(page, raw)
            parsed = urlsplit(url)
            if parsed.netloc != urlsplit(plan['url']).netloc or not parsed.path.startswith(prefix):
                continue
            if not re.search(r'\.(css|js|mjs)$', parsed.path):
                continue
            types = {'text/css'} if parsed.path.endswith('.css') else {'text/javascript', 'application/javascript'}
            assets[url] = {'sha256': hashlib.sha256(fetch(url, types)).hexdigest(), 'types': sorted(types)}
    if not assets:
        raise ValueError('No legacy child assets found for adoption comparison')
    return assets


def verify_legacy_assets(assets):
    for url, expected in assets.items():
        if hashlib.sha256(fetch(url, set(expected['types']))).hexdigest() != expected['sha256']:
            raise ValueError('Previously published asset URL changed content: ' + url)


def public_check(plan, store, pointer):
    receipt = {'release_id': pointer['release_id']}
    uncached = plan.get('host_provider', 'cloudpanel') == 'cloudpanel'
    if uncached:
        receipt['cache'] = verify_uncached_html(plan['url'], plan['pages'])
        for page in plan['pages']:
            if head(page).get('x-mrn-site-release') != pointer['release_id']:
                raise ValueError('Public PHP response did not select the released code: ' + page)
    else:
        receipt['cache'] = html_cache(plan, 'refresh')
    http_check(plan['url'] + '/wp-json/', rest=True)
    deadline = time.monotonic() + (0 if uncached else 120)
    attempts = 0
    while True:
        attempts += 1
        try:
            if not uncached:
                receipt['cache'].update(verify_cached_public(plan))
            if pointer['public_path']:
                manifest = json.loads((store.releases / pointer['release_id'] / 'theme/mrn-assets.json').read_text())
                receipt['assets'] = verify_public(manifest, plan['pages'])
            else:
                receipt['assets'] = legacy_assets(plan)
            break
        except Exception:
            if time.monotonic() >= deadline:
                raise
            time.sleep(5)
    receipt['public_verification_attempts'] = attempts
    store.release(pointer['release_id'])
    store.verify_public_snapshot()
    return receipt


def execute(plan, native_backup=None):
    if plan.get('host_provider') == 'kinsta':
        if native_backup is None:
            raise ValueError('Native Kinsta backup identity is required')
        native_backup.identity({'url': plan['url'], 'root': plan['root'], 'host': plan['ssh_host'],
                                'port': plan['ssh_port'], 'user': plan['ssh_user']})

    def guard(operation):
        if plan.get('host_provider') == 'kinsta':
            return native_backup.verify_transaction_backup(plan.get('native_backup_receipt', {}), operation)
        return backup(plan['root'], operation)

    result, binding, origin = identity(plan)
    store = Store(plan['state_dir'], Path(result['content']) / 'themes' / plan['slug'], result['content'], plan['slug'],
                  protected_state_root=plan.get('_protected_state_root'))
    receipt = {'schema': 1, **binding, 'status': 'in-progress', 'host_provider': plan.get('host_provider', 'cloudpanel'), 'origin_cache': origin, 'steps': []}
    with store.locked_store():
        binding_path = store.state / 'site.json'
        if binding_path.exists() and json.loads(binding_path.read_text()) != binding:
            raise ValueError('Private release storage belongs to a different target')
        order_path = store.state / 'deployment-order.json'
        previous_order = json.loads(order_path.read_text()) if order_path.exists() else None
        order = plan.get('deployment_order')
        check_order(order, previous_order, rollback=bool(plan.get('rollback_to')))
        if order and order['event'] in ('push', 'workflow_run') and plan['environment'] != 'dev' and not order.get('release_intent'):
            raise ValueError('Automatic deployment is Dev-only')
        if order and order.get('release_intent') and plan['environment'] not in (('dev', 'live') if order['release_intent']['target'] == 'both' else (order['release_intent']['target'],)):
            raise ValueError('Release intent does not authorize this environment')
        if order and (order['repository'] != plan['repository'] or (not plan.get('rollback_to') and order['source_sha'] != plan['source_sha'])):
            raise ValueError('Deployment sequence does not match this source/target')
        receipt.update(deployment_order=order, source_sha=plan.get('source_sha'),
                       artifact_sha256=plan.get('artifact_sha256'))
        before = store.pointer()
        if before != plan.get('expected_current'):
            raise ValueError('Active release differs from the reviewed plan')
        if plan.get('host_provider', 'cloudpanel') == 'cloudpanel':
            verify_uncached_html(plan['url'], plan['pages'])
        old_assets = legacy_assets(plan) if before is None or before['public_path'] is None else {}
        adopted = False
        try:
            if before is None:
                if not plan.get('adopt') or digest(inventory(store.public_theme)) != plan['baseline']:
                    raise ValueError('First adoption requires the exact reviewed legacy tree')
                receipt['steps'].append({'operation': 'adopt', 'backup': guard('adopt')})
                durable_replace(binding_path, (json.dumps(binding, sort_keys=True) + '\n').encode())
                adopted = True
                before = store.adopt(plan['baseline'], TOOLS / 'release-bootstrap.php')
                receipt['legacy'] = public_check(plan, store, before)
                verify_legacy_assets(old_assets)
            elif not binding_path.exists():
                raise ValueError('Missing verified target binding')
            if plan.get('rollback_to'):
                selected_id = check(plan['rollback_to'], r'[a-f0-9]{64}', 'rollback release')
            else:
                receipt['steps'].append({'operation': 'stage', 'backup': guard('stage')})
                if order:
                    # Persist after verified backup, before staging/activation.
                    # Even a failed newer run prevents an older queued run from winning.
                    durable_replace(order_path, (json.dumps(order, sort_keys=True) + '\n').encode())
                installed = store.stage(plan['archive'], plan['artifact_sha256'], plan['source_sha'], plan['source_path'])
                selected_id = installed['release_id']
            receipt['steps'].append({'operation': 'activate', 'backup': guard('activate')})
            selected = store.select(selected_id, before)
            receipt['activation'] = public_check(plan, store, selected)
            verify_legacy_assets(old_assets)
            if plan.get('exercise_rollback'):
                receipt['steps'].append({'operation': 'rollback-test', 'backup': guard('rollback')})
                restored = store.select(before['release_id'], selected)
                receipt['rollback'] = public_check(plan, store, restored)
                verify_legacy_assets(old_assets)
                receipt['steps'].append({'operation': 'reactivate', 'backup': guard('reactivate')})
                selected = store.select(selected_id, restored)
                receipt['reactivation'] = public_check(plan, store, selected)
                verify_legacy_assets(old_assets)
            receipt.update(status='public-verified', current=selected, previous=before,
                           legacy_asset_urls_preserved=sorted(old_assets), runtime_qa_required=True)
        except Exception as error:
            receipt.update(status='failed', error=str(error))
            if before is None and adopted:
                before = store.pointer()
            if before is not None:
                try:
                    receipt['steps'].append({'operation': 'recover', 'backup': guard('recover')})
                    current = store.pointer()
                    if current != before:
                        store.select(before['release_id'], current)
                    receipt['recovery'] = public_check(plan, store, before)
                except Exception as recovery_error:
                    receipt['recovery_error'] = str(recovery_error)
                    if adopted:
                        # First-adoption compatibility failure: restore the exact
                        # original public functions.php, without touching assets.
                        receipt['steps'].append({'operation': 'unadopt', 'backup': guard('unadopt')})
                        original = store.releases / before['release_id'] / 'theme/functions.php'
                        durable_replace(store.public_theme / 'functions.php', original.read_bytes(), 0o644)
                        stamp = str(int(time.time() * 1000000000))
                        for name in ('current.json', 'adoption.json', 'site.json'):
                            path = store.state / name
                            if path.exists():
                                os.rename(path, store.state / (name + '.failed-' + stamp))
                        if plan.get('host_provider', 'cloudpanel') != 'cloudpanel':
                            receipt['recovery_cache'] = html_cache(plan, 'refresh')
                        for page in plan['pages']:
                            http_check(page)
                        verify_legacy_assets(old_assets)
                        receipt['recovery'] = {'status': 'original-public-bootstrap-restored'}
            # The receipt must survive failure; the runner treats this status as
            # a failed operation and does not enable deployment readiness.
        receipt_path = store.state / ('receipt-' + str(int(time.time() * 1000000000)) + '.json')
        durable_replace(receipt_path, (json.dumps(receipt, indent=2) + '\n').encode())
    return receipt


def failure_receipt(error):
    """Keep diagnostic evidence when recovery itself fails; never echo a plan.

    Exception messages, source lines and locals can contain credentials. Retain
    only exception classes, errno, source locations and safe filesystem paths.
    The unknown outcome still requires inspection; it never permits a retry.
    """
    failures, seen = [], set()
    while error is not None and id(error) not in seen and len(failures) < 8:
        seen.add(id(error))
        row = {'type': type(error).__name__, 'frames': [
            {'file': Path(frame.filename).name, 'line': frame.lineno, 'function': frame.name}
            for frame in traceback.extract_tb(error.__traceback__)]}
        if isinstance(error, OSError):
            row['errno'] = error.errno
            filename = getattr(error, 'filename', None)
            if isinstance(filename, str) and re.fullmatch(r'/[A-Za-z0-9_./-]+', filename):
                row['path'] = filename
        failures.append(row)
        error = error.__cause__ or error.__context__
    return {'schema': 1, 'status': 'requires-inspection', 'diagnostics': failures}


if __name__ == '__main__':
    try:
        request = json.load(sys.stdin)
        native = request.pop('native', None)
        adapter = None
        if native:
            from kinsta import Kinsta
            adapter = Kinsta(native['token'], native['site_id'], native['environment_id'])
            del native
        response = execute(request['plan'], adapter)
    except Exception as error:
        response = failure_receipt(error)
    print('MRN_RESULT=' + json.dumps(response), flush=True)
    sys.exit(0 if response['status'] == 'public-verified' else 1)
