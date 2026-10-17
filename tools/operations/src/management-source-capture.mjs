import { randomBytes, randomUUID } from 'node:crypto';
import { digest, requireThat } from './contracts.mjs';
import { delegationCanonical, delegationDigest } from './delegation.mjs';
import { compileManagementEvidenceSchema } from './content-management-schema.mjs';
import { ManagementEvidenceLedger } from './management-evidence-store.mjs';
import { ManagementEvidenceSigner } from './management-evidence-callback.mjs';
import { ManagementSourceCatalogRegistry } from './management-source-catalog.mjs';
import { managementSourceWire as wire, parseSource } from './management-source-transfer.mjs';

const schemas = Object.fromEntries(['capture_claims', 'capture_input', 'capture_output'].map(name => [name,
  compileManagementEvidenceSchema({ $ref: `#/$defs/${name}`, $defs: wire.$defs })]));
const check = value => requireThat(value, 'MANAGEMENT_SOURCE_CAPTURE',
  'The original source capture is unavailable, consumed, expired or changed.');
const parse = (name, input) => { const value = schemas[name].safeParse(input); check(value.success); return value.data; };
const same = (left, right) => delegationDigest(left) === delegationDigest(right);
function slotValid(slot) {
  check(slot.manifest_frames === Math.ceil(slot.manifest_bytes / wire.limits.chunk_bytes)
    && slot.total_frames === slot.manifest_frames + slot.native_frames
    && (slot.native_files === 0 ? slot.native_bytes === 0 && slot.native_frames === 0
      : slot.native_frames >= slot.native_files));
}

