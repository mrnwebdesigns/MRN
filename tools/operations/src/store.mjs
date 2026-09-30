import { DatabaseSync } from 'node:sqlite';
import { randomUUID } from 'node:crypto';
import { mkdirSync, chmodSync } from 'node:fs';
import { dirname } from 'node:path';
import { digest, OpsError, requireThat, targetKey } from './contracts.mjs';

export class Store {
  constructor(file) {
    if (file !== ':memory:') mkdirSync(dirname(file), { recursive: true, mode: 0o700 });
    this.db = new DatabaseSync(file);
    if (file !== ':memory:') chmodSync(file, 0o600);
    this.db.exec(`PRAGMA journal_mode=WAL; PRAGMA synchronous=FULL; PRAGMA busy_timeout=5000;
      CREATE TABLE IF NOT EXISTS records(id TEXT PRIMARY KEY, type TEXT NOT NULL, target TEXT NOT NULL, body TEXT NOT NULL);
      CREATE TABLE IF NOT EXISTS requests(subject TEXT NOT NULL, key TEXT NOT NULL, fingerprint TEXT NOT NULL, record_id TEXT NOT NULL, PRIMARY KEY(subject,key));
      CREATE TABLE IF NOT EXISTS locks(target TEXT PRIMARY KEY, operation TEXT NOT NULL UNIQUE);
      CREATE TABLE IF NOT EXISTS audit(seq INTEGER PRIMARY KEY, time TEXT NOT NULL, subject TEXT NOT NULL, target TEXT NOT NULL, operation TEXT NOT NULL, event TEXT NOT NULL, outcome TEXT NOT NULL);`);
  }
  transaction(fn) { this.db.exec('BEGIN IMMEDIATE'); try { const r = fn(); this.db.exec('COMMIT'); return r; } catch (e) { this.db.exec('ROLLBACK'); throw e; } }
  close() { this.db.close(); }
  audit(actor, target, operation, event, outcome) {
    // Never store tool arguments, response bodies, JWTs, passwords, confirmation tokens or backup receipts.
    this.db.prepare('INSERT INTO audit(time,subject,target,operation,event,outcome) VALUES(?,?,?,?,?,?)')
      .run(new Date().toISOString(), actor.subject, targetKey(target), operation || '', event, outcome);
  }
  get(id) { const row = this.db.prepare('SELECT body FROM records WHERE id=?').get(id); requireThat(row, 'NOT_FOUND', 'Record not found.'); return JSON.parse(row.body); }
  save(record) {
    record.updatedAt = new Date().toISOString();
    this.db.prepare('INSERT INTO records(id,type,target,body) VALUES(?,?,?,?) ON CONFLICT(id) DO UPDATE SET body=excluded.body')
      .run(record.id, record.type, targetKey(record.target), JSON.stringify(record));
    return record;
  }
  create(type, actor, target, body = {}) {
    return this.save({ ...body, id: randomUUID(), type, target, requestedBy: actor.subject, createdAt: new Date().toISOString() });
  }
  history(target, type) {
    const rows = type ? this.db.prepare('SELECT body FROM records WHERE target=? AND type=? ORDER BY rowid DESC LIMIT 50').all(targetKey(target), type)
      : this.db.prepare('SELECT body FROM records WHERE target=? ORDER BY rowid DESC LIMIT 50').all(targetKey(target));
    return rows.map(r => JSON.parse(r.body));
  }
  request(actor, key, input, create) {
    requireThat(/^[a-zA-Z0-9._:-]{1,120}$/.test(key || ''), 'REQUEST_KEY_REQUIRED', 'Supply a stable request key for this operation.');
    return this.transaction(() => {
      const fingerprint = digest(input);
      const existing = this.db.prepare('SELECT * FROM requests WHERE subject=? AND key=?').get(actor.subject, key);
      if (existing) {
        requireThat(existing.fingerprint === fingerprint, 'REQUEST_CONFLICT', 'This request key was already used for different input.');
        return { record: this.get(existing.record_id), created: false };
      }
      const record = create();
      this.db.prepare('INSERT INTO requests VALUES(?,?,?,?)').run(actor.subject, key, fingerprint, record.id);
      return { record, created: true };
    });
  }
  approve(id, actor, planDigest) {
    return this.transaction(() => {
      const op = this.get(id);
      requireThat(['prepared', 'approved'].includes(op.status), 'STATE_CONFLICT', 'This operation cannot be approved in its current state.');
      requireThat(op.planDigest === planDigest, 'PLAN_CHANGED', 'Approval must name the exact prepared plan digest.');
      requireThat(op.expiresAt > Date.now(), 'PLAN_EXPIRED', 'The plan expired. Inspect and prepare again.');
      if (op.status === 'approved') return op;
      op.status = 'approved'; op.approvedBy = actor.subject; op.approvedAt = new Date().toISOString();
      this.audit(actor, op.target, id, 'approve', 'approved'); return this.save(op);
    });
  }
  claim(id, actor) {
    return this.transaction(() => {
      const op = this.get(id);
      if (['running', 'verified', 'uncertain', 'failed', 'blocked'].includes(op.status)) return { record: op, claimed: false };
      requireThat(op.status === 'approved' && op.expiresAt > Date.now(), 'APPROVAL_REQUIRED', 'A current exact-plan approval is required.');
      try { this.db.prepare('INSERT INTO locks VALUES(?,?)').run(targetKey(op.target), id); }
      catch { throw new OpsError('SITE_BUSY', 'Another workflow holds this website lock. An uncertain operation requires reconciliation before another write.'); }
      op.status = 'running'; op.executedBy = actor.subject; op.startedAt = new Date().toISOString();
      this.audit(actor, op.target, id, 'execute', 'running');
      return { record: this.save(op), claimed: true };
    });
  }
  finish(id, status, body, actor) {
    return this.transaction(() => {
      const op = this.get(id);
      Object.assign(op, body, { status });
      if (status !== 'uncertain') this.db.prepare('DELETE FROM locks WHERE operation=?').run(id);
      this.audit(actor, op.target, id, 'execute', status); return this.save(op);
    });
  }
}
