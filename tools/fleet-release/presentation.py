"""Native persistence and browser contracts for optional parent presentations."""
from pathlib import Path
import json
import subprocess
from urllib.parse import urlparse

from common import ReleaseError, write

RUNTIME_CASES = (
    'body-quotation', 'bounded-split-frame', 'card-frame', 'cta-frame',
    'faq-aside-frame', 'icon-only-links', 'image-scroll-drift', 'labeled-logos',
    'responsive-tabs-frame', 'service-composition', 'service-copy',
    'tab-action-presentation', 'text-tags-image-actions', 'scroll-words-adaptive-logos',
)


def qualify_presentations(fixture, repo, settings, evidence, *, browser=True):
    if urlparse(fixture.url).hostname != '127.0.0.1':
        raise ReleaseError('Presentation qualification requires the disposable loopback fixture')
    repo, evidence = Path(repo), Path(evidence)
    evidence.mkdir(parents=True, exist_ok=True)
    seed = fixture.php((Path(__file__).with_name('presentation-seed.php')).read_text().removeprefix('<?php\n'))
    write(evidence / 'seed.json', seed)
    tests = repo / 'stack/themes/mrn-base-stack/tests'
    results = {}
    for name in RUNTIME_CASES:
        setup = ('wp_set_current_user(get_user_by("login","fleet-admin")->ID);'
                 'putenv("MRN_STACK_TEST_HOME=".home_url());'
                 'putenv("MRN_CARD_TEST_IMAGE_IDS=' + str(seed['image']) + '");')
        results[name] = fixture.php(setup + 'require ' + repr(str(tests / 'runtime' / (name + '.php'))) + ';')
        if results[name].get('result') != 'passed':
            raise ReleaseError('Native presentation case failed: ' + name)
        write(evidence / (name + '.json'), results[name])
    scripts = [tests / 'js' / (name + '.cjs') for name in ('exclusive-faq', 'image-scroll-drift', 'scroll-words')]
    scripts += [tests / 'runtime' / (name + '.cjs') for name in ('image-action-pointer', 'responsive-tabs-fallback')]
    for script in scripts:
        with (evidence / (script.stem + '.log')).open('w') as output:
            subprocess.run([settings['node'], str(script)], stdout=output, stderr=subprocess.STDOUT,
                           cwd=repo, check=True, timeout=60)
    if browser:
        with (evidence / 'browser.log').open('w') as output:
            subprocess.run([settings['node'], str(Path(__file__).with_name('presentation-browser.cjs'))],
                           input=json.dumps({**fixture.browser_input(), 'presentation': seed,
                                             'output': str(evidence / 'browser.json')}), text=True,
                           stdout=output, stderr=subprocess.STDOUT, cwd=repo, check=True, timeout=900)
    fixture.assert_clean_diagnostics()
    result = {'status': 'pass', 'native_cases': len(results),
              'native_assertions': sum(value['count'] for value in results.values()),
              'javascript_contracts': len(scripts), 'browser': browser}
    write(evidence / 'qualification.json', result)
    return result
