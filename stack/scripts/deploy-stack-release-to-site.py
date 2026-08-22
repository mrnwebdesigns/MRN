#!/usr/bin/env python3
"""Deploy one assembled immutable MRN platform release to a CloudPanel site."""

from __future__ import annotations

import argparse
import datetime as dt
import importlib.util
import json
import os
import re
import shlex
import subprocess
import sys
from pathlib import Path
from urllib.parse import urlsplit


SCRIPT_DIR = Path(__file__).resolve().parent
LOCK_SCRIPT = SCRIPT_DIR / "generate-stack-release-lock.py"
LOCK_SPEC = importlib.util.spec_from_file_location(
    "generate_stack_release_lock", LOCK_SCRIPT
)
release_lock = importlib.util.module_from_spec(LOCK_SPEC)
LOCK_SPEC.loader.exec_module(release_lock)

HOSTNAME_RE = re.compile(
    r"^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+"
    r"[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$"
)
SLUG_RE = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._-]*$")
SSH_LOGIN_RE = re.compile(r"^[A-Za-z0-9._-]+@[A-Za-z0-9._-]+$")
SSH_HOST_RE = re.compile(r"^(?:[A-Za-z0-9._-]+@)?[A-Za-z0-9._-]+$")
ABSOLUTE_PATH_RE = re.compile(r"^/[A-Za-z0-9._/-]+$")
RELEASE_ID_RE = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._-]*$")


class DeployError(RuntimeError):
    """The release cannot be deployed or verified safely."""


def utc_now():
    return dt.datetime.now(dt.timezone.utc).replace(microsecond=0)


def isoformat(value):
    return value.astimezone(dt.timezone.utc).isoformat().replace("+00:00", "Z")


def run_checked(command):
    result = subprocess.run(
        [str(item) for item in command],
        capture_output=True,
        text=True,
        check=False,
    )
    if result.returncode != 0:
        detail = (result.stderr or result.stdout).strip()
        raise DeployError(
            f"Command failed ({' '.join(str(item) for item in command)}): "
            f"{detail or f'exit {result.returncode}'}"
        )
    return result


def normalize_hostname(value):
    text = str(value or "").strip().lower().rstrip(".")
    if "://" in text:
        parsed = urlsplit(text)
        text = str(parsed.hostname or "").lower().rstrip(".")
    if not HOSTNAME_RE.fullmatch(text):
        raise DeployError(f"Invalid site hostname: {value}")
    return text


def validate_slug(value, label):
    text = str(value or "").strip()
    if not SLUG_RE.fullmatch(text):
        raise DeployError(f"Invalid {label}: {value}")
    return text


def validate_absolute_path(value, label):
    text = str(value or "").strip().rstrip("/")
    if not ABSOLUTE_PATH_RE.fullmatch(text) or ".." in Path(text).parts:
        raise DeployError(f"Invalid {label}: {value}")
    return text


def parse_key_values(output):
    values = {}
    for raw in str(output or "").splitlines():
        key, separator, value = raw.partition("=")
        if separator and re.fullmatch(r"[A-Z][A-Z0-9_]*", key):
            values[key] = value
    return values


