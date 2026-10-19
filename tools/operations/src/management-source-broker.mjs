import { randomUUID, randomBytes } from 'node:crypto';
import { z } from 'zod';
import { digest, requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest, delegationIssuer } from './delegation.mjs';
import { managementEvidenceTypes as types } from './management-evidence-contract.mjs';
import { ManagementWorkerContext } from './management-worker-context.mjs';
import { ManagementEvidenceSigner } from './management-evidence-callback.mjs';
import { createPrivateQueryReader } from './access-control.mjs';
import { payload } from './mainwp.mjs';
import { encryptSourceBatch, managementSourceWire, parseSource, sourceBatchPurpose, sourceRecipientKey,
  sourceRecipientPurpose, validateSourceBatch } from './management-source-transfer.mjs';

const sourceReadTool = 'mrn_mainwp__read_content_edit_qualification_source_v1';
// Source batches have their own frozen recovery deadline. Access-veto queries
// retain the shared reader's two-second budget and separate connection.
export class ManagementSourceReader extends createPrivateQueryReader(sourceReadTool) {
  async read(envelope) {
    check(this.ready);
    const input = parseSource('read_input', { signed_request: envelope });
    check(Buffer.byteLength(delegationCanonical(input)) <= managementSourceWire.limits.read_input_bytes);
    const output = parseSource('read_output', payload(await this.client.callTool({ name: sourceReadTool, arguments: input }, undefined,
      { timeout: managementSourceWire.limits.recovery_seconds * 1000 })));
    check(Buffer.byteLength(delegationCanonical(output)) <= managementSourceWire.limits.private_response_bytes);
    return output;
  }
}
export const managementSourceRecipientPath = '/internal/mainwp/management-source-recipient';
export const managementSourceBatchPath = '/internal/mainwp/management-source-batch';
const check = value => requireThat(value, 'MANAGEMENT_SOURCE_BROKER', 'The original private source handoff is unavailable, consumed or changed.');
const pointerShape = { dispatch_id: types.uuid, context_sha256: types.hash };
const enrollSchema = z.object({ ...pointerShape, public_key_base64: z.string().length(60), nonce: types.hash }).strict();
const readSchema = z.object({ ...pointerShape, recipient_id: types.uuid, batch_index: z.number().int().min(0).max(31), nonce: types.hash }).strict();
const parse = (schema, input) => { const result = schema.safeParse(input); check(result.success); return result.data; };
const pointer = value => ({ dispatch_id: value.dispatch_id, context_sha256: value.context_sha256 });

