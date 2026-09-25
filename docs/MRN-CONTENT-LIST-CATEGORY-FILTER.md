# Content list category filtering

Content Lists / Reference Content retain the existing taxonomy filter, including
saved Tags, Categories and custom taxonomy selections. Its editor label now
names tags and categories explicitly. Existing field keys and default values are
unchanged.

A separate **Category Filter** can further narrow the list:

- **No Category Filter** preserves existing behavior, including rows saved before
  these fields existed.
- **Use Specific Categories** accepts comma-separated category slugs.
- **Use Current Page/Post Categories** reads categories from the rendered page or
  post. A context with no assigned categories returns no matches.
- **Category Matching** supports any or all selected categories. Descendants are
  included; all-mode requires a match in each selected category's subtree.

When both filters are enabled, an item must satisfy both. For example, select
Tags with the `featured` slug in the existing filter, then `news` in the Category
Filter to show featured news. Specific Content selections can also be narrowed
by category; their ordering remains unchanged.

Category controls appear only for sources registered with the built-in
`category` taxonomy. Switching sources retains category values, and unsupported
sources ignore that constraint. Custom category taxonomies remain available
through the existing taxonomy selector. The change adds no REST/AJAX endpoints,
frontend assets, layouts, content migrations, or database options.

## Local regression checks

Use an isolated `.localhost` runtime with ACF Pro, MRN Reusable Block Library,
MRN Site Colors and this parent theme. The fixture refuses remote runtimes and
creates only uniquely identified test posts and terms. Set `MRN_CATEGORY_FIXTURE`
to an absolute private JSON path, then run:

```sh
wp --path=<isolated-public> eval-file <theme>/tests/runtime/content-list-category-filter.php setup
wp --path=<isolated-public> eval-file <theme>/tests/runtime/content-list-category-filter.php check
```

The check covers real WordPress queries, ACF save/reload, tags alone, categories
alone, combined any/all filters, descendants, current-page categories, unknown
and empty categories, legacy saved rows, unsupported sources, cloned ACF
conditional keys, and the real row renderer.

For editor/browser checks, set `MRN_CATEGORY_LOGIN` to a private JSON file with
`username` and `password` for a disposable local administrator and
`MRN_CATEGORY_EVIDENCE` to an existing scratch directory. Run
`tests/runtime/content-list-category-filter-browser.cjs` with Playwright and
`@axe-core/playwright` available. It saves and reloads real editor selections,
checks source switching and conditional fields, validates frontend results, runs
axe on the new controls, and captures desktop/tablet/mobile screenshots.

Run `check` again after the browser tests, then `cleanup`. Revoke the disposable
administrator/session and remove its private login file. Do not install the
fixture or its credentials on shared remote runtimes.

## Task and release boundary

Task branch: `codex/content-list-category-filter-20260925`.

The feature merged in PR #123 as its own commit. The separate Content Only
editor guidance merged in PR #125, preserving both changes in the shared editor
files. Parent theme `1.5.0` includes these changes in the
`2026.09.25-content-filters-fleet` release candidate. See
`docs/releases/2026.09.25-content-filters-fleet.md` for qualification and rollout
boundaries. Source QA and candidate packaging do not represent a site deployment.

## Validation on 2026-09-25

The disposable local WordPress runtime passed 19 integration assertions and five
browser scenarios, including an actual editor save/reload and anonymous filtered
output. The category controls had no axe WCAG A/AA violations. Desktop, tablet,
and mobile screenshots were reviewed.

Scoped MRN QA passed static/security checks, REST index health, browser smoke,
accessibility, page timing, and Core Web Vitals. Existing PHPStan symbol warnings
and fleet parity drift were advisory; neither was changed here. API surface
checks were not applicable because no endpoints changed. Release lock, rollout,
and license coverage were outside this feature task; INP was not measured by the
fixture. The disposable runtime and credentials were removed after verification.
