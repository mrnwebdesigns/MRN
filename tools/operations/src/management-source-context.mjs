import { z } from 'zod';
import { requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest, delegationIssuer } from './delegation.mjs';
import { ManagementWorkerContext } from './management-worker-context.mjs';
import { ManagementSourceCapture } from './management-source-capture.mjs';
import { managementEvidenceTypes as types } from './management-evidence-contract.mjs';

export const managementSourceContextPath = '/internal/mainwp/management-source-context';
export const managementSourceContextPurpose = 'mrn-content-management-source-broker-context-v1:';
const requestSchema = z.object({ dispatch_id: types.uuid, context_sha256: types.hash, nonce: types.hash }).strict();
const check = value => requireThat(value, 'MANAGEMENT_SOURCE_CONTEXT', 'The original protected source context is unavailable or changed.');

// Additive private readback. No provider status lookup, capture retry, catalog
// installation, source selection or production startup registration occurs here.
export class ManagementSourceContext {
  constructor({ worker, source, now = () => Date.now(), monotonic = () => performance.now() }) {
    check(worker instanceof ManagementWorkerContext && source instanceof ManagementSourceCapture && source.ledger === worker.ledger);
    Object.assign(this, { worker, source, store: worker.ledger.store, now, monotonic });
    this.store.db.exec(`CREATE TABLE IF NOT EXISTS management_source_context_reads(
      dispatch TEXT NOT NULL, nonce TEXT NOT NULL, state TEXT NOT NULL, PRIMARY KEY(dispatch,nonce));`);
  }
  async read(authorization, input) {
    const parsed = requestSchema.safeParse(input); check(parsed.success);
    const request = parsed.data, pointer = { dispatch_id: request.dispatch_id, context_sha256: request.context_sha256 };
    const start = this.now(), started = this.monotonic();
    const within = () => check(this.now() >= start && this.now() - start < 5000
      && this.monotonic() >= started && this.monotonic() - started < 5000);
    const response = await this.worker.read(authorization, pointer); within();
    const fresh = JSON.parse(Buffer.from(response.signed_context.payload_base64, 'base64'));
    const trust = delegationDigest(this.worker.trust());
    const current = () => {
      within(); const original = this.worker.ledger.originalDispatch(request.dispatch_id);
      check(delegationDigest(original.context) === request.context_sha256 && fresh.expires_at * 1000 > this.now()
        && delegationDigest(original.provenance) === delegationDigest(fresh.recorded_dispatch)
        && delegationDigest(this.worker.trust()) === trust);
      return original;
    };
    this.store.transaction(() => {
      current();
      const rows = this.store.db.prepare('SELECT nonce FROM management_source_context_reads WHERE dispatch=?').all(request.dispatch_id);
      check(rows.length < 2 && !rows.some(row => row.nonce === request.nonce));
      this.store.db.prepare('INSERT INTO management_source_context_reads VALUES(?,?,?)').run(request.dispatch_id, request.nonce, 'reading');
    });
    try {
      return this.store.transaction(() => {
        const original = current(), source = this.source.resolve(original);
        check(delegationDigest(source.context.management) === request.context_sha256
          && source.context.source_recipe_catalog_sha256 === source.ownerCatalogSha256);
        const at = Math.floor(this.now() / 1000);
        const claims = { schema: 1, issuer: delegationIssuer,
          audience: `https://github.com/${original.provenance.repository}/actions/runs/${original.provenance.run_id}`,
          action: 'read-original-management-source', ...request,
          dispatch_record_sha256: original.provenance.dispatch_record_sha256,
          source_context: source.context, source_context_sha256: delegationDigest(source.context),
          slot: source.slot, slot_sha256: delegationDigest(source.slot), issued_at: at,
          expires_at: Math.min(at + 5, fresh.expires_at, Math.floor(original.observation.original_expires_at / 1000)) };
        check(claims.expires_at > at);
        const result = { signed_source_context: this.worker.signer.envelope(claims, managementSourceContextPurpose) };
        check(Buffer.byteLength(delegationCanonical(result)) <= 32768); current();
        this.store.db.prepare('UPDATE management_source_context_reads SET state=? WHERE dispatch=? AND nonce=?')
          .run('observed', request.dispatch_id, request.nonce);
        this.worker.ledger.access.audit(original.context.binding.qualification_job_id, 'source-context-read', '', 'clear', original.revision);
        current(); return result;
      });
    } catch {
      this.store.db.prepare('UPDATE management_source_context_reads SET state=? WHERE dispatch=? AND nonce=?')
        .run('failed', request.dispatch_id, request.nonce);
      check(false);
    }
  }
}
