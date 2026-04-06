#!/usr/bin/env bash
set -euo pipefail

STACK_ROOT=""
SITE_BOOTSTRAP=""
MARKER_NAME=".mrn_bootstrapped"
SITES_ROOT="/home"
SITE_DISCOVERY_GLOB=""
NOTIFY_EMAIL=""
DRY_RUN="false"
STATUS_DIR=""
STATUS_FILE=""

usage() {
  cat <<'USAGE'
Usage:
  bootstrap-new-sites.sh --stack-root /opt/mrnplugins [--site-discovery-glob '/home/*stack*/htdocs/*,/home/*strap*/htdocs/*'] [--notify-email you@example.com] [--sites-root /home] [--dry-run]

This script scans CloudPanel-style paths:
  /home/<site-user>/htdocs/<domain>

For each WordPress install without a marker file, it runs:
  scripts/site-bootstrap.sh
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --stack-root)
      STACK_ROOT="${2:-}"
      shift 2
      ;;
    --sites-root)
      SITES_ROOT="${2:-}"
      shift 2
      ;;
    --site-discovery-glob)
      SITE_DISCOVERY_GLOB="${2:-}"
      shift 2
      ;;
    --notify-email)
      NOTIFY_EMAIL="${2:-}"
      shift 2
      ;;
    --dry-run)
      DRY_RUN="true"
      shift
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

if [[ -z "${STACK_ROOT}" ]]; then
  STACK_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
fi

SITE_BOOTSTRAP="${STACK_ROOT}/scripts/site-bootstrap.sh"
STATUS_DIR="${STACK_ROOT}/runtime"
STATUS_FILE="${STATUS_DIR}/bootstrap-status.env"

if [[ ! -x "${SITE_BOOTSTRAP}" ]]; then
  echo "Bootstrap script missing or not executable: ${SITE_BOOTSTRAP}" >&2
  exit 1
fi

mkdir -p "${STATUS_DIR}"

escape_status_value() {
  local val="${1:-}"
  val="${val//$'\n'/ }"
  val="${val//$'\r'/ }"
  printf '%s' "${val}"
}

write_bootstrap_status() {
  local running="$1"
  local current="$2"
  local total="$3"
  local site_path="${4:-}"
  local site_domain="${5:-}"
  local state="${6:-}"
  local ts
  ts="$(date -Is 2>/dev/null || date)"

  {
    printf 'RUNNING=%s\n' "$(escape_status_value "${running}")"
    printf 'CURRENT=%s\n' "$(escape_status_value "${current}")"
    printf 'TOTAL=%s\n' "$(escape_status_value "${total}")"
    printf 'SITE_PATH=%s\n' "$(escape_status_value "${site_path}")"
    printf 'SITE_DOMAIN=%s\n' "$(escape_status_value "${site_domain}")"
    printf 'STATE=%s\n' "$(escape_status_value "${state}")"
    printf 'UPDATED_AT=%s\n' "$(escape_status_value "${ts}")"
  } > "${STATUS_FILE}" || true

  chmod 0664 "${STATUS_FILE}" 2>/dev/null || true
}

clear_bootstrap_status() {
  write_bootstrap_status "0" "0" "0" "" "" "idle"
}

trap 'clear_bootstrap_status' EXIT

scan_and_bootstrap() {
  local site_user_dir domain_dir marker
  local -a domain_dirs
  local -a glob_parts
  local glob_part
  local -a matched_dirs
  local failed_count=0
  local total_targets=0
  local current_index=0
  shopt -s nullglob

  if [[ -n "${SITE_DISCOVERY_GLOB}" ]]; then
    IFS=',' read -r -a glob_parts <<< "${SITE_DISCOVERY_GLOB}"
    domain_dirs=()
    for glob_part in "${glob_parts[@]}"; do
      glob_part="$(printf '%s' "${glob_part}" | xargs)"
      [[ -n "${glob_part}" ]] || continue
      mapfile -t matched_dirs < <(compgen -G "${glob_part}" || true)
      if [[ "${#matched_dirs[@]}" -gt 0 ]]; then
        domain_dirs+=("${matched_dirs[@]}")
      fi
    done
    if [[ "${#domain_dirs[@]}" -gt 1 ]]; then
      mapfile -t domain_dirs < <(printf '%s\n' "${domain_dirs[@]}" | sort -u)
    fi
  else
    domain_dirs=()
    for site_user_dir in "${SITES_ROOT}"/*; do
      [[ -d "${site_user_dir}/htdocs" ]] || continue
      for domain_dir in "${site_user_dir}/htdocs"/*; do
        [[ -d "${domain_dir}" ]] || continue
        domain_dirs+=("${domain_dir}")
      done
    done
  fi

  for domain_dir in "${domain_dirs[@]}"; do
    [[ -d "${domain_dir}" ]] || continue
    if [[ ! -f "${domain_dir}/wp-config.php" && ! -f "${domain_dir}/public/wp-config.php" && ! -f "${domain_dir}/app/public/wp-config.php" ]]; then
      continue
    fi
    marker="${domain_dir}/${MARKER_NAME}"
    if [[ -f "${marker}" ]]; then
      continue
    fi
    total_targets=$((total_targets + 1))
  done

  if [[ "${total_targets}" -eq 0 ]]; then
    clear_bootstrap_status
    return 0
  fi

  for domain_dir in "${domain_dirs[@]}"; do
    [[ -d "${domain_dir}" ]] || continue
    if [[ ! -f "${domain_dir}/wp-config.php" && ! -f "${domain_dir}/public/wp-config.php" && ! -f "${domain_dir}/app/public/wp-config.php" ]]; then
      continue
    fi

    marker="${domain_dir}/${MARKER_NAME}"
    if [[ -f "${marker}" ]]; then
      continue
    fi

    current_index=$((current_index + 1))
    write_bootstrap_status "1" "${current_index}" "${total_targets}" "${domain_dir}" "$(basename "${domain_dir}")" "bootstrapping"

    echo "Found new WordPress site: ${domain_dir}"
    if [[ "${DRY_RUN}" == "true" ]]; then
      echo "Dry run: would bootstrap ${domain_dir}"
      write_bootstrap_status "1" "${current_index}" "${total_targets}" "${domain_dir}" "$(basename "${domain_dir}")" "dry-run"
      continue
    fi

    if [[ -n "${NOTIFY_EMAIL:-}" ]]; then
      if ! "${SITE_BOOTSTRAP}" --site-path "${domain_dir}" --notify-email "${NOTIFY_EMAIL}"; then
        echo "Bootstrap failed for ${domain_dir}; continuing scan." >&2
        failed_count=$((failed_count + 1))
        write_bootstrap_status "1" "${current_index}" "${total_targets}" "${domain_dir}" "$(basename "${domain_dir}")" "failed"
      fi
    else
      if ! "${SITE_BOOTSTRAP}" --site-path "${domain_dir}"; then
        echo "Bootstrap failed for ${domain_dir}; continuing scan." >&2
        failed_count=$((failed_count + 1))
        write_bootstrap_status "1" "${current_index}" "${total_targets}" "${domain_dir}" "$(basename "${domain_dir}")" "failed"
      fi
    fi
  done

  clear_bootstrap_status

  if [[ "${failed_count}" -gt 0 ]]; then
    echo "Bootstrap scan completed with ${failed_count} failed site(s)." >&2
    return 1
  fi
}

scan_and_bootstrap
