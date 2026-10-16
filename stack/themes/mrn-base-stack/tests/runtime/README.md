# Builder integration checks

## Header Utility Message scheduling

Use a disposable loopback WordPress fixture with ACF Pro and the candidate
parent theme. Pass `url` (http://127.0.0.1 with its port), `username`, `password`
and an absolute `output` directory to `node header-utility-message-schedule-browser.cjs`
on stdin. The runner rejects remote URLs. It uses the native ACF options page
to enable scheduling, choose Los Angeles while the browser uses Tokyo, save
and reload dates, weekly rules, daily hours and multiline custom dates, and
reject an incomplete daily window. Read back
`mrn_base_stack_get_header_utility_message_schedule()` in a separate WordPress
request to verify the selected zone, UTC timestamps and stored rules. Destroy
the fixture afterward; this test changes its global Header Utility Message
options and must never run against a client site. Credentials remain on stdin;
screenshots and receipts stay outside tracked source.

The standalone PHP harness tests validation, named and fixed-offset zones,
spring gaps, repeated autumn hours and fractional offsets. The Playwright
schedule spec executes real helper output against cached HTML and simulated
clocks, including weekly, overnight, holiday/observed-day, annual/custom-day,
DST, manual-switch, keyboard and WCAG AA cases.

## Shortcode / Embed provider output

On an explicitly resolved `.localhost` runtime with WPForms active, set
`MRN_EXTERNAL_WIDGET_FORM_ID` to an existing local form ID and run
`wp eval-file <theme>/tests/runtime/external-widget-shortcodes.php`.
`MRN_EXTERNAL_WIDGET_TEMPLATE` may point at the candidate template in an isolated
worktree. The checks cover real WPForms markup, shortcode execution and escaping,
mixed HTML/shortcodes, provider data/accessibility/styles/configuration, raw HTML
restrictions, and iframe defaults. They make no submissions or persisted changes.

## Resource videos

Use a disposable loopback WordPress fixture with ACF Pro, the candidate parent
theme, and the Stack Content Only MU component. `resource-video.php` refuses
remote environments. Set `MRN_RESOURCE_VIDEO_STATE` to a private unused JSON
path and `MRN_RESOURCE_VIDEO_VIDEO` / `MRN_RESOURCE_VIDEO_POSTER` to existing
local MP4 / PNG fixture assets. Run `wp eval-file resource-video.php setup`,
then `check`. The checks cover retained uploads, remote provider URLs, missing
media, URL validation, poster/title/CTA triggers, title-only output, links off,
PDF behavior and conditional asset discovery. Dispose of the fixture database
and uploads after qualification; these scripts do not edit client content.

Pass the private `WordPressFixture.browser_input()` JSON, with the setup state
under `resource`, to `node resource-video-browser.cjs` on stdin. It checks native
Classic Editor validation/save/reload, delayed video loading, local/direct
remote playback, provider iframe construction, responsive posters, WCAG AA,
keyboard containment, Escape, focus return and player teardown. Provider iframe
construction does not establish third-party availability or embedding rights.
Keep session credentials, screenshots and evidence outside tracked source.

## Locations

Use a disposable loopback WordPress fixture with ACF Pro, Config Helper, the
Stack Content Only component and the candidate parent theme. Client Local Hub
sites and remote sites are outside this fixture's scope. In a separate request,
seed Business Information phone/text/fax with `9195551111`, `9195552222`, and
`9195553333`, plus Monday hours `08:00:00`–`16:00:00`, using their existing ACF
field keys and the new `field_mrn_business_fax` key. A separate request preserves
the Business Information helper's request-scoped cache contract.

Define `MRN_LOCATIONS_FIXTURE` as `true` and require `locations.php` through
WP-CLI. It refuses non-loopback or non-local environments, creates an owned
draft Location, and checks real ACF field references, custom address/social/
contact/hour persistence, independent inheritance and restored custom values.
Destroy the disposable database after qualification.

Run `locations-browser.cjs` with a private JSON object on stdin containing
`url`, `login_url`, `username`, `password`, `location_id` from the PHP check,
`engine_root` (MRN QA Engine dependencies) and an absolute `output_directory`.
It verifies native Classic Editor saves/reloads, checkbox visibility, retained
custom values and WCAG 2.1 A/AA across all four tabs. The runner saves screenshots
and scan results in the output directory; credentials are never written there.

## Deferred builder layout templates

On an explicitly resolved Local Hub `.localhost` runtime with the candidate
parent theme, set `MRN_LAZY_FIXTURE` to a private scratch JSON path. Run
`wp eval-file <theme>/tests/runtime/lazy-layouts.php setup`, then `block` to
create an owned draft page and a reusable-block picker fixture. The state file
records hashes of published page content and metadata before qualification.

Run `block-data` to seed the owned reusable block for conversion testing.
Use the native Classic Editor to add Hero and After Content layouts, nested
column layouts, duplicate/reorder rows, convert a reusable block into a
page-specific block, open the image picker, and save/reload the draft. Try saving
while a layout is loading: the editor must wait and retain the existing rows.
The `request` mode exercises the actual layout handler with `nonce`,
`capability`, `input`, `layout`, or `valid` cases. `check` reads back the draft
and refuses changed published page content/metadata. `cleanup` verifies fixture
ownership and moves the owned records to Trash; it never deletes client data.
Retain results outside tracked source and remove the scratch directory afterward.

The standalone `tests/php/lazy-layouts.php` test covers suppression boundaries
for saved rows, repeater templates, nested layout requests, defaults, unrelated
fields, and non-editor requests. Existing cloned-AJAX and field-finalization
tests remain required. This qualification changes no ACF vendor files and
does not constitute a Stack release or remote deployment.

Run `node tests/js/lazy-layouts.cjs` from the parent theme to check failed or
malformed requests, retry, pending-save guards, conversion completion, and a
layout limit reached by another editor action during an in-flight request.

## Empty builders, 404 options and repeater safeguards

On Platform's resolved `.localhost` runtime, run `builder-performance.php check`
through `wp eval-file`. It requires empty Hero and After Content on the front
page and performs process-local checks only: no catalog growth, custom-hook,
default/cache/metadata/preview fallbacks, five-row limits and legacy-row preservation.
`request` accepts `valid`, `nonce`, `capability`, `target`, `input`, or `layout`
for the actual 404 options AJAX endpoint.

For native browser qualification, set `MRN_PERFORMANCE_FIXTURE` to an unused
private scratch JSON path and run `setup`. It creates tagged draft Page and FAQ
records, with 55 nested Stats and 25 standalone FAQ rows, and snapshots the exact
404 options namespace. It temporarily removes the ACF write guard only while
seeding historical oversized fixture values. In the editor, edit/save row 55,
edit/save FAQ row 25, and add/save a 404 Text row containing the unique text
`MRN performance fixture`. Run `readback` to verify counts and saved content.
Run `cleanup` to restore the 404 snapshot and move the owned drafts to Trash.
Do not run this fixture against concurrently edited 404 settings. Keep the
snapshot until restoration is verified, then archive evidence outside source.

Run `repeater-limits.php` through `wp eval-file` on `platform.localhost` to check
the bounded collection policy against real ACF APIs. Another `.localhost` runtime
requires `MRN_STACK_TEST_HOME` to equal its exact `home_url()`. It creates an owned draft and
private options fixture, checks complete-tree rejection, nested/group/options
fields, REST schemas, row additions and legacy reorder, then deletes the owned draft
and deletes only its options fixture. The optional `browser` argument retains
the draft for native editing: attempt Add Item at 30 FAQ rows, edit/save question
30, and verify it after reload. Delete that tagged draft after readback. Other
repeaters retain five rows unless their specific collection contract allows more.

Run `migration-collections.php` with `MRN_STACK_TEST_HOME` set to the exact local
home URL and `--user=<administrator>`. It checks a single 27-question FAQ, overflow
refusal in a split-column tree, Hero links, required migration collection sizes,
reusable placement width/source isolation and manual versus automatic jump links.
It creates only owned draft fixtures and deletes them in `finally`. Run it against
the candidate source, never an unrelated runtime still using the previous theme.

## Reference Content integration checks

Resolve a Local Hub WordPress path and its `.localhost` URL before running these
tests. They require ACF Pro, the current parent theme, and the Stack Content Only
MU component. Resources and Locations must be registered. Remote URLs are refused.

1. Preserve the local parent theme before applying the source under test.
2. Set `MRN_REFERENCE_FIXTURE` to an absolute, private scratch JSON path.
3. Run `wp --path=<local-public> eval-file <theme>/tests/runtime/reference-content.php setup`.
   Setup creates only tagged fixture posts, two uploaded files and three unique
   category terms. It refuses an existing state file or conflicting term slugs.
4. Run the same command with `check`. It exercises real WordPress taxonomy
   registrations, ACF saved keys/values, `WP_Query` any/all term filters, real row
   rendering, public and Content Only destinations, explicit link-off values,
   missing files, PDF attributes and disabled Team Member profiles.
5. Supply an authorized temporary local administrator session as a private JSON
   file with Playwright-format `cookies`, and set `MRN_REFERENCE_AUTH` to it.
   Keep this file outside tracked source and never log its contents. Session
   creation/revocation belongs to the local test runner, not the fixture.
6. Set `MRN_REFERENCE_EVIDENCE` to an existing scratch directory and run
   `node <theme>/tests/runtime/reference-content-browser.cjs` with the theme's
   Playwright dependencies available. It uses the real authenticated editor,
   selects Categories, changes sources, toggles links, saves/reloads the form,
   verifies cloned After Content/nested rows, and checks an anonymous frontend
   and reachable PDF. Screenshots use reduced motion at desktop/tablet/mobile.
7. Run `check` again for database readback, then run `cleanup`. Cleanup verifies
   ownership markers before removing fixture records and media. Revoke the test
   session, remove its cookie file and restore the preserved local parent.

The clone fixtures represent retained Reference Content rows. They do not change
the site's allowed-layout settings or offer new layouts in restricted contexts.
The scripts preserve field names and derive destinations with WordPress APIs.

## Heading and label content tokens

With the candidate parent theme, MRN Tokens and Reusable Block Library active on
an explicitly resolved `.localhost` runtime, run:

`wp --path=<local-public> eval-file <theme>/tests/runtime/heading-content-tokens.php`

These checks use real WordPress shortcode parsing and both the builder and
reusable Basic Block templates. They cover escaped token values, limited inline
HTML, multiple and missing tokens, literal shortcode examples, unrelated and
nested shortcode safety, and the unavailable-plugin fallback. Registrations are
temporary within the test process; the script does not change saved content.
## Resource provider posters

The Resource **Use Provider Poster** toggle defaults off. On a native editor or
authenticated REST save, YouTube/Vimeo oEmbed thumbnails can become normal Media
Library featured images. Saves reuse the imported image for the same video URL.
Disabling the option or switching to an upload restores the prior manual image.
Unavailable/private posters, direct files and Google Drive use the manual image.
No frontend provider fetches or image imports run during rendering.

After the `resource-video.php` fixture setup, run `resource-video-posters.php`
with the same `MRN_RESOURCE_VIDEO_STATE`. It checks real WordPress media handling,
ACF saves, REST authorization, manual restoration, repeated saves, unavailable
metadata, unsafe URLs and invalid image responses. Provider HTTP responses in
this suite are explicit offline fixtures, not provider-availability evidence.
For `resource-video-posters-browser.cjs`, load
`resource-video-posters-fixture.php` only as a disposable loopback MU fixture;
pass the existing private browser JSON on stdin. Never install the fixture on
a client site. Remove its entire private runtime after qualification.

## Native presentation source qualification

`native-presentations.php` requires an administrator-owned `.localhost` clone and
an exact `MRN_STACK_TEST_HOME` match. It creates/deletes its own draft in `finally`,
uses the candidate parent plus RBL 0.2.2, and checks all eight optional native
presentations. The optional `MRN_PRESENTATION_PROOF_PATH` must be an HTML path
outside the web root. It writes escaped native markup and existing Site Styles
for separate browser qualification; it does not install code or convert records.
See `docs/qa/mrnwebdesigns-migration-collections-20261015.md` for the exact source
qualification boundary and installed-editor/release gates.

### Finite Tabs and stacked Split composition

`tabbed-split-composition.php` requires `MRN_STACK_TEST_HOME` to match an
explicit administrator-owned `.localhost` runtime. It creates and removes one
draft, checks the deep native ACF roundtrip and actual component output, keeps
existing first-row output unless stacking is selected, and validates root/nested
key isolation. `MRN_SPLIT_PROOF_PATH` optionally writes a static HTML proof outside
authored content; serve it beside the parent CSS and `js/front-end-tabs.js`.

`split-stats-field-query.php` is read-only and checks isolated media-selector
field recovery for left, right and Tabbed Split columns on the same guarded
runtime. It does not submit an admin AJAX request or change any content.
