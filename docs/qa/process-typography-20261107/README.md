# Native Process circles and independent rich-text controls

This source fills the migration's reported circle, heading and paragraph/list
presentation gaps through optional native Process controls. It adds no site copy,
new frontend script or HTTP endpoint. Unset controls preserve existing output.

Circle controls select Site Heading/Body Font for numbers, horizontal inner padding
and its small-screen value, muted border width, bounded bottom bleed, and an
explicit 768px small-circle view. The existing 767px default remains unchanged.
Detail headings can select Site Font and bounded weight, with a separate mobile
line height. Detail number offsets have independent desktop/mobile fields.

Paragraphs can have their own maximum width and before/after margins, independent
of wider lists. Lists can select their own size, line height, Site Color, bounded
weight, inner top padding, after-spacing, item after-spacing and mobile columns.
Introduction paragraph spacing is independent. Each property activates only when
its validated field is selected. Outer section spacing remains in row presets.
Fonts/weights/views use closed choices; dimensions reuse existing bounded helpers.

[235 PHP assertions on PHP 8.2](php82.json) and [PHP 8.5](php85.json) pass, including
all 163 existing Process/frame assertions, exact copy/links, six overview labels,
five panels, malformed input refusal and dormant Standard/Pricing/Timeline fields.
[98 browser checks over 16 widths](browser.json), 320–1440px, verify independent
paragraph/list geometry/type/color, circle bounds, both sides of 768px/767px,
keyboard/focus, no-script and reduced motion. A reader's mobile collapse remains
selected when entering reduced motion; fresh reduced-motion entry expands all.
Axe WCAG A/AA passes in four states, excluding only owner-accepted contrast.

The computed geometry and typography of a second unselected Process instance
match the immutable d135558c stylesheet at every tested width. These are synthetic
component proofs with controlled Site Font/Text boundaries, not authored-page
parity. [Desktop](typography-desktop.png), [mobile](typography-mobile.png).
Existing [61 frame](frame-regression.json), [44 Process](process-regression.json),
[70 FAQ PHP](faq-php.json) and [16 FAQ browser](faq-browser.json) checks pass.
No network requests or runtime writes occur in the component tests.

Only the Process helper and scoped Process stylesheet are runtime adoption inputs.
Installed ACF/editor, exact source/palette, saved metadata, full-page parity,
API/accessibility/performance and authored preservation must be separately proved
on the exact local migration clone before accepting its Website Design page.
The staged source commit gate is a separate task proof; existing advisory missing
WP-CLI-stub coverage is not complete type analysis. Remote Config Helper parity,
site-owner readiness, release/version/lock and Fleet promotion remain separate.

The branch remains intentionally unmerged. No authored conversion, shared service
restart, production content change or remote migration is included in this source
checkpoint. Existing migration holds and the case-study model remain in force.
