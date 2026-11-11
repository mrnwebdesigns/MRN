#!/usr/bin/env node
/** Capture a reviewed MRN Layout Library manifest with an authenticated browser context. */
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const argv = process.argv.slice(2);
const value = (name) => {
	const index = argv.indexOf(name);
	return index >= 0 ? argv[index + 1] : '';
};
const manifestPath = value('--manifest');
const outputDirectory = value('--output');
const storageState = value('--storage-state');

if (!manifestPath || !outputDirectory) {
	throw new Error('Usage: capture.mjs --manifest <manifest.json> --output <directory> [--storage-state <playwright-state.json>]');
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
if (!Array.isArray(manifest.targets) || !manifest.targets.length) {
	throw new Error('Capture manifest has no targets. Regenerate fixtures first.');
}
const fixtureUrl = new URL(manifest.targets[0].url);
if (!storageState && !fixtureUrl.hostname.endsWith('.localhost')) {
	throw new Error('An authenticated Playwright storage state is required outside a local .localhost runtime.');
}

const { chromium } = require(process.env.MRN_PLAYWRIGHT_MODULE || 'playwright');
fs.mkdirSync(outputDirectory, { recursive: true });
const browser = await chromium.launch({ headless: true });
const result = { fingerprint: manifest.fingerprint, captured: [], failed: [] };

try {
	const context = await browser.newContext({ ...(storageState ? { storageState } : {}), ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1100 } });
	const page = await context.newPage();
	for (const target of manifest.targets) {
		try {
			const response = await page.goto(target.url, { waitUntil: 'domcontentloaded', timeout: 8000 });
			if (!response || response.status() >= 400) throw new Error(`HTTP ${response?.status() || 'failure'}`);
			await page.evaluate(() => document.fonts.ready);
			const locator = page.locator(target.selector).first();
			await locator.waitFor({ state: 'visible', timeout: 5000 });
			await locator.screenshot({ path: path.join(outputDirectory, `${target.layout}.jpg`), type: 'jpeg', quality: 82, animations: 'disabled' });
			result.captured.push({ layout: target.layout, source: target.source });
		} catch (error) {
			result.failed.push({ layout: target.layout, message: error instanceof Error ? error.message : String(error) });
		}
	}
	fs.writeFileSync(path.join(outputDirectory, 'capture-report.json'), `${JSON.stringify(result, null, 2)}\n`);
	if (result.failed.length) process.exitCode = 1;
} finally {
	await browser.close();
}
