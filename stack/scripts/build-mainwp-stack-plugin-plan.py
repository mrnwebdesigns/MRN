#!/usr/bin/env python3
"""Build a checksum-locked, one-site update plan for one Stack standard plugin."""

from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import importlib.util
import io
import json
import re
import subprocess
import sys
import tarfile
import zipfile
from pathlib import Path
from urllib.parse import urlsplit


SCRIPT_DIR = Path(__file__).resolve().parent
STACK_DIR = SCRIPT_DIR.parent
REPOSITORY_ROOT = STACK_DIR.parent
DEFAULT_CATALOG = STACK_DIR / "manifests" / "component-catalog.json"
DEFAULT_RELEASES = STACK_DIR / "manifests" / "stack-plugin-releases.json"
DEFAULT_LOCK = STACK_DIR / "manifests" / "stack-release.lock.json"
DEFAULT_LOCK_ARCHIVE = STACK_DIR / "manifests" / "release-locks"
COMMON_BUILDER = SCRIPT_DIR / "build-mainwp-optional-plugin-plan.py"
PLAN_ID_PATTERN = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._-]{2,127}$")
SLUG_PATTERN = re.compile(r"^mrn-[a-z0-9-]+$")
SHA256_PATTERN = re.compile(r"^[a-f0-9]{64}$")
HASH_ALGORITHM = "sha256-tree-v1"

_SPEC = importlib.util.spec_from_file_location("mrn_optional_plugin_plan_common", COMMON_BUILDER)
if _SPEC is None or _SPEC.loader is None:
    raise RuntimeError(f"Could not load shared plan validation from {COMMON_BUILDER}")
_COMMON = importlib.util.module_from_spec(_SPEC)
_SPEC.loader.exec_module(_COMMON)

PlanError = _COMMON.PlanError
read_json = _COMMON.read_json
file_sha256 = _COMMON.file_sha256
parse_time = _COMMON.parse_time
format_time = _COMMON.format_time
version_tuple = _COMMON.version_tuple
run_git = _COMMON.run_git
validate_zip_entries = _COMMON.validate_zip_entries
zip_file_version = _COMMON.zip_file_version


def resolve_release_lock_path(
    inventory_path: Path,
    *,
    explicit_lock: Path | None,
    archive_dir: Path = DEFAULT_LOCK_ARCHIVE,
    current_lock: Path = DEFAULT_LOCK,
) -> Path:
    """Select the exact immutable lock signed by the site's runtime report."""
    if explicit_lock is not None:
        return explicit_lock.expanduser().resolve()

    inventory = read_json(inventory_path)
    site = inventory.get("site")
    release = site.get("release") if isinstance(site, dict) else None
    if not isinstance(release, dict):
        raise PlanError("Site inventory must include the signed Stack release identity")
    release_id = str(release.get("release_id") or "")
    lock_sha = str(release.get("lock_sha256") or "")
    if not PLAN_ID_PATTERN.fullmatch(release_id) or not SHA256_PATTERN.fullmatch(lock_sha):
        raise PlanError("Site inventory contains an invalid Stack release identity")

    current_lock = current_lock.expanduser().resolve()
    archived_lock = (archive_dir.expanduser().resolve() / f"{release_id}.json").resolve()
    if archived_lock.parent != archive_dir.expanduser().resolve():
        raise PlanError("Site Stack release identity does not map to a safe lock path")

    for candidate in (current_lock, archived_lock):
        if not candidate.is_file() or candidate.is_symlink():
            continue
        lock = read_json(candidate)
        if lock.get("release_id") == release_id and file_sha256(candidate) == lock_sha:
            return candidate

    if archived_lock.is_file():
        raise PlanError("Archived release lock does not match the signed site identity")
    raise PlanError(
        "No immutable release lock is retained for the signed site identity; "
        "archive the exact reviewed lock before planning this update"
    )


def resolve_artifact_path(value: object) -> Path:
    """Resolve registry artifact paths relative to the MRN repository root."""
    path = Path(str(value or "")).expanduser()
    return (path if path.is_absolute() else REPOSITORY_ROOT / path).resolve()


def find_catalog_entry(catalog: dict, slug: str) -> dict:
    matches = [
        entry
        for entry in catalog.get("components", [])
        if isinstance(entry, dict) and entry.get("slug") == slug
    ]
    if len(matches) != 1:
        raise PlanError(f"Component catalog must contain exactly one {slug} entry")
    return matches[0]


