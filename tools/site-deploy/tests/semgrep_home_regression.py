"""Exercise the installed scanner under QA's relocated HOME, without network rules."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile


scanner = shutil.which('semgrep')
assert scanner, 'Run install-qa-python.sh and export its GITHUB_PATH first'
with tempfile.TemporaryDirectory(prefix='mrn-semgrep-regression-') as temporary:
    root = Path(temporary)
    home = root / 'isolated-home'
    home.mkdir()
    rule = root / 'rules.yml'
    rule.write_text('''rules:
  - id: forbidden-eval
    languages: [php]
    message: Never execute user-provided PHP
    severity: ERROR
    pattern: eval(...)
''')
    source = root / 'fixture.php'
    env = {**os.environ, 'HOME': str(home), 'SEMGREP_SEND_METRICS': 'off'}
    for code, expected_exit, expected_findings in (
        ('<?php eval($_GET["input"]);', 1, 1),
        ('<?php echo "safe fixture";', 0, 0),
    ):
        source.write_text(code)
        result = subprocess.run(
            [scanner, 'scan', '--config', str(rule), '--metrics=off',
             '--disable-version-check', '--no-git-ignore', '--error', '--json', str(source)],
            cwd=root, env=env, text=True, capture_output=True,
        )
        assert result.returncode == expected_exit, result.stderr
        report = json.loads(result.stdout)
        assert not report['errors'], report['errors']
        assert len(report['results']) == expected_findings, report
print('Semgrep relocated-HOME regression: unsafe PHP blocked; safe PHP accepted')
