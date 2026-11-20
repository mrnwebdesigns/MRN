# Native Careers in Content Lists

Opt in with `record_presentation=careers`. Standard, People and Comparisons
retain their existing behavior. Source selection still belongs to Content Lists.
`list_count_mode=all` means every published matching record with no offset or
pagination; default `limited` preserves the prior item-count query. Compound
`orderby=menu_order_date` means menu order ascending then publication date
descending. Manual selection order keeps precedence. Careers excludes drafts
and password-protected records, including direct helper rendering.

Map existing ACF data using `career_apply_field` (link array or URL),
`career_tags_field` (ordered repeater) and `career_tag_label_field` (text child).
Defaults are `apply_link`, `tags`, `label`. Empty/unsafe apply destinations use the
record permalink. Targets are bounded and escaped; external targets receive
noopener/noreferrer. Title links supply the accessible name; the optional
secondary action uses editable `read_more_label`. Tags stay ordered, repeated
and escaped. No record-model migration or static content duplication is needed.

`career_heading_tag` controls the item heading. Optional frame dimensions,
responsive inner gutters and Site Color choices use existing validators. Compact
gutter changes at 1199px; mobile changes at 767px. Existing row margin/padding
presets remain the only outer spacing owner. Unselected Career controls add no
styles to existing lists. The profile reuses presentation CSS; there is no added
JavaScript, dependency or API route.

`tests/runtime/careers-collection.php` requires an explicit local administrator
target via MRN_STACK_TEST_HOME. It owns and removes its own temporary CPT, ACF
field group and records. It covers saved schema controls, thirteen records beyond
an item limit, compound/manual order, source link fallback, escaped repeated tags,
empty lists, invalid styles/links, and draft/password protection. The fixture tag
field allows five; the renderer adds no truncation or source-field limit change.

The MRN Web Designs site task qualifies the real source separately in
`tests/content/native-careers-preview.php`. Local proof: 96 native assertions,
37 saved-source assertions, unchanged Standard HTML, five responsive widths and
standard MRN QA. Full-site/editor acceptance, installed adoption and promotion
remain separate. This is unpublished task source; component versions, release
locks and deployment state are unchanged.
