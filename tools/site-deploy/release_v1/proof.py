"""Provider throttle classification and local runtime health preflight."""
from email.utils import parsedate_to_datetime
import json
import math
import os
import statistics
import subprocess
import time
from urllib.error import HTTPError, URLError
from urllib.request import HTTPRedirectHandler, build_opener


class Blocked(RuntimeError):
    """A deployment diagnostic safe to persist."""


class Throttled(Blocked):
    def __init__(self, status, retry_after=None):
        if status != 429 and (status != 503 or retry_after is None):
            raise ValueError('Unsupported provider throttle evidence')
        self.status = status
        self.retry_after = retry_after
        super().__init__(f'Provider rate limited browser proof (HTTP {status})')


def retry_delay(value, now):
    if not isinstance(value, str) or len(value) > 128:
        return None
    value = value.strip()
    if value.isascii() and value.isdigit():
        return int(value)
    try:
        stamp = parsedate_to_datetime(value)
        return max(0, math.ceil(stamp.timestamp() - now)) if stamp.tzinfo else None
    except (ValueError, TypeError, OverflowError):
        return None


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def probe(url):
    start = time.monotonic()
    with build_opener(NoRedirect()).open(url, timeout=5) as response:
        return response.status, time.monotonic() - start


def runtime_health(url, *, running=None, samples=5, maximum_seconds=2, spread_seconds=1,
                   load_per_cpu=1.5, sample=probe, load=os.getloadavg):
    if running is not None and running() is not True:
        raise Blocked('Local web server is stopped; start the target in Local Hub')
    if type(samples) is not int or not 2 <= samples <= 20:
        raise Blocked('Local health check requires between 2 and 20 samples')
    observations = load()
    ratio = max(observations[:2]) / (os.cpu_count() or 1)
    if ratio > load_per_cpu:
        raise Blocked(f'Local host saturation: load per CPU {ratio:.2f} exceeds {load_per_cpu}')
    timings = []
    for _ in range(samples):
        try:
            status, elapsed = sample(url)
        except HTTPError as error:
            status, elapsed = error.code, 0
            error.close()
        except (OSError, URLError) as error:
            raise Blocked('Local web server is unreachable; check the server and Local Hub router') from error
        if status == 502:
            raise Blocked('Local router returned 502; check the web server, PHP worker and upstream logs')
        if not 200 <= status < 300:
            raise Blocked(f'Local QA probe returned HTTP {status}')
        if not isinstance(elapsed, (int, float)) or not math.isfinite(elapsed) or elapsed < 0:
            raise Blocked('Local performance sample is invalid')
        timings.append(elapsed)
    if statistics.median(timings) > maximum_seconds or max(timings) - min(timings) > spread_seconds:
        raise Blocked('Local performance samples are unstable or slow; stabilize the runtime and rerun preflight')
    return {'sample_seconds': [round(value, 3) for value in timings], 'load_per_cpu': round(ratio, 3)}


def playwright_proof(script, release, urls, *, node='node', timeout=240):
    """Run an installed proof script through the Playwright JSON protocol."""
    env = {key: value for key, value in os.environ.items() if key != 'MRN_RELEASE_RECEIPT_KEY'}
    result = subprocess.run([node, str(script)], input=json.dumps({'release_id': release, 'urls': urls}),
                            capture_output=True, text=True, timeout=timeout, env=env)
    try:
        evidence = json.loads(result.stdout)
        if isinstance(evidence, dict) and evidence.get('throttle'):
            throttle = evidence['throttle']
            raise Throttled(throttle['status'], throttle.get('retry_after'))
    except (ValueError, KeyError, TypeError) as error:
        raise Blocked('Browser proof did not return valid structured evidence') from error
    if result.returncode:
        raise Blocked('Browser proof process failed; inspect private browser diagnostics')
    return evidence
