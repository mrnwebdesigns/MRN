#!/usr/bin/env python3
"""Audit merged stack drift or validate a proposed immutable stack promotion."""

from __future__ import annotations

import argparse
import datetime as dt
import importlib.util
import json
import os
import subprocess
import sys
from pathlib import Path


SCRIPT_DIR = Path(__file__).resolve().parent
LOCK_PATH = SCRIPT_DIR.parent / "manifests" / "stack-release.lock.json"
CATALOG_PATH = SCRIPT_DIR.parent / "manifests" / "component-catalog.json"
LOCK_SCRIPT = SCRIPT_DIR / "generate-stack-release-lock.py"
LOCK_SPEC = importlib.util.spec_from_file_location(
    "generate_stack_release_lock", LOCK_SCRIPT
)
release_lock = importlib.util.module_from_spec(LOCK_SPEC)
LOCK_SPEC.loader.exec_module(release_lock)

REPORT_SCHEMA_VERSION = 1
DEPLOYABLE_ROOTS = (
    "plugins/",
    "mu-plugins/",
    "shared/",
    "stack/mu-plugins/",
    "stack/themes/",
)
DEPLOYMENT_CONTRACT_PREFIXES = (
    "stack/configs/",
    "stack/scripts/",
    "stack/server-config/",
)
RELEASE_METADATA_PATHS = {
    "stack/CHANGELOG.md",
    "stack/STACK_VERSION.md",
    "stack/manifests/component-catalog.json",
    "stack/manifests/plugins.txt",
    "stack/manifests/stack-release.lock.json",
    "stack/manifests/stack-release.lock.schema.json",
    "stack/manifests/themes.txt",
}


class PromotionError(RuntimeError):
    """Promotion evidence cannot be evaluated safely."""


def run_git(repo_root, *arguments, check=True):
    result = subprocess.run(
        ["git", "-C", str(repo_root), *arguments],
        capture_output=True,
        text=True,
        check=False,
    )
    if check and result.returncode != 0:
        detail = (result.stderr or result.stdout).strip()
        raise PromotionError(detail or f"git {' '.join(arguments)} failed")
    return result


def git_output(repo_root, *arguments):
    return run_git(repo_root, *arguments).stdout.strip()


def utc_now():
    return (
        dt.datetime.now(dt.timezone.utc)
        .replace(microsecond=0)
        .isoformat()
        .replace("+00:00", "Z")
    )


