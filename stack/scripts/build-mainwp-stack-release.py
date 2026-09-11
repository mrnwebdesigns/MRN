#!/usr/bin/env python3
"""Build a deterministic MainWP package for one canonical MRN Stack release."""

from __future__ import annotations

import argparse
import hashlib
import importlib.util
import json
import re
import sys
import zipfile
from pathlib import Path


SCRIPT_DIR = Path(__file__).resolve().parent
LOCK_SCRIPT = SCRIPT_DIR / "generate-stack-release-lock.py"
LOCK_SPEC = importlib.util.spec_from_file_location(
    "generate_stack_release_lock", LOCK_SCRIPT
)
release_lock = importlib.util.module_from_spec(LOCK_SPEC)
LOCK_SPEC.loader.exec_module(release_lock)

PLAN_SCHEMA = 2
IDENTIFIER_PATTERN = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._-]{2,127}$")
SLUG_PATTERN = re.compile(r"^mrn-[a-z0-9-]+$")
MU_FILE_PATTERN = re.compile(r"^mu-plugins/mrn-[a-z0-9-]+\.php$")
MU_DIRECTORY_PATTERN = re.compile(r"^mu-plugins/mrn-[a-z0-9-]+$")
PLUGIN_DIRECTORY_PATTERN = re.compile(r"^plugins/mrn-[a-z0-9-]+$")
LOCK_TARGET = "mu-plugins/mrn-stack-release.lock.json"
AGENT_SLUG = "mrn-stack-deployment-agent"
PARENT_THEME_SLUG = "mrn-base-stack"
CHILD_THEME_SLUG = "mrn-base-stack-child"
MAX_COMPONENTS = 40
MAX_ARCHIVE_ENTRIES = 5000
MAX_EXPANDED_BYTES = 100 * 1024 * 1024
MAX_PACKAGE_BYTES = 50 * 1024 * 1024
MINIMUM_STACK_AGENT_VERSION = (0, 2, 0)


class BuildError(RuntimeError):
    """The selected immutable release cannot produce a safe fleet package."""


