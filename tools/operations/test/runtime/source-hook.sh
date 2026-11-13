#!/usr/bin/env bash
set -euo pipefail
source_hook_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
[[ "$#" == 3 ]]
node "$source_hook_root/check-native-source.mjs" "$@"
