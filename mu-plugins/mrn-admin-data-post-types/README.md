# MRN Admin Data Post Types

Shared stack contract for custom post types that are editable content records,
not public destinations. Typical candidates include testimonials,
announcements, cards, locations, people, logos, and similar records rendered by
another page or component.

Do not use this contract when a CPT needs single URLs, archives, public REST
discovery, or native search/navigation visibility.

## Usage

Register the CPT normally, then select its key from theme or plugin code:

```php
add_filter(
	'mrn_admin_data_post_types',
	function ( $post_types ) {
		$post_types[] = 'testimonial';
		$post_types['announcement'] = array(
			'show_ui'       => true,
			'show_in_menu'  => true, // A parent menu slug is also accepted.
			'admin_cleanup' => true,
		);

		return $post_types;
	}
);
```

The registration filter enforces `public=false`,
`publicly_queryable=false`, `show_in_nav_menus=false`, `has_archive=false`,
`rewrite=false`, and `query_var=false`. Admin UI and menu visibility default to
enabled and can be configured per CPT. The optional cleanup defaults to enabled
and removes View/Preview row actions, preview URLs, and sample permalink
markup.

`exclude_from_search` defaults to `true`, but flips to `false` automatically
for a CPT that has a configured search-result destination — see
[Search-result destination](#search-result-destination-engine-agnostic) below.

This does not alter `WP_Query`, `get_posts()`, REST controller permissions, or
post capabilities. Components can continue querying explicitly with
`post_type => 'testimonial'`; the CPT simply has no public WordPress route.

## Admin-selected CPTs (no code required)

Administrators can also mark a CPT admin/data-only from
**Site Configurations -> Admin -> Content Types** in the `mrn-config-helper`
plugin, which lists every registered CPT automatically — no filter code
needed, including for CPTs added by future plugins. That screen stores its
selection in the `mrn_admin_data_post_types_manual` option via
`mrn_admin_data_post_types_set_manual_selection()`, read back through
`mrn_admin_data_post_types_get_manual_selection()`.

A CPT declared through the `mrn_admin_data_post_types` filter always takes
precedence over an admin-selected entry for the same post type — code-level
declarations can't be turned off from the UI. Use
`mrn_admin_data_post_types_get_code_declared()` to check whether a given CPT
is locked in code.

## Search-result destination (engine-agnostic)

By default a content-only CPT never surfaces in search results at all
(`exclude_from_search=true`) — there's no page to link to. Administrators can
optionally set a destination page per post type from
**Site Configurations -> Admin -> Content Types -> Content Only Search
Destination** in `mrn-config-helper`, for content that's genuinely displayed
somewhere (e.g. a `team_member` CPT shown on a "Team" page). When a
destination is configured for a CPT, `exclude_from_search` is automatically
switched to `false` for it, so WordPress's own search query includes it
(`WP_Query` expands an unset `post_type` during a search to every post type
with `exclude_from_search=false`, independent of `public`).

This intentionally does not assume or integrate with any specific search
plugin (native WP search, SearchWP, Relevanssi, etc.) — it only makes the
matching decision possible and exposes one resolver function any search
implementation can call for the correct link:

```php
$url = function_exists( 'mrn_admin_data_post_types_get_search_destination_url' )
	? mrn_admin_data_post_types_get_search_destination_url( $post->post_type )
	: '';

if ( '' === $url ) {
	$url = get_permalink( $post ); // Normal post types, or CPTs with no destination configured.
}
```

Other relevant functions: `mrn_admin_data_post_types_get_search_destinations()`
(full post_type => page_id map), `mrn_admin_data_post_types_has_search_destination(
$post_type )` (cheap bool check, safe during CPT registration), and
`mrn_admin_data_post_types_set_search_destination( $post_type, $page_id )`
(`$page_id = 0` clears an entry). The resolved URL passes through the
`mrn_admin_data_post_types_search_destination_url` filter for
site-specific overrides.

The base theme (`mrn-base-stack`) is the first adopter:
`mrn_base_stack_get_search_result_permalink()` in `inc/template-tags.php` calls
the resolver and falls back to `get_permalink()`, used by
`template-parts/content-search.php` and the search-context branch of
`mrn_base_stack_post_thumbnail()`. Every on-site search result — whether
matched by native WP search or a SearchWP-seeded form (which submits back to
this same search results page) — renders through that one template, so fixing
the link there covers both without assuming which engine matched the post.

## Current stack adopters

- The base theme's `testimonial` CPT.
- Every CPT returned by the MRN Reusable Block Library's filtered post-type
  definitions. This includes future reusable block types added through that
  library contract.

## Deployment note

After changing an existing CPT from public to admin/data-only, flush rewrite
rules once in each environment (for example by re-saving Permalinks or using
`wp rewrite flush`). Also clear page/object caches and regenerate or clear XML
sitemap caches so stale URLs disappear.
