# MRN Public Security Hardening

Shared MU plugin for public-facing security hardening on normal MRN brochure and client sites.

## Features

- Redirects public author archives to the site home URL by default.
- Adds a noindex fallback for author archives when redirects are disabled.
- Removes `author_name` and `author_url` from oEmbed responses.
- Returns early REST `rest_forbidden` responses for unauthenticated write requests to known admin-only scanner-noise routes.
- Serves `/.well-known/security.txt` from WordPress/plugin logic.
- Protects the stack-owned `/uptimerobot-check/` health page with `noindex`, `nofollow`, `noarchive`, an `X-Robots-Tag` response header, a `robots.txt` disallow rule in the first wildcard crawler block for Cloudflare compatibility, and core sitemap exclusion.
- Adds a read-only admin status page at `Advanced > Public Security` when the MRN Advanced admin menu is available.

## Admin Status Page

The status page shows the current filtered state for:

- plugin/version loading
- author archive redirect and noindex fallback
- oEmbed author field stripping
- guarded REST routes, methods, and allowed capabilities
- generated `security.txt` fields
- UptimeRobot check-page slug and page presence

It also includes a copy button for the per-site rollout prompt. The page does not save options; site-specific changes should still be handled with filters or site-local configuration.

The admin menu label intentionally omits `MRN`. By default, the page is placed under the Admin Menu Editor top-level item titled `Advanced`; sites without that parent fall back to WordPress Tools unless a filter supplies a different parent.

## Default REST Guarded Routes

- `/smartcrawl/v1/instant-indexing`
- `/wpmudev-dashboard/v1/plugins/action`

The REST guard runs on `rest_authentication_errors` so it can return a `401` before route required-parameter validation runs. Users with `manage_options` or `manage_network_options` are allowed through.

## QA Engine

Run plugin-scoped QA from this directory or with an explicit project root:

```bash
mrn-qa run --project-root /Users/khofmeyer/Development/MRN/mu-plugins/mrn-public-security-hardening
```

The committed `.mrn-qa.env`, `stack.lock`, `STACK_BASELINE.md`, `phpcs.xml.dist`, `phpstan.neon.dist`, and Semgrep config make this shared MU plugin scannable as a standalone security plugin while keeping browser/runtime checks tied to explicit site QA.

## Filters

### Admin Page

- `mrn_public_security_admin_capability`
- `mrn_public_security_admin_parent_slug`
- `mrn_public_security_admin_page_title`
- `mrn_public_security_admin_menu_title`

```php
add_filter(
	'mrn_public_security_admin_parent_slug',
	function () {
		return '#ame-unclickable-menu-item-1';
	}
);
```

### Author Archives

- `mrn_public_security_author_archive_redirect_enabled`
- `mrn_public_security_author_archive_redirect_target`
- `mrn_public_security_author_archive_redirect_status`
- `mrn_public_security_author_archive_noindex_enabled`

```php
add_filter( 'mrn_public_security_author_archive_redirect_enabled', '__return_false' );

add_filter(
	'mrn_public_security_author_archive_redirect_target',
	function () {
		return home_url( '/about/' );
	}
);
```

### oEmbed

- `mrn_public_security_oembed_strip_author_enabled`

```php
add_filter( 'mrn_public_security_oembed_strip_author_enabled', '__return_false' );
```

### REST Guard

- `mrn_public_security_rest_guard_enabled`
- `mrn_public_security_guarded_rest_routes`
- `mrn_public_security_guarded_rest_methods`
- `mrn_public_security_guarded_rest_capabilities`
- `mrn_public_security_rest_guard_error_message`

```php
add_filter(
	'mrn_public_security_guarded_rest_routes',
	function ( $routes ) {
		$routes[] = '/vendor/v1/admin-only-action';
		return $routes;
	}
);
```

### security.txt

- `mrn_public_security_security_txt_enabled`
- `mrn_public_security_security_txt_contact_email`
- `mrn_public_security_security_txt_contact`
- `mrn_public_security_security_txt_expires`
- `mrn_public_security_security_txt_canonical`
- `mrn_public_security_security_txt_policy_url`
- `mrn_public_security_security_txt_default_policy_path`
- `mrn_public_security_security_txt_fields`
- `mrn_public_security_security_txt_content`

```php
add_filter(
	'mrn_public_security_security_txt_contact_email',
	function () {
		return 'security@example.org';
	}
);

add_filter(
	'mrn_public_security_security_txt_fields',
	function ( $fields ) {
		$fields['Preferred-Languages'] = 'en';
		return $fields;
	}
);
```

### UptimeRobot health check

- `mrn_public_security_uptime_robot_check_slug`

The default slug is `uptimerobot-check`. Stack bootstrap creates and publishes that page, and UptimeRobot monitors its permalink rather than the homepage.

## QA Checklist

- `php -l mrn-public-security-hardening.php` passes.
- `/.well-known/security.txt` returns `200` with `text/plain`.
- `/author/{username}/` redirects to `home_url( '/' )` by default.
- oEmbed response data does not include `author_name` or `author_url`.
- Unauthenticated `POST {}` requests to guarded REST routes return `401 rest_forbidden`, not a missing-parameter `400`.
- Authenticated administrators can still reach the guarded REST routes for normal plugin handling.
- `/uptimerobot-check/` returns an `X-Robots-Tag: noindex, nofollow, noarchive` header, is disallowed in `/robots.txt`, and is absent from the core page sitemap.
