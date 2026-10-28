"""Consistent public verification identity and bounded, non-secret diagnostics."""
import re
import urllib.error
import urllib.parse
import urllib.request

USER_AGENT = 'Mozilla/5.0 (compatible; MRN-Deployment/1.0)'


def safe_url(url):
    value = urllib.parse.urlsplit(url)
    return urllib.parse.urlunsplit((value.scheme, value.hostname or '', value.path, '', ''))


def diagnostics(error):
    if isinstance(error, PublicHTTPError):
        return error.diagnostics
    if isinstance(error, urllib.error.HTTPError):
        result = {'url': safe_url(error.url), 'http_status': error.code}
        ray = error.headers.get('cf-ray', '') if error.headers else ''
        if re.fullmatch(r'[a-fA-F0-9]{8,32}-[A-Za-z]{3}', ray):
            result['cloudflare_ray'] = ray
        return result
    return {'error_type': type(error).__name__}


class PublicHTTPError(RuntimeError):
    def __init__(self, evidence):
        self.diagnostics = evidence
        super().__init__('Public HTTP verification failed: ' + str(evidence))


def open_public(url, headers=None, timeout=30):
    request = urllib.request.Request(url, headers={**(headers or {}), 'User-Agent': USER_AGENT})
    try:
        return urllib.request.urlopen(request, timeout=timeout)
    except urllib.error.HTTPError as error:
        evidence = diagnostics(error)
        # Retain only the numeric Cloudflare error, never an arbitrary body.
        body = error.read(8192).decode('utf-8', errors='replace')
        match = re.search(r'(?:error(?:\s+code)?[\s:]+)(1\d{3})\b', body, re.I)
        if match:
            evidence['cloudflare_error'] = int(match.group(1))
        raise PublicHTTPError(evidence) from None
    except (urllib.error.URLError, TimeoutError, OSError) as error:
        raise PublicHTTPError({'url': safe_url(url), 'error_type': type(error).__name__}) from None
