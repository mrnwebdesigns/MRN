#!/usr/bin/env python3
"""Build a checksum-locked, one-site, upgrade-only optional-plugin plan."""

from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import json
import re
import subprocess
import sys
import zipfile
from pathlib import Path
from urllib.parse import urlsplit


SCRIPT_DIR = Path(__file__).resolve().parent
STACK_DIR = SCRIPT_DIR.parent
REPOSITORY_ROOT = STACK_DIR.parent
DEFAULT_CATALOG = STACK_DIR / "manifests" / "component-catalog.json"
DEFAULT_RELEASES = STACK_DIR / "manifests" / "optional-plugin-releases.json"
PLAN_ID_PATTERN = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._-]{2,127}$")
SLUG_PATTERN = re.compile(r"^(?:mrn-[a-z0-9-]+|background-video-popout-disabler)$")
VERSION_PATTERN = re.compile(r"^(\d+)\.(\d+)\.(\d+)$")
SHA256_PATTERN = re.compile(r"^[a-f0-9]{64}$")


class PlanError(RuntimeError):
    """The supplied release or site cannot produce a safe optional plan."""


def resolve_artifact_path(value: object) -> Path:
    """Resolve registry artifacts from the MRN repository, not the caller CWD."""
    path = Path(str(value or "")).expanduser()
    return (path if path.is_absolute() else REPOSITORY_ROOT / path).resolve()


