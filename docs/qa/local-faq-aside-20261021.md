# Local-only FAQ and Split aside candidate

The owner requires migration work to remain local until complete and explicitly
approved. **No push, PR update, acceptance merge, Fleet promotion/publication or
remote installation is authorized in this phase.** This overrides the normal
automatic publication continuation for this task. Parent 1.6.9 remains installed;
these source changes retain its version because they are an unpublished candidate.
Work is uncommitted on `codex/local-faq-aside-migration-20261021` in this task's
existing isolated Stack worktree. The site converter changes are local too.

Optional FAQ fields supply question typography, answer leading/color, item
padding/gaps, gutter and icon width. A finite Split main/aside frame supplies
content/gutter/column widths, gaps, heading space and sticky offset. CTA framing
adds optional typography/colors for the aside. All dimensions reuse the existing
bounded validator and colors require existing Site Colors. Nested fields keep
unique keys. Blank frame selectors retain prior output; CTA and Split typography
selectors activate per validated property. No JavaScript or API surface changed.
Section spacing stays with native row presets, with one outer owner. The Split
inner container clears repeated block padding; the opt-in outer frame releases
clipping so the sticky aside follows page scrolling.

Local proof uses source bootstrap through CLI only, without replacing installed
managed components. The site's owned-draft export retains all 27 FAQ questions,
answers, order and complete destinations, then removes its draft. The actual
FAQ/home/SEO pages remain legacy. All 63 authored source hashes and indexed
builder metadata remain unchanged; only the previously accepted 11578 conversion
exists. Actual candidate editor save/reload and authored conversion remain open.

- 307 existing native assertions plus **47 new assertions = 354** pass.
- All 24 standalone PHP regressions pass. Site converter: 32 groups / 48 fixtures,
  all removed, against installed and source runtimes.
- FAQ full-page proof passes WCAG 2.1 A/AA and keyboard smoke without ignored
  regions/rules. Enter toggles native details while retaining visible focus.
- At 2560/1280/1199/992px, section heights, question widths, type and 260px aside
  match the initialized legacy section. Nine viewports have no overflow. Below
  992px the aside stacks. Its native action adds about 14.8px to stacked height,
  retained as an explicit visible difference for acceptance.
- Mobile client width 375px gains 121px to 313px content width from native preset
  selections, with the matching 18px question type.
- Full FAQ proof CWV: LCP116ms, CLS0.00175, INP16ms, all good local lab ratings.
  The separate SEO proof's earlier layout-shift concern remains open.

[Full local FAQ proof](https://mrnwebdesigns-stack-migration.localhost/stack-local-faq-preview-20261021/faq.html)
and [comparison](https://mrnwebdesigns-stack-migration.localhost/stack-local-faq-preview-20261021/comparison.html).
Evidence: [native assertions](local-faq-aside-20261021/native-faq.json),
[responsive geometry](local-faq-aside-20261021/responsive.json),
[CWV](local-faq-aside-20261021/cwv.ndjson). Site checkpoint:
`docs/stack-migration-2026-10-21/local-faq-and-visibility/README.md` in the site task
worktree. Remaining known catalog gap: homepage case-study tab/pill composition.
No source publication or remote adoption claim is made.
