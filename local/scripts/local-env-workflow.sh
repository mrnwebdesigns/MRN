#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  local-env-workflow.sh pull \
    [<site-hostname>] \
    [<local-site-path>] \
    [--site-hostname <site-hostname>] \
    [--local-site-path </absolute/local/site/path>] \
    [--sync-runtime] \
    [--no-sync-runtime] \
    [--local-api-auto-create] \
    [--no-local-api-auto-create] \
    [--snapshot-if-missing] \
    [--snapshot-root <path>] \
    [--local-home-url <local-home-url>] \
    [--local-sites-root <path>] \
    [--map-file <path>] \
    [--discovery-ssh-host <ssh-host>] \
    [--skip-db] \
    [--skip-uploads] \
    [--dry-run]

  local-env-workflow.sh deploy \
    [<site-hostname>] \
    [<local-site-path>] \
    [--site-hostname <site-hostname>] \
    [--local-site-path </absolute/local/site/path>] \
    [--deploy-scope site|stack] \
    [--local-home-url <local-home-url>] \
    [--local-sites-root <path>] \
    [--map-file <path>] \
    [--discovery-ssh-host <ssh-host>] \
    [--skip-backup] \
    [--skip-db] \
    [--skip-uploads] \
    [--delete-uploads] \
    [--yes] \
    [--dry-run]

Description:
  Adds a Local-first pull/deploy workflow for MRN sites using canonical site-owner
  SSH resolution and preflight helpers.

  - pull:
    - resolves the live site owner via resolve-live-site-owner.sh
    - starts halted Local sites automatically (when Local GraphQL is available)
    - can sync runtime code surfaces (themes/plugins/mu-plugins) from live site
      into Local before DB/media pull (auto for Local API-resolved sites)
    - pulls uploads and/or database into Local
    - rewrites imported DB URLs back to the local home URL
    - when local-site-path cannot be resolved, attempts Local app GraphQL
      auto-create/reuse for <site-hostname-without-.mrndev.io>.local
    - with --snapshot-if-missing, if no Local path is resolvable, uses snapshot mode:
      uploads + DB export files are saved to <snapshot-root>/<site-hostname>/

  - deploy:
    - runs preflight-live-site-deploy.sh (owner verify + backup by default)
    - prompts for deploy scope if not provided
    - deploy scope "site": pushes local uploads/database (site content/config)
    - deploy scope "stack": pushes canonical repo stack code surfaces
      (stack theme + plugins + mu-plugins + shared runtime)

Notes:
  - This helper is intentionally scoped to one site at a time.
  - If local-site-path is omitted, it is resolved from:
    1) map file entry (hostname|path)
    2) <local-sites-root>/<hostname>/app/public
    3) <local-sites-root>/<hostname-without-.mrndev.io>/app/public
    4) Local app GraphQL auto-create/reuse (pull only, when enabled)
  - Snapshot mode does not import into Local DB; it stores artifacts on disk.
  - Deploy writes are blocked until explicit confirmation unless --yes is passed.
  - --dry-run previews rsync actions and skips database write operations.
EOF
}

fail() {
	echo "FAIL: $*" >&2
	exit 1
}

note() {
	echo "$*" >&2
}

COMMAND="${1:-}"
if [[ -z "${COMMAND}" || "${COMMAND}" == "-h" || "${COMMAND}" == "--help" ]]; then
	usage
	exit 0
fi
shift || true

if [[ "${COMMAND}" != "pull" && "${COMMAND}" != "deploy" ]]; then
	echo "Unknown command: ${COMMAND}" >&2
	usage >&2
	exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
STACK_SCRIPT_DIR="${REPO_ROOT}/stack/scripts"
RESOLVE_SCRIPT="${STACK_SCRIPT_DIR}/resolve-live-site-owner.sh"
PREFLIGHT_SCRIPT="${STACK_SCRIPT_DIR}/preflight-live-site-deploy.sh"
DEPLOY_THEME_SCRIPT="${STACK_SCRIPT_DIR}/deploy-live-theme.sh"

SITE_HOSTNAME=""
LOCAL_SITE_PATH=""
LOCAL_SITE_PATH_FROM_USER=0
LOCAL_HOME_URL=""
LOCAL_SITES_ROOT="${MRN_LOCAL_SITES_ROOT:-/Users/khofmeyer/Local Sites}"
MAP_FILE="${MRN_LOCAL_SITE_MAP_FILE:-${REPO_ROOT}/local/configs/local-site-map.mrndev.io.txt}"
SNAPSHOT_ROOT="${MRN_PULL_SNAPSHOT_ROOT:-${REPO_ROOT}/.tmp/mrndev-snapshots}"
SNAPSHOT_MODE=0
SNAPSHOT_IF_MISSING=0
SNAPSHOT_SITE_ROOT=""
SNAPSHOT_UPLOADS_PATH=""
SNAPSHOT_DB_DIR=""
DISCOVERY_SSH_HOST="mrndev"
LOCAL_API_AUTO_CREATE="${MRN_LOCAL_API_AUTO_CREATE:-1}"
LOCAL_GRAPHQL_INFO_FILE="${MRN_LOCAL_GRAPHQL_INFO_FILE:-${HOME}/Library/Application Support/Local/graphql-connection-info.json}"
LOCAL_API_WAIT_TIMEOUT="${MRN_LOCAL_API_WAIT_TIMEOUT:-240}"
LOCAL_API_POLL_INTERVAL="${MRN_LOCAL_API_POLL_INTERVAL:-2}"
FILTER_WPCLI_DEPRECATED="${MRN_FILTER_WPCLI_DEPRECATED:-1}"
PULL_SYNC_RUNTIME_MODE="${MRN_PULL_SYNC_RUNTIME_MODE:-auto}"
LOCAL_AUTO_CREATED_SITE=0
LOCAL_API_RESOLVED_SITE=0
LOCAL_AUTO_CREATED_SITE_URL=""
LOCAL_AUTO_CREATED_SITE_DOMAIN=""
LOCAL_AUTO_CREATE_ERROR=""
LOCAL_SITE_ID=""
LOCAL_DB_SOCKET=""
DEPLOY_SCOPE=""
SKIP_BACKUP=0
SKIP_DB=0
SKIP_UPLOADS=0
DELETE_UPLOADS=0
DRY_RUN=0
YES=0
POSITIONAL_ARGS=()

while [[ $# -gt 0 ]]; do
	case "$1" in
		--site-hostname)
			SITE_HOSTNAME="${2:-}"
			shift 2
			;;
		--local-site-path)
			LOCAL_SITE_PATH="${2:-}"
			LOCAL_SITE_PATH_FROM_USER=1
			shift 2
			;;
		--snapshot-root)
			SNAPSHOT_ROOT="${2:-}"
			shift 2
			;;
		--local-api-auto-create)
			LOCAL_API_AUTO_CREATE=1
			shift
			;;
		--no-local-api-auto-create)
			LOCAL_API_AUTO_CREATE=0
			shift
			;;
		--sync-runtime)
			PULL_SYNC_RUNTIME_MODE="always"
			shift
			;;
		--no-sync-runtime)
			PULL_SYNC_RUNTIME_MODE="never"
			shift
			;;
		--snapshot-if-missing)
			SNAPSHOT_IF_MISSING=1
			shift
			;;
		--local-home-url)
			LOCAL_HOME_URL="${2:-}"
			shift 2
			;;
		--local-sites-root)
			LOCAL_SITES_ROOT="${2:-}"
			shift 2
			;;
		--map-file)
			MAP_FILE="${2:-}"
			shift 2
			;;
		--discovery-ssh-host)
			DISCOVERY_SSH_HOST="${2:-}"
			shift 2
			;;
		--deploy-scope)
			DEPLOY_SCOPE="${2:-}"
			shift 2
			;;
		--skip-backup)
			SKIP_BACKUP=1
			shift
			;;
		--skip-db)
			SKIP_DB=1
			shift
			;;
		--skip-uploads)
			SKIP_UPLOADS=1
			shift
			;;
		--delete-uploads)
			DELETE_UPLOADS=1
			shift
			;;
		--dry-run)
			DRY_RUN=1
			shift
			;;
		--yes)
			YES=1
			shift
			;;
		-h|--help)
			usage
			exit 0
			;;
		--)
			shift
			while [[ $# -gt 0 ]]; do
				POSITIONAL_ARGS+=("$1")
				shift
			done
			;;
		-*)
			echo "Unknown argument: $1" >&2
			usage >&2
			exit 1
			;;
		*)
			POSITIONAL_ARGS+=("$1")
			shift
			;;
	esac
