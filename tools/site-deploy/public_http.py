"""Paced public verification, bounded rate-limit recovery and safe diagnostics."""
from email.utils import parsedate_to_datetime
import math
import re
import time
import urllib.error
import urllib.parse
import urllib.request

USER_AGENT = 'Mozilla/5.0 (compatible; MRN-Deployment/1.0)'
# Verification is synchronous. Limit each origin to one request per second,
# including independent first/warm asset reads and cache-header checks.
REQUEST_INTERVAL = 1.0
RATE_LIMIT_ATTEMPTS = 4
RATE_LIMIT_WAIT_BUDGET = 120.0
_last_request = {}


def _pace(url):
    origin = urllib.parse.urlsplit(url)
    key = (origin.scheme, origin.hostname, origin.port)
    remaining = _last_request.get(key, -math.inf) + REQUEST_INTERVAL - time.monotonic()
    if remaining > 0:
        time.sleep(remaining)
    _last_request[key] = time.monotonic()


def _retry_after(headers):
    value = headers.get('Retry-After', '').strip() if headers else ''
    if re.fullmatch(r'[0-9]{1,10}', value):
        return int(value)
    if len(value) <= 128:
        try:
            stamp = parsedate_to_datetime(value)
            if stamp.tzinfo is not None:
                return max(0, math.ceil(stamp.timestamp() - time.time()))
        except (ValueError, TypeError, OverflowError):
            pass
    return None


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
    waited = 0.0
    for attempt in range(1, RATE_LIMIT_ATTEMPTS + 1):
        _pace(url)
        try:
            return urllib.request.urlopen(request, timeout=timeout)
        except urllib.error.HTTPError as error:
            evidence = diagnostics(error)
            retry_after = _retry_after(error.headers) if error.code == 429 else None
            try:
                # Retain only the numeric Cloudflare error, never an arbitrary body.
                body = error.read(8192).decode('utf-8', errors='replace')
            finally:
                error.close()
            match = re.search(r'(?:error(?:\s+code)?[\s:]+)(1\d{3})\b', body, re.I)
            if match:
                evidence['cloudflare_error'] = int(match.group(1))
            if error.code == 429:
                evidence.update(attempts=attempt, retry_wait_seconds=waited)
                if retry_after is not None:
                    evidence['retry_after_seconds'] = retry_after
                delay = retry_after if retry_after is not None else 15 * 2 ** (attempt - 1)
                # Never shorten a server-requested delay to fit the budget.
                if attempt < RATE_LIMIT_ATTEMPTS and delay <= RATE_LIMIT_WAIT_BUDGET - waited:
                    time.sleep(delay)
                    waited += delay
                    continue
            raise PublicHTTPError(evidence) from None
        except (urllib.error.URLError, TimeoutError, OSError) as error:
            raise PublicHTTPError({'url': safe_url(url), 'error_type': type(error).__name__}) from None
