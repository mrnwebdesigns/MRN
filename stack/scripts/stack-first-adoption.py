#!/usr/bin/env python3
"""Guarded one-time first adoption of an MRN Stack release on a legacy-shaped site.

A legacy-shaped site runs a renamed copy of an old Stack parent (for example
``template=mrnwebdesigns``, ``stylesheet=mrnwebdesigns-child``) and has no valid
runtime release lock, so ``deploy-stack-release-to-site.py`` refuses it (and
would overwrite the old parent). This tool installs the canonical parent theme
as a NEW ``themes/<lock parent slug>`` directory and installs or replaces every
release component at its lock ``deployed_path`` plus the runtime release lock.

It never changes ``template``/``stylesheet``, never touches the legacy parent or
the active child, and never activates or deactivates plugins. Switching the
parent is the job of the later site child deploy; the remaining activation
steps are reported instead.

Subcommands (shared arguments shown once):

  plan     read-only on the target; prints the plan and its plan hash.
  apply    --plan-hash <sha> --receipt-out <journal.json>
           refuses a changed plan or any blocking finding, creates and verifies
           a labelled database-only Updraft backup (preflight --with-db-backup),
           tars every path it will replace into a private directory outside the
           web root, writes the journal, installs, then verifies.
  verify   hashes every installed path, checks HTTP 200 on the legacy parent and
           a full WP-CLI load without fatal.
  rollback --journal <journal.json> --confirm "ROLLBACK <hostname> <release_id>"
           refuses if targets changed since apply, takes a fresh database backup,
           restores replaced paths from the tar and removes added paths.

Example (development target; host specifics are arguments, not code):

  stack/scripts/stack-first-adoption.py plan \\
    --release-lock stack/manifests/stack-release.lock.json \\
    --artifact-root <payload-dir> \\
    --site-url https://mrnwebdesigns.mrndev.io \\
    --expected-environment <development|staging|production> \\
    --expected-template mrnwebdesigns --expected-stylesheet mrnwebdesigns-child \\
    --expected-site-root /home/mrnwebdesigns-stack/htdocs/mrnwebdesigns.mrndev.io \\
    --discovery-ssh-host mrndev --receipt-out <plan-receipt.json>

For a host without CloudPanel owner discovery (for example SiteGround), add
``--site-user <user> --ssh-alias <alias-from-~/.ssh/config>``; the site root is
then taken from ``--expected-site-root``.

Build ``<payload-dir>`` with ``assemble-stack-release.py --output <dir>``. Every
non-dry-run write (apply, rollback) requires the backup gate and explicit owner
approval of the exact plan hash. Nothing here prints secrets.
"""

from __future__ import annotations

import argparse
import hashlib
import importlib.util
import json
import re
import shlex
import sys
import urllib.error
import urllib.request
from pathlib import Path
from urllib.parse import urlsplit


SCRIPT_DIR = Path(__file__).resolve().parent
DEPLOY_SPEC = importlib.util.spec_from_file_location(
    "deploy_stack_release_to_site", SCRIPT_DIR / "deploy-stack-release-to-site.py"
)
deploy = importlib.util.module_from_spec(DEPLOY_SPEC)
DEPLOY_SPEC.loader.exec_module(deploy)
release_lock = deploy.release_lock

SCHEMA_VERSION = 1
STATE_MARKER = "MRN_ADOPT_STATE="
LOAD_MARKER = "MRN_ADOPT_LOAD_OK"
BACKUP_PARENT = ".mrn-deploy-backups"
FUNCTION_RE = re.compile(r"^\s*function\s+&?([A-Za-z_][A-Za-z0-9_]*)\s*\(")
GUARD_RE = re.compile(r"function_exists\s*\(\s*['\"]\\?([A-Za-z_][A-Za-z0-9_]*)['\"]")
NAMESPACE_RE = re.compile(r"^\s*namespace\s+[A-Za-z_\\]")
PLUGIN_NAME_RE = re.compile(r"Plugin Name:\s*(.+?)\s*(?:\*/)?\s*$", re.I)
MU_REF_RE = re.compile(r"(?:mu-plugins/|['\"]/)(?!mu-plugins/)([A-Za-z0-9._-]+)/")
REMOTE_GREP = (
    r"^[[:space:]]*function[[:space:]]+&?[A-Za-z_][A-Za-z0-9_]*[[:space:]]*\(|"
    r"function_exists[[:space:]]*\(|^[[:space:]]*namespace[[:space:]]+[A-Za-z_]"
)

# Code WordPress never loads at runtime; scanning it only yields test stubs.
SYMBOL_SKIP_DIRS = {"tests", "test", "vendor", "node_modules"} | set(release_lock.EXCLUDED_DIRECTORIES)

STATE_PHP = (
    "if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . "
    "'wp-admin/includes/plugin.php'; } echo '" + STATE_MARKER + "' . wp_json_encode("
    " array( 'home_url' => home_url(), 'environment_type' => wp_get_environment_type(),"
    " 'template' => get_option( 'template' ), 'stylesheet' => get_option( 'stylesheet' ),"
    " 'active_plugins' => array_values( (array) get_option( 'active_plugins', array() ) ),"
    " 'is_multisite' => is_multisite() ) ) . \"\\n\";"
)


class AdoptionError(RuntimeError):
    """The first adoption cannot proceed or be verified safely."""


# ---------------------------------------------------------------- helpers


def canonical_sha256(value):
    data = json.dumps(value, sort_keys=True, separators=(",", ":")).encode("utf-8")
    return hashlib.sha256(data).hexdigest()


def ssh(ctx, script, runner):
    return runner(["ssh", ctx["ssh_login"], script]).stdout