done

if [[ -z "${SITE_HOSTNAME}" && ${#POSITIONAL_ARGS[@]} -ge 1 ]]; then
	SITE_HOSTNAME="${POSITIONAL_ARGS[0]}"
fi

if [[ -z "${LOCAL_SITE_PATH}" && ${#POSITIONAL_ARGS[@]} -ge 2 ]]; then
	LOCAL_SITE_PATH="${POSITIONAL_ARGS[1]}"
	LOCAL_SITE_PATH_FROM_USER=1
fi

if [[ ${#POSITIONAL_ARGS[@]} -gt 2 ]]; then
	fail "Too many positional arguments. Expected: [site-hostname] [local-site-path]"
fi

if [[ "${COMMAND}" == "pull" ]]; then
	if [[ -n "${DEPLOY_SCOPE}" ]]; then
		fail "--deploy-scope is only valid for deploy."
	fi
	if [[ "${SKIP_BACKUP}" -eq 1 ]]; then
		fail "--skip-backup is only valid for deploy."
	fi
	if [[ "${YES}" -eq 1 ]]; then
		fail "--yes is only valid for deploy."
	fi
	if [[ "${DELETE_UPLOADS}" -eq 1 ]]; then
		fail "--delete-uploads is only valid for deploy."
	fi
fi

if [[ "${COMMAND}" == "deploy" && -n "${DEPLOY_SCOPE}" ]]; then
	if [[ "${DEPLOY_SCOPE}" != "site" && "${DEPLOY_SCOPE}" != "stack" ]]; then
		fail "--deploy-scope must be one of: site, stack"
	fi
fi

if [[ "${PULL_SYNC_RUNTIME_MODE}" != "auto" && "${PULL_SYNC_RUNTIME_MODE}" != "always" && "${PULL_SYNC_RUNTIME_MODE}" != "never" ]]; then
	fail "Invalid runtime sync mode '${PULL_SYNC_RUNTIME_MODE}'. Use auto, always, or never."
fi

for required in bash rsync sed ssh tr awk sort; do
	command -v "${required}" >/dev/null 2>&1 || fail "Required command not found: ${required}"
done

[[ -x "${RESOLVE_SCRIPT}" ]] || fail "Missing resolve helper: ${RESOLVE_SCRIPT}"
[[ -x "${PREFLIGHT_SCRIPT}" ]] || fail "Missing preflight helper: ${PREFLIGHT_SCRIPT}"
[[ -x "${DEPLOY_THEME_SCRIPT}" ]] || fail "Missing live theme helper: ${DEPLOY_THEME_SCRIPT}"

lookup_local_path_from_map() {
	local site_hostname="$1"
	local line=""
	local key=""
	local value=""

	[[ -f "${MAP_FILE}" ]] || return 1

	while IFS= read -r line; do
		line="${line#"${line%%[![:space:]]*}"}"
		line="${line%"${line##*[![:space:]]}"}"

		[[ -n "${line}" ]] || continue
		[[ "${line}" == \#* ]] && continue
		[[ "${line}" == *"|"* ]] || continue

		key="${line%%|*}"
		value="${line#*|}"
		key="${key#"${key%%[![:space:]]*}"}"
		key="${key%"${key##*[![:space:]]}"}"
		value="${value#"${value%%[![:space:]]*}"}"
		value="${value%"${value##*[![:space:]]}"}"

		if [[ "${key}" == "${site_hostname}" ]]; then
			printf '%s\n' "${value}"
			return 0
		fi
	done < "${MAP_FILE}"

	return 1
}

resolve_local_site_path_auto() {
	local site_hostname="$1"
	local mapped=""
	local base_name=""
	local candidate1=""
	local candidate2=""

	mapped="$(lookup_local_path_from_map "${site_hostname}" || true)"
	if [[ -n "${mapped}" ]]; then
		printf '%s\n' "${mapped}"
		return 0
	fi

	base_name="${site_hostname%.mrndev.io}"
	candidate1="${LOCAL_SITES_ROOT}/${site_hostname}/app/public"
	candidate2="${LOCAL_SITES_ROOT}/${base_name}/app/public"

	if [[ -d "${candidate1}" ]]; then
		printf '%s\n' "${candidate1}"
		return 0
	fi

	if [[ -d "${candidate2}" ]]; then
		printf '%s\n' "${candidate2}"
		return 0
	fi

	return 1
}

parse_local_api_kv_output() {
	local raw="$1"
	local line
	local key
	local value

	while IFS= read -r line; do
		[[ -n "${line}" ]] || continue
		key="${line%%=*}"
		value="${line#*=}"

		case "${key}" in
			LOCAL_PATH)
				LOCAL_SITE_PATH="${value}"
				LOCAL_API_RESOLVED_SITE=1
				;;
			LOCAL_URL)
				LOCAL_AUTO_CREATED_SITE_URL="${value}"
				;;
			LOCAL_DOMAIN)
				LOCAL_AUTO_CREATED_SITE_DOMAIN="${value}"
				;;
			LOCAL_ACTION)
				if [[ "${value}" == "created" ]]; then
					LOCAL_AUTO_CREATED_SITE=1
				fi
				;;
			LOCAL_NOTE)
				note "${value}"
				;;
		esac
	done <<< "${raw}"
}

ensure_local_site_running_for_pull() {
	local output
	local line
	local key
	local value

	[[ "${COMMAND}" == "pull" ]] || return 0
	[[ "${SNAPSHOT_MODE}" -eq 0 ]] || return 0
	[[ -n "${LOCAL_WP_PATH}" ]] || return 0
	[[ -f "${LOCAL_GRAPHQL_INFO_FILE}" ]] || return 0

	command -v python3 >/dev/null 2>&1 || return 0
	command -v curl >/dev/null 2>&1 || return 0

	output="$(
		python3 - "${LOCAL_GRAPHQL_INFO_FILE}" "${LOCAL_WP_PATH}" "${LOCAL_API_WAIT_TIMEOUT}" "${LOCAL_API_POLL_INTERVAL}" <<'PY'
import json
import os
import pathlib
import subprocess
import sys
import time

info_file = os.path.expanduser(sys.argv[1].strip())
local_wp_path = os.path.normpath(os.path.abspath(os.path.expanduser(sys.argv[2].strip())))
wait_timeout = max(int(sys.argv[3]), 1)
poll_interval = max(float(sys.argv[4]), 0.5)

def emit(message):
    print(f"LOCAL_NOTE={message}")

def normalize(path):
    return os.path.normpath(os.path.abspath(os.path.expanduser(path)))

def gql(endpoint, token, query, variables=None):
    payload = {"query": query}
    if variables is not None:
        payload["variables"] = variables
    proc = subprocess.run(
        [
            "curl", "-sS", "-X", "POST", endpoint,
            "-H", "Content-Type: application/json",
            "-H", f"Authorization: Bearer {token}",
            "--data-binary", json.dumps(payload),
        ],
        capture_output=True,
        text=True,
    )
    if proc.returncode != 0:
        stderr = (proc.stderr or "").strip() or "curl error"
        raise RuntimeError(f"Local GraphQL request failed: {stderr}")

    try:
        response = json.loads(proc.stdout)
    except Exception:
        raise RuntimeError("Local GraphQL returned non-JSON output.")

    if response.get("errors"):
        messages = "; ".join((err.get("message") or "unknown error") for err in response["errors"])
        raise RuntimeError(f"Local GraphQL error: {messages}")

    return response.get("data") or {}

if not os.path.isfile(info_file):
    raise SystemExit(0)

try:
    info = json.loads(pathlib.Path(info_file).read_text())
except Exception:
    raise SystemExit(0)

endpoint = info.get("url")
token = info.get("authToken")
if not endpoint or not token:
    raise SystemExit(0)

paths = [local_wp_path]
if local_wp_path.endswith("/app/public"):
    paths.append(os.path.normpath(local_wp_path[:-len("/app/public")]))
if local_wp_path.endswith("/public"):
    paths.append(os.path.normpath(local_wp_path[:-len("/public")]))

seen = set()
candidate_paths = []
for item in paths:
    normalized = normalize(item)
    if normalized not in seen:
        seen.add(normalized)
        candidate_paths.append(normalized)

def fetch_sites():
    data = gql(endpoint, token, "query { sites { id name domain path status } }")
    return data.get("sites") or []

def find_site_by_path(sites):
    for site in sites:
        site_path = normalize((site or {}).get("path") or "")
        if site_path in candidate_paths:
            return site
    return None

sites = fetch_sites()
site = find_site_by_path(sites)
if site is None:
    raise SystemExit(0)

site_id = (site.get("id") or "").strip()
site_status = (site.get("status") or "").strip().lower()
site_label = (site.get("domain") or site.get("name") or site_id or "local site").strip()

if site_status == "running" or not site_id:
    raise SystemExit(0)

emit(f"Local site '{site_label}' is {site_status or 'not running'}; starting before pull import.")
gql(endpoint, token, "mutation($id: ID!){ startSite(id: $id){ id status } }", {"id": site_id})

deadline = time.time() + wait_timeout
last_status = site_status
while time.time() <= deadline:
    sites = fetch_sites()
    current = None
    for item in sites:
        if ((item or {}).get("id") or "").strip() == site_id:
            current = item
            break
    if current is None:
        raise RuntimeError(f"Local site '{site_label}' was not found after start.")

    current_status = (current.get("status") or "").strip().lower()
    if current_status and current_status != last_status:
        emit(f"Local site status: {current_status}")
        last_status = current_status
    if current_status == "running":
        raise SystemExit(0)
    if current_status in {"errored", "error"}:
        raise RuntimeError(
            f"Local site '{site_label}' entered status '{current_status}' while starting."
        )
    time.sleep(poll_interval)

raise RuntimeError(
    f"Timed out waiting for Local site '{site_label}' to reach running status ({wait_timeout}s)."
)
PY
	)" || fail "Could not ensure Local site is running before pull. Open Local and start the site, then rerun pull-site."

	while IFS= read -r line; do
		[[ -n "${line}" ]] || continue
		key="${line%%=*}"
		value="${line#*=}"
		if [[ "${key}" == "LOCAL_NOTE" ]]; then
			note "${value}"
		fi
	done <<< "${output}"
}

attempt_local_api_site_prepare() {
	local output

	LOCAL_AUTO_CREATE_ERROR=""

	if [[ "${LOCAL_API_AUTO_CREATE}" -ne 1 ]]; then
		LOCAL_AUTO_CREATE_ERROR="Local API auto-create is disabled."
		return 1
	fi

	if [[ "${COMMAND}" != "pull" ]]; then
		LOCAL_AUTO_CREATE_ERROR="Local API auto-create is only available for pull."
		return 1
	fi

	if [[ "${SNAPSHOT_IF_MISSING}" -eq 1 ]]; then
		LOCAL_AUTO_CREATE_ERROR="Snapshot fallback was requested."
		return 1
	fi

	command -v python3 >/dev/null 2>&1 || {
		LOCAL_AUTO_CREATE_ERROR="python3 is required for Local API auto-create."
		return 1
	}
	command -v curl >/dev/null 2>&1 || {
		LOCAL_AUTO_CREATE_ERROR="curl is required for Local API auto-create."
		return 1
	}

	output="$(
		python3 - "${SITE_HOSTNAME}" "${LOCAL_SITES_ROOT}" "${LOCAL_GRAPHQL_INFO_FILE}" "${LOCAL_API_WAIT_TIMEOUT}" "${LOCAL_API_POLL_INTERVAL}" <<'PY'
import json
import os
import pathlib
import re
import subprocess
import sys
import time

site_hostname = sys.argv[1].strip()
local_sites_root = os.path.expanduser(sys.argv[2].strip())
info_file = os.path.expanduser(sys.argv[3].strip())
wait_timeout = int(sys.argv[4])
poll_interval = float(sys.argv[5])

def emit(key, value):
    print(f"{key}={value}")

def fail(message, code=1):
    emit("LOCAL_ERROR", message)
    raise SystemExit(code)

if not os.path.isfile(info_file):
    fail(f"Local connection info not found at {info_file}", 2)

try:
    info = json.loads(pathlib.Path(info_file).read_text())
except Exception as exc:
    fail(f"Failed to parse Local connection info: {exc}", 3)

endpoint = info.get("url")
token = info.get("authToken")
if not endpoint or not token:
    fail("Local connection info is missing url/authToken.", 4)

def gql(query, variables=None):
    payload = {"query": query}
    if variables is not None:
        payload["variables"] = variables
    proc = subprocess.run(
        [
            "curl", "-sS", "-X", "POST", endpoint,
            "-H", "Content-Type: application/json",
            "-H", f"Authorization: Bearer {token}",
            "--data-binary", json.dumps(payload),
        ],
        capture_output=True,
        text=True,
    )
    if proc.returncode != 0:
        stderr = (proc.stderr or "").strip()
        fail(f"Local GraphQL request failed: {stderr or 'curl error'}", 5)
    try:
        data = json.loads(proc.stdout)
    except Exception:
        fail("Local GraphQL returned non-JSON output.", 6)
    if data.get("errors"):
        messages = "; ".join((err.get("message") or "unknown error") for err in data["errors"])
        fail(f"Local GraphQL error: {messages}", 7)
    return data.get("data") or {}

def expand_local_path(raw):
    if not raw:
        return ""
    expanded = os.path.expanduser(raw)
    return os.path.abspath(expanded)

def find_site_by_domain_or_path(sites, domain, expected_path):
    expected = os.path.normpath(expected_path)
    for site in sites:
        site_domain = (site.get("domain") or "").strip()
        site_path = os.path.normpath(expand_local_path(site.get("path") or ""))
        if site_domain == domain:
            return site
        if site_path == expected:
            return site
    return None

def fetch_sites():
    response = gql("query { sites { id name domain path status url } }")
    return response.get("sites") or []

base_name = site_hostname
suffix = ".mrndev.io"
if site_hostname.endswith(suffix):
    base_name = site_hostname[: -len(suffix)]
base_name = base_name.strip().strip(".")
if not base_name:
    fail(f"Could not derive local site name from hostname '{site_hostname}'.", 8)

domain = f"{base_name}.local"
path = os.path.join(local_sites_root, base_name)
name = re.sub(r"[^A-Za-z0-9._-]+", "-", base_name)
if not name:
    name = "mrn-site"
admin_email_slug = re.sub(r"[^A-Za-z0-9._-]+", "-", base_name).strip("-") or "mrn-site"
admin_email = f"dev+{admin_email_slug}@mrn.local"

sites = fetch_sites()
site = find_site_by_domain_or_path(sites, domain, path)
action = "reused"

if site is None:
    action = "created"
    mutation = "mutation($input:AddSiteInput!){ addSite(input:$input){ id status } }"
    gql(mutation, {
        "input": {
            "name": name,
            "path": path,
            "domain": domain,
            "environment": "preferred",
            "wpAdminUsername": "admin",
            "wpAdminPassword": "admin",
            "wpAdminEmail": admin_email,
            "goToSite": False,
        }
    })

deadline = time.time() + max(wait_timeout, 1)
last_status = ""
final_site = None
while time.time() <= deadline:
    sites = fetch_sites()
    site = find_site_by_domain_or_path(sites, domain, path)
    if site is not None:
        current_status = (site.get("status") or "").strip().lower()
        if current_status != last_status and current_status:
            emit("LOCAL_NOTE", f"Local site status: {current_status}")
            last_status = current_status
        if current_status in {"running", "halted"}:
            final_site = site
            break
    time.sleep(max(poll_interval, 0.5))

if final_site is None:
    fail(
        f"Timed out waiting for Local site '{domain}' to become ready "
        f"(expected running or halted within {wait_timeout}s).",
        9,
    )

site_path = expand_local_path(final_site.get("path") or path)
local_wp_path = os.path.join(site_path, "app", "public")

emit("LOCAL_ACTION", action)
emit("LOCAL_DOMAIN", domain)
emit("LOCAL_URL", (final_site.get("url") or f"http://{domain}").strip())
emit("LOCAL_PATH", local_wp_path)
emit("LOCAL_NOTE", f"Local site ready at {site_path} ({domain}).")
PY
	)" || {
		if [[ -z "${output}" ]]; then
			LOCAL_AUTO_CREATE_ERROR="Local API auto-create failed."
		else
			LOCAL_AUTO_CREATE_ERROR="$(printf '%s\n' "${output}" | awk -F= '/^LOCAL_ERROR=/{print $2}' | tail -n 1)"
			if [[ -z "${LOCAL_AUTO_CREATE_ERROR}" ]]; then
				LOCAL_AUTO_CREATE_ERROR="Local API auto-create failed."
			fi
		fi
		return 1
	}

	parse_local_api_kv_output "${output}"
	if [[ -z "${LOCAL_SITE_PATH}" ]]; then
		LOCAL_AUTO_CREATE_ERROR="Local API did not return a local path."
		return 1
	fi

	return 0
}

enable_snapshot_mode() {
	local reason="${1:-}"
	local safe_name

	safe_name="$(printf '%s' "${SITE_HOSTNAME}" | tr -c '[:alnum:]._-' '-')"
	SNAPSHOT_SITE_ROOT="${SNAPSHOT_ROOT}/${safe_name}"
	SNAPSHOT_UPLOADS_PATH="${SNAPSHOT_SITE_ROOT}/uploads"
	SNAPSHOT_DB_DIR="${SNAPSHOT_SITE_ROOT}/db"
	SNAPSHOT_MODE=1

	if [[ -n "${reason}" ]]; then
		note "Local path unavailable (${reason}); using snapshot mode at ${SNAPSHOT_SITE_ROOT}."
	else
		note "Local path unavailable; using snapshot mode at ${SNAPSHOT_SITE_ROOT}."
	fi
}

[[ -n "${SITE_HOSTNAME}" ]] || fail "Site hostname is required. Use --site-hostname or positional argument."

if [[ -z "${LOCAL_SITE_PATH}" ]]; then
	LOCAL_SITE_PATH="$(resolve_local_site_path_auto "${SITE_HOSTNAME}" || true)"
fi

if [[ -z "${LOCAL_SITE_PATH}" ]]; then
	if [[ "${COMMAND}" == "pull" && "${SNAPSHOT_IF_MISSING}" -eq 0 ]]; then
		if attempt_local_api_site_prepare; then
			note "Resolved local site path via Local app API: ${LOCAL_SITE_PATH}"
		elif [[ -n "${LOCAL_AUTO_CREATE_ERROR}" ]]; then
			note "Local app auto-create unavailable: ${LOCAL_AUTO_CREATE_ERROR}"
		fi
	fi

	if [[ -z "${LOCAL_SITE_PATH}" && "${COMMAND}" == "pull" && "${SNAPSHOT_IF_MISSING}" -eq 1 ]]; then
		enable_snapshot_mode "no mapping/path for ${SITE_HOSTNAME}"
	elif [[ -z "${LOCAL_SITE_PATH}" ]]; then
		fail "Could not resolve local site path for ${SITE_HOSTNAME}. Use --local-site-path or add a mapping in ${MAP_FILE}."
	fi
fi

if [[ "${SNAPSHOT_MODE}" -eq 0 && ! -d "${LOCAL_SITE_PATH}" ]]; then
	if [[ "${COMMAND}" == "pull" && "${SNAPSHOT_IF_MISSING}" -eq 0 && "${LOCAL_SITE_PATH_FROM_USER}" -eq 0 ]]; then
		if attempt_local_api_site_prepare; then
			note "Resolved missing local path via Local app API: ${LOCAL_SITE_PATH}"
		elif [[ -n "${LOCAL_AUTO_CREATE_ERROR}" ]]; then
			note "Local app auto-create unavailable: ${LOCAL_AUTO_CREATE_ERROR}"
		fi
	fi

	if [[ "${SNAPSHOT_MODE}" -eq 0 && ! -d "${LOCAL_SITE_PATH}" && "${COMMAND}" == "pull" && "${SNAPSHOT_IF_MISSING}" -eq 1 && "${LOCAL_SITE_PATH_FROM_USER}" -eq 0 ]]; then
		enable_snapshot_mode "resolved path missing (${LOCAL_SITE_PATH})"
	elif [[ "${SNAPSHOT_MODE}" -eq 0 && ! -d "${LOCAL_SITE_PATH}" ]]; then
		fail "Local site path not found: ${LOCAL_SITE_PATH}"
	fi
fi

LOCAL_WP_PATH=""
SITE_USER=""
SITE_ROOT=""
SSH_ALIAS=""
SSH_LOGIN=""
BACKUP_LABEL=""
WP_BIN=""

resolve_wp_path() {
	local base="$1"

	if [[ -f "${base}/wp-config.php" ]]; then
		printf '%s' "${base}"
		return 0
	fi
	if [[ -f "${base}/public/wp-config.php" ]]; then
		printf '%s/public' "${base}"
		return 0
	fi
	if [[ -f "${base}/app/public/wp-config.php" ]]; then
		printf '%s/app/public' "${base}"
		return 0
	fi

	return 1
}

if [[ "${SNAPSHOT_MODE}" -eq 0 ]]; then
	if ! LOCAL_WP_PATH="$(resolve_wp_path "${LOCAL_SITE_PATH}")"; then
		fail "Could not find wp-config.php in ${LOCAL_SITE_PATH} (checked root/public/app/public)."
	fi
fi

detect_local_wp_bin() {
	local local_wp
	local_wp="/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp"

	if [[ -x "${local_wp}" ]]; then
		WP_BIN="${local_wp}"
		return
	fi

	if command -v wp >/dev/null 2>&1; then
		WP_BIN="$(command -v wp)"
		return
	fi

	fail "Could not find wp-cli in PATH or Local bundle."
}

resolve_local_site_db_socket() {
	local sites_json
	local output

	LOCAL_SITE_ID=""
	LOCAL_DB_SOCKET=""

	if [[ "${SNAPSHOT_MODE}" -ne 0 || -z "${LOCAL_WP_PATH}" ]]; then
		return 0
	fi

	sites_json="${HOME}/Library/Application Support/Local/sites.json"
	[[ -f "${sites_json}" ]] || return 0

	output="$(
		python3 - "${sites_json}" "${LOCAL_WP_PATH}" <<'PY'
import json
import os
import pathlib
import sys

sites_path = pathlib.Path(sys.argv[1])
local_wp_path = os.path.normpath(sys.argv[2])

candidates = [local_wp_path]
if local_wp_path.endswith("/app/public"):
    candidates.append(os.path.normpath(local_wp_path[:-len("/app/public")]))
if local_wp_path.endswith("/public"):
    candidates.append(os.path.normpath(local_wp_path[:-len("/public")]))

# Preserve order while de-duping.
seen = set()
ordered = []
for path in candidates:
    if path and path not in seen:
        seen.add(path)
        ordered.append(path)

try:
    raw = json.loads(sites_path.read_text())
except Exception:
    raise SystemExit(0)

sites = raw.values() if isinstance(raw, dict) else raw
match = None
for site in sites:
    site_path = os.path.normpath(os.path.expanduser((site or {}).get("path") or ""))
    if site_path in ordered:
        match = site
        break

if not match:
    raise SystemExit(0)

site_id = (match.get("id") or "").strip()
if not site_id:
    raise SystemExit(0)

socket_path = str(pathlib.Path.home() / "Library/Application Support/Local/run" / site_id / "mysql" / "mysqld.sock")

print(f"LOCAL_SITE_ID={site_id}")
print(f"LOCAL_DB_SOCKET={socket_path}")
PY
	)" || true

	while IFS='=' read -r key value; do
		case "${key}" in
			LOCAL_SITE_ID) LOCAL_SITE_ID="${value}" ;;
			LOCAL_DB_SOCKET) LOCAL_DB_SOCKET="${value}" ;;
		esac
	done <<< "${output}"
}

ensure_local_wp_cli_socket_db_host() {
	local wp_config

	[[ -n "${LOCAL_DB_SOCKET}" ]] || return 0
	[[ -S "${LOCAL_DB_SOCKET}" ]] || return 0

	wp_config="${LOCAL_WP_PATH}/wp-config.php"
	[[ -f "${wp_config}" ]] || return 0

	python3 - "${wp_config}" "${LOCAL_DB_SOCKET}" <<'PY'
import pathlib
import re
import sys

wp_config = pathlib.Path(sys.argv[1])
socket_path = sys.argv[2]

text = wp_config.read_text()
updated = text

if "$mrn_local_socket" in text:
    updated = re.sub(
        r"\$mrn_local_socket\s*=\s*'[^']*';",
        f"$mrn_local_socket  = '{socket_path}';",
        text,
        count=1,
    )
else:
    block = (
        "$mrn_local_db_host = 'localhost';\n"
        f"$mrn_local_socket  = '{socket_path}';\n\n"
        "if (PHP_SAPI === 'cli' && file_exists($mrn_local_socket)) {\n"
        "\t$mrn_local_db_host = 'localhost:' . $mrn_local_socket;\n"
        "}\n\n"
        "define( 'DB_HOST', $mrn_local_db_host );"
    )
    updated, replacements = re.subn(
        r"define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]localhost['\"]\s*\);",
        block,
        text,
        count=1,
    )
    if replacements == 0:
        raise SystemExit(0)

if updated != text:
    wp_config.write_text(updated)
PY

	export MYSQL_UNIX_PORT="${LOCAL_DB_SOCKET}"
}

ensure_local_mysql_bin_path() {
	local services_root
	local candidate
	local -a mysql_bin_candidates

	if command -v mysql >/dev/null 2>&1 && command -v mysqlcheck >/dev/null 2>&1; then
		return 0
	fi

	services_root="/Applications/Local.app/Contents/Resources/extraResources/lightning-services"
	[[ -d "${services_root}" ]] || return 1

	while IFS= read -r candidate; do
		mysql_bin_candidates+=("${candidate}")
	done < <(find "${services_root}" -type d -path "*/mysql-*/bin/*/bin" 2>/dev/null | sort -r)

	for candidate in "${mysql_bin_candidates[@]}"; do
		if [[ -x "${candidate}/mysql" && -x "${candidate}/mysqlcheck" ]]; then
			PATH="${candidate}:${PATH}"
			export PATH
			return 0
		fi
	done

	return 1
}

assert_local_db_clients_ready() {
	if command -v mysql >/dev/null 2>&1 && command -v mysqlcheck >/dev/null 2>&1; then
		return 0
	fi

	ensure_local_mysql_bin_path >/dev/null 2>&1 || true

	if command -v mysql >/dev/null 2>&1 && command -v mysqlcheck >/dev/null 2>&1; then
		return 0
	fi

	fail "Missing mysql/mysqlcheck client tools for local wp db commands. Open Local once, then rerun (or install mysql client binaries in PATH)."
}

if [[ "${SNAPSHOT_MODE}" -eq 0 ]]; then
	ensure_local_site_running_for_pull
	detect_local_wp_bin
	resolve_local_site_db_socket
	ensure_local_wp_cli_socket_db_host
	ensure_local_mysql_bin_path >/dev/null 2>&1 || true
fi

run_local_wp() {
	local stdout_filter=1

	if [[ $# -ge 2 && "$1" == "db" && "$2" == "export" ]]; then
		stdout_filter=0
	fi

	if [[ "${FILTER_WPCLI_DEPRECATED}" -eq 1 ]]; then
		if [[ "${stdout_filter}" -eq 1 ]]; then
			WP_CLI_PHP_ARGS='-d error_reporting=6143 -d display_errors=0' "${WP_BIN}" --path="${LOCAL_WP_PATH}" "$@" \
				> >(sed -E '/^(PHP )?Deprecated: /d;/^[[:space:]]*$/d') \
				2> >(sed -E '/^(PHP )?Deprecated: /d;/^[[:space:]]*$/d' >&2)
		else
			WP_CLI_PHP_ARGS='-d error_reporting=6143 -d display_errors=0' "${WP_BIN}" --path="${LOCAL_WP_PATH}" "$@" \
				2> >(sed -E '/^(PHP )?Deprecated: /d;/^[[:space:]]*$/d' >&2)
		fi
	else
		WP_CLI_PHP_ARGS='-d error_reporting=6143 -d display_errors=0' "${WP_BIN}" --path="${LOCAL_WP_PATH}" "$@"
	fi
}

extract_last_http_url() {
	local raw="$1"
	printf '%s\n' "${raw}" | tr -d '\r' | awk '
		/^https?:\/\/[^[:space:]]+$/ { value = $0 }
		END {
			if (value != "") {
				print value
			}
		}
	'
}

build_remote_wp_command() {
	local cmd
	local arg

	cmd="wp --path=$(printf '%q' "${SITE_ROOT}")"
	for arg in "$@"; do
		cmd+=" $(printf '%q' "${arg}")"
	done

	printf '%s' "${cmd}"
}

run_site_remote() {
	local command="$1"
	ssh -l "${SITE_USER}" "${SSH_ALIAS}" "${command}"
}

run_site_wp() {
	local command
	command="$(build_remote_wp_command "$@")"
	run_site_remote "${command}"
}

parse_site_context() {
	local raw="$1"
	while IFS='=' read -r key value; do
		case "${key}" in
			SITE_USER) SITE_USER="${value}" ;;
			SITE_ROOT) SITE_ROOT="${value}" ;;
			SSH_ALIAS) SSH_ALIAS="${value}" ;;
			SSH_LOGIN) SSH_LOGIN="${value}" ;;
			BACKUP_LABEL) BACKUP_LABEL="${value}" ;;
		esac
	done <<< "${raw}"
}

