# Reference Content integration checks

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
