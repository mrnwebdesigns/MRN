#!/usr/bin/env bash
set -euo pipefail

STACK_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGINS_FILE="${STACK_ROOT}/manifests/plugins.txt"
THEMES_FILE="${STACK_ROOT}/manifests/themes.txt"
LICENSES_FILE="${STACK_ROOT}/manifests/licenses.txt"
IMPORTERS_DIR="${STACK_ROOT}/configs/importers"
EXPORTS_DIR="${STACK_ROOT}/configs/exports"
SECRETS_DIR="${STACK_ROOT}/secrets"
MU_PLUGINS_SOURCE_DIR="${STACK_ROOT}/mu-plugins"
MARKER_NAME=".mrn_bootstrapped"
DEFAULT_THEME_STARTER="underscores"
SITE_THEME_CLONE_SOURCE_SLUG="${STACK_SITE_THEME_CLONE_SOURCE_SLUG:-mrn-base-stack}"
BOOTSTRAP_WARNINGS=()

usage() {
  cat <<'USAGE'
Usage:
  site-bootstrap.sh --site-path /home/<user>/htdocs/<domain> [--site-user <user>] [--plugins-file <path>] [--themes-file <path>] [--licenses-file <path>] [--notify-email <email>]

Notes:
  - Run as root (recommended on CloudPanel).
  - Plugin manifest format:
      plugin-slug
      plugin-slug|version
      https://example.com/plugin.zip
  - Theme manifest format:
      theme-slug
      theme-slug|version
      /absolute/path/to/theme.zip
      https://example.com/theme.zip
      theme-slug|active
      theme-slug|version|active
USAGE
}

SITE_PATH=""
SITE_USER=""
WP_PATH=""
NOTIFY_EMAIL="${STACK_NOTIFY_EMAIL:-${BOOTSTRAP_NOTIFY_EMAIL:-wordpress_admin@mrnwebdesigns.com}}"
SLACK_WEBHOOK_URL="${STACK_SLACK_WEBHOOK_URL:-${BOOTSTRAP_SLACK_WEBHOOK_URL:-}}"
SLACK_WEBHOOK_URL_FILE="${STACK_SLACK_WEBHOOK_URL_FILE:-${STACK_ROOT}/secrets/slack-webhook-url.txt}"
SLACK_CHANNEL="${STACK_SLACK_CHANNEL:-}"
SLACK_USERNAME="${STACK_SLACK_USERNAME:-MRN Bootstrap}"
SLACK_ICON_EMOJI="${STACK_SLACK_ICON_EMOJI:-:rocket:}"
SENDGRID_MANAGEMENT_API_KEY="${MRN_SENDGRID_MANAGEMENT_API_KEY:-${STACK_SENDGRID_MANAGEMENT_API_KEY:-}}"
SENDGRID_MANAGEMENT_API_KEY_FILE="${STACK_SENDGRID_MANAGEMENT_API_KEY_FILE:-${STACK_ROOT}/secrets/sendgrid-management-api-key.txt}"

if [[ -z "${SLACK_WEBHOOK_URL}" && -f "${SLACK_WEBHOOK_URL_FILE}" ]]; then
  SLACK_WEBHOOK_URL="$(tr -d '\r\n' < "${SLACK_WEBHOOK_URL_FILE}")"
fi

if [[ -z "${SENDGRID_MANAGEMENT_API_KEY}" && -f "${SENDGRID_MANAGEMENT_API_KEY_FILE}" ]]; then
  SENDGRID_MANAGEMENT_API_KEY="$(tr -d '\r\n' < "${SENDGRID_MANAGEMENT_API_KEY_FILE}")"
fi

while [[ $# -gt 0 ]]; do
  case "$1" in
    --site-path)
      SITE_PATH="${2:-}"
      shift 2
      ;;
    --site-user)
      SITE_USER="${2:-}"
      shift 2
      ;;
    --plugins-file)
      PLUGINS_FILE="${2:-}"
      shift 2
      ;;
    --themes-file)
      THEMES_FILE="${2:-}"
      shift 2
      ;;
    --licenses-file)
      LICENSES_FILE="${2:-}"
      shift 2
      ;;
    --notify-email)
      NOTIFY_EMAIL="${2:-}"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage
      exit 1
      ;;
  esac
done

send_notification() {
  local subject body
  subject="$1"
  body="$2"
  if [[ -z "${NOTIFY_EMAIL}" ]]; then
    return 0
  fi
  if command -v mail >/dev/null 2>&1; then
    printf '%s\n' "${body}" | mail -s "${subject}" "${NOTIFY_EMAIL}" || true
    return 0
  fi
  if command -v sendmail >/dev/null 2>&1; then
    {
      printf 'To: %s\n' "${NOTIFY_EMAIL}"
      printf 'Subject: %s\n' "${subject}"
      printf '\n'
      printf '%s\n' "${body}"
    } | sendmail -t || true
  fi
}

json_escape() {
  local value="${1:-}"
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  value="${value//$'\n'/\\n}"
  value="${value//$'\r'/\\r}"
  value="${value//$'\t'/\\t}"
  printf '%s' "${value}"
}

send_slack_notification() {
  local title="$1"
  local body="$2"
  local color="${3:-#1f6feb}"
  local domain channel_field payload

  [[ -n "${SLACK_WEBHOOK_URL}" ]] || return 0
  command -v curl >/dev/null 2>&1 || return 0

  domain="$(basename "${SITE_PATH:-unknown}")"
  if [[ -n "${SLACK_CHANNEL}" ]]; then
    channel_field="\"channel\":\"$(json_escape "${SLACK_CHANNEL}")\","
  else
    channel_field=""
  fi

  payload='{
    '"${channel_field}"'
    "username":"'"$(json_escape "${SLACK_USERNAME}")"'",
    "icon_emoji":"'"$(json_escape "${SLACK_ICON_EMOJI}")"'",
    "attachments":[
      {
        "color":"'"$(json_escape "${color}")"'",
        "title":"'"$(json_escape "${title}")"'",
        "text":"'"$(json_escape "${body}")"'",
        "footer":"MRN Stack Bootstrap",
        "ts":'"$(date +%s)"'
      }
    ]
  }'

  curl -sS -X POST -H 'Content-type: application/json' --data "${payload}" "${SLACK_WEBHOOK_URL}" >/dev/null || true
}

