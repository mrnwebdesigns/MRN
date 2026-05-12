#!/usr/bin/env bash
set -euo pipefail

ENGINE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
REPO_NAME="${1:-MRN-qa-engine}"
VISIBILITY="${2:-private}"

if ! command -v gh >/dev/null 2>&1; then
  echo "GitHub CLI (gh) is required." >&2
  exit 1
fi

if [[ ! -d "${ENGINE_ROOT}/.git" ]]; then
  echo "Not a git repo: ${ENGINE_ROOT}" >&2
  exit 1
fi

cd "${ENGINE_ROOT}"

gh repo create "${REPO_NAME}" --"${VISIBILITY}" --source . --remote origin --push
