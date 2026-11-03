# Round 2 refinements — installed on the protected review copy

3 November 2026. The owner approved UX-011–UX-017 and subsequently authorized moving the pilot to mrndev.io. The current review copy is **https://admin-editor-pilot.mrndev.io**. Source remains in `codex/admin-editor-pilot-20261029`; Local Hub and the imported Trilliant source were preserved. Admin UI CSS **3.3.3**, Editor Lockdown **1.0.41**, semantic contract 1.1.0.

**Visual review can continue through the [32-capture paired gallery](round-3.html). Live save qualification is blocked by Cloudflare's shared admin challenge.** Codex's embedded browser also rejected the HTTP Basic Auth prompt. The live review site remains protected; no authentication control was removed. The gallery is a static local artifact, not an authenticated live editor. New annotations start at UX-018 and remain collect-only until approved.

## Material editing changes

- **UX-011, UX-013:** an empty page shows Hero, Content and After Content Add entries in the outline and native region controls in the workspace. It explains how to add the first section, with unavailable arrangement/previous/next controls hidden. A first appended section enters focused editing; the last removal is intended to restore the empty state. Page settings use tighter title spacing and the empty permalink slot loses its reserved height. Native populated permalink actions remain.
- **UX-012:** empty and arrangement regions are inset cards with 18px desktop / 14px narrow padding. Native ACF fields remain in their original form and retain lifecycle ownership.
- **UX-014, UX-015:** WPForms and SEOPress keep their actual SVG shapes, recolored through narrowly scoped semantic masks. AME labels, links and configuration remain authoritative.
- **UX-016:** one **Screen** toolbar menu contains the original Screen Options and Help controls. Native panels, form IDs and core handlers remain. Panels scroll below the toolbar; Escape closes them and returns focus. Page titles no longer share space with detached utility buttons.
- **UX-017:** labelled Light/Dark/System icon buttons live in the user dropdown. The existing per-user preference endpoint, nonce/capability checks, early theme marker, System CSS and failure rollback remain. Choosing the saved preference sends no request.

The migrated toolbar exposed a real compatibility conflict: Toolbar Editor had persisted the former appearance node's parent as `mrn-appearance`. Reusing that node ID under `user-actions` produced an orphan after its configuration pass. The account control now has the distinct ID `mrn-account-appearance`; this preserves AME ownership without editing its configuration, installing a provider contract or adding browser reconciliation work.

## Verified and blocked checks

| Check | Result and limits |
| --- | --- |
| Migration/content integrity | Both 19- and 32-section drafts preserve draft status and complete native ACF/content hashes after URL canonicalization. A second database readback after challenged submissions still matches. [Evidence](refinements-round2/remote-integrity-readback.json). |
| Native save-integrity helper | Loaded and byte-identical to the existing source helper. Emergency disable and exact review-host PHP contracts pass. This proves configuration, not successful remote Save Draft. |
| Light/Dark/System account actions | Keyboard activation and persistence across navigation pass. The harness initially raced the native hover menu; corrected tests explicitly open, focus and close it. |
| Screen Options and Help | Light and Dark native panel opening, focus, Escape and focus return pass. No duplicate control implementation. |
| Accessibility | Zero WCAG A/AA violations in the scoped toolbar/sidebar scans and ten representative Light/Dark screen scans. Editor scope covers MRN workspace controls, not all native ACF/TinyMCE/vendor interiors. [Capture checks](refinements-round2/capture-results.json), [interaction summary](refinements-round2/runtime-summary.json). |
| Responsive captures | 390px dashboard, General settings, complex editor and empty workspace reflow checks pass; corresponding full-page screenshots retained. |
| Empty page | Native picker is reachable; first append creates/selects the row in the original form. Both themes expose all three Add routes. Save/reload and last-removal persistence remain blocked. |
| Native Save Draft / reload / incomplete-save HTTP 400 | **Blocked, not passed.** Cloudflare returns HTTP 403 managed challenge for native `/wp-admin/post.php` POSTs. Edge event `a54c68921942215d` identifies custom rule `5778f8dc0ad64ea19abc7a6601cb89a8`. It also prevented the complex fixture save harness from completing. No published content was created. |
| Source QA | Separate editor/admin logical commits and each subsequent patch passed staged MRN QA. Full-component PHP lint, security/WordPress checks and serial PHPStan pass; prior source reports retain their sandbox PHPStan warning rather than relabeling it. [Admin source report](refinements-round2/qa-mrn-admin-ui-css-source.md), [editor source report](refinements-round2/qa-mrn-editor-lockdown-source.md), [serial PHPStan](refinements-round2/phpstan-serial.txt). |
| Screenshots | [Manifest](refinements-round2/screenshots.json): 32 actual captures, both themes, same representative routes, 1487px desktop / 390px mobile. The gallery is open in Codex; its 32 images, local links, collect-only metadata, 1487/390px reflow and WCAG A/AA checks pass ([artifact checks](refinements-round2/gallery-checks.json)). It links genuine earlier captures where available. Before images retain their original date/viewport; no simulated before state. |

