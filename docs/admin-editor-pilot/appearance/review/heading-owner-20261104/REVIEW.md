# UX-036 — Restore AME heading alignment

Installed on **admin-editor-pilot.mrndev.io**; source
`fcd00a2aa5b715703a4c5337ea85f42564eb70ae`.

The owner clarified that section headings should be left justified, with AME
providing alignment control where available. This supersedes the horizontal
centering introduced by UX-034.

Installed AME source confirms its heading style editor has **Title → Alignment**
(`titles.alignment`: left/center/right). Its CSS generator uses title margins and
text alignment. The MRN override had reset those margins, flex sizing and text
alignment while centering the surrounding group. Those horizontal overrides are
removed. The existing native left configuration now renders again; vertical
`align-items: center` remains for the caret. No AME configuration was changed.

Only `mu-plugins/mrn-admin-ui-css/mrn-admin-shell.css` changed. Media icon fixes
remain intact. No JavaScript, requests, handlers or new settings were introduced.
Payload: -69 raw CSS bytes / +22 gzip bytes including the explanatory comment.

## Verification

- Scoped MRN commit gate passed (`source-qa.log`).
- Candidate and installed checks pass for all five headings in Light/Dark,
  expanded/collapsed keyboard states, native mobile navigation and Media drawer.
  Desktop left inset is 10px; core mobile retains its existing 15px inset.
  Caret/title vertical difference is less than 0.01 CSS pixels.
- Four scoped axe WCAG A/AA scans have zero findings. Native links are unchanged
  and no script errors occurred.
- Fresh database-only Updraft backup `273d68eb7a07` verified remotely before the
  atomic single-file install; receipt records hashes and private rollback.
- Existing Codex Dashboard tab was refreshed and captured (`codex-installed.png`).
  Editing tabs were not reloaded. Human acceptance remains pending.
- API/save regression and broad CPU benchmarks were intentionally not repeated
  for this CSS-only adjustment. Existing CPU/OOM/save and release/Fleet gates
  remain open. No production deployment, release or Fleet activation.
