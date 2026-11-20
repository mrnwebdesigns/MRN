#!/usr/bin/env bash
# Synchronise premium plugin licenses from the MRN-business 1Password item into
# the MainWP Dashboard's sealed license store (MRN MainWP Operations API).
#
#   --check (default)  Read-only. Compare 1Password fingerprints with the
#                      Dashboard's `wp mrn-mainwp-licenses status`.
#   --apply            Seal each selected handler on the Dashboard. Requires
#                      --dashboard-backup-verified (the Dashboard's own backup
#                      gate is a separate, operator-run step).
#
# Secret values are read with `op read`, kept in shell memory, and sent to the
# Dashboard only on stdin of `wp mrn-mainwp-licenses set <handler>`. They are
# never placed in argv, files, logs or output. Only fingerprints are printed.
#
# See docs/MRN-PLUGIN-LICENSE-DELIVERY.md.
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  sync-dashboard-licenses.sh --vault <vault> --item <item> --wp-path <dashboard WordPress path>
                             [--dashboard-ssh mainwp-tailscale] [--handler <id>]...
                             [--check | --apply --dashboard-backup-verified]

Handlers: acf_pro ame_pro happyfiles_pro seopress_pro updraftplus_premium wpforms
The 1Password item holds one field per logical key, labelled exactly as in
stack/manifests/premium-licenses.txt (for example ACF_PRO_LICENSE_KEY).
USAGE
}

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
REGISTRY="${REPO_ROOT}/stack/manifests/premium-licenses.txt"
VAULT=""
ITEM=""
WP_PATH=""
DASHBOARD_SSH="mainwp-tailscale"
MODE="check"
BACKUP_VERIFIED=0
HANDLERS=()

while [[ $# -gt 0 ]]; do
  case "$1" in
    --vault) VAULT="${2:-}"; shift 2 ;;
    --item) ITEM="${2:-}"; shift 2 ;;
    --wp-path) WP_PATH="${2:-}"; shift 2 ;;
    --dashboard-ssh) DASHBOARD_SSH="${2:-}"; shift 2 ;;
    --handler) HANDLERS+=("${2:-}"); shift 2 ;;
    --check) MODE="check"; shift ;;
    --apply) MODE="apply"; shift ;;
    --dashboard-backup-verified) BACKUP_VERIFIED=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
  esac
done

[[ -n "${VAULT}" && -n "${ITEM}" && -n "${WP_PATH}" ]] || { usage >&2; exit 2; }
[[ "${WP_PATH}" =~ ^/[A-Za-z0-9._/-]+$ ]] || { echo "Invalid --wp-path." >&2; exit 2; }
[[ "${DASHBOARD_SSH}" =~ ^[A-Za-z0-9._-]+$ ]] || { echo "Invalid --dashboard-ssh alias." >&2; exit 2; }
if [[ "${MODE}" == "apply" && "${BACKUP_VERIFIED}" -ne 1 ]]; then
  echo "Refusing --apply without --dashboard-backup-verified (verify the Dashboard backup first)." >&2
  exit 2
fi
command -v op >/dev/null || { echo "1Password CLI (op) is required." >&2; exit 1; }
command -v python3 >/dev/null || { echo "python3 is required." >&2; exit 1; }

# Never let op select the personal account.
export OP_ACCOUNT="mrnwebdesigns.1password.com"

# handler -> comma-separated logical keys, from the coverage registry.
# (Portable to macOS /bin/bash 3.2: no associative arrays or mapfile.)
registry_rows() {
  grep -v '^[[:space:]]*#' "${REGISTRY}" | grep -v '^[[:space:]]*$' | tr -d '\r'
}
keys_for() {
  registry_rows | awk -F'|' -v h="$1" '$2 == h { print $3 }'
}

if [[ "${#HANDLERS[@]}" -eq 0 ]]; then
  while IFS= read -r handler; do
    HANDLERS+=("${handler}")
  done < <(registry_rows | cut -d'|' -f2 | sort)
fi
for handler in "${HANDLERS[@]}"; do
  [[ -n "$(keys_for "${handler}")" ]] || { echo "Unknown handler: ${handler}" >&2; exit 2; }
done

remote_wp() {
  # shellcheck disable=SC2029 # Arguments are validated identifiers/paths, never secrets.
  ssh -o ConnectTimeout=20 "${DASHBOARD_SSH}" "wp --path=$(printf '%q' "${WP_PATH}") mrn-mainwp-licenses $*"
}

# Emit a NUL-separated "KEY\0value\0..." stream on stdout from 1Password.
read_values() {
  local handler="$1" key value fields
  IFS=',' read -r -a fields <<< "$(keys_for "${handler}")"
  for key in "${fields[@]}"; do
    if ! value="$(op read "op://${VAULT}/${ITEM}/${key}" 2>/dev/null)"; then
      echo "1Password field missing or unreadable: ${key}" >&2
      return 1
    fi
    printf '%s\0%s\0' "${key}" "${value}"
  done
}

# Convert the NUL stream to JSON (apply) or a fingerprint (check), matching
# MRN_MainWP_Plugin_Licenses::fingerprint().
TRANSFORM='
import hashlib, json, sys
handler, mode = sys.argv[1], sys.argv[2]
parts = sys.stdin.buffer.read().split(b"\0")[:-1]
values = {parts[i].decode(): parts[i + 1].decode() for i in range(0, len(parts), 2)}
if mode == "json":
    sys.stdout.write(json.dumps(values))
else:
    ident = values["UPDRAFTPLUS_EMAIL"].strip().lower() if handler == "updraftplus_premium" else next(iter(values.values())).strip()
    sys.stdout.write(hashlib.sha256(("mrn-plugin-license-v1\0" + handler + "\0" + ident).encode()).hexdigest()[:16])
'

status="$(remote_wp status)" || { echo "Could not read Dashboard license status over ${DASHBOARD_SSH}." >&2; exit 1; }
failures=0
for handler in "${HANDLERS[@]}"; do
  if ! wanted="$(read_values "${handler}" | python3 -I -c "${TRANSFORM}" "${handler}" fingerprint)"; then
    failures=$((failures + 1)); continue
  fi
  current="$(printf '%s\n' "${status}" | awk -v h="${handler}" '$1 == h { print ($2 == "provisioned" ? $3 : "missing") }')"
  if [[ "${current}" == "${wanted}" ]]; then
    printf '%-20s current      %s\n' "${handler}" "${wanted}"
    continue
  fi
  if [[ "${MODE}" == "check" ]]; then
    printf '%-20s differs      1Password %s, Dashboard %s\n' "${handler}" "${wanted}" "${current:-missing}"
    continue
  fi
  if read_values "${handler}" | python3 -I -c "${TRANSFORM}" "${handler}" json | remote_wp set "${handler}" >/dev/null; then
    printf '%-20s provisioned  %s\n' "${handler}" "${wanted}"
  else
    printf '%-20s FAILED\n' "${handler}" >&2
    failures=$((failures + 1))
  fi
done

if [[ "${failures}" -gt 0 ]]; then
  echo "${failures} handler(s) failed." >&2
  exit 1
fi
if [[ "${MODE}" == "apply" ]]; then
  echo "Dashboard store updated. Next: preview-plugin-licenses-v1 for each affected site, then apply after a backup receipt."
fi
