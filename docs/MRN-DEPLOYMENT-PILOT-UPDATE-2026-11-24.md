# MRN deployment update — SiteGround staging qualification

The isolated `staging2.mrnwebdesigns.com` pilot passed verified remote database
backup, immutable activation/interruption recovery, proof-only retry after a
controlled 429, tracked media/content migration, completed replay, exact-path
cache refresh and code rollback before content writes. Final QA Engine proof
passed 12 first/warm page/device samples, including six required-image checks.
Live code/content was unchanged. This is provider/adapter qualification, not a
full-site accessibility or Core Web Vitals audit, and it does not enroll Live.

The shared changes remove site duplication discovered by that test:

- The generated asset helper keeps manifest-owned child CSS/JS out of
  SiteGround runtime minify/combine rewriting. Other assets and native HTML-cache
  APIs retain their behavior.
- Nested shipped vendor assets stay in the immutable bundle. The builder
  validates the complete candidate before exclusive publication; invalid or
  competing outputs cannot replace an existing artifact.
- QA Engine accepts WordPress stylesheet version queries while verifying exact
  origin/path and fetched bytes. Optional tracked resources bind immutable media
  or qualified provider derivatives to required page URLs. Missing references,
  changed bytes, stale device HTML and required runtime errors remain failures.

## Adoption

1. Pin the released shared tooling and its tested QA Engine revision in the
   site's existing source/workflow route. A PR remains optional; Git tags,
   reviewed non-Git snapshots and explicit emergency Live hotfixes retain their
   existing gates.
2. Provision the protected provider adapter, persistent private journal and
   receipt key on a durable runner. Keep these outside artifacts and ephemeral
   GitHub-hosted jobs. Qualify exact target identity, provider backup, all active
   cache layers and rollback for each environment independently.
3. Include the generated immutable asset helper once. Package declared scripts
   and media through `scripts/content/migrations.json`. Derive QA Engine resource
   expectations from the verified migration and attachment/provider receipts;
   retain original assets and record native optimized delivery separately.
4. Qualify a harmless fixture, proof-only retry, completed content replay,
   scoped refresh/verification and rollback before content writes. Retain the
   signed terminal receipt before marking the environment deployable.

Sites retain their target/source authorization, credentials, content intent,
WordPress/ACF lookups, migration environment allowlists, affected paths and
visual/interaction/accessibility/performance QA plans. Shared tooling owns the
phase gates, immutable artifact validation, media limits, locks, backup and
receipt contract, replay protection and QA Engine orchestration. Outside
developers submit exact source to that workflow; they do not need local QA
Engine installations.

WP Engine, SiteGround and Kinsta remain the supported enrollment providers.
Kinsta's approved native transaction-backup and oldest-eligible tracked-snapshot
rotation rules are unchanged. Shared source publication never installs a client
release, clears a hold or promotes staging to production.
