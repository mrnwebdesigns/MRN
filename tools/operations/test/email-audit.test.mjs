import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdtempSync, writeFileSync, chmodSync, symlinkSync, rmSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { configSchema } from '../src/config.mjs';
import { digest } from '../src/contracts.mjs';
import { Store } from '../src/store.mjs';
import { EmailAuditStorage, loadEmailAuditEngine } from '../src/email-audit.mjs';
const config = JSON.parse(readFileSync(new URL('../deploy/mrn-apps.service.json', import.meta.url), 'utf8'));

const owner = 'a'.repeat(64);
const job = () => ({ schema: 1, identity: owner, audit_id: randomUUID(), rows: [] });
function fixture(t) { const store = new Store(':memory:'); t.after(() => store.close()); return new EmailAuditStorage(store, owner); }

test('audit configuration cannot activate without the existing Google delegation contract', () => {
  const emailAudit = { enginePath: '/reviewed/dist/mrn-email-audit.js', engineSha256: 'a'.repeat(64) };
  assert.throws(() => configSchema.parse({ ...config, emailAudit }));
  const c = configSchema.parse({ ...config, emailAudit, delegation: { keyId: 'reviewed', privateKeyPath: '/private/key' },
    auth: { ...config.auth, googleWorkspace: { connectionId: 'con_Approved' } } });
  assert.equal(c.writesEnabled, false); assert.equal(c.github, undefined); assert.equal(c.contentEdits, undefined);
});

test('audit runtime loader rejects wrong artifact, symlinks and writable code', async t => {
  const root = mkdtempSync(join(tmpdir(), 'mrn-engine-')); t.after(() => rmSync(root, { recursive: true, force: true }));
  const enginePath = join(root, 'engine.mjs');
  const source = "export const EMAIL_AUDIT_ENGINE_CONTRACT='mrn-email-audit-engine-v1'; export async function executeEmailAuditWithTransport(){}";
  writeFileSync(enginePath, source, { mode: 0o600 });
  assert.equal(typeof await loadEmailAuditEngine({ enginePath, engineSha256: digest(source) }), 'function');
  await assert.rejects(() => loadEmailAuditEngine({ enginePath, engineSha256: 'f'.repeat(64) }), { code: 'EMAIL_AUDIT_ENGINE' });
  const link = join(root, 'linked.mjs'); symlinkSync(enginePath, link);
  await assert.rejects(() => loadEmailAuditEngine({ enginePath: link, engineSha256: digest(source) }), { code: 'EMAIL_AUDIT_ENGINE' });
  chmodSync(enginePath, 0o666);
  await assert.rejects(() => loadEmailAuditEngine({ enginePath, engineSha256: digest(source) }), { code: 'EMAIL_AUDIT_ENGINE' });
});

test('private audit retention is bounded and duplicate exports reuse the same artifact', t => {
  const s = fixture(t), first = job(); s.save(first);
  const uri = s.write(first.audit_id, '{}', '.results.json');
  assert.equal(s.write(first.audit_id, '{}', '.results.json'), uri);
  for (let i = 0; i < 19; i++) s.save(job());
  assert.throws(() => s.save(job()), { code: 'EMAIL_AUDIT_CAPACITY' });
  assert.equal(s.db.prepare('SELECT COUNT(*) AS n FROM email_audits').get().n, 20);
  assert.throws(() => s.save({ ...first, identity: 'b'.repeat(64) }), { code: 'EMAIL_AUDIT_IDENTITY' });
  assert.throws(() => s.write(first.audit_id, 'x', '/tmp/leak'), { code: 'EMAIL_AUDIT_SIZE' });
  assert.throws(() => s.write(first.audit_id, 'x'.repeat(10 * 1024 * 1024 + 1), '.results.json'), { code: 'EMAIL_AUDIT_SIZE' });
});

test('restart recovery reclaims only a demonstrably dead owner process and releases by nonce', t => {
  const s = fixture(t), first = job(); s.save(first);
  const child = spawnSync(process.execPath, ['-e', '']); assert.equal(child.status, 0);
  assert.throws(() => process.kill(child.pid, 0), { code: 'ESRCH' });
  s.db.prepare('INSERT INTO email_audit_locks VALUES(?,?,?,?)').run(owner, first.audit_id, child.pid, randomUUID());
  const release = s.lock(first.audit_id);
  assert.throws(() => s.lock(first.audit_id), { code: 'EMAIL_AUDIT_BUSY' });
  s.db.prepare('UPDATE email_audit_locks SET nonce=?').run('newer-owner'); release();
  assert.equal(s.db.prepare('SELECT nonce FROM email_audit_locks').get().nonce, 'newer-owner');
});
