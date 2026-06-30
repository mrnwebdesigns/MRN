# AGENTS.md - MRN Schema Bridge

## Purpose

This directory contains the shared MRN Schema Bridge MU plugin release unit.

## Rules

- Keep the plugin safe for WordPress must-use plugin loading.
- Keep all schema behavior filterable per site.
- Do not add client-specific domains, email addresses, URLs, secrets, or policy text to shared source.
- Keep package identity MRN-prefixed in source/docs when needed, but do not use `MRN` in visible wp-admin menu labels, page titles, buttons, or admin help copy.
- Treat third-party SEO/schema plugins as integrations, not as part of the plugin brand.
- Treat `mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-schema-bridge` as the canonical plugin readiness signal.

## Safety

- Never auto-deploy from this directory.
- Coordinate stack loader changes separately from plugin source changes.
- Runtime acceptance should be run against an explicit target site when validating live structured data.
