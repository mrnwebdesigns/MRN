#!/usr/bin/env bash
set -euo pipefail

STACK_ROOT="${STACK_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
SITE_PATH="${SITE_PATH:-}"
SITE_USER="${SITE_USER:-}"
WP_PATH="${WP_PATH:-}"
IMPORT_MANIFEST="${STACK_ROOT}/manifests/importers.txt"
EXPORTS_DIR="${STACK_ROOT}/configs/exports"
WP_SKIP_PLUGINS="${STACK_IMPORTER_SKIP_PLUGINS:-}"
MRN_BRANDING_ASSETS_READY=0
MRN_BRANDING_TOOLBAR_URL=""
MRN_BRANDING_LOGIN_URL=""

if [[ -z "${SITE_PATH}" || -z "${SITE_USER}" || -z "${WP_PATH}" ]]; then
  echo "Importer context missing (SITE_PATH/SITE_USER/WP_PATH). Skipping importer."
  exit 0
fi

if [[ ! -f "${IMPORT_MANIFEST}" ]]; then
  echo "Importer manifest not found: ${IMPORT_MANIFEST}. Skipping importer."
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

apply_happyfiles_defaults() {
  local code

  if ! run_wp plugin is-active happyfiles-pro >/dev/null 2>&1 && ! run_wp plugin is-active happyfiles >/dev/null 2>&1; then
    echo "HappyFiles is not active. Skipping HappyFiles defaults."
    return 0
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
}

