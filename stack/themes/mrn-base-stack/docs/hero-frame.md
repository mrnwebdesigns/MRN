# Basic Hero frame controls

Parent 1.6.1 introduced optional native Basic Hero controls for inner gutters,
content and heading widths, internal spacing, responsive typography and action
icon height. Blank controls retain existing output. They are not added to body
Basic rows or Split Heroes.

Parent 1.6.2 adds four optional fields:

- **Small-Screen Gutter Breakpoint:** blank or 767 retains the existing 767px
  breakpoint; 960 and 1199 select the corresponding maximum viewport width.
  Content-width and heading-spacing breakpoints remain independent.
- **Body H2 Typography:** choose the existing H3 type scale while retaining H2
  markup. The renderer does not rewrite content or change heading levels.
- **Space Before Body H2 Headings** and **Space After Body H2 Headings:** validated
  non-negative lengths scoped to this Hero's body H2 headings. For example, `1em`
  tracks the selected responsive type scale.

The typography choice reads the existing H3 Site Styles tokens for color, family,
size, weight, line height, letter spacing and text transform. Explicit property
declarations also work with Site Styles' generated literal global heading rules.
Unknown choices and unsafe lengths do not generate classes or styles. No
JavaScript, global typography setting or global spacing default changes.

The native runtime fixture in `tests/runtime/hero-frame.php` verifies schema,
save/readback, malformed values, opt-in rendering and unchanged defaults. A site
migration must separately qualify its own content hierarchy, responsive layout,
editor persistence and full-page accessibility before converting authored pages.
