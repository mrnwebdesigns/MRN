#!/usr/bin/env bash

set -euo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/report.sh"

# Inputs expected from caller:
# PROJECT_ROOT, STACK_ROOT, SITE_PATH, SITE_URL
# CHANGED_FILES (newline-delimited)
# CLASS_HAS_THEME, CLASS_HAS_FRONTEND, CLASS_HAS_SECURITY_SENSITIVE

resolve_bin() {
  local bin_name="$1"
  local local_bin="${PROJECT_ROOT}/vendor/bin/${bin_name}"
  local stack_bin="${STACK_ROOT}/vendor/bin/${bin_name}"

  if [[ -x "${local_bin}" ]]; then
    printf '%s\n' "${local_bin}"
    return 0
  fi

  if [[ -x "${stack_bin}" ]]; then
    printf '%s\n' "${stack_bin}"
    return 0
  fi

  if command_exists "${bin_name}"; then
    command -v "${bin_name}"
    return 0
  fi

  return 1
}

wp_target_dirs() {
  local dirs=()

  [[ -d "${PROJECT_ROOT}/plugins" ]] && dirs+=("${PROJECT_ROOT}/plugins")
  [[ -d "${PROJECT_ROOT}/wp-content/mu-plugins" ]] && dirs+=("${PROJECT_ROOT}/wp-content/mu-plugins")
  [[ -d "${PROJECT_ROOT}/themes" ]] && dirs+=("${PROJECT_ROOT}/themes")
  dirs+=("${PROJECT_ROOT}")

  printf '%s\n' "${dirs[@]}"
}

run_php_lint() {
  local target_dirs=()
  local _dir
  while IFS= read -r _dir; do
    [[ -z "${_dir}" ]] && continue
    target_dirs+=("${_dir}")
  done < <(wp_target_dirs)

  local count=0
  local failed=0
  local php_file

  while IFS= read -r -d '' php_file; do
    count=$((count + 1))
    if ! php -l "${php_file}" >/dev/null 2>&1; then
      failed=$((failed + 1))
    fi
  done < <(find "${target_dirs[@]}" -type f -name '*.php' -not -path '*/vendor/*' -not -path '*/node_modules/*' -print0 2>/dev/null)

  if [[ ${count} -eq 0 ]]; then
    report_add_tool_row "PHP lint" "Ran" "Pass (with warnings)" "${PROJECT_ROOT}" "No PHP files detected in target dirs"
    return 0
  fi

  if [[ ${failed} -eq 0 ]]; then
    report_add_tool_row "PHP lint" "Ran" "Pass" "${PROJECT_ROOT}" "${count} files linted"
    return 0
  fi

  report_add_tool_row "PHP lint" "Ran" "Fail" "${PROJECT_ROOT}" "${failed}/${count} files failed lint"
  return 1
}