ensure_mrn_branding_assets() {
  if [[ "${MRN_BRANDING_ASSETS_READY}" == "1" ]]; then
    return 0
  fi

  local toolbar_source login_source escaped_toolbar_source escaped_login_source code output
  toolbar_source="${EXPORTS_DIR}/mrn-logo-png.png"
  login_source="${EXPORTS_DIR}/MRN-Login.png"

  if [[ ! -f "${toolbar_source}" || ! -f "${login_source}" ]]; then
    echo "MRN branding asset warning: bundled logo files were not found." >&2
    return 1
  fi

  escaped_toolbar_source="${toolbar_source//\\/\\\\}"
  escaped_toolbar_source="${escaped_toolbar_source//\'/\\\'}"
  escaped_login_source="${login_source//\\/\\\\}"
  escaped_login_source="${escaped_login_source//\'/\\\'}"

  code='$toolbar_source = '\'''"${escaped_toolbar_source}"''\'';
$login_source = '\'''"${escaped_login_source}"''\'';
$uploads = wp_upload_dir();
if (!is_array($uploads) || !empty($uploads["error"]) || empty($uploads["basedir"]) || empty($uploads["baseurl"])) {
    fwrite(STDERR, "Could not resolve WordPress uploads directory for MRN branding assets.\n");
    exit(1);
}
$target_dir = trailingslashit($uploads["basedir"]) . "mrn-stack-branding";
$target_url = trailingslashit($uploads["baseurl"]) . "mrn-stack-branding";
if (!wp_mkdir_p($target_dir)) {
    fwrite(STDERR, "Could not create MRN branding uploads directory.\n");
    exit(1);
}
$assets = [
    "toolbar" => [$toolbar_source, $target_dir . "/mrn-admin-menu-logo.png", $target_url . "/mrn-admin-menu-logo.png"],
    "login" => [$login_source, $target_dir . "/mrn-login-logo.png", $target_url . "/mrn-login-logo.png"],
];
foreach ($assets as $asset) {
    [$source, $destination] = $asset;
    if (!is_file($source) || !copy($source, $destination)) {
        fwrite(STDERR, "Could not install MRN branding asset: {$source}\n");
        exit(1);
    }
    @chmod($destination, 0644);
}
echo "toolbar_url=" . esc_url_raw($assets["toolbar"][2]) . "\n";
echo "login_url=" . esc_url_raw($assets["login"][2]) . "\n";'

  if ! output="$(run_wp eval "${code}")"; then
    return 1
  fi

  MRN_BRANDING_TOOLBAR_URL="$(printf '%s\n' "${output}" | awk -F= '$1 == "toolbar_url" {print substr($0, index($0, "=") + 1)}' | tail -n 1)"
  MRN_BRANDING_LOGIN_URL="$(printf '%s\n' "${output}" | awk -F= '$1 == "login_url" {print substr($0, index($0, "=") + 1)}' | tail -n 1)"

  if [[ -z "${MRN_BRANDING_TOOLBAR_URL}" || -z "${MRN_BRANDING_LOGIN_URL}" ]]; then
    echo "MRN branding asset warning: installer did not return usable asset URLs." >&2
    return 1
  fi

  MRN_BRANDING_ASSETS_READY=1
  echo "Installed MRN branding assets for AME: ${MRN_BRANDING_TOOLBAR_URL}, ${MRN_BRANDING_LOGIN_URL}" >&2
  return 0
}

prepare_ame_container_import_file() {
  local source_file="$1"
  local tmp_file escaped_source escaped_tmp escaped_toolbar_url escaped_login_url code

  if ! ensure_mrn_branding_assets; then
    printf '%s' "${source_file}"
    return 0
  fi

  tmp_file="$(mktemp "${TMPDIR:-/tmp}/mrn-ame-container.XXXXXX")"
  chmod 0666 "${tmp_file}" 2>/dev/null || true

  escaped_source="${source_file//\\/\\\\}"
  escaped_source="${escaped_source//\'/\\\'}"
  escaped_tmp="${tmp_file//\\/\\\\}"
  escaped_tmp="${escaped_tmp//\'/\\\'}"
  escaped_toolbar_url="${MRN_BRANDING_TOOLBAR_URL//\\/\\\\}"
  escaped_toolbar_url="${escaped_toolbar_url//\'/\\\'}"
  escaped_login_url="${MRN_BRANDING_LOGIN_URL//\\/\\\\}"
  escaped_login_url="${escaped_login_url//\'/\\\'}"

  code='$source = '\'''"${escaped_source}"''\'';
$target = '\'''"${escaped_tmp}"''\'';
$toolbar_url = '\'''"${escaped_toolbar_url}"''\'';
$login_url = '\'''"${escaped_login_url}"''\'';
$json = file_get_contents($source);
if (!is_string($json) || $json === "") {
    fwrite(STDERR, "AME container file is empty or unreadable while preparing branding assets.\n");
    exit(1);
}
$container = json_decode($json, true);
if (!is_array($container) || !isset($container["settings"]) || !is_array($container["settings"])) {
    fwrite(STDERR, "Invalid AME container JSON while preparing branding assets.\n");
    exit(1);
}
if (!isset($container["settings"]["branding"]) || !is_array($container["settings"]["branding"])) {
    $container["settings"]["branding"] = [];
}
$container["settings"]["branding"]["is_toolbar_wp_logo_hidden"] = true;
$container["settings"]["branding"]["custom_toolbar_logo"] = [
    "attachmentId" => 0,
    "attachmentSiteId" => 0,
    "attachmentUrl" => null,
    "externalUrl" => $toolbar_url,
    "width" => 16,
    "height" => 16,
];
if (empty($container["settings"]["branding"]["custom_toolbar_logo_link"])) {
    $container["settings"]["branding"]["custom_toolbar_logo_link"] = "https://mrnwebdesigns.com";
}
if (!isset($container["settings"]["login-page"]) || !is_array($container["settings"]["login-page"])) {
    $container["settings"]["login-page"] = [];
}
$container["settings"]["login-page"]["logo_image"] = [
    "attachmentSiteId" => 0,
    "attachmentUrl" => null,
    "externalUrl" => $login_url,
    "width" => 90,
    "height" => 90,
];
if (empty($container["settings"]["login-page"]["logo_link_url"])) {
    $container["settings"]["login-page"]["logo_link_url"] = "https://mrnwebdesigns.com";
}
if (empty($container["settings"]["login-page"]["logo_title_text"])) {
    $container["settings"]["login-page"]["logo_title_text"] = "Created by MRN Web Designs";
}
$encoded = wp_json_encode($container, JSON_UNESCAPED_SLASHES);
if (!is_string($encoded) || $encoded === "" || file_put_contents($target, $encoded) === false) {
    fwrite(STDERR, "Could not write prepared AME container with MRN branding assets.\n");
    exit(1);
}
echo "Prepared AME container with site-local MRN branding assets.\n";'

  if ! run_wp eval "${code}" >&2; then
    rm -f "${tmp_file}"
    printf '%s' "${source_file}"
    return 0
  fi

  printf '%s' "${tmp_file}"
}

apply_option_json() {
  local storage="$1"
  local file_path="$2"
  local option_name="$3"
  local escaped_file escaped_name code

  escaped_file="${file_path//\\/\\\\}"
  escaped_file="${escaped_file//\'/\\\'}"
  escaped_name="${option_name//\\/\\\\}"
  escaped_name="${escaped_name//\'/\\\'}"
  code='$file = '\'''"${escaped_file}"''\'';
if (!is_file($file)) { fwrite(STDERR, "JSON config file not found.\n"); exit(1); }
$json = file_get_contents($file);
if (!is_string($json) || $json === "") { fwrite(STDERR, "JSON config file is empty or unreadable.\n"); exit(1); }
$data = json_decode($json, true);
if (!is_array($data)) { fwrite(STDERR, "Invalid JSON config payload.\n"); exit(1); }
$name = '\'''"${escaped_name}"''\'';
if ("'"${storage}"'" === "site_option_json") { update_site_option($name, $data); } else { update_option($name, $data); }
echo "Imported JSON option: {$name}\n";'
  run_wp eval "${code}"
}

apply_advanced_editor_tools_json() {
  local file_path="$1"
  local escaped_file code

  escaped_file="${file_path//\\/\\\\}"
  escaped_file="${escaped_file//\'/\\\'}"
  code='$file = '\'''"${escaped_file}"''\'';
if (!is_file($file)) { fwrite(STDERR, "Advanced Editor Tools settings file not found.\n"); exit(1); }
$json = file_get_contents($file);
if (!is_string($json) || $json === "") { fwrite(STDERR, "Advanced Editor Tools settings file is empty or unreadable.\n"); exit(1); }
$data = json_decode($json, true);
if (!is_array($data) || !isset($data["settings"], $data["admin_settings"]) || !is_array($data["settings"]) || !is_array($data["admin_settings"])) {
    fwrite(STDERR, "Invalid Advanced Editor Tools export payload.\n");
    exit(1);
}
update_option("tadv_settings", $data["settings"]);
update_option("tadv_admin_settings", $data["admin_settings"]);
echo "Imported Advanced Editor Tools settings.\n";'
  run_wp eval "${code}"
}

apply_option_text() {
  local storage="$1"
  local file_path="$2"
  local option_name="$3"
  local escaped_file escaped_name code

  escaped_file="${file_path//\\/\\\\}"
  escaped_file="${escaped_file//\'/\\\'}"
  escaped_name="${option_name//\\/\\\\}"
  escaped_name="${escaped_name//\'/\\\'}"
  code='$file = '\'''"${escaped_file}"''\'';
if (!is_file($file)) { fwrite(STDERR, "Text config file not found.\n"); exit(1); }
$value = file_get_contents($file);
if (!is_string($value)) { fwrite(STDERR, "Text config file unreadable.\n"); exit(1); }
$name = '\'''"${escaped_name}"''\'';
if ("'"${storage}"'" === "site_option_text") { update_site_option($name, $value); } else { update_option($name, $value); }
echo "Imported text option: {$name}\n";'
  run_wp eval "${code}"
}

apply_license_vault_json() {
  local file_path="$1"
  local mode="$2"
  local escaped_file code

  escaped_file="${file_path//\\/\\\\}"
  escaped_file="${escaped_file//\'/\\\'}"
  code='$file = '\'''"${escaped_file}"''\'';
if (!is_file($file)) { fwrite(STDERR, "License vault file not found.\n"); exit(1); }
$json = file_get_contents($file);
if (!is_string($json) || $json === "") { fwrite(STDERR, "License vault file is empty or unreadable.\n"); exit(1); }
$decoded = json_decode($json, true);
if (!is_array($decoded) || (($decoded["tool"] ?? "") !== "mrn-license-vault")) {
    fwrite(STDERR, "Invalid license-vault export payload.\n");
    exit(1);
}
$entries = (isset($decoded["entries"]) && is_array($decoded["entries"])) ? $decoded["entries"] : [];
$mode = "'"${mode}"'";
if ($mode !== "merge" && $mode !== "overwrite") { $mode = "overwrite"; }
if (!class_exists("MRN_License_Vault")) {
    $plugin = WP_PLUGIN_DIR . "/mrn-license-vault/mrn-license-vault.php";
    if (is_file($plugin)) { require_once $plugin; }
}
if (!class_exists("MRN_License_Vault")) {
    fwrite(STDERR, "MRN License Vault plugin not available.\n");
    exit(1);
}
$ref = new ReflectionClass("MRN_License_Vault");
$method = $ref->getMethod("import_vault_entries");
if (method_exists($method, "setAccessible")) { $method->setAccessible(true); }
$count = (int) $method->invoke(null, $entries, $mode);
echo "Imported license vault entries: {$count}\n";'
  run_wp eval "${code}"
}

apply_unified_export_zip() {
  local file_path="$1"
  local code

  code='$zipPath = "'"${file_path}"'";
if (!is_file($zipPath)) { fwrite(STDERR, "Unified export ZIP not found.\n"); exit(1); }
if (!class_exists("ZipArchive")) { fwrite(STDERR, "ZipArchive is not available.\n"); exit(1); }
$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) { fwrite(STDERR, "Could not open unified export ZIP.\n"); exit(1); }
$mrnJson = null;
$ameJson = null;
$ameContainers = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = (string) $zip->getNameIndex($i);
    $base = basename($name);
    if ($mrnJson === null && $base === "mrn-editor-tools-settings.json") {
        $mrnJson = $zip->getFromIndex($i);
    }
    if ($ameJson === null && $base === "ame-toolbar-editor.settings.json") {
        $ameJson = $zip->getFromIndex($i);
    }
    $isAmeDat = (strpos($base, "admin-menu-editor-pro.") === 0 && substr($base, -4) === ".dat");
    $isMenuSnapshot = (strpos($base, "admin menu (") !== false);
    if ($isAmeDat && !$isMenuSnapshot) {
        $raw = $zip->getFromIndex($i);
        if (is_string($raw) && $raw !== "") {
            $decoded = json_decode($raw, true);
            if (
                is_array($decoded)
                && isset($decoded["format"], $decoded["settings"])
                && is_array($decoded["format"])
                && (($decoded["format"]["name"] ?? "") === "Admin Menu Editor configuration container")
                && is_array($decoded["settings"])
            ) {
                $ameContainers[] = $decoded;
            }
        }
    }
}
$zip->close();
$imported = 0;
if (is_string($mrnJson) && $mrnJson !== "") {
    $decoded = json_decode($mrnJson, true);
    if (is_array($decoded)) {
        update_option("mrn_editor_tools_settings", $decoded);
        $imported++;
    }
}
if (is_string($ameJson) && $ameJson !== "") {
    $decoded = json_decode($ameJson, true);
    if (is_array($decoded)) {
        if (array_key_exists("ws_abe_admin_bar_settings", $decoded)) {
            update_option("ws_abe_admin_bar_settings", is_array($decoded["ws_abe_admin_bar_settings"]) ? $decoded["ws_abe_admin_bar_settings"] : []);
        }
        if (array_key_exists("ws_abe_admin_bar_nodes", $decoded)) {
            update_option("ws_abe_admin_bar_nodes", is_array($decoded["ws_abe_admin_bar_nodes"]) ? $decoded["ws_abe_admin_bar_nodes"] : []);
        }
        if (array_key_exists("ws_abe_override_global_menu", $decoded)) {
            update_option("ws_abe_override_global_menu", !empty($decoded["ws_abe_override_global_menu"]));
        }
        $imported++;
    }
}
$ameImported = 0;
$ameSkipped = 0;
$ameFailed = 0;
if (!empty($ameContainers)) {
    if (!class_exists("YahnisElsts\\\\AdminMenuEditor\\\\ImportExport\\\\wsAmeImportExportFeature")) {
        $plugin = WP_PLUGIN_DIR . "/admin-menu-editor-pro/menu-editor.php";
        if (is_file($plugin)) { require_once $plugin; }
    }

    global $wp_menu_editor;
    if (!isset($wp_menu_editor) || !is_object($wp_menu_editor)) {
        if (class_exists("ameMenu")) {
            if (method_exists("ameMenu", "get_instance")) {
                $wp_menu_editor = ameMenu::get_instance();
            } elseif (method_exists("ameMenu", "getEditor")) {
                $wp_menu_editor = ameMenu::getEditor();
            }
        }
    }
    if ((!isset($wp_menu_editor) || !is_object($wp_menu_editor)) && function_exists("do_action")) {
        do_action("admin_menu", "");
        if (class_exists("ameMenu")) {
            if (method_exists("ameMenu", "get_instance")) {
                $wp_menu_editor = ameMenu::get_instance();
            } elseif (method_exists("ameMenu", "getEditor")) {
                $wp_menu_editor = ameMenu::getEditor();
            }
        }
    }

    if (class_exists("YahnisElsts\\\\AdminMenuEditor\\\\ImportExport\\\\wsAmeImportExportFeature") && isset($wp_menu_editor)) {
        $featureClass = "YahnisElsts\\AdminMenuEditor\\ImportExport\\wsAmeImportExportFeature";
        $feature = $featureClass::get_instance($wp_menu_editor);
        foreach ($ameContainers as $container) {
            $enabledComponents = null;
            $componentConfigs = null;

            if (isset($container["settings"]) && is_array($container["settings"]) && array_key_exists("roles-and-capabilities", $container["settings"])) {
                $roleNames = [];
                $rolesBlock = $container["settings"]["roles-and-capabilities"] ?? null;
                if (is_array($rolesBlock) && isset($rolesBlock["roles"]) && is_array($rolesBlock["roles"])) {
                    foreach (array_keys($rolesBlock["roles"]) as $roleName) {
                        if (is_string($roleName) && ($roleName !== "")) {
                            $roleNames[] = $roleName;
                        }
                    }
                }

                if (!empty($roleNames)) {
                    $componentConfigs = [
                        "roles-and-capabilities" => wp_json_encode([
                            "roles" => array_values(array_unique($roleNames)),
                            "localOnlyCapStrategy" => "disable",
                        ]),
                    ];
                } else {
                    //Skip role import if no explicit role selection can be derived from the container.
                    $enabledComponents = array_fill_keys(array_keys($container["settings"]), true);
                    $enabledComponents["roles-and-capabilities"] = false;
                }
            }

            $result = $feature->import_data($container, $enabledComponents, $componentConfigs);
            if (!is_array($result)) {
                $ameFailed++;
                continue;
            }

            foreach ($result as $componentKey => $statusObj) {
                if (!is_object($statusObj) || !method_exists($statusObj, "isAnySuccess")) {
                    continue;
                }
                if ($statusObj->isAnySuccess()) {
                    $ameImported++;
                    continue;
                }
                $primary = method_exists($statusObj, "getPrimaryMessage") ? strtolower(trim((string) $statusObj->getPrimaryMessage())) : "";
                if ($primary === "no changes" || $primary === "skipped" || $primary === "nothing to import") {
                    $ameSkipped++;
                } else {
                    $ameFailed++;
                }
            }
        }
    } else {
        fwrite(STDERR, "AME import skipped: import engine/editor instance unavailable.\n");
        $ameFailed++;
    }
}

$recognized = $imported + count($ameContainers);
if ($recognized === 0) { fwrite(STDERR, "No recognized unified export files found in ZIP.\n"); exit(1); }
if ($ameFailed > 0) { fwrite(STDERR, "AME import encountered failures ({$ameFailed}).\n"); exit(1); }
echo "Imported unified exporter payload sections: {$imported}; AME components imported={$ameImported}, skipped={$ameSkipped}, failed={$ameFailed}\n";'
  run_wp eval "${code}"
}

apply_ame_toolbar_editor_json() {
  local file_path="$1"
  local escaped_file escaped_toolbar_url code

  escaped_file="${file_path//\\/\\\\}"
  escaped_file="${escaped_file//\'/\\\'}"
  escaped_toolbar_url=""

  if ensure_mrn_branding_assets; then
    escaped_toolbar_url="${MRN_BRANDING_TOOLBAR_URL//\\/\\\\}"
    escaped_toolbar_url="${escaped_toolbar_url//\'/\\\'}"
  fi

  code='$file = '\'''"${escaped_file}"''\'';
$toolbarLogoUrl = '\'''"${escaped_toolbar_url}"''\'';
if (!is_file($file)) { fwrite(STDERR, "AME Toolbar Editor file not found.\n"); exit(1); }
$json = file_get_contents($file);
if (!is_string($json) || $json === "") { fwrite(STDERR, "AME Toolbar Editor file is empty or unreadable.\n"); exit(1); }
$decoded = json_decode($json, true);
if (!is_array($decoded)) { fwrite(STDERR, "Invalid AME Toolbar Editor JSON payload.\n"); exit(1); }

$settings = $decoded["ws_abe_admin_bar_settings"] ?? [];
$nodes = $decoded["ws_abe_admin_bar_nodes"] ?? [];
$override = $decoded["ws_abe_override_global_menu"] ?? false;

if (!is_array($settings)) {
    fwrite(STDERR, "AME Toolbar Editor settings payload must be an object/array.\n");
    exit(1);
}
if (!is_array($nodes)) {
    fwrite(STDERR, "AME Toolbar Editor nodes payload must be an object/array.\n");
    exit(1);
}

if ($toolbarLogoUrl !== "") {
    if (!isset($settings["nodes"]) || !is_array($settings["nodes"])) {
        $settings["nodes"] = [];
    }
    if (!isset($settings["nodes"]["wp-logo"]) || !is_array($settings["nodes"]["wp-logo"])) {
        $settings["nodes"]["wp-logo"] = [
            "id" => "wp-logo",
            "group" => false,
            "parent" => false,
            "is_custom" => false,
            "is_hidden" => false,
        ];
    }
    if (!isset($settings["nodes"]["wp-logo"]["defaults"]) || !is_array($settings["nodes"]["wp-logo"]["defaults"])) {
        $settings["nodes"]["wp-logo"]["defaults"] = [];
    }
    $settings["nodes"]["wp-logo"]["is_hidden"] = false;
    $settings["nodes"]["wp-logo"]["defaults"]["title"] = "<img src=\"" . esc_url($toolbarLogoUrl) . "\" style=\"display: inline-block; max-height: 100%;padding: 0; margin: 0; vertical-align: top; position: relative;top: 50%; -ms-transform: translateY(-50%); transform: translateY(-50%);\">";
    $settings["nodes"]["wp-logo"]["defaults"]["href"] = "https://mrnwebdesigns.com";
    $settings["nodes"]["wp-logo"]["defaults"]["titleAttr"] = "";
}

update_option("ws_abe_admin_bar_settings", $settings);
update_option("ws_abe_admin_bar_nodes", $nodes);
update_option("ws_abe_override_global_menu", !empty($override));

echo "Imported AME Toolbar Editor settings.\n";'
  run_wp eval "${code}"
}

apply_ame_container_json() {
  local file_path="$1"
  local escaped_file code cli_output import_file temp_import_file status

  import_file="$(prepare_ame_container_import_file "${file_path}")"
  temp_import_file=""
  if [[ "${import_file}" != "${file_path}" ]]; then
    temp_import_file="${import_file}"
  fi

  escaped_file="${import_file//\\/\\\\}"
  escaped_file="${escaped_file//\'/\\\'}"

  if cli_output="$(run_wp admin-menu-editor import "${import_file}" 2>&1)"; then
    printf '%s\n' "${cli_output}"
    echo "AME container import complete via wp admin-menu-editor import."
    if ! apply_ame_roles_component_from_container "${import_file}"; then
      echo "WARNING: AME roles-and-capabilities follow-up import failed." >&2
    fi
    if [[ -n "${temp_import_file}" ]]; then
      rm -f "${temp_import_file}"
    fi
    return 0
  fi
  echo "AME CLI import failed; falling back to import engine path." >&2
  printf '%s\n' "${cli_output}" >&2

  code='$file = '\'''"${escaped_file}"''\'';
if (!is_file($file)) { fwrite(STDERR, "AME container file not found.\n"); exit(1); }
$json = file_get_contents($file);
if (!is_string($json) || $json === "") { fwrite(STDERR, "AME container file is empty or unreadable.\n"); exit(1); }
$container = json_decode($json, true);
if (!is_array($container)) { fwrite(STDERR, "Failed to parse AME container JSON.\n"); exit(1); }
if (
    !isset($container["format"]["name"], $container["settings"])
    || ($container["format"]["name"] !== "Admin Menu Editor configuration container")
    || !is_array($container["settings"])
) {
    fwrite(STDERR, "Unexpected JSON format. Not an AME configuration container.\n");
    exit(1);
}

if (!class_exists("YahnisElsts\\\\AdminMenuEditor\\\\ImportExport\\\\wsAmeImportExportFeature") && !class_exists("wsAmeImportExportFeature")) {
    $plugin = WP_PLUGIN_DIR . "/admin-menu-editor-pro/menu-editor.php";
    if (is_file($plugin)) { require_once $plugin; }
    $importExportFile = WP_PLUGIN_DIR . "/admin-menu-editor-pro/extras/import-export/import-export.php";
    if (is_file($importExportFile)) { require_once $importExportFile; }
}

global $wp_menu_editor;
if (!isset($wp_menu_editor) || !is_object($wp_menu_editor)) {
    if (class_exists("ameMenu")) {
        if (method_exists("ameMenu", "get_instance")) {
            $wp_menu_editor = ameMenu::get_instance();
        } elseif (method_exists("ameMenu", "getEditor")) {
            $wp_menu_editor = ameMenu::getEditor();
        }
    }
}
if ((!isset($wp_menu_editor) || !is_object($wp_menu_editor)) && function_exists("do_action")) {
    do_action("admin_menu", "");
    if (class_exists("ameMenu")) {
        if (method_exists("ameMenu", "get_instance")) {
            $wp_menu_editor = ameMenu::get_instance();
        } elseif (method_exists("ameMenu", "getEditor")) {
            $wp_menu_editor = ameMenu::getEditor();
        }
    }
}

$enabledComponents = null;
$componentConfigs = null;
if (array_key_exists("roles-and-capabilities", $container["settings"])) {
    $roleNames = [];
    $rolesBlock = $container["settings"]["roles-and-capabilities"] ?? null;
    if (is_array($rolesBlock) && isset($rolesBlock["roles"]) && is_array($rolesBlock["roles"])) {
        foreach (array_keys($rolesBlock["roles"]) as $roleName) {
            if (is_string($roleName) && ($roleName !== "")) {
                $roleNames[] = $roleName;
            }
        }
    }

    if (!empty($roleNames)) {
        $componentConfigs = [
            "roles-and-capabilities" => wp_json_encode([
                "roles" => array_values(array_unique($roleNames)),
                "localOnlyCapStrategy" => "disable",
            ]),
        ];
    } else {
        $enabledComponents = array_fill_keys(array_keys($container["settings"]), true);
        $enabledComponents["roles-and-capabilities"] = false;
    }
}

$featureClass = null;
if (class_exists("YahnisElsts\\AdminMenuEditor\\ImportExport\\wsAmeImportExportFeature")) {
    $featureClass = "YahnisElsts\\AdminMenuEditor\\ImportExport\\wsAmeImportExportFeature";
} elseif (class_exists("wsAmeImportExportFeature")) {
    $featureClass = "wsAmeImportExportFeature";
}
if ($featureClass === null) {
    fwrite(STDERR, "AME import engine class unavailable.\n");
    exit(1);
}
$feature = null;
if (is_callable([$featureClass, "get_instance"])) {
    try {
        $feature = $featureClass::get_instance();
    } catch (Throwable $e) {
        $feature = null;
    }
    if ((!is_object($feature)) && isset($wp_menu_editor) && is_object($wp_menu_editor)) {
        try {
            $feature = $featureClass::get_instance($wp_menu_editor);
        } catch (Throwable $e) {
            $feature = null;
        }
    }
}
if (!is_object($feature)) {
    fwrite(STDERR, "AME import engine/editor instance unavailable.\n");
    exit(1);
}
$moduleStatus = $feature->import_data($container, $enabledComponents, $componentConfigs);

$successful = 0;
$failed = 0;
$skipped = 0;
foreach ($moduleStatus as $id => $status) {
    if (!is_object($status) || !method_exists($status, "getPrimaryMessage")) {
        continue;
    }
    $message = (string)$status->getPrimaryMessage();
    echo $id . ": " . $message . "\n";

    if (method_exists($status, "isAnySuccess") && $status->isAnySuccess()) {
        $successful++;
        continue;
    }

    $primary = strtolower(trim($message));
    if ($primary === "no changes" || $primary === "skipped" || $primary === "nothing to import") {
        $skipped++;
    } else {
        $failed++;
    }
}

if (($successful <= 0) && ($failed > 0)) {
    fwrite(STDERR, "AME import failed.\n");
    exit(1);
}
echo "AME container import complete: success={$successful}, skipped={$skipped}, failed={$failed}\n";'
  status=0
  run_wp eval "${code}" || status=$?
  if [[ -n "${temp_import_file}" ]]; then
    rm -f "${temp_import_file}"
  fi
  return "${status}"
}

apply_ame_roles_component_from_container() {
  local file_path="$1"
  local escaped_file code

  escaped_file="${file_path//\\/\\\\}"
  escaped_file="${escaped_file//\'/\\\'}"

  code='$file = '\'''"${escaped_file}"''\'';
if (!is_file($file)) { fwrite(STDERR, "AME container file not found for roles import.\n"); exit(1); }
$json = file_get_contents($file);
if (!is_string($json) || $json === "") { fwrite(STDERR, "AME container file is empty or unreadable.\n"); exit(1); }
$container = json_decode($json, true);
if (!is_array($container) || !isset($container["settings"]) || !is_array($container["settings"])) {
    fwrite(STDERR, "Invalid AME container JSON for roles import.\n");
    exit(1);
}
if (!array_key_exists("roles-and-capabilities", $container["settings"])) {
    echo "AME roles-and-capabilities component not present in container; skipping.\n";
    exit(0);
}

if (!class_exists("YahnisElsts\\\\AdminMenuEditor\\\\ImportExport\\\\wsAmeImportExportFeature") && !class_exists("wsAmeImportExportFeature")) {
    $plugin = WP_PLUGIN_DIR . "/admin-menu-editor-pro/menu-editor.php";
    if (is_file($plugin)) { require_once $plugin; }
    $importExportFile = WP_PLUGIN_DIR . "/admin-menu-editor-pro/extras/import-export/import-export.php";
    if (is_file($importExportFile)) { require_once $importExportFile; }
}

global $wp_menu_editor;
if (!isset($wp_menu_editor) || !is_object($wp_menu_editor)) {
    if (class_exists("ameMenu")) {
        if (method_exists("ameMenu", "get_instance")) {
            $wp_menu_editor = ameMenu::get_instance();
        } elseif (method_exists("ameMenu", "getEditor")) {
            $wp_menu_editor = ameMenu::getEditor();
        }
    }
}
if ((!isset($wp_menu_editor) || !is_object($wp_menu_editor)) && function_exists("do_action")) {
    do_action("admin_menu", "");
    if (class_exists("ameMenu")) {
        if (method_exists("ameMenu", "get_instance")) {
            $wp_menu_editor = ameMenu::get_instance();
        } elseif (method_exists("ameMenu", "getEditor")) {
            $wp_menu_editor = ameMenu::getEditor();
        }
    }
}

$rolesBlock = $container["settings"]["roles-and-capabilities"] ?? null;
$roleNames = [];
if (is_array($rolesBlock) && isset($rolesBlock["roles"]) && is_array($rolesBlock["roles"])) {
    foreach (array_keys($rolesBlock["roles"]) as $roleName) {
        if (is_string($roleName) && ($roleName !== "")) {
            $roleNames[] = $roleName;
        }
    }
}
if (empty($roleNames)) {
    echo "AME roles component has no role entries; skipping explicit roles import.\n";
    exit(0);
}

$enabledComponents = array_fill_keys(array_keys($container["settings"]), false);
$enabledComponents["roles-and-capabilities"] = true;
$componentConfigs = [
    "roles-and-capabilities" => wp_json_encode([
        "roles" => array_values(array_unique($roleNames)),
        "localOnlyCapStrategy" => "disable",
    ]),
];

$featureClass = null;
if (class_exists("YahnisElsts\\AdminMenuEditor\\ImportExport\\wsAmeImportExportFeature")) {
    $featureClass = "YahnisElsts\\AdminMenuEditor\\ImportExport\\wsAmeImportExportFeature";
} elseif (class_exists("wsAmeImportExportFeature")) {
    $featureClass = "wsAmeImportExportFeature";
}
if ($featureClass === null) {
    fwrite(STDERR, "AME import engine class unavailable for roles import.\n");
    exit(1);
}
$feature = null;
if (is_callable([$featureClass, "get_instance"])) {
    try {
        $feature = $featureClass::get_instance();
    } catch (Throwable $e) {
        $feature = null;
    }
    if ((!is_object($feature)) && isset($wp_menu_editor) && is_object($wp_menu_editor)) {
        try {
            $feature = $featureClass::get_instance($wp_menu_editor);
        } catch (Throwable $e) {
            $feature = null;
        }
    }
}
if (!is_object($feature)) {
    fwrite(STDERR, "AME import engine/editor instance unavailable for roles import.\n");
    exit(1);
}
$moduleStatus = $feature->import_data($container, $enabledComponents, $componentConfigs);
$status = $moduleStatus["roles-and-capabilities"] ?? null;
if (is_object($status) && method_exists($status, "getPrimaryMessage")) {
    $msg = (string) $status->getPrimaryMessage();
    echo "roles-and-capabilities: {$msg}\n";
    if (method_exists($status, "isAnySuccess") && $status->isAnySuccess()) {
        echo "AME roles-and-capabilities import complete.\n";
        exit(0);
    }
    $primary = strtolower(trim($msg));
    if ($primary === "no changes" || $primary === "skipped" || $primary === "nothing to import") {
        echo "AME roles-and-capabilities import skipped/no changes.\n";
        exit(0);
    }
}
fwrite(STDERR, "AME roles-and-capabilities import failed.\n");
exit(1);'
  run_wp eval "${code}"
}

apply_updraftplus_settings_json() {
  local file_path="$1"
  local option_name="${2:-updraft_options}"
  local payload_b64 option_b64 payload

  if [[ ! -f "${file_path}" ]]; then
    echo "Updraft settings file not found: ${file_path}" >&2
    return 1
  fi

  payload="$(cat "${file_path}")"
  payload_b64="$(printf '%s' "${payload}" | base64 | tr -d '\n')"
  option_b64="$(printf '%s' "${option_name}" | base64 | tr -d '\n')"

  run_wp eval '
$json = base64_decode("'"${payload_b64}"'", true);
$option_name = base64_decode("'"${option_b64}"'", true);
if ($json === false || $option_name === false) {
    fwrite(STDERR, "Invalid Updraft importer arguments.\n");
    exit(1);
}
if ($json === "") { fwrite(STDERR, "Updraft settings payload is empty.\n"); exit(1); }
$decoded = json_decode($json, true);
if (!is_array($decoded)) { fwrite(STDERR, "Invalid Updraft settings JSON payload.\n"); exit(1); }

$settings = $decoded["data"] ?? null;
if (!is_array($settings)) {
    fwrite(STDERR, "Updraft settings export is missing a valid data payload.\n");
    exit(1);
}

$placeholder_array_keys = [
    "updraft_service",
    "updraft_email",
    "updraft_report_warningsonly",
    "updraft_report_wholebackup",
    "updraft_report_dbbackup",
];

foreach ($placeholder_array_keys as $key) {
    if (!array_key_exists($key, $settings)) {
        continue;
    }
    $value = $settings[$key];
    if (is_array($value)) {
        $settings[$key] = array_values(array_filter($value, static function ($item) {
            return $item !== "0" && $item !== "" && $item !== 0 && $item !== null;
        }));
        continue;
    }
    if ($value === "0" || $value === "" || $value === 0) {
        $settings[$key] = [];
    }
}

// Apply the shared backup policy to every imported site. Use a deterministic
// overnight slot so CloudPanel sites do not all start large jobs at midnight.
$site_host = (string) wp_parse_url(home_url("/"), PHP_URL_HOST);
$site_namespace = sanitize_title($site_host);
if ("" === $site_namespace) {
    $site_namespace = "wordpress-site";
}
$schedule_slot = abs((int) crc32($site_host)) % 240;
$schedule_time = sprintf("%02d:%02d", 1 + intdiv($schedule_slot, 60), $schedule_slot % 60);

$settings["updraft_interval"] = "daily";
$settings["updraft_interval_database"] = "daily";
$settings["updraft_starttime_files"] = $schedule_time;
$settings["updraft_starttime_db"] = $schedule_time;
$settings["updraft_retain"] = "4";
$settings["updraft_retain_db"] = "4";
$settings["updraft_delete_local"] = "1";
$settings["updraft_include_wpcore"] = "0";

// The Updraft S3 path is "bucket/optional-prefix". Give each site its own
// prefix so a restore or remote rescan cannot import another site history.
if (isset($settings["updraft_s3"]["settings"]) && is_array($settings["updraft_s3"]["settings"])) {
    foreach ($settings["updraft_s3"]["settings"] as $instance_id => $instance_settings) {
        if (!is_array($instance_settings)) {
            continue;
        }
        $current_path = isset($instance_settings["path"]) ? trim((string) $instance_settings["path"], "/") : "";
        $path_parts = array_values(array_filter(explode("/", $current_path), "strlen"));
        $bucket = $path_parts[0] ?? "";
        if ("" === $bucket) {
            continue;
        }
        $settings["updraft_s3"]["settings"][$instance_id]["path"] = $bucket . "/sites/" . $site_namespace;
    }
}

$imported = 0;
foreach ($settings as $key => $value) {
    if (!is_string($key) || $key === "") {
        continue;
    }
    update_option($key, $value);
    $imported++;
}

// Keep a full payload copy for diagnostics/compatibility, but write live settings
// as individual options because Updraft reads them that way.
update_option($option_name, $settings);
echo "Imported Updraft settings into {$imported} individual options and {$option_name}\n";
echo "Applied Updraft policy for {$site_namespace}: daily at {$schedule_time}, retain 4\n";

// Direct option imports do not invoke the settings API callbacks that normally
// create the Updraft WP-Cron rows. Schedule them explicitly after import.
global $updraftplus;
if (is_object($updraftplus)) {
    if (method_exists($updraftplus, "schedule_backup")) {
        $updraftplus->schedule_backup($settings["updraft_interval"]);
    }
    if (method_exists($updraftplus, "schedule_backup_database")) {
        $updraftplus->schedule_backup_database($settings["updraft_interval_database"]);
    }
}

// If the MU retention helper is available, reconcile its schedule immediately
// after importing Updraft settings.
if (function_exists("mrn_updraft_local_retention_schedule_cleanup")) {
    mrn_updraft_local_retention_schedule_cleanup();
    $hook = "mrn_updraft_local_retention_cleanup";
    $event = function_exists("wp_get_scheduled_event") ? wp_get_scheduled_event($hook) : false;

    if ($event && isset($event->schedule)) {
        echo "Verified MU retention cron hook {$hook} ({$event->schedule})\n";
    } else {
        echo "MU retention cron hook {$hook} is not currently scheduled\n";
    }
}'
}

resolve_file_path() {
  local ref="$1"
  if [[ "${ref}" == /* ]]; then
    printf '%s' "${ref}"
    return 0
  fi
  if [[ "${ref}" == secret:* ]]; then
    printf '%s/%s' "${STACK_ROOT}/secrets" "${ref#secret:}"
    return 0
  fi
  printf '%s/%s' "${EXPORTS_DIR}" "${ref}"
}

errors=0

while IFS= read -r raw || [[ -n "${raw}" ]]; do
  line="${raw%%#*}"
  line="$(printf '%s' "${line}" | tr -d '\r' | xargs)"
  [[ -z "${line}" ]] && continue

  IFS='|' read -r type file_ref target mode <<< "${line}"
  type="$(printf '%s' "${type:-}" | xargs)"
  file_ref="$(printf '%s' "${file_ref:-}" | xargs)"
  target="$(printf '%s' "${target:-}" | xargs)"
  mode="$(printf '%s' "${mode:-}" | xargs)"

  if [[ -z "${type}" || -z "${file_ref}" ]]; then
    echo "Importer warning: invalid mapping line (missing type/file): ${line}"
    errors=$((errors + 1))
    continue
  fi

  file_path="$(resolve_file_path "${file_ref}")"
  if [[ ! -f "${file_path}" ]]; then
    echo "Importer warning: file not found for mapping '${type}': ${file_path}"
    errors=$((errors + 1))
    continue
  fi

  if [[ "${type}" == "option_json" || "${type}" == "site_option_json" ]]; then
    if [[ -z "${target}" ]]; then
      echo "Importer warning: option name required for ${type}: ${line}"
      errors=$((errors + 1))
      continue
    fi
    if ! apply_option_json "${type}" "${file_path}" "${target}"; then
      echo "Importer warning: failed applying ${type} (${target}) from ${file_path}"
      errors=$((errors + 1))
    fi
    continue
  fi

  if [[ "${type}" == "ame_container_json" ]]; then
    file_path="$(resolve_file_path "${file_ref}")"
    if [[ ! -f "${file_path}" ]]; then
      echo "Importer warning: missing AME container file ${file_path}"
      errors=$((errors + 1))
      continue
    fi
    if ! apply_ame_container_json "${file_path}"; then
      echo "Importer warning: failed importing AME container from ${file_path}"
      errors=$((errors + 1))
    fi
    continue
  fi

  if [[ "${type}" == "ame_toolbar_editor_json" ]]; then
    file_path="$(resolve_file_path "${file_ref}")"
    if [[ ! -f "${file_path}" ]]; then
      echo "Importer warning: missing AME Toolbar Editor file ${file_path}"
      errors=$((errors + 1))
      continue
    fi
    if ! apply_ame_toolbar_editor_json "${file_path}"; then
      echo "Importer warning: failed importing AME Toolbar Editor settings from ${file_path}"
      errors=$((errors + 1))
    fi
    continue
  fi

  if [[ "${type}" == "advanced_editor_tools_json" ]]; then
    if ! apply_advanced_editor_tools_json "${file_path}"; then
      echo "Importer warning: failed importing Advanced Editor Tools settings from ${file_path}"
      errors=$((errors + 1))
    fi
    continue
  fi

  if [[ "${type}" == "option_text" || "${type}" == "site_option_text" ]]; then
    if [[ -z "${target}" ]]; then
      echo "Importer warning: option name required for ${type}: ${line}"
      errors=$((errors + 1))
      continue
    fi
    if ! apply_option_text "${type}" "${file_path}" "${target}"; then
      echo "Importer warning: failed applying ${type} (${target}) from ${file_path}"
      errors=$((errors + 1))
    fi
    continue
  fi

  if [[ "${type}" == "updraftplus_settings_json" ]]; then
    option_name="${target:-updraft_options}"
    if ! apply_updraftplus_settings_json "${file_path}" "${option_name}"; then
      echo "Importer warning: failed importing Updraft settings from ${file_path}"
      errors=$((errors + 1))
    fi
    continue
  fi

  if [[ "${type}" == "mrn_license_vault_json" ]]; then
    vault_mode="${target:-overwrite}"
    if ! apply_license_vault_json "${file_path}" "${vault_mode}"; then
      echo "Importer warning: failed importing MRN License Vault payload from ${file_path}"
      errors=$((errors + 1))
    fi
    continue
  fi

  if [[ "${type}" == "mrn_unified_export_zip" ]]; then
    if ! apply_unified_export_zip "${file_path}"; then
      echo "Importer warning: failed importing MRN Unified Export payload from ${file_path}"
      errors=$((errors + 1))
    fi
    continue
  fi

  echo "Importer warning: unknown mapping type '${type}'"
  errors=$((errors + 1))
done < "${IMPORT_MANIFEST}"

if ! apply_happyfiles_defaults; then
  echo "Importer warning: failed applying HappyFiles defaults."
  errors=$((errors + 1))
fi

if [[ "${errors}" -gt 0 ]]; then
  echo "Importer completed with ${errors} warning(s)."
else
  echo "Importer completed with no warnings."
fi

exit 0
