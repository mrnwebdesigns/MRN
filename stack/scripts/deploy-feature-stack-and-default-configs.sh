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
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
LIVE_SITE_HOSTNAME="${MRN_DEFAULT_CONFIGS_HOSTNAME:-default-configs.mrndev.io}"
LIVE_SITE_ROOT_OVERRIDE="${MRN_DEFAULT_CONFIGS_LIVE_SITE_ROOT:-}"
LIVE_SITE_THEME_SLUG="${MRN_DEFAULT_CONFIGS_THEME_SLUG:-default-configs}"
LIVE_SITE_THEME_NAME="${MRN_DEFAULT_CONFIGS_THEME_NAME:-default configs}"
LIVE_SITE_TEXT_DOMAIN="${MRN_DEFAULT_CONFIGS_TEXT_DOMAIN:-default-configs}"

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
LIVE_SITE_ROOT=""
LIVE_SITE_USER=""
LIVE_SITE_SSH_LOGIN=""
LOCAL_THEME_DIR="${REPO_ROOT}/stack/themes/mrn-base-stack"
LOCAL_STACK_MU_DIR="${REPO_ROOT}/stack/mu-plugins"
LOCAL_MU_SOURCE_ROOT="${REPO_ROOT}/mu-plugins"
LOCAL_SHARED_DIR="${REPO_ROOT}/shared"
LIVE_SITE_THEME_DIR=""
LIVE_SITE_ACTIVE_STYLESHEET=""
STACK_SYNC_USER="${SSH_HOST%%@*}"

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
	local remote_host="$1"
	local command="$2"
	ssh "${remote_host}" "${command}"
}

get_remote_tree_acl_entries() {
	local remote_host="$1"
	local path="$2"

	if ! run_remote "${remote_host}" "command -v getfacl >/dev/null 2>&1"; then
		return 0
	fi

	run_remote "${remote_host}" "getfacl -R -cp '${path}' 2>/dev/null | grep -E '^(default:|user:[^:]+:|group:[^:]+:)' | head -n 20" | tr -d '\r'
}

normalize_remote_tree_acls() {
	local remote_host="$1"
	local path="$2"
	local label="$3"
	local residual_acls=""

	if ! run_remote "${remote_host}" "command -v setfacl >/dev/null 2>&1"; then
		echo "WARNING: setfacl is not available on ${remote_host}; skipping ACL normalization for ${label}." >&2
		return 0
	fi

	echo "Removing inherited ACLs from ${label}..."
	run_remote "${remote_host}" "setfacl -R -b '${path}'"
	run_remote "${remote_host}" "find '${path}' -type d -print0 | xargs -0 -r -n 50 setfacl -k"

	residual_acls="$(get_remote_tree_acl_entries "${remote_host}" "${path}" || true)"
	if [[ -n "${residual_acls}" ]]; then
		echo "Residual ACLs detected for ${label}; retrying ACL cleanup..."
		run_remote "${remote_host}" "setfacl -R -b '${path}'"
		run_remote "${remote_host}" "find '${path}' -type d -print0 | xargs -0 -r -n 1 setfacl -k"

		residual_acls="$(get_remote_tree_acl_entries "${remote_host}" "${path}" || true)"
		if [[ -n "${residual_acls}" ]]; then
			echo "ERROR: ${label} still has residual ACLs after cleanup." >&2
			echo "${residual_acls}" >&2
			return 1
		fi
	fi
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

verify_remote_tree_user_absent() {
	local remote_host="$1"
	local path="$2"
	local label="$3"
	local forbidden_user="$4"
	local matches=""

	matches="$(run_remote "${remote_host}" "find '${path}' -not -path '*/.git/*' -user '${forbidden_user}' -print | head -n 20" | tr -d '\r')"
	if [[ -n "${matches}" ]]; then
		echo "ERROR: ${label} still contains files owned by ${forbidden_user}." >&2
		echo "${matches}" >&2
		return 1
	fi
}

verify_remote_path_owner_mode() {
	local remote_host="$1"
	local path="$2"
	local expected_user="$3"
	local expected_mode="$4"
	local label="$5"
	local stat_output owner mode

	stat_output="$(run_remote "${remote_host}" "stat -c '%U %a %n' '${path}'" | tr -d '\r')"
	owner="$(printf '%s\n' "${stat_output}" | awk 'NR==1 { print $1 }')"
	mode="$(printf '%s\n' "${stat_output}" | awk 'NR==1 { print $2 }')"

	if [[ "${owner}" != "${expected_user}" || "${mode}" != "${expected_mode}" ]]; then
		echo "ERROR: ${label} expected ${expected_user}/${expected_mode} but saw ${stat_output}" >&2
		return 1
	fi
}

echo "Deploying stack feature surfaces to ${SSH_HOST}..."

PREP_ARGS=(
	--site-hostname "${LIVE_SITE_HOSTNAME}"
	--discovery-ssh-host "${SSH_HOST}"
)

if [[ "${DRY_RUN}" -eq 1 ]]; then
	PREP_ARGS+=(--skip-backup)
else
	PREP_ARGS+=(--backup-label "stack-feature-$(printf '%s' "${LIVE_SITE_HOSTNAME}" | tr -c '[:alnum:]._- ' '-' | tr ' ' '-')-$(date +%Y%m%d%H%M%S)")
fi

PREP_OUTPUT="$("${SCRIPT_DIR}/preflight-live-site-deploy.sh" "${PREP_ARGS[@]}")"

while IFS='=' read -r key value; do
	case "${key}" in
		SITE_USER) LIVE_SITE_USER="${value}" ;;
		SITE_ROOT) LIVE_SITE_ROOT="${value}" ;;
		SSH_LOGIN) LIVE_SITE_SSH_LOGIN="${value}" ;;
	esac