def read_json(path: Path) -> dict:
    try:
        payload = json.loads(Path(path).read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        raise PlanError(f"Could not read JSON from {path}: {error}") from error
    if not isinstance(payload, dict):
        raise PlanError(f"JSON root must be an object: {path}")
    return payload


def file_sha256(path: Path) -> str:
    digest = hashlib.sha256()
    try:
        with Path(path).open("rb") as handle:
            for chunk in iter(lambda: handle.read(1024 * 1024), b""):
                digest.update(chunk)
    except OSError as error:
        raise PlanError(f"Could not read package {path}: {error}") from error
    return digest.hexdigest()


def parse_time(value: object, label: str) -> dt.datetime:
    text = str(value or "")
    try:
        parsed = dt.datetime.fromisoformat(text.replace("Z", "+00:00"))
    except ValueError as error:
        raise PlanError(f"{label} must be an ISO-8601 timestamp") from error
    if parsed.tzinfo is None:
        raise PlanError(f"{label} must include a timezone")
    return parsed.astimezone(dt.timezone.utc)


def format_time(value: dt.datetime) -> str:
    return value.astimezone(dt.timezone.utc).isoformat().replace("+00:00", "Z")


def version_tuple(value: object, label: str) -> tuple[int, int, int]:
    match = VERSION_PATTERN.fullmatch(str(value or ""))
    if not match:
        raise PlanError(f"{label} must use x.y.z versioning")
    return tuple(int(part) for part in match.groups())


def find_entry(payload: dict, collection: str, slug: str, label: str) -> dict:
    matches = [
        entry
        for entry in payload.get(collection, [])
        if isinstance(entry, dict) and entry.get("slug") == slug
    ]
    if len(matches) != 1:
        raise PlanError(f"{label} must contain exactly one {slug} entry")
    return matches[0]


def run_git(repo: Path, *arguments: str) -> str:
    result = subprocess.run(
        ["git", "-C", str(repo), *arguments],
        capture_output=True,
        text=True,
        check=False,
    )
    if result.returncode != 0:
        detail = (result.stderr or result.stdout).strip()
        raise PlanError(detail or f"git {' '.join(arguments)} failed for {repo}")
    return result.stdout.strip()


def validate_source(source: dict) -> dict:
    repo = Path(str(source.get("path") or "")).expanduser().resolve()
    expected_commit = str(source.get("git_commit") or "")
    if not re.fullmatch(r"[a-f0-9]{40}", expected_commit):
        raise PlanError("Release source git_commit must be a full SHA-1")
    if run_git(repo, "status", "--porcelain"):
        raise PlanError(f"Release source repository is dirty: {repo}")
    head = run_git(repo, "rev-parse", "HEAD")
    origin_main = run_git(repo, "rev-parse", "origin/main")
    if head != expected_commit or origin_main != expected_commit:
        raise PlanError("Release source must equal its registered commit and origin/main")
    return {
        "repository": str(source.get("repository") or ""),
        "path": str(repo),
        "git_commit": expected_commit,
    }


def zip_file_version(archive: zipfile.ZipFile, main_file: str) -> str:
    try:
        source = archive.read(main_file).decode("utf-8")
    except (KeyError, UnicodeDecodeError) as error:
        raise PlanError(f"Package main file is missing or invalid: {main_file}") from error
    match = re.search(r"^[ \t/*#@]*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$", source, re.M)
    if not match:
        raise PlanError("Package main file has no x.y.z Version header")
    return match.group(1)


def validate_zip_entries(archive: zipfile.ZipFile, slug: str) -> None:
    names = archive.namelist()
    if not names:
        raise PlanError("Package is empty")
    if len(names) != len(set(names)):
        raise PlanError("Package contains duplicate paths")
    for info in archive.infolist():
        name = info.filename.replace("\\", "/")
        parts = Path(name).parts
        is_symlink = (info.external_attr >> 16) & 0o170000 == 0o120000
        if (
            not name
            or name.startswith("/")
            or ".." in parts
            or not parts
            or parts[0] != slug
            or is_symlink
        ):
            raise PlanError(f"Package contains an unsafe path: {name}")


def validate_package(path: Path, release: dict, expected_version: str) -> dict:
    package = release.get("package") or {}
    path = Path(path).expanduser().resolve()
    expected_checksum = str(package.get("sha256") or "")
    if not SHA256_PATTERN.fullmatch(expected_checksum):
        raise PlanError("Registered package SHA-256 is invalid")
    if path.name != package.get("filename"):
        raise PlanError("Package filename does not match the release registry")
    try:
        size = path.stat().st_size
    except OSError as error:
        raise PlanError(f"Could not stat package {path}: {error}") from error
    if size != package.get("size_bytes"):
        raise PlanError("Package size does not match the release registry")
    actual_checksum = file_sha256(path)
    if actual_checksum != expected_checksum:
        raise PlanError("Package checksum does not match the release registry")

    slug = str(release.get("slug") or "")
    main_file = str(package.get("main_file") or "")
    try:
        with zipfile.ZipFile(path) as archive:
            validate_zip_entries(archive, slug)
            embedded_version = zip_file_version(archive, main_file)
    except (OSError, zipfile.BadZipFile) as error:
        raise PlanError(f"Package is not a readable ZIP: {path}") from error
    if embedded_version != expected_version:
        raise PlanError("Package version does not match the release registry")
    return {
        "path": str(path),
        "filename": path.name,
        "main_file": main_file,
        "size_bytes": size,
        "sha256": actual_checksum,
    }


def validate_readiness(
    site: dict,
    slug: str,
    current_version: str,
    rollback_main_file: str,
) -> tuple[dict, dict]:
    backup = site.get("backup_readiness")
    if not isinstance(backup, dict):
        raise PlanError("backup_readiness must be an object")
    required_backup = (
        "ready",
        "provider",
        "remote_destination_configured",
        "wp_cli_available",
        "backup_command_available",
    )
    if any(key not in backup for key in required_backup):
        raise PlanError("backup_readiness is incomplete")
    if (
        backup.get("ready") is not True
        or backup.get("remote_destination_configured") is not True
        or backup.get("wp_cli_available") is not True
        or backup.get("backup_command_available") is not True
    ):
        raise PlanError("Site is not ready for the required remote database backup")

    rollback = site.get("rollback_readiness")
    if not isinstance(rollback, dict) or rollback.get("ready") is not True:
        raise PlanError("Site is not rollback-ready")
    if rollback.get("version") != current_version:
        raise PlanError("Rollback version does not match the installed version")
    rollback_checksum = str(rollback.get("package_sha256") or "")
    if not SHA256_PATTERN.fullmatch(rollback_checksum):
        raise PlanError("Rollback package SHA-256 is invalid")
    rollback_path = Path(str(rollback.get("package_path") or "")).expanduser().resolve()
    if file_sha256(rollback_path) != rollback_checksum:
        raise PlanError("Rollback package checksum does not match")
    try:
        with zipfile.ZipFile(rollback_path) as archive:
            validate_zip_entries(archive, slug)
            embedded = zip_file_version(archive, rollback_main_file)
    except (OSError, zipfile.BadZipFile) as error:
        raise PlanError("Rollback package is not a readable ZIP") from error
    if embedded != current_version:
        raise PlanError("Rollback package version does not match the installed version")

    normalized_rollback = {
        **rollback,
        "package_path": str(rollback_path),
        "package_filename": rollback_path.name,
        "main_file": rollback_main_file,
        "size_bytes": rollback_path.stat().st_size,
        "package_sha256": rollback_checksum,
    }
    return backup, normalized_rollback


def validate_site(
    inventory: dict,
    slug: str,
    target_version: str,
    generated_at: dt.datetime,
    max_age_seconds: int,
    rollback_main_file: str,
) -> tuple[dict, dict, dict]:
    if inventory.get("schema_version") != 1:
        raise PlanError("Inventory schema_version must be 1")
    site = inventory.get("site")
    if not isinstance(site, dict):
        raise PlanError("Inventory must contain one exact site object")
    site_id = site.get("site_id")
    if not isinstance(site_id, int) or isinstance(site_id, bool) or site_id < 1:
        raise PlanError("site_id must be a positive integer")
    site_url = str(site.get("site_url") or "")
    parsed_url = urlsplit(site_url)
    if (
        parsed_url.scheme != "https"
        or not parsed_url.hostname
        or parsed_url.username
        or parsed_url.password
        or parsed_url.query
        or parsed_url.fragment
    ):
        raise PlanError("site_url must be one exact credential-free HTTPS URL")

    synced_at = parse_time(site.get("inventory_synced_at"), "inventory_synced_at")
    age = int((generated_at - synced_at).total_seconds())
    if age < -60 or age > max_age_seconds:
        raise PlanError("Site inventory is not fresh")
    age = max(0, age)

    plugin = site.get("plugin")
    if not isinstance(plugin, dict) or plugin.get("slug") != slug:
        raise PlanError("Inventory does not describe the requested plugin")
    if plugin.get("installed") is not True:
        raise PlanError("Plugin is absent; new installation requires a separate authorized plan")
    if not isinstance(plugin.get("active"), bool):
        raise PlanError("Plugin active state must be explicit")
    current_version = str(plugin.get("version") or "")
    current_tuple = version_tuple(current_version, "Installed plugin version")
    target_tuple = version_tuple(target_version, "Target plugin version")
    if current_tuple >= target_tuple:
        raise PlanError("An upgrade-only plan requires current_version < target_version")

    backup, rollback = validate_readiness(
        site,
        slug,
        current_version,
        rollback_main_file,
    )
    normalized_site = {
        "site_id": site_id,
        "site_url": site_url,
        "inventory_synced_at": format_time(synced_at),
        "inventory_age_seconds": age,
        "installed": True,
        "active": plugin["active"],
        "current_version": current_version,
        "target_version": target_version,
    }
    return normalized_site, backup, rollback


def build_plan(
    *,
    catalog_path: Path,
    releases_path: Path,
    inventory_path: Path,
    artifact_path: Path | None,
    plugin_slug: str,
    plan_id: str,
    generated_at: dt.datetime,
    max_inventory_age_seconds: int,
) -> dict:
    if not SLUG_PATTERN.fullmatch(plugin_slug):
        raise PlanError("plugin_slug must be an MRN plugin slug")
    if not PLAN_ID_PATTERN.fullmatch(plan_id):
        raise PlanError("plan_id is invalid")
    if max_inventory_age_seconds < 60 or max_inventory_age_seconds > 3600:
        raise PlanError("max inventory age must be between 60 and 3600 seconds")

    catalog = read_json(catalog_path)
    releases = read_json(releases_path)
    inventory = read_json(inventory_path)
    catalog_entry = find_entry(catalog, "components", plugin_slug, "Component catalog")
    release = find_entry(releases, "releases", plugin_slug, "Release registry")
    target_version = str(release.get("version") or "")
    version_tuple(target_version, "Target plugin version")

    if catalog_entry.get("target_tier") == "platform-required":
        raise PlanError("Platform-required components belong to the schema-2 release")
    current_distribution = catalog_entry.get("current_distribution")
    if current_distribution not in {"catalog-only", "standard-bootstrap"}:
        raise PlanError(
            "Optional plan targets must be catalog-only or standard-bootstrap"
        )
    if catalog_entry.get("version") != target_version:
        raise PlanError("Catalog and release registry versions differ")
    if release.get("target_tier") != catalog_entry.get("target_tier"):
        raise PlanError("Catalog and release registry target tiers differ")
    if release.get("current_distribution") != current_distribution:
        raise PlanError("Catalog and release registry distributions differ")
    if (release.get("update_policy") or {}).get("mode") != "upgrade-only":
        raise PlanError("Release registry must require upgrade-only execution")

    source = validate_source(release.get("source") or {})
    registered_path = resolve_artifact_path(
        (release.get("package") or {}).get("path")
    )
    package = validate_package(
        artifact_path or registered_path,
        release,
        target_version,
    )
    site, backup, rollback = validate_site(
        inventory,
        plugin_slug,
        target_version,
        generated_at,
        max_inventory_age_seconds,
        package["main_file"],
    )

    return {
        "schema_version": 1,
        "plan_type": "optional-plugin-update",
        "plan_id": plan_id,
        "generated_at": format_time(generated_at),
        "plugin": {
            "slug": plugin_slug,
            "target_version": target_version,
            "target_tier": catalog_entry["target_tier"],
            "current_distribution": catalog_entry["current_distribution"],
            "source": source,
            "package": package,
        },
        "site": site,
        "operation": "upgrade-only",
        "preflight": {
            "ready": True,
            "package_sha256": package["sha256"],
            "backup_readiness": backup,
            "rollback_readiness": rollback,
            "blockers": [],
        },
        "execution_contract": {
            "preflight_ability": "mrn-mainwp/preflight-optional-plugin-update-v1",
            "controller_ability": "mrn-mainwp/update-optional-plugin-v1",
            "rollback_ability": "mrn-mainwp/rollback-optional-plugin-v1",
            "minimum_controller_version": "0.9.1",
            "precondition_hash_source": "controller-preflight",
            "rollback_artifact_model": "operator-supplied-checksum-locked-package",
            "allow_new_install": False,
            "preserve_active_state": True,
            "requires_fresh_inventory_recheck": True,
            "requires_confirm": True,
            "requires_fresh_database_backup_receipt": True,
            "requires_precondition_hash": True,
        },
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--catalog", type=Path, default=DEFAULT_CATALOG)
    parser.add_argument("--release-registry", type=Path, default=DEFAULT_RELEASES)
    parser.add_argument("--inventory", type=Path, required=True)
    parser.add_argument("--artifact", type=Path)
    parser.add_argument("--plugin-slug", required=True)
    parser.add_argument("--plan-id", required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--as-of")
    parser.add_argument("--max-inventory-age-seconds", type=int, default=900)
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    generated_at = (
        parse_time(args.as_of, "as_of")
        if args.as_of
        else dt.datetime.now(dt.timezone.utc).replace(microsecond=0)
    )
    try:
        plan = build_plan(
            catalog_path=args.catalog,
            releases_path=args.release_registry,
            inventory_path=args.inventory,
            artifact_path=args.artifact,
            plugin_slug=args.plugin_slug,
            plan_id=args.plan_id,
            generated_at=generated_at,
            max_inventory_age_seconds=args.max_inventory_age_seconds,
        )
        encoded = (json.dumps(plan, indent=2, sort_keys=True) + "\n").encode("utf-8")
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_bytes(encoded)
        print(
            json.dumps(
                {
                    "output": str(args.output.resolve()),
                    "plan_sha256": hashlib.sha256(encoded).hexdigest(),
                    "site_id": plan["site"]["site_id"],
                    "site_url": plan["site"]["site_url"],
                    "operation": plan["operation"],
                    "current_version": plan["site"]["current_version"],
                    "target_version": plan["site"]["target_version"],
                    "package_sha256": plan["preflight"]["package_sha256"],
                    "ready": plan["preflight"]["ready"],
                },
                sort_keys=True,
            )
        )
    except PlanError as error:
        print(f"ERROR: {error}", file=sys.stderr)
        return 2
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
