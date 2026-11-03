# Round 2 refinements — installed on the protected review copy

3 November 2026. The owner approved UX-011–UX-017 and subsequently authorized moving the pilot to mrndev.io. The current review copy is **https://admin-editor-pilot.mrndev.io**. Source remains in `codex/admin-editor-pilot-20261029`; Local Hub and the imported Trilliant source were preserved. Admin UI CSS **3.3.3**, Editor Lockdown **1.0.42**, semantic contract 1.1.0.

**Visual review can continue through the [32-capture paired gallery](round-3.html). The approved Cloudflare exception is applied; 19/32-section saves and incomplete-save protection pass, but save latency remains a blocker.** After explicit owner approval, the extra HTTP gate was removed from this review host only, following verified remote backup. Codex now has a verified native WordPress session as `mrn-admin`; the live dashboard is ready for annotations. WordPress authentication, HTTPS, noindex and WAF are unchanged. The gallery remains a static comparison artifact. New annotations start at UX-018 and remain collect-only until approved.

## Material editing changes

- **UX-011, UX-013:** an empty page shows Hero, Content and After Content Add entries in the outline and native region controls in the workspace. It explains how to add the first section, with unavailable arrangement/previous/next controls hidden. A first appended section enters focused editing; last removal restores the empty state after native save/reload. Page settings use tighter title spacing and the empty permalink slot loses its reserved height. Native populated permalink actions remain.
- **UX-012:** empty and arrangement regions are inset cards with 18px desktop / 14px narrow padding. Native ACF fields remain in their original form and retain lifecycle ownership.
- **UX-014, UX-015:** WPForms and SEOPress keep their actual SVG shapes, recolored through narrowly scoped semantic masks. AME labels, links and configuration remain authoritative.
- **UX-016:** one **Screen** toolbar menu contains the original Screen Options and Help controls. Native panels, form IDs and core handlers remain. Panels scroll below the toolbar; Escape closes them and returns focus. Page titles no longer share space with detached utility buttons.
- **UX-017:** labelled Light/Dark/System icon buttons live in the user dropdown. The existing per-user preference endpoint, nonce/capability checks, early theme marker, System CSS and failure rollback remain. Choosing the saved preference sends no request.

The migrated toolbar exposed a real compatibility conflict: Toolbar Editor had persisted the former appearance node's parent as `mrn-appearance`. Reusing that node ID under `user-actions` produced an orphan after its configuration pass. The account control now has the distinct ID `mrn-account-appearance`; this preserves AME ownership without editing its configuration, installing a provider contract or adding browser reconciliation work.

The native picker test found a second zero-row problem: the outline chose ACF's top “insert before first row” action, which has no insertion target on an empty field. Editor Lockdown 1.0.42 (`bc91c90f`) now chooses the field's native bottom append action, excluding top and nested clone controls. The [three-file installation receipt](refinements-round2/native-append-install.json) records the fresh verified remote backup and rollback. This changes one click-time selector and introduces no startup work, request, observer, provider contract or storage change. The 32 paired screenshots remain the 1.0.41 visual captures: this follow-up changes routing, not appearance.

## Verified and blocked checks

