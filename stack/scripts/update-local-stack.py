#!/usr/bin/env python3
"""Apply a reviewed, checksum-locked Stack payload to one Local Hub clone.

This code-only adapter preserves child themes, plugin activation and data. It
does not enroll or update remote sites, install missing standard plugins, or
run existing-site retirement migrations. An imported parent release bootstrap
can be detached for local development only with an explicit flag; its private
code and public asset generations remain intact for recovery.
"""
from contextlib import contextmanager
import argparse
import fcntl
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
from urllib.parse import urlsplit
import uuid


SPEC = importlib.util.spec_from_file_location(
    "local_stack_release_lock", Path(__file__).with_name("generate-stack-release-lock.py")
)
release_lock = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(release_lock)
LOCK_PATH = "mu-plugins/mrn-stack-release.lock.json"
PARENT_BOOTSTRAP = "mu-plugins/000-mrn-parent-release.php"


def digest(value):
    return hashlib.sha256(value).hexdigest()


def canonical(value):
    return json.dumps(value, sort_keys=True, separators=(",", ":")).encode()


def physical(path):
    path = Path(path).absolute()
    if path.resolve() != path:
        raise ValueError("Local paths must be physical, without symlink ancestors")
    return path


def inventory(path):
    path = physical(path)
    if not path.exists():
        return None
    if path.is_file():
        return {path.name: digest(path.read_bytes())}
    files = {}
    for root, directories, names in os.walk(path, followlinks=False):
        here = Path(root)
        directories[:] = sorted(name for name in directories
                                if name not in release_lock.EXCLUDED_DIRECTORIES
                                and not (here == path and name in release_lock.ROOT_EXCLUDED_DIRECTORIES))
        for name in directories:
            if (here / name).is_symlink():
                raise ValueError("Local code trees must contain only physical files and directories")
        for name in sorted(names):
            if name in release_lock.EXCLUDED_FILES:
                continue
            entry = here / name
            if entry.is_symlink() or not entry.is_file():
                raise ValueError("Local code trees must contain only physical files and directories")
            files[entry.relative_to(path).as_posix()] = digest(entry.read_bytes())
    return files


def unmanaged_paths(path):
    """Identify excluded development/cache nodes without scanning their contents."""
    if not path.is_dir():
        return []
    result = []
    for root, directories, names in os.walk(path, followlinks=False):
        here = Path(root)
        excluded = [name for name in directories if name in release_lock.EXCLUDED_DIRECTORIES
                    or (here == path and name in release_lock.ROOT_EXCLUDED_DIRECTORIES)]
        directories[:] = sorted(name for name in directories if name not in excluded)
        result.extend((here / name).relative_to(path).as_posix() for name in excluded)
        result.extend((here / name).relative_to(path).as_posix() for name in names
                      if name in release_lock.EXCLUDED_FILES)
    return sorted(result)


def slot_inventory(path, relative):
    """Normalize a journal slot's root filename to its deployed filename."""
    result = inventory(path)
    if result is not None and Path(path).is_file():
        return {Path(relative).name: digest(Path(path).read_bytes())}
    return result


def load_site(manifest):
    manifest = physical(manifest)
    raw = json.loads(manifest.read_text())
    slug = raw.get("slug", "")
    url = urlsplit(raw.get("localUrl", ""))
    if (not re.fullmatch(r"[a-z0-9_-]+", slug)
            or raw.get("runtime") != "local-vm-openlitespeed"
            or url.scheme not in ("http", "https")
            or url.hostname != slug + ".localhost"
            or url.netloc != url.hostname or url.path not in ("", "/")
            or url.query or url.fragment):
        raise ValueError("Only an exact Local Hub Lima .localhost clone is supported")
    root = physical(raw["localRoot"])
    public = physical(raw["publicPath"])
    php = raw.get("phpVersion", "")
    if (manifest != root / ".mrn-site.json" or public != root / "public"
            or not public.is_dir() or not re.fullmatch(r"8\.[0-9]", php)):
        raise ValueError("Manifest, local root, public root and supported PHP identity must agree")
    return {"manifest": str(manifest), "slug": slug, "root": str(root),
            "public": str(public), "url": raw["localUrl"].rstrip("/"), "php": php}