done <<< "${PREP_OUTPUT}"

if [[ -z "${LIVE_SITE_USER}" || -z "${LIVE_SITE_ROOT}" || -z "${LIVE_SITE_SSH_LOGIN}" ]]; then
	echo "Live-site preflight did not return complete site-owner details." >&2
	exit 1
fi

if [[ -n "${LIVE_SITE_ROOT_OVERRIDE}" && "${LIVE_SITE_ROOT_OVERRIDE}" != "${LIVE_SITE_ROOT}" ]]; then
	echo "Resolved live site root (${LIVE_SITE_ROOT}) does not match MRN_DEFAULT_CONFIGS_LIVE_SITE_ROOT (${LIVE_SITE_ROOT_OVERRIDE})." >&2
	exit 1
fi

LIVE_SITE_THEME_DIR="${LIVE_SITE_ROOT}/wp-content/themes/${LIVE_SITE_THEME_SLUG}"
LIVE_SITE_ACTIVE_STYLESHEET="$(ssh "${LIVE_SITE_SSH_LOGIN}" "wp option get stylesheet --path='${LIVE_SITE_ROOT}' 2>/dev/null" | tr -d '\r' | xargs || true)"

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
	"${LIVE_SITE_SSH_LOGIN}:${LIVE_SITE_THEME_DIR}/" \
	"${THEME_EXCLUDES[@]}"

run_rsync \
	"${LOCAL_SHARED_DIR}/" \
	"${SSH_HOST}:${STACK_ROOT_REMOTE}/shared/" \
	"${COMMON_DIR_EXCLUDES[@]}"

run_rsync \
	"${LOCAL_SHARED_DIR}/" \
	"${LIVE_SITE_SSH_LOGIN}:${LIVE_SITE_ROOT}/wp-content/shared/" \
	"${COMMON_DIR_EXCLUDES[@]}"

for slug in "${MU_PLUGIN_DIRS[@]}"; do
	run_rsync \
	"${LOCAL_MU_SOURCE_ROOT}/${slug}/" \
	"${SSH_HOST}:${STACK_ROOT_REMOTE}/mu-plugins/${slug}/" \
	"${COMMON_DIR_EXCLUDES[@]}"

	run_rsync \
		"${LOCAL_MU_SOURCE_ROOT}/${slug}/" \
		"${LIVE_SITE_SSH_LOGIN}:${LIVE_SITE_ROOT}/wp-content/mu-plugins/${slug}/" \
		"${COMMON_DIR_EXCLUDES[@]}"
done

for wrapper in "${LOCAL_STACK_MU_DIR}"/mrn-*.php; do
	[[ -f "${wrapper}" ]] || continue
	run_rsync \
		"${wrapper}" \
		"${SSH_HOST}:${STACK_ROOT_REMOTE}/mu-plugins/$(basename "${wrapper}")"
	run_rsync \
		"${wrapper}" \
		"${LIVE_SITE_SSH_LOGIN}:${LIVE_SITE_ROOT}/wp-content/mu-plugins/$(basename "${wrapper}")"
done

