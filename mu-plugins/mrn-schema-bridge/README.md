# MRN Schema Bridge

Shared MU plugin for structured data normalization on MRN sites.

## Purpose

MRN Schema Bridge keeps shared schema policy out of child themes and out of site-specific patches. It lets the active SEO/schema plugin keep producing its normal graph, then applies MRN rules to remove internal implementation details and normalize public entity references.

## Version

Current version: `0.4.4`

## Features

- Removes internal or non-public WordPress author `Person` nodes from supported schema graphs.
- Replaces references to removed authors with the site organization when needed.
- Suppresses older base-stack business JSON-LD when a supported schema provider is active.
- Adds supplemental `Service` schema for configured service pages.
- Enriches or adds `ContactPage` schema for configured contact pages.
- Adds supplemental project/case-study `CreativeWork` schema for configured project post types.
- Adds `ProfilePage`/`Person` schema for public team-member profiles.
- Adds `ImageGallery`/`CollectionPage` schema from visible gallery items.
- Adds render-aware `Quotation` schema only for testimonials visibly output by the base theme.
- Supports schema-only post descriptions through hidden post meta or a site option map.
- Adds an SEO & Schema Classic Editor panel for page intent and description overrides.
- Enriches active-provider organization identity from the canonical Business Information screen.
- Isolates SmartCrawl compatibility for legacy sites where SmartCrawl remains the selected provider.
- Publishes separate robots.txt policies for AI search/retrieval and model-training crawlers.
- Adds a Tools > Schema Health admin screen for same-site sitemap scans.
- Keeps behavior filterable per site.
- Merges supplemental nodes into supported provider graphs when available and emits a standalone supplemental graph only when needed.

## Schema Ownership

- Business Information owns public organization identity, address, phone, hours, logo, and schema policy.
- SEOPress owns the base `WebSite`, `WebPage`, breadcrumb, article, sitemap, canonical, and social graph on standardized sites. SmartCrawl remains a migration-only fallback when it is still the selected provider.
- MRN Schema Bridge enriches and normalizes that graph and owns stack-specific CPT/page mappings.
- Theme components supply visible source data and do not print competing site-wide schema when a supported provider is active.

Rendered testimonials are the exception to head-time graph merging: dynamic Content rows are not resolved until the page body renders, so the base theme reports visible testimonial quotes to the bridge and the bridge emits one deduplicated `Quotation` graph in the footer. It never converts testimonials into `Review`, `Rating`, or `AggregateRating` entities.

The Business Information > Identity & Schema tab controls organization type, legal/alternate name, public email, area served, coordinates, author policy, and AI crawler policy.

The post editor's SEO & Schema panel defaults to Auto. Use the page-intent override only for About, Collection, Contact, Profile, or Service pages. The None option disables bridge-owned supplemental schema without removing the active provider's safe base WebPage graph.

## Supplemental Schema

Service schema is opt-in by page ID through the `mrn_schema_bridge_service_page_ids` option or the `mrn_schema_bridge_service_page_ids` filter. The optional `mrn_schema_bridge_service_area_served` option/filter can add a simple `areaServed` value.

ContactPage schema is opt-in by page ID through the `mrn_schema_bridge_contact_page_ids` option or the `mrn_schema_bridge_contact_page_ids` filter. When the active schema provider already outputs a `ContactPage`, the bridge enriches that node instead of adding a duplicate. Organization contact points can be supplied through the `mrn_schema_bridge_contact_points` option/filter as a single contact point object or a list of objects. Supported contact point fields are `contactType`, `email`, `telephone`, `url`, `areaServed`, and `availableLanguage`.

Schema descriptions use the first available value from:

1. Hidden post meta named `_mrn_schema_bridge_schema_description`.
2. The `mrn_schema_bridge_post_schema_descriptions` option, keyed by post ID or post slug.
3. The WordPress excerpt.
4. Trimmed post content.

Project/case-study schema defaults to the base-stack `case_study` post type and retains `mrn_case_study` compatibility. It can be changed with the `mrn_schema_bridge_project_post_types` filter.

