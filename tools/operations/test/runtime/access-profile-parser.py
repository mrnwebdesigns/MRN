#!/usr/bin/env python3
"""Read Xdebug 3.5.3 cachegrind; export fixed labels only, never raw names/paths."""
import re

# Instrumentation revision 2: the selected HTTP profile file measured 34,092,391
# bytes. This read-only diagnostic budget stays below its 64MiB storage cap.
MAXIMUM = 40 * 1024 * 1024
# Revision 3: the admitted file contained 3,919,774 lines. This parser-work
# budget is diagnostic only; reference, line-width and byte limits stay fixed.
MAX_LINES = 5_000_000
GROUPS = {
    'Content_Access': ('management_existing_readback', 'readback_for'),
    'Content_Source': ('verify', 'readback'),
    'Management_Requester_State': ('current', 'descriptor', 'record_current', 'proven'),
    'Management_Requester_Coherence': ('read', 'load', 'descriptor', 'proven'),
    'Management_Bound_Queue': ('retained_job', 'current', 'authorize', 'valid'),
    'Management_Bound_Original': ('current', 'historical'),
    'Content_Catalog': ('policy', 'schema', 'refresh_authority'),
    'Content_Management': ('qualification_json', 'qualification_value', 'qualification_record_fresh'),
    'Management_Evidence_Authority': ('authorization',),
}
LABELS = {f'MRN_MainWP_{group}{separator}{method}': f'{group}.{method}'
          for group, methods in GROUPS.items() for method in methods for separator in ('::', '->')}
LABELS.update({f'wpdb->{method}': f'wpdb.{method}' for method in ('query', 'get_var', 'get_row', 'get_results', 'prepare')})
LABELS.update({f'php::{method}': method for method in ('hash_file', 'hash', 'json_encode', 'json_decode', 'sodium_crypto_secretbox_open')})
REQUIRED = ('Content_Access.management_existing_readback', 'Content_Access.readback_for',
            'Management_Bound_Queue.retained_job', 'Management_Bound_Queue.current')


def bounded(value):
    result = int(value)
    if not 0 <= result <= 9007199254740991:
        raise ValueError('Profile integer out of bounds')
    return result


def parse_profile(stream):
    names, files, rows = {}, set(), {}
    total_bytes = 0
    stage, current, call_target, ended = 'version', None, None, False
    summary = None

    def reference(value, table, functions=False):
        match = re.fullmatch(r'\(([1-9][0-9]{0,7})\)(?: (.{1,8192}))?', value)
        if not match:
            raise ValueError('Malformed profile reference')
        key, label = int(match[1]), match[2]
        if label is not None:
            if key in table or len(table) >= 50000:
                raise ValueError('Duplicate or excessive profile references')
            if functions:
                table[key] = LABELS.get(label)  # Drop all dynamic labels immediately.
            else:
                table.add(key)
        if key not in table:
            raise ValueError('Unresolved profile reference')
        return table[key] if functions else None

    for count in range(MAX_LINES + 1):
        raw = stream.readline(16385)
        if not raw:
            break
        if count >= MAX_LINES:
            raise ValueError('Excessive profile lines')
        total_bytes += len(raw)
        if total_bytes > MAXIMUM or len(raw) > 16384 or not raw.endswith(b'\n'):
            raise ValueError('Oversized or truncated profile')
        line = raw[:-1].decode('utf8', errors='strict')
        if '\x00' in line:
            raise ValueError('Malformed profile text')
        if not line:
            if stage == 'edges':
                stage = 'block'
            continue
        if ended:
            raise ValueError('Trailing profile data')
        if stage == 'version':
            if line != 'version: 1':
                raise ValueError('Unknown profile version')
            stage = 'creator'
        elif stage == 'creator':
            if not re.fullmatch(r'creator: xdebug 3\.5\.3 \(PHP 8\.3\.[0-9]+\)', line):
                raise ValueError('Unqualified profiler or PHP version')
            stage = 'cmd'
        elif stage == 'cmd':
            if not line.startswith('cmd: '):
                raise ValueError('Missing profile command')
            stage = 'part'
        elif stage == 'part':
            if line != 'part: 1':
                raise ValueError('Multiple profile parts')
            stage = 'positions'
        elif stage == 'positions':
            if line != 'positions: line':
                raise ValueError('Unknown profile positions')
            stage = 'events'
        elif stage == 'events':
            if line != 'events: Time_(10ns) Memory_(bytes)':
                raise ValueError('Unknown profile events')
            stage = 'block'
        elif stage == 'block' and line.startswith('summary: '):
            match = re.fullmatch(r'summary: ([0-9]+) ([0-9]+)', line)
            if not match:
                raise ValueError('Malformed profile summary')
            summary = bounded(match[1]); bounded(match[2]); ended = True
        elif stage == 'block' and line.startswith('fl='):
            reference(line[3:], files); stage = 'fn'
        elif stage == 'fn' and line.startswith('fn='):
            current = reference(line[3:], names, True); stage = 'self'
            if current:
                row = rows.setdefault(current, {'calls': 0, 'self_10ns': 0, 'inclusive_10ns': 0})
                row['calls'] += 1
        elif stage in ('self', 'edge-cost'):
            match = re.fullmatch(r'([0-9]+) ([0-9]+) ([0-9]+)', line)
            if not match:
                raise ValueError('Malformed profile cost')
            bounded(match[1]); cost = bounded(match[2]); bounded(match[3])
            if current:
                row = rows[current]
                row['inclusive_10ns'] = bounded(row['inclusive_10ns'] + cost)
                if stage == 'self':
                    row['self_10ns'] = bounded(row['self_10ns'] + cost)
            stage = 'edges'; call_target = None
        elif stage == 'edges' and line.startswith('cfl='):
            reference(line[4:], files); stage = 'cfn'
        elif stage == 'cfn' and line.startswith('cfn='):
            call_target = reference(line[4:], names, True); stage = 'calls'
        elif stage == 'calls' and line == 'calls=1 0 0':
            stage = 'edge-cost'
        else:
            raise ValueError('Unexpected profile structure')
    else:
        raise ValueError('Excessive profile lines')
    if not ended or summary is None or not names or call_target is not None:
        raise ValueError('Incomplete profile')
    return {'instrumented': True, 'total_10ns': summary, 'raw_bytes': total_bytes,
            'representative_path': all(rows.get(label, {}).get('calls', 0) > 0 for label in REQUIRED),
            'functions': [{'label': label, **row} for label, row in sorted(rows.items())]}
