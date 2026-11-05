"""Prepare a least-privilege QA egress grant without changing unrelated policy."""
import argparse
import copy
import json
from pathlib import Path

import json5

CI_TAG = 'tag:amishmike-ci'
EXIT_TAG = 'tag:qa'
QA_IP = '100.97.81.44'
EGRESS_IP = '159.65.33.118'
REPOSITORY = 'mrnwebdesigns/amishmike-theme'
GRANT = {'src': [CI_TAG], 'dst': ['autogroup:internet'], 'via': [EXIT_TAG],
         'ip': ['tcp:80', 'tcp:443', 'tcp:53', 'udp:53']}
DENY_TEST = {'src': CI_TAG, 'proto': 'tcp', 'deny': [
    'tag:infrastructure:22', 'tag:infrastructure:80', 'tag:infrastructure:443',
    'tag:infrastructure:3306', 'tag:mrn-server:22', '1.1.1.1:22', '10.0.0.1:443',
]}
WEB_TEST = {'src': CI_TAG, 'proto': 'tcp', 'accept': ['1.1.1.1:80', '1.1.1.1:443']}
IDENTITY = {
    'keyType': 'federated', 'description': 'Amish Mike protected-main release QA',
    'scopes': ['auth_keys'], 'tags': [CI_TAG],
    'issuer': 'https://token.actions.githubusercontent.com',
    'subject': 'repo:mrnwebdesigns@273106862/amishmike-theme@1421114071:environment:production',
    'customClaimRules': {
        'repository': REPOSITORY, 'repository_id': '1421114071',
        'repository_owner_id': '273106862', 'ref': 'refs/heads/main',
        'workflow_ref': REPOSITORY + '/.github/workflows/deploy-kinsta.yml@refs/heads/main',
        'repository_visibility': 'private',
    },
}


def decode(text):
    value = json5.loads(text, allow_duplicate_keys=False)
    if not isinstance(value, dict):
        raise ValueError('Expected one tailnet policy object')
    return value


def tokens(text):
    """Find structural boundaries, retaining every original comment and byte."""
    out, index = [], 0
    while index < len(text):
        char = text[index]
        if char.isspace():
            index += 1
            continue
        if text.startswith('//', index):
            end = text.find('\n', index)
            index = len(text) if end < 0 else end + 1
            continue
        if text.startswith('/*', index):
            end = text.find('*/', index + 2)
            if end < 0:
                raise ValueError('Unclosed comment')
            index = end + 2
            continue
        start = index
        if char in ('"', "'"):
            quote = char
            index += 1
            while index < len(text):
                if text[index] == '\\':
                    index += 2
                elif text[index] == quote:
                    index += 1
                    break
                else:
                    index += 1
            out.append((json5.loads(text[start:index]), start, index, 'string'))
        else:
            index += 1
            out.append((char, start, index, 'punctuation'))
    return out


def append_member(text, name, value, field=False):
    parts = tokens(text)
    depth = 0
    for index, part in enumerate(parts):
        token = part[0]
        if depth == 1 and part[3] == 'string' and token == name:
            if parts[index + 1][0] != ':' or parts[index + 2][0] not in ('[', '{'):
                raise ValueError('Expected an existing policy collection: ' + name)
            start = index + 2
            opening = parts[start][0]
            expected = '{' if field else '['
            if opening != expected:
                raise ValueError('Unexpected collection type')
            nesting = 1
            end = start + 1
            while nesting:
                if parts[end][3] != 'string':
                    if parts[end][0] in ('[', '{'):
                        nesting += 1
                    elif parts[end][0] in (']', '}'):
                        nesting -= 1
                if nesting:
                    end += 1
            last = parts[end - 1]
            close = parts[end][1]
            insertion = text.rfind('\n', 0, close) + 1
            if text[insertion:close].strip():
                insertion = close
            comma = '' if last[0] in (opening, ',') else ','
            payload = json.dumps(value, indent='\t', ensure_ascii=False)
            if field:
                payload = payload[1:-1].strip()
            payload = '\n'.join('\t\t' + line for line in payload.splitlines())
            text = text[:insertion] + payload + ',\n' + text[insertion:]
            if comma:
                text = text[:last[2]] + comma + text[last[2]:]
            return text
        if part[3] != 'string':
            if token in ('[', '{'):
                depth += 1
            elif token in (']', '}'):
                depth -= 1
    raise ValueError('Policy collection not found: ' + name)


def prepare(text):
    before = decode(text)
    for key, kind in [('grants', list), ('tagOwners', dict), ('tests', list)]:
        if not isinstance(before.get(key), kind):
            raise ValueError('Existing typed ' + key + ' required')
    for grant in before['grants'] + before.get('acls', []):
        if any(src in ('*', 'autogroup:tagged') for src in grant.get('src', [])):
            raise ValueError('Broad existing grants require review before enrolling a CI tag')
    additions = [('tagOwners', {CI_TAG: ['autogroup:admin']}, True),
                 ('grants', GRANT, False), ('tests', DENY_TEST, False), ('tests', WEB_TEST, False)]
    expected = copy.deepcopy(before)
    for name, value, field in additions:
        if field:
            if CI_TAG in expected[name]:
                if expected[name][CI_TAG] != value[CI_TAG]:
                    raise ValueError('Existing CI tag ownership differs')
                continue
            expected[name].update(value)
        else:
            owned = [row for row in expected[name] if CI_TAG in row.get('src', [])]
            allowed = [GRANT] if name == 'grants' else [DENY_TEST, WEB_TEST]
            if any(row not in allowed for row in owned):
                raise ValueError('Existing CI grant/test differs')
            if value in expected[name]:
                continue
            expected[name].append(value)
        text = append_member(text, name, value, field)
    if decode(text) != expected:
        raise ValueError('Prepared policy differs from the exact scoped additions')
    return text


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--policy', required=True)
    parser.add_argument('--output', required=True)
    args = parser.parse_args()
    destination = Path(args.output)
    destination.mkdir(exist_ok=False)
    (destination / 'policy.hujson').write_text(prepare(Path(args.policy).read_text()))
    (destination / 'federated-identity.json').write_text(json.dumps(IDENTITY, indent=2) + '\n')
