# Landing Pages

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