## Provider Rollout Defaults

When SmartCrawl is the selected provider, existing values are preserved and missing defaults are filled once per bridge release:

- sitemap, title/meta, social, instant indexing, SEO analysis, and readability modules
- organization site representation and canonical Business Information name/logo
- schema archive support and test controls
- disabled author/date/search/comment/audio/video schema unless explicitly configured
- SmartCrawl XML sitemap ownership, automatic regeneration, and stylesheet

MRN SEO Helper remains the owner of public post-type title and meta-description templates.

When both providers are loaded and MRN SEO Helper does not select one explicitly, Schema Bridge prefers SEOPress. SmartCrawl graph normalization, option overlays, and defaults stop running as soon as SEOPress is authoritative. The legacy module remains packaged for migration rollback and SmartCrawl-only sites.

## AI Crawler Policy

The virtual WordPress robots.txt allows AI search/retrieval crawlers by default and blocks model-training crawlers by default on public sites. Business Information can change either policy independently.

Search/retrieval agents: `OAI-SearchBot`, `Claude-SearchBot`, `Claude-User`.

Training agents: `GPTBot`, `ClaudeBot`, `Google-Extended`.

The bridge does not publish `llms.txt`.

## Schema Health

The Schema Health screen runs a capped, same-site crawl from a sitemap URL and reports:

- missing or malformed JSON-LD
- duplicate organization-like schema nodes
- organization schema missing logo/image data
- internal author `Person` nodes that still appear in rendered output
- missing canonical links and sitemap URLs carrying `noindex`
- missing core properties for Service, Article, ProfilePage, and LocalBusiness nodes
- HTTP status issues for sitemap URLs

The scan stores the last report in the `mrn_schema_bridge_schema_health_last_report` option. It does not rewrite pages, SEO metadata, or provider settings.

## Default Blocked Author Names

- `nethues`

## Filters

### Global

- `mrn_schema_bridge_enabled`
- `mrn_schema_bridge_schema_graph`
- `mrn_schema_bridge_author_policy`
- `mrn_schema_bridge_allowed_organization_types`
- `mrn_schema_bridge_business_schema_setting`
- `mrn_schema_bridge_canonical_organization_properties`
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
- `mrn_schema_bridge_legacy_smartcrawl_compatibility_enabled`
- `mrn_schema_bridge_contact_page_ids`
- `mrn_schema_bridge_contact_page_schema_node`
- `mrn_schema_bridge_contact_points`
- `mrn_schema_bridge_post_schema_description_meta_key`
- `mrn_schema_bridge_post_schema_descriptions`
- `mrn_schema_bridge_post_schema_description`
- `mrn_schema_bridge_service_area_served`
- `mrn_schema_bridge_service_page_ids`
- `mrn_schema_bridge_service_schema_node`
- `mrn_schema_bridge_project_post_types`
- `mrn_schema_bridge_project_schema_node`
- `mrn_schema_bridge_profile_page_schema_node`
- `mrn_schema_bridge_gallery_schema_node`
- `mrn_schema_bridge_testimonial_schema_node`
- `mrn_schema_bridge_rendered_testimonial_schema_nodes`

`mrn_schema_bridge_supplemental_schema_nodes` receives the current `WP_Post` as its second argument, or `null` when no singular post context is available.

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
- `php tests/contract-regression.php` passes.
- Internal author `Person` nodes are removed from supported schema output.
- Public authors can be preserved with `mrn_schema_bridge_public_author_names`.
- Removed author references are replaced with the organization reference.
- Tools > Schema Health can run a same-site sitemap scan.
- Supplemental nodes are merged into supported provider output when available and otherwise print as one JSON-LD graph.
- `robots.txt` reflects the configured AI search and training crawler policy on public sites.
- Auto, About, Contact, Service, Profile, Gallery, and Case Study templates render the expected graph without duplicate entity IDs.
- Only testimonials whose quote text is visibly rendered produce `Quotation` nodes; repeated output is deduplicated and no review/rating types are emitted.
