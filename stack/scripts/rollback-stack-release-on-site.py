#!/usr/bin/env python3
"""Restore the code archive recorded by a verified MRN deployment receipt."""

from __future__ import annotations

import argparse
import datetime as dt
import importlib.util
import json
import os
import re
import shlex
import sys
from pathlib import Path


SCRIPT_DIR = Path(__file__).resolve().parent
DEPLOY_SCRIPT = SCRIPT_DIR / "deploy-stack-release-to-site.py"
DEPLOY_SPEC = importlib.util.spec_from_file_location(
    "deploy_stack_release_to_site", DEPLOY_SCRIPT
)
deploy_helper = importlib.util.module_from_spec(DEPLOY_SPEC)
DEPLOY_SPEC.loader.exec_module(deploy_helper)


class RollbackError(RuntimeError):
    """The recorded deployment cannot be rolled back safely."""


def validate_receipt(payload):
    required = {
        "schema_version",
        "status",
        "release_id",
        "hostname",
        "site_user",
        "site_root",
        "ssh_login",
        "remote_code_archive",
        "remote_code_archive_sha256",
        "target_paths",
        "previous_release",
    }
    missing = sorted(required - set(payload))
    if missing:
        raise RollbackError("Rollback receipt is missing: " + ", ".join(missing))
    if payload["schema_version"] != 1:
        raise RollbackError("Unsupported rollback receipt schema")
    if payload["status"] not in {"deployment-failed", "verified-current"}:
        raise RollbackError(
            f"Receipt status is not rollback-eligible: {payload['status']}"
        )
    deploy_helper.normalize_hostname(payload["hostname"])
    deploy_helper.validate_absolute_path(payload["site_root"], "site root")
    deploy_helper.validate_absolute_path(
        payload["remote_code_archive"], "remote code archive"
    )
    if (
        "/deployment-backups/" not in payload["remote_code_archive"]
        or not payload["remote_code_archive"].endswith(".tar.gz")
    ):
        raise RollbackError("Receipt archive is outside deployment-backups")
    if not re.fullmatch(
        r"[0-9a-f]{64}", str(payload["remote_code_archive_sha256"])
    ):
        raise RollbackError("Receipt archive checksum is invalid")
    if not deploy_helper.SSH_LOGIN_RE.fullmatch(str(payload["ssh_login"])):
        raise RollbackError("Receipt contains an unsafe SSH login")
    if not isinstance(payload["target_paths"], list) or not payload["target_paths"]:
        raise RollbackError("Receipt contains no target paths")
    for relative in payload["target_paths"]:
        path = Path(str(relative))
        if path.is_absolute() or ".." in path.parts or not str(relative).startswith(
            "wp-content/"
        ):
            raise RollbackError(f"Unsafe rollback target: {relative}")
    previous = payload.get("previous_release") or {}
    if (
        previous.get("valid") is not True
        or not previous.get("release_id")
        or not previous.get("sha256")
    ):
        raise RollbackError("Receipt has no verified previous runtime release")
    if payload.get("rollback"):
        raise RollbackError("Receipt already records a completed rollback")
    return payload


def rollback(
    receipt_path,
    confirmation,
    *,
    discovery_ssh_host="mrndev",
    now=None,
    command_runner=deploy_helper.run_checked,
):
    now = now or dt.datetime.now(dt.timezone.utc).replace(microsecond=0)
    receipt = validate_receipt(deploy_helper.read_json(receipt_path))
    expected_confirmation = (
        f"ROLLBACK {receipt['hostname']} {receipt['release_id']}"
    )
    if confirmation != expected_confirmation:
        raise RollbackError("Rollback confirmation phrase does not match receipt")

    backup_label = (
        f"prerollback-{receipt['hostname']}-{receipt['release_id']}-"
        f"{now.strftime('%Y%m%d%H%M%S')}"
    )
    preflight = command_runner(
        [
            SCRIPT_DIR / "preflight-live-site-deploy.sh",
            "--site-hostname",
            receipt["hostname"],
            "--discovery-ssh-host",
            discovery_ssh_host,
            "--with-db-backup",
            "--backup-label",
            backup_label,
        ]
    )
    values = deploy_helper.parse_key_values(preflight.stdout)
    if (
        values.get("BACKUP_LABEL") != backup_label
        or values.get("SSH_LOGIN") != receipt["ssh_login"]
        or values.get("SITE_ROOT") != receipt["site_root"]
    ):
        raise RollbackError("Rollback preflight receipt does not match deployment receipt")

    archive = receipt["remote_code_archive"]
    site_root = receipt["site_root"]
    ssh_login = receipt["ssh_login"]
    commands = [
        "set -eu",
        f"test -f {shlex.quote(archive)}",
        f"printf '%s  %s\\n' {shlex.quote(receipt['remote_code_archive_sha256'])} "
        f"{shlex.quote(archive)} | sha256sum -c -",
    ]
    for relative in receipt["target_paths"]:
        commands.append(f"rm -rf {shlex.quote(f'{site_root}/{relative}')}")
    commands.extend(
        [
            f"tar -xzf {shlex.quote(archive)} -C {shlex.quote(site_root)}",
        ]
    )
    command_runner(["ssh", ssh_login, "; ".join(commands)])
    deploy_helper.normalize_permissions(
        ssh_login, site_root, receipt["target_paths"], command_runner
    )

    report = deploy_helper.read_runtime_report(ssh_login, site_root, command_runner)
    previous = receipt["previous_release"]
    reported = report.get("release_lock") or {}
    if (
        reported.get("valid") is not True
        or reported.get("release_id") != previous["release_id"]
        or reported.get("sha256") != previous["sha256"]
    ):
        raise RollbackError("Restored runtime does not match the previous release receipt")
    for key in ("missing_required", "drifted_required", "legacy_flat_collisions"):
        if report.get(key):
            raise RollbackError(f"Restored runtime contains {key}: {report[key]}")

    receipt["rollback"] = {
        "status": "verified-restored",
        "restored_at": deploy_helper.isoformat(now),
        "pre_rollback_backup_label": backup_label,
        "restored_release": reported,
    }
    deploy_helper.write_receipt(receipt_path, receipt)
    return receipt["rollback"]


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--receipt", required=True, type=Path)
    parser.add_argument("--confirm", required=True)
    parser.add_argument("--discovery-ssh-host", default="mrndev")
    args = parser.parse_args(argv)
    try:
        result = rollback(
            args.receipt,
            args.confirm,
            discovery_ssh_host=args.discovery_ssh_host,
        )
    except (RollbackError, deploy_helper.DeployError) as error:
        print(f"ERROR: {error}", file=sys.stderr)
        return 1
    print(json.dumps(result, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
