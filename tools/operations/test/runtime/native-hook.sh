#!/usr/bin/env bash
set -euo pipefail
gateway_test_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
[[ "$#" == 3 || "$#" == 4 ]]
node "$gateway_test_root/check-provider.mjs" "$1" "$2" "$3" native
if [[ "$#" == 4 ]]; then
  node "$gateway_test_root/check-management.mjs" "$@"
fi
