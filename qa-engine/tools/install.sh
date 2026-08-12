#!/usr/bin/env bash
set -euo pipefail

ENGINE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
TARGET_DIR="${HOME}/.local/bin"
TARGET_LINK="${TARGET_DIR}/mrn-qa"

mkdir -p "${TARGET_DIR}"
ln -sfn "${ENGINE_ROOT}/bin/mrn-qa" "${TARGET_LINK}"

cat <<MSG
Installed mrn-qa:
  ${TARGET_LINK} -> ${ENGINE_ROOT}/bin/mrn-qa

If needed, add to PATH:
  export PATH="${TARGET_DIR}:\$PATH"
MSG
