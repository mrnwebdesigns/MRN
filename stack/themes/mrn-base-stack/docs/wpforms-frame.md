# Optional WPForms content frame

WPForms rows can select **Content Frame → Contained Content**. Blank preserves
existing field defaults and rendered markup. This adds no script or request.

- Canvas Width caps the inner container; Content Width separately bounds the
  existing heading/intro/form group. The bounded group begins at the leading
  edge by default; Center and Trailing Edge are optional.
- Inner Gutter and its 1199px compact override control the canvas inset.
- Heading size, line height, weight, palette color and following space remain
  editable native fields. Semantic heading tags retain their existing control.
- Dimensions reuse the existing non-negative length/clamp validators. Typography
  accepts only the existing bounded Site Text token namespace. Canvas width
  refuses `none`; content maximum width supports it like other content frames.
- Small-Screen Heading can visually hide the heading at 767px while retaining
  its semantic text for screen readers. Blank keeps it visible.
- Outer section margins/padding continue to use registered row presets. These
  controls do not modify WPForms fields, validation, submissions or settings.

The frame wraps the existing stacked/form-left/form-right layout contract,
without changing the unconfigured layout. Runtime coverage in
`tests/runtime/wpforms-frame.php` checks opt-in behavior, malformed inputs,
saved fields, native renderer output, unchanged blank output and owned cleanup.
Source qualification, local adoption, owner approval and release publication
remain separate gates.
