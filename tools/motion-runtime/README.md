# Stack Motion core

`npm ci --ignore-scripts && npm run build` reproduces the committed core build.
`npm run check` verifies exact output and installed dependency versions.

Motion, framer-motion and motion-dom are pinned to 12.38.0; motion-utils is
pinned to that release's 12.36.0 dependency. The complete existing UMD asset
is preserved. The core build exports only `animate`, `inView`, and `scroll`
under `window.mrnBaseStackCoreMotion`; it never writes `window.Motion`.

Full is the compatibility default. A reviewed site may select `core` through
`mrn_base_stack_motion_runtime`. Any extension needing another Motion API must
enqueue or depend on the existing `mrn-base-stack-motion` handle. Before script
dependency resolution, a queued full consumer makes built-in effects use that
full handle too, avoiding the extra core transfer. Undeclared asynchronous
global consumers are outside the opt-in contract: retain the default full
runtime on those sites until their dependency ownership is corrected.

The component asset registry and legacy explicit enqueue helper share
`mrn_base_stack_register_motion_runtime()`. Qualification must exercise
`mrn_base_stack_enqueue_front_end_component_assets()` with the opt-in; testing
the legacy helper alone does not prove the builder's actual asset selection.

This build changes no effect options, timings, text, markup or CSS. Qualification
must cover normal and reduced motion, no-JavaScript/library failure, queued
legacy consumers and the affected site's complete interactions. Source
qualification and Fleet publication do not deploy a site.
