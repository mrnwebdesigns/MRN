# Theme Task List

This document turns the stack theme roadmap into practical work for the development team.

Use it with:

- `/Users/khofmeyer/Development/MRN/stack/THEME_ROADMAP.md`
- `/Users/khofmeyer/Development/MRN/stack/BUILDER_CONVENTIONS.md`
- `/Users/khofmeyer/Development/MRN/stack/STACK_OPERATIONS.md`

## How To Use This

- Treat each item as a candidate ticket or milestone.
- Keep the base theme focused on reusable system value, not one-site styling.
- When a task changes durable theme behavior, update:
  - this file
  - `THEME_ROADMAP.md`
  - `memory.md`

## Phase 1: System Foundation

### Layout System

- Define and document the canonical container widths used by the theme.
- Normalize section padding and vertical rhythm across standard layouts.
- Define shared max-width rules for content, wide content, and media-led sections.
- Audit current layout wrappers so all standard builder sections use consistent shell classes.

### Typography

- Establish the default typography scale for headings, body copy, lists, and captions.
- Normalize heading margins and content rhythm for classic post/page content.
- Define a consistent treatment for inline links inside body copy.
- Review readability defaults for long-form content areas.

### Buttons And Links

- Formalize the theme-level button system for primary, secondary, and text-link treatments.
- Ensure CTA, Basic, Grid, and Slider links all map to the same visual language.
- Document the exact classes or data hooks front-end developers should rely on.

### Forms

- Create a clean base form style for inputs, selects, textareas, and buttons.
- Confirm third-party form plugins inherit sensible theme defaults without over-styling.

### Media

- Normalize responsive image handling for all section layouts.
- Define shared rules for aspect-ratio behavior where layouts need it.
- Review default video spacing and embed wrappers so media sections feel intentional.

## Phase 2: Template Coverage

### Header And Footer

- Define the minimum starter header structure every site gets.
- Define the minimum starter footer structure every site gets.
- Clarify which header/footer concerns belong in theme templates versus Config Helper or Site Styles.

Current first-pass header baseline completed:

- header now prefers the `Business Information` logo and falls back to:
  - WordPress custom logo
  - site title
- primary menu uses `menu-1`
- utility menu uses `menu-2`
- theme-owned header toggles now live on the `Theme Header/Footer` options page for:
  - utility menu
  - stack search area
  - business phone
  - business profile
- theme-owned business/contact data now lives on the `Business Information` options page
- current business logo variants available for future header/footer work:
  - default
  - inverted
  - footer
  - footer inverted
- business information now also acts as the theme-owned schema source for:
  - business profile
  - logo
  - contact numbers
  - address
  - weekday hours
  - social URLs via Config Helper
- header search is expected to render through the theme hook:
  - `mrn_base_stack_header_search`
- the current default header search implementation is a SearchWP-friendly form, so the header toggle now renders usable search without waiting on a bespoke component

Current first-pass footer baseline completed:

- footer menu uses `menu-3`
- legal menu uses `menu-4`
- footer can now consume:
  - business profile
  - business phone
  - text/SMS number
  - address
  - weekday hours
  - social links
  - legal text
  - copyright text
- footer continues the shared-source rule:
  - business data from `Business Information`
  - social links from `Config Helper`

### Core Templates

- Review and strengthen:
  - front page behavior
  - page template shell
  - single post template
  - archive template
  - search template
  - 404 template
- Add better empty states where templates currently feel bare.

### Navigation

- Define a cleaner base navigation pattern for desktop and mobile.
- Establish the starter interaction model for submenus and responsive navigation.
- Document which navigation behavior is guaranteed by the base theme.

### Pagination And Post Meta

- Normalize archive pagination styling and spacing.
- Review post meta presentation for single posts and archives.
- Keep metadata flexible enough for site-specific adjustments.

## Phase 3: Builder Presentation Polish

### Standard Layout Review

- Audit every standard theme-owned layout for:
  - responsive behavior
  - spacing consistency
  - heading hierarchy
  - image/media handling
  - token usage

Current standard layouts to review:

- `Text - label|title|text with editor`
- `Basic - label|title|text with editor|image|link`
- `CTA - label|title|text with editor|link`
- `Grid - label|title|repeater`
- `Slider - label|title|slides`
- `Logos - label|heading|image|link`
- `Image - label|title|text with editor`
- `Video - remote|upload`
- `Stats - label|heading|items`
- `Showcase - label|heading|image|link`
- `External - widget/iFrame`
- `Two Column Split`
- hero layouts

### Shared Section Behavior

- Normalize background color and background image behavior across supported section layouts.
- Review accent spacing defaults and override expectations.
- Confirm section spacing behaves well when accent graphics are active.

Current family-expression baseline completed:

- `Basic` now uses the layered shell to make `Content`, `Wide`, and `Full Width` read differently without abandoning the shared wrapper contract
- `Image Content` now follows the same family-level rule set
- layered `Wide` and `Full Width` variants for those families now explicitly collapse to one column on small screens

### Media Performance

- Review hero background media behavior on real devices.
- Confirm remote and uploaded video strategies are documented and performant enough.
- Identify whether more lazy/deferred media behaviors should become stack defaults.

### Class And Hook Contract

- Audit the front-end classes and data attributes emitted by the theme builder.
- Reduce one-off naming drift where possible.
- Document stable hooks front-end developers should rely on.

## Phase 4: Team Handoff Readiness

### Backend Handoff

- Document exactly where new layouts should be registered.
- Document how reusable block conversion targets should be added safely.
- Document where theme helpers belong versus plugin helpers.

### Frontend Handoff

- Create a front-end implementation reference for each standard layout.
- Clarify which layout configs are purely presentation controls versus content-model fields.
- Define the expected relationship between Site Styles tokens and theme CSS.

### Figma Handoff

- Define how Figma sections should map to standard stack layouts.
- Identify which common design patterns already have a layout versus need a new one.
- Document when design should drive a new stack pattern versus site-specific styling only.

### QA And Acceptance

- Define what “theme-ready for handoff” means for a new site build.
- Add a checklist for:
  - responsive review
  - template coverage
  - builder layout QA
  - token usage
  - accessibility review

Current width-system QA harness baseline:

- local QA pages now cover every current width-sensitive theme layout family plus reusable block families
- reusable block QA is seeded through dedicated reusable block fixtures and direct `Reusable Block` layout pages
- `After Content` now has a dedicated QA page so the cloned bucket can be checked against the same width vocabulary as `Content`

## Suggested First Tickets

- Create a shared theme spacing and container system.
  - First pass completed in the base theme CSS with:
    - shared shell gutter token
    - shared section spacing tokens
    - shared row-gap token
    - starter container helper classes:
      - `.mrn-shell-container`
      - `.mrn-shell-container--content`
      - `.mrn-shell-container--wide`
- Audit and normalize all current builder section wrappers.
- Strengthen header/footer starter templates.
- Build out archive, search, and 404 templates to starter quality.
- Document stable front-end hooks for every current standard layout.

## Rule Of Thumb

When deciding whether a task belongs in the base theme, ask:

- Will this help most future MRN sites?
- Does this reduce repeated rebuild work for the team?
- Is this a system improvement rather than one-site styling?

If yes, it is likely a good base-theme task.
