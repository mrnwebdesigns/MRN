#!/usr/bin/env python3
"""Generate and validate the immutable MRN stack release lock."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import subprocess
import sys
from pathlib import Path


LOCK_SCHEMA_VERSION = 1
HASH_ALGORITHM = "sha256-tree-v1"
REPORT_SCHEMA_VERSION = 1
EXCLUDED_DIRECTORIES = {
    ".git",
    ".tmp",
    "node_modules",
    "playwright-report",
    "test-results",
    "zip",
}
EXCLUDED_FILES = {".DS_Store"}


class ReleaseLockError(RuntimeError):
    """The source tree cannot produce a trustworthy release lock."""


def run_git(repo_root, *args):
    result = subprocess.run(
        ["git", "-C", str(repo_root), *args],
        capture_output=True,
        text=True,
        check=False,
    )
    if result.returncode != 0:
        detail = (result.stderr or result.stdout).strip()
        raise ReleaseLockError(detail or f"git {' '.join(args)} failed")
    return result.stdout.strip()


def repository_root(path):
    return Path(run_git(path, "rev-parse", "--show-toplevel")).resolve()


def repository_commit(path):
    return run_git(path, "rev-parse", "HEAD")


def assert_clean_external_repository(path, expected_repository):
    root = repository_root(path)
    dirty = run_git(root, "status", "--porcelain=v1")
    if dirty:
        raise ReleaseLockError(
            f"External component repository is dirty: {expected_repository} ({root})"
        )
    return root


def file_sha256(path):
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def iter_digest_files(source):
    if source.is_symlink():
        raise ReleaseLockError(f"Release sources may not be symlinks: {source}")
    if source.is_file():
        yield source.name, source
        return
    if not source.is_dir():
        raise ReleaseLockError(f"Release source does not exist: {source}")

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
                raise ReleaseLockError(f"Release sources may not contain symlinks: {path}")
            relative = path.relative_to(source).as_posix()
            yield relative, path


def tree_sha256(source):
    """Hash sorted path, content hash, and size records for deployable files."""
    digest = hashlib.sha256()
    count = 0
    for relative, path in iter_digest_files(Path(source)):
        size = path.stat().st_size
        record = f"{relative}\0{file_sha256(path)}\0{size}\n".encode("utf-8")
        digest.update(record)
        count += 1
    if count == 0:
        raise ReleaseLockError(f"Release source contains no deployable files: {source}")
    return digest.hexdigest(), count


def read_json(path):
    try:
        with Path(path).open(encoding="utf-8") as handle:
            return json.load(handle)
    except (OSError, ValueError) as error:
        raise ReleaseLockError(f"Could not read JSON from {path}: {error}") from error


def read_header_version(path):
    try:
        text = Path(path).read_text(encoding="utf-8", errors="replace")[:16384]
    except OSError as error:
        raise ReleaseLockError(f"Could not read version header from {path}: {error}") from error
    match = re.search(r"^[ \t/*#]*Version:[ \t]*([^\r\n*]+)", text, re.MULTILINE)
    return match.group(1).strip() if match else ""


def read_release_metadata(path):
    text = Path(path).read_text(encoding="utf-8")
    release = re.search(r"^- Stack release: `([^`]+)`", text, re.MULTILINE)
    release_date = re.search(r"^- Release date: `([^`]+)`", text, re.MULTILINE)
    if not release or not release_date:
        raise ReleaseLockError(f"Could not parse release metadata from {path}")
    return release.group(1), release_date.group(1)


def parse_theme_manifest(path):
    themes = []
    for raw in Path(path).read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        parts = line.split("|")
        source = parts[0]
        slug = Path(source).name
        if slug.endswith(".zip"):
            slug = slug[:-4]
        themes.append({"slug": slug, "active": "active" in parts[1:]})
    if not themes:
        raise ReleaseLockError(f"Theme manifest contains no themes: {path}")
    return themes


def resolve_component_source(repo_root, entry, standalone_plugins_root):
    source = entry.get("source") or {}
    raw_path = str(source.get("path") or "").strip()
    repository = str(source.get("repository") or "").strip()
    if not raw_path or not repository:
        raise ReleaseLockError(f"Component {entry.get('slug')} has incomplete source metadata")

    if repository == "MRN":
        path = repo_root / raw_path
        source_root = repo_root
        portable_path = raw_path
    else:
        path = Path(raw_path).expanduser()
        if not path.exists():
            path = standalone_plugins_root / str(entry.get("slug") or "")
        source_root = assert_clean_external_repository(path, repository)
        portable_path = Path(path).resolve().relative_to(source_root).as_posix() or "."
    if not path.exists():
        raise ReleaseLockError(f"Component source is missing: {entry.get('slug')} ({path})")
    return path.resolve(), source_root, portable_path


def component_entry_file(source_path, entry):
    runtime_type = entry.get("runtime_type")
    slug = entry.get("slug")
    if runtime_type == "mu-loader":
        return source_path
    candidate = source_path / f"{slug}.php"
    if not candidate.is_file():
        raise ReleaseLockError(f"Component entrypoint is missing: {candidate}")
    return candidate


def deployed_component_path(entry):
    slug = entry["slug"]
    if entry["runtime_type"] == "mu-loader":
        return f"mu-plugins/{slug}.php"
    if entry["runtime_type"] == "mu-component":
        return f"mu-plugins/{slug}"
    if entry["runtime_type"] == "standard-plugin":
        return f"plugins/{slug}"
    raise ReleaseLockError(
        f"Unsupported runtime type for platform-required component {slug}: "
        f"{entry['runtime_type']}"
    )


def build_lock(repo_root, catalog_path, stack_version_path, theme_manifest_path, standalone_root):
    catalog = read_json(catalog_path)
    components = catalog.get("components")
    if not isinstance(components, list):
        raise ReleaseLockError("Component catalog has no components array")

    required = [entry for entry in components if entry.get("target_tier") == "platform-required"]
    slugs = [str(entry.get("slug") or "").strip() for entry in required]
    if not required or any(not slug for slug in slugs):
        raise ReleaseLockError("Platform-required catalog entries must have slugs")
    if len(slugs) != len(set(slugs)):
        raise ReleaseLockError("Component catalog contains duplicate platform-required slugs")

    release_id, release_date = read_release_metadata(stack_version_path)
    mrn_commit = repository_commit(repo_root)
    locked_components = []
    loader_version = None

    for entry in sorted(required, key=lambda item: item["slug"]):
        source_path, source_root, portable_path = resolve_component_source(
            repo_root, entry, standalone_root
        )
        entry_file = component_entry_file(source_path, entry)
        actual_version = read_header_version(entry_file)
        expected_version = str(entry.get("version") or "").strip()
        if not expected_version or actual_version != expected_version:
            raise ReleaseLockError(
                f"Version drift for {entry['slug']}: catalog={expected_version or 'missing'} "
                f"source={actual_version or 'missing'}"
            )
        digest, file_count = tree_sha256(source_path)
        source_repository = str((entry.get("source") or {}).get("repository"))
        source_commit = mrn_commit if source_repository == "MRN" else repository_commit(source_root)
        locked_components.append(
            {
                "slug": entry["slug"],
                "name": entry.get("name") or entry["slug"],
                "version": expected_version,
                "runtime_type": entry["runtime_type"],
                "required": True,
                "deployed_path": deployed_component_path(entry),
                "source": {
                    "repository": source_repository,
                    "git_commit": source_commit,
                    "path": portable_path,
                },
                "sha256": digest,
                "file_count": file_count,
            }
        )
        if entry["slug"] == "mrn-loader":
            loader_version = expected_version

    if not loader_version:
        raise ReleaseLockError("Platform-required catalog does not contain mrn-loader")

    locked_themes = []
    for theme in sorted(parse_theme_manifest(theme_manifest_path), key=lambda item: item["slug"]):
        source_path = repo_root / "stack" / "themes" / theme["slug"]
        version = read_header_version(source_path / "style.css")
        if not version:
            raise ReleaseLockError(f"Theme has no Version header: {theme['slug']}")
        digest, file_count = tree_sha256(source_path)
        locked_themes.append(
            {
                "slug": theme["slug"],
                "version": version,
                "active": theme["active"],
                "verification_mode": "site-derived" if theme["active"] else "exact",
                "deployed_path": f"themes/{theme['slug']}",
                "source": {
                    "repository": "MRN",
                    "git_commit": mrn_commit,
                    "path": f"stack/themes/{theme['slug']}",
                },
                "sha256": digest,
                "file_count": file_count,
            }
        )

    return {
        "schema_version": LOCK_SCHEMA_VERSION,
        "release_id": release_id,
        "released_at": f"{release_date}T00:00:00Z",
        "source": {"repository": "MRN", "git_commit": mrn_commit},
        "stack_version": release_id,
        "hash_algorithm": HASH_ALGORITHM,
        "compatibility": {
            "minimum_loader_version": loader_version,
            "runtime_report_schema_version": REPORT_SCHEMA_VERSION,
        },
        "components": locked_components,
        "themes": locked_themes,
    }


def validate_lock(payload):
    required_keys = {
        "schema_version",
        "release_id",
        "released_at",
        "source",
        "stack_version",
        "hash_algorithm",
        "compatibility",
        "components",
        "themes",
    }
    missing = sorted(required_keys - set(payload))
    if missing:
        raise ReleaseLockError("Release lock is missing keys: " + ", ".join(missing))
    if payload["schema_version"] != LOCK_SCHEMA_VERSION:
        raise ReleaseLockError("Unsupported release lock schema version")
    if payload["hash_algorithm"] != HASH_ALGORITHM:
        raise ReleaseLockError("Unsupported release lock hash algorithm")
    if not payload["components"] or not payload["themes"]:
        raise ReleaseLockError("Release lock must contain components and themes")
    slugs = [entry.get("slug") for entry in payload["components"]]
    if len(slugs) != len(set(slugs)):
        raise ReleaseLockError("Release lock contains duplicate component slugs")
    return payload


def main(argv=None):
    script_path = Path(__file__).resolve()
    default_root = script_path.parents[2]
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repo-root", type=Path, default=default_root)
    parser.add_argument("--catalog", type=Path)
    parser.add_argument("--stack-version", type=Path)
    parser.add_argument("--theme-manifest", type=Path)
    parser.add_argument(
        "--standalone-plugins-root",
        type=Path,
        default=Path(os.environ.get("MRN_STANDALONE_PLUGINS_ROOT", "~/Development/MRN-plugins")).expanduser(),
    )
    parser.add_argument("--output", type=Path)
    parser.add_argument("--check", type=Path, help="validate an existing lock instead of generating")
    args = parser.parse_args(argv)

    try:
        if args.check:
            payload = validate_lock(read_json(args.check))
        else:
            repo_root = args.repo_root.resolve()
            payload = build_lock(
                repo_root,
                args.catalog or repo_root / "stack/manifests/component-catalog.json",
                args.stack_version or repo_root / "stack/STACK_VERSION.md",
                args.theme_manifest or repo_root / "stack/manifests/themes.txt",
                args.standalone_plugins_root.resolve(),
            )
            validate_lock(payload)
    except ReleaseLockError as error:
        sys.stderr.write(f"error: {error}\n")
        return 2

    serialized = json.dumps(payload, indent=2, sort_keys=True) + "\n"
    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(serialized, encoding="utf-8")
        sys.stderr.write(f"Wrote {args.output}\n")
    else:
        sys.stdout.write(serialized)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
