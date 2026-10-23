// Independent implementation agreement on synthetic identity-signed records.
// This does not dispatch a worker, authenticate OIDC or perform an HTTP read.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { delegationCanonical } from '../../src/delegation.mjs';
import { existingWorkerContractSha256, existingWorkerPurpose,
  verifyExistingManagementWorkerRecord } from '../../src/existing-management-worker-contract.mjs';

export async function checkExistingManagementWorker(runner) {
  const independent = await import(pathToFileURL(join(runner, 'src/management-existing-worker-context.mjs')));
  const { existingWorkerFixture, workerDenialCases } = await import(pathToFileURL(join(runner, 'test/fixtures/management-existing-worker-context.mjs')));
  assert.equal(independent.existingWorkerContractSha256, existingWorkerContractSha256);
  assert.equal(independent.existingWorkerPurpose, existingWorkerPurpose);
  assert.deepEqual(readFileSync(join(runner, 'contracts/content-management-existing-worker-v2-proposal.json')),
    readFileSync(new URL('../../contracts/content-management-existing-worker-v2-proposal.json', import.meta.url)));
  const readers = [verifyExistingManagementWorkerRecord, independent.verifyExistingManagementWorkerRecord];
  const positive = existingWorkerFixture(), raw = positive.encode();
  const results = readers.map(read => read({ ...positive.input, raw }));
  assert.equal(delegationCanonical(results[0]), delegationCanonical(results[1]));
  for (const r of results) for (const flag of ['current_access_proven', 'hosted_oidc_proven', 'source_verified', 'admitted'])
    assert.equal(r[flag], false);
  let negatives = 0;
  const deny = (input, bytes) => {
    for (const read of readers) assert.throws(() => read({ ...input, raw: bytes }),
      error => error.code === 'MANAGEMENT_EXISTING_WORKER_CONTRACT' || error.message === 'MANAGEMENT_EXISTING_WORKER_CONTRACT');
    negatives++;
  };
  for (const [, mutate] of workerDenialCases) {
    const f = existingWorkerFixture(); mutate(f); deny(f.input, f.encode());
  }
  const registry = JSON.parse(readFileSync(new URL('../../contracts/content-management-existing-wire-v2-proposal.json', import.meta.url)));
  for (const purpose of ['mrn-content-management-worker-context-v1:', ...Object.keys(registry.signature_roles)])
    deny(positive.input, positive.encode(positive.claims, purpose));
  const canonical = delegationCanonical(positive.claims);
  for (const bytes of [Buffer.from(' ' + canonical), Buffer.from('{"schema":1,' + canonical.slice(1)),
    Buffer.from('{"\\u0073chema":2,' + canonical.slice(1)), Buffer.from([0xff, 0xfe]),
    Buffer.alloc(48001, 32), Buffer.from('{')]) deny(positive.input, positive.encode(bytes));
  for (const bytes of [Buffer.from(' ' + raw.toString()), Buffer.from('{"signed_context":null,' + raw.toString().slice(1)),
    Buffer.alloc(65537), Buffer.alloc(0), Buffer.from([0xff]), Buffer.from('{')]) deny(positive.input, bytes);
  return { scope: 'synthetic-existing-worker-record-consistency-only', wire_sha256: existingWorkerContractSha256,
    positive_records: 1, negative_records: negatives, independent_implementations: 2,
    current_access_proven: false, hosted_oidc_proven: false, source_verified: false, admitted: false };
}
