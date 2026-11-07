# Native Process paragraph controls under site styles

Real MRN Web Designs page CSS exposed that selected introduction/answer font
sizes and line heights were applied only to wrapper elements. Site paragraph
rules therefore overrode them. Valid selections now activate paragraph-scoped
classes for size, mobile size, line height and Site Color. Unset controls retain
their previous behavior. No new handler or JavaScript is introduced.

An optional native Detail List Start Margin field handles inherited site list
indentation independently of list padding and outer section presets. Values pass
the existing bounded dimension validator. The site migration selects 0px; other
Process rows receive no override until the control is selected.

244 isolated PHP contract/renderer/refusal checks pass on each PHP 8.2 and 8.5.
99 browser assertions pass over 16 widths, with later site-like paragraph/list
rules. Selected introduction type remains 20px/28px and answer line height 21px;
the list start margin remains 0px. Keyboard, reduced motion, mobile manual
collapse, no-script content and axe WCAG A/AA pass, with only the owner's contrast
exception. Dormant Process computed styles match the immutable 6f6e7861 baseline
at all 16 widths. Existing first-five/six-label content boundaries remain exact.

This is migration-branch source qualification, not a Stack release or full native
Website Design page acceptance. Parent version and release locks are unchanged;
feature branches remain intentionally unmerged. Local installation, actual-source
responsive comparison, editor save and public runtime checks are recorded in the
site migration receipt. No production work or shared service restart is included.
