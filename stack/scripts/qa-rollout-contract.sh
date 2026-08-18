#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  qa-rollout-contract.sh [--ssh-host <ssh-host>] [--live-site-root <path>] [--stack-root-remote <path>] [--expected-theme-slug <slug>]

Default checks:
  - required Stack plugins are present in the local plugin manifest
  - standalone sticky-toolbar fallback matches the canonical shared source
  - local theme version matches packaged zip version
  - stack MU wrapper versions match the components they load
  - stack shared runtime exists on the server
  - live site shared runtime exists when a live rollout target is configured or discoverable
  - stack/live Updraft local retention MU plugin files exist when a live rollout target is configured or discoverable
  - live site schedules the Updraft local retention cron hook when a live rollout target is configured or discoverable
  - live active stylesheet resolves when a live rollout target is configured or discoverable
  - live active theme version matches local source when a live rollout target is configured or discoverable
  - case study files and CPT exist when local source includes them
EOF
}

SSH_HOST="${MRN_ROLLOUT_SSH_HOST:-mrndev}"
STACK_ROOT_REMOTE="/home/mrndev-stack-manager/stack"
LIVE_SITE_ROOT="${MRN_DEFAULT_CONFIGS_LIVE_SITE_ROOT:-}"
EXPECTED_THEME_SLUG="${MRN_DEFAULT_CONFIGS_THEME_SLUG:-default-configs}"
LIVE_SITE_ROOT_EXPLICIT=0

if [[ -n "${MRN_DEFAULT_CONFIGS_LIVE_SITE_ROOT:-}" ]]; then
	LIVE_SITE_ROOT_EXPLICIT=1
fi

while [[ $# -gt 0 ]]; do
	case "$1" in
		--ssh-host)
			SSH_HOST="${2:-}"
			shift 2
			;;
		--live-site-root)
			LIVE_SITE_ROOT="${2:-}"
			LIVE_SITE_ROOT_EXPLICIT=1
			shift 2
			;;
		--stack-root-remote)
			STACK_ROOT_REMOTE="${2:-}"
			shift 2
			;;
		--expected-theme-slug)
			EXPECTED_THEME_SLUG="${2:-}"
			shift 2
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
LOCAL_THEME_DIR="${REPO_ROOT}/stack/themes/mrn-base-stack"
LOCAL_THEME_ZIP="${REPO_ROOT}/releases/stack/mrn-base-stack.zip"
LOCAL_PLUGINS_MANIFEST="${REPO_ROOT}/stack/manifests/plugins.txt"
LOCAL_STICKY_HELPER="${REPO_ROOT}/shared/mrn-sticky-settings-toolbar.php"
LOCAL_STICKY_FALLBACK="${REPO_ROOT}/plugins/mrn-universal-sticky-bar/includes/mrn-sticky-settings-toolbar.php"

REMOTE_SHARED_DIR="${STACK_ROOT_REMOTE}/shared"
REMOTE_STACK_RETENTION_WRAPPER="${STACK_ROOT_REMOTE}/mu-plugins/mrn-updraft-local-retention.php"
REMOTE_STACK_RETENTION_MAIN="${STACK_ROOT_REMOTE}/mu-plugins/mrn-updraft-local-retention/mrn-updraft-local-retention.php"
REMOTE_ACTIVE_THEME_SLUG=""
REMOTE_ACTIVE_THEME_DIR=""

fail() {
	echo "FAIL: $*" >&2
	exit 1
}

pass() {
	echo "PASS: $*"
}

require_command() {
	local cmd="$1"
	command -v "${cmd}" >/dev/null 2>&1 || fail "Required command not found: ${cmd}"
}

extract_theme_version() {
	local file_path="$1"
	sed -n 's/^Version:[[:space:]]*//p' "${file_path}" | head -n1 | xargs
}

remote_file_exists() {
	local file_path="$1"
	ssh "${SSH_HOST}" "test -f '${file_path}'"
}

remote_dir_exists() {
	local dir_path="$1"
	ssh "${SSH_HOST}" "test -d '${dir_path}'"
}

require_command ssh
require_command unzip

[[ -f "${LOCAL_PLUGINS_MANIFEST}" ]] || fail "Local plugin manifest not found: ${LOCAL_PLUGINS_MANIFEST}"
grep -Fxq '/home/mrndev-stack-manager/stack/packages/mrn-universal-sticky-bar.zip' "${LOCAL_PLUGINS_MANIFEST}" || fail "Required Stack plugin missing from manifest: mrn-universal-sticky-bar"
pass "Required Stack plugin manifest includes mrn-universal-sticky-bar"

[[ -f "${LOCAL_STICKY_HELPER}" ]] || fail "Canonical sticky-toolbar helper not found: ${LOCAL_STICKY_HELPER}"
[[ -f "${LOCAL_STICKY_FALLBACK}" ]] || fail "Standalone sticky-toolbar fallback not found: ${LOCAL_STICKY_FALLBACK}"
cmp -s "${LOCAL_STICKY_HELPER}" "${LOCAL_STICKY_FALLBACK}" || fail "Standalone sticky-toolbar fallback differs from the canonical shared source"
pass "Standalone sticky-toolbar fallback matches the canonical shared source"