def wp(ctx, runner, *arguments):
    return runner(deploy.remote_wp(ctx["ssh_login"], ctx["site_root"], *arguments)).stdout


def digest_excluded(relative, top_name):
    """Mirror generate-stack-release-lock.iter_digest_files exclusions."""
    parts = relative.split("/")
    if any(part in release_lock.EXCLUDED_DIRECTORIES for part in parts[:-1]):
        return True
    if len(parts) > 1 and parts[0] in release_lock.ROOT_EXCLUDED_DIRECTORIES:
        return True
    name = parts[-1]
    return name in release_lock.EXCLUDED_FILES or (
        name == ".mrn-qa.env" and top_name in release_lock.LOCAL_QA_PROFILE_COMPONENTS
    )


def records_tree_sha256(records):
    """sha256-tree-v1 over (relative, sha256, size) records in os.walk order."""

    def order(paths, prefix=""):
        files, directories = [], set()
        for path in paths:
            rest = path[len(prefix):]
            if "/" in rest:
                directories.add(rest.split("/", 1)[0])
            else:
                files.append(path)
        ordered = sorted(files)
        for directory in sorted(directories):
            child = f"{prefix}{directory}/"
            ordered.extend(order([p for p in paths if p.startswith(child)], child))
        return ordered

    by_path = {relative: (sha, size) for relative, sha, size in records}
    digest = hashlib.sha256()
    for relative in order(list(by_path)):
        sha, size = by_path[relative]
        digest.update(f"{relative}\0{sha}\0{size}\n".encode("utf-8"))
    return (digest.hexdigest() if by_path else None), len(by_path)


def php_symbols(lines):
    """Return ({lowercase name: declared name}, guarded lowercase names, namespaced)."""
    declared, guarded, namespaced = {}, set(), False
    for line in lines:
        if NAMESPACE_RE.search(line):
            namespaced = True
        match = FUNCTION_RE.search(line)
        if match:
            declared.setdefault(match.group(1).lower(), match.group(1))
        guarded.update(name.lower() for name in GUARD_RE.findall(line))
    return declared, guarded, namespaced


# ------------------------------------------------------- local payload side


def adoption_operations(payload, artifact_root):
    parent_slug = next(
        theme["slug"]
        for theme in payload["themes"]
        if theme.get("deployment_role") == "parent-template"
        and theme.get("verification_mode") == "exact"
    )
    # Reuse the deploy mapping, pinning the parent to its own new directory.
    operations, standard_plugins = deploy.deployment_operations(
        payload, artifact_root, parent_slug
    )
    runtime = {entry["slug"]: entry["runtime_type"] for entry in payload["components"]}
    for operation in operations:
        operation["runtime_type"] = runtime.get(
            operation["slug"],
            "parent-theme" if operation["slug"] == parent_slug else "release-lock",
        )
        digest, count = release_lock.tree_sha256(Path(operation["source"]))
        operation["expected_sha256"] = digest
        operation["expected_file_count"] = count
    return parent_slug, operations, standard_plugins


def payload_symbols(operations):
    """Global function declarations from payload code that WordPress will load."""
    symbols = []
    for operation in operations:
        if operation["runtime_type"] in {"parent-theme", "release-lock"}:
            continue
        source = Path(operation["source"])
        files = [source] if source.is_file() else sorted(source.rglob("*.php"))
        for path in files:
            if path.suffix != ".php" or SYMBOL_SKIP_DIRS & set(path.relative_to(source).parts[:-1]):
                continue
            declared, guarded, namespaced = php_symbols(
                path.read_text(encoding="utf-8", errors="replace").splitlines()
            )
            if declared and not namespaced:
                symbols.append(
                    {
                        "slug": operation["slug"],
                        "runtime_type": operation["runtime_type"],
                        "declared": declared,
                        "guarded": guarded,
                    }
                )
    return symbols


def payload_plugin_names(operations):
    names = {}
    for operation in operations:
        source = Path(operation["source"])
        candidates = [source] if source.is_file() else sorted(source.glob("*.php"))
        for path in candidates:
            for line in path.read_text(encoding="utf-8", errors="replace").splitlines()[:30]:
                match = PLUGIN_NAME_RE.search(line)
                if match:
                    names[match.group(1).strip()] = operation["target"]
                    break
    return names


# --------------------------------------------------------- remote target side


def hash_prelude(site_root):
    return (
        "set -eu; umask 077; "
        f"root={shlex.quote(site_root)}; "
        "if command -v sha256sum >/dev/null 2>&1; then H='sha256sum'; else H='shasum -a 256'; fi; "
        "tree() { p=\"$root/$1\"; "
        "if [ -L \"$p\" ]; then printf 'P\\t%s\\tlink\\n' \"$1\"; "
        "elif [ -f \"$p\" ]; then printf 'P\\t%s\\tfile\\n' \"$1\"; "
        "printf 'F\\t%s\\t%s\\t%s\\t%s\\n' \"$1\" \"$($H \"$p\" | awk '{print $1}')\" "
        "\"$(wc -c < \"$p\" | tr -d ' ')\" \"${p##*/}\"; "
        "elif [ -d \"$p\" ]; then printf 'P\\t%s\\tdir\\n' \"$1\"; "
        "find \"$p\" -mindepth 1 \\( -type f -o -type l \\) -print | while IFS= read -r f; do "
        "r=\"${f#\"$p\"/}\"; if [ -L \"$f\" ]; then printf 'L\\t%s\\t%s\\n' \"$1\" \"$r\"; "
        "else printf 'F\\t%s\\t%s\\t%s\\t%s\\n' \"$1\" \"$($H \"$f\" | awk '{print $1}')\" "
        "\"$(wc -c < \"$f\" | tr -d ' ')\" \"$r\"; fi; done; "
        "elif [ -e \"$p\" ]; then printf 'P\\t%s\\tother\\n' \"$1\"; "
        "else printf 'P\\t%s\\tabsent\\n' \"$1\"; fi; }; "
    )


