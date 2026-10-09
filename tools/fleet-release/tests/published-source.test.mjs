import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import crypto from 'node:crypto';
import { loadPublishedFleetSource } from '../../../stack/scripts/published-fleet-source.mjs';
const hash = file => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
function fixture() {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'fleet-index-'));
  const release = '2026.10.09-fleet-auto-123456abcdef';
  const index = {schema_version: 1, status: 'fleet_ready', release_id: release,
    lock_sha256: 'a'.repeat(64), source_vector_sha256: 'b'.repeat(64),
    site_writes: false, site_adoption_verified: false, artifact_root: root};
  const values = {catalog: {components: []}, registry: {releases: []},
    qualification: {status: 'pass', release_id: release, lock_sha256: index.lock_sha256,
      source_vector_sha256: index.source_vector_sha256,
      coverage: ['source','contracts','installed-default-packages','no-woocommerce','native-editor',
        'native-wpforms','api','browser','accessibility','performance','core-web-vitals','distribution']},
    publication: {status: 'pass', site_writes: false, hosted: {status: 'published_verified',
      release_id: release, lock_sha256: index.lock_sha256}}};
  for (const [kind, value] of Object.entries(values)) {
    const filename = path.join(root, kind + '.json');
    fs.writeFileSync(filename, JSON.stringify(value), {mode: 0o600});
    index[kind + '_path'] = filename;
    index[kind + '_sha256'] = hash(filename);
  }
  const filename = path.join(root, 'current.json');
  const save = () => fs.writeFileSync(filename, JSON.stringify(index), {mode: 0o600});
  save();
  return {root, index, values, filename, save, cleanup: () => fs.rmSync(root, {recursive: true})};
}
test('explicit Fleet reads the published source map without a site operation', () => {
  const f = fixture();
  try { assert.equal(loadPublishedFleetSource(f.root, f.filename).releaseId, f.index.release_id); }
  finally { f.cleanup(); }
});
test('tampered evidence, writable files and symlinks cannot become Fleet inputs', () => {
  const f = fixture();
  try {
    fs.appendFileSync(f.index.catalog_path, ' ');
    assert.throws(() => loadPublishedFleetSource(f.root, f.filename), /checksum/);
    f.index.catalog_sha256 = hash(f.index.catalog_path); f.save();
    fs.chmodSync(f.index.catalog_path, 0o666);
    assert.throws(() => loadPublishedFleetSource(f.root, f.filename), /owner-controlled/);
    fs.chmodSync(f.index.catalog_path, 0o600);
    const real = f.index.catalog_path; f.index.catalog_path += '.link';
    fs.symlinkSync(real, f.index.catalog_path); f.save();
    assert.throws(() => loadPublishedFleetSource(f.root, f.filename), /owner-controlled/);
  } finally { f.cleanup(); }
});
test('missing no-Woo coverage and mismatched publication bindings block', () => {
  const f = fixture();
  try {
    f.values.qualification.coverage = f.values.qualification.coverage.filter(v => v !== 'no-woocommerce');
    fs.writeFileSync(f.index.qualification_path, JSON.stringify(f.values.qualification));
    f.index.qualification_sha256 = hash(f.index.qualification_path); f.save();
    assert.throws(() => loadPublishedFleetSource(f.root, f.filename), /binding/);
    f.values.qualification.coverage.push('no-woocommerce');
    fs.writeFileSync(f.index.qualification_path, JSON.stringify(f.values.qualification));
    f.index.qualification_sha256 = hash(f.index.qualification_path);
    f.values.publication.hosted.release_id = 'wrong';
    fs.writeFileSync(f.index.publication_path, JSON.stringify(f.values.publication));
    f.index.publication_sha256 = hash(f.index.publication_path); f.save();
    assert.throws(() => loadPublishedFleetSource(f.root, f.filename), /binding/);
  } finally { f.cleanup(); }
});
