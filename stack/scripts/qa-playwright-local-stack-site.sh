#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  qa-playwright-local-stack-site.sh [site-path]

Default site path:
  /Users/khofmeyer/Local Sites/mrn-plugin-stack/app/public

Environment:
  MRN_WP_ADMIN_USER   Optional WordPress admin username for editor smoke tests.
  MRN_WP_ADMIN_PASS   Optional WordPress admin password for editor smoke tests.

Notes:
  - Public-site smoke tests always run.
  - Admin builder coverage auto-provisions a local-only QA admin when credentials are not provided.
EOF
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
	usage
	exit 0
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
THEME_DIR="${REPO_ROOT}/stack/themes/mrn-base-stack"
SITE_PATH="${1:-/Users/khofmeyer/Local Sites/mrn-plugin-stack/app/public}"
LOCAL_WP="/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp"

if command -v wp >/dev/null 2>&1; then
	WP_BIN="$(command -v wp)"
elif [[ -x "${LOCAL_WP}" ]]; then
	WP_BIN="${LOCAL_WP}"
else
	echo "Could not find wp-cli. Install wp or use Local's bundled binary." >&2
	exit 1
fi

if [[ ! -d "${SITE_PATH}" ]]; then
	echo "Site path not found: ${SITE_PATH}" >&2
	exit 1
fi

if [[ ! -f "${THEME_DIR}/package.json" ]]; then
	echo "Theme package.json not found: ${THEME_DIR}/package.json" >&2
	exit 1
fi

if [[ ! -d "${THEME_DIR}/node_modules/@playwright/test" ]]; then
	echo "Missing Playwright dependency. Run 'cd ${THEME_DIR} && npm install' first." >&2
	exit 1
fi

if [[ ! -d "${HOME}/Library/Caches/ms-playwright" ]]; then
	echo "Playwright browsers are not installed. Run 'cd ${THEME_DIR} && npx playwright install chromium' first." >&2
	exit 1
fi

run_wp() {
	local output
	local exit_code

	set +e
	output="$(WP_CLI_PHP_ARGS='-d error_reporting=6143' "${WP_BIN}" --path="${SITE_PATH}" "$@" 2>&1)"
	exit_code=$?
	set -e

	printf '%s\n' "${output}" | sed \
		-e '/^Deprecated: Case statements followed by a semicolon/d' \
		-e '/^PHP Deprecated:  Case statements followed by a semicolon/d'

	return "${exit_code}"
}

ensure_local_admin_credentials() {
	local qa_user
	local qa_pass
	local qa_email

	qa_user="${MRN_WP_ADMIN_USER:-codex_qa_admin}"
	qa_pass="${MRN_WP_ADMIN_PASS:-}"
	qa_email="codex-qa-admin@local.test"

	if [[ -z "${qa_pass}" ]]; then
		set +o pipefail
		qa_pass="$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 24)"
		set -o pipefail
	fi

	if run_wp user get "${qa_user}" --field=ID >/dev/null; then
		run_wp user update "${qa_user}" --role=administrator --user_pass="${qa_pass}" >/dev/null
	else
		run_wp user create "${qa_user}" "${qa_email}" --role=administrator --user_pass="${qa_pass}" >/dev/null
	fi

	MRN_WP_ADMIN_USER="${qa_user}"
	MRN_WP_ADMIN_PASS="${qa_pass}"
}

BASE_URL="$(run_wp option get home | tail -n 1)"
SAMPLE_PAGE_PATH="$(run_wp eval '$page = get_page_by_path("sample-page"); echo $page ? wp_make_link_relative( get_permalink( $page ) ) : "/sample-page/";' | tail -n 1)"
SAMPLE_PAGE_EDIT_PATH="$(run_wp eval '$page = get_page_by_path("sample-page"); echo $page ? "/wp-admin/post.php?post=" . (int) $page->ID . "&action=edit" : "";' | tail -n 1)"
SETTINGS_PAGE_PATH="$(run_wp eval 'echo is_plugin_active("mrn-config-helper/mrn-config-helper.php") ? "/wp-admin/options-general.php?page=mrn-config-helper" : "";' | tail -n 1)"

echo "Playwright theme dir: ${THEME_DIR}"
echo "Local site path: ${SITE_PATH}"
echo "Base URL: ${BASE_URL}"
echo "Sample page path: ${SAMPLE_PAGE_PATH}"

if [[ -n "${SAMPLE_PAGE_EDIT_PATH}" ]]; then
	ensure_local_admin_credentials
	echo "Admin builder smoke: enabled"
else
	echo "Admin builder smoke: skipped (sample page editor path not available)"
fi

if [[ -n "${SETTINGS_PAGE_PATH}" ]]; then
	ensure_local_admin_credentials
	echo "Settings page smoke: enabled"
else
	echo "Settings page smoke: skipped (mrn-config-helper inactive)"
fi

cd "${THEME_DIR}"

env -u FORCE_COLOR -u NO_COLOR \
	MRN_PLAYWRIGHT_BASE_URL="${BASE_URL}" \
	MRN_SAMPLE_PAGE_PATH="${SAMPLE_PAGE_PATH}" \
	MRN_WP_ADMIN_USER="${MRN_WP_ADMIN_USER:-}" \
	MRN_WP_ADMIN_PASS="${MRN_WP_ADMIN_PASS:-}" \
	MRN_SAMPLE_PAGE_EDIT_PATH="${SAMPLE_PAGE_EDIT_PATH}" \
	MRN_SETTINGS_PAGE_PATH="${SETTINGS_PAGE_PATH}" \
	MRN_SETTINGS_TOOLBAR_SELECTOR=".mrn-sticky-save-bar" \
	MRN_SETTINGS_CONTENT_SELECTOR="#wpcontent .wrap" \
	npx playwright test
