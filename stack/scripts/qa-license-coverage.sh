#!/usr/bin/env bash
# Verifies every third-party packaged plugin in the bootstrap manifest either
# has a licenses.txt mapping or a declared exemption.
#
# Vendor-neutral: this checks manifest coverage only. It never reads secret
# values and never contacts a secret provider.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PLUGINS_FILE="${REPO_ROOT}/stack/manifests/plugins.txt"
LICENSES_FILE="${REPO_ROOT}/stack/manifests/licenses.txt"
EXEMPTIONS_FILE="${REPO_ROOT}/stack/manifests/license-exemptions.txt"

fail_count=0
pass() { printf 'PASS  %s\n' "$1"; }
fail() { printf 'FAIL  %s\n' "$1" >&2; fail_count=$((fail_count + 1)); }

for f in "${PLUGINS_FILE}" "${LICENSES_FILE}" "${EXEMPTIONS_FILE}"; do
  [[ -f "${f}" ]] || { echo "Missing required manifest: ${f}" >&2; exit 1; }
done

# Plugin folders that have a license mapping.
license_folders=()
while IFS= read -r folder; do
  [[ -n "${folder}" ]] && license_folders+=("${folder}")
done < <(
  grep -v '^[[:space:]]*#' "${LICENSES_FILE}" | grep -v '^[[:space:]]*$' \
    | cut -d'|' -f1 | cut -d'/' -f1 | sort -u
)

is_mrn_owned() {
  case "$1" in
    mrn-*|searchwp-editor-performance|background-video-popout-disabler) return 0 ;;
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
    # Package filenames may carry a version suffix, e.g. searchwp-4.6.1.zip.
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
    pass "${zip_name} -> licenses.txt mapping"
  elif has_exemption "${zip_name}"; then
    pass "${zip_name} -> declared exemption"
  else
    fail "${zip_name} has no licenses.txt mapping and no declared exemption"
  fi
done < "${PLUGINS_FILE}"

# Every secret file referenced by licenses.txt must be declared in the
# vendor-neutral credential map, so materialisation has a source key.
CRED_FILES="${REPO_ROOT}/stack/manifests/credential-files.txt"
if [[ -f "${CRED_FILES}" ]]; then
  while IFS= read -r ref; do
    [[ -z "${ref}" ]] && continue
    if grep -q "|${ref}|" "${CRED_FILES}"; then
      pass "${ref} declared in credential-files.txt"
    else
      fail "${ref} is used by licenses.txt but not declared in credential-files.txt"
    fi
  done < <(
    grep -v '^[[:space:]]*#' "${LICENSES_FILE}" | grep -v '^[[:space:]]*$' \
      | sed 's/.*secretfile[a-z]*://' | sort -u
  )
else
  fail "Missing credential map: ${CRED_FILES}"
fi

if [[ "${fail_count}" -gt 0 ]]; then
  echo "License coverage check failed with ${fail_count} issue(s)." >&2
  exit 1
fi
echo "License coverage check passed."
