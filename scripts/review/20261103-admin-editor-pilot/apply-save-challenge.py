"""Apply only the owner-approved review save exception; refuse live drift."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess
import urllib.error
import urllib.request

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--apply', action='store_true')
args = parser.parse_args()
proposal = json.loads(Path(__file__).with_name('proposed-save-challenge.json').read_text())
out = Path('outputs/admin-review-migration')
out.mkdir(parents=True, exist_ok=True)
credential = subprocess.run(
    ['op', 'item', 'get', 'Production Hub - Cloudflare', '--vault', 'Production Hub', '--format', 'json'],
    env={**os.environ, 'OP_ACCOUNT': 'mrnwebdesigns.1password.com'},
    capture_output=True, text=True, timeout=45,
)
if credential.returncode:
    raise SystemExit('Cloudflare credential lookup unavailable')
fields = {f.get('label'): f.get('value', '') for f in json.loads(credential.stdout).get('fields', [])}
token = fields.get('CLOUDFLARE_API_TOKEN')
if not token:
    raise SystemExit('Expected Cloudflare token missing')


def api(path, method='GET', body=None):
    request = urllib.request.Request(
        'https://api.cloudflare.com/client/v4/' + path,
        data=json.dumps(body).encode() if body is not None else None,
        method=method,
        headers={'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json'},
    )
    try:
        with urllib.request.urlopen(request, timeout=40) as response:
            result = json.load(response)
    except urllib.error.HTTPError as error:
        raise SystemExit('Cloudflare request failed with HTTP ' + str(error.code)) from None
    if not result.get('success'):
        raise SystemExit('Cloudflare request did not succeed')
    return result['result']


zones = api('zones?name=mrndev.io')
if len(zones) != 1 or zones[0]['name'] != 'mrndev.io' or zones[0]['status'] != 'active':
    raise SystemExit('Exact active zone unavailable')
zone = zones[0]
if zone['account'] != {'id': '9842ad7e7c668405c0bffcc6cbfede3a', 'name': 'MRN Web Designs'}:
    raise SystemExit('Cloudflare account mismatch')
path = 'zones/' + zone['id'] + '/rulesets/' + proposal['ruleset_id']
before = api(path)
managed_path = 'zones/' + zone['id'] + '/rulesets/phases/http_request_firewall_managed/entrypoint'
managed_before = api(managed_path)
rules = [r for r in before['rules'] if r['id'] == proposal['rule_id']]
if len(rules) != 1 or before['phase'] != 'http_request_firewall_custom':
    raise SystemExit('Exact custom rule unavailable')
current = rules[0]
if current != proposal['before']:
    raise SystemExit('Live rule drift; do not overwrite or broaden the approved change')
expected = '(' + current['expression'] + ' and not (http.host eq "admin-editor-pilot.mrndev.io" and http.request.uri.path eq "/wp-admin/post.php" and http.request.method eq "POST" and http.cookie contains "wordpress_logged_in_"))'
if proposal['after']['expression'] != expected:
    raise SystemExit('Proposal differs from the approved exact exception')
for key in ('action', 'description', 'enabled', 'ref'):
    if proposal['after'][key] != current[key]:
        raise SystemExit('Unapproved non-expression change')
print(json.dumps({'zone': zone['name'], 'account': zone['account']['name'], 'rule_version': current['version'], 'preflight': 'exact match', 'apply': args.apply}))
if not args.apply:
    raise SystemExit(0)
# Provider config rollback is retained privately before the single-rule PATCH.
snapshot = out / 'waf-before-approved-save-exception.json'
with snapshot.open('x') as handle:
    json.dump({'custom': before, 'managed': managed_before}, handle, indent=2)
snapshot.chmod(0o600)
api(path + '/rules/' + proposal['rule_id'], 'PATCH', proposal['after'])
after = api(path)
updated = next(r for r in after['rules'] if r['id'] == proposal['rule_id'])
if any(updated.get(k) != v for k, v in proposal['after'].items()):
    raise SystemExit('Applied rule readback mismatch; retain rollback and stop')
if [r['id'] for r in before['rules']] != [r['id'] for r in after['rules']]:
    raise SystemExit('Rule order changed unexpectedly')
if [r for r in before['rules'] if r['id'] != proposal['rule_id']] != [r for r in after['rules'] if r['id'] != proposal['rule_id']]:
    raise SystemExit('Another custom rule changed; investigate before continuing')
if api(managed_path) != managed_before:
    raise SystemExit('Managed rules changed; investigate before continuing')
receipt = {
    'status': 'applied-and-read-back', 'zone': zone['name'], 'host': 'admin-editor-pilot.mrndev.io',
    'rule_id': updated['id'], 'before_version': current['version'], 'after_version': updated['version'],
    'last_updated': updated['last_updated'], 'expression': updated['expression'],
    'other_custom_rules_and_order_unchanged': True, 'managed_ruleset_unchanged': True,
    'rollback_snapshot_sha256': hashlib.sha256(snapshot.read_bytes()).hexdigest(),
}
(out / 'waf-applied-receipt.json').write_text(json.dumps(receipt, indent=2) + '\n')
print(json.dumps(receipt))
