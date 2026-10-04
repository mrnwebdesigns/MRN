#!/usr/bin/env node
// Read-only browser verification using the pinned MRN QA Engine's Playwright.
import { createRequire } from 'node:module';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import path from 'node:path';
import { stylesheetFiles } from './stylesheet-routes.mjs';

const require = createRequire(path.resolve(process.env.MRN_QA_ENGINE_ROOT, 'package.json'));
const { chromium } = require('@playwright/test');
const [manifestPath, receiptPath, outputDirectory] = process.argv.slice(2);
const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
const receipt = JSON.parse(await readFile(receiptPath, 'utf8'));
if (receipt.status !== 'public-verified' || !receipt.current?.release_id) throw new Error('Verified activation receipt required');
const pages = [...new Set(receipt.activation.cache.pages.map(row => row.url))];
const origin = new URL(receipt.url).origin;
const prefix = '/wp-content/' + manifest.public_path + '/';
const legacy = '/wp-content/themes/' + manifest.slug + '/';
const owned = '/wp-content/mrn-assets/' + manifest.slug + '/';
await mkdir(outputDirectory, { recursive: true });
const browser = await chromium.launch();
const results = [];
try {
  for (const [viewportName, width, height] of [['desktop', 1440, 1000], ['tablet', 1024, 1000], ['mobile', 390, 844]]) {
    for (const [index, url] of pages.entries()) {
      if (new URL(url).origin !== origin || new URL(url).search) throw new Error('Unexpected verification target');
      const page = await browser.newPage({ viewport: { width, height }, reducedMotion: 'reduce' });
      const assets = [], errors = [], pending = [];
      page.on('pageerror', error => errors.push(error.message));
      page.on('response', response => {
        const assetUrl = new URL(response.url());
        if (assetUrl.origin !== origin || !(assetUrl.pathname.startsWith(owned) || assetUrl.pathname.startsWith(legacy))) return;
        pending.push((async () => {
          if (!assetUrl.pathname.startsWith(prefix) || response.status() !== 200) throw new Error('Stale or unavailable child asset: ' + assetUrl);
          const key = decodeURIComponent(assetUrl.pathname.slice(prefix.length));
          const expected = manifest.static_files[key];
          const body = await response.body();
          const sha256 = createHash('sha256').update(body).digest('hex');
          if (!expected || expected.sha256 !== sha256 || expected.bytes !== body.length) throw new Error('Browser asset checksum mismatch: ' + assetUrl);
          assets.push({ url: String(assetUrl), sha256, bytes: body.length });
        })().catch(error => errors.push(error.message)));
      });
      const response = await page.goto(url, { waitUntil: 'load', timeout: 60000 });
      const releaseHeader = response.headers()['x-mrn-site-release'];
      const requiresHeader = !receipt.host_provider || receipt.host_provider === 'cloudpanel';
      if (response.status() !== 200 || ((requiresHeader || releaseHeader) && releaseHeader !== receipt.current.release_id)) errors.push('Browser selected a different release');
      await page.evaluate(() => document.fonts.ready);
      // Trigger ordinary lazy-loaded dependencies without clicks or data writes.
      await page.evaluate(async () => {
        for (let y = 0; y < document.documentElement.scrollHeight; y += window.innerHeight) {
          window.scrollTo(0, y); await new Promise(resolve => setTimeout(resolve, 100));
        }
        window.scrollTo(0, 0);
      });
      await page.waitForTimeout(1000);
      await page.screenshot({ path: path.join(outputDirectory, `${viewportName}-${index}.png`), fullPage: true });
      await Promise.all(pending);
      const expectedStylesheets = stylesheetFiles(manifest, url).map(file => prefix + file);
      const appliedStylesheets = await page.evaluate(() => Array.from(document.styleSheets, sheet => sheet.href));
      if (!assets.some(asset => appliedStylesheets.includes(asset.url) && expectedStylesheets.includes(new URL(asset.url).pathname))) errors.push('Released child stylesheet was not loaded');
      results.push({ url, viewport: viewportName, assets, errors });
      await page.close();
    }
  }
} finally {
  await browser.close();
}
await writeFile(path.join(outputDirectory, 'browser-assets.json'), JSON.stringify(results, null, 2) + '\n');
const failures = results.flatMap(row => row.errors);
console.log(JSON.stringify({ pages: results.length, assets: results.reduce((count, row) => count + row.assets.length, 0), failures }));
if (failures.length) process.exitCode = 1;
