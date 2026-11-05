# UX-046 — Content belongs in Content

Installed only on `admin-editor-pilot.mrndev.io`. Source commits `48728b7f5884`
(content routing) and `d2b1b0e93448` (keyboard fallback). Awaiting visual review.

## Editor behavior

The workspace previously treated only MRN Flexible Content groups as content.
Ordinary ACF authoring groups fell through to Page Settings, and native core body
content lacked a matching visibility boundary.

The existing workspace now uses registered WordPress post-type support for title,
body, excerpt and featured image. It uses the existing ACF group discovery and
field definitions for ACF panels. Non-sidebar groups and sidebar groups containing
authored, nested or unknown/custom fields belong to Content. Only sidebar groups
composed entirely of switches and presentation fields remain in Settings. A mixed
group remains intact in Content: field type alone is not used to split fields or
infer their frontend meaning. No new ownership/configuration contract was added.

On this child theme, Team Member Settings also contains published eyebrow and
back-link text; the whole mixed group therefore belongs in Content. Job Title, Bio,
photo and those labels are available there. Posts keep native body/excerpt and Card
Details there; Press Releases keep their body/excerpt and sidebar ACF image there.
Publishing, attributes, taxonomies, slug, SEO and other administrative panels remain
in Settings. Post types outside the existing approved workspace retain their native
editor; the Resource screen was checked as a boundary case.

The outline provides direct links to ordinary content panels. Validation opens the
correct tab and uses ACF's own tab/repeater/layout reveal behavior. Section jumps
scroll to the selected layout, accounting for preceding authoring fields. Empty
builder instructions no longer describe a content-filled record as empty. Native
controls remain in their original form and parent containers, with unchanged names,
values, save handlers and nonce/capability protection. ACF conditional logic and its
native hide-on-screen stylesheet remain authoritative. Optional WordPress content
panel checkboxes are managed like the existing builder-region checkboxes.

[Before: fields in Settings](before-team-settings.png),
[installed Content, Light](installed-team-content-light.png),
[installed Content, Dark](installed-team-content-dark.png),
[installed Settings, Light](installed-team-settings-light.png),
[installed Settings, Dark](installed-team-settings-dark.png).
The saved [Team Member review fixture](https://admin-editor-pilot.mrndev.io/wp-admin/post.php?post=2618&action=edit)
is open in Codex; the owner's unsaved editor was not reloaded.

## Qualification

- MRN staged-source gates passed: `c463cdb26d7501f6820f20fef564612f833a7be1`
  and `a46a826794b158baeaec02e99796ab2db685b72e`.
  [Main source gate](source-qa.log), [focus follow-up gate](focus-source-qa.log).
  PHP lint, security/WPCS checks, diff check and manual JS syntax check pass.
  The existing PHP ACF-discovery harness now also verifies core support, mixed and
  nested groups, conservative unknown-field handling and configuration-only groups.
- [Candidate checks](candidate.json) and [18 installed checks](installed.json) cover
  Light/Dark, mobile, ACF validation routing, keyboard panel navigation, original form
  identity/values, native core fields and the 19/32-section builder fixtures.
  A [synthetic no-Flexible-Content case](core-only.json) verifies mobile focus and Escape
  when only native/core or ordinary ACF content panels are available.
- [Native save/reload](candidate-save.json) passed through the real WordPress form on
  owned draft fixtures 2618 (Team Member) and 2619 (Post), including saving while in
  Settings. ACF Job Title/Bio and core body/excerpt persist. Save round trips were
  7.96s and 6.97s. Fresh installed browser and [WordPress readback](content-readback.json)
  confirm the values and draft status. Existing 2523/2524 content hashes are unchanged.
- Scoped axe WCAG A/AA checks pass on the new workspace controls. Native TinyMCE
  warnings match the [pre-change baseline](baseline-a11y.json): Visual has 24 unnamed
  child-button and 24 nested-interactive reports; Text has one unlabeled native ACF
  textarea report. These are recorded limitations, not an AA certification. No new
  warning was introduced by routing. Native Visual/Text behavior is retained.
- REST health returns 200; no new API handlers, permissions, nonces or persistence
  paths. [Versioned asset hashes](served-files.json) match committed source.
- [Alternating startup samples](performance.json) on the complex fixture have 210
  resource requests in both versions. [Measured medians and payload](performance-summary.json):
  script time 1536ms before / 767ms candidate; task time 2141ms / 1224ms; heap 30.65MB /
  27.88MB. Samples vary substantially, so these show no observed regression in this
  run, not a qualified CPU improvement. Added payload is 750 gzip JS bytes and 35 gzip
  CSS bytes. No added requests, observers, polling or per-keystroke classification.

The staged-source gate deliberately skips named-site runtime checks; the targeted
browser/save/API/accessibility/performance checks above supply that coverage.
Whole-plugin readiness, broad third-party compatibility, Fleet parity, release and
promotion checks were not run for this bounded review change. CPU qualification,
Local Hub OOM and prior release blockers remain open.

## Files and installation

Changed runtime files:

- `mrn-editor-lockdown/includes/section-navigator.php`
- `mrn-editor-lockdown/assets/section-navigator.js`
- `mrn-admin-ui-css/mrn-editor-workspace.css`

Regression source: `tests/php/acf-layout-discovery.php` and
`tests/runtime/content-routing-fixture.php` in editor-lockdown. The latter creates
only marked, reusable draft fixtures under the exact approved host guard; it is
stored privately on the review host and is not a public endpoint.

Exact-host, owner, writable-path and before/after hash checks preceded each atomic
install. Fresh labeled database-only Updraft backups were verified remotely before
fixture setup and code writes. [Main install receipt](install-receipt.json),
[keyboard follow-up receipt](focus-install-receipt.json). Private rollback copies
remain available. Temporary QA authentication JSON files were removed after testing.
No production/Fleet deployment or release publication.

Next annotation: UX-047. Review the content grouping before further UX changes.
