#!/usr/bin/env bash
set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  qa-local-stack-site.sh [site-path]

Default:
  /Users/khofmeyer/Local Sites/mrn-plugin-stack/app/public
EOF
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
	usage
	exit 0
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
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

echo "Local site path: ${SITE_PATH}"
echo "WP-CLI binary: ${WP_BIN}"

run_wp option get home
run_wp eval 'echo wp_get_theme()->get("Name") . "|" . wp_get_theme()->get("Version") . PHP_EOL;'
run_wp eval 'echo function_exists("mrn_base_stack_get_builder_add_row_layout_menu_items") ? "builder-menu-helper:loaded\n" : "builder-menu-helper:missing\n";'
run_wp eval 'if (! function_exists("acf_get_field")) { echo "acf:missing\n"; return; } $field = acf_get_field("field_mrn_page_content_rows"); echo (is_array($field) && ! empty($field["layouts"])) ? "builder-layouts:loaded\n" : "builder-layouts:missing\n";'