def wp(site, arguments):
    php = site["php"].replace(".", "")
    command = ["limactl", "shell", "--workdir=/", "mrn-openlitespeed", "--",
               "sudo", "-u", "nobody", "env",
               "MRN_WP_CLI_PHP=/usr/local/lsws/lsphp" + php + "/bin/php",
               "wp", "--path=/srv/mrn-sites/" + site["slug"] + "/public", *arguments]
    result = subprocess.run(command, capture_output=True, text=True, timeout=120)
    if result.returncode:
        # WordPress diagnostics can contain configuration values; retain no raw
        # stdout/stderr in receipts or error messages.
        raise RuntimeError("Local WordPress command failed; no private diagnostics disclosed")
    return result.stdout


def inspect_site(site):
    program = '''
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$plugins = array();
foreach (get_plugins() as $file => $data) { $plugins[] = dirname($file); }
$child = get_stylesheet_directory();
$selection = null;
if (class_exists('MRN_Component_Release_Runtime')) {
    $selection = json_decode(file_get_contents(dirname(get_template_directory(), 4) . '/current.json'), true);
}
echo 'MRN_LOCAL_RESULT=' . wp_json_encode(array(
 'identity' => array('home' => untrailingslashit(home_url()), 'siteurl' => untrailingslashit(site_url()),
 'template' => get_template(), 'stylesheet' => get_stylesheet(), 'child_directory' => $child,
 'child_manifest' => is_file($child . '/mrn-assets.json') ? hash_file('sha256', $child . '/mrn-assets.json') : null,
 'parent_directory' => get_template_directory(), 'parent_selection' => $selection,
 'local_guard' => function_exists('mrn_local_safety_is_local_clone') && mrn_local_safety_is_local_clone(),
 'installed' => array_values(array_unique($plugins)), 'active' => (array)get_option('active_plugins', array())),
 'report' => mrn_loader_get_runtime_report()));
'''
    lines = [line[17:] for line in wp(site, ["eval", program]).splitlines()
             if line.startswith("MRN_LOCAL_RESULT=")]
    if len(lines) != 1:
        raise ValueError("One unambiguous local runtime identity is required")
    return json.loads(lines[0])


def safe_relative(value):
    if (not isinstance(value, str) or not re.fullmatch(r"[A-Za-z0-9._/-]+", value)
            or value.startswith("/") or any(part in ("", ".", "..") for part in value.split("/"))):
        raise ValueError("Unsafe component path")
    return value


