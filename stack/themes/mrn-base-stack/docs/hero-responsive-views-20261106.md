# Local responsive Hero qualification — 2026-11-06

Optional Basic Hero controls close a verified site migration gap: a narrow heading
view at 1100px and a smaller heading/body view at 640px. Existing blank selections
retain 900px/767px. The additional 640px row-padding choice changes only the vertical
preset breakpoint; side padding and margins keep their independent behavior.

All dimensions remain authored fields or native spacing presets. No site ID, copy,
color value, preset value or script is embedded in Stack. The existing length and
select validators refuse malformed values. Body Basic/Split Heroes gain no fields.

The guarded local runtime fixture passes 229 assertions including native ACF
save/readback, malformed choices/lengths, opt-in rendering, single inline fallback,
no duplicated fields and owned-draft cleanup. The migration clone's new Insights
candidate passes 415 assertions / 68 samples at 17 widths, with scripts on/off,
including breakpoint edges. The already-converted Thank You still passes all 709
frontend assertions / 44 samples. All 79 authored states and 65 routes remain exact.

Only inc/builder/hero-frame.php and css/layouts/hero.css were installed for local
qualification; exact originals are privately backed up and source/install hashes
are recorded by the site checkpoint. No installed version, historical stack.lock,
release manifest or production site changed. The source branch remains a migration
candidate, not a Stack release or Fleet promotion. Full Insights authoring, motion
and complete-page acceptance remain separate from component qualification.
