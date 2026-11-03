# Optional native image overlays — migration code checkpoint

Image Content now exposes an opt-in image-marker collection (maximum 30), small
inside-image attribution, a visible-action-only link scope, explicit action
alignment/type/arrow, tablet height and full-media frame sizing. Existing image,
caption and whole-image link defaults remain unchanged. Row spacing stays in
existing row presets; these controls do not add an outer-spacing fallback.

Marker media uses native attachments with editable percentages, width, center/tip
anchor, decorative/alternative-text semantics, optional shadow and bounded hover
scale. Informative markers without alt are omitted. Decorative markers use empty
alt plus aria-hidden. Reduced motion and coarse pointers stay still. Styles and
coordinates use existing validated helpers; URLs and output attributes are escaped.

Visible-action-only links keep the base image unlinked, require a visible action,
retain safe external rel attributes and reset to a visible fixed position on
keyboard focus. Fine-pointer following remains frame-coalesced and clamped. Its
link transition is restricted to background so inherited all-property transitions
cannot animate pointer coordinates. Existing whole-image action behavior is tested.

Local qualification: 39 overlay assertions on PHP 8.2 and 8.5, 27 full theme PHP
suites per version (54 runs), existing pointer regression, 28 browser interactions,
and nine-width source/native comparisons with a separately recorded native mobile
spacing correction. MRN source/API/accessibility/performance QA passed for Home and
an isolated component before a later localhost HTTPS connection refusal. Contrast
alone is excluded by the owner. Full-theme configured PHPStan has zero errors;
the partial engine scan has 41 advisories with no baseline changes.

This is a code checkpoint, not final site acceptance or a Stack release. The final
runtime-bound Classic Editor save/readback and complete Contact page/form remain
held: an earlier owned draft save preceded a runtime CSS refinement and its binding
correctly refused stale proof; localhost subsequently refused HTTPS. Owned drafts
were removed and authored source/styles/routes/media preserved. No release lock,
version, package, Fleet promotion, remote site deployment or database write.

Site migration evidence and exact local rollback receipts are recorded in the
mrnwebdesigns site branch under docs/stack-migration-2026-11-03/map-overlays/.