def find_release(releases: dict, slug: str, version: str) -> dict:
    matches = [
        entry
        for entry in releases.get("releases", [])
        if isinstance(entry, dict)
        and entry.get("slug") == slug
        and entry.get("version") == version
    ]
    if len(matches) != 1:
        raise PlanError(f"Release registry must contain exactly one {slug} {version} entry")
    return matches[0]


def validate_source(source: dict, *, current_target: bool) -> dict:
    """Require clean source and a registered commit already present on origin/main."""
    repo = Path(str(source.get("path") or "")).expanduser().resolve()
    expected_commit = str(source.get("git_commit") or "")
    if not re.fullmatch(r"[a-f0-9]{40}", expected_commit):
        raise PlanError("Release source git_commit must be a full SHA-1")
    if run_git(repo, "status", "--porcelain"):
        raise PlanError(f"Release source repository is dirty: {repo}")
    origin_main = run_git(repo, "rev-parse", "origin/main")
    result = subprocess.run(
        ["git", "-C", str(repo), "merge-base", "--is-ancestor", expected_commit, origin_main],
        capture_output=True,
        text=True,
        check=False,
    )
    if result.returncode != 0:
        raise PlanError("Registered release source is not contained in origin/main")
    if current_target and origin_main != expected_commit:
        raise PlanError("Target release source must equal the current origin/main commit")
    return {
        "repository": str(source.get("repository") or ""),
        "path": str(repo),
        "git_commit": expected_commit,
    }


def tree_order(paths: list[str], prefix: str = "") -> list[str]:
    """Match release/runtime files-first recursive sha256-tree-v1 traversal."""
    direct: list[str] = []
    directories: set[str] = set()
    for relative in paths:
        if prefix and not relative.startswith(prefix):
            continue
        remainder = relative[len(prefix) :] if prefix else relative
        if "/" not in remainder:
            direct.append(relative)
        else:
            directories.add(remainder.split("/", 1)[0])
    ordered = sorted(direct, key=lambda item: item.rsplit("/", 1)[-1])
    for directory in sorted(directories):
        ordered.extend(tree_order(paths, f"{prefix}{directory}/"))
    return ordered


def is_deployable_path(relative: str) -> bool:
    """Apply the release/runtime exclusions to one repository-relative path."""
    parts = relative.split("/")
    excluded_directories = {
        ".git",
        ".tmp",
        "node_modules",
        "playwright-report",
        "test-results",
        "zip",
        "ai-data",
    }
    return not (
        not relative
        or ".DS_Store" in parts
        or ".git" in parts
        or any(part in excluded_directories for part in parts)
        or (len(parts) > 1 and parts[0] == "vendor")
    )


def git_tree_hash(repo: Path, commit: str) -> tuple[str, int]:
    """Hash the exact committed Git export without changing checkout state."""
    exported = subprocess.run(
        ["git", "-C", str(repo), "archive", "--format=tar", commit],
        capture_output=True,
        check=False,
    )
    if exported.returncode != 0:
        raise PlanError("Could not export the registered release source tree")
    files: dict[str, bytes] = {}
    try:
        with tarfile.open(fileobj=io.BytesIO(exported.stdout), mode="r:") as archive:
            for member in archive.getmembers():
                relative = member.name.rstrip("/")
                if member.isdir() or not is_deployable_path(relative):
                    continue
                if member.issym() or member.islnk():
                    raise PlanError("Registered release source contains a symlink")
                if not member.isfile():
                    raise PlanError(
                        f"Registered release source contains an unsupported entry: {relative}"
                    )
                handle = archive.extractfile(member)
                if handle is None:
                    raise PlanError(f"Could not read registered source file: {relative}")
                files[relative] = handle.read()
    except tarfile.TarError as error:
        raise PlanError("Registered release source is not a valid Git archive") from error
    if not files:
        raise PlanError("Registered release source contains no deployable files")
    digest = hashlib.sha256()
    ordered = tree_order(list(files))
    for relative in ordered:
        contents = files[relative]
        digest.update(
            relative.encode("utf-8")
            + b"\0"
            + hashlib.sha256(contents).hexdigest().encode("ascii")
            + b"\0"
            + str(len(contents)).encode("ascii")
            + b"\n"
        )
    return digest.hexdigest(), len(ordered)


