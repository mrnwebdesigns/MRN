# Local labeled Logos and semantic quotations — October 22

The existing migration task adds optional Labeled Logos to the parent builder,
preserving ordered captions beside image references, rich descriptions and
native responsive frame/spacing values. Standard grid and slider output remains
the default. Intrinsic image widths are reserved before lazy loading; labeled
mode excludes slider assets even with a dormant slider choice.

Body Text adds opt-in blockquote semantics, an optional decorative opening mark,
text weight and bounded responsive size/line-height controls. Adjacent native
Split/Body Text stores inline attribution. Unselected rows retain their output;
no site copy, custom API route or new JavaScript dependency is introduced.
All outer spacing remains in native row presets; no child CSS compensates for
missing controls.

The site converter 20261022.3 qualifies five populated logo rows/eight ordered
images and four populated testimonials without adopting dedicated Case Study
fields. Owner-approved source cleanup removes Abilitations editor wrappers and
seven empty rows on four other case studies; media remains retained. Those are
site migration scripts, separate from the reusable parent capabilities.

Source checks: **687 native runtime assertions**, **252 saved-logo + 111
saved-testimonial checks**, 233 Overview regressions and 32 converter groups /
49 removed fixtures pass. At 1280/1024/900px the compiled NAA preview matches
complete row heights, content widths, effective typography and gaps. Native
side-padding presets improve mobile content widths; mobile geometry intentionally
differs. Six measured widths have no horizontal overflow.

Preview:
https://mrnwebdesigns-stack-migration.localhost/stack-case-inline-preview-20261022/naa.html

The preview replaces the parent stylesheet in its original position so the
child's fonts/settings retain their normal precedence. It replaces only the two
specified NAA rows; other rows remain legacy. Actual authored NAA and the managed
parent are unchanged. CLI qualification uses the guarded private candidate.

Standard Stack and site MRN QA both pass with advisories (191.9s/97.2s).
Code/security/compatibility, REST runtime, public browser, accessibility and timing
coverage run. PHPStan retains 14 Stack/199 site findings; Config Helper remote
parity has no discovered roots and a standalone rollout fallback is unavailable.
Preview CLS varies from 0.00210 in the Stack run to 0.12456 in the site run, so
full-page layout shift remains open. Site Semgrep/API surface scanning is skipped
where unconfigured or without new handlers. Autofix is disabled. No release or
complete-site performance clearance follows from these standard runs. Full-page/editor and managed-adoption acceptance
remain open. Source is local and uncommitted in this task worktree; no publication,
Fleet promotion, managed install, authored native conversion or remote write was
performed. The site's current inventory is 290 rows: 174 implemented mappings,
52 presentation reviews, 60 converter-work, three native reconciliations and one
retained pilot source. Only the existing pilot record is converted.
