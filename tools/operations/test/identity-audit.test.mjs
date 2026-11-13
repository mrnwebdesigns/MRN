import test from 'node:test';
import assert from 'node:assert/strict';
import { DatabaseSync } from 'node:sqlite';
import { mkdtempSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { Store } from '../src/store.mjs';

test('identity audit migration preserves historical events and uncertain operation locks', t => {
  const root = mkdtempSync(join(tmpdir(), 'mrn-audit-migration-')); t.after(() => rmSync(root, { recursive: true, force: true })); const path = join(root, 'old.sqlite');
  const old = new DatabaseSync(path);
  old.exec("CREATE TABLE audit(seq INTEGER PRIMARY KEY, time TEXT NOT NULL, subject TEXT NOT NULL, target TEXT NOT NULL, operation TEXT NOT NULL, event TEXT NOT NULL, outcome TEXT NOT NULL); INSERT INTO audit VALUES(1,'historical','old-person','target','operation','execute','uncertain'); CREATE TABLE locks(target TEXT PRIMARY KEY, operation TEXT NOT NULL UNIQUE); INSERT INTO locks VALUES('target','operation');"); old.close();
  const store = new Store(path);
  assert.equal(store.db.prepare('SELECT identity FROM audit WHERE seq=1').get().identity, '{}');
  assert.equal(store.db.prepare('SELECT operation FROM locks WHERE target=?').get('target').operation, 'operation');
  store.audit({ subject: 'google-oauth2|123', googleSubject: '123', verifiedEmail: 'staff@mrnwebdesigns.com', identityProvider: 'google-oauth2', token: 'never-store-this' },
    { url: 'https://fixture.test' }, 'new-operation', 'inspect', 'returned');
  const events = store.db.prepare('SELECT * FROM audit').all();
  assert.deepEqual(JSON.parse(events[1].identity), { googleSubject: '123', email: 'staff@mrnwebdesigns.com', provider: 'google-oauth2' });
  assert.equal(JSON.stringify(events).includes('never-store-this'), false); store.close();
  const again = new Store(path); assert.equal(again.db.prepare('SELECT count(*) AS n FROM audit').get().n, 2); again.close();
});