def hash_records(files: dict[str, bytes]) -> tuple[str, int]:
    """Hash in the shared files-first recursive order."""
    if not files:
        raise PlanError("Package contains no deployable files")
    digest = hashlib.sha256()
    ordered = tree_order(list(files))
    for relative in ordered:
        contents = files[relative]
        digest.update(
            relative.encode("utf-8")
            + b"\0"
            + hashlib.sha256(contents).hexdigest().encode("ascii")
            + b"\0"
            + str(len(contents)).encode("ascii")
            + b"\n"
        )
    return digest.hexdigest(), len(ordered)


def validate_legacy_supplements(release: dict, *, allowed: bool) -> dict[str, dict]:
    """Normalize exact legacy files retained solely for rollback parity."""
    records = release.get("legacy_supplements", [])
    if not isinstance(records, list):
        raise PlanError("legacy_supplements must be an array")
    if records and not allowed:
        raise PlanError("The current target release cannot contain legacy supplement files")
    normalized: dict[str, dict] = {}
    for record in records:
        if not isinstance(record, dict):
            raise PlanError("Legacy supplement record is invalid")
        path = str(record.get("path") or "")
        sha256 = str(record.get("sha256") or "")
        size = record.get("size_bytes")
        if (
            not path.startswith("ai-data/")
            or ".." in Path(path).parts
            or not SHA256_PATTERN.fullmatch(sha256)
            or not isinstance(size, int)
            or isinstance(size, bool)
            or size < 1
            or path in normalized
        ):
            raise PlanError("Legacy supplement must be one exact checksummed ai-data file")
        normalized[path] = {"sha256": sha256, "size_bytes": size}
    return normalized


def zip_tree_evidence(
    archive: zipfile.ZipFile,
    slug: str,
    legacy_supplements: dict[str, dict] | None = None,
) -> tuple[tuple[str, int], tuple[str, int]]:
    """Calculate full rollback tree and commit-owned source-tree evidence."""
    files: dict[str, bytes] = {}
    source_files: dict[str, bytes] = {}
    supplements = legacy_supplements or {}
    prefix = f"{slug}/"
    for info in archive.infolist():
        name = info.filename.replace("\\", "/")
        if name.endswith("/"):
            continue
        relative = name[len(prefix) :]
        if not is_deployable_path(relative) and relative not in supplements:
            raise PlanError(f"Package contains a path excluded from the Stack tree: {name}")
        if relative in files:
            raise PlanError(f"Package contains a duplicate deployable path: {name}")
        files[relative] = archive.read(info)
        if relative not in supplements:
            source_files[relative] = files[relative]
    for relative, expected in supplements.items():
        contents = files.get(relative)
        if (
            contents is None
            or len(contents) != expected["size_bytes"]
            or hashlib.sha256(contents).hexdigest() != expected["sha256"]
        ):
            raise PlanError(f"Legacy rollback supplement does not match: {relative}")
    return hash_records(files), hash_records(source_files)


def zip_tree_hash(archive: zipfile.ZipFile, slug: str) -> tuple[str, int]:
    """Calculate a clean sha256-tree-v1 from an exact-root plugin archive."""
    full, _source = zip_tree_evidence(archive, slug)
    return full


def validate_release_contract(release: dict, catalog_entry: dict) -> None:
    if release.get("runtime_type") != "standard-plugin":
        raise PlanError("Selective Stack releases must be standard plugins")
    if release.get("target_tier") != "platform-required":
        raise PlanError("Selective Stack releases must be platform-required")
    if release.get("current_distribution") != "standard-bootstrap":
        raise PlanError("Selective Stack releases must use standard-bootstrap distribution")
    if (release.get("update_policy") or {}).get("mode") != "upgrade-only":
        raise PlanError("Selective Stack release registry must require upgrade-only execution")
    if release.get("target_tier") != catalog_entry.get("target_tier"):
        raise PlanError("Catalog and release target tiers differ")
    if release.get("current_distribution") != catalog_entry.get("current_distribution"):
        raise PlanError("Catalog and release distributions differ")


