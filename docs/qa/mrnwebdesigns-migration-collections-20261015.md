# Migration collection contracts — October 15, 2026

Owner authorized shared Stack work in this site migration chat. Isolated branch:
`codex/mrnwebdesigns-migration-capabilities`, based on
`3c2ae1d92d0741559a352db978295ab4ef17ed4d`. Parent source version **1.5.20** is an
uncommitted candidate; it is not installed or published. Site work remains on
its separate `codex/full-stack-migration` branch.

## FAQ grouping verified

Page **1124**, `frequently-asked-questions`, has one Hero and **one FAQ layout**.
FAQ row index 1 contains **27 populated questions**, rendered as one accordion.
A fresh public GET of `https://mrnwebdesigns.com/frequently-asked-questions/`
independently returned **one FAQ section, one accordion list and 27 questions**.
The SEO page has another four-question FAQ. Across the inventory there are two
FAQ sets with 31 questions total and five other accordion sections containing
39 items. Those other sections contain 5, 7 or 10 items; they do not explain
the FAQ page's 27. No splitting of its single set is needed.

## Implemented candidate

| Contract | Behavior |
| --- | --- |
| FAQ/accordion | 30 questions per set, including nested/prefixed clones. |
| Logos | 30 items, covering the authored 18-logo collection. |
| Showcase/cards/grids/sliders | 12 items, covering six service items and 7–10 images. |
| Other repeaters | Default five-row ceiling and tighter existing limits remain. |
| Hero | Four links, matching its renderer. Ordinary Basic/item links retain their limits. |
| Reusable placement | Explicit root/nested width registration, preserving native spacing and source references. |
| FAQ with aside | FAQ is available in Two Column Split beside native content or CTA. |
| Jump navigation | Optional authored label/anchor pairs; unset source retains automatic FAQ discovery. Manual mode has a “Page sections” accessible label. |

Editor, REST schema and ACF API limits agree. Overflow is refused before any
parent, sibling or child metadata changes. Oversized historical collections
remain editable without growth, and reducing them removes the larger allowance.
Migration never seeds artificial row counts or bypasses validation. Only the
owned historical regression fixture temporarily removes the guard while seeding
its draft and restores the guard in `finally`.

Reusable width already had renderer/editor normalization support but was absent
from the raw registered fields used by API consumers. Explicit registration fixes
that discrepancy without running the complete editor normalizer on public requests
or copying reusable content. These collection/composition changes add no frontend dependency. The optional presentation features below add one conditionally loaded vanilla JavaScript/CSS pair.

## Qualification

Evidence is under `reports/mrnwebdesigns-migration-2026-10-15/`:

- `faq-source-audit.json` and `live-faq-structure.json`: fresh local/live counts.
- `repeater-limits.json`: 28 passing assertions, including one set of 27, 30/31
  boundaries, REST/editor agreement, historical limits, reorder, nested/group/options
  refusal and write-token expiry.
- `migration-collections.json`: 33 passing assertions covering required migration
  sizes, Hero links, split FAQ rendering/atomic refusal, reusable source isolation
  and manual/automatic navigation.
- Relevant standalone suites pass: component asset discovery (including nested
  FAQ), cloned ACF AJAX fields, After Content contracts, field finalization,
  contextual motion targets and lazy layout preparation.
- `task-qa.md`: task-scoped MRN QA, security, compatibility, API and diff checks.
- `source-readback.json`: all **63 authored records retain identical builder
  metadata** after fixture cleanup. No authored record was converted.

Candidate source was loaded through a private exact-clone CLI bootstrap, changing
`template_directory` only in that process. Installed files, options and selected
release remain unchanged. Real Classic Editor round trips and qualification against an installed candidate remain required. Browser/axe, responsive checks and timing against the native source preview are documented below; that preview does not establish installed adoption.
Tests against the old installed theme were not substituted as candidate proof.
The remote Dev fleet parity script was excluded from this local source task;
Config Helper was not changed. Promotion and installed adoption remain open.

## Eight native presentations implemented

The eight source capabilities below are now implemented. They cover 54 original
presentation rows before converter reconciliation; real content has not been
converted. Authored spacing remains in the existing Stack spacing fields.

| Requirement | Rows | Native fields and behavior |
| --- | ---: | --- |
| Full image treatment | 35 | Image Content `image_presentation`: `scroll-zoom` or `tilted-gallery`; `media_height` and `media_height_mobile` accept validated non-negative CSS lengths; `gallery_columns` stores up to five ordered columns, each with up to 30 images; optional `image_overlay`. |
| Hero rotation | 1 | `rotating_phrases_one` / `rotating_phrases_two`, 30 phrases each, `rotation_interval`; native Hero retains both authored links (four-link ceiling). |
| CTA rotation | 6 | The same optional phrase contract on native Basic/CTA and their reusable sources; use `{phrase_one}` / `{phrase_two}` in Heading. Pause/resume, offscreen/hidden-page suspension, stable accessible first phrases and reduced-motion/no-JS output. |
| Work image strip | 4 | Showcase `showcase_presentation=scroll-strip`, preserving its media, captions and links; wide viewports progress horizontally with scroll and Arrow keys; skip moves focus beyond the range; narrow/reduced-motion layouts retain native horizontal scrolling. |
| Timeline | 5 | Cards `card_presentation=timeline`, `timeline_image`, `timeline_skip_label`, and existing ordered Card Rows; progress/select steps, retain nested copy/links, and skip by keyboard. Tall, narrow, reduced-motion and no-JS presentations expose all steps without pinning. |
| Comparison records | 1 | Content Lists `record_presentation=comparisons` preserves source, selection/order and pagination; seven existing records provide before/after images, tags and legacy RGB colors through the item-data contract. |
| People | 1 | Content Lists `record_presentation=people` preserves all nine member records, featured images and designations. Optional hover/focus video remains unloaded until interaction, stops on blur/leave/hidden page, and does not play with reduced motion. No current member has an authored hover video; an owned fixture qualifies that optional behavior. |
| Video expansion/fade | 1 | Video `video_presentation=scroll-expand` and optional Site Styles `video_follow_color`; existing deferred player, controls/poster contract retained; the following native surface receives the transition color. Reduced motion restores static sizing/background. |

