#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  deploy-feature-stack-and-default-configs.sh [--ssh-host <ssh-host>] [--dry-run]

Description:
  Sync the canonical stack theme, stack MU plugin source, and stack MU loader
  wrappers to the stack server source-of-truth paths
  (/home/mrndev-stack-manager/stack on the target host). This is the tree
  site-bootstrap.sh reads MU-plugin source from when provisioning a new
  CloudPanel site, so this sync is what actually gets new sites onto current
  stack code.

Notes:
  - This is the canonical feature-deploy helper for stack theme and stack MU work.
  - Standard plugins still follow their own plugin release flow.
  - The default target host is mrndev-stack-manager@167.99.54.77.
  - This script no longer syncs a live reference site. It previously also
    synced to default-configs.mrndev.io, but that site no longer exists on
    this host (confirmed 2026-08-20: absent from the full /home account
    listing under mrndev-stack-manager). If a live reference site is needed
    again, use deploy-live-theme.sh against a specific site once one exists.
EOF
}

SSH_HOST="mrndev-stack-manager@167.99.54.77"
DRY_RUN=0
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

while [[ $# -gt 0 ]]; do
	case "$1" in
		--ssh-host)
			SSH_HOST="${2:-}"
			shift 2
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

STACK_ROOT_REMOTE="/home/mrndev-stack-manager/stack"
LOCAL_THEME_DIR="${REPO_ROOT}/stack/themes/mrn-base-stack"
LOCAL_STACK_MU_DIR="${REPO_ROOT}/stack/mu-plugins"
LOCAL_MU_SOURCE_ROOT="${REPO_ROOT}/mu-plugins"
LOCAL_SHARED_DIR="${REPO_ROOT}/shared"

MU_PLUGIN_DIRS=(
	"mrn-active-style-guide"
	"mrn-admin-data-post-types"
	"mrn-admin-ui-css"
	"mrn-dashboard-support"
	"mrn-disable-comments"
	"mrn-editor-lockdown"
	"mrn-environment-runtime"
	"mrn-public-security-hardening"
	"mrn-schema-bridge"
	"mrn-shared-assets"
	"mrn-site-colors"
	"mrn-updraft-local-retention"
)

THEME_EXCLUDES=(
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

COMMON_DIR_EXCLUDES=(
	--exclude=.git
	--exclude=.gitignore
	--exclude=.DS_Store
)

RSYNC_FLAGS=(
	-rlt
	--delete
	--omit-dir-times
)

if [[ "${DRY_RUN}" -eq 1 ]]; then
	RSYNC_FLAGS+=(--dry-run --itemize-changes)
fi

for required in rsync ssh; do
	if ! command -v "${required}" >/dev/null 2>&1; then
		echo "Required command not found: ${required}" >&2
		exit 1
	fi
done

if [[ ! -d "${LOCAL_THEME_DIR}" ]]; then
	echo "Theme source directory not found: ${LOCAL_THEME_DIR}" >&2
	exit 1
fi

if [[ ! -d "${LOCAL_SHARED_DIR}" ]]; then
	echo "Shared source directory not found: ${LOCAL_SHARED_DIR}" >&2
	exit 1
fi

for slug in "${MU_PLUGIN_DIRS[@]}"; do
	if [[ ! -d "${LOCAL_MU_SOURCE_ROOT}/${slug}" ]]; then
		echo "MU plugin source directory not found: ${LOCAL_MU_SOURCE_ROOT}/${slug}" >&2
		exit 1
	fi
done

run_rsync() {
	local source="$1"
	local destination="$2"
	shift 2
	echo "Syncing ${source} -> ${destination}"
	rsync "${RSYNC_FLAGS[@]}" "$@" "${source}" "${destination}"
}

run_remote() {
	local remote_host="$1"
	local command="$2"
	ssh "${remote_host}" "${command}"
}

normalize_remote_tree_permissions() {
	local remote_host="$1"
	local path="$2"
	local label="$3"
	local user_filter="${4:-}"
	local find_prefix="find '${path}'"

	if [[ -n "${user_filter}" ]]; then
		find_prefix+=" -user '${user_filter}'"
	fi

	echo "Normalizing ${label} permissions..."
	run_remote "${remote_host}" "${find_prefix} -type d -exec chmod 755 {} +"
	run_remote "${remote_host}" "${find_prefix} -type f -not -path '*/.git/*' -exec chmod 644 {} +"
}

verify_remote_tree_file_modes() {
	local remote_host="$1"
	local path="$2"
	local label="$3"
	local user_filter="${4:-}"
	local find_prefix="find '${path}'"
	local out_of_spec=""

	if [[ -n "${user_filter}" ]]; then
		find_prefix+=" -user '${user_filter}'"
	fi

	out_of_spec="$(run_remote "${remote_host}" "${find_prefix} -type f -not -path '*/.git/*' ! -perm 644 -print | head -n 20" | tr -d '\r')"
	if [[ -n "${out_of_spec}" ]]; then
		echo "ERROR: ${label} still has files that are not mode 644 after normalization." >&2
		echo "${out_of_spec}" >&2
		return 1
	fi
}

echo "Deploying stack feature surfaces to ${SSH_HOST}..."

run_rsync \
	"${LOCAL_THEME_DIR}/" \
	"${SSH_HOST}:${STACK_ROOT_REMOTE}/themes/mrn-base-stack/" \
	"${THEME_EXCLUDES[@]}"

run_rsync \
	"${LOCAL_SHARED_DIR}/" \
	"${SSH_HOST}:${STACK_ROOT_REMOTE}/shared/" \
	"${COMMON_DIR_EXCLUDES[@]}"

for slug in "${MU_PLUGIN_DIRS[@]}"; do
	run_rsync \
	"${LOCAL_MU_SOURCE_ROOT}/${slug}/" \
	"${SSH_HOST}:${STACK_ROOT_REMOTE}/mu-plugins/${slug}/" \
	"${COMMON_DIR_EXCLUDES[@]}"
done

for wrapper in "${LOCAL_STACK_MU_DIR}"/mrn-*.php; do
	[[ -f "${wrapper}" ]] || continue
	run_rsync \
		"${wrapper}" \
		"${SSH_HOST}:${STACK_ROOT_REMOTE}/mu-plugins/$(basename "${wrapper}")"
done

if [[ "${DRY_RUN}" -eq 0 ]]; then
	normalize_remote_tree_permissions "${SSH_HOST}" "${STACK_ROOT_REMOTE}/themes/mrn-base-stack" "stack theme"
	normalize_remote_tree_permissions "${SSH_HOST}" "${STACK_ROOT_REMOTE}/shared" "stack shared runtime"
	normalize_remote_tree_permissions "${SSH_HOST}" "${STACK_ROOT_REMOTE}/mu-plugins" "stack mu-plugins"

	verify_remote_tree_file_modes "${SSH_HOST}" "${STACK_ROOT_REMOTE}/themes/mrn-base-stack" "stack theme"
	verify_remote_tree_file_modes "${SSH_HOST}" "${STACK_ROOT_REMOTE}/shared" "stack shared runtime"
	verify_remote_tree_file_modes "${SSH_HOST}" "${STACK_ROOT_REMOTE}/mu-plugins" "stack mu-plugins"
fi

echo "Stack source-of-truth sync completed."