def validate_package(
    path: Path,
    release: dict,
    expected_version: str,
    *,
    allow_legacy_supplements: bool,
) -> tuple[dict, dict, tuple[str, int]]:
    """Validate package bytes, root, version, and deployable tree."""
    package = release.get("package") or {}
    tree = release.get("tree") or {}
    path = Path(path).expanduser().resolve()
    expected_checksum = str(package.get("sha256") or "")
    expected_tree = str(tree.get("sha256") or "")
    expected_count = tree.get("file_count")
    supplements = validate_legacy_supplements(
        release, allowed=allow_legacy_supplements
    )
    if not SHA256_PATTERN.fullmatch(expected_checksum):
        raise PlanError("Registered package SHA-256 is invalid")
    if tree.get("hash_algorithm") != HASH_ALGORITHM or not SHA256_PATTERN.fullmatch(expected_tree):
        raise PlanError("Registered deployable tree contract is invalid")
    if not isinstance(expected_count, int) or isinstance(expected_count, bool) or expected_count < 1:
        raise PlanError("Registered deployable file count is invalid")
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
    if main_file != f"{slug}/{slug}.php":
        raise PlanError("Selective Stack packages require the conventional exact main file")
    try:
        with zipfile.ZipFile(path) as archive:
            validate_zip_entries(archive, slug)
            embedded_version = zip_file_version(archive, main_file)
            (actual_tree, actual_count), source_tree = zip_tree_evidence(
                archive, slug, supplements
            )
    except (OSError, zipfile.BadZipFile) as error:
        raise PlanError(f"Package is not a readable ZIP: {path}") from error
    if embedded_version != expected_version:
        raise PlanError("Package version does not match the release registry")
    if actual_tree != expected_tree or actual_count != expected_count:
        raise PlanError("Package deployable tree does not match the release registry")
    return (
        {
            "path": str(path),
            "filename": path.name,
            "main_file": main_file,
            "size_bytes": size,
            "sha256": actual_checksum,
        },
        {
            "hash_algorithm": HASH_ALGORITHM,
            "sha256": actual_tree,
            "file_count": actual_count,
        },
        source_tree,
    )


def validate_backup_readiness(site: dict) -> dict:
    backup = site.get("backup_readiness")
    if not isinstance(backup, dict):
        raise PlanError("backup_readiness must be an object")
    if backup.get("ready") is not True or backup.get("remote_destination_configured") is not True:
        raise PlanError("Site is not ready for the required remote database backup")
    has_api = backup.get("backup_api_available") is True
    has_command = backup.get("backup_command_available") is True
    if not has_api and not has_command:
        raise PlanError("Backup readiness must prove an available backup API or command")
    if "plugin_installed" in backup and backup.get("plugin_installed") is not True:
        raise PlanError("Backup provider plugin is not installed")
    if "plugin_active" in backup and backup.get("plugin_active") is not True:
        raise PlanError("Backup provider plugin is not active")
    return backup


def validate_baseline(lock_path: Path, site: dict, slug: str) -> tuple[dict, dict]:
    lock = read_json(lock_path)
    if lock.get("schema_version") != 1 or lock.get("hash_algorithm") != HASH_ALGORITHM:
        raise PlanError("Immutable Stack release lock is invalid")
    release_id = str(lock.get("release_id") or "")
    lock_sha = file_sha256(lock_path)
    reported = site.get("release")
    if not isinstance(reported, dict):
        raise PlanError("Site inventory must include the signed Stack release identity")
    if reported.get("present") is not True or reported.get("valid") is not True:
        raise PlanError("Site does not report a valid immutable Stack release lock")
    if reported.get("release_id") != release_id or reported.get("lock_sha256") != lock_sha:
        raise PlanError("Site Stack release identity does not match the reviewed baseline")
    matches = [
        component
        for component in lock.get("components", [])
        if isinstance(component, dict) and component.get("slug") == slug
    ]
    if len(matches) != 1 or matches[0].get("runtime_type") != "standard-plugin":
        raise PlanError("Requested plugin is not exactly one standard-plugin baseline component")
    return (
        {
            "release_id": release_id,
            "lock_sha256": lock_sha,
            "hash_algorithm": HASH_ALGORITHM,
        },
        matches[0],
    )


