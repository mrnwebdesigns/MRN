# MRN Schema Bridge

Shared MU plugin for structured data normalization on MRN sites.

## Purpose

MRN Schema Bridge keeps shared schema policy out of child themes and out of site-specific patches. It lets the active SEO/schema plugin keep producing its normal graph, then applies MRN rules to remove internal implementation details and normalize public entity references.

## Version

Current version: `0.3.0`

## Features

- Removes internal or non-public WordPress author `Person` nodes from supported schema graphs.
- Replaces references to removed authors with the site organization when needed.
- Suppresses older base-stack business JSON-LD when a supported schema provider is active.
- Adds supplemental `Service` schema for configured service pages.
- Adds supplemental project/case-study `CreativeWork` schema for configured project post types.
- Adds a Tools > Schema Health admin screen for same-site sitemap scans.
- Keeps behavior filterable per site.
- No-ops unless a supported schema provider is active.

## Supplemental Schema

Service schema is opt-in by page ID through the `mrn_schema_bridge_service_page_ids` option or the `mrn_schema_bridge_service_page_ids` filter. The optional `mrn_schema_bridge_service_area_served` option/filter can add a simple `areaServed` value.

Project/case-study schema defaults to the shared `mrn_case_study` post type and can be changed with the `mrn_schema_bridge_project_post_types` filter.

## Schema Health

The Schema Health screen runs a capped, same-site crawl from a sitemap URL and reports:

- missing or malformed JSON-LD
- duplicate organization-like schema nodes
- organization schema missing logo/image data
- internal author `Person` nodes that still appear in rendered output
- HTTP status issues for sitemap URLs

The scan stores the last report in the `mrn_schema_bridge_schema_health_last_report` option. It does not rewrite pages, SEO metadata, or provider settings.

## Default Blocked Author Names

- `nethues`

## Filters

### Global

- `mrn_schema_bridge_enabled`
- `mrn_schema_bridge_schema_graph`
- `mrn_schema_bridge_supplemental_schema_enabled`
- `mrn_schema_bridge_supplemental_schema_nodes`
- `mrn_schema_bridge_schema_health_allowed_scan_url`
- `mrn_schema_bridge_schema_health_capability`
- `mrn_schema_bridge_schema_health_default_sitemap_url`
- `mrn_schema_bridge_schema_health_default_url_limit`
- `mrn_schema_bridge_schema_health_max_body_bytes`
- `mrn_schema_bridge_schema_health_max_sitemap_limit`
- `mrn_schema_bridge_schema_health_max_url_limit`
- `mrn_schema_bridge_schema_health_request_timeout`
- `mrn_schema_bridge_supported_schema_provider_loaded`
- `mrn_schema_bridge_suppress_legacy_business_schema_enabled`
- `mrn_schema_bridge_service_area_served`
- `mrn_schema_bridge_service_page_ids`
- `mrn_schema_bridge_service_schema_node`
- `mrn_schema_bridge_project_post_types`
- `mrn_schema_bridge_project_schema_node`

```php
add_filter( 'mrn_schema_bridge_enabled', '__return_false' );
```

### Author Person Nodes

- `mrn_schema_bridge_blocked_author_names`
- `mrn_schema_bridge_public_author_names`
- `mrn_schema_bridge_strip_non_public_author_person_nodes_enabled`
- `mrn_schema_bridge_organization_reference`

```php
add_filter(
	'mrn_schema_bridge_public_author_names',
	function ( $names ) {
		$names[] = 'Jane Public';
		return $names;
	}
);
```

```php
add_filter(
	'mrn_schema_bridge_blocked_author_names',
	function ( $names ) {
		$names[] = 'internal-dev-user';
		return $names;
	}
);
```

## QA Engine

Run plugin-scoped QA from this directory or with an explicit project root:

```bash
mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-schema-bridge
```

## QA Checklist

- `php -l mrn-schema-bridge.php` passes.
- Internal author `Person` nodes are removed from supported schema output.
- Public authors can be preserved with `mrn_schema_bridge_public_author_names`.
- Removed author references are replaced with the organization reference.
- Tools > Schema Health can run a same-site sitemap scan.
- The plugin produces no frontend output of its own.
