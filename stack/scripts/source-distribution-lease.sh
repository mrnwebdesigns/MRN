#!/usr/bin/env bash
# Source consumers hold this shared lease while reading one qualified bundle.
# This protects distribution files only; it never authorizes a site write.
MRN_SOURCE_LEASE_PROTOCOL=1

mrn_source_distribution_lease() {
  local stack_root="$1" lock
  lock="$(cd "${stack_root}/.." && pwd)/.mrn-source-publication.lock"
  if ! command -v flock >/dev/null 2>&1; then
    echo 'Source distribution lease requires flock; bootstrap is deferred.' >&2
    return 1
  fi
  if [[ -L "${lock}" ]]; then
    echo 'Source distribution lock may not be a symlink.' >&2
    return 1
  fi
  exec 19>>"${lock}"
  flock --shared 19
}
