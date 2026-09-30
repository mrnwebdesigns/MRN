#!/usr/bin/env python3
"""Backup-gated CloudPanel Dev child-theme activation. Live is not supported.

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
from urllib.parse import urlsplit, urljoin

from atomic_store import Store, durable_replace, inventory
from cache_policy import canonical_pages, head, verify_cloudpanel_origin, verify_uncached_html
from deploy import check, digest, http_check
from verify_public_assets import Assets, fetch, verify as verify_public

TOOLS = Path(__file__).resolve().parent


def wp(root, code, extra_env=None):
    # Recovery backups must also work if the selected theme cannot bootstrap.
    result = subprocess.run(['wp', '--path=' + root, '--skip-themes', 'eval', code],
                            capture_output=True, text=True, env={**os.environ, **(extra_env or {})})
    lines = [line[11:] for line in result.stdout.splitlines() if line.startswith('MRN_RESULT=')]
    if result.returncode or len(lines) != 1:
        raise RuntimeError('WP-CLI identity/backup operation failed; inspect the private host log')
    return json.loads(lines[0])


def backup(root, operation):
    label = 'mrn-' + operation + '-' + str(int(time.time()))
    code = (TOOLS / 'backup.php').read_text().removeprefix('<?php')
    receipt = wp(root, code, {'MRN_BACKUP_LABEL': label})
    if receipt.get('valid') is not True or receipt.get('label') != label:
        raise ValueError('Fresh remote database backup was not verified')
    return receipt


def identity(plan):
    if plan.get('environment') != 'dev' or plan.get('backup_provider') != 'updraft':
        raise ValueError('Only the CloudPanel Dev adapter is implemented; Live remains disabled')
    parsed = urlsplit(plan['url'])
    if not parsed.hostname or not parsed.hostname.endswith('.mrndev.io') or parsed.path:
        raise ValueError('Only a verified mrndev.io Dev target is supported')
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
    if any(result.get(key) != plan[key] for key in ('url', 'slug', 'template', 'root')):
        raise ValueError('Fresh WordPress identity differs from the approved target')
    if result['content'] != plan['root'] + '/wp-content' or plan['slug'] == plan['template']:
        raise ValueError('Only the verified standard child-theme layout is supported')
    cache_path = Path.home() / '.varnish-cache/settings.json'
    origin = verify_cloudpanel_origin(cache_path, result)
    binding = {key: plan[key] for key in ('repository', 'environment', 'url', 'root', 'template', 'slug')}
    return result, binding, origin


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
    receipt['cache'] = verify_uncached_html(plan['url'], plan['pages'])
    for page in plan['pages']:
        if head(page).get('x-mrn-site-release') != pointer['release_id']:
            raise ValueError('Public PHP response did not select the released code: ' + page)
    http_check(plan['url'] + '/wp-json/', rest=True)
    if pointer['public_path']:
        manifest = json.loads((store.releases / pointer['release_id'] / 'theme/mrn-assets.json').read_text())
        receipt['assets'] = verify_public(manifest, plan['pages'])
    else:
        receipt['assets'] = legacy_assets(plan)
    store.release(pointer['release_id'])
    store.verify_public_snapshot()
    return receipt


def execute(plan):
    result, binding, origin = identity(plan)
    store = Store(plan['state_dir'], Path(result['content']) / 'themes' / plan['slug'], result['content'], plan['slug'])
    receipt = {'schema': 1, **binding, 'status': 'in-progress', 'origin_cache': origin, 'steps': []}
    with store.locked_store():
        binding_path = store.state / 'site.json'
        if binding_path.exists() and json.loads(binding_path.read_text()) != binding:
            raise ValueError('Private release storage belongs to a different target')
        before = store.pointer()
        if before != plan.get('expected_current'):
            raise ValueError('Active release differs from the reviewed plan')
        verify_uncached_html(plan['url'], plan['pages'])
        old_assets = legacy_assets(plan) if before is None or before['public_path'] is None else {}
        adopted = False
        try:
            if before is None:
                if not plan.get('adopt') or digest(inventory(store.public_theme)) != plan['baseline']:
                    raise ValueError('First adoption requires the exact reviewed legacy tree')
                receipt['steps'].append({'operation': 'adopt', 'backup': backup(plan['root'], 'adopt')})
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
                receipt['steps'].append({'operation': 'stage', 'backup': backup(plan['root'], 'stage')})
                installed = store.stage(plan['archive'], plan['artifact_sha256'], plan['source_sha'], plan['source_path'])
                selected_id = installed['release_id']
            receipt['steps'].append({'operation': 'activate', 'backup': backup(plan['root'], 'activate')})
            selected = store.select(selected_id, before)
            receipt['activation'] = public_check(plan, store, selected)
            verify_legacy_assets(old_assets)
            if plan.get('exercise_rollback'):
                receipt['steps'].append({'operation': 'rollback-test', 'backup': backup(plan['root'], 'rollback')})
                restored = store.select(before['release_id'], selected)
                receipt['rollback'] = public_check(plan, store, restored)
                verify_legacy_assets(old_assets)
                receipt['steps'].append({'operation': 'reactivate', 'backup': backup(plan['root'], 'reactivate')})
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
                    receipt['steps'].append({'operation': 'recover', 'backup': backup(plan['root'], 'recover')})
                    current = store.pointer()
                    if current != before:
                        store.select(before['release_id'], current)
                    receipt['recovery'] = public_check(plan, store, before)
                except Exception as recovery_error:
                    receipt['recovery_error'] = str(recovery_error)
                    if adopted:
                        # First-adoption compatibility failure: restore the exact
                        # original public functions.php, without touching assets.
                        receipt['steps'].append({'operation': 'unadopt', 'backup': backup(plan['root'], 'unadopt')})
                        original = store.releases / before['release_id'] / 'theme/functions.php'
                        durable_replace(store.public_theme / 'functions.php', original.read_bytes(), 0o644)
                        stamp = str(time.time_ns())
                        for name in ('current.json', 'adoption.json', 'site.json'):
                            path = store.state / name
                            if path.exists():
                                os.rename(path, store.state / (name + '.failed-' + stamp))
                        http_check(plan['url'] + '/')
                        receipt['recovery'] = {'status': 'original-public-bootstrap-restored'}
            # The receipt must survive failure; the runner treats this status as
            # a failed operation and does not enable deployment readiness.
        receipt_path = store.state / ('receipt-' + str(time.time_ns()) + '.json')
        durable_replace(receipt_path, (json.dumps(receipt, indent=2) + '\n').encode())
    return receipt


if __name__ == '__main__':
    response = execute(json.load(sys.stdin))
    print('MRN_RESULT=' + json.dumps(response), flush=True)
    sys.exit(0 if response['status'] == 'public-verified' else 1)
