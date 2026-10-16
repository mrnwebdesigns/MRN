# Native Tabs and stacked Split columns — source qualification

Parent candidate **1.5.24** extends the existing Two Column Split with an opt-in
`column_rows_mode=stack`, up to ten native rows per column, an internal content
gap and vertical alignment. Existing unset/single rows keep first-child output;
previously ignored children are not activated automatically. Child margin and
padding remain native Stack spacing fields. No site CSS gap patch, new layout
type or JavaScript is added.

Tabs can hold one finite Split panel. Split columns offer native Stats, alongside
their existing content layouts; neither nested Tabs nor another Split is added
to the column catalog. Stats fields are hydrated only after root registration,
then cloned with keys unique to each column/context. This avoids both recursive
Tabs hydration and collisions with root Stats editor controls. Derived icon/media
field keys use the existing ACF query registration path and authorization.

Icon-only tabs gain an editable accessible name. A visible tab label takes
precedence to preserve label-in-name; existing unnamed tabs retain their numbered
fallback. The existing arrow-key/focus/tab-panel behavior is reused.

## Qualification boundary

The guarded migration clone uses a private CLI source bootstrap and static
component preview. Managed runtime remains published parent **1.5.20 / RBL
0.2.2**. No authored record is converted. Source acceptance, publication, installed
adoption, Classic Editor acceptance and full-page conversion remain separate.

- **28 native assertions** cover bounded and nonrecursive fields, isolated root/
  nested keys, retained root Display Styles/width controls, deep ACF roundtrip,
  ordered render of heading/Stats/rich copy/final CTA, complete query links and
  safe targets, icon-only names, unique HTML IDs, authored gap/alignment, default
  compatibility, invalid-style refusal and removal of the owned draft.
- Three additional read-only isolated ACF field-query checks recover native media
  selectors in left, right and tabbed Split columns. The complete root Stats
  schema exactly equals frozen parent 1.5.23 after cloning.
- **142 existing assertions** cover collection limits (28), migration collections
  (33), native presentations (40), Body Text lists (19) and image Cards (22;
  proof-export assertions were not requested in this repeat).
- Converter **20261016.9** refuses excess flexible children before writing. It
  requires the stacked Split contract for a separate consultation CTA and
  verifies that CTA in the actual rendered HTML. Source 1.5.24: **25 groups / 40
  removed fixtures**. Installed 1.5.20: **25 / 34**. Frozen source 1.5.23: **25 /
  39**, including missing stacked-column refusal. Repeat, rollback and retained
  source metadata remain covered.
- All **63 authored source hashes** remain identical; 300 saved rows/48 families
  remain inventoried. The 174 mapped-row figure is source mapping, not completed
  page conversion. One Tabs presentation gap remains until native tag/pill
  presentation and the mapper are qualified.
- Browser: at 1280px the Split has two columns; at 390px it has one, all four
  children and the final action remain visible, internal gap is 24px and no
  document overflow occurs. ArrowRight/ArrowLeft move focus/selection and expose
  only the corresponding panel. Source screenshot/preview are local evidence.
- Targeted MRN QA Engine WCAG 2 A/AA and 2.1 AA and Core Web Vitals smoke
  pass on the source component preview. They do not certify the site homepage.
- No new REST/AJAX/admin-post mutation endpoint or permissions were added. Existing
  ACF authorization applies; style enums and dimensions are bounded and escaped.
  Runtime API and authenticated Classic Editor tests wait for installed adoption.
  Remote parity, provider backup and deployment checks belong to rollout.

Source preview: https://mrnwebdesigns-stack-migration.localhost/stack-tabbed-split-preview-20261016/

The earlier Body Text/image Cards data qualification did not establish multiple
Split-child rendering. No authored page used it. Converter v9 now holds that
composition on older parents, rather than saving an invisible CTA. Parent 1.5.24
provides the missing explicit contract.
