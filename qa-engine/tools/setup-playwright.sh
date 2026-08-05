#!/usr/bin/env bash
set -euo pipefail

ENGINE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"

cd "${ENGINE_ROOT}"

echo "Installing engine Playwright dependencies..."
npm install

echo "Installing Chromium browser for Playwright..."
npx playwright install chromium

echo "Playwright setup complete for MRN QA Engine."
