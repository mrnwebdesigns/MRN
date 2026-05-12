#!/usr/bin/env bash
set -euo pipefail

git config --global alias.mrn-qa '!mrn-qa run --project-root "$(pwd)"'

echo "Installed git alias: git mrn-qa"
echo "Usage:"
echo "  git mrn-qa"
