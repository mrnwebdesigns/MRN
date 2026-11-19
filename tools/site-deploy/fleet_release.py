#!/usr/bin/env python3
"""Plan, inspect or perform a phase for one enrolled Fleet release target."""
import argparse
import json
import os
from pathlib import Path
import shlex
import stat
import sys

sys.dont_write_bytecode = True

from release_v1.cli import configuration, request_from
from release_v1.controller import PHASES
from release_v1.proof import Blocked


def protected(path, *, key=False):
    path = Path(path)
    if not path.is_absolute() or path.resolve() != path:
        raise Blocked('Use the absolute physical path of a protected runner file')
    info = path.stat()
    owner = os.geteuid()
    parent = path.parent.stat()
    if (not stat.S_ISREG(info.st_mode) or info.st_uid != owner or info.st_mode & (0o077 if key else 0o022)
            or parent.st_uid != owner or parent.st_mode & 0o077):
        raise Blocked('Runner configuration and receipt key require an owner-only directory')
    return path


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__, prog='mrn fleet release')
    parser.add_argument('operation', choices=('plan', 'status', *PHASES, 'recover', 'rollback', 'abort'))
    parser.add_argument('--site', required=True, help='exact enrolled origin')
    parser.add_argument('--adapter', required=True, help='protected runner configuration')
    parser.add_argument('--key-file', help='owner-only receipt key file when no key is injected')
    parser.add_argument('--request', help='original authenticated release request')
    parser.add_argument('--archive', help='original immutable release archive')
    parser.add_argument('--confirm-site', help='exact origin for recovery, rollback, abort or production')
    args = parser.parse_args(argv)
    try:
        if args.key_file:
            key = protected(args.key_file, key=True).read_text().strip()
            if os.environ.get('MRN_RELEASE_RECEIPT_KEY', key) != key:
                raise Blocked('Receipt key sources disagree')
            os.environ['MRN_RELEASE_RECEIPT_KEY'] = key
        controller = configuration(protected(args.adapter))
        if controller.target.origin != args.site:
            raise Blocked('Requested origin differs from protected target')
        if args.operation == 'status':
            result = controller.status()
        else:
            if not args.request:
                raise Blocked('An authenticated release request is required')
            request = request_from(args.request)
            if args.operation == 'plan':
                result = controller.plan(request, args.archive)
            else:
                result = controller.run(args.operation, request, args.archive,
                                        confirmed_origin=args.confirm_site)
        phase = result['next']
        if phase != 'inspect-target':
            request_path = args.request or '<request.json>'
            if phase == 'preflight' and result.get('deployment_id') and result['lock'] == 'none':
                request_path = '<new-request.json>'
            parts = ['mrn', 'fleet', 'release', phase, '--site', args.site, '--adapter', args.adapter,
                     '--request', request_path]
            if args.key_file:
                parts += ['--key-file', args.key_file]
            if phase in ('preflight', 'activate', 'migrate'):
                parts += ['--archive', args.archive or '<release.tar>']
            if controller.target.environment == 'production' or phase in ('recover', 'rollback'):
                parts += ['--confirm-site', args.site]
            result['next_command'] = shlex.join(parts)
        print(json.dumps(result, indent=2))
        return 0
    except Blocked as error:
        print(json.dumps({'status': 'blocked', 'diagnostic': str(error)}), file=sys.stderr)
    except (ValueError, OSError, TypeError, KeyError, ImportError) as error:
        print(json.dumps({'status': 'blocked', 'diagnostic': type(error).__name__}), file=sys.stderr)
    return 1


if __name__ == '__main__':
    raise SystemExit(main())