def read_trees(ctx, relatives, runner):
    script = hash_prelude(ctx["site_root"]) + " ".join(
        f"tree {shlex.quote(relative)};" for relative in relatives
    )
    trees = {relative: {"type": "absent", "records": [], "links": []} for relative in relatives}
    for line in ssh(ctx, script, runner).splitlines():
        fields = line.split("\t")
        if fields[0] == "P" and len(fields) == 3 and fields[1] in trees:
            trees[fields[1]]["type"] = fields[2]
        elif fields[0] == "F" and len(fields) == 5 and fields[1] in trees:
            trees[fields[1]]["records"].append((fields[4], fields[2], int(fields[3])))
        elif fields[0] == "L" and len(fields) == 3 and fields[1] in trees:
            trees[fields[1]]["links"].append(fields[2])
    summary = {}
    for relative, tree in trees.items():
        top = Path(relative).name
        raw_files = sorted(r[0] for r in tree["records"])
        records = [r for r in tree["records"] if not digest_excluded(r[0], top)]
        links = [link for link in tree["links"] if not digest_excluded(link, top)]
        digest, count = records_tree_sha256(records)
        summary[relative] = {
            "type": tree["type"],
            "sha256": digest,
            "file_count": count,
            "files": raw_files,
            "links": sorted(links),
        }
    return summary


def read_site_state(ctx, runner, *, skip_themes=True):
    arguments = ["eval", STATE_PHP] + (["--skip-themes"] if skip_themes else [])
    for line in wp(ctx, runner, *arguments).splitlines():
        if line.startswith(STATE_MARKER):
            try:
                state = json.loads(line[len(STATE_MARKER):])
            except json.JSONDecodeError as error:
                raise AdoptionError("Site state was not valid JSON") from error
            if isinstance(state, dict):
                return state
    raise AdoptionError("WP-CLI did not return the site state marker")


def read_inventory(ctx, runner):
    """Top-level mu-plugins/plugins entries, plugin names, MU code references."""
    script = (
        "set -eu; "
        f"c={shlex.quote(ctx['site_root'] + '/wp-content')}; "
        "for a in mu-plugins plugins; do for e in \"$c/$a\"/* ; do "
        "[ -e \"$e\" ] || [ -L \"$e\" ] || continue; "
        "if [ -L \"$e\" ]; then t=link; elif [ -d \"$e\" ]; then t=dir; else t=file; fi; "
        "printf 'E\\t%s\\t%s\\t%s\\n' \"$a\" \"$t\" \"${e##*/}\"; done; done; "
        "for f in \"$c\"/mu-plugins/*.php \"$c\"/plugins/*.php \"$c\"/plugins/*/*.php; do "
        "[ -f \"$f\" ] || continue; n=$(head -n 30 \"$f\" | grep -m1 -i 'Plugin Name:' || true); "
        "if [ -n \"$n\" ]; then printf 'N\\t%s\\t%s\\n' \"${f#\"$c\"/}\" \"$n\"; fi; done; "
        "for f in \"$c\"/mu-plugins/*.php; do [ -f \"$f\" ] || continue; "
        "grep -E \"(mu-plugins/|['\\\"]/)[A-Za-z0-9._-]+/\" \"$f\" | while IFS= read -r l; do "
        "printf 'R\\t%s\\t%s\\n' \"${f##*/}\" \"$l\"; done || true; done; true"
    )
    inventory = {"entries": [], "plugin_names": {}, "mu_refs": {}}
    for line in ssh(ctx, script, runner).splitlines():
        fields = line.split("\t", 3)
        if fields[0] == "E" and len(fields) == 4:
            inventory["entries"].append(
                {"area": fields[1], "type": fields[2], "name": fields[3]}
            )
        elif fields[0] == "N" and len(fields) == 3:
            match = PLUGIN_NAME_RE.search(fields[2])
            if match:
                inventory["plugin_names"]["wp-content/" + fields[1]] = match.group(1).strip()
        elif fields[0] == "R" and len(fields) == 3:
            refs = inventory["mu_refs"].setdefault(fields[1], [])
            for name in MU_REF_RE.findall(fields[2]):
                refs.append({"dir": name, "once": "_once" in fields[2]})
    return inventory


def read_remote_symbols(ctx, relatives, runner):
    if not relatives:
        return []
    root = ctx["site_root"]
    script = (
        f"cd {shlex.quote(root)} && "
        "grep -rHE --include='*.php' "
        + " ".join(f"--exclude-dir={shlex.quote(name)}" for name in sorted(SYMBOL_SKIP_DIRS))
        + " "
        f"{shlex.quote(REMOTE_GREP)} "
        + " ".join(shlex.quote(relative) for relative in relatives)
        + " 2>/dev/null || true"
    )
    by_file = {}
    for line in ssh(ctx, script, runner).splitlines():
        path, separator, text = line.partition(":")
        if separator:
            by_file.setdefault(path, []).append(text)
    symbols = []
    for path, lines in sorted(by_file.items()):
        declared, guarded, namespaced = php_symbols(lines)
        if declared and not namespaced:
            symbols.append({"path": path, "declared": declared, "guarded": guarded})
    return symbols


# ------------------------------------------------------------------- policy