def read_json(path):
    try:
        payload = json.loads(Path(path).read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        raise DeployError(f"Could not read JSON from {path}: {error}") from error
    if not isinstance(payload, dict):
        raise DeployError(f"JSON root must be an object: {path}")
    return payload


def write_receipt(path, payload):
    destination = Path(path).expanduser().resolve()
    destination.parent.mkdir(parents=True, exist_ok=True)
    temporary = destination.with_name(destination.name + ".tmp")
    temporary.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    os.chmod(temporary, 0o600)
    os.replace(temporary, destination)


def verify_artifacts(payload, artifact_root, lock_path):
    root = Path(artifact_root).expanduser().resolve()
    results = []
    for entry in list(payload["components"]) + list(payload["themes"]):
        source = root / str(entry["deployed_path"])
        digest, count = release_lock.tree_sha256(source)
        if digest != entry["sha256"] or count != entry["file_count"]:
            raise DeployError(f"Artifact checksum mismatch for {entry['slug']}")
        results.append(
            {
                "slug": entry["slug"],
                "deployed_path": entry["deployed_path"],
                "sha256": digest,
                "file_count": count,
            }
        )
    embedded_lock = root / "mu-plugins/mrn-stack-release.lock.json"
    if not embedded_lock.is_file():
        raise DeployError("Assembled release does not contain its embedded lock")
    if release_lock.file_sha256(embedded_lock) != release_lock.file_sha256(Path(lock_path)):
        raise DeployError("Embedded release lock does not match selected lock")
    return results


def remote_wp(ssh_login, site_root, *arguments):
    command = " ".join(
        ["wp", f"--path={shlex.quote(site_root)}"]
        + [shlex.quote(str(argument)) for argument in arguments]
    )
    return ["ssh", ssh_login, command]


def read_live_shape(ssh_login, site_root, command_runner=run_checked):
    template = command_runner(
        remote_wp(ssh_login, site_root, "option", "get", "template", "--skip-themes")
    ).stdout.strip()
    stylesheet = command_runner(
        remote_wp(
            ssh_login, site_root, "option", "get", "stylesheet", "--skip-themes"
        )
    ).stdout.strip()
    return (
        validate_slug(template, "live template slug"),
        validate_slug(stylesheet, "live stylesheet slug"),
    )


def read_runtime_report(ssh_login, site_root, command_runner=run_checked):
    result = command_runner(remote_wp(ssh_login, site_root, "mrn", "stack-report"))
    try:
        payload = json.loads(result.stdout)
    except json.JSONDecodeError as error:
        raise DeployError("Site did not return a valid MRN runtime report") from error
    if not isinstance(payload, dict):
        raise DeployError("Site runtime report root is not an object")
    return payload


def deployment_operations(payload, artifact_root, template_slug):
    root = Path(artifact_root).expanduser().resolve()
    operations = []
    standard_plugins = []
    loader_operation = None

    for entry in payload["components"]:
        runtime_type = entry["runtime_type"]
        source = root / entry["deployed_path"]
        if runtime_type == "mu-loader":
            target = "wp-content/mu-plugins/mrn-loader.php"
        elif runtime_type == "mu-component":
            target = f"wp-content/mu-plugins/{entry['slug']}"
        elif runtime_type == "standard-plugin":
            target = f"wp-content/plugins/{entry['slug']}"
            standard_plugins.append(entry["slug"])
        elif runtime_type == "shared-runtime":
            target = "wp-content/shared"
        else:
            raise DeployError(
                f"Unsupported runtime type in release lock: {runtime_type}"
            )
        operation = {
            "slug": entry["slug"],
            "source": str(source),
            "target": target,
            "kind": "file" if source.is_file() else "directory",
        }
        if runtime_type == "mu-loader":
            loader_operation = operation
        else:
            operations.append(operation)

    exact_parent_themes = [
        theme
        for theme in payload["themes"]
        if theme.get("verification_mode") == "exact"
        and theme.get("deployment_role") == "parent-template"
    ]
    if len(exact_parent_themes) != 1:
        raise DeployError("Release lock must contain one exact parent-template theme")
    parent = exact_parent_themes[0]
    operations.append(
        {
            "slug": parent["slug"],
            "source": str(root / parent["deployed_path"]),
            "target": f"wp-content/themes/{template_slug}",
            "kind": "directory",
        }
    )
    if loader_operation:
        operations.append(loader_operation)
    operations.append(
        {
            "slug": "mrn-stack-release-lock",
            "source": str(root / "mu-plugins/mrn-stack-release.lock.json"),
            "target": "wp-content/mu-plugins/mrn-stack-release.lock.json",
            "kind": "file",
        }
    )

    targets = [operation["target"] for operation in operations]
    if len(targets) != len(set(targets)):
        raise DeployError("Release maps multiple artifacts to one live target")
    return operations, sorted(standard_plugins)


def verify_runtime_report(payload, report, lock_sha256):
    reported_lock = report.get("release_lock") or {}
    if (
        reported_lock.get("valid") is not True
        or reported_lock.get("release_id") != payload["release_id"]
        or reported_lock.get("sha256") != lock_sha256
    ):
        raise DeployError("Runtime report does not identify the selected release lock")
    if report.get("schema_version") != (
        payload.get("compatibility") or {}
    ).get("runtime_report_schema_version"):
        raise DeployError("Runtime report schema does not match release compatibility")
    for key in ("missing_required", "drifted_required", "legacy_flat_collisions"):
        if report.get(key):
            raise DeployError(f"Runtime report contains {key}: {report[key]}")
    exact_themes = {
        theme["slug"]
        for theme in payload["themes"]
        if theme.get("verification_mode") == "exact"
    }
    runtime_themes = {
        theme.get("slug"): theme
        for theme in report.get("themes") or []
        if isinstance(theme, dict)
    }
    for slug in exact_themes:
        if (runtime_themes.get(slug) or {}).get("matches_release") is not True:
            raise DeployError(f"Runtime parent theme does not match release: {slug}")
    return True


def create_code_archive(
    ssh_login, site_root, site_home, target_paths, archive_path, command_runner=run_checked
):
    manifest_path = f"{site_home}/deployment-backups/.archive-files-{os.getpid()}.txt"
    commands = [
        "set -eu",
        f"mkdir -p {shlex.quote(str(Path(archive_path).parent))}",
        f": > {shlex.quote(manifest_path)}",
        f"trap 'rm -f {shlex.quote(manifest_path)}' EXIT",
    ]
    for relative in target_paths:
        absolute = f"{site_root}/{relative}"
        commands.append(
            f"if [ -e {shlex.quote(absolute)} ]; then "
            f"printf '%s\\n' {shlex.quote(relative)} >> {shlex.quote(manifest_path)}; fi"
        )
    commands.extend(
        [
        f"tar -czf {shlex.quote(archive_path)} -C {shlex.quote(site_root)} "
        f"--files-from {shlex.quote(manifest_path)}",
        f"test -s {shlex.quote(archive_path)}",
        f"sha256sum {shlex.quote(archive_path)} | awk '{{print $1}}'",
        ]
    )
    output = command_runner(["ssh", ssh_login, "; ".join(commands)]).stdout.strip()
    digest = output.splitlines()[-1] if output else ""
    if not re.fullmatch(r"[0-9a-f]{64}", digest):
        raise DeployError("Remote code archive did not return a SHA-256 receipt")
    return digest


def verify_previous_runtime_report(report):
    reported_lock = report.get("release_lock") or {}
    if (
        reported_lock.get("valid") is not True
        or not reported_lock.get("release_id")
        or not re.fullmatch(r"[0-9a-f]{64}", str(reported_lock.get("sha256") or ""))
    ):
        raise DeployError("Existing runtime has no verified release identity")
    for key in ("missing_required", "drifted_required", "legacy_flat_collisions"):
        if report.get(key):
            raise DeployError(f"Existing runtime contains {key}: {report[key]}")
    return reported_lock


def sync_operation(operation, ssh_login, site_root, command_runner=run_checked):
    source = Path(operation["source"])
    target = f"{site_root}/{operation['target']}"
    parent = str(Path(target).parent)
    command_runner(["ssh", ssh_login, f"mkdir -p {shlex.quote(parent)}"])
    if operation["kind"] == "file":
        command_runner(
            ["rsync", "-rlt", "--chmod=F644", str(source), f"{ssh_login}:{target}"]
        )
    else:
        command_runner(
            [
                "rsync",
                "-rlt",
                "--delete",
                "--omit-dir-times",
                f"{source}/",
                f"{ssh_login}:{target}/",
            ]
        )


def normalize_permissions(ssh_login, site_root, target_paths, command_runner=run_checked):
    commands = ["set -eu"]
    for relative in target_paths:
        target = f"{site_root}/{relative}"
        commands.append(
            f"if [ -d {shlex.quote(target)} ]; then "
            f"find {shlex.quote(target)} -type d -exec chmod 755 {{}} +; "
            f"find {shlex.quote(target)} -type f -exec chmod 644 {{}} +; "
            f"elif [ -f {shlex.quote(target)} ]; then chmod 644 {shlex.quote(target)}; fi"
        )
    command_runner(["ssh", ssh_login, "; ".join(commands)])


def deploy(
    *,
    release_lock_path,
    artifact_root,
    site_hostname,
    discovery_ssh_host,
    receipt_out,
    backup_label=None,
    dry_run=False,
    now=None,
    command_runner=run_checked,
):
    now = now or utc_now()
    hostname = normalize_hostname(site_hostname)
    lock_path = Path(release_lock_path).expanduser().resolve()
    payload = release_lock.validate_lock(read_json(lock_path))
    if not RELEASE_ID_RE.fullmatch(str(payload.get("release_id") or "")):
        raise DeployError("Release ID is unsafe for deployment labels")
    if not SSH_HOST_RE.fullmatch(str(discovery_ssh_host or "")):
        raise DeployError("Discovery SSH host is unsafe")
    artifact_checks = verify_artifacts(payload, artifact_root, lock_path)
    lock_sha256 = release_lock.file_sha256(lock_path)
    if not backup_label:
        backup_label = (
            f"predeploy-{hostname}-{payload['release_id']}-"
            f"{now.strftime('%Y%m%d%H%M%S')}"
        )

    preflight = SCRIPT_DIR / "preflight-live-site-deploy.sh"
    preflight_command = [
        preflight,
        "--site-hostname",
        hostname,
        "--discovery-ssh-host",
        discovery_ssh_host,
    ]
    if dry_run:
        preflight_command.append("--skip-backup")
    else:
        preflight_command.extend(["--with-db-backup", "--backup-label", backup_label])
    preflight_result = command_runner(preflight_command)
    receipt_values = parse_key_values(preflight_result.stdout)
    required = {"SITE_HOSTNAME", "SITE_USER", "SITE_ROOT", "SSH_LOGIN"}
    if not required.issubset(receipt_values):
        raise DeployError("Live preflight did not return complete site-owner details")
    if normalize_hostname(receipt_values["SITE_HOSTNAME"]) != hostname:
        raise DeployError("Live preflight returned the wrong hostname")
    if not SSH_LOGIN_RE.fullmatch(receipt_values["SSH_LOGIN"]):
        raise DeployError("Live preflight returned an unsafe SSH login")
    site_root = validate_absolute_path(receipt_values["SITE_ROOT"], "site root")
    ssh_login = receipt_values["SSH_LOGIN"]
    template_slug, stylesheet_slug = read_live_shape(
        ssh_login, site_root, command_runner
    )
    operations, standard_plugins = deployment_operations(
        payload, artifact_root, template_slug
    )

    plan = {
        "release_id": payload["release_id"],
        "release_lock_sha256": lock_sha256,
        "hostname": hostname,
        "site_root": site_root,
        "site_user": receipt_values["SITE_USER"],
        "ssh_login": ssh_login,
        "template": template_slug,
        "stylesheet": stylesheet_slug,
        "child_theme_preserved": stylesheet_slug != template_slug,
        "artifact_checks": artifact_checks,
        "operations": operations,
        "standard_plugins_to_activate": standard_plugins,
        "dry_run": dry_run,
    }
    if dry_run:
        return plan

    if receipt_values.get("BACKUP_LABEL") != backup_label:
        raise DeployError("Verified backup receipt is missing or does not match")

    previous_report = read_runtime_report(ssh_login, site_root, command_runner)
    previous_release = verify_previous_runtime_report(previous_report)
    site_home = validate_absolute_path(
        command_runner(["ssh", ssh_login, "pwd"]).stdout.strip(), "site home"
    )
    archive_path = (
        f"{site_home}/deployment-backups/"
        f"pre-{payload['release_id']}-{now.strftime('%Y%m%d%H%M%S')}.tar.gz"
    )
    target_paths = [operation["target"] for operation in operations]
    archive_sha256 = create_code_archive(
        ssh_login,
        site_root,
        site_home,
        target_paths,
        archive_path,
        command_runner,
    )

    receipt = {
        "schema_version": 1,
        "status": "archive-created",
        "created_at": isoformat(now),
        "release_id": payload["release_id"],
        "release_lock_sha256": lock_sha256,
        "hostname": hostname,
        "site_user": receipt_values["SITE_USER"],
        "site_root": site_root,
        "ssh_login": ssh_login,
        "template": template_slug,
        "stylesheet": stylesheet_slug,
        "backup_label": backup_label,
        "remote_code_archive": archive_path,
        "remote_code_archive_sha256": archive_sha256,
        "target_paths": target_paths,
        "previous_release": previous_release,
    }
    write_receipt(receipt_out, receipt)

    try:
        for operation in operations:
            sync_operation(operation, ssh_login, site_root, command_runner)
        normalize_permissions(ssh_login, site_root, target_paths, command_runner)
        if standard_plugins:
            command_runner(
                remote_wp(
                    ssh_login,
                    site_root,
                    "plugin",
                    "activate",
                    *standard_plugins,
                )
            )
        report = read_runtime_report(ssh_login, site_root, command_runner)
        verify_runtime_report(payload, report, lock_sha256)
    except Exception as error:
        receipt["status"] = "deployment-failed"
        receipt["failed_at"] = isoformat(utc_now())
        receipt["error"] = str(error)
        write_receipt(receipt_out, receipt)
        if isinstance(error, DeployError):
            raise
        raise DeployError(str(error)) from error

    receipt["status"] = "verified-current"
    receipt["verified_at"] = isoformat(utc_now())
    receipt["runtime_release"] = report.get("release_lock")
    write_receipt(receipt_out, receipt)
    plan["receipt"] = str(Path(receipt_out).expanduser().resolve())
    plan["remote_code_archive"] = archive_path
    plan["runtime_verified"] = True
    return plan


def main(argv=None):
    default_root = Path(__file__).resolve().parents[2]
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--release-lock", required=True, type=Path)
    parser.add_argument("--artifact-root", required=True, type=Path)
    parser.add_argument("--site-hostname", required=True)
    parser.add_argument("--discovery-ssh-host", default="mrndev")
    parser.add_argument("--receipt-out", required=True, type=Path)
    parser.add_argument("--backup-label")
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args(argv)

    try:
        result = deploy(
            release_lock_path=args.release_lock,
            artifact_root=args.artifact_root,
            site_hostname=args.site_hostname,
            discovery_ssh_host=args.discovery_ssh_host,
            receipt_out=args.receipt_out,
            backup_label=args.backup_label,
            dry_run=args.dry_run,
        )
    except (DeployError, release_lock.ReleaseLockError) as error:
        print(f"ERROR: {error}", file=sys.stderr)
        return 1
    print(json.dumps(result, indent=2))
    if not args.dry_run:
        print(f"ROLLBACK_RECEIPT={Path(args.receipt_out).expanduser().resolve()}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
