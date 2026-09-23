# Frontend asset performance

Load assets according to the components a page renders and the states visitors
can reach. A plugin being active does not mean every page needs all its assets.
Keep these decisions in the owner of the markup: Stack components in the parent
theme, custom site components in the child theme. Do not globally remove a
plugin's styles on the strength of one homepage audit.

## Qualifying a conditional removal

1. Capture the actual public HTML, asset URLs and versions, mobile and desktop
   Lighthouse reports, and CSS coverage. Inventory browser requests as well as
   WordPress's enqueue queue: a dependency can restore a dequeued stylesheet.
2. Inspect the rules that coverage says were used. Root custom properties and
   accessibility utilities still count; establish which retained stylesheet
   supplies them. Coverage during initial load alone does not establish safety.
3. Test the candidate in an isolated browser before changing the server. Compare
   computed styles and geometry across the entire page at phone, tablet, and
   desktop widths. Exercise menus, hover/focus, carousels, forms, and any dialogs
   that can appear on that page. Preserve reduced-motion and no-JavaScript
   behavior where relevant.
4. Keep the allow-list narrow and conditional. Verify native shop, category,
   product, cart, checkout, and account routes retain their dependencies.
5. Run the scoped MRN QA checks and accessibility checks. Deploy the exact small
   patch after a verified remote database backup, retain a file rollback, clear
   the page cache, and read back both file hashes and public HTML.
6. Compare fresh Google PSI reports and repeat when timing variance obscures the
   result. Record bytes and request counts separately from score changes. A
   smaller payload is not proof of a particular LCP or score improvement.

## Measurement discipline

- Change one behavior per experiment. Do not combine stylesheet removal,
  deferred scripts, image changes, and server configuration in one comparison.
- Use the same viewport, browser, throttling, HTML source, and interception
  method in each local comparison. Run Lighthouse serially. A normal live
  request is not directly comparable to a replay that intercepts every asset.
- Keep rejected experiments. Loading form styles asynchronously, for example,
  should not be shipped merely because a report lists those files as blocking.
- Keep real-user metrics separate from lab metrics. PSI field data covers a
  rolling period and will not immediately reflect a deployment.
- Do not postpone spam protection, wishlist state, consent, or other required
  behavior just to improve an initial-load score.

## Gloves Online example, September 2026

The custom homepage uses child-theme product cards. Browser coverage found that
the three core WooCommerce stylesheets contributed only shared color variables
and a screen-reader utility already provided by retained styles. An isolated
comparison at 412, 768, and 1366 CSS pixels found no differences in visible
element geometry or the sampled computed style properties, including the
newsletter and open mobile menu. Full-page axe WCAG A/AA checks had no violations.

The site-specific change adds `woocommerce-general`, `woocommerce-layout`, and
`woocommerce-smallscreen` to the existing homepage-only dequeue list. It saves
three blocking requests and about 16 KiB in the local capture (17.3 KiB in the
Google capture). It does not remove WooCommerce scripts or styles on other
routes. Other Stack sites must establish their own component and dependency
contract before using the same allow-list.

Source: Gloves child-theme commit `7339742`. The initial-render navigation
repair is documented separately in [Mobile navigation first paint](MRN-MOBILE-NAVIGATION-FIRST-PAINT.md).