[[ -d "${LOCAL_THEME_DIR}" ]] || fail "Local theme directory not found: ${LOCAL_THEME_DIR}"
[[ -f "${LOCAL_THEME_DIR}/style.css" ]] || fail "Local theme style.css not found."
[[ -f "${LOCAL_THEME_ZIP}" ]] || fail "Local theme zip not found: ${LOCAL_THEME_ZIP}"

LOCAL_THEME_VERSION="$(extract_theme_version "${LOCAL_THEME_DIR}/style.css")"
[[ -n "${LOCAL_THEME_VERSION}" ]] || fail "Could not read local theme version from style.css"
pass "Local theme version is ${LOCAL_THEME_VERSION}"

ZIP_THEME_VERSION="$(
	{
		unzip -p "${LOCAL_THEME_ZIP}" style.css 2>/dev/null ||
		unzip -p "${LOCAL_THEME_ZIP}" "mrn-base-stack/style.css" 2>/dev/null
	} | sed -n 's/^Version:[[:space:]]*//p' | head -n1 | xargs
)"
[[ -n "${ZIP_THEME_VERSION}" ]] || fail "Could not read packaged theme version from ${LOCAL_THEME_ZIP}"
[[ "${ZIP_THEME_VERSION}" == "${LOCAL_THEME_VERSION}" ]] || fail "Packaged theme version (${ZIP_THEME_VERSION}) does not match local source (${LOCAL_THEME_VERSION})"
pass "Packaged theme zip version matches local source"

# Each stack MU wrapper declares its own Version header, and MainWP reads that
# header rather than the component it loads. A wrapper left behind its component
# reports a stale version to every fleet inventory, so treat the mismatch as a
# release-blocking drift rather than cosmetic.
WRAPPER_DRIFT=""
for wrapper in "${REPO_ROOT}/stack/mu-plugins"/*.php; do
	[[ -f "${wrapper}" ]] || continue
	wrapper_slug="$(basename "${wrapper}" .php)"
	component="${REPO_ROOT}/mu-plugins/${wrapper_slug}/${wrapper_slug}.php"
	[[ -f "${component}" ]] || continue
	wrapper_version="$(sed -n 's/^[ *]*Version:[[:space:]]*//p' "${wrapper}" | head -n1 | tr -d '\r' | xargs)"
	component_version="$(sed -n 's/^[ *]*Version:[[:space:]]*//p' "${component}" | head -n1 | tr -d '\r' | xargs)"
	[[ -n "${component_version}" ]] || continue
	if [[ "${wrapper_version}" != "${component_version}" ]]; then
		WRAPPER_DRIFT+="${wrapper_slug} (${wrapper_version:-none} vs ${component_version}) "
	fi
done
[[ -z "${WRAPPER_DRIFT}" ]] || fail "Stack MU wrapper versions behind their components: ${WRAPPER_DRIFT}"
pass "Stack MU wrapper versions match their components"

remote_dir_exists "${REMOTE_SHARED_DIR}" || fail "Remote stack shared runtime missing: ${REMOTE_SHARED_DIR}"
remote_file_exists "${REMOTE_SHARED_DIR}/mrn-sticky-settings-toolbar.php" || fail "Remote stack shared runtime missing mrn-sticky-settings-toolbar.php"
remote_file_exists "${REMOTE_SHARED_DIR}/mrn-universal-sticky-bar-assets.php" || fail "Remote stack shared runtime missing mrn-universal-sticky-bar-assets.php"
pass "Remote stack shared runtime exists"

remote_file_exists "${REMOTE_STACK_RETENTION_WRAPPER}" || fail "Remote stack MU wrapper missing: ${REMOTE_STACK_RETENTION_WRAPPER}"
remote_file_exists "${REMOTE_STACK_RETENTION_MAIN}" || fail "Remote stack MU plugin missing: ${REMOTE_STACK_RETENTION_MAIN}"
pass "Remote stack Updraft local retention MU plugin exists"

if [[ -z "${LIVE_SITE_ROOT}" ]]; then
	LIVE_SITE_ROOT="$(
		ssh "${SSH_HOST}" "find /home -maxdepth 3 -type d -path '*/htdocs/default-configs.mrndev.io' 2>/dev/null | head -n1" | tr -d '\r' | xargs
	)"
fi

if [[ -z "${LIVE_SITE_ROOT}" ]]; then
	pass "No default-configs live site found; live rollout checks skipped"
	echo "Rollout contract verified for stack runtime (no default-configs live target installed)"
	exit 0
fi

if [[ "${LIVE_SITE_ROOT_EXPLICIT}" == "1" ]]; then
	remote_dir_exists "${LIVE_SITE_ROOT}" || fail "Configured live site root missing: ${LIVE_SITE_ROOT}"
fi

