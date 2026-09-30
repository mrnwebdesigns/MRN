# Config Helper 0.1.68 selective release

This registration makes the merged, opt-in SEOPress breadcrumb provider available
through the existing one-site Fleet update workflow. It changes only the Config
Helper catalog target and adds its immutable artifact record. The complete Stack
baseline remains `2026.09.29-updraft-retention`; no full-platform lock or other
component is advanced by this registration.

## Exact source and artifacts

- Source: `mrnwebdesigns/mrn-config-helper`, merged main
  `074e07ddf638bca15bd66cae311ffba69ff66745` (PRs 9 and 10).
- Version: `0.1.68`, 25 deployable files.
- Package: `releases/stack-plugins/mrn-config-helper-0.1.68.zip`.
- Package SHA-256: `a43c8bf7228bb357cf18fb910ff8b87521e0e76873256630be2d8bc12fe5bc59`.
- Tree SHA-256: `23b7941d6d57a4a8441a6d24c05ae47c432f0aaadcf6a7dc612c74499f8db929`.
- Gloves rollback: existing registered `0.1.66`, package SHA-256
  `7073f358b13d2a83e8350e586e532d3370d3914149f17059029c4cefa85c93df`,
  tree SHA-256 `1c5882823dfddcbe24b3a018ca95c726602e557ab15f79d136e276071dc74dd8`.

The ZIP is built from the exact committed Git export using the existing
`build-mainwp-stack-release.py` deterministic ZIP function and the selective
plan's deployable-file rules. Rebuilding produced identical bytes. The selective
plan builder verified both packages against their exact source commits, versions,
file counts and tree hashes. Artifacts are retained outside tracked source under
`releases/stack-plugins/`; the registry binds their bytes.

## Behavior and adoption boundary

Existing sites remain on their saved provider, which defaults to MRN. Installing
0.1.68 does not enable SEOPress or change site options. Shared data/render/shortcode
APIs can subsequently use SEOPress while retaining signed WooCommerce category
navigation, native labels, menu hierarchy, current-page semantics and a legacy
fallback. Both native breadcrumb options must be configured before opting in.

The Gloves child-theme pilot remains in place for the initial plugin update.
Provider selection and retirement of the duplicate site adapter are a subsequent
reviewed migration with their own option/source checks and rollback. Other sites,
tracking, consent, ecommerce events, orders, and legacy metadata are outside this
selective update.

## Qualification and operation

Use full Config Helper release QA, standalone regressions, and its read-only
`tests/runtime/seopress-provider.php` against the named site runtime. On September
30, the live Gloves SEOPress 10.2 / PRO 10.2.1 breadcrumb implementation and option
service matched the local qualification files by SHA-256. That proves parity with
the installed live build; it does not certify other or future vendor versions.

Plan with the canonical `mrn fleet update` workflow for exactly
`https://gloves-online.com` and `mrn-config-helper`. Review the current site baseline,
forward/rollback versions and precondition hash. Execute only through the workflow's
authorization, fresh remote database-only backup and exact runtime readback gates.
The site's older full Stack lock stays intact; the supported selective overlay
records the exact plugin replacement.

Full Stack promotion still has separate deployment-contract release debt recorded
by `qa-stack-promotion.py --mode audit`. This component registration does not
declare that full-platform promotion complete or authorize a fleet-wide rollout.

Site evidence and the running migration record are retained under the Gloves
`reports/seopress-migration-2026-09-30/fleet-provider/` directory and its parent
`RUNBOOK.md`. A source merge or successful preflight is not a deployment receipt.
