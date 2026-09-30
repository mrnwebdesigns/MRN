import {test} from 'node:test';
import assert from 'node:assert/strict';
import {mkdtemp, mkdir, writeFile, readFile, rm} from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import {createHash} from 'node:crypto';
import {buildAssets} from '../build-assets.mjs';

test('Gloves regression: warm old URL keeps old bytes; CSS edits get new URLs and synchronized minification', async () => {
  const root = await mkdtemp(path.join(os.tmpdir(), 'mrn-assets-'));
  try {
    const theme = path.join(root, 'source'); await mkdir(theme);
    await writeFile(path.join(theme, 'style.css'), '.gloves { color: red; }');
    await writeFile(path.join(theme, 'style.min.css'), 'stale minified output');
    const first = await buildAssets({theme, output: path.join(root, 'one'), slug: 'child', sourceSha: 'a'.repeat(40)});
    const warmUrl = first.public_path + '/' + first.assets['style.css'].file;
    const warmBytes = await readFile(path.join(root, 'one/assets', warmUrl));
    await writeFile(path.join(theme, 'style.css'), '.gloves { color: blue; }');
    const second = await buildAssets({theme, output: path.join(root, 'two'), slug: 'child', sourceSha: 'b'.repeat(40)});
    assert.notEqual(second.generation, first.generation);
    assert.deepEqual(await readFile(path.join(root, 'one/assets', warmUrl)), warmBytes);
    const released = await readFile(path.join(root, 'two/assets', second.public_path, second.assets['style.css'].file));
    assert.match(released.toString(), /blue|#00f/);
    assert.equal(createHash('sha256').update(released).digest('hex'), second.assets['style.css'].sha256);
    assert.deepEqual(released, await readFile(path.join(root, 'two/theme/style.min.css')));
    const repeat = await buildAssets({theme, output: path.join(root, 'repeat'), slug: 'child', sourceSha: 'c'.repeat(40)});
    assert.equal(repeat.generation, second.generation, 'URLs derive from final bytes, not source SHA or clock');
  } finally { await rm(root, {recursive: true, force: true}); }
});

test('CSS dependency bytes change the generation and retain relative layout', async () => {
  const root = await mkdtemp(path.join(os.tmpdir(), 'mrn-assets-'));
  try {
    const theme = path.join(root, 'source'); await mkdir(path.join(theme, 'fonts'), {recursive: true});
    await writeFile(path.join(theme, 'fonts/font.woff2'), 'font-a');
    await writeFile(path.join(theme, 'style.css'), '@font-face { src: url(fonts/font.woff2); }');
    const a = await buildAssets({theme, output: path.join(root, 'a'), slug: 'child', sourceSha: 'a'.repeat(40)});
    await writeFile(path.join(theme, 'fonts/font.woff2'), 'font-b');
    const b = await buildAssets({theme, output: path.join(root, 'b'), slug: 'child', sourceSha: 'a'.repeat(40)});
    assert.notEqual(a.generation, b.generation);
    assert.equal((await readFile(path.join(root, 'b/assets', b.public_path, 'fonts/font.woff2'))).toString(), 'font-b');
    await writeFile(path.join(theme, 'style.css'), 'a { background: url(missing.png); }');
    await assert.rejects(buildAssets({theme, output: path.join(root, 'c'), slug: 'child', sourceSha: 'a'.repeat(40)}), /Missing local CSS dependency/);
  } finally { await rm(root, {recursive: true, force: true}); }
});