def read_json(path):
    try:
        payload = json.loads(Path(path).read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        raise PromotionError(f"Could not read JSON from {path}: {error}") from error
    if not isinstance(payload, dict):
        raise PromotionError(f"JSON root must be an object: {path}")
    return payload


def read_json_at_ref(repo_root, reference, path):
    result = run_git(repo_root, "show", f"{reference}:{path}")
    try:
        payload = json.loads(result.stdout)
    except json.JSONDecodeError as error:
        raise PromotionError(
            f"Could not parse {path} at {reference}: {error}"
        ) from error
    if not isinstance(payload, dict):
        raise PromotionError(f"JSON root must be an object: {reference}:{path}")
    return payload


def discover_baseline_ref(repo_root, lock_path, candidate):
    relative = Path(lock_path).resolve().relative_to(repo_root).as_posix()
    commits = git_output(repo_root, "log", "--format=%H", "--", relative).splitlines()
    for commit in commits:
        payload = read_json_at_ref(repo_root, commit, relative)
        if (
            payload.get("release_id") != candidate.get("release_id")
            or (payload.get("source") or {}).get("git_commit")
            != (candidate.get("source") or {}).get("git_commit")
        ):
            return commit
    raise PromotionError("Could not discover a previous immutable release lock")


def lock_entries(payload):
    entries = {}
    for kind in ("components", "themes"):
        for raw in payload.get(kind) or []:
            entry = dict(raw)
            entry["entry_kind"] = kind[:-1]
            entries[str(entry.get("slug") or "")] = entry
    return entries


def path_matches(path, source_path):
    normalized = str(source_path or "").strip().strip("/")
    return bool(normalized) and (path == normalized or path.startswith(normalized + "/"))


def catalog_sources(catalog):
    sources = []
    for entry in catalog.get("components") or []:
        source = entry.get("source") or {}
        if source.get("repository") != "MRN" or not source.get("path"):
            continue
        sources.append(
            {
                "slug": entry.get("slug"),
                "target_tier": entry.get("target_tier"),
                "runtime_type": entry.get("runtime_type"),
                "path": str(source["path"]).strip("/"),
            }
        )
    return sorted(sources, key=lambda item: len(item["path"]), reverse=True)


def theme_sources(lock_payload):
    sources = []
    for entry in lock_payload.get("themes") or []:
        source = entry.get("source") or {}
        if source.get("repository") == "MRN" and source.get("path"):
            sources.append(
                {
                    "slug": entry.get("slug"),
                    "target_tier": "platform-required",
                    "runtime_type": "theme",
                    "path": str(source["path"]).strip("/"),
                }
            )
    return sorted(sources, key=lambda item: len(item["path"]), reverse=True)


def changed_files(repo_root, start_commit, end_commit):
    result = run_git(
        repo_root,
        "diff",
        "--name-only",
        "--diff-filter=ACDMRTUXB",
        f"{start_commit}..{end_commit}",
    )
    return sorted(line for line in result.stdout.splitlines() if line)


def classify_changes(paths, catalog, lock_payload):
    sources = theme_sources(lock_payload) + catalog_sources(catalog)
    sources.sort(key=lambda item: len(item["path"]), reverse=True)
    components = {}
    optional = {}
    contracts = []
    metadata = []
    untracked = []
    other = []

    for path in paths:
        matched = next((source for source in sources if path_matches(path, source["path"])), None)
        if matched:
            target = components if matched["target_tier"] == "platform-required" else optional
            record = target.setdefault(
                matched["slug"],
                {
                    "slug": matched["slug"],
                    "runtime_type": matched["runtime_type"],
                    "target_tier": matched["target_tier"],
                    "source_path": matched["path"],
                    "changed_files": [],
                },
            )
            record["changed_files"].append(path)
        elif path in RELEASE_METADATA_PATHS:
            metadata.append(path)
        elif any(path_matches(path, prefix) for prefix in DEPLOYMENT_CONTRACT_PREFIXES):
            contracts.append(path)
        elif path == "stack/mu-plugins/mrn-loader.php" or path.startswith(DEPLOYABLE_ROOTS):
            untracked.append(path)
        else:
            other.append(path)

    return {
        "required_components": sorted(components.values(), key=lambda item: item["slug"]),
        "independent_release_units": sorted(optional.values(), key=lambda item: item["slug"]),
        "deployment_contracts": sorted(contracts),
        "release_metadata": sorted(metadata),
        "untracked_deployables": sorted(untracked),
        "other_changes": sorted(other),
    }


def external_source_inventory(lock_payload, standalone_root):
    root = Path(standalone_root).expanduser().resolve()
    inventory = []
    for entry in lock_payload.get("components") or []:
        source = entry.get("source") or {}
        repository = str(source.get("repository") or "")
        if repository == "MRN":
            continue
        slug = str(entry.get("slug") or "")
        repo_path = root / slug
        record = {
            "slug": slug,
            "repository": repository,
            "path": str(repo_path),
            "locked_commit": source.get("git_commit"),
            "default_ref": None,
            "default_commit": None,
            "status": "missing",
        }
        if run_git(repo_path, "rev-parse", "--git-dir", check=False).returncode != 0:
            inventory.append(record)
            continue

        symbolic = run_git(
            repo_path,
            "symbolic-ref",
            "--quiet",
            "refs/remotes/origin/HEAD",
            check=False,
        )
        candidates = []
        if symbolic.returncode == 0 and symbolic.stdout.strip():
            candidates.append(symbolic.stdout.strip())
        candidates.extend(("refs/remotes/origin/main", "refs/remotes/origin/master"))
        for reference in candidates:
            resolved = run_git(repo_path, "rev-parse", "--verify", reference, check=False)
            if resolved.returncode == 0:
                record["default_ref"] = reference
                record["default_commit"] = resolved.stdout.strip()
                break
        if not record["default_commit"]:
            record["status"] = "default-ref-missing"
        elif record["default_commit"] == record["locked_commit"]:
            record["status"] = "match"
        else:
            record["status"] = "drift"
        inventory.append(record)
    return sorted(inventory, key=lambda item: item["slug"])


def lock_change_summary(baseline, candidate):
    previous = lock_entries(baseline)
    proposed = lock_entries(candidate)
    changed = []
    for slug in sorted(set(previous) | set(proposed)):
        before = previous.get(slug)
        after = proposed.get(slug)
        if before == after:
            continue
        changed.append(
            {
                "slug": slug,
                "entry_kind": (after or before).get("entry_kind"),
                "previous_version": (before or {}).get("version"),
                "candidate_version": (after or {}).get("version"),
                "previous_commit": ((before or {}).get("source") or {}).get("git_commit"),
                "candidate_commit": ((after or {}).get("source") or {}).get("git_commit"),
                "previous_sha256": (before or {}).get("sha256"),
                "candidate_sha256": (after or {}).get("sha256"),
                "change": "added" if before is None else "removed" if after is None else "updated",
            }
        )
    return changed


def verify_candidate_versions(inventory, baseline, candidate):
    blockers = []
    previous = lock_entries(baseline)
    proposed = lock_entries(candidate)
    for component in inventory["required_components"]:
        slug = component["slug"]
        before = previous.get(slug)
        after = proposed.get(slug)
        if after is None:
            blockers.append(f"Changed required component is missing from candidate lock: {slug}")
            continue
        if before and before.get("sha256") == after.get("sha256"):
            blockers.append(f"Changed required component has an unchanged candidate hash: {slug}")
        if before and before.get("version") == after.get("version"):
            blockers.append(f"Changed required component has no version bump: {slug}")

    for change in lock_change_summary(baseline, candidate):
        if change["change"] != "updated":
            continue
        hash_changed = change["previous_sha256"] != change["candidate_sha256"]
        if hash_changed and change["previous_version"] == change["candidate_version"]:
            blockers.append(
                "Changed locked source has no version bump: " + str(change["slug"])
            )
    return sorted(set(blockers))


def read_stack_release_id(path):
    try:
        release_id, _ = release_lock.read_release_metadata(path)
    except (OSError, release_lock.ReleaseLockError) as error:
        raise PromotionError(str(error)) from error
    return release_id


def repository_state(repo_root):
    head = git_output(repo_root, "rev-parse", "HEAD")
    branch = git_output(repo_root, "branch", "--show-current")
    dirty = bool(git_output(repo_root, "status", "--porcelain=v1", "--untracked-files=all"))
    origin_main_result = run_git(repo_root, "rev-parse", "origin/main", check=False)
    origin_main = origin_main_result.stdout.strip() if origin_main_result.returncode == 0 else None
    return {
        "head": head,
        "branch": branch or None,
        "clean": not dirty,
        "origin_main": origin_main,
    }


def is_ancestor(repo_root, ancestor, descendant):
    result = run_git(repo_root, "merge-base", "--is-ancestor", ancestor, descendant, check=False)
    if result.returncode not in (0, 1):
        raise PromotionError(f"Could not compare Git commits {ancestor} and {descendant}")
    return result.returncode == 0


def evaluate_audit(repo_root, lock_payload, catalog, standalone_root, check_external=True):
    state = repository_state(repo_root)
    baseline_commit = str((lock_payload.get("source") or {}).get("git_commit") or "")
    if not baseline_commit:
        raise PromotionError("Release lock has no MRN source commit")
    if not is_ancestor(repo_root, baseline_commit, state["head"]):
        raise PromotionError("Release lock source commit is not an ancestor of HEAD")

    paths = changed_files(repo_root, baseline_commit, state["head"])
    inventory = classify_changes(paths, catalog, lock_payload)
    inventory["external_required_sources"] = (
        external_source_inventory(lock_payload, standalone_root)
        if check_external
        else []
    )
    blockers = []
    warnings = []
    if not state["clean"]:
        blockers.append("Promotion audit requires a clean worktree")
    if state["origin_main"] and state["head"] != state["origin_main"]:
        blockers.append("Promotion audit must run at current origin/main")
    if inventory["required_components"] or inventory["deployment_contracts"]:
        blockers.append("Merged main contains stack changes not represented by the current release lock")
    if inventory["untracked_deployables"]:
        blockers.append("Merged main contains deployable paths missing from the component catalog or theme lock")
    if inventory["independent_release_units"]:
        warnings.append("Independent release units changed after the stack lock and require their own release decision")
    external_drift = [
        item for item in inventory["external_required_sources"] if item["status"] != "match"
    ]
    if external_drift:
        blockers.append(
            "Required standalone source differs from its locked commit or is unavailable: "
            + ", ".join(item["slug"] for item in external_drift)
        )
    if not check_external:
        warnings.append("Required standalone default branches were not checked")

    return {
        "schema_version": REPORT_SCHEMA_VERSION,
        "generated_at": utc_now(),
        "mode": "audit",
        "status": "pass" if not blockers else "blocked",
        "repository": state,
        "baseline": {
            "release_id": lock_payload.get("release_id"),
            "source_commit": baseline_commit,
        },
        "candidate": None,
        "inventory": inventory,
        "lock_changes": [],
        "blockers": blockers,
        "warnings": warnings,
    }


def evaluate_candidate(
    repo_root, baseline, candidate, catalog, standalone_root, check_external=True
):
    state = repository_state(repo_root)
    baseline_commit = str((baseline.get("source") or {}).get("git_commit") or "")
    candidate_commit = str((candidate.get("source") or {}).get("git_commit") or "")
    if not baseline_commit or not candidate_commit:
        raise PromotionError("Baseline and candidate locks require MRN source commits")
    if not is_ancestor(repo_root, baseline_commit, candidate_commit):
        raise PromotionError("Candidate source commit does not descend from the baseline release")
    if not is_ancestor(repo_root, candidate_commit, state["head"]):
        raise PromotionError("Candidate source commit is not an ancestor of HEAD")

    paths = changed_files(repo_root, baseline_commit, candidate_commit)
    inventory = classify_changes(paths, catalog, candidate)
    inventory["external_required_sources"] = (
        external_source_inventory(candidate, standalone_root)
        if check_external
        else []
    )
    lock_changes = lock_change_summary(baseline, candidate)
    blockers = verify_candidate_versions(inventory, baseline, candidate)
    warnings = []

    if not state["clean"]:
        blockers.append("Candidate verification requires a clean worktree")
    if state["origin_main"] and candidate_commit != state["origin_main"]:
        blockers.append("Candidate lock must be generated from current merged origin/main")
    if inventory["untracked_deployables"]:
        blockers.append("Candidate contains deployable paths missing from the component catalog or theme lock")
    if (inventory["required_components"] or inventory["deployment_contracts"]) and (
        candidate.get("release_id") == baseline.get("release_id")
    ):
        blockers.append("Candidate release ID did not change despite stack release changes")

    stack_release_id = read_stack_release_id(repo_root / "stack/STACK_VERSION.md")
    if stack_release_id != candidate.get("release_id"):
        blockers.append("STACK_VERSION.md does not match the candidate release ID")
    changelog = (repo_root / "stack/CHANGELOG.md").read_text(encoding="utf-8")
    if f"## {candidate.get('release_id')}" not in changelog:
        blockers.append("CHANGELOG.md has no entry for the candidate release ID")

    changed_after_candidate = changed_files(repo_root, candidate_commit, state["head"])
    allowed_after_candidate = {
        "stack/manifests/stack-release.lock.json",
    }
    unsafe_after_candidate = sorted(set(changed_after_candidate) - allowed_after_candidate)
    if unsafe_after_candidate:
        blockers.append("Source changed after candidate lock generation: " + ", ".join(unsafe_after_candidate))
    if inventory["independent_release_units"]:
        warnings.append("Independent release units changed and need explicit component release decisions")
    external_drift = [
        item for item in inventory["external_required_sources"] if item["status"] != "match"
    ]
    if external_drift:
        blockers.append(
            "Candidate does not lock each required standalone default branch: "
            + ", ".join(item["slug"] for item in external_drift)
        )
    if not check_external:
        warnings.append("Required standalone default branches were not checked")

    return {
        "schema_version": REPORT_SCHEMA_VERSION,
        "generated_at": utc_now(),
        "mode": "candidate",
        "status": "pass" if not blockers else "blocked",
        "repository": state,
        "baseline": {
            "release_id": baseline.get("release_id"),
            "source_commit": baseline_commit,
        },
        "candidate": {
            "release_id": candidate.get("release_id"),
            "source_commit": candidate_commit,
        },
        "inventory": inventory,
        "lock_changes": lock_changes,
        "blockers": sorted(set(blockers)),
        "warnings": warnings,
    }


def write_report(path, payload):
    serialized = json.dumps(payload, indent=2, sort_keys=True) + "\n"
    if path:
        destination = Path(path).expanduser().resolve()
        destination.parent.mkdir(parents=True, exist_ok=True)
        destination.write_text(serialized, encoding="utf-8")
    else:
        sys.stdout.write(serialized)


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repo-root", type=Path, default=SCRIPT_DIR.parents[1])
    parser.add_argument("--lock", type=Path, default=LOCK_PATH)
    parser.add_argument("--catalog", type=Path, default=CATALOG_PATH)
    parser.add_argument(
        "--standalone-plugins-root",
        type=Path,
        default=Path(
            os.environ.get("MRN_STANDALONE_PLUGINS_ROOT", "~/Development/MRN-plugins")
        ).expanduser(),
    )
    parser.add_argument(
        "--skip-external-heads",
        action="store_true",
        help="skip required standalone default-branch comparison and report a warning",
    )
    parser.add_argument("--mode", choices=("audit", "candidate"), default="audit")
    parser.add_argument(
        "--baseline-ref",
        help="Git ref containing the prior lock; candidate mode auto-discovers it when omitted",
    )
    parser.add_argument("--report", type=Path)
    args = parser.parse_args(argv)

    try:
        repo_root = Path(args.repo_root).resolve()
        lock_payload = release_lock.validate_lock(read_json(args.lock))
        catalog = read_json(args.catalog)
        if args.mode == "candidate":
            relative_lock = Path(args.lock).resolve().relative_to(repo_root).as_posix()
            baseline_ref = args.baseline_ref or discover_baseline_ref(
                repo_root, args.lock, lock_payload
            )
            baseline = release_lock.validate_lock(
                read_json_at_ref(repo_root, baseline_ref, relative_lock)
            )
            report = evaluate_candidate(
                repo_root,
                baseline,
                lock_payload,
                catalog,
                args.standalone_plugins_root,
                not args.skip_external_heads,
            )
        else:
            report = evaluate_audit(
                repo_root,
                lock_payload,
                catalog,
                args.standalone_plugins_root,
                not args.skip_external_heads,
            )
        write_report(args.report, report)
    except (PromotionError, release_lock.ReleaseLockError, OSError, ValueError) as error:
        sys.stderr.write(f"error: {error}\n")
        return 2

    if report["status"] != "pass":
        for blocker in report["blockers"]:
            sys.stderr.write(f"BLOCKED: {blocker}\n")
        return 1
    sys.stderr.write("PASS: stack promotion evidence is internally consistent\n")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
