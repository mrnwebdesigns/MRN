# Native Process column and divider controls

This opt-in extension fills two migration gaps: a fixed introduction width could
not express a share of the space remaining after the column gap, and native
Process had no trailing introduction divider. Existing Process field defaults,
Standard/Pricing/Timeline Cards, content, required Card Row and item bounds stay
unchanged. No site-specific wording is added to shared source.

## Native contract

Introduction Column Sizing can now divide the available width after the gap.
Its primary and optional 1199px shares are whole percentages bounded to 1–99.
Blank sizing retains the existing introduction width; dormant share values do
nothing. Invalid primary shares fall back to 25%; invalid tablet shares retain
the primary selection. Renderer-generated fraction units prevent CSS injection.

Introduction stacking retains its existing 991px default, with an optional 767px
selection. Its native stacked gap field applies once. The optional decorative
trailing line uses the existing Divider Color plus native inset/gap controls.
It follows the sticky introduction and clips at the details container bottom.
It never takes pointer input or changes the accessible tree. Stacked views hide
the line and stop sticking. Reduced motion keeps the divider's containing block
relative, with the sticky offset removed, and exposes all real detail copy.
Existing row presets continue to own outer margin/padding. No new script or
network payload is introduced; the existing FAQ controller remains unchanged.

## Source qualification

- [139 checks on PHP 8.2](php82.json) and [PHP 8.5](php85.json), including all 103
  existing Process/field-factory checks, native percentage bounds, dormant field
  preservation, unsafe selection/style refusal, exact six-label/five-panel
  output and independent fields.
- [61 browser assertions over 15 widths](browser.json), 320–1440px: actual
  template geometry consumes the width after the selected gap, primary/tablet
  shares and both stacking choices work independently, the divider stays inside
  its clipping container, keyboard focus/disclosures survive, mobile content
  fits, no-script copy remains exposed and reduced motion avoids sticky offsets.
  Axe WCAG A/AA passes in all-open, mobile and reduced-motion states. Contrast
  alone is excluded under the owner's existing instruction.
- [44 existing Process browser checks](existing-process-browser.json),
  [70 FAQ contract checks](faq-contract-regression.json), and
  [16 FAQ browser checks](faq-browser-regression.json) pass. The component proof
  performs no network requests or runtime/content writes.
- PHP/JS lint, diff and staged MRN source commit checks are required for this
  checkpoint. Their static proof does not replace installed ACF/editor/runtime
  qualification or complete-page performance acceptance.

[Desktop](frame-desktop.png), [mobile](frame-mobile.png). These use synthetic
component copy and are not Website Design page-parity screenshots. Visual QA
caught the reduced-motion containing-block/offset issue; the final source and
browser proof include its correction.

## Local adoption and release boundary

Only `inc/builder/process-cards.php` and the scoped Process rules in `style.css`
are runtime adoption inputs. The site migration receipt must verify its exact
local target, pre-write private backup, old/new file checksums and rollback,
registered ACF/REST, editor save, authored-state preservation and applicable
runtime regression checks. Existing Process content is not automatically changed.
The actual source still needs native frame/type/circle/list configuration and
complete Website Design qualification; this capability does not accept the page.

Work stays on the existing owned migration branch, intentionally unmerged.
No Stack version, lock, release catalog, Fleet distribution, shared runtime
service, production content or case-study model is changed. Remote Config Helper
parity and site-owner deployment readiness are deferred to a separately
approved promotion/deployment operation.
