#!/usr/bin/env bash
set -euo pipefail
gateway_test_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec python3 "$gateway_test_root/access-profile-hook.py" "$@"
