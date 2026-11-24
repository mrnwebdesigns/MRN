#!/usr/bin/env python3
"""Build a deterministic child-theme release once for promotion between targets."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tarfile
import tempfile

from deploy import export_payload, check, digest
from verify_release import verify


def inventory(root):
    result = {}
    for path in sorted(root.rglob('*')):
        if path.is_symlink():
            raise ValueError('Release symlinks are not allowed')
        if path.is_file():
            result[path.relative_to(root).as_posix()] = hashlib.sha256(path.read_bytes()).hexdigest()
    return result


def build(sha, source, slug, output):
    check(slug, r'[a-z0-9_-]+', 'stylesheet')
    output = Path(output)
    if output.exists() or output.with_suffix('.json').exists():
        raise ValueError('Release output already exists; do not overwrite artifacts')
    output.parent.mkdir(parents=True, exist_ok=True)
    # Keep the candidate on the destination filesystem for exclusive, atomic
    # publication. No output is visible until the complete archive verifies.
    with tempfile.TemporaryDirectory(prefix='.mrn-release-', dir=output.parent) as temp:
        root = Path(temp)
        theme = root / 'source'
        theme.mkdir()
        export_payload(sha, source, theme)
        if "mrn-release-assets.php" not in (theme / 'functions.php').read_text():
            raise ValueError('Child theme must opt in to the build-owned asset adapter')
        built = root / 'built'
        subprocess.run(['node', str(Path(__file__).with_name('build-assets.mjs')), str(theme), str(built), slug, sha], check=True)
        # Preserve original source; generated minified siblings and manifest win.
        generated = built / 'theme'
        for path in theme.rglob('*'):
            if path.is_file():
                destination = generated / path.relative_to(theme)
                if not destination.exists():
                    destination.parent.mkdir(parents=True, exist_ok=True)
                    shutil.copyfile(path, destination)
        files = inventory(built)
        manifest = json.loads((generated / 'mrn-assets.json').read_text())
        release = {'schema': 1, 'source_sha': sha, 'source_path': source, 'slug': slug,
                   'tree': digest(files), 'files': files, 'generation': manifest['generation'],
                   'manifest_sha256': files['theme/mrn-assets.json']}
        (built / 'release.json').write_text(json.dumps(release, indent=2, sort_keys=True) + '\n')
        candidate = root / 'release.tar'
        # Uncompressed tar avoids timestamp-bearing gzip headers.
        with tarfile.open(candidate, 'w', format=tarfile.USTAR_FORMAT) as archive:
            for path in sorted(built.rglob('*')):
                if not path.is_file():
                    continue
                info = archive.gettarinfo(str(path), path.relative_to(built).as_posix())
                info.uid = info.gid = info.mtime = 0
                info.uname = info.gname = ''
                info.mode = 0o644
                with path.open('rb') as content:
                    archive.addfile(info, content)
        receipt = {k: v for k, v in release.items() if k != 'files'}
        receipt['artifact_sha256'] = hashlib.sha256(candidate.read_bytes()).hexdigest()
        verify(candidate, receipt['artifact_sha256'], sha, source, slug)
        receipt_file = root / 'receipt.json'
        receipt_file.write_text(json.dumps(receipt, indent=2) + '\n')
        # link() refuses an existing name, including a competing builder's
        # output. Never replace an earlier immutable archive or receipt.
        os.link(candidate, output)
        os.link(receipt_file, output.with_suffix('.json'))
        print(json.dumps(receipt, indent=2))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--sha', required=True)
    parser.add_argument('--source', required=True)
    parser.add_argument('--slug', required=True)
    parser.add_argument('--output', required=True)
    args = parser.parse_args()
    build(args.sha, args.source, args.slug, args.output)