def sha256_bytes(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()


def file_sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def version_tuple(value: object) -> tuple[int, int, int]:
    match = re.fullmatch(r"(\d+)\.(\d+)\.(\d+)", str(value or ""))
    if not match:
        raise BuildError(f"Invalid deployment-agent version: {value}")
    return tuple(int(part) for part in match.groups())


def deterministic_zip(path: Path, entries: dict[str, bytes]) -> None:
    with zipfile.ZipFile(path, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for name in sorted(entries):
            info = zipfile.ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            archive.writestr(info, entries[name])


def validate_target(entry: dict) -> tuple[str, str]:
    slug = str(entry.get("slug") or "")
    runtime_type = str(entry.get("runtime_type") or "")
    target = str(entry.get("deployed_path") or "").strip("/")
    if not SLUG_PATTERN.fullmatch(slug):
        raise BuildError(f"Invalid locked component slug: {slug}")
    if runtime_type == "mu-loader" and MU_FILE_PATTERN.fullmatch(target):
        return target, "file"
    if runtime_type == "mu-component" and MU_DIRECTORY_PATTERN.fullmatch(target):
        return target, "directory"
    if (
        runtime_type == "standard-plugin"
        and PLUGIN_DIRECTORY_PATTERN.fullmatch(target)
        and slug != AGENT_SLUG
    ):
        return target, "directory"
    if runtime_type == "shared-runtime" and target == "shared":
        return target, "directory"
    if runtime_type == "parent-theme" and target == f"themes/{PARENT_THEME_SLUG}":
        return target, "directory"
    raise BuildError(f"Locked component is outside the platform allowlist: {slug}")


def component_payload(
    artifact_root: Path, entry: dict, *, runtime_type: str | None = None
) -> tuple[dict, dict[str, bytes]]:
    target, component_type = validate_target(
        {**entry, **({"runtime_type": runtime_type} if runtime_type else {})}
    )
    source = artifact_root / target
    actual_hash, actual_count = release_lock.tree_sha256(source)
    if actual_hash != entry.get("sha256") or actual_count != entry.get("file_count"):
        raise BuildError(f"Assembled artifact does not match the release lock: {entry['slug']}")

    files = []
    payload = {}
    for relative, path in release_lock.iter_digest_files(source):
        archive_path = (
            f"payload/{target}"
            if component_type == "file"
            else f"payload/{target}/{relative}"
        )
        data = path.read_bytes()
        files.append({"source": archive_path, "sha256": sha256_bytes(data)})
        payload[archive_path] = data

    legacy_paths = []
    if entry.get("runtime_type") == "mu-component":
        wrapper = artifact_root / "mu-plugins" / f"{entry['slug']}.php"
        if wrapper.is_file():
            legacy_paths.append(f"mu-plugins/{entry['slug']}.php")

    return (
        {
            "slug": entry["slug"],
            "runtime_type": runtime_type or entry["runtime_type"],
            "type": component_type,
            "target": target,
            "files": files,
            "legacy_paths": legacy_paths,
        },
        payload,
    )


def theme_component(artifact_root: Path, theme: dict) -> tuple[dict, dict[str, bytes]]:
    if (
        theme.get("slug") != PARENT_THEME_SLUG
        or theme.get("verification_mode") != "exact"
        or theme.get("deployment_role") != "parent-template"
        or theme.get("deployed_path") != f"themes/{PARENT_THEME_SLUG}"
    ):
        raise BuildError("The release lock does not contain the canonical exact parent theme")
    component, payload = component_payload(
        artifact_root, theme, runtime_type="parent-theme"
    )
    return component, payload


def build_release(
    lock_path: Path,
    artifact_root: Path,
    output_dir: Path,
    rollout_id: str,
) -> dict:
    if not IDENTIFIER_PATTERN.fullmatch(rollout_id):
        raise BuildError("The rollout ID is invalid")
    lock_path = Path(lock_path).resolve()
    artifact_root = Path(artifact_root).resolve()
    lock_bytes = lock_path.read_bytes()
    lock = release_lock.validate_lock(json.loads(lock_bytes))
    lock_hash = sha256_bytes(lock_bytes)

    components = []
    payload = {}
    prerequisites = []
    for entry in sorted(lock["components"], key=lambda item: str(item.get("slug") or "")):
        if entry.get("required") is not True:
            raise BuildError(
                f"Full Stack packages cannot contain optional components: {entry.get('slug')}"
            )
        if entry.get("slug") == AGENT_SLUG:
            if version_tuple(entry.get("version")) < MINIMUM_STACK_AGENT_VERSION:
                raise BuildError(
                    "The release lock deployment agent does not support schema 2"
                )
            source = artifact_root / str(entry.get("deployed_path") or "")
            actual_hash, actual_count = release_lock.tree_sha256(source)
            if actual_hash != entry.get("sha256") or actual_count != entry.get("file_count"):
                raise BuildError("The assembled deployment agent does not match the release lock")
            prerequisites.append(
                {
                    "slug": AGENT_SLUG,
                    "version": entry["version"],
                    "sha256": entry["sha256"],
                    "file_count": entry["file_count"],
                }
            )
            continue
        component, files = component_payload(artifact_root, entry)
        components.append(component)
        for name, data in files.items():
            if name in payload:
                raise BuildError(f"Duplicate package path: {name}")
            payload[name] = data

    exact_parents = [
        theme
        for theme in lock["themes"]
        if theme.get("verification_mode") == "exact"
        and theme.get("deployment_role") == "parent-template"
    ]
    child_templates = [
        theme
        for theme in lock["themes"]
        if theme.get("verification_mode") == "site-derived"
        and theme.get("deployment_role") == "active-stylesheet-template"
    ]
    if len(exact_parents) != 1 or len(child_templates) != 1:
        raise BuildError("The release lock must contain one parent and one site-derived child theme")
    if child_templates[0].get("slug") != CHILD_THEME_SLUG:
        raise BuildError("The release lock child-theme contract is not canonical")
    parent_component, parent_payload = theme_component(artifact_root, exact_parents[0])
    components.append(parent_component)
    for name, data in parent_payload.items():
        if name in payload:
            raise BuildError(f"Duplicate package path: {name}")
        payload[name] = data

    lock_source = f"payload/{LOCK_TARGET}"
    components.append(
        {
            "slug": "mrn-stack-release-lock",
            "runtime_type": "release-lock",
            "type": "file",
            "target": LOCK_TARGET,
            "files": [{"source": lock_source, "sha256": lock_hash}],
            "legacy_paths": [],
        }
    )
    payload[lock_source] = lock_bytes

    if len(prerequisites) != 1:
        raise BuildError("The release lock must contain the deployment agent prerequisite")
    if not components or len(components) > MAX_COMPONENTS:
        raise BuildError("The platform component count is outside the deployment contract")

    order = {
        "standard-plugin": 10,
        "shared-runtime": 20,
        "parent-theme": 30,
        "mu-component": 40,
        "mu-loader": 50,
        "release-lock": 60,
    }
    components.sort(key=lambda item: (order[item["runtime_type"]], item["slug"]))
    plan = {
        "schema_version": PLAN_SCHEMA,
        "rollout_id": rollout_id,
        "release_id": lock["release_id"],
        "lock_sha256": lock_hash,
        "site_contract": {
            "template": PARENT_THEME_SLUG,
            "stylesheet": CHILD_THEME_SLUG,
            "preserve_stylesheet": True,
        },
        "prerequisites": prerequisites,
        "components": components,
        "protected_paths": [],
    }
    plan_bytes = (
        json.dumps(plan, sort_keys=True, separators=(",", ":"), ensure_ascii=True)
        + "\n"
    ).encode("utf-8")
    plan_hash = sha256_bytes(plan_bytes)
    entries = {"plan.json": plan_bytes, **payload}
    expanded_bytes = sum(len(value) for value in entries.values())
    if len(entries) > MAX_ARCHIVE_ENTRIES or expanded_bytes > MAX_EXPANDED_BYTES:
        raise BuildError("The platform release exceeds the archive safety limits")

    output_dir.mkdir(parents=True, exist_ok=True)
    plan_path = output_dir / "plan.json"
    package_path = output_dir / f"mrn-stack-release-{rollout_id}.zip"
    checksums_path = output_dir / "checksums.json"
    for path in (plan_path, package_path, checksums_path):
        if path.exists():
            raise BuildError(f"Refusing to overwrite existing release artifact: {path}")
    plan_path.write_bytes(plan_bytes)
    deterministic_zip(package_path, entries)
    if package_path.stat().st_size > MAX_PACKAGE_BYTES:
        package_path.unlink()
        plan_path.unlink()
        raise BuildError("The platform release package exceeds the 50 MB limit")

    receipt = {
        "schema_version": PLAN_SCHEMA,
        "release_id": lock["release_id"],
        "rollout_id": rollout_id,
        "lock_sha256": lock_hash,
        "plan_sha256": plan_hash,
        "package_sha256": file_sha256(package_path),
        "package_filename": package_path.name,
        "component_count": len(components),
        "prerequisite_count": len(prerequisites),
        "archive_file_count": len(entries),
        "expanded_bytes": expanded_bytes,
        "package_bytes": package_path.stat().st_size,
    }
    checksums_path.write_text(
        json.dumps(receipt, indent=2, sort_keys=True) + "\n", encoding="utf-8"
    )
    return receipt


def main(argv=None) -> int:
    default_root = Path(__file__).resolve().parents[2]
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--release-lock",
        type=Path,
        default=default_root / "stack/manifests/stack-release.lock.json",
    )
    parser.add_argument("--artifact-root", type=Path, required=True)
    parser.add_argument("--output-dir", type=Path, required=True)
    parser.add_argument("--rollout-id", required=True)
    args = parser.parse_args(argv)
    try:
        receipt = build_release(
            args.release_lock.expanduser().resolve(),
            args.artifact_root.expanduser().resolve(),
            args.output_dir.expanduser().resolve(),
            args.rollout_id,
        )
    except (BuildError, release_lock.ReleaseLockError, OSError, ValueError) as error:
        print(f"ERROR: {error}", file=sys.stderr)
        return 1
    print(json.dumps(receipt, indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
