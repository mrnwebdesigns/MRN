# Native process Cards — source qualification

The migration's Website Design process has six ordered overview labels but only
five detail panels. Its final step is overview-only. This source adds an optional
**Process Overview and Details** presentation to native Cards so that distinction
can be authored explicitly and reused on other sites. No site copy lives in the
shared theme; the committed component fixture uses synthetic example content.

## Native contract

- Existing `card_label` supplies each overview label and detail heading once.
  `process_body` stores rich detail copy, `process_item_display=overview-only`
  retains a label without rendering a panel, and `process_item_tone` selects its
  primary or muted treatment. Empty copy does not imply overview-only behavior.
- Structured process detail fields follow the existing Pricing presentation
  pattern. The single required nested Card Row remains unchanged for existing
  presentations and is hidden for Process; overview-only steps do not need a
  fabricated empty Body Text row.
- Heading/width/outer frame reuse the existing Cards controls. Additional native
  fields hold bounded inner geometry, Site Text typography, Site Colors, first
  number, list columns, toggle icon and scroll activation position. Outer section
  spacing remains in native row presets. Arbitrary CSS is not accepted.
- A six-item semantic overview and independently selected native details preserve
  order. Plain labels are escaped; rich copy uses WordPress post-content KSES;
  common section actions escape URLs and secure new tabs. No new HTTP handler,
  database action, permission grant or public endpoint is introduced.
- The existing FAQ controller handles process details through a second scoped
  selector. Keyboard focus prevents scrolling from closing active content.
  Enter/Space retain native disclosures. Mobile and reduced motion start expanded
  while retaining subsequent manual choices; the sticky aside becomes static.
  All real detail copy remains available without JavaScript.
- Raw metadata and rendered markup discovery enqueue only the existing FAQ script
  for Process, including nested rows. No new JavaScript bundle or extra
  presentation asset is required. Existing Standard, Pricing and FAQ contracts
  retain their prior behavior.

## Source and isolated component proof

- [95 assertions on PHP 8.2](php82.json) and [95 on PHP 8.5](php85.json): unique
  clone-safe field keys, idempotent augmentation, unchanged existing Card Row
  bounds, opt-in defaults, six labels/five panels, complete rich text/links,
  explicit overview-only decisions, unsafe configuration refusal and escaped
  plain labels.
- [44 browser assertions](browser.json): actual PHP template with WordPress
  boundary stubs, independent instances, 17 widths from 320 to 1440px, no overflow,
  keyboard activation/focus, scroll focus retention, mobile manual controls,
  both states of the No Icon field, native mobile typography, script-disabled
  exposure and reduced motion. Axe WCAG A/AA checks pass in all-open, mobile and
  reduced-motion states. Contrast alone is excluded under the owner's instruction.
  The isolated test makes no network requests and performs no runtime writes.
- [70 existing FAQ contract checks](faq-contract-regression.json),
  [16 existing scroll-FAQ browser checks](faq-browser-regression.json), existing
  exclusive-disclosure regression and frontend asset-meta indexing pass.
  Component-asset regression includes three new root/nested/rendered Process
  cases, each requesting only the existing FAQ component.
- Changed/new PHP lint and JavaScript syntax checks pass. The staged MRN QA commit
  gate is separate proof; it is not installed-page or Fleet readiness.

[Desktop component](process-desktop.png), [mobile component](process-mobile.png).
These synthetic component screenshots are not full-page migration parity proof.

## Remaining gates and boundaries

This source is not installed on the migration clone. The Website Design page and
its converter mapping remain held. Registered ACF/REST definitions, a real Classic
Editor save, actual legacy-source encoding, source/native full-page parity,
WordPress API and runtime performance still require qualification after coordinated
runtime adoption. No additional authored record is claimed migrated: 17/63 remain
converted at this checkpoint.

The separately approved Contact update is owned by the speed-improvement chat.
Migration runtime cohorts stay paused until that chat's completion notice. Its
local generated Contact listbox naming finding is retained for coordinated
follow-up; this process source does not modify Contact, CAPTCHA or analytics.

No Stack version, lock, release manifest, main merge, Fleet promotion, shared
service restart, production write or content/media mutation is performed here.
Source is backed up only on the existing owned migration Stack branch. The owner
has explicitly kept the overall migration local pending completion and approval.