if [[ "${DRY_RUN}" -eq 0 ]]; then
	run_remote "${LIVE_SITE_SSH_LOGIN}" "perl -0pi -e 's/^Theme Name:\\s*.*\$/Theme Name: ${LIVE_SITE_THEME_NAME}/m; s/^Text Domain:\\s*.*\$/Text Domain: ${LIVE_SITE_TEXT_DOMAIN}/m;' '${LIVE_SITE_THEME_DIR}/style.css'"
	run_remote "${LIVE_SITE_SSH_LOGIN}" "rm -rf '${LIVE_SITE_THEME_DIR}/test-results' '${LIVE_SITE_THEME_DIR}/playwright-report'"

	normalize_remote_tree_permissions "${SSH_HOST}" "${STACK_ROOT_REMOTE}/themes/mrn-base-stack" "stack theme"
	normalize_remote_tree_acls "${LIVE_SITE_SSH_LOGIN}" "${LIVE_SITE_THEME_DIR}" "live theme"
	normalize_remote_tree_permissions "${LIVE_SITE_SSH_LOGIN}" "${LIVE_SITE_THEME_DIR}" "live theme"
	normalize_remote_tree_permissions "${SSH_HOST}" "${STACK_ROOT_REMOTE}/shared" "stack shared runtime"
	normalize_remote_tree_acls "${LIVE_SITE_SSH_LOGIN}" "${LIVE_SITE_ROOT}/wp-content/shared" "live shared runtime"
	normalize_remote_tree_permissions "${LIVE_SITE_SSH_LOGIN}" "${LIVE_SITE_ROOT}/wp-content/shared" "live shared runtime"
	normalize_remote_tree_permissions "${SSH_HOST}" "${STACK_ROOT_REMOTE}/mu-plugins" "stack mu-plugins"
	normalize_remote_tree_acls "${LIVE_SITE_SSH_LOGIN}" "${LIVE_SITE_ROOT}/wp-content/mu-plugins" "live mu-plugins"
	normalize_remote_tree_permissions "${LIVE_SITE_SSH_LOGIN}" "${LIVE_SITE_ROOT}/wp-content/mu-plugins" "live mu-plugins"

	verify_remote_tree_file_modes "${SSH_HOST}" "${STACK_ROOT_REMOTE}/themes/mrn-base-stack" "stack theme"
	verify_remote_tree_file_modes "${LIVE_SITE_SSH_LOGIN}" "${LIVE_SITE_THEME_DIR}" "live theme"
	verify_remote_tree_file_modes "${SSH_HOST}" "${STACK_ROOT_REMOTE}/shared" "stack shared runtime"
	verify_remote_tree_file_modes "${LIVE_SITE_SSH_LOGIN}" "${LIVE_SITE_ROOT}/wp-content/shared" "live shared runtime"
	verify_remote_tree_file_modes "${SSH_HOST}" "${STACK_ROOT_REMOTE}/mu-plugins" "stack mu-plugins"
	verify_remote_tree_file_modes "${LIVE_SITE_SSH_LOGIN}" "${LIVE_SITE_ROOT}/wp-content/mu-plugins" "live mu-plugins"

	verify_remote_tree_user_absent "${LIVE_SITE_SSH_LOGIN}" "${LIVE_SITE_THEME_DIR}" "live theme" "${STACK_SYNC_USER}"
	verify_remote_tree_user_absent "${LIVE_SITE_SSH_LOGIN}" "${LIVE_SITE_ROOT}/wp-content/shared" "live shared runtime" "${STACK_SYNC_USER}"
	verify_remote_tree_user_absent "${LIVE_SITE_SSH_LOGIN}" "${LIVE_SITE_ROOT}/wp-content/mu-plugins" "live mu-plugins" "${STACK_SYNC_USER}"

	verify_remote_path_owner_mode "${LIVE_SITE_SSH_LOGIN}" "${LIVE_SITE_THEME_DIR}/style.css" "${LIVE_SITE_USER}" "644" "live theme style.css"
	verify_remote_path_owner_mode "${LIVE_SITE_SSH_LOGIN}" "${LIVE_SITE_ROOT}/wp-content/shared/mrn-sticky-settings-toolbar.php" "${LIVE_SITE_USER}" "644" "live shared runtime representative file"
	verify_remote_path_owner_mode "${LIVE_SITE_SSH_LOGIN}" "${LIVE_SITE_ROOT}/wp-content/mu-plugins/mrn-site-colors/mrn-site-colors.php" "${LIVE_SITE_USER}" "644" "live mu-plugin representative file"

	if [[ "${LIVE_SITE_ACTIVE_STYLESHEET}" != "${LIVE_SITE_THEME_SLUG}" ]]; then
		run_remote "${LIVE_SITE_SSH_LOGIN}" "wp theme activate '${LIVE_SITE_THEME_SLUG}' --path='${LIVE_SITE_ROOT}'" >/dev/null
	fi

	run_remote "${LIVE_SITE_SSH_LOGIN}" "cd '${LIVE_SITE_ROOT}' && wp option get stylesheet --path='${LIVE_SITE_ROOT}' && printf '\n---\n' && wp theme list --path='${LIVE_SITE_ROOT}' --format=table 2>/dev/null | grep -E 'name|${LIVE_SITE_THEME_SLUG}|mrn-base-stack'"
fi

echo "Feature deploy sync completed."
