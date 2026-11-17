#!/usr/bin/env bash
set -euo pipefail
[[ "$#" == 3 ]]
gateway_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)"
commit=328957b6fe5bcae423ad4a617f043379b0b7d73f
git -C "$gateway_root" diff --quiet "$commit" -- tools/operations/src tools/operations/package.json tools/operations/package-lock.json
node "$gateway_root/tools/operations/test/runtime/check-provider.mjs" "$@" read-only