// Source-only adapter; production startup supplies no broker or capture
// configuration. Optional HTTP handlers require explicit construction. resolveSource must read a service-owned, authenticated,
// retained capture and its current pre-queue owner catalog pin. There is no
// public method for installing caller-selected slots, catalogs or cache URLs.
export class ManagementSourceBroker {
  constructor({ worker, identity, registrar, reader, resolveSource, now = () => Date.now(), monotonic = () => performance.now() }) {
    check(worker instanceof ManagementWorkerContext && typeof resolveSource === 'function');
    Object.assign(this, { worker, ledger: worker.ledger, store: worker.ledger.store, reader, resolveSource, now, monotonic });
    this.signer = new ManagementEvidenceSigner({ ...identity, now });
    this.registrar = new ManagementEvidenceSigner({ ...registrar, now });
    check(identity.keyId !== registrar.keyId && !identity.privateKey.export({ type: 'pkcs8', format: 'der' })
      .equals(registrar.privateKey.export({ type: 'pkcs8', format: 'der' })));
    this.store.db.exec(`CREATE TABLE IF NOT EXISTS management_source_recipients(
      dispatch TEXT PRIMARY KEY, body TEXT NOT NULL, digest TEXT NOT NULL, key_digest TEXT NOT NULL UNIQUE);
      CREATE TABLE IF NOT EXISTS management_source_reads(
      dispatch TEXT NOT NULL, nonce TEXT NOT NULL, batch INTEGER NOT NULL, state TEXT NOT NULL,
      request_digest TEXT NOT NULL, response_digest TEXT, PRIMARY KEY(dispatch,nonce));`);
  }
  original(input) {
    const value = this.ledger.originalDispatch(input.dispatch_id);
    check(input.context_sha256 === delegationDigest(value.context)); return value;
  }
  recipient(input) {
    const original = this.original(input);
    const row = this.store.db.prepare('SELECT body,digest FROM management_source_recipients WHERE dispatch=?').get(input.dispatch_id);
    check(row && digest(row.body) === row.digest); const recipient = JSON.parse(row.body);
    check(recipient.recipient_id === input.recipient_id && recipient.context_sha256 === input.context_sha256
      && recipient.dispatch_record_sha256 === original.provenance.dispatch_record_sha256
      && recipient.expires_at * 1000 > this.now() && recipient.issued_at * 1000 <= this.now());
    return { original, recipient };
  }
  async enroll(authorization, input) {
    const request = parse(enrollSchema, input); sourceRecipientKey(request.public_key_base64);
    await this.worker.read(authorization, pointer(request));
    return this.store.transaction(() => {
      const original = this.original(request), at = Math.floor(this.now() / 1000);
      const keyDigest = digest(Buffer.from(request.public_key_base64, 'base64'));
      check(!this.store.db.prepare('SELECT dispatch FROM management_source_recipients WHERE dispatch=? OR key_digest=?')
        .get(request.dispatch_id, keyDigest));
      const recipient = { schema: 1, issuer: delegationIssuer,
        audience: `https://github.com/${original.provenance.repository}/actions/runs/${original.provenance.run_id}`,
        action: 'bind-management-source-recipient', ...request, recipient_id: randomUUID(),
        dispatch_record_sha256: original.provenance.dispatch_record_sha256,
        issued_at: at, expires_at: Math.min(at + 300, Math.floor(original.observation.original_expires_at / 1000)) };
      check(recipient.expires_at > at); const body = delegationCanonical(recipient);
      this.store.db.prepare('INSERT INTO management_source_recipients VALUES(?,?,?,?)').run(request.dispatch_id, body, digest(body), keyDigest);
      this.ledger.access.audit(original.context.binding.qualification_job_id, 'source-recipient-bound', '', 'clear', original.revision);
      this.original(request);
      return { signed_recipient: this.signer.envelope(recipient, sourceRecipientPurpose) };
    });
  }
  async read(authorization, input) {
    const request = parse(readSchema, input), startedAt = this.now(), started = this.monotonic();
    const within = () => check(this.now() >= startedAt && this.now() - startedAt < 20000
      && this.monotonic() >= started && this.monotonic() - started < 20000);
    const bounded = async perform => {
      within(); let timer;
      try { return await Promise.race([Promise.resolve().then(perform), new Promise((_, reject) => {
        timer = setTimeout(() => reject(new Error('MANAGEMENT_SOURCE_TIMEOUT')), Math.max(1, 20000 - (this.monotonic() - started)));
      })]); } finally { clearTimeout(timer); }
    };
    await bounded(() => this.worker.read(authorization, pointer(request)));
    const { original, recipient } = this.recipient(request);
    // No worker argument can select a slot, source catalog or backend request.
    const source = await bounded(() => this.resolveSource(original));
    const context = parseSource('source_context', source.context), slot = parseSource('slot', source.slot);
    check(delegationDigest(context.management) === request.context_sha256
      && context.source_recipe_catalog_sha256 === source.ownerCatalogSha256);
    const sourceDigest = delegationDigest({ context, slot, ownerCatalogSha256: source.ownerCatalogSha256 });
    const at = Math.floor(this.now() / 1000);
    const claims = parseSource('read_claims', { schema: 1, issuer: 'mrn-content-qualification', audience: 'https://wpcontrol.mrndev.io',
      issued_at: at, expires_at: Math.min(at + 20, recipient.expires_at), request_id: randomUUID(), nonce: randomBytes(32).toString('hex'),
      action: 'read-management-source', context, transfer_id: slot.transfer_id,
      transfer_manifest_sha256: slot.transfer_manifest_sha256, batch_index: request.batch_index });
    check(claims.expires_at > at && request.batch_index * 16 < slot.total_frames);
    const backendRequest = { signed_request: this.registrar.envelope(claims, managementSourceWire.purposes.read) };
    const requestDigest = delegationDigest(backendRequest);
    this.store.transaction(() => {
      this.recipient(request); within();
      const rows = this.store.db.prepare('SELECT nonce,batch,state FROM management_source_reads WHERE dispatch=?').all(request.dispatch_id);
      check(rows.length < 40 && !rows.some(row => row.nonce === request.nonce || row.state === 'reading'));
      const unique = new Set(rows.map(row => row.batch));
      check(!unique.has(request.batch_index) || rows.length - unique.size < 8);
      this.store.db.prepare('INSERT INTO management_source_reads VALUES(?,?,?,?,?,NULL)')
        .run(request.dispatch_id, request.nonce, request.batch_index, 'reading', requestDigest);
    });
    try {
      const result = await bounded(() => this.reader.read(backendRequest.signed_request));
      within(); this.recipient(request);
      check(claims.expires_at * 1000 > this.now());
      check(delegationDigest(backendRequest) === requestDigest);
      const value = validateSourceBatch(result, context, slot, claims, backendRequest);
      const trustDigest = delegationDigest(this.worker.trust());
      const fresh = await bounded(() => this.worker.read(authorization, pointer(request)));
      const freshClaims = JSON.parse(Buffer.from(fresh.signed_context.payload_base64, 'base64'));
      const current = await bounded(() => this.resolveSource(this.original(request)));
      check(sourceDigest === delegationDigest({ context: current.context, slot: current.slot, ownerCatalogSha256: current.ownerCatalogSha256 }));
      return this.store.transaction(() => {
        const { original: latest } = this.recipient(request); within();
        check(freshClaims.expires_at * 1000 > this.now() && claims.expires_at * 1000 > this.now()
          && trustDigest === delegationDigest(this.worker.trust()));
        const completedAt = this.now();
        const header = { schema: 1, issuer: delegationIssuer, audience: recipient.audience, action: 'observe-management-source-batch',
          ...request, dispatch_record_sha256: recipient.dispatch_record_sha256, recipient_key_sha256: digest(Buffer.from(recipient.public_key_base64, 'base64')),
          source_context_sha256: delegationDigest(context), slot_sha256: delegationDigest(slot), request_sha256: requestDigest,
          response_sha256: delegationDigest(value), started_at_ms: startedAt, completed_at_ms: completedAt,
          issued_at: Math.floor(completedAt / 1000), expires_at: Math.min(Math.floor(completedAt / 1000) + 5, recipient.expires_at) };
        const encrypted = encryptSourceBatch(value, recipient, header);
        const response = { header, encrypted, signed_transfer: this.signer.envelope({ ...header, encrypted_sha256: delegationDigest(encrypted) }, sourceBatchPurpose) };
        check(Buffer.byteLength(delegationCanonical(response)) <= 2 * 1024 * 1024);
        this.store.db.prepare('UPDATE management_source_reads SET state=?,response_digest=? WHERE dispatch=? AND nonce=? AND state=?')
          .run('observed', header.response_sha256, request.dispatch_id, request.nonce, 'reading');
        this.ledger.access.audit(latest.context.binding.qualification_job_id, 'source-batch-observed', '', 'clear', latest.revision);
        this.recipient(request); within();
        check(header.expires_at * 1000 > this.now() && freshClaims.expires_at * 1000 > this.now()
          && trustDigest === delegationDigest(this.worker.trust()));
        return response;
      });
    } catch {
      // Retain the spent nonce/batch on every failure. A late transport promise
      // cannot finish the transaction or emit source after the deadline.
      this.store.db.prepare('UPDATE management_source_reads SET state=? WHERE dispatch=? AND nonce=? AND state=?')
        .run('failed', request.dispatch_id, request.nonce, 'reading');
      check(false);
    }
  }
}
