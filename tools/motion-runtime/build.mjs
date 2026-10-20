/** Reproducible, pinned Motion build for the Stack's three built-in APIs. */
import fs from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
import assert from 'node:assert/strict';
import {build} from 'esbuild';
const directory = path.dirname(fileURLToPath(import.meta.url));
const destination = path.resolve(directory, '../../stack/themes/mrn-base-stack/js/vendor');
const versions = {};
for (const name of ['motion', 'framer-motion', 'motion-dom', 'motion-utils', 'tslib', 'esbuild']) {
  const installed = JSON.parse(await fs.readFile(path.join(directory, 'node_modules', name, 'package.json'), 'utf8'));
  const expected = JSON.parse(await fs.readFile(path.join(directory, 'package.json'), 'utf8')).dependencies[name];
  assert.equal(installed.version, expected, 'Install exact locked dependency: ' + name);
  versions[name] = installed.version;
}
const result = await build({entryPoints: [path.join(directory, 'entry.mjs')], bundle: true, minify: true,
  target: 'es2018', format: 'iife', write: false, legalComments: 'inline',
  banner: {js: '/*! MRN Stack Motion core; Motion 12.38.0 (MIT). See motion-core.LICENSE.txt. */'}});
const bytes = result.outputFiles[0].contents;
const license = await fs.readFile(path.join(directory, 'node_modules/motion/LICENSE.md'));
const manifest = JSON.stringify({schema: 1, versions, exports: ['animate', 'inView', 'scroll'],
  namespace: 'mrnBaseStackCoreMotion', bytes: bytes.length,
  sha256: createHash('sha256').update(bytes).digest('hex')}, null, 2) + '\n';
for (const [name, body] of [['motion-core.js', bytes], ['motion-core.LICENSE.txt', license], ['motion-core.build.json', manifest]]) {
  if (process.argv.includes('--check')) assert.deepEqual(await fs.readFile(path.join(destination, name)), Buffer.from(body), 'Stale generated asset: ' + name);
  else await fs.writeFile(path.join(destination, name), body);
}
console.log(manifest.trim());
