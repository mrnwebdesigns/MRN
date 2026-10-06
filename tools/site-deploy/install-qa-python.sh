#!/usr/bin/env bash
# QA intentionally changes HOME. Never install its Python tools in user-site.
set -euo pipefail
: "${RUNNER_TEMP:?RUNNER_TEMP is required}"
: "${GITHUB_PATH:?GITHUB_PATH is required}"

qa_venv="$(mktemp -d "$RUNNER_TEMP/mrn-qa-python.XXXXXX")"
python3 -m venv "$qa_venv"
"$qa_venv/bin/python" -m pip install --disable-pip-version-check 'semgrep==1.179.0'

# Fail dependency setup before source acceptance if the relocated-HOME contract
# ever breaks. Only expose the environment to later steps after this succeeds.
qa_home="$(mktemp -d "$RUNNER_TEMP/mrn-qa-home.XXXXXX")"
HOME="$qa_home" SEMGREP_SEND_METRICS=off "$qa_venv/bin/semgrep" --version
printf '%s\n' "$qa_venv/bin" >> "$GITHUB_PATH"
