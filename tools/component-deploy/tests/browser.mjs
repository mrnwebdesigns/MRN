// Isolated fixture acceptance, not a deploy command. Never accepts a site URL.
import assert from 'node:assert/strict';
import { readFile, writeFile, rename } from 'node:fs/promises';
import path from 'node:path';
import { chromium } from '@playwright/test';

const [url, publicRoot, state] = process.argv.slice(2);
assert.match(url, /^http:\/\/127\.0\.0\.1:\d+$/);
assert.equal(path.basename(publicRoot), 'public');
assert.equal(path.dirname(publicRoot), path.dirname(state));
assert.match(path.basename(path.dirname(state)), /^mrn-component-wordpress-/);
const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1'] });
const contexts = [];
const failures = [];
const generations = [];
const parentReleases = [];
let cachedResponses = 0;
async function context() {
  const ctx = await browser.newContext({ serviceWorkers: 'block' });
  contexts.push(ctx);
  // Do not use request routing: Playwright interception disables HTTP caching.
  // Fixture HTML/modules contain only loopback assets; DNS is disabled above.
  return ctx;
}
async function visit(ctx, route = '/') {
  const page = await ctx.newPage();
  const cdp = await ctx.newCDPSession(page);
  await cdp.send('Network.enable');
  cdp.on('Network.requestServedFromCache', () => { cachedResponses += 1; });
  cdp.on('Network.responseReceived', event => { if (event.response.fromDiskCache) cachedResponses += 1; });
  page.on('request', request => { if (new URL(request.url()).origin !== url) failures.push(request.url()); });
  page.on('pageerror', error => failures.push(error.message));
  page.on('requestfailed', request => failures.push(request.url()));
  page.on('response', response => { if (response.status() >= 400) failures.push(response.url()); });
  const response = await page.goto(url + route, { waitUntil: 'networkidle' });
  assert.equal(response.status(), 200);
  assert.equal(await page.evaluate(() => window.fixtureDependency), 1);
  assert.equal(await page.evaluate(() => window.fixtureLazy), 1);
  generations.push(await page.locator('#generation').textContent());
  parentReleases.push(await page.locator('meta[name="mrn-parent-release"]').getAttribute('content'));
  return page;
}
async function select(name) {
  // Fixture setup only. Real transport needs its own backup/lock/CAS/journal.
  await writeFile(path.join(state, 'browser-select.json'), await readFile(path.join(state, name)));
  await rename(path.join(state, 'browser-select.json'), path.join(state, 'current.json'));
}
try {
  const returning = await context();
  const first = await visit(returning);
  const oldAssets = await first.locator('link[rel=stylesheet]').evaluateAll(nodes => nodes.map(node => node.href));
  const opcacheEnabled = await first.locator('#generation').getAttribute('data-opcache') === 'yes';
  assert.equal(await first.locator('h1').evaluate(node => getComputedStyle(node).color), 'rgb(255, 0, 0)');
  assert.equal(opcacheEnabled, true);
  await writeFile(path.join(publicRoot, 'retained.html'), await first.content());
  await visit(returning); // Warm browser cache, ordinary navigation.
  await select('browser-next.json');
  const upgraded = await visit(returning);
  assert.equal(await upgraded.locator('h1').evaluate(node => getComputedStyle(node).color), 'rgb(0, 0, 255)');
  const newAssets = await upgraded.locator('link[rel=stylesheet]').evaluateAll(nodes => nodes.map(node => node.href));
  assert.notDeepEqual(oldAssets, newAssets);
  await visit(await context()); // Fresh browser sees the same coherent pair.
  await visit(returning, '/retained.html'); // Old HTML still has old public bytes.
  await select('browser-old.json');
  const rollback = await visit(returning);
  assert.deepEqual(await rollback.locator('link[rel=stylesheet]').evaluateAll(nodes => nodes.map(node => node.href)), oldAssets);
  assert.deepEqual(generations, ['old/old', 'old/old', 'new/new', 'new/new', 'old/old', 'old/old']);
  const oldParent = JSON.parse(await readFile(path.join(state, 'browser-old.json'))).components['mrn-base-stack'].artifact_sha256;
  const newParent = JSON.parse(await readFile(path.join(state, 'browser-next.json'))).components['mrn-base-stack'].artifact_sha256;
  assert.deepEqual(parentReleases, [oldParent, oldParent, newParent, newParent, oldParent, oldParent]);
  assert.deepEqual(failures, []);
  assert.ok(cachedResponses > 0, 'Browser must actually serve warm immutable assets from cache');
  process.stdout.write(JSON.stringify({ generations, parent_releases: parentReleases, opcache_enabled: opcacheEnabled, failures,
    cached_responses: cachedResponses,
    old_assets: oldAssets, new_assets: newAssets, boundary: 'disposable loopback fixture; no provider qualification' }));
} finally {
  for (const ctx of contexts) await ctx.close();
  await browser.close();
}