notify_failure() {
  local code line cmd domain body
  code="${1:-1}"
  line="${2:-unknown}"
  cmd="${3:-unknown}"
  domain="$(basename "${SITE_PATH:-unknown}")"
  body=$(
    cat <<EOF
MRN bootstrap failed.

Site path: ${SITE_PATH}
WordPress path: ${WP_PATH}
Domain: ${domain}
Site user: ${SITE_USER}
Exit code: ${code}
Line: ${line}
Command: ${cmd}
EOF
  )
  send_notification "MRN Bootstrap FAILED: ${domain}" "${body}"
  send_slack_notification "MRN Bootstrap FAILED: ${domain}" "${body}" "#d1242f"
}

trap 'notify_failure "$?" "$LINENO" "$BASH_COMMAND"' ERR

if [[ -z "${SITE_PATH}" ]]; then
  echo "Missing required argument: --site-path" >&2
  exit 1
fi

detect_wp_path() {
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

if ! WP_PATH="$(detect_wp_path "${SITE_PATH}")"; then
  echo "Not a WordPress install (wp-config.php not found at root/public/app/public): ${SITE_PATH}" >&2
  exit 1
fi

if [[ -z "${SITE_USER}" ]]; then
  SITE_USER="$(stat -c '%U' "${SITE_PATH}")"
fi

if ! command -v wp >/dev/null 2>&1; then
  echo "wp-cli is required but was not found in PATH." >&2
  exit 1
fi

if [[ -f "${SITE_PATH}/${MARKER_NAME}" ]]; then
  echo "Already bootstrapped: ${SITE_PATH}"
  send_notification "MRN Bootstrap Skipped: $(basename "${SITE_PATH}")" "Bootstrap skipped because marker exists for ${SITE_PATH}"
  send_slack_notification "MRN Bootstrap Skipped: $(basename "${SITE_PATH}")" "Bootstrap skipped because marker exists for ${SITE_PATH}" "#8b949e"
  exit 0
fi

send_slack_notification \
  "MRN Bootstrap Started: $(basename "${SITE_PATH}")" \
  "Bootstrap started for ${SITE_PATH}\nWordPress path: ${WP_PATH}\nSite user: ${SITE_USER}" \
  "#1f6feb"

run_wp() {
  local -a args
  args=("$@")
  sudo -u "${SITE_USER}" wp --path="${WP_PATH}" "${args[@]}"
}

add_warning() {
  local msg="$1"
  BOOTSTRAP_WARNINGS+=("${msg}")
  echo "WARNING: ${msg}" >&2
}

infer_new_plugin_slug() {
  local source="$1"
  local -a before_list after_list
  local candidate inferred install_output existing_slug

  mapfile -t before_list < <(run_wp plugin list --field=name 2>/dev/null || true)
  if ! install_output="$(run_wp plugin install "${source}" 2>&1)"; then
    existing_slug="$(printf '%s\n' "${install_output}" | sed -nE 's|.*wp-content/plugins/([^/]+)/.*|\1|p' | head -n1)"
    if [[ -n "${existing_slug}" ]] && run_wp plugin is-installed "${existing_slug}" >/dev/null 2>&1; then
      printf '%s' "${existing_slug}"
      return 0
    fi
    return 1
  fi
  mapfile -t after_list < <(run_wp plugin list --field=name 2>/dev/null || true)

  inferred=""
  for candidate in "${after_list[@]}"; do
    if ! printf '%s\n' "${before_list[@]}" | grep -F -x -q "${candidate}"; then
      inferred="${candidate}"
      break
    fi
  done

  if [[ -z "${inferred}" ]]; then
    inferred="$(basename "${source}" .zip)"
  fi

  printf '%s' "${inferred}"
}

derive_site_theme_slug() {
  local raw slug
  raw="$(basename "${SITE_PATH}")"
  raw="${raw%%.*}"
  slug="$(printf '%s' "${raw}" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g; s/^-+//; s/-+$//')"
  if [[ -n "${slug}" && ! "${slug}" =~ ^[a-z] ]]; then
    slug="site-${slug}"
  fi
  if [[ -z "${slug}" ]]; then
    slug="site-theme"
  fi
  printf '%s' "${slug}"
}

derive_site_theme_name() {
  local raw name
  raw="$(basename "${SITE_PATH}")"
  raw="${raw%%.*}"
  name="$(printf '%s' "${raw}" | sed -E 's/[^A-Za-z0-9]+/ /g; s/[[:space:]]+/ /g; s/^[[:space:]]+//; s/[[:space:]]+$//')"
  if [[ -z "${name}" ]]; then
    name="Site Theme"
  fi
  printf '%s' "${name}"
}

normalize_theme_identifier() {
  local val="$1"
  val="$(printf '%s' "${val}" | tr -d '\r' | xargs)"
  if [[ "${val}" == /* || "${val}" == *.zip ]]; then
    val="$(basename "${val}" .zip)"
  fi
  printf '%s' "${val}"
}

activate_theme_with_starter_rename() {
  local slug themes_dir starter_dir target_slug target_dir style_path target_name
  local should_clone="0"
  slug="$(normalize_theme_identifier "$1")"

  if [[ "${slug}" == "${DEFAULT_THEME_STARTER}" ]]; then
    should_clone="1"
  fi
  if [[ -n "${SITE_THEME_CLONE_SOURCE_SLUG}" && "${slug}" == "${SITE_THEME_CLONE_SOURCE_SLUG}" ]]; then
    should_clone="1"
  fi

  if [[ "${should_clone}" != "1" ]]; then
    run_wp theme activate "${slug}"
    return 0
  fi

  themes_dir="${WP_PATH}/wp-content/themes"
  starter_dir="${themes_dir}/${slug}"
  target_slug="$(derive_site_theme_slug)"
  target_dir="${themes_dir}/${target_slug}"

  if [[ ! -d "${starter_dir}" ]]; then
    echo "Starter theme folder missing: ${starter_dir}. Activating ${slug} directly."
    run_wp theme activate "${slug}"
    return 0
  fi

  if [[ "${target_slug}" == "${slug}" ]]; then
    run_wp theme activate "${slug}"
    return 0
  fi

  if [[ ! -d "${target_dir}" ]]; then
    echo "Creating site-specific starter theme: ${target_slug}"
    cp -a "${starter_dir}" "${target_dir}"
  fi

  chown -R "${SITE_USER}:${SITE_USER}" "${target_dir}"

  style_path="${target_dir}/style.css"
  target_name="$(derive_site_theme_name)"
  if [[ -f "${style_path}" ]]; then
    perl -i -pe "s/^Theme Name:\s*.*/Theme Name: ${target_name}/; s/^Text Domain:\s*.*/Text Domain: ${target_slug}/; s/^Author:\s*.*/Author: MRN Web Designs/" "${style_path}"
  fi

  run_wp theme activate "${target_slug}"
  echo "Activated site-specific starter theme: ${target_slug}"

  if [[ -d "${starter_dir}" ]]; then
    rm -rf "${starter_dir}" || add_warning "Failed to remove source starter theme directory: ${starter_dir}"
    echo "Removed source starter theme from site: ${slug}"
  fi
}

install_plugins() {
  if [[ ! -f "${PLUGINS_FILE}" ]]; then
    echo "Plugin manifest not found: ${PLUGINS_FILE}. Skipping plugin install."
    return 0
  fi

  while IFS= read -r line || [[ -n "${line}" ]]; do
    local clean slug version source installed_slug
    clean="${line%%#*}"
    clean="$(echo "${clean}" | xargs)"
    [[ -z "${clean}" ]] && continue

    if [[ "${clean}" == http* ]]; then
      echo "Installing plugin from URL: ${clean}"
      if ! installed_slug="$(infer_new_plugin_slug "${clean}")"; then
        add_warning "Failed to install plugin from URL: ${clean}"
        continue
      fi
      if ! run_wp plugin is-active "${installed_slug}" >/dev/null 2>&1; then
        if ! run_wp plugin activate "${installed_slug}"; then
          add_warning "Installed but failed to activate plugin slug '${installed_slug}' from URL: ${clean}"
        fi
      fi
      continue
    fi

    slug="${clean%%|*}"
    source="${clean}"
    version=""
    if [[ "${clean}" == *"|"* ]]; then
      version="${clean#*|}"
    fi

    if run_wp plugin is-installed "${slug}" >/dev/null 2>&1; then
      echo "Plugin already installed: ${slug}"
    else
      if [[ -n "${version}" && "${slug}" != /* && "${slug}" != *.zip && "${slug}" != http* ]]; then
        echo "Installing ${slug} version ${version}"
        if ! run_wp plugin install "${slug}" --version="${version}"; then
          add_warning "Failed to install plugin ${slug} version ${version}"
          continue
        fi
      else
        echo "Installing ${slug}"
        if [[ "${source}" == /* || "${source}" == *.zip ]]; then
          if ! installed_slug="$(infer_new_plugin_slug "${source}")"; then
            add_warning "Failed to install plugin package: ${source}"
            continue
          fi
          slug="${installed_slug}"
        else
          if ! run_wp plugin install "${source}"; then
            add_warning "Failed to install plugin: ${source}"
            continue
          fi
        fi
      fi
    fi

    if ! run_wp plugin is-active "${slug}" >/dev/null 2>&1; then
      if ! run_wp plugin activate "${slug}"; then
        add_warning "Installed but failed to activate plugin: ${slug}"
      fi
    fi
  done < "${PLUGINS_FILE}"
}

ensure_all_plugins_active() {
  local -a inactive_plugins
  mapfile -t inactive_plugins < <(run_wp plugin list --status=inactive --field=name 2>/dev/null || true)

  if [[ "${#inactive_plugins[@]}" -eq 0 ]]; then
    echo "All plugins are active."
    return 0
  fi

  echo "Activating remaining inactive plugins before licenses/imports."
  local plugin_slug
  for plugin_slug in "${inactive_plugins[@]}"; do
    plugin_slug="$(printf '%s' "${plugin_slug}" | xargs)"
    [[ -z "${plugin_slug}" ]] && continue
    if ! run_wp plugin activate "${plugin_slug}" >/dev/null 2>&1; then
      add_warning "Failed to activate plugin before licenses/imports: ${plugin_slug}"
      continue
    fi
    echo "Activated plugin: ${plugin_slug}"
  done
}

configure_post_types_order() {
  if ! run_wp plugin is-active post-types-order >/dev/null 2>&1; then
    return 0
  fi

  local code
  code='$existing = get_option("cpto_options");
if (!is_array($existing)) { $existing = []; }
$defaults = [
  "show_reorder_interfaces" => [],
  "allow_reorder_default_interfaces" => [],
  "autosort" => 1,
  "adminsort" => 1,
  "use_query_ASC_DESC" => "",
  "capability" => "manage_options",
  "edit_view_links" => "",
  "navigation_sort_apply" => 1,
];
$options = wp_parse_args($existing, $defaults);
update_option("cpto_options", $options);
update_option("CPT_configured", "TRUE");
echo "Configured Post Types Order defaults and marked CPT_configured=TRUE\n";'

  if ! run_wp eval "${code}" >/dev/null; then
    add_warning "Failed to auto-configure Post Types Order settings."
    return 0
  fi

  echo "Auto-configured Post Types Order settings."
}

apply_licenses() {
  apply_updraft_premium_mapping() {
    local raw_value="$1"
    local value_mode="$2"
    local payload_b64

    payload_b64="$(printf '%s' "${raw_value}" | base64 | tr -d '\n')"
    run_wp eval '
$raw = base64_decode("'"${payload_b64}"'", true);
if ($raw === false) {
    fwrite(STDERR, "Invalid Updraft Premium payload.\n");
    exit(1);
}

$mode = "'"${value_mode}"'";
$email = "";
$password = "";
$auto_update = null;

if ($mode === "json") {
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "Updraft Premium JSON payload is invalid.\n");
        exit(1);
    }
    $email = isset($decoded["email"]) ? trim((string) $decoded["email"]) : "";
    $password = isset($decoded["password"]) ? (string) $decoded["password"] : "";
    if (array_key_exists("auto_update", $decoded)) {
        $auto_update = (bool) $decoded["auto_update"];
    }
} else {
    $text = trim((string) $raw);
    if ($text !== "") {
        if (strpos($text, "\n") !== false) {
            $parts = preg_split("/\r?\n/", $text);
            $email = isset($parts[0]) ? trim((string) $parts[0]) : "";
            $password = isset($parts[1]) ? trim((string) $parts[1]) : "";
        } elseif (strpos($text, "|") !== false) {
            $parts = explode("|", $text, 2);
            $email = trim((string) $parts[0]);
            $password = isset($parts[1]) ? trim((string) $parts[1]) : "";
        }
    }
}

if ($email === "" || $password === "") {
    fwrite(STDERR, "Updraft Premium credentials are incomplete (email/password required).\n");
    exit(1);
}

if (!defined("UDADDONS2_SLUG")) {
    $addon_file = WP_PLUGIN_DIR . "/updraftplus/udaddons/updraftplus-addons.php";
    if (is_file($addon_file)) {
        require_once $addon_file;
    }
}

global $updraftplus_addons2, $updraftplus;
if (!is_object($updraftplus_addons2) || !method_exists($updraftplus_addons2, "connection_status")) {
    fwrite(STDERR, "Updraft Premium connection handler is unavailable.\n");
    exit(1);
}

$option_name = "updraftplus-addons_options";
$existing = $updraftplus_addons2->get_option($option_name);
if (!is_array($existing)) {
    $existing = [];
}
$existing["email"] = $email;
$existing["password"] = $password;
$updraftplus_addons2->update_option($option_name, $existing);

$result = $updraftplus_addons2->connection_status();
if (true !== $result) {
    $updraftplus_addons2->update_option($option_name, ["email" => "", "password" => ""]);
    if (is_wp_error($result)) {
        $messages = $result->get_error_messages();
        fwrite(STDERR, "Updraft Premium connect failed: " . implode(" | ", array_map("strval", $messages)) . "\n");
    } else {
        fwrite(STDERR, "Updraft Premium connect failed.\n");
    }
    exit(1);
}

if ($auto_update !== null && is_object($updraftplus) && method_exists($updraftplus, "set_automatic_updates")) {
    $updraftplus->set_automatic_updates($auto_update);
}

echo "Applied and verified Updraft Premium credentials.\n";
'
  }

  apply_wpforms_license_mapping() {
    local raw_value="$1"
    local value_mode="$2"
    local payload_b64

    payload_b64="$(printf '%s' "${raw_value}" | base64 | tr -d '\n')"
    run_wp eval '
$raw = base64_decode("'"${payload_b64}"'", true);
if ($raw === false) {
    fwrite(STDERR, "Invalid WPForms license payload.\n");
    exit(1);
}

$mode = "'"${value_mode}"'";
$key = "";
if ($mode === "json") {
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "WPForms license JSON payload is invalid.\n");
        exit(1);
    }
    $key = isset($decoded["key"]) ? trim((string) $decoded["key"]) : "";
} else {
    $key = trim($raw);
}

if ($key === "") {
    fwrite(STDERR, "WPForms license key is empty.\n");
    exit(1);
}

$option = get_option("wpforms_license", []);
if (!is_array($option)) {
    $option = [];
}
$option["key"] = $key;
update_option("wpforms_license", $option);

if (!class_exists("WPForms_License")) {
    $licenseClass = WP_PLUGIN_DIR . "/wpforms/pro/includes/admin/class-license.php";
    if (is_file($licenseClass)) {
        require_once $licenseClass;
    }
}

if (!class_exists("WPForms_License")) {
    fwrite(STDERR, "WPForms_License class is unavailable.\n");
    exit(1);
}

$license = new WPForms_License();
if (!$license->verify_key($key, false)) {
    $msg = "WPForms license verify failed.";
    if (isset($license->errors) && is_array($license->errors) && !empty($license->errors)) {
        $msg .= " " . implode(" | ", array_map("strval", $license->errors));
    }
    fwrite(STDERR, $msg . "\n");
    exit(1);
}
echo "Applied and verified WPForms license key.\n";
'
  }

  apply_searchwp_license_mapping() {
    local raw_value="$1"
    local value_mode="${2:-text}"
    local payload_b64

    payload_b64="$(printf '%s' "${raw_value}" | base64 | tr -d '\n')"
    run_wp eval '
$raw = base64_decode("'"${payload_b64}"'", true);
if ($raw === false) {
    fwrite(STDERR, "Invalid SearchWP license payload.\n");
    exit(1);
}

$mode = "'"${value_mode}"'";
$key = "";
if ($mode === "json") {
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "SearchWP license JSON payload is invalid.\n");
        exit(1);
    }
    $candidate = $decoded["key"] ?? ($decoded["license_key"] ?? "");
    if (is_string($candidate)) {
        $key = trim($candidate);
    }
} else {
    $key = trim($raw);
}

if ($key === "") {
    fwrite(STDERR, "SearchWP license key is empty.\n");
    exit(1);
}

if (!defined("SEARCHWP_PREFIX")) {
    $bootstrap = WP_PLUGIN_DIR . "/searchwp/bootstrap.php";
    if (is_file($bootstrap)) {
        require_once $bootstrap;
    }
}
if (!defined("SEARCHWP_PREFIX")) {
    define("SEARCHWP_PREFIX", "searchwp_");
}

// Persist raw key to support SearchWP upgrader fallback.
update_option(SEARCHWP_PREFIX . "license_key", $key);

if (!class_exists("\\SearchWP\\Settings")) {
    $settingsFile = WP_PLUGIN_DIR . "/searchwp/includes/Settings.php";
    if (is_file($settingsFile)) {
        require_once $settingsFile;
    }
}
if (!class_exists("\\SearchWP\\License")) {
    $licenseFile = WP_PLUGIN_DIR . "/searchwp/includes/License.php";
    if (is_file($licenseFile)) {
        require_once $licenseFile;
    }
}

if (!class_exists("\\SearchWP\\License")) {
    fwrite(STDERR, "SearchWP license class not available.\n");
    exit(1);
}

$response = \SearchWP\License::activate($key);
if (!is_array($response) || !($response["success"] ?? false)) {
    // Keep a minimal payload so key is visible in settings even if activation endpoint fails.
    update_option(SEARCHWP_PREFIX . "license", [
        "key" => $key,
        "status" => "invalid",
    ]);
    $msg = "SearchWP license activation failed.";
    if (is_array($response) && isset($response["data"])) {
        if (is_string($response["data"])) {
            $msg .= " " . $response["data"];
        } elseif (is_object($response["data"]) && isset($response["data"]->error)) {
            $msg .= " " . (string) $response["data"]->error;
        }
    }
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

echo "Applied and activated SearchWP license key.\n";
'
  }

  apply_acf_pro_license_mapping() {
    local raw_value="$1"
    local value_mode="${2:-text}"
    local payload_b64

    payload_b64="$(printf '%s' "${raw_value}" | base64 | tr -d '\n')"
    run_wp eval '
$raw = base64_decode("'"${payload_b64}"'", true);
if ($raw === false) {
    fwrite(STDERR, "Invalid ACF Pro license payload.\n");
    exit(1);
}

$mode = "'"${value_mode}"'";
$key = "";
if ($mode === "json") {
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "ACF Pro license JSON payload is invalid.\n");
        exit(1);
    }
    $candidate = $decoded["key"] ?? ($decoded["license_key"] ?? "");
    if (is_string($candidate)) {
        $key = trim($candidate);
    }
} else {
    $key = trim($raw);
}

if ($key === "") {
    fwrite(STDERR, "ACF Pro license key is empty.\n");
    exit(1);
}

if (!function_exists("acf_pro_update_license") || !function_exists("acf_pro_activate_license")) {
    $updates_file = WP_PLUGIN_DIR . "/advanced-custom-fields-pro/pro/updates.php";
    if (is_file($updates_file)) {
        require_once $updates_file;
    }
}

if (!function_exists("acf_pro_update_license") || !function_exists("acf_pro_activate_license")) {
    fwrite(STDERR, "ACF Pro license functions are unavailable.\n");
    exit(1);
}

acf_pro_update_license($key);
$result = acf_pro_activate_license($key, true, false);

if (is_wp_error($result)) {
    fwrite(STDERR, "ACF Pro license activation failed: " . implode(" | ", array_map("strval", $result->get_error_messages())) . "\n");
    exit(1);
}

if (is_array($result) && array_key_exists("success", $result) && !$result["success"]) {
    $message = isset($result["message"]) && is_scalar($result["message"]) ? (string) $result["message"] : "ACF Pro license activation failed.";
    fwrite(STDERR, $message . "\n");
    exit(1);
}

echo "Applied and activated ACF Pro license key.\n";
'
  }

  apply_ame_license_mapping() {
    local raw_value="$1"
    local value_mode="$2"
    local payload_b64 site_url

    site_url="$(run_wp option get siteurl 2>/dev/null || true)"
    site_url="${site_url#http:}"
    site_url="${site_url#https:}"
    if [[ -z "${site_url}" ]]; then
      site_url="//$(basename "${SITE_PATH}")"
    fi

    payload_b64="$(printf '%s' "${raw_value}" | base64 | tr -d '\n')"
    run_wp eval '
$raw = base64_decode("'"${payload_b64}"'", true);
if ($raw === false) {
    fwrite(STDERR, "Invalid AME license payload.\n");
    exit(1);
}
$mode = "'"${value_mode}"'";
$siteUrl = "'"${site_url}"'";
$payload = [];
if ($mode === "json") {
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "AME license JSON payload is invalid.\n");
        exit(1);
    }
    $payload = $decoded;
} else {
    $licenseKey = trim($raw);
    if ($licenseKey === "") {
        fwrite(STDERR, "AME license key is empty.\n");
        exit(1);
    }
    $payload = [
        "license_key" => $licenseKey,
        "site_token" => "",
        "license" => [
            "product_slug" => "admin-menu-editor-pro",
            "status" => "valid",
            "site_url" => $siteUrl,
        ],
        "token_history" => [],
    ];
}

if (!isset($payload["license"]) || !is_array($payload["license"])) {
    $payload["license"] = [];
}
$payload["license"]["site_url"] = $siteUrl;
$payload["site_token"] = "";
$payload["token_history"] = [$siteUrl => $siteUrl];

update_option("wsh_license_manager-admin-menu-editor-pro", $payload);
echo "Applied AME license payload for {$siteUrl}\n";
'
  }

  if [[ ! -f "${LICENSES_FILE}" ]]; then
    echo "License manifest not found: ${LICENSES_FILE}. Skipping license apply."
    return 0
  fi

  while IFS= read -r line || [[ -n "${line}" ]]; do
    local clean plugin_basename option_name value_ref value file_path payload_b64 code
    clean="${line%%#*}"
    clean="$(printf '%s' "${clean}" | tr -d '\r' | xargs)"
    [[ -z "${clean}" ]] && continue

    IFS='|' read -r plugin_basename option_name value_ref <<< "${clean}"
    plugin_basename="$(printf '%s' "${plugin_basename:-}" | xargs)"
    option_name="$(printf '%s' "${option_name:-}" | xargs)"
    value_ref="$(printf '%s' "${value_ref:-}" | xargs)"

    if [[ -z "${plugin_basename}" || -z "${option_name}" || -z "${value_ref}" ]]; then
      add_warning "Invalid license mapping (expected plugin|option|value): ${clean}"
      continue
    fi

    local plugin_active_check_slug
    plugin_active_check_slug="${plugin_basename}"
    if ! run_wp plugin is-active "${plugin_active_check_slug}" >/dev/null 2>&1; then
      if [[ "${plugin_basename}" == */* ]]; then
        plugin_active_check_slug="${plugin_basename%%/*}"
      fi
    fi

    if ! run_wp plugin is-active "${plugin_active_check_slug}" >/dev/null 2>&1; then
      add_warning "Skipped license mapping for ${plugin_basename}: plugin is not active."
      continue
    fi

    value="${value_ref}"
    if [[ "${value_ref}" == file:* || "${value_ref}" == filejson:* || "${value_ref}" == secretfile:* || "${value_ref}" == secretfilejson:* ]]; then
      if [[ "${value_ref}" == filejson:* ]]; then
        file_path="${value_ref#filejson:}"
      elif [[ "${value_ref}" == file:* ]]; then
        file_path="${value_ref#file:}"
      elif [[ "${value_ref}" == secretfilejson:* ]]; then
        file_path="${value_ref#secretfilejson:}"
      else
        file_path="${value_ref#secretfile:}"
      fi
      if [[ "${file_path}" != /* ]]; then
        if [[ "${value_ref}" == secretfile:* || "${value_ref}" == secretfilejson:* ]]; then
          file_path="${SECRETS_DIR}/${file_path}"
        else
          file_path="${EXPORTS_DIR}/${file_path}"
        fi
      fi
      if [[ ! -f "${file_path}" ]]; then
        add_warning "Skipped license mapping for ${plugin_basename}: file not found (${file_path})."
        continue
      fi
      value="$(cat "${file_path}")"
    fi

    if [[ "${plugin_basename}" == "admin-menu-editor-pro/menu-editor.php" && "${option_name}" == "wsh_license_manager-admin-menu-editor-pro" ]]; then
      local ame_mode
      ame_mode="text"
      if [[ "${value_ref}" == json:* || "${value_ref}" == filejson:* || "${value_ref}" == secretfilejson:* ]]; then
        ame_mode="json"
      fi
      if ! apply_ame_license_mapping "${value}" "${ame_mode}" >/dev/null; then
        add_warning "Failed to apply AME license mapping for ${plugin_basename}."
        continue
      fi
      echo "Applied license mapping: ${plugin_basename} -> ${option_name} (ame-site-aware)"
      continue
    fi

    if [[ "${plugin_basename}" == "wpforms/wpforms.php" && "${option_name}" == "wpforms_license" ]]; then
      local wpforms_mode
      wpforms_mode="text"
      if [[ "${value_ref}" == json:* || "${value_ref}" == filejson:* || "${value_ref}" == secretfilejson:* ]]; then
        wpforms_mode="json"
      fi
      if ! apply_wpforms_license_mapping "${value}" "${wpforms_mode}" >/dev/null; then
        add_warning "Failed to apply/verify WPForms license mapping for ${plugin_basename}."
        continue
      fi
      echo "Applied license mapping: ${plugin_basename} -> ${option_name} (verified)"
      continue
    fi

    if [[ "${plugin_basename}" == "updraftplus/updraftplus.php" && "${option_name}" == "updraftplus-addons_options" ]]; then
      local updraft_mode
      updraft_mode="text"
      if [[ "${value_ref}" == json:* || "${value_ref}" == filejson:* || "${value_ref}" == secretfilejson:* ]]; then
        updraft_mode="json"
      fi
      if ! apply_updraft_premium_mapping "${value}" "${updraft_mode}" >/dev/null; then
        add_warning "Failed to apply/verify Updraft Premium mapping for ${plugin_basename}."
        continue
      fi
      echo "Applied license mapping: ${plugin_basename} -> ${option_name} (verified)"
      continue
    fi

    if [[ ( "${plugin_basename}" == "searchwp/searchwp.php" || "${plugin_basename}" == "searchwp/index.php" ) && ( "${option_name}" == "searchwp_license_key" || "${option_name}" == "searchwp_license" ) ]]; then
      local searchwp_mode
      searchwp_mode="text"
      if [[ "${value_ref}" == json:* || "${value_ref}" == filejson:* || "${value_ref}" == secretfilejson:* ]]; then
        searchwp_mode="json"
      fi
      if ! apply_searchwp_license_mapping "${value}" "${searchwp_mode}" >/dev/null; then
        add_warning "Failed to apply/activate SearchWP license mapping for ${plugin_basename}."
        continue
      fi
      echo "Applied license mapping: ${plugin_basename} -> ${option_name} (verified)"
      continue
    fi

    if [[ "${plugin_basename}" == "advanced-custom-fields-pro/acf.php" && "${option_name}" == "acf_pro_license" ]]; then
      local acf_mode
      acf_mode="text"
      if [[ "${value_ref}" == json:* || "${value_ref}" == filejson:* || "${value_ref}" == secretfilejson:* ]]; then
        acf_mode="json"
      fi
      if ! apply_acf_pro_license_mapping "${value}" "${acf_mode}" >/dev/null; then
        add_warning "Failed to apply/activate ACF Pro license mapping for ${plugin_basename}."
        continue
      fi
      echo "Applied license mapping: ${plugin_basename} -> ${option_name} (verified)"
      continue
    fi

    if [[ "${value_ref}" == json:* || "${value_ref}" == filejson:* ]]; then
      if [[ "${value_ref}" == json:* ]]; then
        value="${value_ref#json:}"
      fi
      payload_b64="$(printf '%s' "${value}" | base64 | tr -d '\n')"
      code='$json = base64_decode("'"${payload_b64}"'", true);
if ($json === false) { fwrite(STDERR, "Invalid license JSON payload.\n"); exit(1); }
$decoded = json_decode($json, true);
if (!is_array($decoded)) { fwrite(STDERR, "License JSON must decode to an object/array.\n"); exit(1); }
update_option("'"${option_name}"'", $decoded);
echo "Applied JSON license mapping for option '"${option_name}"'\n";'
      if ! run_wp eval "${code}" >/dev/null; then
        add_warning "Failed to apply JSON license mapping for ${plugin_basename} (${option_name})."
        continue
      fi
      echo "Applied license mapping: ${plugin_basename} -> ${option_name} (json)"
      continue
    fi

    if ! run_wp option update "${option_name}" "${value}" >/dev/null; then
      add_warning "Failed to apply license mapping for ${plugin_basename} (${option_name})."
      continue
    fi

    echo "Applied license mapping: ${plugin_basename} -> ${option_name}"
  done < "${LICENSES_FILE}"
}

install_themes() {
  install_default_starter_theme() {
    echo "Applying default starter theme: ${DEFAULT_THEME_STARTER}"
    if ! run_wp theme is-installed "${DEFAULT_THEME_STARTER}" >/dev/null 2>&1; then
      if ! run_wp theme install "${DEFAULT_THEME_STARTER}"; then
        add_warning "Failed to install default starter theme: ${DEFAULT_THEME_STARTER}"
        return 0
      fi
    fi
    if ! activate_theme_with_starter_rename "${DEFAULT_THEME_STARTER}"; then
      add_warning "Failed to activate default starter theme: ${DEFAULT_THEME_STARTER}"
    fi
  }

  if [[ ! -f "${THEMES_FILE}" ]]; then
    echo "Theme manifest not found: ${THEMES_FILE}. Using default starter theme."
    install_default_starter_theme
    return 0
  fi

  local requested_active_slug=""
  local processed_count="0"

  while IFS= read -r line || [[ -n "${line}" ]]; do
    local clean source version flag activate slug before_list after_list inferred_slug install_output fallback_slug
    clean="${line%%#*}"
    clean="$(printf '%s' "${clean}" | tr -d '\r' | xargs)"
    [[ -z "${clean}" ]] && continue
    processed_count="1"

    source="${clean}"
    version=""
    flag=""
    activate="0"

    if [[ "${clean}" == *"|"* ]]; then
      source="$(printf '%s' "${clean%%|*}" | tr -d '\r' | xargs)"
      local rest
      rest="$(printf '%s' "${clean#*|}" | tr -d '\r' | xargs)"
      if [[ "${rest}" == *"|"* ]]; then
        version="$(printf '%s' "${rest%%|*}" | tr -d '\r' | xargs)"
        flag="$(printf '%s' "${rest#*|}" | tr -d '\r' | xargs)"
      else
        if [[ "${rest}" == "active" ]]; then
          flag="${rest}"
        else
          version="$(printf '%s' "${rest}" | tr -d '\r' | xargs)"
        fi
      fi
    fi

    [[ "${flag}" == "active" ]] && activate="1"

    if [[ "${source}" == http* || "${source}" == /* || "${source}" == *.zip ]]; then
      mapfile -t before_list < <(run_wp theme list --field=name 2>/dev/null || true)
      if [[ -n "${version}" ]]; then
        echo "Installing theme from package/source: ${source} (version ${version})"
        if ! install_output="$(run_wp theme install "${source}" --version="${version}" 2>&1)"; then
          fallback_slug="$(normalize_theme_identifier "${source}")"
          if run_wp theme is-installed "${fallback_slug}" >/dev/null 2>&1; then
            slug="${fallback_slug}"
          else
            add_warning "Failed to install theme source: ${source} (version ${version})"
            continue
          fi
        fi
      else
        echo "Installing theme from package/source: ${source}"
        if ! install_output="$(run_wp theme install "${source}" 2>&1)"; then
          fallback_slug="$(normalize_theme_identifier "${source}")"
          if run_wp theme is-installed "${fallback_slug}" >/dev/null 2>&1; then
            slug="${fallback_slug}"
          else
            add_warning "Failed to install theme source: ${source}"
            continue
          fi
        fi
      fi
      if [[ -z "${slug:-}" ]]; then
        mapfile -t after_list < <(run_wp theme list --field=name 2>/dev/null || true)
        inferred_slug=""
        local candidate
        for candidate in "${after_list[@]}"; do
          if ! printf '%s\n' "${before_list[@]}" | grep -F -x -q "${candidate}"; then
            inferred_slug="${candidate}"
            break
          fi
        done
        if [[ -z "${inferred_slug}" ]]; then
          inferred_slug="$(normalize_theme_identifier "${source}")"
        fi
        slug="${inferred_slug}"
      fi
    else
      slug="$(normalize_theme_identifier "${source}")"
      if run_wp theme is-installed "${slug}" >/dev/null 2>&1; then
        echo "Theme already installed: ${slug}"
      else
        if [[ -n "${version}" ]]; then
          echo "Installing theme ${slug} version ${version}"
          if ! run_wp theme install "${slug}" --version="${version}"; then
            add_warning "Failed to install theme ${slug} version ${version}"
            continue
          fi
        else
          echo "Installing theme ${slug}"
          if ! run_wp theme install "${slug}"; then
            add_warning "Failed to install theme ${slug}"
            continue
          fi
        fi
      fi
    fi

    if [[ "${activate}" == "1" ]]; then
      requested_active_slug="${slug}"
    fi
  done < "${THEMES_FILE}"

  if [[ "${processed_count}" == "0" ]]; then
    echo "Theme manifest is empty: ${THEMES_FILE}. Using default starter theme."
    install_default_starter_theme
    return 0
  fi

  if [[ -n "${requested_active_slug}" ]]; then
    echo "Activating theme: ${requested_active_slug}"
    if ! activate_theme_with_starter_rename "${requested_active_slug}"; then
      add_warning "Failed to activate theme: ${requested_active_slug}"
    fi
    return 0
  fi

  echo "No active theme specified in manifest; keeping current active theme."
}

apply_wp_defaults() {
  run_wp option update permalink_structure '/%postname%/'
  # Discourage search engines from indexing bootstrap sites by default.
  run_wp option update blog_public 0
  # Mark all bootstrap sites as development environments in wp-config.php.
  if ! run_wp config set WP_ENVIRONMENT_TYPE development --type=constant; then
    add_warning "Failed to set WP_ENVIRONMENT_TYPE=development in wp-config.php"
  fi
  # Expose the stack-managed SendGrid management key to WordPress when available.
  if [[ -n "${SENDGRID_MANAGEMENT_API_KEY}" ]]; then
    if ! run_wp config set MRN_SENDGRID_MANAGEMENT_API_KEY "${SENDGRID_MANAGEMENT_API_KEY}" --type=constant; then
      add_warning "Failed to set MRN_SENDGRID_MANAGEMENT_API_KEY in wp-config.php"
    fi
  fi
}

run_importers() {
  if [[ ! -d "${IMPORTERS_DIR}" ]]; then
    return 0
  fi

  shopt -s nullglob
  local importer
  for importer in "${IMPORTERS_DIR}"/*.sh; do
    if [[ -x "${importer}" ]]; then
      echo "Running importer: ${importer}"
      STACK_ROOT="${STACK_ROOT}" SITE_PATH="${SITE_PATH}" SITE_USER="${SITE_USER}" WP_PATH="${WP_PATH}" "${importer}"
    fi
  done
}

sync_mu_plugins() {
  local target_dir
  target_dir="${WP_PATH}/wp-content/mu-plugins"

  if [[ ! -d "${MU_PLUGINS_SOURCE_DIR}" ]]; then
    echo "MU plugins source not found: ${MU_PLUGINS_SOURCE_DIR}. Skipping MU sync."
    return 0
  fi

  mkdir -p "${target_dir}"
  if ! rsync -a "${MU_PLUGINS_SOURCE_DIR}/" "${target_dir}/"; then
    add_warning "Failed MU sync from ${MU_PLUGINS_SOURCE_DIR} to ${target_dir}"
    return 0
  fi

  chown -R "${SITE_USER}:${SITE_USER}" "${target_dir}" || add_warning "Failed to chown MU plugins directory: ${target_dir}"
  echo "MU plugins synced: ${MU_PLUGINS_SOURCE_DIR} -> ${target_dir}"
}

main() {
  local domain body
  echo "Bootstrapping site: ${SITE_PATH} (owner: ${SITE_USER})"
  install_plugins
  ensure_all_plugins_active
  configure_post_types_order
  apply_licenses
  sync_mu_plugins
  install_themes
  apply_wp_defaults
  run_importers
  sudo -u "${SITE_USER}" touch "${SITE_PATH}/${MARKER_NAME}"
  echo "Bootstrap complete: ${SITE_PATH}"
  domain="$(basename "${SITE_PATH}")"
  body=$(
    cat <<EOF
MRN bootstrap completed successfully.

Site path: ${SITE_PATH}
WordPress path: ${WP_PATH}
Domain: ${domain}
Site user: ${SITE_USER}
Themes manifest: ${THEMES_FILE}
Plugins manifest: ${PLUGINS_FILE}
EOF
  )
  send_notification "MRN Bootstrap Success: ${domain}" "${body}"
  send_slack_notification "MRN Bootstrap Success: ${domain}" "${body}" "#1f883d"

  if [[ "${#BOOTSTRAP_WARNINGS[@]}" -gt 0 ]]; then
    local warning_lines warning_body
    warning_lines="$(printf '%s\n' "${BOOTSTRAP_WARNINGS[@]}")"
    warning_body=$(
      cat <<EOF
MRN bootstrap completed with warnings.

Site path: ${SITE_PATH}
WordPress path: ${WP_PATH}
Domain: ${domain}
Site user: ${SITE_USER}

Warnings:
${warning_lines}
EOF
    )
    send_notification "MRN Bootstrap WARNINGS: ${domain}" "${warning_body}"
    send_slack_notification "MRN Bootstrap WARNINGS: ${domain}" "${warning_body}" "#d29922"
  fi
}

main "$@"
