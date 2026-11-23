"""Run one MRN site-release v1 phase through a trusted installed adapter."""
import argparse
import importlib.util
import json
import os
from pathlib import Path
import shlex
import sys
import stat

from verify_release import parse_json
from .artifact import Limits, inspect, pack
from .controller import Controller, PHASES, Request, Target
from .journal import Journal
from .proof import Blocked


def protected(path, *, key=False):
    path = Path(path)
    if not path.is_absolute() or path.resolve() != path:
        raise Blocked('Use the absolute physical path of a protected runner file')
    info, parent, owner = path.stat(), path.parent.stat(), os.geteuid()
    if (not stat.S_ISREG(info.st_mode) or info.st_uid != owner or info.st_mode & (0o077 if key else 0o022)
            or parent.st_uid != owner or parent.st_mode & 0o077):
        raise Blocked('Runner configuration and receipt key require an owner-only directory')
    return path


def configuration(path):
    # Provision this file out of band on the runner. Site artifacts cannot
    # supply the adapter implementation, target, state directory or credentials.
    spec = importlib.util.spec_from_file_location('mrn_installed_site_adapter', protected(path))
    adapter_module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(adapter_module)
    configured = adapter_module.configure()
    key = bytes.fromhex(os.environ.get('MRN_RELEASE_RECEIPT_KEY', ''))
    return Controller(configured['adapter'], configured['target'], Journal(configured['journal'], key),
                      **configured.get('policy', {}))


def request_from(path):
    data = parse_json(Path(path).read_bytes())
    data['target'] = Target(**data['target'])
    return Request(**data)


def limits_from(path):
    return Limits(**parse_json(Path(path).read_bytes())) if path else Limits()


def add_package_args(parser):
    parser.add_argument('--source-sha', required=True)
    parser.add_argument('--limits', help='JSON media policy overrides')


def deploy_remaining(controller, request, archive, confirmed_origin=None):
    """Run approved remaining phases; recovery always remains an explicit action."""
    result = controller.run('preflight', request, archive, confirmed_origin=confirmed_origin)
    for _ in PHASES:
        if result['lock'] == 'none':
            return result
        phase = result['next']
        if result['recovery_required'] or phase not in PHASES:
            raise Blocked('Recovery is required; run status and follow its next command')
        result = controller.run(phase, request, archive if phase in ('preflight', 'activate', 'migrate') else None,
                                confirmed_origin=confirmed_origin)
    if result['lock'] != 'none':
        raise Blocked('Release did not reach a terminal state; inspect status')
    return result


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest='command', required=True)
    source = commands.add_parser('snapshot', help='version a reviewed non-Git file list without changing its source')
    source.add_argument('--directory', required=True)
    source.add_argument('--files', required=True, help='JSON list of reviewed relative source files')
    source.add_argument('--output', required=True)
    build = commands.add_parser('pack', help='pack exact Git migration bytes with an existing code artifact')
    add_package_args(build)
    build.add_argument('--repo', required=True)
    build.add_argument('--code', required=True)
    build.add_argument('--output', required=True)
    build.add_argument('--migration-prefix', help='reserve a v1-* script prefix in mixed legacy content trees')
    inspect_parser = commands.add_parser('inspect', help='verify and report media without contacting a target')
    add_package_args(inspect_parser)
    inspect_parser.add_argument('--archive', required=True)
    inspect_parser.add_argument('--sha256', required=True)
    for phase in ('status', 'deploy', *PHASES, 'recover', 'rollback', 'abort'):
        command = commands.add_parser(phase)
        command.add_argument('--adapter', required=True)
        if phase != 'status':
            command.add_argument('--request', required=True)
            command.add_argument('--confirm-target')
            if phase in ('deploy', 'preflight', 'activate', 'migrate'):
                command.add_argument('--archive', required=True)
    args = parser.parse_args(argv)
    try:
        if args.command == 'snapshot':
            from source_snapshot import snapshot
            result = snapshot(args.directory, parse_json(Path(args.files).read_bytes()), args.output)
        elif args.command == 'pack':
            result = pack(args.repo, args.source_sha, args.code, args.output, limits_from(args.limits),
                          migration_prefix=args.migration_prefix)
        elif args.command == 'inspect':
            with inspect(args.archive, args.sha256, args.source_sha, limits_from(args.limits)) as (_, manifest, report):
                result = {**report, 'manifest': manifest}
        else:
            controller = configuration(args.adapter)
            if args.command == 'status':
                result = controller.status()
            else:
                request = request_from(args.request)
                if args.command == 'deploy':
                    result = deploy_remaining(controller, request, args.archive, args.confirm_target)
                else:
                    result = controller.run(args.command, request, getattr(args, 'archive', None),
                                            confirmed_origin=args.confirm_target)
            phase = result['next']
            if phase == 'inspect-target':
                result['next_command'] = None
                result['operator_action'] = 'Reconcile the observed target identity and immutable release with the journal.'
            else:
                request_hint = getattr(args, 'request', None) or '<request.json>'
                if phase == 'preflight' and result.get('deployment_id') and result['lock'] == 'none':
                    request_hint = '<new-request.json>'
                parts = ['mrn-site-release', phase, '--adapter', args.adapter, '--request',
                         request_hint]
                if phase in ('preflight', 'activate', 'migrate'):
                    parts.extend(['--archive', getattr(args, 'archive', None) or '<release.tar>'])
                if controller.target.environment == 'production' or phase in ('recover', 'rollback'):
                    parts.extend(['--confirm-target', controller.target.origin])
                result['next_command'] = shlex.join(parts)
        print(json.dumps(result, indent=2))
        return 0
    except Blocked as error:
        print(json.dumps({'status': 'blocked', 'diagnostic': str(error)}), file=sys.stderr)
    except (ValueError, OSError, TypeError, KeyError, ImportError) as error:
        # The artifact-only commands have no target credentials. Runtime
        # adapters may emit provider details in exception messages.
        diagnostic = str(error) if args.command in ('pack', 'inspect', 'snapshot') else type(error).__name__
        print(json.dumps({'status': 'blocked', 'diagnostic': diagnostic}), file=sys.stderr)
    return 1
