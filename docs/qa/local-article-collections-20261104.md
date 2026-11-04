# Optional native article collections — local migration checkpoint

Content Lists now has an opt-in Articles presentation for published WordPress
posts. It retains existing collection size/order/offset controls and adds optional
row-scoped category buttons, explicit category order/exclusions/empty terms,
incremental batches, sticky-post behavior, reading time, date format, heading-free
generated excerpts and card heading level. Other list presentations retain their
existing queries and output. No article copy or site IDs are embedded in Stack.

The optional editorial frame uses validated native dimensions, Site Colors and
font roles. Separate desktop/tablet/mobile grid, gutters, type and card spacing
are editable. Outer padding/margins consume the existing native row presets,
including explicit 767px row-spacing and optional 900px padding-only switches.
The frame removes repeated inner section padding without child CSS overrides.

All queried cards remain available when JavaScript is unavailable. Enhanced
controls are native grouped toggle buttons, with pressed state, empty results,
polite status announcements and keyboard focus recovery when Load More is
exhausted. Each collection is independent. There are no new endpoints, network
fetches or cosmetic timers. CSS is conditional on article usage; the small script
is conditional on interactive article usage. Native responsive attachments and
missing-image placeholders remain supported. Password-protected article bodies
and reading-time data are not exposed. Output uses existing escaped URLs, text,
attributes and validated frame helpers; normal ACF capability/nonce/tree guards
continue to own editor writes.

Source qualification: 43 article assertions on PHP 8.2 and 8.5; 28 Stack PHP
suites per version (56 runs); configured full-theme PHPStan reports zero errors.
The site adds 32 installed-schema converter/refusal assertions, 39 browser
interaction assertions, and 52 responsive comparisons across 13 widths. Complete
card wording, links, image selections/alt, dates/read times, category order and
excerpts match the reviewed source. The separate mobile preset correction and
semantic H2 selection are recorded in site evidence; neither changes card styling.

This is a local code checkpoint. It does not clear authored-page presentation or
performance holds, update a release lock/version, publish Fleet packages, or deploy
remotely. Exact runtime/editor evidence and outstanding findings are recorded in
the site branch under docs/stack-migration-2026-11-04/article-feeds/.
