# Native CTA frame

Parent 1.6.9 adds an optional **Stacked Content** frame to Page Specific CTA
and its compatibility/nested layouts. Blank frame selections preserve existing
markup and presentation. Fields belong to each layout's unique ACF key scope.

The frame has editable maximum content width, desktop/small-screen inner gutters,
a 767/960/1199px gutter breakpoint, content gap, body-text leading space,
paragraph trailing space, action leading space/minimum width, and content/action
alignment. Action minimum widths are capped to the available content width.
Values use the existing bounded dimension validator. Width also accepts `none`;
unsupported values do not render CSS. Paragraph spacing includes the last
paragraph, so migration tools must account for the resulting action separation.

The outer row owns native margin/padding presets and motion. The inner reusable
CTA keeps its media, heading, body, complete links and native Site Color contract.
Its selected background color also paints the outer row's padding. The parent
supplies the frame styles; migrations must remove or scope obsolete child-theme
spacing overrides instead of copying those values into CSS.

This feature does not migrate authored content, change global defaults, introduce
JavaScript or alter REST/AJAX endpoints. Each site still needs responsive,
Classic Editor and complete-page acceptance before converting content.

Run `tests/runtime/cta-frame.php` through WP-CLI in an explicitly selected guarded
localhost administrator runtime. It creates and removes only its owned draft.

## Optional responsive heading and button frame

The CTA editor now accepts four links, matching the existing reusable renderer's
primary through quaternary action slots. Existing one/two-link rows keep their
order and output. Page-specific and nested compatibility CTAs use isolated keys;
Basic row capacity is unchanged.

Stacked Content adds heading size/trailing-space overrides at 991px and 600px,
responsive inter-action gaps and an optional 600px inner-gutter breakpoint. Blank
controls retain the existing frame. Typography accepts bounded lengths or
allowlisted Site Text heading, body, label and button size/line tokens.

**Custom Button Frame** explicitly opts button links into editable padding,
small-screen padding/font size, corner radius, font family/weight, Site Colors,
hover/focus background and media-icon width. The existing media-icon height and
per-link icon position/gap controls remain available. Text links retain their
presentation. Existing focus indicators remain visible; no animation or JavaScript
is added. Section margins/padding still belong exclusively to row presets.

This source is qualified in the isolated local migration candidate. It is not a
package/version change or managed-site adoption. Full-page and Classic Editor
acceptance still apply to each site's use.