Parent **1.5.20** and Reusable Block Library **0.2.2** are paired source
candidates. The RBL change delegates Basic/CTA headings to the optional parent
contract and retains its existing escaped standalone fallback. It lives in the
separate `mrnwebdesigns-migration-rbl/mrn-reusable-block-library` task worktree.
No existing field keys were replaced: new fields are inserted before ACF extracts
the raw field tree, full-contract clones inherit their reusable source, and
nested compatibility definitions receive unique keys.

The generic `mrn_base_stack_content_list_item_presentation` filter adapts the
normalized people/comparison metadata. The existing pre-render filter still runs
first. Shared code contains no client CPT or record IDs. A site-owned adapter,
`inc/stack-migration-presentations.php`, exposes the existing non-public
`mrn_before_after` CPT through the existing source-choices filter; it does not
change public URL behavior. Color normalization preserves the seven existing
RGB values while refusing injected declarations.

## Presentation qualification and remaining gates

- `native-presentations.json`: **42 passing native ACF/runtime assertions**, in
  addition to the rerun 28 repeater and 33 collection assertions (**103 total**).
  Phrase/gallery overflow refuses the entire tree, including unrelated sibling
  fields. Native copy, links, ordered media, heights, colors and existing records
  survive save/readback/render. XSS and CSS injection cases are refused/escaped.
- Updated component-asset tests cover defaults, nested rows and reusable references;
  standard/legacy pages do not acquire presentation assets. Five standalone suites
  cover assets, cloned AJAX/After Content, finalization, motion choices and lazy
  layouts. RBL layout-class tests also cover the escaped standalone heading fallback.
- `task-qa.md` / `rbl-qa.md`: MRN QA source checks and full RBL component checks;
  no API write surface was introduced. REST runtime health is a separate check
  against the still-installed release. PHPStan scans the existing builder renderer
  declarations so the new timeline calls are checked against their real signatures.
- `presentation-browser.json`: desktop/mobile source-preview evidence. Keyboard
  Step 3 selection retains its copy/link; skip puts focus past the timeline.
  Strip Arrow input changes its translation and skip focuses its end marker.
  Optional people video plays muted on focus and stops on blur. Expanding video
  retains native controls and updates the following surface's selected token.
  At 390x844 there is no document overflow, both media stages are 340px, the people
  list is one column and all four timeline steps remain open. Reduced-motion and
  no-JS behavior are source-reviewed; emulated reduced-motion browser acceptance
  remains part of installed qualification.
- `presentation-accessibility.txt`: engine axe WCAG A/AA and keyboard smoke pass
  on the source preview. Its dynamic aria-expanded/dialog coverage finds none;
  native details progression was exercised separately. `presentation-timing.txt`
  records a local HTTP 200/TTFB/total timing sample. These are not production CWV
  or complete page/media load-performance claims.
- `presentation-source-readback.json`: all **63 authored records retain identical
  builder metadata** after fixture cleanup. Installed parent remains **1.5.18**,
  RBL remains **0.2.1**, and the frozen selected release was not changed.

The source preview is a static document generated by the native candidate
renderers with real local public records/media plus one disposable demonstration.
Candidate files remain outside managed runtime paths; only the two new assets and
rendered HTML are served from the owned `stack-source-preview-20261015` directory.
This demonstrates source rendering and interactions, not real-page parity or an
installed Classic Editor round trip.

Next: accept/package the paired source through the established release process,
adopt the qualified immutable candidate on the migration clone, qualify Classic
Editor saves plus reduced-motion/full-page performance, then update converter
mappings and regenerate every plan/hash against that runtime. Prior plans are
historical after the site adapter changes `functions.php`. Case study **10401**
still has a blank reusable selection requiring a content decision. Converter
transformations, exact token/width mapping and real-page responsive comparisons
remain per batch; no value may be silently flattened or discarded.

Publication must start from clean accepted merged source through the existing
Fleet workflow. No release lock/catalog entry was hand-edited, no production or
site deployment occurred, and the canonical shared checkout was not edited.

## October 16 source reconciliation

The source branch now includes accepted cookie-preferences commit e0bcd17. The
parent candidate advances to 1.5.20 so its bytes cannot collide with the accepted
1.5.19 version. The original October 15 receipts remain historical exact-snapshot
evidence; renewed commit/runtime qualification and release receipts supplement
them. The RBL candidate remains 0.2.2. No release is considered installed by a
source merge alone.
