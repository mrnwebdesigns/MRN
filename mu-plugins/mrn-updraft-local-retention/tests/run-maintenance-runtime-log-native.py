#!/usr/bin/env python3
"""Extend MRN's existing disposable provider fixture; never connect to a live site."""
import argparse
from pathlib import Path
import shutil
import subprocess
import sys

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--provider-root', type=Path, required=True)
parser.add_argument('--wordpress-source', type=Path, required=True)
parser.add_argument('--evidence', type=Path, required=True)
parser.add_argument('--php', type=Path, required=True)
args = parser.parse_args()
stack = Path(__file__).resolve().parents[3]
provider = args.provider_root.resolve()
evidence = args.evidence.resolve()
harness = evidence / 'harness' / 'tests'
harness.mkdir(parents=True, exist_ok=False)
for name in ('native-maintenance.py', 'native-maintenance.php', 'native-maintenance-completion.php'):
    shutil.copyfile(provider / 'tests' / name, harness / name)
source = harness / 'native-maintenance.php'
body = source.read_text()
anchor = 'unlink($plugin_zip);'
if body.count(anchor) != 1:
    raise SystemExit('Provider fixture contract changed; review the extension insertion.')
extension = "require $mrn_stack_source.'/mu-plugins/mrn-updraft-local-retention/tests/maintenance-runtime-log-native.php';\n"
source.write_text(body.replace(anchor, extension + anchor))
result = subprocess.run([
    sys.executable, str(harness / 'native-maintenance.py'),
    '--stack-root', str(stack), '--provider-source', str(provider),
    '--wordpress-source', str(args.wordpress_source.resolve()),
    '--evidence', str(evidence / 'result'), '--php', str(args.php.resolve()),
], check=False)
raise SystemExit(result.returncode)
