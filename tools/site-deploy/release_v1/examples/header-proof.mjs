/** Reference browser proof. Add the site's selectors, assets and QA assertions here. */
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { paceBrowser } from '../browser.mjs';

const require = createRequire(resolve(process.env.MRN_QA_ENGINE_ROOT, 'package.json'));
const { chromium } = require('@playwright/test');
let input = '';
for await (const chunk of process.stdin) input += chunk;
const { release_id: release, urls } = JSON.parse(input);
const browser = await chromium.launch();
const context = await browser.newContext({ serviceWorkers: 'block' });
const summary = await paceBrowser(context, [new URL(urls[0]).origin]);
const page = await context.newPage();
const failed = [];
page.on('pageerror', () => failed.push('page-error'));
page.on('response', response => {
  if (response.status() >= 400) failed.push('http-error');
});
try {
  for (const url of urls) {
    const response = await page.goto(url, { waitUntil: 'load', timeout: 90000 });
    if (!response?.ok() || page.url() !== url || response.headers()['x-mrn-site-release'] !== release) {
      failed.push('release-or-url-mismatch');
      break;
    }
  }
} catch {
  failed.push('navigation-failed');
} finally {
  await browser.close();
}
const { throttle, transport_failure: transportFailed } = summary();
console.log(JSON.stringify({ passed: !failed.length && !transportFailed && !throttle, release_id: release,
  urls, ...(throttle ? { throttle } : {}) }));