| Check | Result and limits |
| --- | --- |
| Migration/content integrity | Both 19- and 32-section drafts preserve draft status and complete native ACF/content hashes after URL canonicalization. A fresh database readback after successful native saves and the rejected incomplete submission still matches. [Evidence](refinements-round2/remote-integrity-readback.json). |
| Native save-integrity helper | Loaded and byte-identical to the existing source helper. Emergency disable and exact review-host PHP contracts pass. Both complex native Save Draft/reload checks and incomplete-save rejection now also pass. |
| Light/Dark/System account actions | Keyboard activation and persistence across navigation pass. The harness initially raced the native hover menu; corrected tests explicitly open, focus and close it. |
| Screen Options and Help | Light and Dark native panel opening, focus, Escape and focus return pass. No duplicate control implementation. |
| Accessibility | Zero WCAG A/AA violations in the scoped toolbar/sidebar scans and ten representative Light/Dark screen scans. Editor scope covers MRN workspace controls, not all native ACF/TinyMCE/vendor interiors. [Capture checks](refinements-round2/capture-results.json), [interaction summary](refinements-round2/runtime-summary.json). |
| Responsive captures | 390px dashboard, General settings, complex editor and empty workspace reflow checks pass; corresponding full-page screenshots retained. |
| Empty page | **Complete 0→1→0 passes:** choose Text in the installed MRN picker, native ACF append/select, Save Draft and explicit reload, remove the row, wait for native detachment, Save Draft/reload to zero. Both themes expose three Add routes and 390px reflow passes. [Saved first section](refinements-round2/native-append-saved.png), [saved empty state](refinements-round2/native-last-removal-saved.png), [database readback](refinements-round2/empty-final-readback.json). |
| Native Save Draft / reload / incomplete-save HTTP 400 | **19/32 integrity passes.** Native Save Draft plus explicit reload preserve every ACF FormData entry. The incomplete-form guard returns HTTP 400, and a fresh database readback preserves all fixture hashes. No content was published. [Results and timings](refinements-round2/native-save-results.json). |
| Source QA | Separate editor/admin logical commits and each subsequent patch passed staged MRN QA. Full-component PHP lint, security/WordPress checks and serial PHPStan pass; prior source reports retain their sandbox PHPStan warning rather than relabeling it. [Admin source report](refinements-round2/qa-mrn-admin-ui-css-source.md), [editor source report](refinements-round2/qa-mrn-editor-lockdown-source.md), [serial PHPStan](refinements-round2/phpstan-serial.txt). |
| Screenshots | [Manifest](refinements-round2/screenshots.json): 32 actual captures, both themes, same representative routes, 1487px desktop / 390px mobile. The gallery is open in Codex; its 32 images, local links, collect-only metadata, 1487/390px reflow and WCAG A/AA checks pass ([artifact checks](refinements-round2/gallery-checks.json)). It links genuine earlier captures where available. Before images retain their original date/viewport; no simulated before state. |

The owner approved the exact review-site exception on 3 November. The guarded apply matched custom rule version 8 and verified version 9, unchanged other custom rules/order and unchanged managed rules ([receipt](refinements-round2/waf-applied-receipt.json)). Only logged-in-cookie POSTs to this host's `/wp-admin/post.php` are exempted from that one custom challenge. Cookie presence is not authentication: the historical [access-boundary test](refinements-round2/access-boundary.json), before the separately approved HTTP-gate removal, confirmed that anonymous admin/static requests and a forged-cookie save POST require Basic Auth (HTTP 401); Basic Auth alone still leads to native WordPress sign-in.

The extra password prompt came from migration-owned Nginx directives, independent of CloudPanel's Basic Auth switch. The owner subsequently approved their removal so Codex could reach normal sign-in. The exact four-line change passed a fresh Updraft/S3 backup gate, hash checks, private rollback, Nginx validation and reload ([receipt](refinements-round2/review-http-gate-removal.json)). Codex reached native WordPress sign-in, authenticated with the existing account and displayed the dashboard. The review frontend/public assets no longer have the extra HTTP gate; WordPress admin authentication remains required. Separate command-line HEAD probes returned 403 and are not claimed as public-header verification.

## Performance

No new framework, plugin, asset request, polling loop, global MutationObserver or startup-wide field scan was introduced. Source growth is recorded in [asset sizes](refinements-round2/source-asset-sizes.json); source bytes are not a runtime performance pass. Existing event/reconciliation paths own empty-row behavior. Account actions send one existing request only on a changed preference.

The serial current-candidate run completed 21 page loads: one warm-up and three measured samples per theme for each 0/19/32-section fixture, with all native TinyMCE instances initialized. [Raw measurements](refinements-round2/review-performance.json) and [aggregates](refinements-round2/performance-summary.json) retain every sample.

| Sections | Browser startup CPU median, Light / Dark | Retained JS heap median, Light / Dark | Search response median, Light / Dark |
| --- | --- | --- | --- |
| 0 | 526 / 651 ms | 17.75 / 17.71 MB | 22 / 22 ms |
| 19 | 1786 / 1786 ms | 27.33 / 27.32 MB | 44 / 67 ms |
| 32 | 2281 / 2437 ms | 30.35 / 30.35 MB | 59 / 70 ms |