// Internal orchestration only. contextReader must perform the fixed authenticated
// original-job bootstrap; it cannot accept caller context, paths or credentials.
// Its qualified provider adapter and startup wiring are separate prerequisites.
// The native provider retains source bytes; this ledger stores only small pins.
export class ManagementSourceCapture {
  constructor({ ledger, catalogs, contextReader, captureReader, registrar, verifyStaff,
    now = () => Date.now(), monotonic = () => performance.now() }) {
    check(ledger instanceof ManagementEvidenceLedger && catalogs instanceof ManagementSourceCatalogRegistry
      && catalogs.ledger === ledger && typeof contextReader?.read === 'function'
      && typeof captureReader?.capture === 'function' && typeof verifyStaff === 'function');
    Object.assign(this, { ledger, catalogs, contextReader, captureReader, verifyStaff, now, monotonic, store: ledger.store });
    this.signer = new ManagementEvidenceSigner({ ...registrar, now });
    this.store.db.exec(`CREATE TABLE IF NOT EXISTS management_source_captures(
      job TEXT PRIMARY KEY, state TEXT NOT NULL, original_digest TEXT NOT NULL,
      started INTEGER NOT NULL, body TEXT, digest TEXT);`);
  }
  original(jobId, expected) {
    const original = this.ledger.captured(jobId);
    check(!expected || same(original.context, expected));
    check(Math.min(original.mapping.actor.expiresAt, original.context.binding.expires_at * 1000) > this.now());
    return original;
  }
  async capture(jobId) {
    const original = this.store.transaction(() => {
      const value = this.original(jobId);
      check(!this.store.db.prepare('SELECT job FROM management_source_captures WHERE job=?').get(jobId));
      this.store.db.prepare('INSERT INTO management_source_captures VALUES(?,?,?,?,NULL,NULL)')
        .run(jobId, 'capturing', delegationDigest(value.context), this.now());
      this.ledger.access.audit(jobId, 'source-capture-started', '', 'clear', value.revision);
      return value;
    });
    const expiry = Math.min(original.mapping.actor.expiresAt, original.context.binding.expires_at * 1000);
    const phase = milliseconds => {
      const started = this.monotonic(), startedAt = this.now();
      const within = () => check(this.now() >= startedAt && this.now() - startedAt < milliseconds
        && this.now() < expiry && this.monotonic() >= started && this.monotonic() - started < milliseconds);
      const run = async perform => {
        within(); let timer;
        try {
          const result = await Promise.race([Promise.resolve().then(perform), new Promise((_, reject) => {
            timer = setTimeout(() => reject(new Error('MANAGEMENT_SOURCE_CAPTURE_TIMEOUT')),
              Math.max(1, Math.min(milliseconds - (this.monotonic() - started), expiry - this.now())));
          })]);
          within(); return result;
        } finally { clearTimeout(timer); }
      };
      return { within, run };
    };
    try {
      const bootstrap = phase(20000);
      await bootstrap.run(() => this.verifyStaff(this.original(jobId, original.context).mapping.actor));
      const context = parseSource('source_context', await bootstrap.run(() => this.contextReader.read(jobId)));
      check(same(context.management, original.context));
      const catalog = this.catalogs.resolve(jobId, context.source_recipe_catalog_sha256);
      bootstrap.within(); this.original(jobId, original.context);
      const native = phase(wire.limits.capture_seconds * 1000), issued = Math.floor(this.now() / 1000);
      const claims = parse('capture_claims', { schema: 1, issuer: 'mrn-content-qualification', audience: 'https://wpcontrol.mrndev.io',
        issued_at: issued, expires_at: Math.min(issued + wire.limits.capture_seconds, Math.floor(expiry / 1000)),
        request_id: randomUUID(), nonce: randomBytes(32).toString('hex'), action: 'capture-management-source', context });
      check(claims.expires_at > issued);
      const request = parse('capture_input', { signed_request: this.signer.envelope(claims, wire.purposes.capture),
        signed_source_catalog: catalog.signedCatalog });
      check(Buffer.byteLength(delegationCanonical(request)) <= wire.limits.capture_input_bytes);
      const result = parse('capture_output', await native.run(() => this.captureReader.capture(request)));
      check(same(result.context, context)); slotValid(result.slot);
      await native.run(() => this.verifyStaff(this.original(jobId, original.context).mapping.actor));
      return this.store.transaction(() => {
        const current = this.original(jobId, original.context);
        const latestCatalog = this.catalogs.resolve(jobId, context.source_recipe_catalog_sha256);
        check(same(latestCatalog, catalog) && claims.expires_at * 1000 > this.now()); native.within();
        const row = this.store.db.prepare('SELECT state,original_digest FROM management_source_captures WHERE job=?').get(jobId);
        check(row?.state === 'capturing' && row.original_digest === delegationDigest(original.context));
        const value = { context, slot: result.slot, ownerCatalogSha256: catalog.ownerCatalogSha256,
          catalogRegistrySha256: catalog.registrySha256, requestSha256: delegationDigest(claims), capturedAt: this.now() };
        const body = delegationCanonical(value);
        this.store.db.prepare('UPDATE management_source_captures SET state=?,body=?,digest=? WHERE job=? AND state=?')
          .run('complete', body, digest(body), jobId, 'capturing');
        this.ledger.access.audit(jobId, 'source-capture-mapped', '', 'clear', current.revision);
        native.within(); this.original(jobId, original.context);
        return this.resolve(current);
      });
    } catch {
      // Never reset this row or retry capture after uncertain or lost responses.
      this.store.db.prepare('UPDATE management_source_captures SET state=? WHERE job=? AND state=?').run('failed', jobId, 'capturing');
      check(false);
    }
  }
  resolve(original) {
    const jobId = original?.context?.binding?.qualification_job_id;
    const current = this.original(jobId, original.context);
    const row = this.store.db.prepare('SELECT * FROM management_source_captures WHERE job=?').get(jobId);
    check(row?.state === 'complete' && row.original_digest === delegationDigest(current.context)
      && typeof row.body === 'string' && row.body.length <= 32768 && digest(row.body) === row.digest);
    const value = JSON.parse(row.body), context = parseSource('source_context', value.context), slot = parseSource('slot', value.slot);
    check(same(context.management, current.context) && context.source_recipe_catalog_sha256 === value.ownerCatalogSha256
      && Number.isSafeInteger(value.capturedAt) && value.capturedAt >= row.started && value.capturedAt <= this.now());
    slotValid(slot);
    const catalog = this.catalogs.resolve(jobId, value.ownerCatalogSha256);
    check(catalog.registrySha256 === value.catalogRegistrySha256);
    // Batch reads use this local immutable mapping. They do not consume any of
    // the provider's seven status reads or renew the original capture lifetime.
    return { context, slot, ownerCatalogSha256: value.ownerCatalogSha256 };
  }
}
