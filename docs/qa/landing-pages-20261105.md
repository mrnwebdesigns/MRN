# Landing Page CPT task qualification

Scope: native Landing Page CPT, its own categories/tags, shared builder wiring,
shared Content Only/Disabled behavior and an independent public-archive switch.
Source baseline: MRN `9838e45b99ed8d7b6e918f8dc9fed811d1ec0d5c`.

## Verified

- Native WordPress integration suite passes public/no-archive, public/archive,
  Content Only, Disabled and restored states. Record IDs, term relationships and
  explicit component queries survive every state; REST/UI flags follow the
  shared contract. Hero, Content and After Content include `landing_page`.
- Seventy real HTTP assertions pass across two disposable local runtimes:
  canonical Stack and the older SiteGround-style parent/child with the site
  compatibility adapter. Coverage includes single pretty/query URLs, CPT pretty/
  query archives, taxonomy pretty/query archives and REST collections.
- Desktop/tablet/mobile browser checks pass at 1440/768/390 px: one H1/main
  landmark, no document overflow, all three builder areas, dedicated taxonomy
  editors, archive control persistence and no page JavaScript errors.
- Axe WCAG A/AA checks find zero violations in the page's main region and the
  new archive-control card in both fixtures. The fixtures use placeholder site
  chrome/content; they do not establish visual brand parity for a migrated site.
- Task-scoped MRN static QA passes. Canonical fixture MRN browser, API,
  accessibility and timing gates pass. PHPStan reported an advisory missing
  snapshot stub path on that run; lint, PHPCS/security, Semgrep and the native
  functional checks completed. No custom REST/AJAX endpoint or frontend asset was
  added.
- The hybrid MRN accessibility run passes the Landing Page route. Its additional
  `/` check fails because the disposable legacy homepage has no seeded legacy
  hero/H1. The explicit failure is retained; this is not full-site signoff.

## Boundaries

This is task qualification, not a full Stack release/Fleet promotion. The release
lock is intentionally not regenerated from a feature worktree. The source release
coordinator owns merged-source qualification and publication.

SiteGround installation requires its exact-origin identity, a fresh labeled
remote database backup, committed package provenance, installed checksum readback
and site verification. MainWP's public-domain connection currently reaches the
separate WPMUDev source and cannot identify the SiteGround copy.
