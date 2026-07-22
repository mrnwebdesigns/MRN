# AGENTS.md - MRN Mega Menu

## Purpose

This directory contains the MRN Mega Menu WordPress plugin release unit.

## Rules

- Keep changes scoped to this plugin release unit.
- Preserve WordPress security controls for admin actions, nonces, capabilities, sanitization, and escaping.
- Preserve keyboard, focus, semantic markup, labels, and WCAG 2.1 AA behavior in all admin and storefront UI.
- WooCommerce is an optional integration. The plugin must fail gracefully when WooCommerce is inactive.
- Treat `MRN_QA_CODE_ANALYSIS_SCOPE=all mrn-qa run --project-root /Users/khofmeyer/Development/MRN/plugins/mrn-mega-menu` as the canonical plugin readiness signal.

## Safety

- Never auto-deploy from this plugin directory.
- Require an explicit release request before building or publishing a release artifact.
- Run separate runtime QA against a named site when validating storefront, WooCommerce, admin, accessibility, or performance behavior.
