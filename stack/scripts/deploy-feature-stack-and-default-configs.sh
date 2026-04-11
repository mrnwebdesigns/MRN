#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  deploy-feature-stack-and-default-configs.sh [--ssh-host <ssh-host>] [--dry-run]

Description:
  Sync the canonical stack theme, stack MU plugin source, and stack MU loader
  wrappers to both:
  - the stack server source-of-truth paths
  - the default-configs live site

Notes:
  - This is the canonical feature-deploy helper for stack theme and stack MU work.
  - Standard plugins still follow their own plugin release flow.
  - The default target host is mrndev-stack-manager@167.99.54.77.
EOF
}

SSH_HOST="mrndev-stack-manager@167.99.54.77"
DRY_RUN=0

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

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
STACK_ROOT_REMOTE="/home/mrndev-stack-manager/stack"
LIVE_SITE_ROOT="${MRN_DEFAULT_CONFIGS_LIVE_SITE_ROOT:-/home/default-configs-stack/htdocs/default-configs.mrndev.io}"
LOCAL_THEME_DIR="${REPO_ROOT}/stack/themes/mrn-base-stack"
LOCAL_STACK_MU_DIR="${REPO_ROOT}/stack/mu-plugins"
LOCAL_MU_SOURCE_ROOT="${REPO_ROOT}/mu-plugins"
LOCAL_SHARED_DIR="${REPO_ROOT}/shared"
LIVE_SITE_THEME_SLUG="${MRN_DEFAULT_CONFIGS_THEME_SLUG:-default-configs}"
LIVE_SITE_THEME_DIR=""
LIVE_SITE_ACTIVE_STYLESHEET=""
REMOTE_SYNC_USER=""

MU_PLUGIN_DIRS=(
	"mrn-active-style-guide"
	"mrn-admin-ui-css"
	"mrn-dashboard-support"
	"mrn-disable-comments"
	"mrn-duplicate-enhance"
	"mrn-editor-lockdown"
	"mrn-editor-ui-css"
	"mrn-reusable-block-library"
	"mrn-shared-assets"
	"mrn-site-colors"
	"mrn-svg-support"
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
	local command="$1"
	ssh "${SSH_HOST}" "${command}"
}

normalize_remote_tree_permissions() {
	local path="$1"
	local label="$2"
	local user_filter="${3:-}"
	local find_prefix="find '${path}'"

	if [[ -n "${user_filter}" ]]; then
		find_prefix+=" -user '${user_filter}'"
	fi

	echo "Normalizing ${label} permissions..."
	run_remote "${find_prefix} -type d -exec chmod 755 {} +"
	run_remote "${find_prefix} -type f -exec chmod 644 {} +"
}

verify_remote_tree_file_modes() {
	local path="$1"
	local label="$2"
	local user_filter="${3:-}"
	local find_prefix="find '${path}'"
	local out_of_spec=""

	if [[ -n "${user_filter}" ]]; then
		find_prefix+=" -user '${user_filter}'"
	fi

	out_of_spec="$(run_remote "${find_prefix} -type f ! -perm 644 -print | head -n 20" | tr -d '\r')"
	if [[ -n "${out_of_spec}" ]]; then
		echo "ERROR: ${label} still has files that are not mode 644." >&2
		echo "${out_of_spec}" >&2
		return 1
	fi
}

resolve_live_site_theme_slug() {
	printf '%s' "${LIVE_SITE_THEME_SLUG}"
}

echo "Deploying stack feature surfaces to ${SSH_HOST}..."

LIVE_SITE_THEME_SLUG="$(resolve_live_site_theme_slug)"
LIVE_SITE_THEME_DIR="${LIVE_SITE_ROOT}/wp-content/themes/${LIVE_SITE_THEME_SLUG}"
REMOTE_SYNC_USER="${SSH_HOST%%@*}"
LIVE_SITE_ACTIVE_STYLESHEET="$(ssh "${SSH_HOST}" "wp option get stylesheet --path='${LIVE_SITE_ROOT}' 2>/dev/null" | tr -d '\r' | xargs || true)"

echo "Live default-configs deploy theme slug: ${LIVE_SITE_THEME_SLUG}"
if [[ -n "${LIVE_SITE_ACTIVE_STYLESHEET}" && "${LIVE_SITE_ACTIVE_STYLESHEET}" != "${LIVE_SITE_THEME_SLUG}" ]]; then
	echo "WARNING: live active stylesheet is ${LIVE_SITE_ACTIVE_STYLESHEET}, expected ${LIVE_SITE_THEME_SLUG}" >&2