verify_site_context() {
	[[ -n "${SITE_USER}" ]] || fail "Resolved SITE_USER was empty."
	[[ -n "${SITE_ROOT}" ]] || fail "Resolved SITE_ROOT was empty."
	[[ -n "${SSH_ALIAS}" ]] || fail "Resolved SSH_ALIAS was empty."
	[[ -n "${SSH_LOGIN}" ]] || fail "Resolved SSH_LOGIN was empty."
}

resolve_pull_target() {
	local output

	output="$("${RESOLVE_SCRIPT}" "${SITE_HOSTNAME}" --ssh-host "${DISCOVERY_SSH_HOST}")" || fail "Could not resolve live site owner for ${SITE_HOSTNAME}."
	parse_site_context "${output}"
	verify_site_context

	run_site_remote "whoami && pwd" >/dev/null || fail "Direct site-owner SSH verify failed for ${SITE_USER}@${SSH_ALIAS}."
}

resolve_deploy_target() {
	local output
	local -a preflight_args

	if [[ "${DRY_RUN}" -eq 1 ]]; then
		note "Dry-run: skipping live preflight writes (backup/settings normalization)."
		resolve_pull_target
		return
	fi

	preflight_args=(
		--site-hostname "${SITE_HOSTNAME}"
		--discovery-ssh-host "${DISCOVERY_SSH_HOST}"
	)


	if [[ "${SKIP_BACKUP}" -eq 1 ]]; then
		fail "--skip-backup cannot be used for a non-dry-run deploy. Use --dry-run for a read-only preview."
	fi
	preflight_args+=(--with-db-backup)

	output="$("${PREFLIGHT_SCRIPT}" "${preflight_args[@]}")" || fail "Preflight failed for ${SITE_HOSTNAME}."
	parse_site_context "${output}"
	verify_site_context
}

