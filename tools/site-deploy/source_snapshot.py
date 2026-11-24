"""Import an explicitly reviewed non-Git file set into a private release snapshot.

This versions the release input, not the client site. The original directory is
read-only. No hooks, build steps, filters, global Git configuration or network
operations are used. Normal QA, artifact and deployment gates still apply.
"""
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import tempfile

MAX_FILES, MAX_FILE, MAX_TOTAL = 20000, 64 * 1024 * 1024, 512 * 1024 * 1024


def snapshot(directory, files, output):
    directory, output = Path(directory).resolve(strict=True), Path(output).absolute()
    if not directory.is_dir() or output.is_relative_to(directory) or output.exists():
        raise ValueError('Snapshot output must be new and outside the source directory')
    if (not isinstance(files, list) or not 1 <= len(files) <= MAX_FILES
            or any(not isinstance(name, str) for name in files)
            or len(set(files)) != len(files) or len({name.casefold() for name in files}) != len(files)):
        raise ValueError('Supply an explicit unique reviewed file list')
    entries, total = {}, 0
    for name in files:
        if (not isinstance(name, str) or not re.fullmatch(r'(?:[A-Za-z0-9][A-Za-z0-9_.-]*/)*[A-Za-z0-9][A-Za-z0-9_.-]*', name)
                or any(part in ('.', '..', 'node_modules', 'vendor') for part in Path(name).parts)
                or Path(name).name.lower() in ('wp-config.php', 'wp-config-sample.php', 'id_rsa', 'id_ed25519')
                or Path(name).suffix.lower() in ('.key', '.pem', '.sql', '.sqlite', '.log', '.env')):
            raise ValueError('Unsafe or private source file in snapshot list')
        path = directory / name
        if any(part.is_symlink() for part in [path, *path.parents] if part != directory.parent):
            raise ValueError('Snapshot source must not traverse a symlink')
        descriptor = os.open(path, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK)
        with os.fdopen(descriptor, 'rb') as stream:
            info = os.fstat(stream.fileno())
            if not stat.S_ISREG(info.st_mode) or info.st_size > MAX_FILE:
                raise ValueError('Snapshot requires bounded regular source files')
            body = stream.read(MAX_FILE + 1)
        total += len(body)
        if len(body) != info.st_size or total > MAX_TOTAL:
            raise ValueError('Source changed or snapshot exceeds byte limits')
        entries[name] = body
    # Build beside the final output, then atomically publish an owner-only tree.
    with tempfile.TemporaryDirectory(prefix='.mrn-snapshot-', dir=output.parent) as temp:
        root = Path(temp) / 'source'
        root.mkdir(mode=0o700)
        env = {'PATH': os.environ.get('PATH', '/usr/bin:/bin'), 'GIT_CONFIG_NOSYSTEM':'1', 'GIT_CONFIG_GLOBAL':os.devnull,
               'GIT_AUTHOR_NAME':'MRN reviewed file package', 'GIT_AUTHOR_EMAIL':'release@invalid.test',
               'GIT_COMMITTER_NAME':'MRN reviewed file package', 'GIT_COMMITTER_EMAIL':'release@invalid.test',
               'GIT_AUTHOR_DATE':'2000-01-01T00:00:00Z', 'GIT_COMMITTER_DATE':'2000-01-01T00:00:00Z'}
        def git(*args, data=None):
            return subprocess.check_output(['git', '-C', str(root), '-c', 'core.hooksPath=' + os.devnull,
                                            *args], input=data, env=env).strip().decode()
        git('init', '-q', '--initial-branch=main')
        index = bytearray()
        for name, body in sorted(entries.items()):
            target = root / name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(body)
            oid = git('hash-object', '--no-filters', '-w', '--stdin', data=body)
            index.extend(('100644 ' + oid + '\t' + name + '\0').encode())
        git('update-index', '-z', '--index-info', data=bytes(index))
        tree = git('write-tree')
        source = git('commit-tree', tree, data=b'Reviewed non-Git release snapshot\n')
        git('update-ref', 'refs/heads/main', source)
        receipt = {'schema':'mrn.source-snapshot/v1', 'source_kind':'reviewed-file-package',
                   'source_sha':source, 'tree':tree, 'bytes':total,
                   'files':{name:hashlib.sha256(body).hexdigest() for name, body in sorted(entries.items())}}
        # Keep provenance outside the source Git tree so QA sees only reviewed inputs.
        (root / '.git/mrn-snapshot.json').write_text(json.dumps(receipt, indent=2) + '\n')
        if output.exists():
            raise ValueError('Snapshot output appeared while packaging')
        os.rename(root, output)
    return receipt