def resolve_target(args, runner, *, with_backup=False, backup_label=None):
    hostname = deploy.normalize_hostname(urlsplit(args.site_url).hostname or "")
    command = [
        SCRIPT_DIR / "preflight-live-site-deploy.sh",
        "--site-hostname",
        hostname,
        "--discovery-ssh-host",
        args.discovery_ssh_host,
    ]
    if args.site_user or args.ssh_alias:
        if not (args.site_user and args.ssh_alias):
            raise AdoptionError("--site-user and --ssh-alias must be given together")
        command += [
            "--site-user", args.site_user,
            "--site-root", args.expected_site_root,
            "--ssh-alias", args.ssh_alias,
        ]
    if with_backup:
        command += ["--with-db-backup", "--backup-label", backup_label]
    else:
        command.append("--skip-backup")
    values = deploy.parse_key_values(runner(command).stdout)
    if not {"SITE_HOSTNAME", "SITE_USER", "SITE_ROOT", "SSH_LOGIN"} <= set(values):
        raise AdoptionError("Preflight did not return complete site-owner details")
    if deploy.normalize_hostname(values["SITE_HOSTNAME"]) != hostname:
        raise AdoptionError("Preflight returned the wrong hostname")
    if not deploy.SSH_LOGIN_RE.fullmatch(values["SSH_LOGIN"]):
        raise AdoptionError("Preflight returned an unsafe SSH login")
    site_root = deploy.validate_absolute_path(values["SITE_ROOT"], "site root")
    if site_root != args.expected_site_root.rstrip("/"):
        raise AdoptionError(
            f"Resolved site root {site_root} is not --expected-site-root"
        )
    if with_backup and values.get("BACKUP_LABEL") != backup_label:
        raise AdoptionError("Verified backup receipt is missing or does not match")
    return {
        "hostname": hostname,
        "site_user": values["SITE_USER"],
        "site_root": site_root,
        "ssh_login": values["SSH_LOGIN"],
        "backup_label": values.get("BACKUP_LABEL") or None,
    }


def check_identity(args, state, parent_slug):
    problems = []
    if state.get("is_multisite"):
        problems.append("multisite is not supported")
    if state.get("home_url") != args.site_url:
        problems.append(f"home_url() is {state.get('home_url')!r}, expected {args.site_url!r}")
    if state.get("environment_type") != args.expected_environment:
        problems.append(
            f"environment type is {state.get('environment_type')!r}, "
            f"expected {args.expected_environment!r}"
        )
    for key, expected in (("template", args.expected_template), ("stylesheet", args.expected_stylesheet)):
        if state.get(key) != expected:
            problems.append(f"{key} is {state.get(key)!r}, expected {expected!r}")
    if args.expected_template == parent_slug:
        problems.append(
            "site already uses the canonical parent; use deploy-stack-release-to-site.py"
        )
    if problems:
        raise AdoptionError("Target identity refused: " + "; ".join(problems))


def classify(operation, tree):
    if tree["type"] == "absent":
        return "absent"
    expected_type = "file" if operation["kind"] == "file" else "dir"
    if tree["type"] != expected_type or tree["links"]:
        return "incompatible"
    if tree["sha256"] == operation["expected_sha256"] and tree["file_count"] == operation["expected_file_count"]:
        return "identical"
    return "different"