resolve_local_home_url() {
	local value
	local derived

	if [[ -n "${LOCAL_HOME_URL}" ]]; then
		return
	fi

	value="$(extract_last_http_url "$(run_local_wp option get home 2>/dev/null || true)")"
	if [[ -z "${value}" && -n "${LOCAL_AUTO_CREATED_SITE_URL}" ]]; then
		value="${LOCAL_AUTO_CREATED_SITE_URL}"
		note "Using Local API URL as local home URL: ${value}"
	fi
	if [[ -z "${value}" && "${SITE_HOSTNAME}" == *.mrndev.io ]]; then
		derived="http://${SITE_HOSTNAME%.mrndev.io}.local"
		value="${derived}"
		note "Using derived local home URL: ${value}"
	fi
	if [[ -z "${value}" ]]; then
		fail "Could not determine local home URL. Pass --local-home-url explicitly."
	fi

	LOCAL_HOME_URL="${value}"
}

confirm_deploy_scope() {
	local choice

	if [[ -n "${DEPLOY_SCOPE}" ]]; then
		return
	fi

	if [[ ! -t 0 ]]; then
		fail "--deploy-scope is required in non-interactive mode."
	fi

	cat <<'EOF'
Choose deploy scope:
  1) site  (local database/uploads -> this site)
  2) stack (canonical MRN stack code -> this site)