REMOTE_LIVE_SHARED_DIR="${LIVE_SITE_ROOT}/wp-content/shared"
REMOTE_LIVE_RETENTION_WRAPPER="${LIVE_SITE_ROOT}/wp-content/mu-plugins/mrn-updraft-local-retention.php"
REMOTE_LIVE_RETENTION_MAIN="${LIVE_SITE_ROOT}/wp-content/mu-plugins/mrn-updraft-local-retention/mrn-updraft-local-retention.php"

remote_dir_exists "${REMOTE_LIVE_SHARED_DIR}" || fail "Live site shared runtime missing: ${REMOTE_LIVE_SHARED_DIR}"
remote_file_exists "${REMOTE_LIVE_SHARED_DIR}/mrn-sticky-settings-toolbar.php" || fail "Live site shared runtime missing mrn-sticky-settings-toolbar.php"
pass "Live site shared runtime exists"

remote_file_exists "${REMOTE_LIVE_RETENTION_WRAPPER}" || fail "Live site MU wrapper missing: ${REMOTE_LIVE_RETENTION_WRAPPER}"
remote_file_exists "${REMOTE_LIVE_RETENTION_MAIN}" || fail "Live site MU plugin missing: ${REMOTE_LIVE_RETENTION_MAIN}"
pass "Live site Updraft local retention MU plugin exists"

REMOTE_RETENTION_CRON_SCHEDULE="$(
	ssh "${SSH_HOST}" "wp eval --path='${LIVE_SITE_ROOT}' '\$hook=\"mrn_updraft_local_retention_cleanup\"; \$event=function_exists(\"wp_get_scheduled_event\") ? wp_get_scheduled_event(\$hook) : false; if (!\$event || !isset(\$event->schedule)) { echo \"missing\"; } else { echo \$event->schedule; }' 2>/dev/null" | tr -d '\r' | xargs
)"
[[ -n "${REMOTE_RETENTION_CRON_SCHEDULE}" && "${REMOTE_RETENTION_CRON_SCHEDULE}" != "missing" ]] || fail "Live site is missing scheduled hook: mrn_updraft_local_retention_cleanup"
[[ "${REMOTE_RETENTION_CRON_SCHEDULE}" == "daily" ]] || fail "Live retention hook recurrence is ${REMOTE_RETENTION_CRON_SCHEDULE}, expected daily"
pass "Live site schedules mrn_updraft_local_retention_cleanup daily"

REMOTE_ACTIVE_THEME_SLUG="$(
	ssh "${SSH_HOST}" "wp option get stylesheet --path='${LIVE_SITE_ROOT}' 2>/dev/null" | tr -d '\r' | xargs
)"
[[ -n "${REMOTE_ACTIVE_THEME_SLUG}" ]] || fail "Could not resolve active stylesheet for ${LIVE_SITE_ROOT}"
[[ "${REMOTE_ACTIVE_THEME_SLUG}" == "${EXPECTED_THEME_SLUG}" ]] || fail "Live active stylesheet (${REMOTE_ACTIVE_THEME_SLUG}) does not match expected rollout theme (${EXPECTED_THEME_SLUG})"
REMOTE_ACTIVE_THEME_DIR="${LIVE_SITE_ROOT}/wp-content/themes/${REMOTE_ACTIVE_THEME_SLUG}"
pass "Live active stylesheet is ${REMOTE_ACTIVE_THEME_SLUG}"

remote_dir_exists "${REMOTE_ACTIVE_THEME_DIR}" || fail "Active live theme directory missing: ${REMOTE_ACTIVE_THEME_DIR}"

REMOTE_THEME_VERSION="$(
	ssh "${SSH_HOST}" "sed -n 's/^Version:[[:space:]]*//p' '${REMOTE_ACTIVE_THEME_DIR}/style.css' 2>/dev/null | head -n1" | tr -d '\r' | xargs
)"
[[ -n "${REMOTE_THEME_VERSION}" ]] || fail "Could not read live theme version from ${REMOTE_ACTIVE_THEME_DIR}/style.css"
[[ "${REMOTE_THEME_VERSION}" == "${LOCAL_THEME_VERSION}" ]] || fail "Live active theme version (${REMOTE_THEME_VERSION}) does not match local source (${LOCAL_THEME_VERSION})"
pass "Live active theme version matches local source"

if [[ -f "${LOCAL_THEME_DIR}/inc/case-study.php" ]]; then
	remote_file_exists "${REMOTE_ACTIVE_THEME_DIR}/inc/case-study.php" || fail "Live active theme missing inc/case-study.php"
	remote_file_exists "${REMOTE_ACTIVE_THEME_DIR}/template-parts/content-case_study.php" || fail "Live active theme missing template-parts/content-case_study.php"
	pass "Live active theme includes Case Study files"

	REMOTE_CASE_STUDY_POST_TYPE="$(
		ssh "${SSH_HOST}" "wp post-type list --path='${LIVE_SITE_ROOT}' --field=name 2>/dev/null | grep -x 'case_study' || true" | tr -d '\r' | xargs
	)"
	[[ "${REMOTE_CASE_STUDY_POST_TYPE}" == "case_study" ]] || fail "Live site did not register case_study post type"
	pass "Live site registers case_study post type"
fi

echo "Rollout contract verified for ${LIVE_SITE_ROOT}"