def find_collisions(operations, state, inventory, remote_symbols, local_symbols, local_names, payload_loader_dirs):
    findings = []
    targets = {operation["target"] for operation in operations}
    active_dirs = {plugin.split("/", 1)[0] for plugin in state.get("active_plugins") or []}
    entries = {(e["area"], e["name"]): e for e in inventory["entries"]}

    def add(kind, severity, detail, **extra):
        finding = {"kind": kind, "severity": severity, "detail": detail, **extra}
        if finding not in findings:
            findings.append(finding)

    mu_names = {name for area, name in entries if area == "mu-plugins"}
    plugin_names = {name for area, name in entries if area == "plugins"}
    lock_by_slug = {operation["slug"]: operation for operation in operations}
    for slug in sorted(mu_names & plugin_names):
        operation = lock_by_slug.get(slug)
        locked = operation is not None and operation["runtime_type"] in {"mu-component", "standard-plugin"}
        severity = "blocking" if locked and slug in active_dirs else "advisory"
        add("mu_and_standard_copy", severity,
            f"{slug} exists as wp-content/mu-plugins/{slug} and wp-content/plugins/{slug}"
            + (" (standard copy is active)" if slug in active_dirs else ""))
    for operation in operations:
        slug, runtime_type = operation["slug"], operation["runtime_type"]
        if runtime_type == "mu-component" and slug in plugin_names and slug not in mu_names:
            add("mu_and_standard_copy", "blocking" if slug in active_dirs else "advisory",
                f"install adds MU copy of {slug} while wp-content/plugins/{slug} exists"
                + (" and is active" if slug in active_dirs else ""))
        if runtime_type == "standard-plugin" and slug in mu_names and slug not in plugin_names:
            add("mu_and_standard_copy", "advisory",
                f"install adds standard copy of {slug} while wp-content/mu-plugins/{slug} exists")

    for (area, name), entry in sorted(entries.items()):
        relative = f"wp-content/{area}/{name}"
        if relative in targets:
            continue
        for operation in operations:
            slug = Path(operation["target"]).name.removesuffix(".php")
            if name != slug and re.fullmatch(re.escape(slug) + r"[-._].+", name) and not name.endswith(".zip"):
                loaded = area == "mu-plugins" and entry["type"] == "file" and name.endswith(".php")
                add("same_slug_other_path", "advisory", f"{relative} resembles release slug {operation['slug']}"
                    + (" and is auto-loaded" if loaded else ""))
                break

    for path, name in sorted(inventory["plugin_names"].items()):
        if path in targets or any(path.startswith(target + "/") for target in targets):
            continue
        if name in local_names:
            area, _, rest = path.removeprefix("wp-content/").partition("/")
            loaded = (area == "mu-plugins" and "/" not in rest) or (
                area == "plugins" and rest in (state.get("active_plugins") or [])
            )
            add("same_plugin_name_other_path", "blocking" if loaded else "advisory",
                f"{path} declares Plugin Name {name!r}, also installed at {local_names[name]}"
                + (" (loaded)" if loaded else ""))

    locked_mu = {op["slug"] for op in operations if op["runtime_type"] == "mu-component"}
    legacy_loader = inventory["mu_refs"].get("mrn-loader.php", [])
    for loader, refs in sorted(inventory["mu_refs"].items()):
        if f"wp-content/mu-plugins/{loader}" in targets:
            continue
        for ref in refs:
            if ref["dir"] in locked_mu:
                add("duplicate_loader", "advisory" if ref["once"] else "blocking",
                    f"wp-content/mu-plugins/{loader} also loads {ref['dir']}"
                    + ("" if ref["once"] else " without require_once/include_once"))
    for ref_dir in sorted({ref["dir"] for ref in legacy_loader}):
        has_own_loader = ("mu-plugins", f"{ref_dir}.php") in entries
        if ref_dir not in payload_loader_dirs and ref_dir not in locked_mu and not has_own_loader \
                and ("mu-plugins", ref_dir) in entries:
            add("stops_loading", "advisory",
                f"wp-content/mu-plugins/{ref_dir} is loaded by the legacy mrn-loader.php "
                "but not by the release loader; it will stop loading")

    # WordPress loads MU plugins, then plugins, then the child and parent theme.
    # A redeclaration is safe only when the later-loading declaration is guarded.
    local_rank = {"mu-loader": 0, "mu-component": 0, "standard-plugin": 1}
    remote_rank = {"mu-plugins": 0, "plugins": 1, "themes": 2}
    for local in local_symbols:
        loaded_now = local["runtime_type"] != "standard-plugin" or local["slug"] in active_dirs
        for remote in remote_symbols:
            for name in sorted(local["declared"].keys() & remote["declared"].keys()):
                shown = local["declared"][name]
                if shown != shown.lower():
                    add("function_collision", "advisory",
                        f"function {shown}() appears in {local['slug']} and {remote['path']} "
                        "(mixed-case name; likely inline JavaScript)")
                    continue
                mine = local_rank.get(local["runtime_type"])
                theirs = remote_rank.get(remote["path"].split("/")[1] if "/" in remote["path"] else "")
                if mine is None or theirs is None or mine == theirs:
                    safe = name in local["guarded"] and name in remote["guarded"]
                else:
                    safe = name in (remote["guarded"] if theirs > mine else local["guarded"])
                severity = "advisory" if safe or not loaded_now else "blocking"
                add("function_collision", severity,
                    f"function {shown}() declared by {local['slug']} and {remote['path']}"
                    + ("" if loaded_now else " (only after activation)")
                    + (" (later declaration guarded)" if safe else ""))
    return findings


def build_plan(args, runner, target):
    lock_path = Path(args.release_lock).expanduser().resolve()
    payload = release_lock.validate_lock(deploy.read_json(lock_path))
    artifact_checks = deploy.verify_artifacts(payload, args.artifact_root, lock_path)
    parent_slug, operations, standard_plugins = adoption_operations(payload, args.artifact_root)
    protected = {f"wp-content/themes/{args.expected_template}", f"wp-content/themes/{args.expected_stylesheet}"}
    if protected & {operation["target"] for operation in operations}:
        raise AdoptionError("Release would target the legacy parent or active child theme")

    state = read_site_state(target, runner)
    check_identity(args, state, parent_slug)

    child = next(
        (t for t in payload["themes"] if t.get("deployment_role") == "active-stylesheet-template"),
        None,
    )
    report_paths = [operation["target"] for operation in operations]
    if child:
        report_paths.append(f"wp-content/themes/{child['slug']}")
    trees = read_trees(target, report_paths, runner)
    inventory = read_inventory(target, runner)

    active = state.get("active_plugins") or []
    active_dirs = {plugin.split("/", 1)[0] for plugin in active}
    targets = {operation["target"] for operation in operations}
    scan = sorted(protected)
    for entry in inventory["entries"]:
        relative = f"wp-content/{entry['area']}/{entry['name']}"
        if relative in targets or entry["type"] == "link":
            continue
        if entry["area"] == "mu-plugins" and (
            entry["name"].endswith(".php")
            or ("mu-plugins", entry["name"] + ".php") in {(e["area"], e["name"]) for e in inventory["entries"]}
        ):
            scan.append(relative)
        if entry["area"] == "plugins" and entry["name"] in active_dirs:
            scan.append(relative)
    remote_symbols = read_remote_symbols(target, sorted(set(scan)), runner)
    payload_loader = Path(args.artifact_root).expanduser().resolve() / "mu-plugins/mrn-loader.php"
    loader_dirs = set(MU_REF_RE.findall(payload_loader.read_text(encoding="utf-8"))) if payload_loader.is_file() else set()

    rows = []
    blocking = []
    for operation in operations:
        tree = trees[operation["target"]]
        status = classify(operation, tree)
        action = {"absent": "add", "identical": "skip", "different": "replace"}.get(status, "refuse")
        row = {
            "slug": operation["slug"],
            "runtime_type": operation["runtime_type"],
            "target": operation["target"],
            "kind": operation["kind"],
            "state": status,
            "action": action,
            "expected_sha256": operation["expected_sha256"],
            "target_sha256": tree["sha256"],
            "target_file_count": tree["file_count"],
        }
        if status == "different" and operation["kind"] == "directory":
            source = Path(operation["source"])
            source_files = {path.relative_to(source).as_posix() for path in source.rglob("*") if path.is_file()}
            row["files_removed"] = sorted(set(tree["files"]) - source_files)
        if operation["runtime_type"] == "standard-plugin":
            row["active"] = operation["slug"] in active_dirs
        rows.append(row)
        if status == "incompatible":
            blocking.append(f"{operation['target']} has an incompatible type or symlinks")
        if operation["runtime_type"] == "parent-theme" and status == "different":
            blocking.append(f"{operation['target']} exists and differs from the payload")

    collisions = find_collisions(
        operations, state, inventory, remote_symbols,
        payload_symbols(operations), payload_plugin_names(operations), loader_dirs,
    )
    blocking.extend(f["detail"] for f in collisions if f["severity"] == "blocking")
    remaining = [
        f"wp plugin activate {slug}" for slug in standard_plugins if slug not in active_dirs
    ]
    remaining.append(
        f"switch template {args.expected_template} -> {parent_slug} through the site child deploy"
    )
    hashed = {
        "schema_version": SCHEMA_VERSION,
        "release_id": payload["release_id"],
        "release_lock_sha256": release_lock.file_sha256(lock_path),
        "payload": artifact_checks,
        "site_url": args.site_url,
        "target": {k: target[k] for k in ("hostname", "site_user", "site_root", "ssh_login")},
        "site_state": {k: state.get(k) for k in ("home_url", "environment_type", "template", "stylesheet", "active_plugins")},
        "target_trees": {path: {k: trees[path][k] for k in ("type", "sha256", "file_count")} for path in sorted(trees)},
        "operations": rows,
        "collisions": collisions,
    }
    plan = dict(hashed)
    plan["plan_hash"] = canonical_sha256(hashed)
    plan["child_theme"] = (
        {"slug": child["slug"], "state": trees[f"wp-content/themes/{child['slug']}"]["type"], "action": "not-installed"}
        if child else None
    )
    plan["autoloaded_on_install"] = [
        row["target"] for row in rows
        if row["action"] in {"add", "replace"} and row["runtime_type"] in {"mu-loader", "mu-component"}
    ]
    plan["blocking"] = blocking
    plan["apply_allowed"] = not blocking
    plan["remaining_activation_steps"] = remaining
    plan["template_and_stylesheet_unchanged_by_this_tool"] = True
    return plan, operations