EOF
	printf "Enter choice (1 or 2): "
	read -r choice

	case "${choice}" in
		1|site|SITE)
			DEPLOY_SCOPE="site"
			;;
		2|stack|STACK)
			DEPLOY_SCOPE="stack"
			;;
		*)
			fail "Invalid deploy choice."
			;;
	esac
}

confirm_deploy_write() {
	local prompt
	local token

	if [[ "${YES}" -eq 1 ]]; then
		return
	fi

	if [[ ! -t 0 ]]; then
		fail "Refusing deploy without confirmation in non-interactive mode. Pass --yes to continue."
	fi

	prompt="Deploy scope '${DEPLOY_SCOPE}' to ${SITE_HOSTNAME}"
	if [[ "${DRY_RUN}" -eq 1 ]]; then
		prompt="${prompt} (dry-run)"
	fi

	echo "${prompt}"
	printf "Type DEPLOY to continue: "
	read -r token

	[[ "${token}" == "DEPLOY" ]] || fail "Deploy cancelled."
}

CODE_EXCLUDES=(
	--exclude=.git
	--exclude=.DS_Store
	--exclude=node_modules
	--exclude=vendor
	--exclude=sass
	--exclude=package-lock.json
	--exclude=package.json
	--exclude=composer.lock
	--exclude=composer.json
	--exclude=README.md
	--exclude=.gitignore
	--exclude=.gitattributes
	--exclude=.github
	--exclude=.travis.yml
	--exclude=phpcs.xml.dist
	--exclude=.stylelintrc.json
	--exclude=.eslintrc
	--exclude=style.css.map
	--exclude=yarn.lock
	--exclude=playwright-report
	--exclude=test-results
)

