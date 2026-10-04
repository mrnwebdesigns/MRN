# Platform SEOPress consent qualification

- Date: 2026-09-24
- Target: `https://platform.localhost/` and `/about/`

## Result

**Promising for initial consent; not yet a replacement that satisfies the MRN
no-tracking-after-withdrawal requirement.** Fresh visits and initial Decline were
quiet at all three tested viewport sizes. Acceptance enabled the configured
Google tag and produced one observed GA4 page view per tested page. Withdrawal
updated consent to denied, but the previously loaded tag emitted further requests
at the subsequent navigation/reload boundary. Existing tracking cookies remained.

The test did not install, update, deactivate, or edit components, and did not
change WordPress consent/tracking settings or content. Browser-only consent
choices were made in isolated contexts, which were closed afterward. The tests
used real network requests; no tracker was mocked or blocked. Test interactions
therefore reached the configured analytics tag.

## Runtime identity

Runtime WP-CLI confirmed the exact local home URL and:

| Component | Version | State |
| --- | --- | --- |
| SEOPress | 10.1 | Active |
| SEOPress PRO | 10.1.1 | Active |
| MRN Cookie Consent | 1.1.40 | Installed, inactive |
| MRN GTM Injector | 1.0.13 | Installed, inactive |
| Google Site Kit | — | Not installed |

The active theme is `mrn-base-stack-child`, with parent `mrn-base-stack`.
SEOPress is configured for GA4 `G-RTZD6VL2FD`, and the consent lifetime is 30 days.
No GTM container was configured in the inspected tracking settings or observed
in the rendered output. Loading `gtag/js` from googletagmanager.com is not evidence
of a GTM container. These results do not qualify GTM or independently injected
third-party scripts.

The free SEOPress 10.1 files passed strict WordPress.org checksum verification:
`Success: Verified 1 of 1 plugins.` Premium PRO files were not checksum-verified.
This qualifies the named local configuration only, not a newer SEOPress release,
every Stack site, or legal compliance. See [runtime inventory](runtime-inventory.json).

## Observed behavior

Fresh isolated Chromium contexts used 1440×1000, 768×1024, and 390×844 viewports.
Desktop and mobile ran the full sequence; tablet covered fresh visit, interaction,
Decline, and reload. An independent desktop CDP run tested acceptance directly
from a fresh visit to `/about/`, then withdrawal and navigation to `/`.

| Check | Observation | Assessment |
| --- | --- | --- |
| Fresh visit, no stored choice | Zero observed external tracking requests; no cookies | Pass in all three viewports |
| Scrolling before choosing | No tracking requests | Pass in all three viewports |
| Initial Decline | Banner closes; only the SEOPress decline cookie is set; no tracking | Pass in all three viewports |
| Reload after initial Decline | Choice persists; no tracking requests | Pass in all three viewports |
| Reopen preferences | Banner reopens and permits a new choice | Pass on desktop/mobile |
| Accept | One `gtag/js` load and one observed GA4 `page_view`; additional advertising/DoubleClick requests | Functional pass, consent scope needs review |
| Reload with acceptance stored | One new GA4 page view for the new document; ordinary prior-document engagement can also be sent | No duplicate page view observed |
| Withdraw after acceptance | SEOPress removes its acceptance cookie, stores Decline, and pushes all four Google consent fields to denied | Consent signal updates correctly |
| Reload/navigate after withdrawal | Google requests still emitted by the already-loaded tag around the navigation boundary | Fail against the strict no-request requirement |
| Tracking-cookie cleanup | `_ga`, `_ga_RTZD6VL2FD`, `_gcl_au`, and third-party `IDE` remained in the test browser | Gap; a denied signal does not remove existing cookies |
| Subsequent navigation after the declined reload | No tracking requests observed on `/about/` | Fresh declined document becomes quiet |

The main run observed 12 stages on desktop, four on tablet, and 12 on mobile.
Observations generally waited 4.5–7 seconds after each action, plus a separate
six-second post-withdrawal interaction window. This is bounded observation, not
proof about every possible timer, browser, or tag configuration.

### Withdrawal evidence

Desktop reload after withdrawal emitted:

```text
POST https://www.google-analytics.com/g/collect
tid=G-RTZD6VL2FD
en=user_engagement
gcs=G100
```

The separate CDP run reproduced this when navigating from `/about/` to `/`:
the request's `documentURL` was still `/about/` and its initiator was `script`.
This supports an outgoing-document request from the previously loaded tag, rather
than assuming the new declined page was independently starting analytics.

