"""One-time, backup-gated import of a Kinsta-promoted staging release store.

Keep the promoted source store intact. Only an exact canonical public loader is
rebound to a fresh, separately bound Live store. No WordPress data is copied.
"""
import base64
import hashlib
import json
import os
from pathlib import Path
import time
from urllib.parse import urlsplit

from atomic_store import Store, durable_replace, inventory
from deploy import check, digest
from verify_release import verify

DOSTER_CUTOVER_ORDER = {
    'event': 'workflow_dispatch', 'repository': 'mrnwebdesigns/doster-and-brown-child-theme',
    'run_attempt': 1, 'run_id': '39438246725', 'run_number': 38,
    'source_sha': '0f9b5b56a3c665e5e4dd15d8ef32ef76fa34d8a5',
    'workflow': 'mrnwebdesigns/doster-and-brown-child-theme/.github/workflows/site-deploy.yml',
}
DOSTER_CUTOVER_POINTER = dict(schema=1, slug='doster-and-brown-child',
    release_id='76611d9a671976f974b9391158303f8f31375a3fb18feda78d75d3eff09b2305',
    public_path='mrn-assets/doster-and-brown-child/922dc8722187320fb69b2265ba692ce49efa30ae92ff3b2cdafc8ef33566883b')
DOSTER_CUTOVER_TREE = '759a5478cc0f41c236a580e49b9715f62b387ab618003874fdf0d71438e28505'


def reviewed_doster_promotion():
    from kinsta import DOSTER_DEV, DOSTER_TARGET
    return dict(schema=2, doster_cutover='doster-live-20261020', source_url=DOSTER_TARGET['url'],
                source_environment_id=DOSTER_DEV, source_sha=DOSTER_CUTOVER_ORDER['source_sha'],
                public_tree=DOSTER_CUTOVER_TREE, pointer=dict(DOSTER_CUTOVER_POINTER))


def doster_cutover(plan, promotion):
    """One reviewed owner-authorized copy; never a generic ordered-store bypass."""
    if not isinstance(promotion, dict) or promotion.get('schema') != 2:
        return False
    from kinsta import DOSTER_REPOSITORY, DOSTER_LIVE_TARGET
    target = dict(DOSTER_LIVE_TARGET, repository=DOSTER_REPOSITORY,
                  slug='doster-and-brown-child', template='mrn-base-stack')
    actual = dict(plan, host=plan.get('ssh_host', plan.get('host')),
                  port=plan.get('ssh_port', plan.get('port')), user=plan.get('ssh_user', plan.get('user')))
    source = reviewed_doster_promotion()
    if (any(actual.get(k) != v for k,v in target.items())
            or any(promotion.get(k) != v for k,v in source.items())):
        raise ValueError('Doster copied-release qualification differs from the exact reviewed Live cutover')
    return True


def qualification_order(plan, doster):
    order = plan.get('deployment_order')
    if not order:
        return
    from deployment_request import validate_order
    validate_order(order)
    if (not doster or order.get('event') != 'workflow_dispatch'
            or order.get('repository') != DOSTER_CUTOVER_ORDER['repository']
            or order.get('workflow') != DOSTER_CUTOVER_ORDER['workflow']
            or order.get('source_sha') != plan.get('source_sha')):
        raise ValueError('Doster Live qualification requires its trusted manual workflow order')


def require_doster_workflow(plan, promotion):
    if doster_cutover(plan,promotion):
        if not plan.get('deployment_order'):
            raise ValueError('Doster Live activation must run through its manual GitHub workflow')
        qualification_order(plan,True)


def source_order_matches(plan, promotion, order):
    return order == DOSTER_CUTOVER_ORDER if doster_cutover(plan, promotion) else order is None