UPLOAD_EXCLUDES=(
	--exclude=.DS_Store
	--exclude=*.log
)

sync_code_directory() {
	local source="$1"
	local destination="$2"
	local delete_mode="${3:-1}"
	local -a flags

	flags=(
		-rlt
		--omit-dir-times
	)

	if [[ "${delete_mode}" -eq 1 ]]; then
		flags+=(--delete)
	fi

	if [[ "${DRY_RUN}" -eq 1 ]]; then
		flags+=(--dry-run --itemize-changes)
	fi

	rsync "${flags[@]}" "${CODE_EXCLUDES[@]}" "${source}" "${destination}"
}

sync_upload_directory() {
	local source="$1"
	local destination="$2"
	local delete_mode="${3:-0}"
	local -a flags

	flags=(
		-rlt
		--omit-dir-times
	)

	if [[ "${delete_mode}" -eq 1 ]]; then
		flags+=(--delete)
	fi

	if [[ "${DRY_RUN}" -eq 1 ]]; then
		flags+=(--dry-run --itemize-changes)
	fi

	rsync "${flags[@]}" "${UPLOAD_EXCLUDES[@]}" "${source}" "${destination}"
}

should_sync_runtime_surfaces() {
	if [[ "${SNAPSHOT_MODE}" -eq 1 ]]; then
		return 1
	fi

	case "${PULL_SYNC_RUNTIME_MODE}" in
		always)
			return 0
			;;
		never)
			return 1
			;;
		auto)
			if [[ "${LOCAL_API_RESOLVED_SITE}" -eq 1 ]]; then
				return 0
			fi
			return 1
			;;
		*)
			return 1
			;;
	esac
}

sync_runtime_surfaces_from_live() {
	local local_wp_content
	local remote_wp_content
	local local_themes
	local local_plugins
	local local_mu_plugins
	local remote_themes
	local remote_plugins
	local remote_mu_plugins
	local -a rsync_flags

	local_wp_content="${LOCAL_WP_PATH}/wp-content"
	remote_wp_content="${SITE_ROOT}/wp-content"
	local_themes="${local_wp_content}/themes/"
	local_plugins="${local_wp_content}/plugins/"
	local_mu_plugins="${local_wp_content}/mu-plugins/"
	remote_themes="${SITE_USER}@${SSH_ALIAS}:${remote_wp_content}/themes/"
	remote_plugins="${SITE_USER}@${SSH_ALIAS}:${remote_wp_content}/plugins/"
	remote_mu_plugins="${SITE_USER}@${SSH_ALIAS}:${remote_wp_content}/mu-plugins/"

	rsync_flags=(
		-rlt
		--omit-dir-times
		--delete
	)

	if [[ "${DRY_RUN}" -eq 1 ]]; then
		rsync_flags+=(--dry-run --itemize-changes)
	fi

	mkdir -p "${local_themes}" "${local_plugins}" "${local_mu_plugins}"

	note "Syncing runtime code surfaces from live -> Local (themes/plugins/mu-plugins)..."
	rsync "${rsync_flags[@]}" "${remote_themes}" "${local_themes}"
	rsync "${rsync_flags[@]}" "${remote_plugins}" "${local_plugins}"
	rsync "${rsync_flags[@]}" "${remote_mu_plugins}" "${local_mu_plugins}"
}

normalize_remote_tree_permissions() {
	local path="$1"
	local label="$2"
	local escaped

	if [[ "${DRY_RUN}" -eq 1 ]]; then
		note "Dry-run: skip permission normalization for ${label}."
		return
	fi

	escaped="$(printf '%q' "${path}")"
	note "Normalizing ${label} permissions..."
	run_site_remote "if [[ -d ${escaped} ]]; then find ${escaped} -type d -exec chmod 755 {} +; find ${escaped} -type f -not -path '*/.git/*' -exec chmod 644 {} +; fi"
}