# -------------------------------------------------------------- subcommands


def cmd_plan(args, runner):
    return build_plan(args, runner, resolve_target(args, runner))[0]


def journal_write(path, journal):
    deploy.write_receipt(path, journal)


def cmd_apply(args, runner, now, fetch=None):
    if not re.fullmatch(r"[0-9a-f]{64}", args.plan_hash or ""):
        raise AdoptionError("--plan-hash must be the 64-character plan hash")
    target = resolve_target(args, runner)
    plan, operations = build_plan(args, runner, target)
    if plan["plan_hash"] != args.plan_hash:
        raise AdoptionError("Plan changed since approval; rerun plan and review it")
    if not plan["apply_allowed"]:
        raise AdoptionError("Plan has blocking findings: " + "; ".join(plan["blocking"]))

    stamp = now.strftime("%Y%m%d%H%M%S")
    label = args.backup_label or f"pre-first-adoption-{target['hostname']}-{plan['release_id']}-{stamp}"
    backed_up = resolve_target(args, runner, with_backup=True, backup_label=label)
    if any(backed_up[k] != target[k] for k in ("site_root", "ssh_login")):
        raise AdoptionError("Backup preflight resolved a different target")
    plan_after, _ = build_plan(args, runner, target)
    if plan_after["plan_hash"] != args.plan_hash:
        raise AdoptionError("Target changed during the backup; nothing was written")

    home = deploy.validate_absolute_path(ssh(target, "cd && pwd -P", runner).strip(), "site home")
    backup_dir = f"{home}/{BACKUP_PARENT}/stack-first-adoption-{plan['release_id']}-{stamp}"
    root = target["site_root"]
    if backup_dir == root or backup_dir.startswith(root + "/"):
        raise AdoptionError("File backup directory would be inside the web root")
    rows = {row["target"]: row for row in plan["operations"]}
    work = [operation for operation in operations if rows[operation["target"]]["action"] != "skip"]
    replaced = [o["target"] for o in work if rows[o["target"]]["action"] == "replace"]
    archive = f"{backup_dir}/replaced-paths.tar.gz" if replaced else None
    script = [
        "set -eu", "umask 077",
        f"mkdir -p {shlex.quote(str(Path(backup_dir).parent))}",
        f"mkdir {shlex.quote(backup_dir)}",
    ]
    if archive:
        script += [
            f"tar -czf {shlex.quote(archive)} -C {shlex.quote(root)} -- "
            + " ".join(shlex.quote(path) for path in replaced),
            f"(sha256sum {shlex.quote(archive)} 2>/dev/null || shasum -a 256 {shlex.quote(archive)}) | awk '{{print $1}}'",
        ]
    output = ssh(target, "; ".join(script), runner).strip()
    archive_sha = output.splitlines()[-1] if archive and output else None
    if archive and not re.fullmatch(r"[0-9a-f]{64}", archive_sha or ""):
        raise AdoptionError("File backup did not return a SHA-256")

    journal = {
        "schema_version": SCHEMA_VERSION,
        "kind": "stack-first-adoption",
        "status": "backup-created",
        "created_at": deploy.isoformat(now),
        "plan_hash": plan["plan_hash"],
        "release_id": plan["release_id"],
        "release_lock_sha256": plan["release_lock_sha256"],
        "site_url": args.site_url,
        "target": target,
        "preflight": {
            "discovery_ssh_host": args.discovery_ssh_host,
            "site_user": args.site_user,
            "ssh_alias": args.ssh_alias,
            "expected_site_root": args.expected_site_root,
        },
        "db_backup_label": label,
        "file_backup_dir": backup_dir,
        "file_backup_archive": archive,
        "file_backup_archive_sha256": archive_sha,
        "operations": [
            {
                "slug": o["slug"],
                "target": o["target"],
                "action": rows[o["target"]]["action"],
                "prior_sha256": rows[o["target"]]["target_sha256"],
                "expected_sha256": o["expected_sha256"],
                "status": "pending",
            }
            for o in work
        ],
        "remaining_activation_steps": plan["remaining_activation_steps"],
        "collisions": plan["collisions"],
    }
    journal_write(args.receipt_out, journal)
    runner(["rsync", "-t", str(Path(args.receipt_out).expanduser().resolve()), f"{target['ssh_login']}:{backup_dir}/journal.json"])

    by_target = {entry["target"]: entry for entry in journal["operations"]}
    try:
        for operation in work:
            by_target[operation["target"]]["status"] = "attempted"
            journal_write(args.receipt_out, journal)
            deploy.sync_operation(operation, target["ssh_login"], root, runner)
            by_target[operation["target"]]["status"] = "applied"
        deploy.normalize_permissions(target["ssh_login"], root, [o["target"] for o in work], runner)
        journal["status"] = "applied"
        journal_write(args.receipt_out, journal)
        journal["verification"] = verify_target(args, runner, target, operations, fetch)
    except Exception as error:
        journal["status"] = "apply-failed"
        journal["error"] = str(error)
        journal_write(args.receipt_out, journal)
        raise AdoptionError(f"{error} (journal: {args.receipt_out}; rollback is available)") from error
    journal["status"] = "applied-verified"
    journal_write(args.receipt_out, journal)
    return journal


