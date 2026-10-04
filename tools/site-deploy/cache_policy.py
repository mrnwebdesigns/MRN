"""Narrow Dev cache adapter: prove HTML is uncached; never purge unrelated data."""
import json
from pathlib import Path
from urllib.parse import urlsplit
import urllib.request


def canonical_pages(site_url, pages):
    site = urlsplit(site_url)
    if site.scheme != 'https' or not site.hostname or site.username or site.password or site.query or site.fragment:
        raise ValueError('Expected canonical HTTPS site identity')
    if not isinstance(pages, list) or not pages or len(pages) > 500:
        raise ValueError('Provide a bounded, nonempty affected HTML URL list')
    if len(set(pages)) != len(pages):
        raise ValueError('Duplicate affected HTML URL')
    for page in pages:
        parsed = urlsplit(page)
        if (parsed.scheme != site.scheme or parsed.netloc != site.netloc or parsed.query or parsed.fragment
                or parsed.username or parsed.password or not parsed.path.startswith('/')
                or '..' in parsed.path.split('/') or '\\' in parsed.path):
            raise ValueError('Affected HTML URL differs from the exact site or uses a bypass query')
    return pages


def verify_cloudpanel_origin(settings_path, wordpress):
    """Read the site-owned provider settings plus freshly queried WP cache state."""
    settings_path = Path(settings_path)
    if settings_path.is_symlink() or settings_path.resolve() != settings_path:
        raise ValueError('CloudPanel cache settings path is aliased')
    settings = json.loads(settings_path.read_text())
    if settings.get('enabled') is not False:
        raise ValueError('CloudPanel HTML cache is enabled or unproven; scoped purge adapter required')
    if (wordpress.get('wp_cache') is not False or wordpress.get('advanced_cache') is not False
            or wordpress.get('cache_plugins') != []):
        raise ValueError('WordPress page cache must be qualified separately')
    return {'provider': 'cloudpanel', 'html_cache': 'disabled',
            'settings_path': str(settings_path), 'wordpress_page_cache': 'disabled'}


def head(url):
    request = urllib.request.Request(url, method='GET', headers={'User-Agent': 'Mozilla/5.0 (compatible; MRN-Deployment/1.0)'})
    with urllib.request.urlopen(request, timeout=30) as response:
        if response.status != 200 or response.url != url:
            raise ValueError('Canonical HTML cache check returned a redirect or error')
        retained = {'content-type', 'cache-control', 'cf-cache-status', 'age', 'x-cache',
                    'x-cache-age', 'x-cache-nxaccel', 'x-mrn-site-release'}
        return {name.lower(): value for name, value in response.headers.items() if name.lower() in retained}


def verify_uncached_html(site_url, pages, fetch_headers=head):
    results = []
    for page in canonical_pages(site_url, pages):
        for phase in ('first', 'warm'):
            headers = fetch_headers(page)
            if 'text/html' not in headers.get('content-type', '').lower():
                raise ValueError('Cache qualification expected HTML')
            # This adapter explicitly requires the observed Cloudflare path.
            # Missing or different provider evidence is not proof of no cache.
            if headers.get('cf-cache-status', '').upper() not in ('DYNAMIC', 'BYPASS'):
                raise ValueError('Edge HTML cache is enabled or unproven: ' + page)
            if headers.get('age') not in (None, '0') or headers.get('x-cache-age') not in (None, '0'):
                raise ValueError('An intermediate HTML cache is active: ' + page)
            results.append({'url': page, 'phase': phase, 'edge': headers['cf-cache-status'],
                            'age': headers.get('age'), 'origin_age': headers.get('x-cache-age')})
    return {'status': 'uncached-html-verified', 'pages': results, 'purged_urls': [],
            'object_cache_changed': False, 'transients_changed': False, 'static_cache_changed': False}
