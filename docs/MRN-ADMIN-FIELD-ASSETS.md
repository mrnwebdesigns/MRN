# ACF admin helper loading

The ACF input enqueue event is not proof that a page contains ACF fields. ACF
PRO's WooCommerce HPOS integration also fires it on the Orders list. Loading
every MRN field helper from that event adds unused scripts, styles, and icon
catalog data to list screens.

The parent theme now separates repeater controls from icon controls. A repeater
requires the repeater helper; an MRN icon-source wrapper requires the icon
chooser, Font Awesome picker styles, and media dependencies. Config Helper's
layout picker requires flexible content. Character Count requires an exact
configured field key or the field's DOM name (`_name` after ACF preparation).
No field keys, saved values, layouts, or frontend templates are changed.

## Lifecycle

- Render hooks cover actual fields on options, taxonomy, user, HPOS order edit,
  and custom admin forms. Empty ACF row templates are rendered too, so adding
  rows continues to use the existing JavaScript append handlers. WordPress
  prints styles enqueued after the head in its admin footer.
- Post editors also support ACF loading a new field group after a template,
  status, format, category, taxonomy, or parent change. Shared Assets discovers
  possible fields once per post editor, using ACF's cached group inventory and
  native location matching. Only the editable location conditions are widened,
  inside a scoped filter removed in `finally`; actual field visibility is
  unchanged. Nested repeater, group, clone, and layout definitions are checked.
- Non-editor list/status screens do not trigger this discovery or load every
  field group. A form embedded on such a screen can still enqueue a helper when
  it renders a matching field.
- Block editors retain the plugins' existing loading behavior because ACF
  block edit forms can arrive over AJAX. The theme retains its existing block
  editor exclusion. Custom integrations with additional AJAX location rules
  need runtime qualification before release.
- Older Shared Assets versions retain conservative early loading on post edit
  screens. Full field-aware post-editor gating requires the updated Shared
  Assets helper. Standalone plugin forms still use render hooks. Character
  Count's existing frontend `acf_form()` behavior is unchanged.
- Duplicate enqueues do not repeat localized metadata. Character Count caches
  its configured asset targets for the request rather than sanitizing the same
  option again for every rendered field.

Config Helper's settings-page icon consumer is unchanged. Its existing picker
enable filter is honored. When Config Helper is absent, the parent picker
fallback retains its supported singular-editor scope. Editor Enhancements and
Focal Point are outside this change.

## Regression checks

From the Stack task checkout:

```sh
php stack/tests/php/admin-field-assets.php /path/to/mrn-config-helper /path/to/mrn-acf-character-count
php stack/tests/php/admin-field-assets.php
php stack/tests/php/admin-field-assets.php /path/to/mrn-config-helper /path/to/mrn-acf-character-count legacy
```

These are standalone PHP hook/field fixtures, not browser or full WordPress
integration tests. They check list-screen absence, nested and potential AJAX
group discovery, dependency preservation, matching identifiers, actual forms,
settings consumers, disabled policies, duplicate catalog prevention, and plugin
combinations. Run the existing Config Helper tests and MRN QA as well.

Before promotion, verify real field rendering, adding rows, template changes,
clones, saving, media selection, keyboard operation, and errors in an isolated
WordPress runtime. Capture Orders/list-page assets and timings in that same
runtime before and after the patch. Source checks alone do not prove a live
speed improvement.

### Local browser fixture

`stack/tests/runtime/admin-field-assets-fixture.php` creates a disposable draft,
ACF field groups, temporary counter targets, and a one-hour WordPress test
session. It requires WP-CLI and a `.localhost` site; the private session file
must be outside the web root. Use `setup`, `reset`, and `cleanup` through
`wp eval-file`. Cleanup restores the counter targets, deletes the fixture
records, and revokes the session. Back up the runtime components before testing
and restore them afterwards; never copy a task worktree's `.git` into WordPress.

Temporarily copy `admin-field-assets-empty-screen.php` into that local runtime's
MU directory. Its Tools page requests ACF input/uploader assets without fields,
reproducing the initialization contract responsible for the HPOS Orders-list
issue. Remove the fixture file afterwards. This fixture does not replace an
actual WooCommerce Orders-list check on the intended deployment target.

Run the browser runner with the QA Engine's installed Playwright and axe:

```sh
MRN_QA_ENGINE_ROOT=/path/to/MRN-qa-engine node stack/tests/runtime/admin-field-assets-browser.cjs baseline /private/session.json /private/results
# Install the patched components and reset the disposable draft, then:
MRN_QA_ENGINE_ROOT=/path/to/MRN-qa-engine node stack/tests/runtime/admin-field-assets-browser.cjs patched /private/session.json /private/results
```

The runner checks the empty-ACF page, Pages, Posts, Site Health, Reading
Settings, a real Classic Editor, and Config Helper settings. It exercises
configured/cloned counters, new repeater rows, keyboard layout/icon selection,
ACF's template-change AJAX insertion, and draft save/reload. Existing disabled
bulk-repeater controls remain disabled. JSON and screenshots record asset
elements, inline character counts, navigation timing, console/asset errors, and
axe WCAG A/AA findings. Compare baseline findings rather than treating inherited
admin markup issues as new regressions. These local timings are diagnostics,
not a production speed benchmark. Keep session files and DOM debug captures
out of Git; they can contain authentication data or nonces.

## Promotion boundary

This is coordinated work across the parent theme, Shared Assets MU plugin,
Config Helper, and Character Count. Merge the three repository changes and
complete version/catalog/release-lock reconciliation in the Stack promotion
workflow. A previously generated release lock or site package does not include
these changes. Do not promote the in-progress worktrees or deploy to a site
without the separate authorization, backup, and runtime verification gates.

References: [ACF render hooks](https://www.advancedcustomfields.com/resources/acf-render_field/),
[ACF JavaScript lifecycle](https://www.advancedcustomfields.com/resources/javascript-api/).
