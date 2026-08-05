# Stack Baseline - MRN Public Security Hardening

## Baseline Snapshot
- Date pinned: 2026-06-30
- Plugin source path: `/Users/khofmeyer/Development/MRN/mu-plugins/mrn-public-security-hardening`
- Intended integration target: MRN shared MU plugin loader and normal MRN brochure/client sites
- Current release model: shared MU plugin release unit

## Why This File Exists
This directory follows MRN QA Engine discovery standards so the security plugin can be checked independently from unrelated stack work.

## Update Process
1. Update the MU plugin source and README together.
2. Run `mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-public-security-hardening`.
3. Run a separate stack QA pass when changing `stack/mu-plugins/mrn-loader.php`.
4. Run explicit runtime smoke against the target site when validating `/.well-known/security.txt`, author redirects, oEmbed, or guarded REST HTTP responses.
5. Update `stack.lock` when the baseline metadata changes.