def validate(plan, promotion):
    check(plan['slug'], r'[a-z0-9_-]+', 'stylesheet')
    doster = doster_cutover(plan, promotion)
    qualification_order(plan, doster)
    fields = {'schema', 'source_url', 'source_environment_id', 'source_sha', 'pointer', 'public_tree'}
    if doster:
        fields.add('doster_cutover')
    if (not isinstance(promotion, dict) or set(promotion) != fields
            or promotion.get('schema') != (2 if doster else 1) or plan.get('environment') != 'live'
            or plan.get('host_provider') != 'kinsta' or plan.get('backup_provider') != 'kinsta'
            or (plan.get('deployment_order') and not doster) or plan.get('adopt') or plan.get('qualification')
            or plan.get('bind_imported_source') or plan.get('rollback_to')):
        raise ValueError('Promoted store import requires an exact manual native Kinsta Live plan')
    url = urlsplit(promotion['source_url'])
    if (url.scheme != 'https' or not url.hostname or url.netloc != url.hostname
            or url.path or url.query or url.fragment or promotion['source_url'] == plan.get('url')):
        raise ValueError('Reviewed staging source URL is invalid')
    check(promotion['source_environment_id'], r'[a-f0-9-]{36}', 'source environment')
    check(promotion['source_sha'], r'[a-f0-9]{40}', 'promoted source SHA')
    check(promotion['public_tree'], r'[a-f0-9]{64}', 'promoted public tree')
    pointer = promotion['pointer']
    if (not isinstance(pointer, dict) or set(pointer) != {'schema', 'slug', 'release_id', 'public_path'}
            or pointer.get('schema') != 1 or pointer.get('slug') != plan['slug']):
        raise ValueError('Reviewed promoted pointer is invalid')
    check(pointer['release_id'], r'[a-f0-9]{64}', 'promoted release')
    candidate = check(plan.get('artifact_sha256', ''), r'[a-f0-9]{64}', 'candidate artifact')
    if candidate == pointer['release_id']:
        raise ValueError('Live qualification must exercise a distinct candidate and rollback pointer')
    check(pointer['public_path'], r'mrn-assets/' + plan['slug'] + r'/[a-f0-9]{64}', 'promoted asset path')
    root = Path(plan['root'])
    expected = root.parent / 'deployments/mrn-site-deploy/live'
    if Path(plan['state_dir']) != expected:
        raise ValueError('Live import requires its canonical separate release store')
    return expected.with_name('dev' if doster else 'staging')


def source_binding(plan, promotion):
    binding = {key: plan[key] for key in ('repository', 'root', 'template', 'slug')}
    return dict(binding, environment='dev' if doster_cutover(plan, promotion) else 'staging', url=promotion['source_url'])


def native_source(adapter, promotion, plan=None):
    if promotion.get('schema') == 2:
        from kinsta import DosterOwnerManualBackup, DOSTER_LIVE
        if (not plan or not doster_cutover(plan, promotion) or not isinstance(adapter, DosterOwnerManualBackup)
                or adapter.environment_id != DOSTER_LIVE or not adapter.identity_verified):
            raise ValueError('Doster cutover requires the separately verified owner-approved Live adapter')
        return
    environments = adapter.api('/sites/' + adapter.site_id + '/environments')['site']['environments']
    matches = [e for e in environments if e.get('id') == promotion['source_environment_id']
               and e.get('name') == 'staging' and e.get('primaryDomain', {}).get('type') == 'staging'
               and 'https://' + e['primaryDomain']['name'] == promotion['source_url']
               and e['id'] != adapter.environment_id]
    if len(matches) != 1:
        raise ValueError('Reviewed staging source is not a sibling native Kinsta environment')


def source_artifact(plan, promotion):
    path = plan.get('promoted_source_archive')
    if not path:
        raise ValueError('The original trusted build artifact is required for Live import')
    return verify(path, promotion['pointer']['release_id'], promotion['source_sha'], plan['source_path'], plan['slug'])


def preflight(target, plan, promotion, before):
    source = validate(plan, promotion)
    verified = source_artifact(plan, promotion)
    if (before.get('state') is not None or before.get('target_binding_exists')
            or before.get('deployment_order') or digest(before['files']) != promotion['public_tree']):
        raise ValueError('Live import destination or reviewed public tree changed')
    native_source(target.native_backup, promotion, plan)
    encoded = base64.b64encode(json.dumps({'source': str(source), 'destination': plan['state_dir'],
                                          'release': promotion['pointer']['release_id']}).encode()).decode()
    result = target.php('''
$c=json_decode(base64_decode('%s'),true);$s=$c['source'];$r=$s.'/releases/'.$c['release'];
echo 'MRN_RESULT='.wp_json_encode(array(
 'environment'=>wp_get_environment_type(),'source_physical'=>realpath($s)===$s,
 'source_private'=>is_dir($s) && (fileperms($s)&0077)===0,
 'source_no_aliases'=>!is_link($s.'/current.json') && !is_link($s.'/site.json') && !is_link($s.'/adoption.json') && !is_link($s.'/deployment-order.json') && !is_link($r.'/installed.json'),
 'destination_exists'=>file_exists($c['destination']) || is_link($c['destination']),
 'pointer'=>!is_link($s.'/current.json') && is_file($s.'/current.json')?json_decode(file_get_contents($s.'/current.json'),true):null,
 'binding'=>!is_link($s.'/site.json') && is_file($s.'/site.json')?json_decode(file_get_contents($s.'/site.json'),true):null,
 'order'=>!is_link($s.'/deployment-order.json') && is_file($s.'/deployment-order.json')?json_decode(file_get_contents($s.'/deployment-order.json'),true):null,
 'metadata'=>!is_link($r.'/installed.json') && is_file($r.'/installed.json')?json_decode(file_get_contents($r.'/installed.json'),true):null));
''' % encoded, skip_themes=True)
    if (result.get('environment') != 'production' or not result.get('source_physical')
            or not result.get('source_private') or not result.get('source_no_aliases') or result.get('destination_exists')
            or result.get('pointer') != promotion['pointer']
            or result.get('binding') != source_binding(plan, promotion)
            or not source_order_matches(plan, promotion, result.get('order'))
            or (result.get('metadata') or {}).get('source_sha') != promotion['source_sha']
            or (result.get('metadata') or {}).get('theme_files') != verified['theme_files']):
        raise ValueError('Promoted Live store preflight differs from the reviewed manifest')
    return {'status': 'promoted-live-import-preflight-verified', 'source_state': str(source),
            'source_sha': promotion['source_sha'], 'pointer': promotion['pointer'],
            'public_tree': promotion['public_tree']}


