# Paid Landing Page SEO policy

Source worktree: `codex/landing-page-paid-seo-20261106`, based on merged main `91b68154`. The owner explicitly requested Stack and site implementation updates and had already authorized the Landing Page CPT Stack work in this chat.

## Behavior

- Native SEOPress noindex defaults apply to all Landing Page singles, optional archives and dedicated category/tag archives. No robots.txt disallow is added: advertising crawlers must reach their destination, and search crawlers must read noindex.
- A WordPress robots filter and HTTP noindex header protect requests when SEOPress is unavailable. Local/staging robots restrictions remain intact.
- SEOPress XML/HTML sitemaps and WordPress core sitemaps omit this CPT and its taxonomies. Explicit HTML sitemap shortcodes also omit paid URLs.
- Default WordPress site search and the navigation chooser exclude Landing Pages. Existing third-party search engine configurations should be checked because an explicit source selection can override the CPT flag.
- Native SEOPress automatic IndexNow submission skips paid-page status transitions; ordinary content and normal SEO editor/settings discovery retain their existing behavior.
- Archive exposure, Content Only/Disabled modes, record retention, REST access and builder wiring remain separate controls. Enabling an archive does not make it indexable.
- Theme version is 1.6.14. No new frontend scripts, styles, tracking tags or content defaults are introduced.

## Executed qualification

On the isolated `mrnwebdesigns-landing` local clone with the legacy parent and native SEOPress 10.4:

- Existing native CPT fixture passed public/no-archive, public/archive, Content Only, Disabled and restored states, including taxonomy, REST, retention and builder checks.
- New integration fixture passed default noindex on a newly created paid page without per-page metadata, search exclusion, XML/explicit HTML/core sitemap exclusion and retained SEOPress editor discovery.
- Native automatic-indexing callback was exercised with synthetic settings and all HTTP intercepted. Paid-page transitions produced zero indexing requests; an ordinary page control reached the intercepted IndexNow transport. Temporary fixture records were deleted.
- The site migration passed initial application and repeat execution with no further default changes. It writes only native SEOPress Landing Page options and dedicated post/term noindex metadata; page publication status stays unchanged.
- Site runtime accessibility and timing checks passed. General local browser smoke retains its existing non-strict warning from intentionally blocked tracking/worker operations. Stack task static QA passed with an advisory missing WP-CLI stub path in the isolated worktree; that is not a full PHPStan or Fleet signoff.

This is task acceptance evidence, not Fleet readiness. Accepted main is reconciled by the configured Fleet release coordinator; full source/package/publication qualification remains a separate gate. SiteGround adoption is separately authorized, backed up and verified in the site repository's deployment record. The migration clone is not a qualification target and was not written.
