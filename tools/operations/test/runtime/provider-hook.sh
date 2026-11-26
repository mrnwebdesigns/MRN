#!/usr/bin/env bash
set -euo pipefail
gateway_test_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
node "$gateway_test_root/check-provider.mjs" "$@"
