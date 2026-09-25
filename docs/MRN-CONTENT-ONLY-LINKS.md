# Content Only and Content List links

Content Only controls whether a content type has its own public pages and
archives. Enable Item Links controls whether items in one Reference Content
(Content List) row are clickable. These are separate settings.

| Content source | Item links on | Item links off |
| --- | --- | --- |
| Public content | Links to available public pages or supported destinations | Content remains visible without links in this row |
| Resources (Content Only) | Links to the uploaded file; PDFs open in a new tab | Content remains visible without file links in this row |
| Content Only with another supported destination | Uses that destination | Content remains visible without links in this row |
| Content Only without a destination | Control is unavailable; no broken profile link is generated | Content remains visible without links |

Resources without a file remain unlinked. Turning links off does not delete a
file, alter other rows, or make an uploaded file private.

## Where editors see the behavior

- The Content Source selector identifies Content Only sources and explains
  that supported file downloads remain available.
- Enable Item Links explains its current On, Off, or Unavailable state. The
  explanation follows source changes, cloned rows, and saved values.
- The Resource File field explains how to display and link the file.
- Site Configurations -> Admin -> Content Types explains both settings, search
  and sitemap behavior, and which types can be switched on or off. Types
  designated Content Only by the theme or a plugin keep that existing policy;
  their disabled switch is explicitly described as always Content Only.

The implementation preserves existing ACF keys, stored settings, post-type
registration, download destinations, and save authorization. It does not
create public Resource pages or archives. The retained legacy resource-download
endpoint still serves existing forced-download URLs; Content List links use
the existing uploaded-file destination.

## Verification and promotion

Use `stack/themes/mrn-base-stack/tests/runtime/reference-content.php` and its
browser companion on a disposable local fixture. They cover filtering, public
and Content Only sources, missing files, PDF response/attributes, links on/off,
cloned rows, and actual editor save/reload. The browser companion also checks
the state explanations and accessible description associations.

Run task-scoped MRN QA against the parent theme and the corresponding Config
Helper worktree. This is a source change, not a Stack promotion. Merge and
reconcile component versions, catalog, release lock, and artifacts through the
separate promotion process before an approved remote rollout.

## Verification on 2026-09-25

The guidance was checked with the merged category-filter and heading-token
changes in an isolated local WordPress runtime. Real editor save/reload,
Resource/public/Content Only source switching, original and cloned link toggles,
anonymous filtered output, and reachable PDF links passed. Config Helper's
Content Types panel exposed descriptions for all 17 controls, including ten
locked types, with no scoped axe WCAG A/AA findings or JavaScript exceptions.

These checks preserve existing file destinations, stored field keys, and
per-row link choices. They do not represent a remote deployment.