def preflight(site, lock_file, lock_sha256, artifact_root, detach_parent=False):
    lock_file, artifact_root = physical(lock_file), physical(artifact_root)
    if not re.fullmatch(r"[a-f0-9]{64}", lock_sha256) or digest(lock_file.read_bytes()) != lock_sha256:
        raise ValueError("Release lock differs from the independently trusted checksum")
    lock = release_lock.validate_lock(json.loads(lock_file.read_text()))
    runtime = inspect_site(site)
    identity = runtime["identity"]
    if (identity["home"] != site["url"] or identity["siteurl"] != site["url"]
            or identity["template"] != "mrn-base-stack"
            or not re.fullmatch(r"[a-z0-9_-]+", identity["stylesheet"])
            or identity["stylesheet"] == "mrn-base-stack" or not identity["local_guard"]):
        raise ValueError("Exact local identity, preserved child and local safety guard are required")
    content = physical(Path(site["public"]) / "wp-content")
    child = physical(content / "themes" / identity["stylesheet"])
    if not child.is_dir():
        raise ValueError("The physical site child theme is unavailable")
    changes = []
    parents = [entry for entry in lock["themes"] if entry["deployment_role"] == "parent-template"]
    for entry in lock["components"] + parents:
        relative = safe_relative(entry["deployed_path"])
        slug, kind = entry["slug"], entry.get("runtime_type", "parent-theme")
        expected_path = {"mu-loader": "mu-plugins/" + slug + ".php",
                         "mu-component": "mu-plugins/" + slug,
                         "standard-plugin": "plugins/" + slug,
                         "shared-runtime": "shared", "parent-theme": "themes/mrn-base-stack"}[kind]
        if slug == "mrn-updraft-backup-policy-loader":
            expected_path = "mu-plugins/mrn-updraft-local-retention.php"
        elif kind == "mu-loader" and slug != "mrn-loader" and slug.endswith("-loader"):
            expected_path = "mu-plugins/" + slug[:-7] + ".php"
        if relative != expected_path:
            raise ValueError("Locked component path is outside the supported local allowlist")
        if kind == "standard-plugin" and slug not in identity["installed"]:
            raise ValueError("A missing standard plugin cannot be installed by the local update adapter")
        source, target = physical(artifact_root / relative), physical(content / relative)
        if (target / ".git").exists():
            raise ValueError("A component source Git checkout cannot be overwritten by a runtime update")
        inventory(source)
        checksum, count = release_lock.tree_sha256(source)
        if (checksum, count) != (entry["sha256"], entry["file_count"]):
            raise ValueError("Artifact differs from the locked component: " + slug)
        before, after = inventory(target), inventory(source)
        if before != after:
            changes.append({"path": relative, "source": str(source), "before": before, "after": after,
                            "unmanaged": unmanaged_paths(target)})
    bootstrap = physical(content / PARENT_BOOTSTRAP)
    if bootstrap.exists():
        code = bootstrap.read_text()
        selection = identity.get("parent_selection") or {}
        if (not detach_parent or "MRN_Component_Release_Runtime::boot(" not in code
                or "MRN component release storage is unavailable." not in code
                or set(selection.get("components", {})) != {"mrn-base-stack"}):
            raise ValueError("Explicit parent-only imported release detachment is required")
        changes.append({"path": PARENT_BOOTSTRAP, "source": None,
                        "before": inventory(bootstrap), "after": None})
    elif identity.get("parent_selection"):
        raise ValueError("An unrecognized component release bootstrap is active")
    changes.append({"path": LOCK_PATH, "source": str(lock_file),
                    "before": inventory(content / LOCK_PATH),
                    "after": {Path(LOCK_PATH).name: lock_sha256}})
    baseline_report = dict(runtime["report"])
    baseline_report.pop("generated_at_utc", None)
    return {"schema": 1, "site": site, "release_id": lock["release_id"], "lock_sha256": lock_sha256,
            "identity": identity, "child_inventory": inventory(child), "changes": changes,
            "baseline_report": baseline_report, "detach_imported_parent": bootstrap.exists(),
            "standard_plugins_preserved": True, "database_migrations": False}


def write_json(path, value):
    fd, temporary = tempfile.mkstemp(prefix=".local-next-", dir=path.parent)
    try:
        with os.fdopen(fd, "wb") as stream:
            stream.write(canonical(value) + b"\n")
            os.fchmod(stream.fileno(), 0o600)
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(temporary, path)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


