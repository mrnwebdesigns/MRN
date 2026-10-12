# Classic Editor startup and QA reconciliation

Local preview: `https://platform.localhost/wp-admin/post.php?post=321&action=edit`.
Remote development and production sites have not been modified.

## Editor behavior

- Remove the per-page layout eligibility metabox. Add Row retains the complete
  eligible catalog and requests unused native controls through the existing
  capability- and nonce-protected endpoint.
- Preserve historical layout-selection metadata, native saved values, complex
  layouts, nested repeaters and clone expansion. Complex saved layouts retain
  full native field discovery.
- Start saved rows closed before paint and delay their native editor widgets.
- Remove default loading feedback. Load the native command palette's exclusive
  dependencies when opened; preserve shared consumers, translations, inline
  settings, CSP attributes and retry behavior.
- Keep native media and Post Duplicator's Classic Editor interaction working
  without the block-editor payload.
- Config Helper 0.1.74 uses the lightweight catalog with a compatibility path
  for older themes. Its accepted breadcrumb retirement remains intact.

## QA corrections

The 167 initial PHPStan findings were reproduced by explicitly passing the
standalone test files despite the configured test exclusions. Their WordPress
API stubs replaced production signatures. QA Engine now uses PHPStan's resolved
configuration to filter both analysis targets and plugin dependency scans.
Tests prove that inherited exclusions work and real production errors still
fail. PHP lint and native test harnesses continue to cover the standalone tests.

Production analysis also needed two real theme helper scan files and accurate
ACF API declarations. The resulting analysis exposed an unchecked false field
type; the fallback now preserves original definitions when ACF's native loader
is unavailable.

QA Engine now defaults Stack helpers to the selected worktree, respects
`site-only` for remote inventory audits, and gives concurrent runs separate
diagnostic and browser-result files. The remote parity audit remains available
under `site+stack`; its eight older Dev installations remain a deployment task.

## Verification

- MRN QA Engine: task scope with strict PHPStan; all executed rows passed.
- Config Helper: full component static QA and REST runtime health passed.
- QA Engine: its own MRN QA, shell regression suite, commit-gate suite and
  19 request-failure tests passed. Native PHPStan exclusion tests passed.
- Theme PHPStan analyzed the runtime theme without findings.
- Native PHP/JS checks passed for saved values, nested controls, metadata-only
  discovery, delayed widgets, inline dependency order and unavailable field types.
- The local ACF flexible-content type is registered and callable; the native
  builder exposes 22 layout definitions after the guarded fallback was installed.
- Earlier authenticated native draft tests covered unchanged rich text,
  edited TinyMCE content, new-row save/reload, modal keyboard/focus behavior,
  real invalid-nonce rejection, media/duplication cancellation, palette retry
  and repeated opening. Tested modals had zero axe A/AA violations, with no
  browser errors. Temporary draft, user, sessions and helper files were removed.
- Initial decoded response bodies fell from 9,099,037 to 7,031,965 bytes:
  2,067,072 bytes (22.7%). These are decoded payload measurements, not a
  controlled timing or compressed-transfer benchmark.

Generic public browser, accessibility, page-speed and CWV rows were intentionally
disabled in the final task runs: authenticated editor checks above cover the
changed interface. Remote inventory and historical rollout checks are outside
`site-only`. None of these task runs is full Fleet release signoff.

## Source and release state

Source versions: parent theme 1.5.13, Editor Lockdown 1.0.34, Shared Assets 0.2.2,
and Config Helper 0.1.74. Theme header, readme and asset version agree; MU wrappers
and component source headers agree. The local parent-theme review ZIP matches
all 172 packaged source files. It is not a published or Fleet-qualified release.
Historical release locks and immutable archives remain preserved. Accepted
merged source continues through the existing Fleet qualification/publication
workflow; individual site updates still require their deployment gates.