def bootstrap(template, store):
    code = Path(template).read_text()
    if code.count('__MRN_STATE_RELATIVE__') != 1:
        raise ValueError('Unexpected bootstrap template')
    relative = os.path.relpath(str(store.state), str(store.content_root.parent))
    return code.replace('__MRN_STATE_RELATIVE__', relative).encode()


def recognized_bootstraps(template, store):
    canonical = bootstrap(template, store)
    relative = os.path.relpath(str(store.state), str(store.content_root.parent))
    assignment = ("$mrn_release_state_root = realpath( ABSPATH . '" + relative + "' );\n"
                  "if ( false === $mrn_release_state_root ) {\n"
                  "\tthrow new RuntimeException( 'MRN private release storage is unavailable.' );\n}\n"
                  "$mrn_release_pointer = $mrn_release_state_root . '/current.json';")
    historical = ("$mrn_release_state_root = '" + str(store.state) + "';\n"
                  "$mrn_release_pointer    = $mrn_release_state_root . '/current.json';")
    return (canonical, canonical.decode().replace(assignment, historical).encode())


def import_store(plan, store, binding, guard, template):
    promotion = plan['promote_kinsta_staging']
    source_path = validate(plan, promotion)
    verified = source_artifact(plan, promotion)
    store.require_lock()
    if (store.pointer() is not None or any(p.name not in ('jobs', 'deployment.lock') for p in store.state.iterdir())
            or plan.get('exercise_rollback') is not True):
        raise ValueError('Live import requires a fresh destination and rollback exercise')
    source = Store(source_path, store.public_theme, store.content_root, store.slug)
    with source.locked_store():
        metadata_paths = [source.state / name for name in ('current.json', 'site.json', 'adoption.json', 'deployment-order.json')]
        metadata_paths.append(source.releases / promotion['pointer']['release_id'] / 'installed.json')
        if any(p.is_symlink() for p in metadata_paths):
            raise ValueError('Promoted private metadata must not be aliased')
        order_path = source.state / 'deployment-order.json'
        source_order = json.loads(order_path.read_text()) if order_path.exists() else None
        if (not source_order_matches(plan, promotion, source_order)
                or json.loads((source.state / 'site.json').read_text()) != source_binding(plan, promotion)
                or source.pointer() != promotion['pointer']):
            raise ValueError('Promoted staging binding, order or pointer changed')
        source.verify_public_snapshot()
        public_files = inventory(source.public_theme)
        if digest(public_files) != promotion['public_tree']:
            raise ValueError('Promoted public theme changed before import')
        original = (source.public_theme / 'functions.php').read_bytes()
        if original not in recognized_bootstraps(template, source):
            raise ValueError('Promoted loader is not the exact canonical staging bootstrap')
        metadata = source.release(promotion['pointer']['release_id'])
        if (metadata.get('source_sha') != promotion['source_sha']
                or metadata.get('theme_files') != verified['theme_files']
                or metadata.get('public_path') != promotion['pointer']['public_path']
                or inventory(source.content_root / metadata['public_path']) != metadata.get('static_files')):
            raise ValueError('Promoted private code or public assets differ from reviewed artifact')
        new_bootstrap = bootstrap(template, store)
        guard('import-promoted-live-store')
        # Preserve recovery evidence before publishing a new pointer or loader.
        durable_replace(store.state / 'promoted-source-bootstrap.php', original)
        record = {'schema': 1, 'source_state': str(source.state), 'source_binding': source_binding(plan, promotion),
                  'source_pointer': promotion['pointer'], 'source_sha': promotion['source_sha'],
                  'public_files': public_files, 'original_bootstrap_sha256': hashlib.sha256(original).hexdigest(),
                  'live_bootstrap_sha256': hashlib.sha256(new_bootstrap).hexdigest()}
        durable_replace(store.state / 'promotion.json', (json.dumps(record, sort_keys=True) + '\n').encode())
        adoption = {'schema': 1, 'slug': store.slug, 'legacy_release_id': metadata['release_id'],
                    'public_files': public_files, 'bootstrap_sha256': record['original_bootstrap_sha256'],
                    'imported': True, 'source_sha': promotion['source_sha']}
        durable_replace(store.state / 'adoption.json', (json.dumps(adoption, sort_keys=True) + '\n').encode())
        # Rehydrate from the verified original archive, never from mutable
        # private metadata alone. The existing stager checks every asset byte.
        store.releases.mkdir(mode=0o700)
        store.stage(plan['promoted_source_archive'], promotion['pointer']['release_id'], promotion['source_sha'], plan['source_path'])
        store.select(metadata['release_id'], None)
        guard('rebind-promoted-live-loader')
        if inventory(store.public_theme) != public_files or source.pointer() != promotion['pointer']:
            raise ValueError('Promoted source changed before loader rebind')
        adoption['bootstrap_sha256'] = record['live_bootstrap_sha256']
        durable_replace(store.state / 'adoption.json', (json.dumps(adoption, sort_keys=True) + '\n').encode())
        durable_replace(store.state / 'site.json', (json.dumps(binding, sort_keys=True) + '\n').encode())
        durable_replace(store.public_theme / 'functions.php', new_bootstrap, 0o644)
        store.verify_public_snapshot()
        return {'operation': 'import-promoted-live-store', 'source_state': str(source.state),
                'current': store.pointer(), 'original_bootstrap_sha256': record['original_bootstrap_sha256'],
                'live_bootstrap_sha256': record['live_bootstrap_sha256']}