These are current-candidate observations, **not a performance pass or a matched regression comparison**. Fresh browser contexts, response buffering and disabled browser cache make measured wall time unsuitable as server TTFB; the final 32-section Dark load took 32.3 seconds despite 2.23 seconds of browser CPU. Other measured complex loads took 10.1–12.9 seconds. Long startup tasks reached 657 ms; this and the wall-time outlier require attribution before release. Idle CPU over two seconds had medians 6.8/7.0 ms (empty), 17.7/19.4 ms (19 sections), and 22.3/21.1 ms (32 sections). No JavaScript page errors were recorded. No polling or content mutation was used for measurements.

**Native save latency is a release blocker:** one serial native run measured 41,633 ms for 19 sections and 120,466 ms for 32 sections from click to POST response. Both returned HTTP 302 and preserved content. These are single current-candidate observations, not a matched regression comparison or proof that the redesigned UI caused the delay. Earlier 30-second harness limits timed out before slow saves completed. Attribute request upload, PHP/ACF/database work and remote request timing before changing editor lifecycle or infrastructure.

Matched prior/candidate CPU and startup-memory qualification remains open. Local Hub's retained forwarding-socket/OOM issue also remains unresolved; moving review does not repair or clear it.

## Isolation and rollback

The dedicated CloudPanel account/database, independent copied uploads, HTTPS, HTTP Basic Auth, noindex, disabled cron/mail/external HTTP and isolated S3 backup prefix were verified. Source login scripts/configuration and deployment identity were excluded or detached. Labeled database-only Updraft backups were verified before import, certificate activation, QA mutations and the account-menu correction. Component rollback copies remain outside the web root. The review access credential is in MRN-business / Production Hub / **MRN Admin Editor Pilot - Review access**; no secrets or database dumps are committed.

Operational provenance lives in [the committed exact-target migration](../../../../scripts/review/20261103-admin-editor-pilot/README.md). Source commits include editor `ea05be7c`, admin `eb68ea95`, exact review-host gate `fa7222dd`, and account-menu correction `502f097a`. No source promotion, release lock, packaging, publication, production-site deployment or Fleet activation occurred.

## Outstanding refinement and qualification queue

1. Attribute slow native saves. The combined 0→1→0 workflow, approved exact-site WAF exception, 19/32 integrity and incomplete-save guard are verified; functional passes do not clear performance qualification.
2. Live Codex access is resolved with the owner-approved HTTP-gate removal and verified native sign-in. Continue live annotations; preserve WordPress authentication.
3. Complete matched prior/candidate CPU/startup-memory qualification and the separately owned Local Hub forwarding repair.
4. Review the implemented visuals with an MRN editor. Retain new feedback as collect-only UX-018 onward.
5. Imported off-origin AME branding points to `trilliant.localhost` and is blocked/missing in remote captures. The newly visible backup-plugin icon also retains native low-contrast branding. Record these for approved narrow follow-up; do not alter AME configuration or vendor internals here.
6. Updraft remote backup jobs verify successfully. Premium advanced-retention capability has not been independently qualified; do not call the environment fully backup-policy compliant on settings alone.

## Changed components/files

- Admin UI CSS: `appearance.php`, `appearance.js`, `mrn-admin-shell.css`, `mrn-editor-workspace.css`, `mrn-modern-admin.css`, component header/lock, and targeted runtime harnesses.
- Editor Lockdown: `assets/section-navigator.js`, `includes/section-navigator.php`, component header/lock, host-gate test and guarded empty fixture helper.
- Exact-target scripts in `scripts/review/20261103-admin-editor-pilot/`, review records, screenshots and backlog. Runtime artifacts, credentials, licensed plugin archives and database exports stay ignored/private.

The corrected empty-page saves took 3.78s (first section) and 2.86s (last removal) from click to POST response in the passing run. Earlier failed runs remain documented in [harness findings](refinements-round2/harness-findings.json), including the real native top-action routing defect. Existing picker cancellation cleanup needs a separately targeted follow-up: the Escape/synthetic-append diagnostic left ACF's cancel listener, consuming the next click. No general picker rewrite was made.

Source and the final evidence snapshot passed staged MRN QA; JavaScript syntax, PHP lint and diff checks pass. Targeted remote browser/API-save/accessibility checks are reported above. Full release, Fleet parity, fleet distribution and broader browser/provider compatibility were intentionally skipped because this remains a bounded review iteration.

Stop for visual review. Release and Fleet gates remain closed.