def validate_site(
    inventory: dict,
    *,
    slug: str,
    main_file: str,
    target_version: str,
    rollback_release: dict,
    baseline_lock: Path,
    generated_at: dt.datetime,
    max_age_seconds: int,
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

    baseline, _baseline_component = validate_baseline(baseline_lock, site, slug)
    plugin = site.get("plugin")
    if not isinstance(plugin, dict) or plugin.get("slug") != slug:
        raise PlanError("Inventory does not describe the requested Stack plugin")
    if plugin.get("main_file") != main_file:
        raise PlanError("Inventory plugin main file does not match the release package")
    if plugin.get("installed") is not True:
        raise PlanError("Plugin is absent; new installation requires a separate authorized plan")
    if not isinstance(plugin.get("active"), bool):
        raise PlanError("Plugin active state must be explicit")
    if plugin.get("loaded") is not True or plugin.get("active") is not True:
        raise PlanError("A platform-required Stack plugin must be active and loaded")
    if plugin.get("runtime_type") != "standard-plugin" or plugin.get("path") != f"plugins/{slug}":
        raise PlanError("Inventory runtime target is not the exact Stack standard-plugin path")
    if plugin.get("hash_algorithm") != HASH_ALGORITHM:
        raise PlanError("Inventory runtime hash algorithm is invalid")

    current_version = str(plugin.get("version") or "")
    current_tree = str(plugin.get("tree_sha256") or "")
    current_count = plugin.get("file_count")
    if not SHA256_PATTERN.fullmatch(current_tree):
        raise PlanError("Inventory runtime tree SHA-256 is invalid")
    if not isinstance(current_count, int) or isinstance(current_count, bool) or current_count < 1:
        raise PlanError("Inventory runtime file count is invalid")
    if version_tuple(current_version, "Installed plugin version") >= version_tuple(target_version, "Target plugin version"):
        raise PlanError("An upgrade-only plan requires current_version < target_version")

    registered_tree = rollback_release.get("tree") or {}
    if (
        registered_tree.get("hash_algorithm") != HASH_ALGORITHM
        or registered_tree.get("sha256") != current_tree
        or registered_tree.get("file_count") != current_count
    ):
        raise PlanError("Current runtime tree does not match the registered rollback release")

    backup = validate_backup_readiness(site)
    normalized = {
        "site_id": site_id,
        "site_url": site_url,
        "inventory_synced_at": format_time(synced_at),
        "inventory_age_seconds": age,
        "installed": True,
        "active": plugin["active"],
        "loaded": True,
        "current_version": current_version,
        "current_tree_sha256": current_tree,
        "current_file_count": current_count,
        "target_version": target_version,
    }
    return normalized, baseline, backup


def build_artifact(release: dict, *, package_override: Path | None, current_target: bool) -> dict:
    version = str(release.get("version") or "")
    version_tuple(version, "Registered plugin version")
    source = validate_source(release.get("source") or {}, current_target=current_target)
    registered_path = resolve_artifact_path((release.get("package") or {}).get("path"))
    package, tree, packaged_source_tree = validate_package(
        package_override or registered_path,
        release,
        version,
        allow_legacy_supplements=not current_target,
    )
    source_tree = git_tree_hash(Path(source["path"]), source["git_commit"])
    if source_tree != packaged_source_tree:
        raise PlanError("Packaged deployable source does not match the exact source commit")
    artifact = {"version": version, "source": source, "package": package, "tree": tree}
    supplements = release.get("legacy_supplements", [])
    if supplements:
        artifact["legacy_supplements"] = sorted(
            supplements, key=lambda record: str(record.get("path") or "")
        )
    return artifact


def build_plan(
    *,
    catalog_path: Path,
    releases_path: Path,
    release_lock_path: Path,
    inventory_path: Path,
    target_artifact_path: Path | None,
    rollback_artifact_path: Path | None,
    plugin_slug: str,
    target_version: str | None,
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
    catalog_entry = find_catalog_entry(catalog, plugin_slug)
    selected_target = target_version or str(catalog_entry.get("version") or "")
    if selected_target != catalog_entry.get("version"):
        raise PlanError("Selective target must equal the component catalog's current version")
    if catalog_entry.get("runtime_type") != "standard-plugin":
        raise PlanError("Selective Stack plans currently support standard plugins only")
    if catalog_entry.get("target_tier") != "platform-required":
        raise PlanError("Selective Stack plans require a platform-required component")
    if catalog_entry.get("current_distribution") != "standard-bootstrap":
        raise PlanError("Selective Stack plans require standard-bootstrap distribution")

    target_release = find_release(releases, plugin_slug, selected_target)
    validate_release_contract(target_release, catalog_entry)
    target = build_artifact(
        target_release,
        package_override=target_artifact_path,
        current_target=True,
    )
    main_file = target["package"]["main_file"]

    site_payload = inventory.get("site") if isinstance(inventory.get("site"), dict) else {}
    plugin_payload = site_payload.get("plugin") if isinstance(site_payload.get("plugin"), dict) else {}
    current_version = str(plugin_payload.get("version") or "")
    rollback_release = find_release(releases, plugin_slug, current_version)
    validate_release_contract(rollback_release, catalog_entry)
    rollback = build_artifact(
        rollback_release,
        package_override=rollback_artifact_path,
        current_target=False,
    )
    if rollback["package"]["main_file"] != main_file:
        raise PlanError("Target and rollback releases do not use the same exact main file")

    site, baseline, backup = validate_site(
        inventory,
        slug=plugin_slug,
        main_file=main_file,
        target_version=selected_target,
        rollback_release=rollback_release,
        baseline_lock=release_lock_path,
        generated_at=generated_at,
        max_age_seconds=max_inventory_age_seconds,
    )

    return {
        "schema_version": 1,
        "plan_type": "stack-plugin-update",
        "plan_id": plan_id,
        "generated_at": format_time(generated_at),
        "baseline": baseline,
        "plugin": {
            "slug": plugin_slug,
            "main_file": main_file,
            "runtime_type": "standard-plugin",
            "target_tier": "platform-required",
            "current_distribution": "standard-bootstrap",
            "target": target,
            "rollback": rollback,
        },
        "site": site,
        "operation": "upgrade-only",
        "preflight": {
            "ready": True,
            "package_sha256": target["package"]["sha256"],
            "tree_sha256": target["tree"]["sha256"],
            "backup_readiness": backup,
            "rollback_readiness": {
                "ready": True,
                "version": rollback["version"],
                "package_path": rollback["package"]["path"],
                "package_filename": rollback["package"]["filename"],
                "package_sha256": rollback["package"]["sha256"],
                "tree_sha256": rollback["tree"]["sha256"],
                "file_count": rollback["tree"]["file_count"],
            },
            "blockers": [],
        },
        "execution_contract": {
            "preflight_ability": "mrn-mainwp/preflight-stack-plugin-update-v1",
            "controller_ability": "mrn-mainwp/update-stack-plugin-v1",
            "rollback_ability": "mrn-mainwp/rollback-stack-plugin-v1",
            "minimum_controller_version": "0.9.4",
            "precondition_hash_source": "controller-preflight",
            "release_identity_model": "immutable-baseline-plus-component-overlay",
            "allow_new_install": False,
            "preserve_active_state": True,
            "requires_fresh_inventory_recheck": True,
            "requires_runtime_tree_readback": True,
            "requires_confirm": True,
            "requires_fresh_database_backup_receipt": True,
            "requires_precondition_hash": True,
        },
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--catalog", type=Path, default=DEFAULT_CATALOG)
    parser.add_argument("--release-registry", type=Path, default=DEFAULT_RELEASES)
    parser.add_argument(
        "--release-lock",
        type=Path,
        help="Explicit immutable baseline lock; otherwise select by signed site identity",
    )
    parser.add_argument("--release-lock-dir", type=Path, default=DEFAULT_LOCK_ARCHIVE)
    parser.add_argument("--inventory", type=Path, required=True)
    parser.add_argument("--target-artifact", type=Path)
    parser.add_argument("--rollback-artifact", type=Path)
    parser.add_argument("--plugin-slug", required=True)
    parser.add_argument("--target-version")
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
        release_lock_path = resolve_release_lock_path(
            args.inventory,
            explicit_lock=args.release_lock,
            archive_dir=args.release_lock_dir,
        )
        plan = build_plan(
            catalog_path=args.catalog,
            releases_path=args.release_registry,
            release_lock_path=release_lock_path,
            inventory_path=args.inventory,
            target_artifact_path=args.target_artifact,
            rollback_artifact_path=args.rollback_artifact,
            plugin_slug=args.plugin_slug,
            target_version=args.target_version,
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
                    "baseline_release_id": plan["baseline"]["release_id"],
                    "plugin_slug": plan["plugin"]["slug"],
                    "current_version": plan["site"]["current_version"],
                    "target_version": plan["site"]["target_version"],
                    "package_sha256": plan["preflight"]["package_sha256"],
                    "tree_sha256": plan["preflight"]["tree_sha256"],
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
