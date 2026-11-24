#!/usr/bin/env node
// Convert the deployment contract into QA Engine's read-only release proof.
// Browser/device/resource logic belongs exclusively to the pinned QA Engine.
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { stylesheetFiles } from './stylesheet-routes.mjs';

const [manifestPath, receiptPath, outputDirectory] = process.argv.slice(2);
const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
const receipt = JSON.parse(await readFile(receiptPath, 'utf8'));
if (receipt.status !== 'public-verified' || !receipt.current?.release_id || !process.env.MRN_QA_ENGINE_ROOT) {
  throw new Error('Verified activation receipt and pinned QA Engine required');
}
const urls = [...new Set(receipt.activation.cache.pages.map(row => row.url))];
const input = { schema: 'mrn.release-proof/v1', source_sha: receipt.source_sha,
  artifact_sha256: receipt.artifact_sha256, release_id: receipt.current.release_id,
  origin: new URL(receipt.url).origin, urls,
  asset_prefix: '/wp-content/' + manifest.public_path + '/',
  owned_prefixes: ['/wp-content/mrn-assets/' + manifest.slug + '/', '/wp-content/themes/' + manifest.slug + '/'],
  assets: manifest.static_files,
  stylesheets: Object.fromEntries(urls.map(url => [url, stylesheetFiles(manifest, url)])) };
await mkdir(outputDirectory, { recursive: true });
const requestPath = path.join(outputDirectory, 'proof-request.json');
await writeFile(requestPath, JSON.stringify(input, null, 2) + '\n');
const engine = path.resolve(process.env.MRN_QA_ENGINE_ROOT, 'tools/verify-release.mjs');
const result = spawnSync(process.execPath, [engine, requestPath, outputDirectory], { stdio: 'inherit' });
if (result.error || result.signal) throw new Error('QA Engine release proof did not complete');
process.exitCode = result.status ?? 1;
