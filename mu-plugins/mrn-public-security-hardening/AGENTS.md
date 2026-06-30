# AGENTS.md - MRN Public Security Hardening

## Purpose
This directory contains the shared MRN Public Security Hardening MU plugin release unit.

## Rules
- Keep the plugin safe for WordPress must-use plugin loading.
- Keep all public hardening behavior filterable per site.
- Preserve REST guard behavior for unauthenticated scanner-noise write requests before required-parameter validation runs.
- Preserve administrator pass-through for users with `manage_options` or `manage_network_options`.
- Do not add client-specific domains, email addresses, URLs, secrets, or policy text to shared source.
- Treat `mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-public-security-hardening` as the canonical plugin readiness signal.

## Safety
- Never auto-deploy from this directory.
- Coordinate stack loader changes separately from plugin source changes.
- Runtime acceptance should be run against an explicit target site when validating live HTTP behavior.
