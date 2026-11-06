# Landing Pages

Landing Pages are paid-ad destinations. Their SEO policy is `noindex`, with
normal crawling allowed so search engines can read the directive and AdsBot can
verify destinations. Do not add a robots.txt disallow for these URLs.

`inc/landing-page-seo.php` supplies SEOPress defaults for singles, the optional
archive and dedicated categories/tags, excludes them from XML/HTML and core
sitemaps, and sends an `X-Robots-Tag: noindex` fallback. SEOPress automatic
IndexNow submissions skip this CPT while its normal SEO editor remains available.
The CPT is excluded from default site search and the menu chooser. Explicitly
configured third-party search engines can override a WordPress CPT exclusion;
check those engines when adopting this policy. Archive exposure remains an
independent control and does not enable organic indexing.

Campaign UTM/click parameters must survive the landing request. Retain the
site's consent controls and implement campaign-specific conversion tracking only
after the actual measurement IDs and success event are agreed. `noindex` is not
access control: unpublished review content must remain draft/private.

`landing_page` is a theme-owned CPT. It uses the existing Hero, Content and After
Content builders and the shared page presentation. The separate Landing Page
template for ordinary WordPress Pages remains available.

- Landing Pages have dedicated `landing_page_category` and `landing_page_tag`
  taxonomies. They do not share the blog's categories or tags.
- Standalone URLs use `/landing-pages/<slug>/`.
- Archives are off by default. Site Configurations → Admin → Content Types →
  Landing Page Archives enables the CPT, category and tag archives together.
- Existing Content Only and Disabled controls are authoritative. Content Only
  retains editing and explicit component queries while removing public pages and
  archives. Disabled also removes the UI and REST registration. Neither deletes
  records or terms. Hiding a menu is a separate presentation setting.
- The archive preference survives Content Only/Disabled transitions. Changes
  rebuild rewrites once on the next request; query-string and stale archive
  requests cannot bypass the off switch.

The controls require the current Config Helper extension hook and the shared
`mrn-admin-data-post-types` MU component. Existing hybrid sites can load the exact
module through a site-owned compatibility loader and retain their page shell until the
canonical parent is adopted. Do not replace an older parent theme wholesale just
to add this content type. Record source commits, installed checksums and the
dependency versions, and pass backup/QA/environment gates before installation.

Introducing the CPT does not convert ordinary pages, publish content, change
menus, or exempt future migration refreshes from preserving new Landing Pages.
