# AGENTS.md - Archived MRN Admin UI CSS Legacy

## Purpose
This directory preserves the retired MRN Admin UI CSS Legacy source for historical reference. It is not a supported release unit and must not be restored to runtime packaging without a separately approved compatibility review.

## Rules
- Keep changes scoped to this plugin release unit.
- Preserve WordPress security controls for admin actions, REST/AJAX/admin-post handlers, nonces, capabilities, sanitization, and escaping.
- Do not package, deploy, or reactivate this archived component.

## Safety
- Never auto-deploy from this plugin directory.
- Require explicit release references for promotion.
- Run separate runtime QA against a named site when validating front-end, admin, API, accessibility, or performance behavior.