def http_get(url):
    request = urllib.request.Request(url, headers={"User-Agent": "mrn-stack-first-adoption-verify"})
    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            return response.status, response.read(2_000_000).decode("utf-8", errors="replace")
    except urllib.error.HTTPError as error:
        return error.code, ""


def verify_target(args, runner, target, operations, fetch=None):
    fetch = fetch or http_get
    trees = read_trees(target, [o["target"] for o in operations], runner)
    mismatched = [
        o["target"] for o in operations
        if trees[o["target"]]["sha256"] != o["expected_sha256"]
        or trees[o["target"]]["file_count"] != o["expected_file_count"]
    ]
    if mismatched:
        raise AdoptionError("Installed trees do not match the payload: " + ", ".join(mismatched))
    state = read_site_state(target, runner, skip_themes=False)
    if state.get("template") != args.expected_template or state.get("stylesheet") != args.expected_stylesheet:
        raise AdoptionError("template/stylesheet changed; the site must stay on the legacy parent")
    if LOAD_MARKER not in wp(target, runner, "eval", f"echo '{LOAD_MARKER}';"):
        raise AdoptionError("WP-CLI full load did not complete")
    status, body = fetch(args.site_url)
    if status != 200:
        raise AdoptionError(f"{args.site_url} answered HTTP {status}")
    legacy_paths = [f"/wp-content/themes/{args.expected_template}/", f"/wp-content/themes/{args.expected_stylesheet}/"]
    if not any(path in body for path in legacy_paths):
        raise AdoptionError("Front page does not reference the legacy parent or child theme")
    parent = next(o["target"] for o in operations if o["runtime_type"] == "parent-theme")
    if f"/{parent}/" in body:
        raise AdoptionError("Front page renders assets from the newly installed parent")
    return {
        "status": "verified",
        "installed_paths": len(operations),
        "http_status": status,
        "template": state["template"],
        "stylesheet": state["stylesheet"],
        "wp_cli_full_load": True,
    }


def cmd_verify(args, runner, fetch=None):
    target = resolve_target(args, runner)
    lock_path = Path(args.release_lock).expanduser().resolve()
    payload = release_lock.validate_lock(deploy.read_json(lock_path))
    deploy.verify_artifacts(payload, args.artifact_root, lock_path)
    _parent, operations, standard_plugins = adoption_operations(payload, args.artifact_root)
    result = verify_target(args, runner, target, operations, fetch)
    active = {p.split("/", 1)[0] for p in read_site_state(target, runner)["active_plugins"]}
    result["remaining_activation_steps"] = [
        f"wp plugin activate {slug}" for slug in standard_plugins if slug not in active
    ]
    return result