@contextmanager
def writer(site):
    state = physical(Path(site["root"]) / "output" / "local-fleet-updates")
    state.mkdir(parents=True, exist_ok=True, mode=0o700)
    os.chmod(state, 0o700)
    fd = os.open(state / "writer.lock", os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
    try:
        fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        yield state
    finally:
        os.close(fd)


def verify_runtime(plan):
    site = plan["site"]
    current = inspect_site(site)
    before, after, report = plan["identity"], current["identity"], current["report"]
    for key in ("home", "siteurl", "template", "stylesheet", "child_directory", "child_manifest", "active"):
        if before[key] != after[key]:
            raise ValueError("Local update changed preserved site identity or child/plugin state")
    parent = next(row for row in report["themes"] if row["slug"] == "mrn-base-stack")
    if (report["release_lock"]["sha256"] != plan["lock_sha256"]
            or report["release_lock"]["release_id"] != plan["release_id"]
            or report["missing_required"] or report["drifted_required"]
            or report["legacy_flat_collisions"] or parent["matches_release"] is not True
            or after["parent_directory"] != "/srv/mrn-sites/" + site["slug"] + "/public/wp-content/themes/mrn-base-stack"
            or not after["local_guard"]):
        raise ValueError("Selected local runtime does not match the exact release")
    return current


def restore(journal_path):
    journal_path = physical(journal_path)
    journal = json.loads(journal_path.read_text())
    plan = journal["plan"]
    if digest(canonical(plan)) != journal.get("plan_sha256"):
        raise ValueError("Recovery journal plan checksum differs")
    site = load_site(plan["site"]["manifest"])
    if (site != plan["site"] or journal_path.parent.parent != Path(site["root"]) / "output/local-fleet-updates"
            or journal_path.name != "journal.json" or journal.get("schema") != 1):
        raise ValueError("Recovery journal and local site identity differ")
    content = physical(Path(site["public"]) / "wp-content")
    # Inspect every recovery slot before undoing any slot. An unrelated edit
    # blocks recovery instead of being overwritten by an old code snapshot.
    for index, row in enumerate(plan["changes"]):
        target = physical(content / safe_relative(row["path"]))
        if not (row["path"] in (LOCK_PATH, PARENT_BOOTSTRAP) or row["path"].startswith(("mu-plugins/mrn-", "plugins/mrn-"))
                or row["path"] in ("shared", "themes/mrn-base-stack")):
            raise ValueError("Recovery slot is outside the local component allowlist")
        current = inventory(target)
        if current is not None and current not in (row["before"], row["after"]):
            raise ValueError("Recovery refused an unrelated component edit")
        backup = journal_path.parent / "before" / str(index)
        if backup.exists() and slot_inventory(backup, row["path"]) != row["before"]:
            raise ValueError("Recovery before-state was modified")
        if row["before"] is not None and not backup.exists() and current != row["before"]:
            raise ValueError("Recovery before-state is missing")
        for relative in row.get("unmanaged", []):
            safe_relative(relative)
            preserved, original = target / relative, backup / relative
            if ((preserved.exists() or preserved.is_symlink())
                    and (original.exists() or original.is_symlink())):
                raise ValueError("Recovery found duplicate unmanaged development files")
    for index, row in reversed(list(enumerate(plan["changes"]))):
        target = content / row["path"]
        backup = journal_path.parent / "before" / str(index)
        if backup.exists():
            if target.exists():
                for relative in row.get("unmanaged", []):
                    preserved = target / safe_relative(relative)
                    original = backup / relative
                    if preserved.exists() or preserved.is_symlink():
                        original.parent.mkdir(parents=True, exist_ok=True)
                        os.replace(preserved, original)
                shutil.rmtree(target) if target.is_dir() else target.unlink()
            os.replace(backup, target)
        elif row["before"] is None and inventory(target) == row["after"] and target.exists():
            shutil.rmtree(target) if target.is_dir() else target.unlink()
    for row in plan["changes"]:
        if inventory(content / row["path"]) != row["before"]:
            raise ValueError("Recovery did not restore the exact component before-state")
    wp(site, ["cache", "flush"])
    if inspect_site(site)["identity"] != plan["identity"]:
        raise ValueError("Recovered WordPress identity differs from the before-state")
    journal["status"] = "rolled-back"
    write_json(journal_path, journal)
    return journal


def apply(plan, approved_sha256):
    if digest(canonical(plan)) != approved_sha256:
        raise ValueError("Local plan differs from the reviewed checksum")
    site = plan["site"]
    content = physical(Path(site["public"]) / "wp-content")
    with writer(site) as state:
        for previous in state.glob("*/journal.json"):
            if json.loads(previous.read_text()).get("status") not in ("verified-current", "rolled-back"):
                raise ValueError("An incomplete local update needs recovery before another update")
        if inspect_site(site)["identity"] != plan["identity"]:
            raise ValueError("Reviewed WordPress identity changed")
        child = content / "themes" / plan["identity"]["stylesheet"]
        if inventory(child) != plan["child_inventory"]:
            raise ValueError("Reviewed child theme changed")
        for row in plan["changes"]:
            if inventory(content / row["path"]) != row["before"]:
                raise ValueError("Reviewed component before-state changed")
        directory = state / str(uuid.uuid4())
        directory.mkdir(mode=0o700)
        (directory / "before").mkdir()
        (directory / "next").mkdir()
        journal_path = directory / "journal.json"
        journal = {"schema": 1, "status": "preparing", "plan_sha256": approved_sha256, "plan": plan}
        write_json(journal_path, journal)
        try:
            for index, row in enumerate(plan["changes"]):
                if row["source"] is None:
                    continue
                source, stage = physical(row["source"]), directory / "next" / str(index)
                if source.is_dir():
                    stage.mkdir(mode=0o755)
                    for relative, entry in release_lock.iter_digest_files(source):
                        destination = stage / relative
                        destination.parent.mkdir(parents=True, exist_ok=True, mode=0o755)
                        shutil.copyfile(entry, destination)
                        os.chmod(destination, 0o644)
                else:
                    shutil.copyfile(source, stage)
                    os.chmod(stage, 0o644)
                if slot_inventory(stage, row["path"]) != row["after"]:
                    raise ValueError("Staged artifact changed after preflight")
            journal["status"] = "applying"
            write_json(journal_path, journal)
            for index, row in enumerate(plan["changes"]):
                target = content / row["path"]
                if target.exists():
                    os.replace(target, directory / "before" / str(index))
                if row["source"] is not None:
                    target.parent.mkdir(parents=True, exist_ok=True)
                    os.replace(directory / "next" / str(index), target)
                    for relative in row.get("unmanaged", []):
                        original = directory / "before" / str(index) / relative
                        preserved = target / safe_relative(relative)
                        if original.exists() or original.is_symlink():
                            preserved.parent.mkdir(parents=True, exist_ok=True)
                            os.replace(original, preserved)
            wp(site, ["cache", "flush"])
            verification = verify_runtime(plan)
            if inventory(child) != plan["child_inventory"]:
                raise ValueError("The site child theme changed during the local update")
            journal.update(status="verified-current", verification=verification)
            write_json(journal_path, journal)
        except Exception:
            try:
                restore(journal_path)
            except Exception:
                journal["status"] = "recovery-required"
                write_json(journal_path, journal)
                raise RuntimeError("Local update failed; recovery is required from the retained journal") from None
            raise RuntimeError("Local update failed verification and its code was rolled back") from None
        return {"status": journal["status"], "release_id": plan["release_id"],
                "changed_paths": [row["path"] for row in plan["changes"]],
                "journal": str(journal_path), "child_preserved": True, "database_migrations": False}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--site-manifest", type=Path)
    parser.add_argument("--release-lock", type=Path)
    parser.add_argument("--lock-sha256")
    parser.add_argument("--artifact-root", type=Path)
    parser.add_argument("--detach-imported-parent", action="store_true")
    parser.add_argument("--plan-out", type=Path)
    parser.add_argument("--execute", action="store_true")
    parser.add_argument("--approve-plan-sha256")
    parser.add_argument("--rollback", type=Path)
    args = parser.parse_args()
    if args.rollback:
        journal = json.loads(physical(args.rollback).read_text())
        site = load_site(journal["plan"]["site"]["manifest"])
        with writer(site):
            result = restore(args.rollback)
        print(json.dumps({"status": result["status"], "journal": str(args.rollback)}))
        return
    if not all((args.site_manifest, args.release_lock, args.lock_sha256, args.artifact_root)):
        parser.error("site manifest, release lock, trusted lock checksum and artifact root are required")
    plan = preflight(load_site(args.site_manifest), args.release_lock, args.lock_sha256,
                     args.artifact_root, args.detach_imported_parent)
    checksum = digest(canonical(plan))
    if args.plan_out:
        write_json(physical(args.plan_out), plan)
    if args.execute:
        print(json.dumps(apply(plan, args.approve_plan_sha256)))
    else:
        print(json.dumps({"status": "preflight-only", "release_id": plan["release_id"],
                          "plan_sha256": checksum, "changed_paths": [row["path"] for row in plan["changes"]],
                          "detach_imported_parent": plan["detach_imported_parent"], "child_preserved": True}))


if __name__ == "__main__":
    main()
