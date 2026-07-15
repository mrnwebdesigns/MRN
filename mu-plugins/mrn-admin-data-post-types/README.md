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
`publicly_queryable=false`, `exclude_from_search=true`,
`show_in_nav_menus=false`, `has_archive=false`, `rewrite=false`, and
`query_var=false`. Admin UI and menu visibility default to enabled and can be
configured per CPT. The optional cleanup defaults to enabled and removes
View/Preview row actions, preview URLs, and sample permalink markup.

This does not alter `WP_Query`, `get_posts()`, REST controller permissions, or
post capabilities. Components can continue querying explicitly with
`post_type => 'testimonial'`; the CPT simply has no public WordPress route.

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
