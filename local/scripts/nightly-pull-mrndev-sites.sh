#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  nightly-pull-mrndev-sites.sh \
    [--discovery-ssh-host <ssh-host>] \
    [--local-sites-root <path>] \
    [--map-file <path>] \
    [--snapshot-root <path>] \
    [--skip-db] \
    [--with-uploads] \
    [--dry-run]

Description:
  Discovers live sites ending in .mrndev.io and runs the canonical Local pull
  helper for each site. If a local Local app path is not available, pull runs
  in snapshot mode and stores artifacts under <snapshot-root>/<site-hostname>/.

Default behavior:
  - pulls database for every mapped site
  - skips uploads for nightly speed unless --with-uploads is provided

Path resolution:
  1) explicit map file entry: hostname|/absolute/local/site/path
  2) inferred local path candidates:
     - <local-sites-root>/<hostname>/app/public
     - <local-sites-root>/<hostname without .mrndev.io>/app/public
  3) fallback snapshot mode (no local path required)

Examples:
  nightly-pull-mrndev-sites.sh
  nightly-pull-mrndev-sites.sh --with-uploads
  nightly-pull-mrndev-sites.sh --dry-run
EOF
}

fail() {
	echo "FAIL: $*" >&2
	exit 1
}

note() {
	echo "$*" >&2
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
LOCAL_WORKFLOW="${SCRIPT_DIR}/local-env-workflow.sh"
DISCOVERY_SSH_HOST="mrndev"
LOCAL_SITES_ROOT="${MRN_LOCAL_SITES_ROOT:-/Users/khofmeyer/Local Sites}"
MAP_FILE="${MRN_LOCAL_SITE_MAP_FILE:-${REPO_ROOT}/local/configs/local-site-map.mrndev.io.txt}"
SNAPSHOT_ROOT="${MRN_PULL_SNAPSHOT_ROOT:-${REPO_ROOT}/.tmp/mrndev-snapshots}"
SKIP_DB=0
SKIP_UPLOADS=1
DRY_RUN=0

while [[ $# -gt 0 ]]; do
	case "$1" in
		--discovery-ssh-host)
			DISCOVERY_SSH_HOST="${2:-}"
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
		--snapshot-root)
			SNAPSHOT_ROOT="${2:-}"
			shift 2
			;;
		--skip-db)
			SKIP_DB=1
			shift
			;;
		--with-uploads)
			SKIP_UPLOADS=0
			shift
			;;
		--dry-run)
			DRY_RUN=1
			shift
			;;
		-h|--help)
			usage
			exit 0
			;;
		*)
			echo "Unknown argument: $1" >&2
			usage >&2
			exit 1
			;;
	esac
done

[[ -x "${LOCAL_WORKFLOW}" ]] || fail "Missing local workflow helper: ${LOCAL_WORKFLOW}"
[[ -d "${LOCAL_SITES_ROOT}" ]] || fail "Local Sites root not found: ${LOCAL_SITES_ROOT}"

for required in awk bash find sed sort ssh; do
	command -v "${required}" >/dev/null 2>&1 || fail "Required command not found: ${required}"
done

discover_mrndev_hosts() {
	local remote_script

	remote_script=$(cat <<'EOF'
set -euo pipefail
find /home -mindepth 3 -maxdepth 3 -type d -path "/home/*/htdocs/*.mrndev.io" 2>/dev/null \
	| sed -E 's#.*/htdocs/([^/]+)$#\1#' \
	| sort -u
EOF
)

	ssh "${DISCOVERY_SSH_HOST}" "bash -s" <<< "${remote_script}" | tr -d '\r'
}

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

resolve_local_site_path() {
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

run_pull_for_site() {
	local site_hostname="$1"
	local local_site_path="${2:-}"
	local -a args

	args=(
		pull
		--site-hostname "${site_hostname}"
		--discovery-ssh-host "${DISCOVERY_SSH_HOST}"
		--snapshot-if-missing
		--snapshot-root "${SNAPSHOT_ROOT}"
	)

	if [[ -n "${local_site_path}" ]]; then
		args+=(--local-site-path "${local_site_path}")
	fi

	if [[ "${SKIP_DB}" -eq 1 ]]; then
		args+=(--skip-db)
	fi

	if [[ "${SKIP_UPLOADS}" -eq 1 ]]; then
		args+=(--skip-uploads)
	fi

	if [[ "${DRY_RUN}" -eq 1 ]]; then
		args+=(--dry-run)
	fi

	"${LOCAL_WORKFLOW}" "${args[@]}"
}

note "Discovering .mrndev.io sites via ${DISCOVERY_SSH_HOST}..."
SITE_HOSTS="$(discover_mrndev_hosts || true)"

if [[ -z "${SITE_HOSTS}" ]]; then
	fail "No .mrndev.io sites discovered from ${DISCOVERY_SSH_HOST}."
fi

TOTAL=0
SYNCED=0
SKIPPED=0
FAILED=0

OLD_IFS="${IFS}"
IFS=$'\n'
SITE_HOST_ARRAY=( ${SITE_HOSTS} )
IFS="${OLD_IFS}"

for SITE_HOST in "${SITE_HOST_ARRAY[@]}"; do
	[[ -n "${SITE_HOST}" ]] || continue
	TOTAL=$((TOTAL + 1))
	PULL_MODE="snapshot"

	LOCAL_PATH="$(resolve_local_site_path "${SITE_HOST}" || true)"
	if [[ -z "${LOCAL_PATH}" ]]; then
		note "PULL: ${SITE_HOST} -> snapshot mode (${SNAPSHOT_ROOT})"
	else
		if [[ ! -d "${LOCAL_PATH}" ]]; then
			note "PULL: ${SITE_HOST} -> snapshot mode (${SNAPSHOT_ROOT}) [missing local path: ${LOCAL_PATH}]"
			LOCAL_PATH=""
		else
			PULL_MODE="local"
			note "PULL: ${SITE_HOST} -> ${LOCAL_PATH}"
		fi
	fi

	# Prevent nested ssh/wp commands from consuming this loop's stdin.
	if run_pull_for_site "${SITE_HOST}" "${LOCAL_PATH}" < /dev/null; then
		SYNCED=$((SYNCED + 1))
	else
		if [[ "${PULL_MODE}" == "snapshot" ]]; then
			note "SKIP: ${SITE_HOST} (snapshot pull failed; likely no direct site-owner SSH access)"
			SKIPPED=$((SKIPPED + 1))
		else
			note "FAIL: ${SITE_HOST}"
			FAILED=$((FAILED + 1))
		fi
	fi
done

note "Nightly pull summary: total=${TOTAL} synced=${SYNCED} skipped=${SKIPPED} failed=${FAILED}"

if [[ "${FAILED}" -gt 0 ]]; then
	exit 1
fi
