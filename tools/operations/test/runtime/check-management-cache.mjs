// Cross-repository byte integration. GitHub transport and ownership are
// synthetic; acquisition, ZIP extraction and full reconstruction are real.
import assert from 'node:assert/strict';
import { generateKeyPairSync } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { chmodSync, existsSync, mkdtempSync, readFileSync, realpathSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { digest } from '../../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../../src/delegation.mjs';
import { GithubApp } from '../../src/github.mjs';
import { ManagementSourceCacheRegistry, managementSourceRepository as repository } from '../../src/management-source-cache.mjs';
import { encryptSourceBatch, sourceBatchPurpose } from '../../src/management-source-transfer.mjs';

export async function checkManagementCache(runner) {
  const { extractManagementCacheArchive } = await import(pathToFileURL(join(runner, 'src/management-cache-archive.mjs')));
  const { ManagementSourceAssembly } = await import(pathToFileURL(join(runner, 'src/management-source-assembly.mjs')));
  const { sourceAssemblyFixture } = await import(pathToFileURL(join(runner, 'test/fixtures/management-source-assembly.mjs')));
  const { sourceRecipientFixture } = await import(pathToFileURL(join(runner, 'test/fixtures/management-source-recipient.mjs')));
  const f = sourceAssemblyFixture({ cache: true, excluded: true });
  const temporary = realpathSync(mkdtempSync(join(tmpdir(), 'mrn-cache-integration-'))), archive = join(temporary, 'root.zip');
  const theme = f.input.parityManifest.roots.find(root => root.path === 'wp-content/themes/fixture/');
  let acquired, extracted, assembly, output, transfer;
  try {
    execFileSync('python3', ['-I', join(runner, 'test/fixtures/cache-archive.py')], { input: JSON.stringify({ path: archive,
      entries: theme.files.map(file => ({ name: file.path.slice(theme.path.length), base64: f.data.get(file.path).toString('base64') })) }),
    stdio: ['pipe', 'ignore', 'pipe'], timeout: 5000, maxBuffer: 4096 });
    chmodSync(archive, 0o600);
    const bytes = readFileSync(archive), artifact = f.input.catalog.artifacts[0];
    artifact.archive_sha256 = digest(bytes); artifact.maximum_archive_bytes = bytes.length;
    f.input.context.source_recipe_catalog_sha256 = delegationDigest(f.input.catalog); f.reframe();
    const selected = { catalog_artifact: artifact, github_artifact_id: '456', run_id: '123', source_sha: 'b'.repeat(40),
      created_at: Math.floor(f.now / 1000) - 60, expires_at: Math.floor(f.now / 1000) + 3600 };
    const registry = { schema: 1, repository, repository_id: '789', entries: [selected] }, pin = delegationDigest(registry);
    const metadata = { id: 456, expired: false, digest: 'sha256:' + digest(bytes), size_in_bytes: bytes.length,
      workflow_run: { id: 123, repository_id: 789, head_sha: selected.source_sha },
      created_at: new Date(selected.created_at * 1000).toISOString(), expires_at: new Date(selected.expires_at * 1000).toISOString() };
    const json = (value, status = 200) => new Response(JSON.stringify(value), { status, headers: { 'Content-Type': 'application/json' } });
    let calls = 0, authorizations = 0;
    const github = new GithubApp({ appId: 1, installationId: 2, privateKey: generateKeyPairSync('rsa', { modulusLength: 2048 }).privateKey,
      repositories: { [repository]: 789 }, now: () => f.now, fetchImpl: async (url, request) => {
        calls++; const address = String(url);
        if (address.endsWith('/access_tokens')) {
          assert.deepEqual(JSON.parse(request.body), { repository_ids: [789], permissions: { contents: 'read', actions: 'read' } });
          return json({ token: 'synthetic-cache-read', expires_at: new Date(f.now + 3600000).toISOString(),
            repositories: [{ id: 789, full_name: repository }], permissions: { contents: 'read', actions: 'read', metadata: 'read' } }, 201);
        }
        if (address === `https://api.github.com/repos/${repository}/actions/artifacts/456`) return json(metadata);
        if (address.endsWith('/zip')) return new Response('', { status: 302, headers: { Location: 'https://fixture.blob.core.windows.net/root.zip' } });
        assert.equal(address, 'https://fixture.blob.core.windows.net/root.zip');
        assert.deepEqual(request.headers, { 'Accept-Encoding': 'identity' });
        return new Response(bytes, { headers: { 'Content-Length': String(bytes.length) } });
      } });
    const cache = new ManagementSourceCacheRegistry({ current: () => registry, expectedSha256: () => pin, github, now: () => f.now });
    acquired = await cache.acquire(artifact, { expiresAt: f.now + 60000, authorize: async () => { authorizations++; } });
    assert.equal(calls, 4); assert.ok(authorizations > 1); assert.equal(acquired.inventoryVerified, false);
    const inventory = Object.fromEntries(theme.files.map(file => [file.path.slice(theme.path.length), file.sha256]));
    extracted = extractManagementCacheArchive({ archivePath: acquired.archivePath, artifact, inventory, expiresAt: acquired.expiresAt }, () => f.now);
    assert.equal(extracted.result.source_bytes_verified, true); assert.equal(extracted.result.authority_verified, false);
    // A synthetic original record supplies the recipient identity. Encrypt every
    // native/manifest batch with shipping gateway code, then pass only the
    // independent runner's verified plaintext into complete reconstruction.
    // Cache ZIP delivery remains local here, so it is not an encrypted-cache proof.
    transfer = sourceRecipientFixture(false, null, f); transfer.accept();
    assembly = new ManagementSourceAssembly(f.input, () => f.now);
    for (const expected of f.batches) {
      const request = transfer.recipient.readRequest(expected.batch.batch_index, f.now), value = expected.batch;
      const header = { schema: 1, issuer: 'https://operations.mrnwebdesigns.com', audience: transfer.enrollment.audience,
        action: 'observe-management-source-batch', ...request, dispatch_record_sha256: transfer.enrollment.dispatch_record_sha256,
        recipient_key_sha256: digest(Buffer.from(transfer.request.public_key_base64, 'base64')),
        source_context_sha256: delegationDigest(f.input.context), slot_sha256: delegationDigest(f.input.slot),
        request_sha256: value.request_sha256, response_sha256: delegationDigest(value),
        started_at_ms: f.now, completed_at_ms: f.now, issued_at: f.now / 1000, expires_at: f.now / 1000 + 5 };
      const encrypted = encryptSourceBatch(value, transfer.enrollment, header);
      const bytes = Buffer.from(delegationCanonical({ header, encrypted,
        signed_transfer: transfer.envelope({ ...header, encrypted_sha256: delegationDigest(encrypted) }, sourceBatchPurpose) }));
      const opened = transfer.recipient.openBatch(bytes, request.nonce, f.now);
      assert.equal(delegationCanonical(opened.batch), delegationCanonical(value));
      assembly.accept(opened);
      assert.throws(() => transfer.recipient.openBatch(bytes, request.nonce, f.now), /SOURCE_TRANSFER_INVALID/);
    }
    output = assembly.finish(new Map([[artifact.artifact_id, { directory: extracted.directory, archive_sha256: extracted.archive_sha256 }]]));
    assert.equal(output.result.manifest_sha256, f.expected); assert.equal(output.result.source_bytes_verified, true);
    assert.equal(output.result.provenance_verified, false); assert.equal(output.result.admitted, false);
    assert.deepEqual(readFileSync(join(output.directory, 'wp-content/themes/fixture/.gitignore')), f.data.get('wp-content/themes/fixture/.gitignore'));
  } finally {
    transfer?.recipient.destroy(); output?.cleanup(); assembly?.destroy(); extracted?.cleanup(); acquired?.cleanup(); f.close(); rmSync(temporary, { recursive: true, force: true });
  }
  for (const path of [output.directory, extracted.directory, acquired.archivePath]) assert.equal(existsSync(path), false);
  return { cacheAcquisitionAssemblyVerified: true, encryptedNativeAssemblyVerified: true,
    hostedCacheProvenanceVerified: false, encryptedCacheDeliveryVerified: false };
}
