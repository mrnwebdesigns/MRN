#!/usr/bin/env bash
set -euo pipefail

ENGINE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
SITE_URL="${1:-${MRN_QA_BASE_URL:-}}"
SMOKE_SCOPE="${MRN_QA_SMOKE_SCOPE:-public}"
SAMPLE_PATH="${MRN_QA_SAMPLE_PATH:-/sample-page/}"
IGNORE_REGEX="${MRN_QA_PLAYWRIGHT_IGNORE_REGEX:-}"

if [[ -z "${SITE_URL}" ]]; then
  echo "SITE_URL is required for engine Playwright smoke." >&2
  exit 1
fi

if ! command -v npm >/dev/null 2>&1; then
  echo "npm is required for engine Playwright smoke." >&2
  exit 1
fi

if [[ ! -d "${ENGINE_ROOT}/node_modules/@playwright/test" ]]; then
  echo "Playwright deps missing in engine repo. Run:"
  echo "  cd ${ENGINE_ROOT} && npm install"
  echo "  npx playwright install chromium"
  exit 1
fi

cd "${ENGINE_ROOT}"

env \
  MRN_QA_BASE_URL="${SITE_URL}" \
  MRN_QA_SMOKE_SCOPE="${SMOKE_SCOPE}" \
  MRN_QA_SAMPLE_PATH="${SAMPLE_PATH}" \
  MRN_QA_PLAYWRIGHT_IGNORE_REGEX="${IGNORE_REGEX}" \
  MRN_QA_ADMIN_USER="${MRN_QA_ADMIN_USER:-}" \
  MRN_QA_ADMIN_PASS="${MRN_QA_ADMIN_PASS:-}" \
  npx playwright test --config "${ENGINE_ROOT}/playwright.config.mjs"
