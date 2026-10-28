import { randomUUID } from 'node:crypto';
import { lstatSync, readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { z } from 'zod';
import { digest, requireThat, OpsError } from './contracts.mjs';
import { DelegatedMainwpClient } from './delegated-mainwp.mjs';
import { payload } from './mainwp.mjs';

const prefix = 'mrn_mainwp__';
const nativePrefix = 'mrn-mainwp/';
const contract = 'mrn-email-address-audit-v1';
const maximumBytes = 10 * 1024 * 1024;
const uuid = z.string().uuid();
const common = { audit_id: uuid };
export const auditSchemas = {
  start: z.object({ address: z.string().email().max(254), scope: z.enum(['site', 'sites', 'fleet']),
    site_urls: z.array(z.string().url().max(512)).min(1).max(200).optional() }).strict(),
  continue: z.object({ ...common, max_sites: z.number().int().min(1).max(10).optional(),
    max_pages_per_site: z.number().int().min(1).max(8).optional(), max_duration_ms: z.number().int().min(1000).max(20000).optional(),
    retry_site_ids: z.array(z.number().int().positive().safe()).max(200).optional(),
    refresh_site_ids: z.array(z.number().int().positive().safe()).max(200).optional() }).strict(),
  get: z.object({ ...common, offset: z.number().int().min(0).max(200).optional(), limit: z.number().int().min(1).max(20).optional(),
    match_offset: z.number().int().min(0).max(100000).optional(), match_limit: z.number().int().min(1).max(100).optional() }).strict(),
  export: z.object({ ...common, format: z.enum(['json', 'csv']) }).strict(),
};
export const auditResourceTemplate = 'mrn-operations://email-audits/{audit_id}/{report_id}';
const reportUri = (id, report) => `mrn-operations://email-audits/${id}/${report}`;
const eventTarget = { url: 'mrn-operations://email-audits' };

// The operator installs the reviewed immutable MCP package. No chat-supplied
// module, credential, path or tool name can become an executable dependency.
export async function loadEmailAuditEngine(config) {
  const stat = lstatSync(config.enginePath);
  requireThat(stat.isFile() && !stat.isSymbolicLink() && stat.nlink === 1 && !(stat.mode & 0o022)
    && stat.size <= 1024 * 1024 && digest(readFileSync(config.enginePath)) === config.engineSha256,
  'EMAIL_AUDIT_ENGINE', 'The installed audit engine does not match its reviewed artifact.');
  const engine = await import(pathToFileURL(config.enginePath).href);
  requireThat(engine.EMAIL_AUDIT_ENGINE_CONTRACT === 'mrn-email-audit-engine-v1'
    && typeof engine.executeEmailAuditWithTransport === 'function', 'EMAIL_AUDIT_ENGINE', 'The audit engine contract is unavailable.');
  return engine.executeEmailAuditWithTransport;
}

export function auditOwner(actor) {
  requireThat(actor?.identityProvider === 'google-oauth2' && /^[0-9]{1,64}$/.test(actor.googleSubject)
    && actor.hostedDomain === 'mrnwebdesigns.com' && actor.subject === `google-oauth2|${actor.googleSubject}` && actor.verifiedEmail?.endsWith('@mrnwebdesigns.com')
    && /^con_[A-Za-z0-9]+$/.test(actor.googleConnection) && actor.expiresAt > Date.now(),
  'AUTH_REQUIRED', 'An active verified MRN Google identity is required.');
  return digest({ schema: 1, subject: actor.subject, googleSubject: actor.googleSubject,
    connection: actor.googleConnection, email: actor.verifiedEmail });
}

// Separate tables prevent generic operation/history tools from exposing audit
// evidence. Every read/write key includes the independently verified owner.
export class EmailAuditStorage {
  constructor(store, owner) {
    this.store = store; this.db = store.db; this.owner = owner;
    this.db.exec(`CREATE TABLE IF NOT EXISTS email_audits(owner TEXT NOT NULL,id TEXT NOT NULL,body TEXT NOT NULL,PRIMARY KEY(owner,id));
      CREATE TABLE IF NOT EXISTS email_audit_locks(owner TEXT NOT NULL,id TEXT NOT NULL,pid INTEGER NOT NULL,nonce TEXT NOT NULL,PRIMARY KEY(owner,id));
      CREATE TABLE IF NOT EXISTS email_audit_reports(owner TEXT NOT NULL,id TEXT NOT NULL,audit_id TEXT NOT NULL,state_sha TEXT NOT NULL,body TEXT NOT NULL,mime TEXT NOT NULL,sha TEXT NOT NULL,PRIMARY KEY(owner,id));`);
  }
  load(id) {
    uuid.parse(id);
    const row = this.db.prepare('SELECT body FROM email_audits WHERE owner=? AND id=?').get(this.owner, id);
    requireThat(row, 'NOT_FOUND', 'Audit not found for this Google account.');
    const job = JSON.parse(row.body);
    requireThat(job.identity === this.owner && job.audit_id === id, 'EMAIL_AUDIT_IDENTITY', 'Audit ownership could not be verified.');
    return job;
  }
  save(job) {
    uuid.parse(job.audit_id);
    requireThat(job.identity === this.owner, 'EMAIL_AUDIT_IDENTITY', 'Audit ownership could not be verified.');
    const body = JSON.stringify(job);
    requireThat(Buffer.byteLength(body) <= maximumBytes, 'EMAIL_AUDIT_SIZE', 'The audit exceeds the hosted storage limit; use a smaller exact site scope.');
    this.store.transaction(() => {
      const existing = this.db.prepare('SELECT 1 FROM email_audits WHERE owner=? AND id=?').get(this.owner, job.audit_id);
      if (!existing) {
        const total = this.db.prepare('SELECT COUNT(*) AS n FROM email_audits').get().n;
        const own = this.db.prepare('SELECT COUNT(*) AS n FROM email_audits WHERE owner=?').get(this.owner).n;
        requireThat(total < 200 && own < 20, 'EMAIL_AUDIT_CAPACITY', 'Audit retention capacity reached; an MRN operator must review retained reports.');
      }
      this.db.prepare('INSERT INTO email_audits VALUES(?,?,?) ON CONFLICT(owner,id) DO UPDATE SET body=excluded.body').run(this.owner, job.audit_id, body);
      this.capacity();
    });
  }
  capacity() {
    const bytes = this.db.prepare('SELECT (SELECT COALESCE(SUM(LENGTH(CAST(body AS BLOB))),0) FROM email_audits) + (SELECT COALESCE(SUM(LENGTH(CAST(body AS BLOB))),0) FROM email_audit_reports) AS n').get().n;
    requireThat(bytes <= 512 * 1024 * 1024, 'EMAIL_AUDIT_CAPACITY', 'Audit retention capacity reached; an MRN operator must review retained reports.');
  }
  lock(id) {
    uuid.parse(id); const nonce = randomUUID();
    this.store.transaction(() => {
      const previous = this.db.prepare('SELECT pid,nonce FROM email_audit_locks WHERE owner=? AND id=?').get(this.owner, id);
      if (previous) {
        let dead = false;
        if (Number.isSafeInteger(previous.pid) && previous.pid > 0) {
          try { process.kill(previous.pid, 0); } catch (error) { dead = error.code === 'ESRCH'; }
        }
        requireThat(dead, 'EMAIL_AUDIT_BUSY', 'This audit has an active or unresolved reader.');
        this.db.prepare('DELETE FROM email_audit_locks WHERE owner=? AND id=? AND nonce=?').run(this.owner, id, previous.nonce);
      }
      this.db.prepare('INSERT INTO email_audit_locks VALUES(?,?,?,?)').run(this.owner, id, process.pid, nonce);
    });
    return () => this.db.prepare('DELETE FROM email_audit_locks WHERE owner=? AND id=? AND nonce=?').run(this.owner, id, nonce);
  }
  write(id, body, suffix) {
    const job = this.load(id);
    requireThat(['.results.json', '.results.csv', '.coverage.json'].includes(suffix) && Buffer.byteLength(body) <= maximumBytes,
      'EMAIL_AUDIT_SIZE', 'The report exceeds the hosted export limit.');
    const state = digest(job), sha = digest(body), mime = suffix === '.results.csv' ? 'text/csv' : 'application/json';
    return this.store.transaction(() => {
      const existing = this.db.prepare('SELECT id FROM email_audit_reports WHERE owner=? AND audit_id=? AND state_sha=? AND sha=? AND mime=?').get(this.owner, id, state, sha, mime);
      if (existing) return reportUri(id, existing.id);
      const total = this.db.prepare('SELECT COUNT(*) AS n FROM email_audit_reports').get().n;
      const own = this.db.prepare('SELECT COUNT(*) AS n FROM email_audit_reports WHERE owner=?').get(this.owner).n;
      requireThat(total < 500 && own < 50, 'EMAIL_AUDIT_CAPACITY', 'Report retention capacity reached; an MRN operator must review retained reports.');
      const report = randomUUID();
      this.db.prepare('INSERT INTO email_audit_reports VALUES(?,?,?,?,?,?,?)').run(this.owner, report, id, state, body, mime, sha);
      this.capacity(); return reportUri(id, report);
    });
  }
  report(id, reportId) {
    uuid.parse(reportId); const job = this.load(id);
    const row = this.db.prepare('SELECT * FROM email_audit_reports WHERE owner=? AND id=? AND audit_id=?').get(this.owner, reportId, id);
    requireThat(row, 'NOT_FOUND', 'Report not found for this Google account.');
    requireThat(row.state_sha === digest(job) && row.sha === digest(row.body), 'EMAIL_AUDIT_STALE_EXPORT', 'The audit changed; create a fresh report.');
    return row;
  }
}

// Only these native read failures describe incomplete coverage. Access,
// identity, schema and unknown errors retain the gateway's fatal behavior.
const coverageFailures = new Set(['EMAIL_AUDIT_READER_UNAVAILABLE', 'EMAIL_AUDIT_TRANSPORT',
  'EMAIL_AUDIT_STALE_INVENTORY', 'EMAIL_AUDIT_INSPECTION_FAILED']);

export class EmailAuditWorkflow {
  constructor({ registry, store, connect, engine }) { Object.assign(this, { registry, store, connect, engine }); }
  check(actor) {
    const owner = auditOwner(actor), member = this.registry.member(actor);
    requireThat(member.portfolio?.sources.includes('mainwp') && member.portfolio.actions.includes('read'),
      'FORBIDDEN', 'Current MainWP portfolio read access is required.');
    return owner;
  }
  async session(actor, action, args, finish = result => result) {
    const owner = this.check(actor), storage = new EmailAuditStorage(this.store, owner);
    if (action !== 'start') storage.load(args.audit_id); // No cross-subject backend lookup.
    const client = await this.connect(actor);
    try {
      requireThat(client instanceof DelegatedMainwpClient, 'DELEGATION_UNAVAILABLE', 'Audits require verified Google delegation.');
      await client.verifyStaff(); this.check(actor);
      this.store.audit(actor, eventTarget, args.audit_id, `email-audit:${action}`, 'attempted');
      const transport = { identity: owner,
        fatal: error => error instanceof OpsError,
        call: async (ability, input, _internal, timeout) => {
          this.check(actor);
          // The shared engine's sync uses MainWP's stock selector; the existing
          // delegation contract deliberately accepts exactly one numeric site.
          const nativeArgs = ability === 'mainwp/sync-sites-v1' ? { site_ids: input.site_ids_or_domains } : input;
          const name = ability.startsWith(nativePrefix) ? prefix + ability.slice(nativePrefix.length).replaceAll('-', '_')
            : ability.slice('mainwp/'.length).replaceAll('-', '_');
          let result;
          try { result = payload(await client.callTool({ name, arguments: nativeArgs }, undefined, { timeout })); }
          catch (error) {
            this.check(actor);
            if (ability === `${nativePrefix}audit-email-address-v1` && error instanceof OpsError && coverageFailures.has(error.code))
              throw new Error(error.code); // The engine records only this closed site-coverage reason.
            throw error;
          }
          this.check(actor);
          if ([`${nativePrefix}authorize-email-address-audit-v1`, `${nativePrefix}audit-email-address-v1`].includes(ability))
            requireThat(result?.contract === contract, 'EMAIL_AUDIT_CONTRACT', 'The native audit contract does not match this gateway.');
          return result;
        },
      };
      // Serial readers prevent a failed authorization branch from leaving an
      // outstanding sibling request after its job lock is released.
      const result = await this.engine(transport, `${prefix}${action}_email_address_audit_v1`,
        action === 'continue' ? { ...args, concurrency: 1 } : args, undefined, storage);
      // Recheck all exact site ACLs after a long read, including export/result
      // release. Engine get performs fresh inventory and authorization only.
      const id = result.audit_id;
      await this.engine(transport, `${prefix}get_email_address_audit_v1`, { audit_id: id, limit: 1, match_limit: 1 }, undefined, storage);
      await client.verifyStaff(); this.check(actor);
      const output = finish(result, storage);
      this.store.audit(actor, eventTarget, id, `email-audit:${action}`, 'returned');
      return output;
    } catch (error) {
      this.store.audit(actor, eventTarget, args.audit_id, `email-audit:${action}`, 'failed');
      throw error;
    } finally { await client.close(); }
  }
  async run(actor, action, input) {
    const args = auditSchemas[action].parse(input);
    return this.session(actor, action, args, (result, storage) => {
      if (action !== 'export') return result;
      const { file, coverage_file, permissions: _permissions, ...summary } = result;
      const links = [file, coverage_file].filter(Boolean).map(uri => {
        const report = storage.report(result.audit_id, uri.split('/').at(-1));
        return { uri, mimeType: report.mime, bytes: Buffer.byteLength(report.body), sha256: report.sha };
      });
      return { ...summary, reports: links, delivery: 'authenticated_mcp_resource', delivery_verified: false };
    });
  }
  async readReport(actor, uri) {
    const match = /^mrn-operations:\/\/email-audits\/([^/]+)\/([^/]+)$/.exec(uri);
    requireThat(match, 'NOT_FOUND', 'Report not found.');
    const [, id, reportId] = match; uuid.parse(id); uuid.parse(reportId);
    const storage = new EmailAuditStorage(this.store, this.check(actor));
    storage.report(id, reportId);
    return this.session(actor, 'get', { audit_id: id, limit: 1, match_limit: 1 }, (_result, current) => {
      const report = current.report(id, reportId);
      return { contents: [{ uri, mimeType: report.mime, text: report.body }] };
    });
  }
}
