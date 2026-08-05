#!/usr/bin/env node

import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const python = process.env.MRN_AUDIT_PYTHON
	|| '/Users/khofmeyer/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/bin/python3';
const script = join(scriptDir, 'build-layout-repeater-audit.py');
const result = spawnSync(python, [script], {
	cwd: join(scriptDir, '..', '..'),
	stdio: 'inherit',
});

process.exit(result.status ?? 1);