run_phpcs() {
  local phpcs_bin=""
  if ! phpcs_bin="$(resolve_bin phpcs)"; then
    report_add_tool_row "PHPCS" "Skipped" "Fail/Blocked" "${PROJECT_ROOT}" "phpcs not found"
    return 1
  fi

  local standard="${PROJECT_ROOT}/phpcs.xml.dist"
  local sniffs="WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput,WordPress.Security.EscapeOutput,WordPress.DB.PreparedSQL"

  local targets=()
  [[ -d "${PROJECT_ROOT}/plugins" ]] && targets+=("${PROJECT_ROOT}/plugins")
  [[ -d "${PROJECT_ROOT}/wp-content/mu-plugins" ]] && targets+=("${PROJECT_ROOT}/wp-content/mu-plugins")
  [[ -d "${PROJECT_ROOT}/themes" ]] && targets+=("${PROJECT_ROOT}/themes")

  if [[ ${#targets[@]} -eq 0 ]]; then
    targets+=("${PROJECT_ROOT}")
  fi

  local cmd=("${phpcs_bin}" --extensions=php --report=summary --sniffs="${sniffs}" --runtime-set ignore_warnings_on_exit 1)
  [[ -f "${standard}" ]] && cmd+=(--standard="${standard}")

  if "${cmd[@]}" "${targets[@]}" >/tmp/mrn-qa-phpcs.out 2>&1; then
    report_add_tool_row "PHPCS" "Ran" "Pass" "${PROJECT_ROOT}" "security sniffs passed"
    return 0
  fi

  local summary
  summary="$(tail -n 3 /tmp/mrn-qa-phpcs.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  report_add_tool_row "PHPCS" "Ran" "Fail" "${PROJECT_ROOT}" "${summary:-PHPCS failed}"
  return 1
}

run_phpstan() {
  local phpstan_bin=""
  if ! phpstan_bin="$(resolve_bin phpstan)"; then
    report_add_tool_row "PHPStan" "Skipped" "Fail/Blocked" "${PROJECT_ROOT}" "phpstan not found"
    return 1
  fi

  local config="${PROJECT_ROOT}/phpstan.neon.dist"
  local targets=()
  [[ -d "${PROJECT_ROOT}/plugins" ]] && targets+=("${PROJECT_ROOT}/plugins")
  [[ -d "${PROJECT_ROOT}/wp-content/mu-plugins" ]] && targets+=("${PROJECT_ROOT}/wp-content/mu-plugins")
  [[ -d "${PROJECT_ROOT}/themes" ]] && targets+=("${PROJECT_ROOT}/themes")
  if [[ ${#targets[@]} -eq 0 ]]; then
    targets+=("${PROJECT_ROOT}")
  fi

  local cmd=(php "${phpstan_bin}" analyse --memory-limit=2G --no-progress)
  [[ -f "${config}" ]] && cmd+=(--configuration="${config}")

  if "${cmd[@]}" "${targets[@]}" >/tmp/mrn-qa-phpstan.out 2>&1; then
    report_add_tool_row "PHPStan" "Ran" "Pass" "${PROJECT_ROOT}" "static analysis passed"
    return 0
  fi

  local summary
  summary="$(tail -n 8 /tmp/mrn-qa-phpstan.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  report_add_tool_row "PHPStan" "Ran" "Fail" "${PROJECT_ROOT}" "${summary:-PHPStan failed}"
  return 1
}

run_semgrep() {
  local config="${PROJECT_ROOT}/semgrep/security-audit.yml"
  if [[ ! -f "${config}" ]]; then
    report_add_tool_row "Semgrep" "Skipped" "Pass (with warnings)" "${PROJECT_ROOT}" "semgrep config not found"
    return 0
  fi

  if ! command_exists semgrep; then
    report_add_tool_row "Semgrep" "Skipped" "Fail/Blocked" "${PROJECT_ROOT}" "semgrep not installed"
    return 1
  fi

  local targets=()
  [[ -d "${PROJECT_ROOT}/plugins" ]] && targets+=("${PROJECT_ROOT}/plugins")
  [[ -d "${PROJECT_ROOT}/wp-content/mu-plugins" ]] && targets+=("${PROJECT_ROOT}/wp-content/mu-plugins")
  [[ -d "${PROJECT_ROOT}/themes" ]] && targets+=("${PROJECT_ROOT}/themes")
  if [[ ${#targets[@]} -eq 0 ]]; then
    targets+=("${PROJECT_ROOT}")
  fi

  local semgrep_home="${PROJECT_ROOT}/.tmp/semgrep-home"
  ensure_dir "${semgrep_home}"
  if HOME="${semgrep_home}" SEMGREP_SEND_METRICS=off semgrep scan --config "${config}" --error --no-git-ignore "${targets[@]}" >/tmp/mrn-qa-semgrep.out 2>&1; then
    report_add_tool_row "Semgrep" "Ran" "Pass" "${PROJECT_ROOT}" "no blocking findings"
    return 0
  fi

  local summary
  summary="$(tail -n 12 /tmp/mrn-qa-semgrep.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  report_add_tool_row "Semgrep" "Ran" "Fail" "${PROJECT_ROOT}" "${summary:-Semgrep findings present}"
  return 1
}

run_git_diff_check() {
  if git -C "${PROJECT_ROOT}" diff --check >/tmp/mrn-qa-diffcheck.out 2>&1; then
    report_add_tool_row "git diff --check" "Ran" "Pass" "${PROJECT_ROOT}" "no whitespace errors"
    return 0
  fi

  local summary
  summary="$(cat /tmp/mrn-qa-diffcheck.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  report_add_tool_row "git diff --check" "Ran" "Fail" "${PROJECT_ROOT}" "${summary:-whitespace issues found}"
  return 1
}

run_qa_theme_script() {
  local script_path="${STACK_ROOT}/stack/scripts/qa-theme.sh"
  if [[ "${CLASS_HAS_THEME}" != "1" ]]; then
    report_add_tool_row "qa-theme.sh" "Skipped" "Pass (with warnings)" "${script_path}" "skipped-by-scope"
    return 0
  fi

  if [[ ! -x "${script_path}" ]]; then
    report_add_tool_row "qa-theme.sh" "Skipped" "Fail/Blocked" "${script_path}" "script missing"
    return 1
  fi

  if "${script_path}" >/tmp/mrn-qa-theme.out 2>&1; then
    report_add_tool_row "qa-theme.sh" "Ran" "Pass" "${script_path}" "theme QA passed"
    return 0
  fi

  report_add_tool_row "qa-theme.sh" "Ran" "Fail" "${script_path}" "$(tail -n 6 /tmp/mrn-qa-theme.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  return 1
}

run_qa_security_script() {
  local script_path="${STACK_ROOT}/stack/scripts/qa-security.sh"
  if [[ "${CLASS_HAS_SECURITY_SENSITIVE}" != "1" ]]; then
    report_add_tool_row "qa-security.sh" "Skipped" "Pass (with warnings)" "${script_path}" "skipped-by-scope"
    return 0
  fi

  if [[ ! -x "${script_path}" ]]; then
    report_add_tool_row "qa-security.sh" "Skipped" "Fail/Blocked" "${script_path}" "script missing"
    return 1
  fi

  if "${script_path}" >/tmp/mrn-qa-security.out 2>&1; then
    report_add_tool_row "qa-security.sh" "Ran" "Pass" "${script_path}" "security QA passed"
    return 0
  fi

  report_add_tool_row "qa-security.sh" "Ran" "Fail" "${script_path}" "$(tail -n 8 /tmp/mrn-qa-security.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  return 1
}

run_qa_playwright_script() {
  local tool_name="qa-playwright-local-stack-site.sh"
  local stack_script_path="${STACK_ROOT}/stack/scripts/qa-playwright-local-stack-site.sh"
  local engine_script_path="${ENGINE_ROOT}/tools/run-playwright-smoke.sh"
  local provider="${MRN_QA_PLAYWRIGHT_PROVIDER:-engine}"

  if [[ "${MRN_QA_RUN_SMOKE}" != "always" && "${MRN_QA_RUN_SMOKE}" != "1" && "${MRN_QA_RUN_SMOKE}" != "true" ]]; then
    report_add_tool_row "${tool_name}" "Skipped" "Pass (with warnings)" "${engine_script_path}" "smoke policy set to ${MRN_QA_RUN_SMOKE}"
    return 0
  fi

  if [[ -z "${SITE_URL}" ]]; then
    report_add_tool_row "${tool_name}" "Skipped" "Fail/Blocked" "${engine_script_path}" "SITE_URL missing"
    return 1
  fi

  local smoke_scope="${MRN_SMOKE_SCOPE:-public}"
  local runner_path=""
  local run_label=""

  if [[ "${provider}" == "engine" ]]; then
    runner_path="${engine_script_path}"
    run_label="engine"
  elif [[ "${provider}" == "stack" ]]; then
    runner_path="${stack_script_path}"
    run_label="stack"
  else
    if [[ -x "${engine_script_path}" ]]; then
      runner_path="${engine_script_path}"
      run_label="engine(auto)"
    elif [[ -x "${stack_script_path}" ]]; then
      runner_path="${stack_script_path}"
      run_label="stack(auto)"
    else
      report_add_tool_row "${tool_name}" "Skipped" "Fail/Blocked" "${engine_script_path}" "No Playwright runner available (engine/stack missing)"
      return 1
    fi
  fi

  if [[ ! -x "${runner_path}" ]]; then
    report_add_tool_row "${tool_name}" "Skipped" "Fail/Blocked" "${runner_path}" "runner script missing"
    return 1
  fi

  local exit_code=0
  set +e
  if [[ "${runner_path}" == "${engine_script_path}" ]]; then
    MRN_QA_SMOKE_SCOPE="${smoke_scope}" \
      MRN_QA_SAMPLE_PATH="${MRN_QA_SAMPLE_PATH:-/sample-page/}" \
      MRN_QA_PLAYWRIGHT_IGNORE_REGEX="${MRN_QA_PLAYWRIGHT_IGNORE_REGEX:-}" \
      "${runner_path}" "${SITE_URL}" >/tmp/mrn-qa-playwright.out 2>&1
    exit_code=$?
  else
    if [[ -z "${SITE_PATH}" || ! -d "${SITE_PATH}" ]]; then
      set -e
      report_add_tool_row "${tool_name}" "Skipped" "Fail/Blocked" "${runner_path}" "SITE_PATH missing for stack runner"
      return 1
    fi
    MRN_SMOKE_SCOPE="${smoke_scope}" "${runner_path}" "${SITE_PATH}" >/tmp/mrn-qa-playwright.out 2>&1
    exit_code=$?
  fi
  set -e

  if [[ ${exit_code} -eq 0 ]]; then
    report_add_tool_row "${tool_name}" "Ran" "Pass" "${runner_path}" "${run_label}, scope=${smoke_scope}"
    return 0
  fi

  if [[ "${MRN_SMOKE_STRICT}" == "1" ]]; then
    report_add_tool_row "${tool_name}" "Ran" "Fail" "${runner_path}" "$(tail -n 8 /tmp/mrn-qa-playwright.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
    return 1
  fi

  report_add_tool_row "${tool_name}" "Ran" "Pass (with warnings)" "${runner_path}" "non-strict ${run_label} warning: $(tail -n 4 /tmp/mrn-qa-playwright.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  return 0
}

run_qa_pagespeed_script() {
  local script_path="${STACK_ROOT}/stack/scripts/qa-page-speed.sh"

  if [[ "${CLASS_HAS_FRONTEND}" != "1" ]]; then
    report_add_tool_row "qa-page-speed.sh" "Skipped" "Pass (with warnings)" "${script_path}" "skipped-by-scope"
    return 0
  fi

  if [[ ! -x "${script_path}" ]]; then
    report_add_tool_row "qa-page-speed.sh" "Skipped" "Fail/Blocked" "${script_path}" "script missing"
    return 1
  fi

  if [[ -z "${SITE_URL}" ]]; then
    report_add_tool_row "qa-page-speed.sh" "Skipped" "Fail/Blocked" "${script_path}" "SITE_URL missing"
    return 1
  fi

  if "${script_path}" "${SITE_URL}" / /sample-page/ >/tmp/mrn-qa-pagespeed.out 2>&1; then
    report_add_tool_row "qa-page-speed.sh" "Ran" "Pass" "${script_path}" "page-speed checks passed"
    return 0
  fi

  report_add_tool_row "qa-page-speed.sh" "Ran" "Fail" "${script_path}" "$(tail -n 8 /tmp/mrn-qa-pagespeed.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  return 1
}

run_qa_rollout_contract_script() {
  local script_path="${STACK_ROOT}/stack/scripts/qa-rollout-contract.sh"

  if [[ "${MRN_QA_SCOPE}" != "site+stack" ]]; then
    report_add_tool_row "qa-rollout-contract.sh" "Skipped" "Pass (with warnings)" "${script_path}" "skipped-by-scope"
    return 0
  fi

  if [[ ! -x "${script_path}" ]]; then
    report_add_tool_row "qa-rollout-contract.sh" "Skipped" "Fail/Blocked" "${script_path}" "script missing"
    return 1
  fi

  if "${script_path}" >/tmp/mrn-qa-rollout.out 2>&1; then
    report_add_tool_row "qa-rollout-contract.sh" "Ran" "Pass" "${script_path}" "rollout contract passed"
    return 0
  fi

  report_add_tool_row "qa-rollout-contract.sh" "Ran" "Fail" "${script_path}" "$(tail -n 8 /tmp/mrn-qa-rollout.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  return 1
}

run_qa_local_stack_site_script() {
  local script_path="${STACK_ROOT}/stack/scripts/qa-local-stack-site.sh"
  if [[ ! -x "${script_path}" ]]; then
    report_add_tool_row "qa-local-stack-site.sh" "Skipped" "Pass (with warnings)" "${script_path}" "not available"
    return 0
  fi

  if [[ "${MRN_QA_SCOPE}" != "site+stack" ]]; then
    report_add_tool_row "qa-local-stack-site.sh" "Skipped" "Pass (with warnings)" "${script_path}" "scope does not require"
    return 0
  fi

  if "${script_path}" >/tmp/mrn-qa-localstack.out 2>&1; then
    report_add_tool_row "qa-local-stack-site.sh" "Ran" "Pass" "${script_path}" "passed"
    return 0
  fi

  report_add_tool_row "qa-local-stack-site.sh" "Ran" "Fail" "${script_path}" "$(tail -n 8 /tmp/mrn-qa-localstack.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  return 1
}

run_audit_config_helper_parity_script() {
  local script_path="${STACK_ROOT}/stack/scripts/audit-config-helper-parity.sh"

  if [[ ! -x "${script_path}" ]]; then
    report_add_tool_row "audit-config-helper-parity.sh" "Skipped" "Fail/Blocked" "${script_path}" "required script missing"
    return 1
  fi

  if "${script_path}" >/tmp/mrn-qa-parity.out 2>&1; then
    report_add_tool_row "audit-config-helper-parity.sh" "Ran" "Pass" "${script_path}" "parity OK"
    return 0
  fi

  report_add_tool_row "audit-config-helper-parity.sh" "Ran" "Fail" "${script_path}" "$(tail -n 8 /tmp/mrn-qa-parity.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  return 1
}

run_npm_audit_row() {
  if [[ ! -f "${PROJECT_ROOT}/package-lock.json" ]]; then
    report_add_tool_row "npm audit" "Skipped" "Pass (with warnings)" "${PROJECT_ROOT}" "package-lock.json missing"
    return 0
  fi

  if ! command_exists npm; then
    report_add_tool_row "npm audit" "Skipped" "Fail/Blocked" "${PROJECT_ROOT}" "npm missing"
    return 1
  fi

  if (cd "${PROJECT_ROOT}" && npm audit --omit=dev --omit=optional --audit-level=high >/tmp/mrn-qa-npm-audit.out 2>&1); then
    report_add_tool_row "npm audit" "Ran" "Pass" "${PROJECT_ROOT}" "no high vulnerabilities"
    return 0
  fi

  report_add_tool_row "npm audit" "Ran" "Fail" "${PROJECT_ROOT}" "$(tail -n 8 /tmp/mrn-qa-npm-audit.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  return 1
}

run_composer_audit_row() {
  if [[ ! -f "${PROJECT_ROOT}/composer.lock" ]]; then
    report_add_tool_row "Composer audit" "Skipped" "Pass (with warnings)" "${PROJECT_ROOT}" "composer.lock missing"
    return 0
  fi

  if ! command_exists composer; then
    report_add_tool_row "Composer audit" "Skipped" "Fail/Blocked" "${PROJECT_ROOT}" "composer missing"
    return 1
  fi

  if composer audit --locked --working-dir="${PROJECT_ROOT}" >/tmp/mrn-qa-composer-audit.out 2>&1; then
    report_add_tool_row "Composer audit" "Ran" "Pass" "${PROJECT_ROOT}" "no known advisories"
    return 0
  fi

  report_add_tool_row "Composer audit" "Ran" "Fail" "${PROJECT_ROOT}" "$(tail -n 8 /tmp/mrn-qa-composer-audit.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  return 1
}

run_eslint_row() {
  if [[ ! -f "${PROJECT_ROOT}/package.json" ]]; then
    report_add_tool_row "ESLint" "Skipped" "Pass (with warnings)" "${PROJECT_ROOT}" "package.json missing"
    return 0
  fi

  if ! command_exists npm; then
    report_add_tool_row "ESLint" "Skipped" "Fail/Blocked" "${PROJECT_ROOT}" "npm missing"
    return 1
  fi

  if [[ ! -d "${PROJECT_ROOT}/node_modules" ]]; then
    report_add_tool_row "ESLint" "Skipped" "Pass (with warnings)" "${PROJECT_ROOT}" "node_modules missing"
    return 0
  fi

  if (cd "${PROJECT_ROOT}" && npm run --silent lint:js >/tmp/mrn-qa-eslint.out 2>&1); then
    report_add_tool_row "ESLint" "Ran" "Pass" "${PROJECT_ROOT}" "JS lint passed"
    return 0
  fi

  report_add_tool_row "ESLint" "Ran" "Fail" "${PROJECT_ROOT}" "$(tail -n 8 /tmp/mrn-qa-eslint.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  return 1
}

run_stylelint_row() {
  if [[ ! -f "${PROJECT_ROOT}/package.json" ]]; then
    report_add_tool_row "Stylelint" "Skipped" "Pass (with warnings)" "${PROJECT_ROOT}" "package.json missing"
    return 0
  fi

  if ! command_exists npm; then
    report_add_tool_row "Stylelint" "Skipped" "Fail/Blocked" "${PROJECT_ROOT}" "npm missing"
    return 1
  fi

  if [[ ! -d "${PROJECT_ROOT}/node_modules" ]]; then
    report_add_tool_row "Stylelint" "Skipped" "Pass (with warnings)" "${PROJECT_ROOT}" "node_modules missing"
    return 0
  fi

  if (cd "${PROJECT_ROOT}" && npm run --silent lint:css >/tmp/mrn-qa-stylelint.out 2>&1); then
    report_add_tool_row "Stylelint" "Ran" "Pass" "${PROJECT_ROOT}" "CSS lint passed"
    return 0
  fi

  report_add_tool_row "Stylelint" "Ran" "Fail" "${PROJECT_ROOT}" "$(tail -n 8 /tmp/mrn-qa-stylelint.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  return 1
}

run_phpunit_row() {
  local phpunit_bin="${PROJECT_ROOT}/vendor/bin/phpunit"
  local phpunit_config="${PROJECT_ROOT}/phpunit.xml.dist"

  if [[ ! -x "${phpunit_bin}" || ! -f "${phpunit_config}" ]]; then
    report_add_tool_row "PHPUnit" "Skipped" "Pass (with warnings)" "${PROJECT_ROOT}" "phpunit or config missing"
    return 0
  fi

  if "${phpunit_bin}" --configuration "${phpunit_config}" >/tmp/mrn-qa-phpunit.out 2>&1; then
    report_add_tool_row "PHPUnit" "Ran" "Pass" "${PROJECT_ROOT}" "tests passed"
    return 0
  fi

  report_add_tool_row "PHPUnit" "Ran" "Fail" "${PROJECT_ROOT}" "$(tail -n 8 /tmp/mrn-qa-phpunit.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g')"
  return 1
}

run_wordpress_checks() {
  local failure_count=0

  run_php_lint || failure_count=$((failure_count + 1))
  run_phpcs || failure_count=$((failure_count + 1))
  run_phpstan || failure_count=$((failure_count + 1))
  run_semgrep || failure_count=$((failure_count + 1))
  run_git_diff_check || failure_count=$((failure_count + 1))
  run_audit_config_helper_parity_script || failure_count=$((failure_count + 1))
  run_qa_theme_script || failure_count=$((failure_count + 1))
  run_qa_security_script || failure_count=$((failure_count + 1))
  run_qa_playwright_script || failure_count=$((failure_count + 1))
  run_qa_pagespeed_script || failure_count=$((failure_count + 1))
  run_qa_rollout_contract_script || failure_count=$((failure_count + 1))
  run_qa_local_stack_site_script || failure_count=$((failure_count + 1))

  run_eslint_row || failure_count=$((failure_count + 1))
  run_stylelint_row || failure_count=$((failure_count + 1))
  run_composer_audit_row || failure_count=$((failure_count + 1))
  run_npm_audit_row || failure_count=$((failure_count + 1))
  run_phpunit_row || failure_count=$((failure_count + 1))

  return ${failure_count}
}
