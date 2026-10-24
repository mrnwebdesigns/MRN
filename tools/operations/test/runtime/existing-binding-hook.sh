#!/usr/bin/env bash
set -euo pipefail
gateway_test_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
[[ "$#" == 4 ]]
node "$gateway_test_root/check-existing-binding.mjs" "$@"
