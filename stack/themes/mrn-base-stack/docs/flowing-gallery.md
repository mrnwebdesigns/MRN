# Optional flowing horizontal gallery

Showcase's existing Scroll-driven Horizontal Strip retains its pinned behavior
and unset presentation defaults. The optional Responsive Media Strip frame adds
editable image width/height, gap and edge gutter at 1280/1024/768/480px, radius,
and an optional 768px row-preset breakpoint. All section spacing stays in native
row presets; the frame consumes their desktop/mobile values once.

Flow with Page Scroll moves a gallery through the viewport without adding a
pinned spacer or sticky stage. Editable pace is vertical pixels per horizontal
pixel (0.1–10, default 1.6). Above 768px it uses the existing shared passive
scroll/resize/intersection scheduler. Keyboard focus, reduced motion and small
screens use ordinary horizontal scrolling. An accessible focus-revealed skip
link remains out of normal layout flow. No new library or timer is introduced.

The frame uses full-source responsive images so a pre-cropped gallery rendition
does not change the selected crop. Invalid dimensions are inert; dormant fields
cannot activate the frame or flow. Standard Showcase and pinned strips retain
their existing output. Source qualification is local only; no release/version,
packaging or installed-parent adoption occurs here.

Run tests/runtime/strip-frame.php in an explicitly selected local administrator
runtime, plus tests/js/flow-strip.cjs and the existing image-motion regression.
