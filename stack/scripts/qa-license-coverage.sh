#!/usr/bin/env bash
# Verifies every third-party packaged plugin in the bootstrap manifest either
# has a premium-licenses.txt row or a declared exemption, and that every row
# names a known post-bootstrap license handler and well-formed logical keys.
#
# Bootstrap does not apply licenses; the MainWP license delivery actions do.
# Vendor-neutral: this checks manifest coverage only. It never reads secret
# values and never contacts a secret provider.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PLUGINS_FILE="${REPO_ROOT}/stack/manifests/plugins.txt"
LICENSES_FILE="${REPO_ROOT}/stack/manifests/premium-licenses.txt"
EXEMPTIONS_FILE="${REPO_ROOT}/stack/manifests/license-exemptions.txt"
BOOTSTRAP_SCRIPT="${REPO_ROOT}/stack/scripts/site-bootstrap.sh"

# Handler ids implemented by the child-side `mrn_plugin_licenses` operation in
# mrn-stack-deployment-agent. Keep in sync with that plugin's handler map.
KNOWN_HANDLERS=" acf_pro ame_pro happyfiles_pro seopress_pro updraftplus_premium wpforms "

fail_count=0
pass() { printf 'PASS  %s\n' "$1"; }
fail() { printf 'FAIL  %s\n' "$1" >&2; fail_count=$((fail_count + 1)); }

for f in "${PLUGINS_FILE}" "${LICENSES_FILE}" "${EXEMPTIONS_FILE}" "${BOOTSTRAP_SCRIPT}"; do
  [[ -f "${f}" ]] || { echo "Missing required file: ${f}" >&2; exit 1; }
done

registry_rows() {
  grep -v '^[[:space:]]*#' "${LICENSES_FILE}" | grep -v '^[[:space:]]*$' | tr -d '\r' || true
}

# Every registry row is well formed and names a known handler.
while IFS='|' read -r basename handler keys extra; do
  if [[ -z "${basename}" || -z "${handler}" || -z "${keys}" || -n "${extra}" ]]; then
    fail "Malformed premium-licenses.txt row for '${basename}' (expected plugin|handler|KEYS)"
    continue
  fi
  if [[ "${basename}" != */*.php ]]; then
    fail "${basename} is not a plugin basename (folder/file.php)"
  fi
  if [[ "${KNOWN_HANDLERS}" != *" ${handler} "* ]]; then
    fail "${basename} uses unknown license handler '${handler}'"
    continue
  fi
  if [[ ! "${keys}" =~ ^[A-Z][A-Z0-9_]*(,[A-Z][A-Z0-9_]*)*$ ]]; then
    fail "${basename} has malformed logical keys '${keys}'"
    continue
  fi
  pass "${basename} -> ${handler} (${keys})"
done < <(registry_rows)

# Plugin folders that have a registry row.
license_folders=()
while IFS= read -r folder; do
  [[ -n "${folder}" ]] && license_folders+=("${folder}")
done < <(registry_rows | cut -d'|' -f1 | cut -d'/' -f1 | sort -u)

is_mrn_owned() {
  case "$1" in
    mrn-*|background-video-popout-disabler) return 0 ;;
    *) return 1 ;;
  esac
}

has_exemption() {
  grep -v '^[[:space:]]*#' "${EXEMPTIONS_FILE}" | grep -v '^[[:space:]]*$' \
    | cut -d'|' -f1 | grep -qx "$1"
}

has_license_mapping() {
  local base="$1" folder
  for folder in "${license_folders[@]}"; do
    [[ -z "${folder}" ]] && continue
    if [[ "${base}" == "${folder}" ]]; then return 0; fi
    # Package filenames may carry a version suffix, e.g. updraftplus.2.26.6.zip.
    case "${base}" in
      "${folder}"[-._]*) return 0 ;;
    esac
  done
  return 1
}

while IFS= read -r line; do
  line="${line%%#*}"
  line="$(printf '%s' "${line}" | tr -d '\r' | xargs || true)"
  [[ -z "${line}" ]] && continue
  source="${line%%|*}"
  case "${source}" in
    /*.zip|*.zip) ;;
    *) continue ;;   # WordPress.org slugs manage their own updates.
  esac
  zip_name="$(basename "${source}")"
  base="${zip_name%.zip}"
  is_mrn_owned "${base}" && continue
  if has_license_mapping "${base}"; then
    pass "${zip_name} -> premium-licenses.txt row"
  elif has_exemption "${zip_name}"; then
    pass "${zip_name} -> declared exemption"
  else
    fail "${zip_name} has no premium-licenses.txt row and no declared exemption"
  fi
done < "${PLUGINS_FILE}"

# Bootstrap must never read or apply licenses again.
if grep -qE 'secretfile(json)?:|apply_licenses|LICENSES_FILE|licenses\.txt|LICENSE FAILURE' "${BOOTSTRAP_SCRIPT}"; then
  fail "site-bootstrap.sh references license sources; licenses are applied post-bootstrap only"
else
  pass "site-bootstrap.sh does not read or apply licenses"
fi

if [[ "${fail_count}" -gt 0 ]]; then
  echo "License coverage check failed with ${fail_count} issue(s)." >&2
  exit 1
fi
echo "License coverage check passed."