def restore_import(plan, store, guard):
    """Restore the exact original loader; quarantine metadata, never erase code."""
    store.require_lock()
    record = json.loads((store.state / 'promotion.json').read_text())
    promotion = plan['promote_kinsta_staging']
    source_path = validate(plan, promotion)
    if (record.get('source_state') != str(source_path)
            or record.get('source_binding') != source_binding(plan, promotion)
            or record.get('source_pointer') != promotion['pointer']
            or digest(record.get('public_files', {})) != promotion['public_tree']):
        raise ValueError('Promoted recovery record differs from the reviewed plan')
    original = (store.state / 'promoted-source-bootstrap.php').read_bytes()
    if hashlib.sha256(original).hexdigest() != record['original_bootstrap_sha256']:
        raise ValueError('Promoted source recovery snapshot changed')
    current = inventory(store.public_theme)
    expected = dict(record['public_files'])
    current_bootstrap = current.get('functions.php')
    expected['functions.php'] = current_bootstrap
    if (current != expected or current_bootstrap not in
            (record['original_bootstrap_sha256'], record['live_bootstrap_sha256'])):
        raise ValueError('Public drift blocks promoted loader recovery')
    source = Store(record['source_state'], store.public_theme, store.content_root, store.slug)
    with source.locked_store():
        order_path = source.state / 'deployment-order.json'
        if order_path.is_symlink() or not source_order_matches(plan, promotion, json.loads(order_path.read_text()) if order_path.exists() else None):
            raise ValueError('Original promoted order changed before recovery')
        if (source.pointer() != record['source_pointer']
                or json.loads((source.state / 'site.json').read_text()) != record['source_binding']):
            raise ValueError('Original promoted store changed before recovery')
        source.release(record['source_pointer']['release_id'])
        guard('restore-promoted-source-loader')
        durable_replace(store.public_theme / 'functions.php', original, 0o644)
        source.verify_public_snapshot()
        stamp = str(int(time.time() * 1000000000))
        for name in ('current.json', 'adoption.json', 'site.json'):
            path = store.state / name
            if path.exists():
                os.rename(path, store.state / (name + '.failed-' + stamp))
    return {'status': 'original-promoted-store-restored', 'current': record['source_pointer']}
