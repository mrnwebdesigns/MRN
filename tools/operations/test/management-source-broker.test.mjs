import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createDecipheriv, createPublicKey, diffieHellman, hkdfSync, verify } from 'node:crypto';
import { digest } from '../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../src/delegation.mjs';
import { ManagementSourceBroker } from '../src/management-source-broker.mjs';
import { sourceBatchPurpose, sourceCipherPurpose, sourceRecipientPurpose } from '../src/management-source-transfer.mjs';
import { sourceBrokerFixture } from './fixtures/management-source.mjs';
import { setImmediate } from 'node:timers';


test('original OIDC run enrolls one private recipient and observes actual encrypted provider bytes',async t=>{
  const f=await sourceBrokerFixture(t), response=await f.read();
  const signed=Buffer.from(response.signed_transfer.payload_base64,'base64');
  assert.ok(verify(null,Buffer.concat([Buffer.from(sourceBatchPurpose),signed]),f.identity.publicKey,Buffer.from(response.signed_transfer.signature_base64,'base64')));
  assert.deepEqual(JSON.parse(signed),{...response.header,encrypted_sha256:delegationDigest(response.encrypted)});
  assert.equal(response.header.dispatch_record_sha256,f.p.dispatch_record_sha256);
  assert.equal(response.header.source_context_sha256,delegationDigest(f.source.context));
  const encrypted=response.encrypted,publicKey=createPublicKey({key:Buffer.from(encrypted.ephemeral_public_key_base64,'base64'),type:'spki',format:'der'});
  const secret=diffieHellman({privateKey:f.pair.privateKey,publicKey}),aad=Buffer.from(delegationCanonical(response.header));
  const key=Buffer.from(hkdfSync('sha256',secret,Buffer.from(f.enrolled.recipient_id),Buffer.concat([Buffer.from(sourceCipherPurpose),Buffer.from(digest(aad),'hex')]),32));
  const decipher=createDecipheriv('aes-256-gcm',key,Buffer.from(encrypted.iv_base64,'base64'));
  decipher.setAAD(aad);decipher.setAuthTag(Buffer.from(encrypted.tag_base64,'base64'));
  const plain=Buffer.concat([decipher.update(Buffer.from(encrypted.ciphertext_base64,'base64')),decipher.final()]);
  assert.equal(digest(plain),response.header.response_sha256);
  const body=JSON.parse(plain);assert.equal(body.frames[1].data_base64,'YWJj');assert.equal(body.slot.full_source_verified,false);
  const stored=JSON.stringify(f.store.db.prepare('SELECT * FROM management_source_reads').all());
  for(const value of ['data_base64','post_content','never-store-this-token',f.actor.verifiedEmail])assert.equal(stored.includes(value),false);
  secret.fill(0);key.fill(0);plain.fill(0);
});

test('one-run recipient cannot be replaced, reset after restart, or supplied by a different OIDC run',async t=>{
  const f=await sourceBrokerFixture(t);await assert.rejects(f.enroll);
  const restarted=new ManagementSourceBroker(f.options);
  await assert.rejects(()=>restarted.enroll('',f.enrollment));
  const authorization=await f.token();await assert.rejects(()=>restarted.enroll(authorization,f.enrollment));
  f.claims.run_id='31';await assert.rejects(f.read);assert.equal(f.calls,0);
  f.claims.run_id='30';
  for(const extra of [{email:f.actor.verifiedEmail},{slot:f.source.slot},{url:'https://untrusted.test'}])await assert.rejects(()=>f.read({...f.request(),...extra}));
  assert.equal(f.calls,0);
});

test('changed provider frames, counts, slot, context, hash and request binding are withheld',async t=>{
  for(const mutate of [v=>{v.frames[1].data_base64='YWJk';},v=>{v.frames.reverse();},v=>{v.frames.pop();},
    v=>{v.frames[0].kind='native';},v=>{v.slot.manifest_bytes++;},v=>{v.request_sha256='f'.repeat(64);},
    v=>{v.context.management.binding.site_id++;},v=>{v.frames[1].bytes++;},v=>{v.extra=true;}]){
    const f=await sourceBrokerFixture(t);f.state.backend=mutate;await assert.rejects(f.read);
    assert.equal(f.store.db.prepare('SELECT state FROM management_source_reads').get().state,'failed');
  }
});

test('nonce replay, pending parallel reads and recovery limits consume durable original counters',async t=>{
  const f=await sourceBrokerFixture(t), request=f.request();await f.read(request);await assert.rejects(()=>f.read(request));assert.equal(f.calls,1);
  for(let i=0;i<8;i++)await f.read();await assert.rejects(f.read);assert.equal(f.calls,9);
  const g=await sourceBrokerFixture(t);let release;g.state.backend=()=>new Promise(resolve=>{release=resolve;});
  const pending=g.read();while(!release)await new Promise(resolve=>setImmediate(resolve));
  await assert.rejects(g.read);release();await pending;assert.equal(g.calls,1);
});

test('revocation, original expiry, source-policy drift and elapsed deadlines withhold bytes after backend acquisition',async t=>{
  for(const mutate of [f=>f.access.recordPendingRevocation(f.job),f=>{f.policy.policy_epoch++;},
    f=>{f.state.mono=20000;},f=>{f.state.now+=20000;},f=>{f.state.now--;},
    f=>{f.source.ownerCatalogSha256='f'.repeat(64);},f=>{f.state.staff=()=>{throw new Error('suspended');};}]){
    const f=await sourceBrokerFixture(t);f.state.backend=()=>mutate(f);await assert.rejects(f.read);assert.equal(f.calls,1);
    assert.equal(f.store.db.prepare('SELECT state FROM management_source_reads').get().state,'failed');
  }
  const f=await sourceBrokerFixture(t);let resolutions=0;
  f.state.resolve=()=>{if(++resolutions===2)f.state.now+=5000;};
  await assert.rejects(f.read);assert.equal(f.calls,1);
  assert.equal(f.store.db.prepare('SELECT state FROM management_source_reads').get().state,'failed');
});

test('frozen provider bytes and recipient purpose remain separate from report admission and production startup',()=>{
  assert.equal(digest(readFileSync(new URL('../contracts/content-management-source-transfer-v1.json',import.meta.url))),
    '36320f6d2d5dac5523ecc68b0fa463c2fa1246cbf75f81e3209c055aa196f3e9');
  assert.notEqual(sourceRecipientPurpose,sourceBatchPurpose);
  const main=readFileSync(new URL('../src/main.mjs',import.meta.url),'utf8');assert.equal(main.includes('ManagementSourceBroker'),false);
});