def cmd_rollback(args, runner, now):
    journal_path = Path(args.journal).expanduser().resolve()
    journal = deploy.read_json(journal_path)
    if journal.get("kind") != "stack-first-adoption" or journal.get("schema_version") != SCHEMA_VERSION:
        raise AdoptionError("Not a first-adoption journal")
    if journal.get("rollback"):
        raise AdoptionError("Journal already records a completed rollback")
    if journal.get("status") not in {"applied", "applied-verified", "apply-failed"}:
        raise AdoptionError(f"Journal status is not rollback-eligible: {journal.get('status')}")
    target = journal["target"]
    expected_confirmation = f"ROLLBACK {target['hostname']} {journal['release_id']}"
    if args.confirm != expected_confirmation:
        raise AdoptionError(f"Rollback requires --confirm {expected_confirmation!r}")
    root = deploy.validate_absolute_path(target["site_root"], "site root")
    entries = journal["operations"]
    for entry in entries:
        relative = Path(entry["target"])
        if relative.is_absolute() or ".." in relative.parts or not entry["target"].startswith("wp-content/"):
            raise AdoptionError(f"Unsafe journal target: {entry['target']}")
    archive = journal.get("file_backup_archive")
    if archive and not archive.startswith(journal["file_backup_dir"] + "/"):
        raise AdoptionError("Journal archive is outside its backup directory")

    pre = journal["preflight"]
    args.site_url = journal["site_url"]
    args.discovery_ssh_host = pre["discovery_ssh_host"]
    args.site_user, args.ssh_alias = pre["site_user"], pre["ssh_alias"]
    args.expected_site_root = pre["expected_site_root"]
    trees = read_trees(target, [e["target"] for e in entries], runner)
    changed = []
    for entry in entries:
        current = trees[entry["target"]]["sha256"]
        allowed = {
            "applied": {entry["expected_sha256"]},
            "pending": {entry["prior_sha256"]},
        }.get(entry["status"])
        if allowed is not None and current not in allowed:
            changed.append(entry["target"])
    if changed:
        raise AdoptionError("Targets changed since apply; refusing rollback: " + ", ".join(changed))

    label = f"pre-first-adoption-rollback-{target['hostname']}-{now.strftime('%Y%m%d%H%M%S')}"
    backed_up = resolve_target(args, runner, with_backup=True, backup_label=label)
    if any(backed_up[k] != target[k] for k in ("site_root", "ssh_login")):
        raise AdoptionError("Rollback preflight resolved a different target")

    touched = [e for e in entries if e["status"] != "pending"]
    restore = [e["target"] for e in touched if e["action"] == "replace"]
    script = ["set -eu"]
    if restore:
        script += [
            f"test -f {shlex.quote(archive)}",
            f"test \"$((sha256sum {shlex.quote(archive)} 2>/dev/null || shasum -a 256 {shlex.quote(archive)}) | awk '{{print $1}}')\" = "
            f"{shlex.quote(journal['file_backup_archive_sha256'])}",
        ]
    for entry in touched:
        script.append(f"rm -rf {shlex.quote(root + '/' + entry['target'])}")
    if restore:
        script.append(
            f"tar -xzf {shlex.quote(archive)} -C {shlex.quote(root)} -- "
            + " ".join(shlex.quote(path) for path in restore)
        )
    ssh(target, "; ".join(script), runner)
    after = read_trees(target, [e["target"] for e in entries], runner)
    wrong = [e["target"] for e in entries if after[e["target"]]["sha256"] != e["prior_sha256"]]
    if wrong:
        raise AdoptionError("Rollback did not restore: " + ", ".join(wrong))
    journal["rollback"] = {
        "status": "verified-restored",
        "restored_at": deploy.isoformat(now),
        "pre_rollback_backup_label": label,
        "restored": restore,
        "removed": [e["target"] for e in touched if e["action"] == "add"],
    }
    journal_write(journal_path, journal)
    return journal["rollback"]


def build_parser():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = parser.add_subparsers(dest="command", required=True)

    def site_args(p):
        p.add_argument("--release-lock", required=True, type=Path)
        p.add_argument("--artifact-root", required=True, type=Path)
        p.add_argument("--site-url", required=True)
        p.add_argument("--expected-environment", required=True)
        p.add_argument("--expected-template", required=True)
        p.add_argument("--expected-stylesheet", required=True)
        p.add_argument("--expected-site-root", required=True)
        p.add_argument("--discovery-ssh-host", default="mrndev")
        p.add_argument("--site-user")
        p.add_argument("--ssh-alias")
        p.add_argument("--receipt-out", type=Path)

    for name in ("plan", "apply", "verify"):
        p = sub.add_parser(name)
        site_args(p)
        if name == "apply":
            p.add_argument("--plan-hash", required=True)
            p.add_argument("--backup-label")
    p = sub.add_parser("rollback")
    p.add_argument("--journal", required=True, type=Path)
    p.add_argument("--confirm", required=True, help="ROLLBACK <hostname> <release_id>")
    p.add_argument("--receipt-out", type=Path)
    return parser


def main(argv=None, runner=deploy.run_checked, now=None, fetch=None):
    args = build_parser().parse_args(argv)
    now = now or deploy.utc_now()
    try:
        if args.command != "rollback":
            parsed = urlsplit(args.site_url)
            if parsed.scheme != "https" or not parsed.hostname or parsed.query or parsed.fragment:
                raise AdoptionError("--site-url must be an exact https URL")
            deploy.validate_slug(args.expected_template, "expected template")
            deploy.validate_slug(args.expected_stylesheet, "expected stylesheet")
            deploy.validate_absolute_path(args.expected_site_root, "expected site root")
        if args.command == "plan":
            result = cmd_plan(args, runner)
        elif args.command == "apply":
            if not args.receipt_out:
                raise AdoptionError("apply requires --receipt-out (the rollback journal)")
            result = cmd_apply(args, runner, now, fetch)
        elif args.command == "verify":
            result = cmd_verify(args, runner, fetch)
        else:
            result = cmd_rollback(args, runner, now)
    except (AdoptionError, deploy.DeployError, release_lock.ReleaseLockError) as error:
        print(f"ERROR: {error}", file=sys.stderr)
        return 1
    if args.receipt_out and args.command != "apply":
        deploy.write_receipt(args.receipt_out, {"command": args.command, "result": result})
    print(json.dumps(result, indent=2, default=sorted))
    if args.command == "plan":
        print(f"PLAN_HASH={result['plan_hash']}")
        print(f"APPLY_ALLOWED={'yes' if result['apply_allowed'] else 'no'}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
