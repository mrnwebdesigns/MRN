# Local native service text grids — October 28

Unpublished continuation on the migration task branch after **ff970c6**.
No version, lock, catalog, package, publication, managed install or remote write.
The site checkpoint is **c4909f3**; both worktrees retain subsequent uncommitted
source. Owner authorization permits this shared work in the migration chat.

Optional native Cards fields add section heading type controls and responsive
spacing, a tablet row gap and a 991px/600px grid profile with 1199px gap changes.
Body Text can use the same heading/spacing profile and optional mobile text
spacing. Existing profiles remain unchanged when unselected; exclusions use
zero-specificity selectors so old Body rules keep their cascade strength.
Existing bounded dimensions, Site Text/Color tokens, isolated keys and native
renderers remain in use. No JS, new library or outer section-gap CSS is added.
The site's old padding bridge excludes the selected grid profile, leaving
outer spacing on native row presets. This child compatibility change is local
candidate source, not installed adoption.

48 native control, 127 saved-row, 84 existing Card frame and 33 quotation
assertions pass, alongside 32 converter groups / 49 removed fixtures and exact
preservation of all 63 authored records. Blog and PPC each retain all six
ordered headings and descriptions in native editable fields. Desktop/tablet
geometry and type match; native mobile presets repair inherited 111px widths
to 303px. Full-page and Classic Editor acceptance remain separate work.

The fresh audit also identifies a further native pricing gap across four
sections: 12 price/badge labels, four highlighted plans and 84 ordered features,
up to 15 per card, plus their actions and descriptions. Matching structured
controls and sufficient per-card feature capacity are required before those
rows map. The site's report and regression distinguish that gap from the 34
other converter-work rows. No pricing renderer is added in this batch.

Final QA receipts and source/candidate/artifact hashes are retained by the site
under `docs/stack-migration-2026-10-28/text-grid/`, with the evidence boundaries in
`LOCAL_SERVICE_TEXT_GRIDS.md`. Contrast alone is excluded by owner direction;
remote parity, rollout and site-owner deploy readiness stay deferred locally.

Both standard local MRN QA runs complete: code/security/WPCS/compatibility,
public REST/browser and accessibility checks pass, with 57 Stack / 114 site
PHPStan advisories. Overall remains Fail/Blocked by homepage TTFB (2.008–3.996s)
and variable full-page CWV. Blog preview CLS varies from 0.12441 to 0.00193;
a good later sample does not erase the earlier shift. Only contrast is excluded,
with no performance threshold changes. The site retains the complete reports
and a separate unchanged legacy Blog CWV comparison; full-page performance,
Classic Editor and installed adoption remain open.

The fresh unchanged legacy Blog comparison has CLS 0.124398 and LCP 11.092s,
with homepage LCP 46.912s / CLS 0.126134 in that separate run. This supports
existing full-page performance debt, without proving its cause or clearing
regressions. The local task remains qualified only for the selected row scope.
