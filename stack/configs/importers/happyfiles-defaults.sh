#!/usr/bin/env bash
set -euo pipefail

SITE_PATH="${SITE_PATH:-}"
SITE_USER="${SITE_USER:-}"
WP_PATH="${WP_PATH:-}"
WP_SKIP_PLUGINS="${STACK_IMPORTER_SKIP_PLUGINS:-}"

if [[ -z "${SITE_PATH}" || -z "${SITE_USER}" || -z "${WP_PATH}" ]]; then
  echo "HappyFiles defaults importer context missing (SITE_PATH/SITE_USER/WP_PATH). Skipping importer."
  exit 0
fi

run_wp() {
  local -a args
  args=("$@")
  if [[ -n "${WP_SKIP_PLUGINS}" ]]; then
    sudo -u "${SITE_USER}" wp --path="${WP_PATH}" --skip-plugins="${WP_SKIP_PLUGINS}" "${args[@]}"
  else
    sudo -u "${SITE_USER}" wp --path="${WP_PATH}" "${args[@]}"
  fi
}

if ! run_wp plugin is-active happyfiles-pro >/dev/null 2>&1 && ! run_wp plugin is-active happyfiles >/dev/null 2>&1; then
  echo "HappyFiles is not active. Skipping HappyFiles defaults."
  exit 0
fi

code="$(cat <<'PHP'
$access = get_option( 'happyfiles_folder_access', [] );
if ( ! is_array( $access ) ) {
    $access = [];
}

$access['editor'] = 'full';
update_option( 'happyfiles_folder_access', $access );

echo "HappyFiles folder access default applied: editor=full\n";
PHP
)"

run_wp eval "${code}"
