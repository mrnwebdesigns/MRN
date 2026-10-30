#!/usr/bin/env bash
set -euo pipefail
gateway_test_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
[[ "$#" == 3 || "$#" == 4 ]]
if [[ "${MRN_NATIVE_WRITE_WORKFLOWS:-0}" == 1 ]]; then
  [[ "$#" == 3 ]]
  node "$gateway_test_root/check-provider.mjs" "$@" write-workflows
elif [[ "$#" == 4 ]]; then
  node "$gateway_test_root/check-management.mjs" "$@"
else
  node "$gateway_test_root/check-provider.mjs" "$@" native
fi