ensure_remote_dir() {
	local path="$1"
	local escaped

	escaped="$(printf '%q' "${path}")"

	if [[ "${DRY_RUN}" -eq 1 ]]; then
		run_site_remote "if [[ ! -d ${escaped} ]]; then echo 'NOTE: remote dir missing (dry-run): ${path}' >&2; fi"
		return
	fi

	run_site_remote "mkdir -p ${escaped}"
}

pull_uploads() {
	local remote_uploads
	local local_uploads

	remote_uploads="${SITE_ROOT}/wp-content/uploads/"
	if [[ "${SNAPSHOT_MODE}" -eq 1 ]]; then
		local_uploads="${SNAPSHOT_UPLOADS_PATH}/"
	else
		local_uploads="${LOCAL_WP_PATH}/wp-content/uploads/"
	fi

	mkdir -p "${local_uploads}"
	if [[ "${SNAPSHOT_MODE}" -eq 1 ]]; then
		note "Pulling uploads from ${SITE_HOSTNAME} -> snapshot..."
	else
		note "Pulling uploads from ${SITE_HOSTNAME} -> local..."
	fi
	sync_upload_directory "${SITE_USER}@${SSH_ALIAS}:${remote_uploads}" "${local_uploads}" 1
}

pull_database() {
	local local_home_before
	local imported_home
	local tmp_sql
	local tmp_sql_gz
	local safe_name
	local snapshot_file
	local timestamp

	if [[ "${SNAPSHOT_MODE}" -eq 0 ]]; then
		assert_local_db_clients_ready
		resolve_local_home_url
		local_home_before="${LOCAL_HOME_URL}"
	fi

	if [[ "${DRY_RUN}" -eq 1 ]]; then
		note "Dry-run: skipping database pull/import."
		return
	fi

	safe_name="$(printf '%s' "${SITE_HOSTNAME}" | tr -c '[:alnum:]._-' '-')"
	if [[ "${SNAPSHOT_MODE}" -eq 1 ]]; then
		mkdir -p "${SNAPSHOT_DB_DIR}"
		timestamp="$(date +%Y%m%d-%H%M%S)"
		snapshot_file="${SNAPSHOT_DB_DIR}/${safe_name}.${timestamp}.sql.gz"

		note "Exporting live database from ${SITE_HOSTNAME} -> snapshot..."
		if ! run_site_wp db export - --single-transaction --quick --skip-lock-tables | gzip -c > "${snapshot_file}"; then
			fail "Live DB export failed while writing snapshot for ${SITE_HOSTNAME}."
		fi
		ln -sfn "${snapshot_file}" "${SNAPSHOT_DB_DIR}/latest.sql.gz"
		note "Snapshot DB saved: ${snapshot_file}"
		return
	fi

	tmp_sql="$(mktemp "/tmp/${safe_name}.pull.XXXXXX")"
	tmp_sql_gz="${tmp_sql}.sql.gz"
	mv "${tmp_sql}" "${tmp_sql_gz}"
	tmp_sql="${tmp_sql_gz}"
	trap 'rm -f "${tmp_sql}"' RETURN

	note "Exporting live database from ${SITE_HOSTNAME}..."
	if ! run_site_wp db export - --single-transaction --quick --skip-lock-tables | gzip -c > "${tmp_sql}"; then
		fail "Live DB export failed for ${SITE_HOSTNAME}."
	fi

	note "Importing live database into local site..."
	if ! gunzip -c "${tmp_sql}" | run_local_wp db import -; then
		fail "Local DB import failed. In Local app, start/restart the site and confirm WP Admin loads, then rerun pull-site."
	fi

	imported_home="$(extract_last_http_url "$(run_local_wp option get home 2>/dev/null || true)")"
	if [[ -z "${imported_home}" ]]; then
		fail "Imported local home URL was empty after DB import."
	fi

	if [[ "${imported_home}" != "${local_home_before}" ]]; then
		note "Rewriting imported URLs to local home (${local_home_before})..."
		run_local_wp search-replace "${imported_home}" "${local_home_before}" --all-tables --skip-columns=guid --precise --report-changed-only
	fi

	run_local_wp option update home "${local_home_before}" >/dev/null
	run_local_wp option update siteurl "${local_home_before}" >/dev/null

	rm -f "${tmp_sql}"
	trap - RETURN
}

deploy_site_uploads() {
	local remote_uploads
	local local_uploads
	local delete_mode

	remote_uploads="${SITE_ROOT}/wp-content/uploads/"
	local_uploads="${LOCAL_WP_PATH}/wp-content/uploads/"
	delete_mode=0

	if [[ "${DELETE_UPLOADS}" -eq 1 ]]; then
		delete_mode=1
	fi

	ensure_remote_dir "${SITE_ROOT}/wp-content/uploads"
	note "Deploying local uploads to ${SITE_HOSTNAME}..."
	sync_upload_directory "${local_uploads}" "${SITE_USER}@${SSH_ALIAS}:${remote_uploads}" "${delete_mode}"
	normalize_remote_tree_permissions "${SITE_ROOT}/wp-content/uploads" "live uploads"
}

deploy_site_database() {
	local remote_home

	assert_local_db_clients_ready
	resolve_local_home_url
	remote_home="$(extract_last_http_url "$(run_site_wp option get home 2>/dev/null || true)")"
	[[ -n "${remote_home}" ]] || fail "Could not read remote home URL from ${SITE_HOSTNAME}."

	if [[ "${DRY_RUN}" -eq 1 ]]; then
		note "Dry-run: skipping remote database import."
		return
	fi

	note "Pushing local database to ${SITE_HOSTNAME}..."
	if ! run_local_wp db export - --single-transaction --quick --skip-lock-tables | gzip -c | run_site_remote "gunzip -c | wp --path=$(printf '%q' "${SITE_ROOT}") db import -"; then
		fail "Deploy DB pipeline failed while pushing local DB to ${SITE_HOSTNAME}."
	fi

	if [[ "${LOCAL_HOME_URL}" != "${remote_home}" ]]; then
		note "Rewriting deployed URLs back to remote home (${remote_home})..."
		run_site_wp search-replace "${LOCAL_HOME_URL}" "${remote_home}" --all-tables --skip-columns=guid --precise --report-changed-only
	fi

	run_site_wp option update home "${remote_home}" >/dev/null
	run_site_wp option update siteurl "${remote_home}" >/dev/null
}

