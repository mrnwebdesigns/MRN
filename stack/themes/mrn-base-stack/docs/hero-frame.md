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

## Unpublished local candidate: inner canvas and row spacing

Two optional native controls support inherited layouts with a separate inner
canvas and spacing breakpoint. **Wide Inner Maximum Width** caps the Wide/Full
Hero's inner canvas (for example 1170px); blank retains 78rem. Content-width
Heroes retain their separate cap. Values use the existing bounded length/clamp
validator; `none`, negative values and CSS injection are refused for this cap.

**Row Spacing Mobile Breakpoint** can select 767px instead of the existing 782px.
Only that Hero keeps its desktop preset values through 768px; the selected
spacing presets still own every margin and padding value. Gutter and typography
breakpoints stay independent. Empty fields preserve existing markup and computed
layout, and no new script, timer or request is added.


## Optional heading entrance

`hero_heading_entrance` is a native Basic Hero select. Blank keeps the existing
static heading. `gentle-rise` selects a 700ms on-load fade from opacity 0 to 1
and a 0.35em upward settle with `cubic-bezier(0.16, 1, 0.3, 1)`. It applies only
to direct Hero heading elements, leaving body copy, actions, labels and media alone.
It uses the existing Hero stylesheet and adds no JavaScript or Motion dependency.
Reduced motion disables the animation. Enabling the existing Row Effects suppresses
this entrance, preventing competing transforms. Unknown and malformed values do
not activate it. It changes no row-spacing preset or unselected Hero output.
