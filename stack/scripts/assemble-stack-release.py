#!/usr/bin/env python3
"""Assemble an immutable MRN platform release from its release lock."""

from __future__ import annotations

import argparse
import importlib.util
import json
import os
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path


SCRIPT_DIR = Path(__file__).resolve().parent
LOCK_SCRIPT = SCRIPT_DIR / "generate-stack-release-lock.py"
LOCK_SPEC = importlib.util.spec_from_file_location(
    "generate_stack_release_lock", LOCK_SCRIPT
)
release_lock = importlib.util.module_from_spec(LOCK_SPEC)
LOCK_SPEC.loader.exec_module(release_lock)


class AssemblyError(RuntimeError):
    """The selected release cannot be assembled exactly."""


def run_git(repo, *args, check=True):
    result = subprocess.run(
        ["git", "-C", str(repo), *args],
        capture_output=True,
        text=True,
        check=False,
    )
    if check and result.returncode != 0:
        detail = (result.stderr or result.stdout).strip()
        raise AssemblyError(detail or f"git {' '.join(args)} failed")
    return result


def assert_source_provenance(repo, expected_commit, source_path, *, exact_head):
    repo = Path(repo).resolve()
    expected_commit = str(expected_commit or "")
    if len(expected_commit) != 40:
        raise AssemblyError(f"Invalid source commit for {source_path}")
    run_git(repo, "cat-file", "-e", f"{expected_commit}^{{commit}}")

    head = run_git(repo, "rev-parse", "HEAD").stdout.strip()
    if exact_head and head != expected_commit:
        raise AssemblyError(
            f"External source HEAD mismatch for {source_path}: "
            f"expected {expected_commit}, found {head}"
        )
    if not exact_head:
        changed = run_git(
            repo,
            "diff",
            "--quiet",
            expected_commit,
            "HEAD",
            "--",
            str(source_path),
            check=False,
        )
        if changed.returncode not in (0, 1):
            raise AssemblyError(f"Could not compare release source path: {source_path}")
        if changed.returncode == 1:
            raise AssemblyError(
                f"MRN source path changed after locked commit: {source_path}"
            )

    dirty = run_git(
        repo,
        "status",
        "--porcelain=v1",
        "--untracked-files=all",
        "--",
        str(source_path),
    ).stdout.strip()
    if dirty:
        raise AssemblyError(f"Release source path is dirty: {source_path}")


def resolve_source(repo_root, standalone_root, entry):
    source = entry.get("source") or {}
    repository = str(source.get("repository") or "").strip()
    raw_path = str(source.get("path") or "").strip()
    expected_commit = str(source.get("git_commit") or "").strip()
    if not repository or not raw_path:
        raise AssemblyError(f"Incomplete source metadata for {entry.get('slug')}")

    if repository == "MRN":
        source_repo = Path(repo_root).resolve()
        source_path = source_repo / raw_path
        assert_source_provenance(
            source_repo, expected_commit, raw_path, exact_head=False
        )
    else:
        source_repo = Path(standalone_root).resolve() / str(entry.get("slug") or "")
        source_path = source_repo / raw_path
        assert_source_provenance(
            source_repo, expected_commit, raw_path, exact_head=True
        )
    if not source_path.exists():
        raise AssemblyError(f"Release source is missing: {source_path}")
    return source_path.resolve()


def copy_deployable_tree(source, destination):
    source = Path(source)
    destination = Path(destination)
    if source.is_file():
        destination.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source, destination)
        return

    destination.mkdir(parents=True, exist_ok=True)
    for relative, path in release_lock.iter_digest_files(source):
        target = destination / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(path, target)


def assemble(lock_path, repo_root, standalone_root, output, *, force=False):
    payload = release_lock.validate_lock(release_lock.read_json(lock_path))
    output = Path(output).expanduser().resolve()
    if output.exists() and not force:
        raise AssemblyError(f"Output already exists: {output}")
    output.parent.mkdir(parents=True, exist_ok=True)

    entries = list(payload["components"]) + list(payload["themes"])
    seen_paths = set()
    with tempfile.TemporaryDirectory(
        prefix=f".{output.name}.assembling-", dir=output.parent
    ) as temporary:
        staging = Path(temporary) / "release"
        staging.mkdir()
        results = []
        for entry in entries:
            deployed_path = str(entry.get("deployed_path") or "")
            portable = Path(deployed_path)
            if portable.is_absolute() or ".." in portable.parts or deployed_path in seen_paths:
                raise AssemblyError(
                    f"Unsafe or duplicate deployed path for {entry.get('slug')}"
                )
            seen_paths.add(deployed_path)
            source = resolve_source(repo_root, standalone_root, entry)
            source_hash, source_count = release_lock.tree_sha256(source)
            if (
                source_hash != entry.get("sha256")
                or source_count != entry.get("file_count")
            ):
                raise AssemblyError(
                    f"Locked source checksum mismatch for {entry.get('slug')}"
                )

            destination = staging / portable
            copy_deployable_tree(source, destination)
            assembled_hash, assembled_count = release_lock.tree_sha256(destination)
            if assembled_hash != source_hash or assembled_count != source_count:
                raise AssemblyError(
                    f"Assembled checksum mismatch for {entry.get('slug')}"
                )
            results.append(
                {
                    "slug": entry["slug"],
                    "deployed_path": deployed_path,
                    "sha256": assembled_hash,
                    "file_count": assembled_count,
                }
            )

        lock_destination = staging / "mu-plugins" / "mrn-stack-release.lock.json"
        lock_destination.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(lock_path, lock_destination)
        metadata = {
            "schema_version": 1,
            "release_id": payload["release_id"],
            "release_lock_sha256": release_lock.file_sha256(Path(lock_path)),
            "artifacts": results,
        }
        (staging / "mrn-release-metadata.json").write_text(
            json.dumps(metadata, indent=2) + "\n", encoding="utf-8"
        )

        if output.exists():
            if output.is_symlink() or not output.is_dir():
                raise AssemblyError(f"Refusing to replace unsafe output: {output}")
            shutil.rmtree(output)
        os.replace(staging, output)
    return metadata


def main(argv=None):
    default_root = Path(__file__).resolve().parents[2]
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--release-lock",
        default=default_root / "stack/manifests/stack-release.lock.json",
        type=Path,
    )
    parser.add_argument("--repo-root", default=default_root, type=Path)
    parser.add_argument(
        "--standalone-plugins-root",
        default=Path(
            os.environ.get(
                "MRN_STANDALONE_PLUGINS_ROOT",
                str(default_root.parent / "MRN-plugins"),
            )
        ),
        type=Path,
    )
    parser.add_argument("--output", required=True, type=Path)
    parser.add_argument("--force", action="store_true")
    args = parser.parse_args(argv)

    try:
        metadata = assemble(
            args.release_lock,
            args.repo_root,
            args.standalone_plugins_root,
            args.output,
            force=args.force,
        )
    except (AssemblyError, release_lock.ReleaseLockError) as error:
        print(f"ERROR: {error}", file=sys.stderr)
        return 1
    print(json.dumps(metadata, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
