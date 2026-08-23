#!/usr/bin/env python3
"""Build a deterministic, lock-derived MainWP MU release package."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import sys
import zipfile
from pathlib import Path


EXCLUDED_DIRECTORIES = {
    ".git",
    ".tmp",
    "node_modules",
    "playwright-report",
    "test-results",
    "zip",
}
EXCLUDED_FILES = {".DS_Store"}
MU_RUNTIME_TYPES = {"mu-loader", "mu-component"}
IDENTIFIER_PATTERN = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._-]{2,127}$")
MU_PATH_PATTERN = re.compile(
    r"^mu-plugins/mrn-[a-z0-9-]+(?:\.php|(?:/[A-Za-z0-9._-]+)*)$"
)
LOCK_TARGET = "mu-plugins/mrn-stack-release.lock.json"
MAX_COMPONENTS = 40
MAX_ARCHIVE_ENTRIES = 5000
MAX_EXPANDED_BYTES = 100 * 1024 * 1024
MAX_PACKAGE_BYTES = 50 * 1024 * 1024


class BuildError(RuntimeError):
    """The requested release package cannot be built safely."""


def sha256_bytes(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()


def file_sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def iter_deployable_files(source: Path):
    if source.is_symlink():
        raise BuildError(f"Release sources may not be symlinks: {source}")
    if source.is_file():
        yield source.name, source
        return
    if not source.is_dir():
        raise BuildError(f"Release source does not exist: {source}")

    for root, directories, files in os.walk(source, followlinks=False):
        directories[:] = sorted(
            directory
            for directory in directories
            if directory not in EXCLUDED_DIRECTORIES
        )
        root_path = Path(root)
        for filename in sorted(files):
            if filename in EXCLUDED_FILES:
                continue
            path = root_path / filename
            if path.is_symlink():
                raise BuildError(f"Release sources may not contain symlinks: {path}")
            yield path.relative_to(source).as_posix(), path


def tree_sha256(source: Path):
    digest = hashlib.sha256()
    count = 0
    for relative, path in iter_deployable_files(source):
        size = path.stat().st_size
        digest.update(f"{relative}\0{file_sha256(path)}\0{size}\n".encode("utf-8"))
        count += 1
    if not count:
        raise BuildError(f"Release source contains no deployable files: {source}")
    return digest.hexdigest(), count


def read_lock(path: Path):
    try:
        lock_bytes = path.read_bytes()
        lock = json.loads(lock_bytes)
    except (OSError, ValueError) as error:
        raise BuildError(f"Could not read release lock {path}: {error}") from error
    if (
        not isinstance(lock, dict)
        or lock.get("schema_version") != 1
        or lock.get("hash_algorithm") != "sha256-tree-v1"
        or not IDENTIFIER_PATTERN.fullmatch(str(lock.get("release_id", "")))
        or not isinstance(lock.get("components"), list)
    ):
        raise BuildError("The release lock identity or schema is invalid.")
    return lock, lock_bytes


def valid_mu_path(path: str) -> bool:
    return path == LOCK_TARGET or bool(MU_PATH_PATTERN.fullmatch(path))


def build_component(repo_root: Path, locked: dict):
    runtime_type = str(locked.get("runtime_type", ""))
    slug = str(locked.get("slug", ""))
    source_record = locked.get("source")
    if runtime_type not in MU_RUNTIME_TYPES:
        return None
    if (
        not re.fullmatch(r"mrn-[a-z0-9-]+", slug)
        or not isinstance(source_record, dict)
        or source_record.get("repository") != "MRN"
    ):
        raise BuildError(f"Locked MU component has an invalid source contract: {slug}")

    source = (repo_root / str(source_record.get("path", ""))).resolve()
    try:
        source.relative_to(repo_root)
    except ValueError as error:
        raise BuildError(f"MU source escapes the repository: {slug}") from error

    actual_hash, actual_count = tree_sha256(source)
    if actual_hash != locked.get("sha256") or actual_count != locked.get("file_count"):
        raise BuildError(f"Source tree does not match the release lock: {slug}")

    target = str(locked.get("deployed_path", ""))
    component_type = "file" if runtime_type == "mu-loader" else "directory"
    if not valid_mu_path(target):
        raise BuildError(f"Locked MU target is outside the allowlist: {target}")

    files = []
    payload = {}
    for relative, path in iter_deployable_files(source):
        archive_path = f"payload/{target}"
        if component_type == "directory":
            archive_path = f"{archive_path}/{relative}"
        data = path.read_bytes()
        files.append({"source": archive_path, "sha256": sha256_bytes(data)})
        payload[archive_path] = data

    legacy_paths = []
    wrapper = repo_root / "stack" / "mu-plugins" / f"{slug}.php"
    if component_type == "directory" and wrapper.is_file():
        legacy_paths.append(f"mu-plugins/{slug}.php")

    return {
        "plan": {
            "slug": slug,
            "type": component_type,
            "target": target,
            "files": files,
            "legacy_paths": legacy_paths,
        },
        "payload": payload,
    }


def deterministic_zip(path: Path, entries: dict[str, bytes]):
    with zipfile.ZipFile(path, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for name in sorted(entries):
            info = zipfile.ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            archive.writestr(info, entries[name])


def build_release(repo_root: Path, lock_path: Path, output_dir: Path, rollout_id: str):
    if not IDENTIFIER_PATTERN.fullmatch(rollout_id):
        raise BuildError("The rollout ID is invalid.")
    lock, lock_bytes = read_lock(lock_path)
    lock_hash = sha256_bytes(lock_bytes)

    components = []
    payload = {}
    for locked in sorted(lock["components"], key=lambda item: str(item.get("slug", ""))):
        built = build_component(repo_root, locked)
        if built is None:
            continue
        components.append(built["plan"])
        for name, data in built["payload"].items():
            if name in payload:
                raise BuildError(f"Duplicate payload path: {name}")
            payload[name] = data

    lock_source = f"payload/{LOCK_TARGET}"
    components.append(
        {
            "slug": "mrn-stack-release-lock",
            "type": "file",
            "target": LOCK_TARGET,
            "files": [{"source": lock_source, "sha256": lock_hash}],
            "legacy_paths": [],
        }
    )
    payload[lock_source] = lock_bytes
    components.sort(key=lambda item: item["slug"])
    if not components or len(components) > MAX_COMPONENTS:
        raise BuildError("The MU component count is outside the deployment contract.")

    plan = {
        "schema_version": 1,
        "rollout_id": rollout_id,
        "release_id": lock["release_id"],
        "lock_sha256": lock_hash,
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
        raise BuildError("The MU release exceeds the archive entry or expanded-size limit.")

    output_dir.mkdir(parents=True, exist_ok=True)
    plan_path = output_dir / "plan.json"
    package_path = output_dir / f"mrn-mu-release-{rollout_id}.zip"
    receipt_path = output_dir / "checksums.json"
    plan_path.write_bytes(plan_bytes)
    deterministic_zip(package_path, entries)
    if package_path.stat().st_size > MAX_PACKAGE_BYTES:
        package_path.unlink()
        raise BuildError("The MU release package exceeds the 50 MB limit.")

    receipt = {
        "schema_version": 1,
        "release_id": lock["release_id"],
        "rollout_id": rollout_id,
        "lock_sha256": lock_hash,
        "plan_sha256": plan_hash,
        "package_sha256": file_sha256(package_path),
        "package_filename": package_path.name,
        "component_count": len(components),
        "archive_file_count": len(entries),
        "expanded_bytes": expanded_bytes,
        "package_bytes": package_path.stat().st_size,
    }
    receipt_path.write_text(
        json.dumps(receipt, indent=2, sort_keys=True) + "\n", encoding="utf-8"
    )
    return receipt


def parse_args(argv=None):
    default_root = Path(__file__).resolve().parents[2]
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repo-root", type=Path, default=default_root)
    parser.add_argument(
        "--lock",
        type=Path,
        default=Path("stack/manifests/stack-release.lock.json"),
    )
    parser.add_argument("--output-dir", type=Path, required=True)
    parser.add_argument("--rollout-id", required=True)
    return parser.parse_args(argv)


def main(argv=None):
    args = parse_args(argv)
    repo_root = args.repo_root.expanduser().resolve()
    lock_path = args.lock.expanduser()
    if not lock_path.is_absolute():
        lock_path = repo_root / lock_path
    try:
        receipt = build_release(
            repo_root,
            lock_path.resolve(),
            args.output_dir.expanduser().resolve(),
            args.rollout_id,
        )
    except BuildError as error:
        print(f"ERROR: {error}", file=sys.stderr)
        return 1
    print(json.dumps(receipt, indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
