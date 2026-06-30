# AGENTS.md - MRN Admin UI CSS

## Purpose
This directory contains the MRN Admin UI CSS WordPress plugin shared MU plugin release unit.

## Rules
- Keep changes scoped to this plugin release unit.
- Preserve WordPress security controls for admin actions, REST/AJAX/admin-post handlers, nonces, capabilities, sanitization, and escaping.
- Treat `MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-admin-ui-css` as the canonical plugin readiness signal.

## Safety
- Never auto-deploy from this plugin directory.
- Require explicit release references for promotion.
- Run separate runtime QA against a named site when validating front-end, admin, API, accessibility, or performance behavior.
