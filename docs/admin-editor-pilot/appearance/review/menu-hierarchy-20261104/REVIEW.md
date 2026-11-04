# Active submenu hierarchy — 4 November 2026

Source `162a1d8d`, scoped to `mrn-admin-ui-css/mrn-admin-shell.css`.
Standing owner authorization covers implementation, targeted QA and this review
deployment. Production/Fleet/release gates remain closed.

The primary section retains its filled accent highlight. Its submenu now has
18px more indentation, slightly smaller 12px text, tighter vertical spacing,
and an underlined current-item label without the competing filled background.
Hover and keyboard focus remain visible in both themes. AME configuration,
native links/current-state markup and all editor behavior are unchanged.

Candidate verification substitutes only the stylesheet in an isolated QA
browser against the real review site's Pages menu. Installed verification
uses the deployed asset without substitution. Both runs cover Light/Dark,
current and hover contrast, keyboard focus, scoped axe WCAG A/AA, native
collapsed-sidebar flyouts and 390px mobile navigation. Appearance and sidebar
preferences are restored. See the JSON results and matching screenshots.

MRN QA commit gate passed for the single stylesheet. PHP/API/save-integrity
tests were inapplicable to this CSS-only change; broad performance, editor,
parity and release qualification were not repeated. The asset adds 121 gzip
bytes, zero JavaScript and zero requests. Normal/hover text contrast is
9.79/8.73:1 in Light and 12.09/8.08:1 in Dark.

Installation checks the exact existing and candidate SHA-256 values and creates
a fresh verified remote DB backup (`891cd15a0159`) before the single-file atomic
replacement. Original CSS is retained outside the web root; the receipt records
its path, hashes and backup. Existing filemtime asset versioning invalidates the
stylesheet cache; no plugin version or release publication is needed for this
review-only iteration.

The original folded-menu test attempted to hover the full collapsed link, which
extends outside the viewport in native WordPress. Targeting its visible icon and
scrolling to the top resolved the test interaction; both final runs exercise
that native flyout. No extra menu behavior or width override was introduced.

- [Candidate checks](candidate-results.json)
- [Installed checks](installed-results.json)
- [Backup and installed hashes](install-receipt.json)
- [Light](installed-light.png) / [Dark](installed-dark.png)
