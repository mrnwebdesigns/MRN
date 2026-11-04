# UX-039 — Section heading pointer-focus border

The blue rectangle was the heading's `:focus` outline after pointer selection in the page outline or Previous/Next controls. Changed the existing heading selector to `:focus-visible` in `mu-plugins/mrn-admin-ui-css/mrn-editor-workspace.css`. Pointer-selected headings now have no outline; keyboard-selected headings retain the existing 2px semantic accent outline. Focus still moves to the heading as before.

Source commit: `9258536badb29f8010317e9fc65c8380c210e34c`. One CSS file installed only to `admin-editor-pilot.mrndev.io` after the labeled remote database-only Updraft backup was verified. Exact before/after hashes and private rollback location are in `install-receipt.json`.

MRN staged-source QA passes. Targeted browser checks reproduce the original outline and verify the installed pointer/keyboard behavior in both Light and Dark modes; the served CSS SHA-256 matches committed source. See `before.json`, `candidate.json`, and `installed.json`. The initial test assumed the handle was a direct child of the ACF layout; it was corrected to match ACF's actual nested heading markup. The final assertions pass, with no product behavior change beyond the requested selector.

No JavaScript, DOM, ACF lifecycle, validation, publishing or save-path changes, new assets, requests or observers. Broad runtime/axe, content saves and CPU benchmarks were not repeated for this selector-only change; the relevant keyboard focus was explicitly verified. Existing CPU/OOM and release/Fleet gates remain open. Existing user edit tabs were preserved; the agent-owned Codex tab shows the selected second section without the border.

[Before](before-light-pointer.png) · [Installed Light](installed-light-pointer.png) · [Installed Dark](installed-dark-pointer.png) · [Keyboard focus retained](installed-light-keyboard.png) · [Codex view](codex-pointer.png)
