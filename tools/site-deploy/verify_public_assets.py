#!/usr/bin/env python3
"""Verify normal public HTML and immutable child asset responses against a build."""
import argparse
import gzip
import hashlib
from html.parser import HTMLParser
import json
from pathlib import Path
import re
import urllib.parse
import urllib.request


class Assets(HTMLParser):
    def __init__(self):
        super().__init__()
        self.urls = []

    def handle_starttag(self, tag, attributes):
        attrs = dict(attributes)
        if tag == 'script' and attrs.get('src'):
            self.urls.append(attrs['src'])
        if tag == 'link' and attrs.get('href') and attrs.get('rel') in ('stylesheet', 'preload', 'modulepreload'):
            self.urls.append(attrs['href'])


def fetch(url, content_types):
    request = urllib.request.Request(url, headers={'User-Agent': 'MRN-Asset-Verification/1.0', 'Accept-Encoding': 'gzip'})
    with urllib.request.urlopen(request, timeout=30) as response:
        if response.status != 200 or response.url != url:
            raise ValueError('Unexpected status or redirect at ' + url)
        content_type = response.headers.get_content_type()
        if content_type not in content_types:
            raise ValueError('Unexpected content type at ' + url)
        body = response.read(32 * 1024 * 1024 + 1)
        if len(body) > 32 * 1024 * 1024:
            raise ValueError('Oversized public response')
        encoding = response.headers.get('Content-Encoding', 'identity')
        if encoding == 'gzip':
            body = gzip.decompress(body)
        elif encoding != 'identity':
            raise ValueError('Unsupported content encoding')
        return body


def verify(manifest, pages, fetcher=fetch):
    results = []
    legacy = '/wp-content/themes/' + manifest['slug'] + '/'
    owned = '/wp-content/mrn-assets/' + manifest['slug'] + '/'
    prefix = '/wp-content/' + manifest['public_path'] + '/'
    for page in pages:
        if urllib.parse.urlsplit(page).query or not page.startswith('https://'):
            raise ValueError('Use normal canonical HTTPS pages without bypass queries')
        for phase in ('first', 'warm'):
            parser = Assets()
            parser.feed(fetcher(page, {'text/html'}).decode('utf-8'))
            verified = {}
            for raw in parser.urls:
                url = urllib.parse.urljoin(page, raw)
                parsed = urllib.parse.urlsplit(url)
                if parsed.path.startswith(legacy) and re.search(r'\.(css|m?js)$', parsed.path):
                    raise ValueError('Public HTML still references a mutable child asset: ' + url)
                if not parsed.path.startswith(owned):
                    continue
                if not parsed.path.startswith(prefix) or parsed.query:
                    raise ValueError('Public HTML references a different asset generation: ' + url)
                name = urllib.parse.unquote(parsed.path[len(prefix):])
                expected = manifest['static_files'].get(name)
                if not expected:
                    raise ValueError('Public asset is absent from the manifest: ' + url)
                types = {'text/css'} if name.endswith('.css') else {'application/javascript', 'text/javascript'}
                if not name.endswith(('.css', '.js', '.mjs')):
                    # Font/image preloads are checked separately from executable assets.
                    types = {'font/woff2', 'font/woff', 'font/ttf', 'font/otf', 'application/font-woff', 'application/octet-stream', 'image/svg+xml', 'image/png', 'image/webp', 'image/jpeg', 'image/avif'}
                body = fetcher(url, types)
                actual = hashlib.sha256(body).hexdigest()
                if actual != expected['sha256'] or len(body) != expected['bytes']:
                    raise ValueError('Public asset checksum differs from the release: ' + url)
                verified[url] = actual
            stylesheet = prefix + manifest['assets']['style.css']['file']
            if not any(urllib.parse.urlsplit(url).path == stylesheet for url in verified):
                raise ValueError('Page did not reference the released child stylesheet: ' + page)
            results.append({'page': page, 'phase': phase, 'assets': verified})
    return {'status': 'public-html-assets-verified', 'generation': manifest['generation'], 'pages': results,
            'remaining_acceptance': 'Browser-loaded dependencies and applicable MRN runtime QA require separate evidence.'}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--manifest', required=True)
    parser.add_argument('--pages', required=True, help='JSON array of canonical affected page URLs')
    parser.add_argument('--receipt', required=True)
    args = parser.parse_args()
    result = verify(json.loads(Path(args.manifest).read_text()), json.loads(Path(args.pages).read_text()))
    Path(args.receipt).write_text(json.dumps(result, indent=2) + '\n')
    print(json.dumps({'status': result['status'], 'generation': result['generation'], 'page_checks': len(result['pages'])}))