The exact Cloudflare exception is prepared in [the review migration proposal](../../../../scripts/review/20261103-admin-editor-pilot/proposed-save-challenge.json), **not applied**. It would exclude only logged-in-cookie POSTs to this host's `/wp-admin/post.php` from this one custom challenge. Cookie presence is not authentication: Basic Auth and WordPress cookie/nonce/capability validation still enforce access, and managed WAF remains. Explicit owner approval is pending because the MRN operating policy treats live WAF changes as production mutations.

## Performance

No new framework, plugin, asset request, polling loop, global MutationObserver or startup-wide field scan was introduced. Source growth is recorded in [asset sizes](refinements-round2/source-asset-sizes.json); source bytes are not a runtime performance pass. Existing event/reconciliation paths own empty-row behavior. Account actions send one existing request only on a changed preference.

The serial current-candidate run completed 21 page loads: one warm-up and three measured samples per theme for each 0/19/32-section fixture, with all native TinyMCE instances initialized. [Raw measurements](refinements-round2/review-performance.json) and [aggregates](refinements-round2/performance-summary.json) retain every sample.

| Sections | Browser startup CPU median, Light / Dark | Retained JS heap median, Light / Dark | Search response median, Light / Dark |
| --- | --- | --- | --- |
| 0 | 526 / 651 ms | 17.75 / 17.71 MB | 22 / 22 ms |
| 19 | 1786 / 1786 ms | 27.33 / 27.32 MB | 44 / 67 ms |
| 32 | 2281 / 2437 ms | 30.35 / 30.35 MB | 59 / 70 ms |

These are current-candidate observations, **not a performance pass or a matched regression comparison**. Fresh browser contexts, response buffering and disabled browser cache make measured wall time unsuitable as server TTFB; the final 32-section Dark load took 32.3 seconds despite 2.23 seconds of browser CPU. Other measured complex loads took 10.1–12.9 seconds. Long startup tasks reached 657 ms; this and the wall-time outlier require attribution before release. Idle CPU over two seconds had medians 6.8/7.0 ms (empty), 17.7/19.4 ms (19 sections), and 22.3/21.1 ms (32 sections). No JavaScript page errors were recorded. No polling or content mutation was used for measurements.

Matched prior/candidate CPU and startup-memory qualification remains open. Local Hub's retained forwarding-socket/OOM issue also remains unresolved; moving review does not repair or clear it.

## Isolation and rollback

The dedicated CloudPanel account/database, independent copied uploads, HTTPS, HTTP Basic Auth, noindex, disabled cron/mail/external HTTP and isolated S3 backup prefix were verified. Source login scripts/configuration and deployment identity were excluded or detached. Labeled database-only Updraft backups were verified before import, certificate activation, QA mutations and the account-menu correction. Component rollback copies remain outside the web root. The review access credential is in MRN-business / Production Hub / **MRN Admin Editor Pilot - Review access**; no secrets or database dumps are committed.

Operational provenance lives in [the committed exact-target migration](../../../../scripts/review/20261103-admin-editor-pilot/README.md). Source commits include editor `ea05be7c`, admin `eb68ea95`, exact review-host gate `fa7222dd`, and account-menu correction `502f097a`. No source promotion, release lock, packaging, publication, production-site deployment or Fleet activation occurred.

## Outstanding refinement and qualification queue

1. Approve or decline the exact review-site challenge exception; then finish 0→1→0, 19/32 native saves/reloads and truncated-save rejection. Until then the site is not qualified for editing saves.
2. Resolve protected live access in Codex; static paired annotation review is available meanwhile. Keep authentication intact.
3. Complete matched prior/candidate CPU/startup-memory qualification and the separately owned Local Hub forwarding repair.
4. Review the implemented visuals with an MRN editor. Retain new feedback as collect-only UX-018 onward.
5. Imported off-origin AME branding points to `trilliant.localhost` and is blocked/missing in remote captures. The newly visible backup-plugin icon also retains native low-contrast branding. Record these for approved narrow follow-up; do not alter AME configuration or vendor internals here.
6. Updraft remote backup jobs verify successfully. Premium advanced-retention capability has not been independently qualified; do not call the environment fully backup-policy compliant on settings alone.

## Changed components/files

- Admin UI CSS: `appearance.php`, `appearance.js`, `mrn-admin-shell.css`, `mrn-editor-workspace.css`, `mrn-modern-admin.css`, component header/lock, and targeted runtime harnesses.
- Editor Lockdown: `assets/section-navigator.js`, `includes/section-navigator.php`, component header/lock, host-gate test and guarded empty fixture helper.
- Exact-target scripts in `scripts/review/20261103-admin-editor-pilot/`, review records, screenshots and backlog. Runtime artifacts, credentials, licensed plugin archives and database exports stay ignored/private.

Stop for visual review and the outstanding narrow access decision. Release and Fleet gates remain closed.
