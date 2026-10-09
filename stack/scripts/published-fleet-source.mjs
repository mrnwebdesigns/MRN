/* Owner-local, checksum-bound qualified source input for explicit Fleet plans. */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const SHA = /^[a-f0-9]{64}$/;
const requiredCoverage = ['source', 'contracts', 'installed-default-packages', 'installed-optional-packages', 'no-woocommerce',
  'native-editor', 'native-wpforms', 'api', 'browser', 'accessibility', 'performance',
  'core-web-vitals', 'distribution'];

function readOwned(filename, expected) {
  const info = fs.lstatSync(filename);
  if (!info.isFile() || info.isSymbolicLink() || (info.mode & 0o022) ||
      (typeof process.getuid === 'function' && info.uid !== process.getuid())) {
    throw new Error('Published Fleet source must be an owner-controlled regular file.');
  }
  const bytes = fs.readFileSync(filename);
  if (expected && crypto.createHash('sha256').update(bytes).digest('hex') !== expected) {
    throw new Error('Published Fleet source checksum differs.');
  }
  return JSON.parse(bytes);
}

export function loadPublishedFleetSource(repositoryRoot, override = '') {
  const filename = override || path.join(repositoryRoot, 'releases', 'fleet-ready', 'current.json');
  if (!fs.existsSync(filename)) return null;
  const index = readOwned(filename);
  if (index.schema_version !== 1 || index.status !== 'fleet_ready' ||
      index.site_writes !== false || index.site_adoption_verified !== false ||
      !SHA.test(index.lock_sha256) || !SHA.test(index.source_vector_sha256)) {
    throw new Error('Published Fleet source is not qualified.');
  }
  for (const kind of ['catalog', 'registry', 'optional_registry', 'qualification', 'publication']) {
    if (!path.isAbsolute(index[`${kind}_path`] || '') || !SHA.test(index[`${kind}_sha256`] || '')) {
      throw new Error('Published Fleet source has incomplete evidence.');
    }
  }
  const catalog = readOwned(index.catalog_path, index.catalog_sha256);
  const registry = readOwned(index.registry_path, index.registry_sha256);
  const optionalRegistry = readOwned(index.optional_registry_path, index.optional_registry_sha256);
  const qualification = readOwned(index.qualification_path, index.qualification_sha256);
  const publication = readOwned(index.publication_path, index.publication_sha256);
  if (qualification.status !== 'pass' || qualification.release_id !== index.release_id ||
      qualification.lock_sha256 !== index.lock_sha256 ||
      qualification.source_vector_sha256 !== index.source_vector_sha256 ||
      !requiredCoverage.every(value => qualification.coverage?.includes(value)) ||
      publication.status !== 'pass' || publication.site_writes !== false ||
      publication.hosted?.status !== 'published_verified' ||
      publication.hosted?.release_id !== index.release_id ||
      publication.hosted?.lock_sha256 !== index.lock_sha256 ||
      !path.isAbsolute(index.artifact_root || '')) {
    throw new Error('Published Fleet source qualification/publication binding differs.');
  }
  for (const [slug, hold] of Object.entries(qualification.held_defaults || {})) {
    if ([...registry.releases, ...optionalRegistry.releases].some(row => row.slug === slug && row.version !== hold.version)) {
      throw new Error('Published Fleet source exposes an unqualified held version.');
    }
  }
  return { catalog, registry, optionalRegistry, artifactRoot: index.artifact_root, releaseId: index.release_id };
}

// The Python optional-plan builder uses the same ownership/checksum verifier.
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  process.stdout.write(JSON.stringify(loadPublishedFleetSource(process.argv[2])) + '\n');
}