fi

run_rsync \
	"${LOCAL_THEME_DIR}/" \
	"${SSH_HOST}:${STACK_ROOT_REMOTE}/themes/mrn-base-stack/" \
	"${THEME_EXCLUDES[@]}"

run_rsync \
	"${LOCAL_THEME_DIR}/" \
	"${SSH_HOST}:${LIVE_SITE_THEME_DIR}/" \
	"${THEME_EXCLUDES[@]}"

run_rsync \
	"${LOCAL_SHARED_DIR}/" \
	"${SSH_HOST}:${STACK_ROOT_REMOTE}/shared/" \
	"${COMMON_DIR_EXCLUDES[@]}"

run_rsync \
	"${LOCAL_SHARED_DIR}/" \
	"${SSH_HOST}:${LIVE_SITE_ROOT}/wp-content/shared/" \
	"${COMMON_DIR_EXCLUDES[@]}"

for slug in "${MU_PLUGIN_DIRS[@]}"; do
	run_rsync \
	"${LOCAL_MU_SOURCE_ROOT}/${slug}/" \
	"${SSH_HOST}:${STACK_ROOT_REMOTE}/mu-plugins/${slug}/" \
	"${COMMON_DIR_EXCLUDES[@]}"

	run_rsync \
		"${LOCAL_MU_SOURCE_ROOT}/${slug}/" \
		"${SSH_HOST}:${LIVE_SITE_ROOT}/wp-content/mu-plugins/${slug}/" \
		"${COMMON_DIR_EXCLUDES[@]}"
done

for wrapper in "${LOCAL_STACK_MU_DIR}"/mrn-*.php; do
	[[ -f "${wrapper}" ]] || continue
	run_rsync \
	"${wrapper}" \
	"${SSH_HOST}:${STACK_ROOT_REMOTE}/mu-plugins/$(basename "${wrapper}")"
	run_rsync \
		"${wrapper}" \
		"${SSH_HOST}:${LIVE_SITE_ROOT}/wp-content/mu-plugins/$(basename "${wrapper}")"
done

if [[ "${DRY_RUN}" -eq 0 ]]; then
	normalize_remote_tree_permissions "${STACK_ROOT_REMOTE}/themes/mrn-base-stack" "stack theme"
	normalize_remote_tree_permissions "${LIVE_SITE_THEME_DIR}" "live theme" "${REMOTE_SYNC_USER}"
	normalize_remote_tree_permissions "${STACK_ROOT_REMOTE}/shared" "stack shared runtime"
	normalize_remote_tree_permissions "${LIVE_SITE_ROOT}/wp-content/shared" "live shared runtime" "${REMOTE_SYNC_USER}"
	normalize_remote_tree_permissions "${STACK_ROOT_REMOTE}/mu-plugins" "stack mu-plugins"
	normalize_remote_tree_permissions "${LIVE_SITE_ROOT}/wp-content/mu-plugins" "live mu-plugins" "${REMOTE_SYNC_USER}"

	verify_remote_tree_file_modes "${STACK_ROOT_REMOTE}/themes/mrn-base-stack" "stack theme"
	verify_remote_tree_file_modes "${LIVE_SITE_THEME_DIR}" "live theme" "${REMOTE_SYNC_USER}"
	verify_remote_tree_file_modes "${STACK_ROOT_REMOTE}/shared" "stack shared runtime"
	verify_remote_tree_file_modes "${LIVE_SITE_ROOT}/wp-content/shared" "live shared runtime" "${REMOTE_SYNC_USER}"
	verify_remote_tree_file_modes "${STACK_ROOT_REMOTE}/mu-plugins" "stack mu-plugins"
	verify_remote_tree_file_modes "${LIVE_SITE_ROOT}/wp-content/mu-plugins" "live mu-plugins" "${REMOTE_SYNC_USER}"

	if [[ "${LIVE_SITE_ACTIVE_STYLESHEET}" != "${LIVE_SITE_THEME_SLUG}" ]]; then
		run_remote "wp theme activate '${LIVE_SITE_THEME_SLUG}' --path='${LIVE_SITE_ROOT}'" >/dev/null
	fi

	run_remote "cd '${LIVE_SITE_ROOT}' && wp option get stylesheet --path='${LIVE_SITE_ROOT}' && printf '\n---\n' && wp theme list --path='${LIVE_SITE_ROOT}' --format=table 2>/dev/null | grep -E 'name|${LIVE_SITE_THEME_SLUG}|mrn-base-stack'"
fi

echo "Feature deploy sync completed."
