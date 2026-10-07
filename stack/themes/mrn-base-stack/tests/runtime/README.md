# Builder integration checks

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
default/cache/metadata/preview fallbacks, pagination and legacy-row preservation.
`request` accepts `valid`, `nonce`, `capability`, `target`, `input`, or `layout`
for the actual 404 options AJAX endpoint.

For native browser qualification, set `MRN_PERFORMANCE_FIXTURE` to an unused
private scratch JSON path and run `setup`. It creates tagged draft Page and FAQ
records, with 55 nested Stats and 25 standalone FAQ rows, and snapshots the exact
404 options namespace. In the editor, edit/save row 55, navigate to FAQ page two,
edit/save row 25, and add/save a 404 Text row containing the unique text
`MRN performance fixture`. Run `readback` to verify counts and saved content.
Run `cleanup` to restore the 404 snapshot and move the owned drafts to Trash.
Do not run this fixture against concurrently edited 404 settings. Keep the
snapshot until restoration is verified, then archive evidence outside source.

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
