# Native responsive Logos — source qualification

Parent candidate **1.5.21** adds optional native Logos controls needed by the MRN Web Designs migration: desktop views up to seven, up to four authored viewport overrides (one to seven views), a maximum viewport for autoplay, continuous looping and an editable gap. Existing rows retain their prior defaults and generic slider behavior when the new fields are unset. Root, nested and normalized Layout contracts expose seven; no reusable-plugin update is required.

The migration's intended settings are seven desktop views, five at <=1199px, three at <=767px and two at <=479px; a 30px gap; linear four-second movement only at <=1199px and only when content overflows. These values are stored by the site converter in native fields, not hardcoded in the shared component. Native rendering clamps or discards invalid optional values; the repeater has a four-row ceiling.

The opt-in control pauses both the autoplay timer and an active transition. Motion pauses for manual pause, hover, keyboard focus, reduced motion, hidden documents and offscreen sliders. A responsive no-JavaScript fallback keeps images visible and horizontally scrollable. Its minimum-width fix prevents a grid child from overflowing on mobile; row margin/padding selectors remain unchanged. Existing generic slider options do not gain an undefined easing or clone override.

## Qualification

Source was loaded only through a private exact-clone CLI bootstrap into the guarded `mrnwebdesigns-stack-migration.localhost` runtime. The installed managed theme and release lock were not replaced. Proof HTML uses the six existing homepage logo attachments without importing or modifying media. All test drafts and converter journals were removed.

- `tests/js/responsive-logos.cjs` verifies prior generic defaults, seven/five/three/two views, viewport and overflow conditions, immediate transition freeze, user pause/resume, focus/hover, reduced motion, document/intersection visibility, delayed start and malformed-data fallback.
- `tests/runtime/responsive-logos.php` passed **24 assertions**, including native ACF keys, root/nested field uniqueness, write/readback, renderer data, invalid-value handling and exact fixture cleanup.
- Existing source runtime suites passed **101 assertions**: repeater limits 28, migration collections 33 and native presentations 40. The previous optional proof-file assertions are not part of those 101.
- Site converter `20261016.5` passed **19 groups / 27 cleaned fixtures** against both candidate source and the installed 1.5.20 baseline. The candidate maps native responsive settings, media and links with repeat/no-op and exact rollback. The baseline still refuses the missing contract; schema presence is checked rather than inferred from a version number.
- Browser source preview: widths 390/600/1000/1280 produce two/three/five/seven views without document overflow. Desktop remains static. Mobile pause was checked across successive computed positions and freezes the current transition; focused ArrowRight advances the slider.
- Targeted MRN QA Engine WCAG 2 A/AA and 2.1 AA scan passed without excluded rules or regions. Local component CWV: LCP 388ms, CLS 0.00959, INP 24ms, all good. This is a component fixture, not full-site performance signoff. Reduced motion and no-JS behavior are source/unit reviewed; browser emulation is not claimed.

Private evidence is under the migration clone's `logs/responsive-logos-*` and the owned preview at `/stack-responsive-logos-preview-20261016/`. The adjacent `responsive-logos-20261016/` evidence folder contains content-free runtime reports and browser metrics. The static staged commit gate is recorded separately by MRN QA.

Source acceptance, automatic release publication, clone adoption and real-page migration are separate gates. At this source checkpoint, the clone still runs published parent 1.5.20 / RBL 0.2.2 and the site writer retains whole-record refusal. The homepage's four unmapped layouts, exact Hero color, historical metadata, video presentation and authored-page/editor acceptance remain open. No authored page or production environment was changed by this qualification.