deploy_stack_theme() {
	local active_stylesheet
	local active_template
	local target_theme_slug
	local remote_theme_path
	local theme_meta
	local preserve_theme_name
	local preserve_text_domain
	local -a theme_args

	active_stylesheet="$(run_site_wp option get stylesheet --skip-themes | tr -d '\r' | tail -n 1 || true)"
	active_template="$(run_site_wp option get template --skip-themes | tr -d '\r' | tail -n 1 || true)"

	[[ -n "${active_stylesheet}" ]] || fail "Could not resolve active stylesheet on ${SITE_HOSTNAME}."
	[[ -n "${active_template}" ]] || fail "Could not resolve active template on ${SITE_HOSTNAME}."

	if [[ "${active_stylesheet}" == "${active_template}" ]]; then
		target_theme_slug="${active_stylesheet}"
	else
		target_theme_slug="${active_template}"
	fi

	remote_theme_path="${SITE_ROOT}/wp-content/themes/${target_theme_slug}"
	theme_meta="$(run_site_wp eval "\$theme = wp_get_theme('${target_theme_slug}'); echo \$theme->get('Name') . PHP_EOL; echo \$theme->get('TextDomain') . PHP_EOL;" | tr -d '\r')"
	preserve_theme_name="$(printf '%s\n' "${theme_meta}" | sed -n '1p')"
	preserve_text_domain="$(printf '%s\n' "${theme_meta}" | sed -n '2p')"

	[[ -n "${preserve_theme_name}" ]] || fail "Could not resolve live Theme Name for ${target_theme_slug}."

	if [[ "${DRY_RUN}" -eq 1 ]]; then
		note "Dry-run: previewing stack theme sync to ${remote_theme_path}..."
		sync_code_directory "${REPO_ROOT}/stack/themes/mrn-base-stack/" "${SITE_USER}@${SSH_ALIAS}:${remote_theme_path}/" 1
		return
	fi

	note "Deploying canonical stack theme to live theme path ${remote_theme_path}..."

	theme_args=(
		--site-user "${SITE_USER}"
		--site-path "${SITE_ROOT}"
		--theme-src "${REPO_ROOT}/stack/themes/mrn-base-stack"
		--remote-theme-path "${remote_theme_path}"
		--ssh-host "${SITE_USER}@${SSH_ALIAS}"
		--direct-ssh
		--preserve-theme-name "${preserve_theme_name}"
	)

	if [[ -n "${preserve_text_domain}" ]]; then
		theme_args+=(--preserve-text-domain "${preserve_text_domain}")
	fi

	"${DEPLOY_THEME_SCRIPT}" "${theme_args[@]}"
}

deploy_stack_mu_plugins() {
	local mu_root
	local remote_root
	local dir
	local slug

	mu_root="${REPO_ROOT}/mu-plugins"
	remote_root="${SITE_ROOT}/wp-content/mu-plugins"

	[[ -d "${mu_root}" ]] || fail "Missing local mu-plugins source: ${mu_root}"
	ensure_remote_dir "${remote_root}"

	for dir in "${mu_root}"/mrn-*; do
		[[ -d "${dir}" ]] || continue
		slug="$(basename "${dir}")"
		note "Syncing MU plugin ${slug}..."
		sync_code_directory "${dir}/" "${SITE_USER}@${SSH_ALIAS}:${remote_root}/${slug}/" 1
		normalize_remote_tree_permissions "${remote_root}/${slug}" "mu-plugin ${slug}"
	done
}

deploy_stack_plugins() {
	local plugins_root
	local remote_root
	local dir
	local slug

	plugins_root="${REPO_ROOT}/plugins"
	remote_root="${SITE_ROOT}/wp-content/plugins"

	[[ -d "${plugins_root}" ]] || fail "Missing local plugins source: ${plugins_root}"
	ensure_remote_dir "${remote_root}"

	for dir in "${plugins_root}"/*; do
		[[ -d "${dir}" ]] || continue
		slug="$(basename "${dir}")"
		note "Syncing plugin ${slug}..."
		sync_code_directory "${dir}/" "${SITE_USER}@${SSH_ALIAS}:${remote_root}/${slug}/" 1
		normalize_remote_tree_permissions "${remote_root}/${slug}" "plugin ${slug}"
	done
}

deploy_stack_shared() {
	local shared_root
	local remote_root

	shared_root="${REPO_ROOT}/shared"
	remote_root="${SITE_ROOT}/wp-content/shared"

	[[ -d "${shared_root}" ]] || fail "Missing local shared source: ${shared_root}"
	ensure_remote_dir "${remote_root}"
	note "Syncing shared runtime..."
	sync_code_directory "${shared_root}/" "${SITE_USER}@${SSH_ALIAS}:${remote_root}/" 1
	normalize_remote_tree_permissions "${remote_root}" "shared runtime"
}

run_pull() {
	local local_visit_url
	local derived_local_url

	note "Resolving live site owner for pull..."
	resolve_pull_target
	note "Resolved ${SITE_HOSTNAME} as ${SITE_USER} (${SITE_ROOT})."
	if [[ "${SNAPSHOT_MODE}" -eq 1 ]]; then
		note "Mode: snapshot-only (not a runnable Local site)."
		note "Snapshot root: ${SNAPSHOT_SITE_ROOT}"
	else
		note "Mode: runnable Local site sync."
		if [[ "${LOCAL_AUTO_CREATED_SITE}" -eq 1 ]]; then
			note "Local site was auto-created via Local app API."
		elif [[ -n "${LOCAL_AUTO_CREATED_SITE_DOMAIN}" ]]; then
			note "Local site was auto-resolved via Local app API (${LOCAL_AUTO_CREATED_SITE_DOMAIN})."
		fi
		note "Local WordPress path: ${LOCAL_WP_PATH}"

		if should_sync_runtime_surfaces; then
			sync_runtime_surfaces_from_live
		else
			note "Skipping runtime code sync (mode: ${PULL_SYNC_RUNTIME_MODE})."
		fi
	fi

	if [[ "${SKIP_UPLOADS}" -eq 0 ]]; then
		pull_uploads
	else
		note "Skipping uploads pull (--skip-uploads)."
	fi

	if [[ "${SKIP_DB}" -eq 0 ]]; then
		pull_database
	else
		note "Skipping database pull (--skip-db)."
	fi

	if [[ "${SNAPSHOT_MODE}" -eq 1 ]]; then
		note "Snapshot pull artifacts saved under ${SNAPSHOT_SITE_ROOT}."
		note "No local visit URL is available in snapshot mode."
		note "When you're ready, create/map a Local site path for ${SITE_HOSTNAME} and rerun pull-site for a runnable URL."
		if [[ -f "${SNAPSHOT_DB_DIR}/latest.sql.gz" ]]; then
			note "Latest snapshot DB: ${SNAPSHOT_DB_DIR}/latest.sql.gz"
		fi
		if [[ -d "${SNAPSHOT_UPLOADS_PATH}" ]]; then
			note "Snapshot uploads dir: ${SNAPSHOT_UPLOADS_PATH}"
		fi
	else
		local_visit_url="$(extract_last_http_url "$(run_local_wp option get home 2>/dev/null || true)")"
		if [[ -n "${local_visit_url}" ]]; then
			note "Local visit URL: ${local_visit_url}"
		elif [[ -n "${LOCAL_AUTO_CREATED_SITE_URL}" ]]; then
			note "Local visit URL: ${LOCAL_AUTO_CREATED_SITE_URL}"
		else
			derived_local_url=""
			if [[ "${SITE_HOSTNAME}" == *.mrndev.io ]]; then
				derived_local_url="http://${SITE_HOSTNAME%.mrndev.io}.local"
			fi
			if [[ -n "${derived_local_url}" ]]; then
				note "Local visit URL: ${derived_local_url} (derived from hostname)."
			else
				note "Local visit URL: unavailable (could not read WP home option)."
			fi
		fi
	fi

	note "Pull workflow completed for ${SITE_HOSTNAME}."
}

run_deploy() {
	note "Running deploy preflight..."
	resolve_deploy_target
	note "Preflight passed for ${SITE_HOSTNAME}."

	confirm_deploy_scope
	confirm_deploy_write

	if [[ "${DEPLOY_SCOPE}" == "site" ]]; then
		note "Running site-scope deploy..."
		if [[ "${SKIP_UPLOADS}" -eq 0 ]]; then
			deploy_site_uploads
		else
			note "Skipping uploads deploy (--skip-uploads)."
		fi

		if [[ "${SKIP_DB}" -eq 0 ]]; then
			deploy_site_database
		else
			note "Skipping database deploy (--skip-db)."
		fi
	else
		note "Running stack-scope deploy..."
		deploy_stack_theme
		deploy_stack_mu_plugins
		deploy_stack_plugins
		deploy_stack_shared
	fi

	note "Deploy workflow completed for ${SITE_HOSTNAME} (scope: ${DEPLOY_SCOPE})."
}

case "${COMMAND}" in
	pull)
		run_pull
		;;
	deploy)
		run_deploy
		;;
esac