The mobile reload boundary also emitted tag/diagnostic requests to
`googletagmanager.com/gtag/js` and `/td`. The main run did not record CDP document
attribution for those extra mobile requests; their exact origin needs investigation.
Both desktop and mobile emitted the denied-state engagement request.

Evidence: [network stages](network-results.json),
[independent fresh-accept/CDP run](fresh-accept-cdp.json).

### Consent scope and interface

- Acceptance grants `analytics_storage`, `ad_storage`, `ad_user_data`, and
  `ad_personalization` together. Google Ads/DoubleClick requests were observed,
  despite the banner text referring to analytics and improving the experience.
  Align the intended tracking behavior and the consent wording/categories before
  adopting this as a Stack default. No separate category choices were available
  in this configuration.
- The preference-reopen button works, but its saved label is **Nom Nom**. Use an
  understandable label such as **Cookie preferences** in the proposed defaults.
- The announcement modal covers the consent controls on initial homepage load.
  The first automated Decline click timed out because the announcement backdrop
  intercepted it. The completed test dismissed the announcement through its
  normal **Close announcement details** button; no CSS or settings were altered.
  Resolve the order/layering of those two interfaces before launch.
- The banner fits each tested viewport. Its heights were 82 px desktop, 204 px
  tablet, and 252 px mobile. Accept and Decline remain visible on mobile.

Screenshots: [desktop](desktop-fresh.png), [tablet](tablet-fresh.png),
[mobile](mobile-fresh.png), [initial announcement overlap](desktop-announcement-overlap.png).

## Accessibility, API, and performance

Consent-scoped axe WCAG A/AA scans reported zero violations, with incomplete
contrast checks for the banner text/link. Computed styles and visual inspection
were used to review those: message/white contrast was 10.98:1, link and Accept
contrast 17.40:1, and Decline contrast 10.31:1. These values describe the inspected
mobile styles, not every hover/focus/theme variation.

Keyboard traversal reached the privacy link, Accept, Decline, and the reopen
button. Keyboard Enter successfully accepted consent. Decline displayed a visible
3 px focus outline. Tab subsequently entered the dimmed background despite the
backdrop; review the intended modal/focus behavior as part of the UI integration.
These checks do not constitute full accessibility signoff.

The [MRN QA Engine report](mrn-qa.md) records:

- REST index: HTTP 200, pass.
- Public browser smoke on `/` and `/about/`: pass.
- Page timing: pass against configured 2-second TTFB / 5-second total thresholds.
- Accessibility: homepage passed; `/about/` failed on two existing links lacking
  accessible names, both targeting `/dummy-content/select-link/`. These are page
  content findings, not failures of the consent buttons.
- The Engine also ran its Stack Config Helper parity advisory, which reported
  unrelated site drift. It is not evidence about this local consent replacement.
- PHP/code-change checks were skipped because no executable project code changed;
  PHPCBF was disabled. Core Web Vitals auto-skipped because there was no triggering
  code change. No release/deployment signoff was attempted.

Engine overall result was **Fail/Blocked**. Preserve that status; the narrower
consent findings above must not be described as a full QA pass.

The Engine used the isolated planning worktree as its project root to keep source
analysis bounded, with the runtime explicitly set to Platform:

```sh
MRN_QA_SAMPLE_PATH=/about/ mrn-qa run \
  --project-root /Users/khofmeyer/Development/MRN-task-worktrees/stack-simplification-plan-20260924 \
  --profile wordpress-site --scope site-only \
  --site-path /Users/khofmeyer/Development/MRN-sites/platform/public \
  --site-url https://platform.localhost \
  --run-api always --run-smoke always --run-accessibility always \
  --run-performance always --run-phpcbf never --run-cwv auto \
  --smoke-scope public --playwright-provider engine \
  --output-file /Users/khofmeyer/Development/MRN-task-worktrees/stack-simplification-plan-20260924/stack/review-evidence/2026-09-24-platform-consent/mrn-qa.md
```

## Consequence for the cleanup plan

Keep Platform as the local consent qualification target. Initial opt-in behavior
supports continuing the SEOPress evaluation, but do not treat MRN Cookie Consent
or GTM Injector retirement as qualified yet.

Next, assess the selected supported SEOPress release/settings against the observed
withdrawal and consent-scope gaps. Any version/configuration or integration change
needs its own approved scope and repeat test. Do not patch vendor files as a
shortcut. Test an explicitly approved GTM setup separately if GTM remains a Stack
requirement. Preserve the old MRN implementations and avoid modifying other sites
on the strength of this canary alone.
