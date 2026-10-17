import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { generateKeyPairSync, randomBytes, verify } from 'node:crypto';
import { createLocalJWKSet, exportJWK, SignJWT } from 'jose';
import { digest } from '../../src/contracts.mjs';
import { delegationCanonical, delegationDigest } from '../../src/delegation.mjs';
import { ManagementWorkerContext, managementWorkerAudience } from '../../src/management-worker-context.mjs';
import { ManagementSourceBroker } from '../../src/management-source-broker.mjs';
import { managementEvidenceFixture } from './management-evidence.mjs';

const oidc = generateKeyPairSync('rsa',{modulusLength:2048});
const jwk = {...await exportJWK(oidc.publicKey),alg:'RS256',kid:'fixture-oidc'};
const keys = createLocalJWKSet({keys:[jwk]});
export async function sourceBrokerFixture(t, { bindRecipient = true, now = 1800000000000 } = {}) {
  const f = managementEvidenceFixture(t, ':memory:', now), p = f.dispatch();
  const identity = {keyId:'fixture-identity',...generateKeyPairSync('ed25519')};
  const registrar = {keyId:'fixture-registrar',...generateKeyPairSync('ed25519')};
  const pointer = {dispatch_id:p.dispatch_id,context_sha256:delegationDigest(f.ledger.captured(f.job).context)};
  const trust = {repositoryOwnerId:'98',environment:'MRN QA',runnerEnvironment:'github-hosted',subject:`repo:${p.repository}:environment:MRN QA`};
  const claims = {iss:'https://token.actions.githubusercontent.com',aud:managementWorkerAudience,sub:trust.subject,
    iat:f.state.now/1000,nbf:f.state.now/1000,exp:f.state.now/1000+60,jti:'source-test-original',repository:p.repository,
    repository_id:p.repository_id,repository_owner:'mrnwebdesigns',repository_owner_id:'98',repository_visibility:'private',
    run_id:p.run_id,run_attempt:'1',actor_id:'40',workflow_ref:`${p.repository}/${p.workflow_path}@${p.ref}`,
    workflow_sha:p.runner_source_sha,sha:p.runner_source_sha,ref:p.ref,ref_type:'branch',event_name:'workflow_dispatch',
    environment:trust.environment,runner_environment:'github-hosted'};
  const token = async () => `Bearer ${await new SignJWT(claims).setProtectedHeader({alg:'RS256',kid:'fixture-oidc'}).sign(oidc.privateKey)}`;
  const worker = new ManagementWorkerContext({ledger:f.ledger,identity,trust:()=>trust,keys,now:()=>f.state.now,
    monotonic:()=>f.state.mono,verifyStaff:async actor=>{assert.equal(actor.googleSubject,f.actor.googleSubject);await f.state.staff?.();}});
  const fixture = JSON.parse(readFileSync(new URL('./content-management-source-transfer-v1.json',import.meta.url)));
  fixture.context.management = f.ledger.captured(f.job).context;
  fixture.transfer_manifest.context = fixture.context;
  const manifest = Buffer.from(delegationCanonical(fixture.transfer_manifest));
  const slot = {...fixture.capture_output.slot,manifest_bytes:manifest.length,transfer_manifest_sha256:digest(manifest)};
  const source = {context:fixture.context,slot,ownerCatalogSha256:fixture.context.source_recipe_catalog_sha256};
  const frames = [{index:0,kind:'manifest',bytes:manifest.length,sha256:digest(manifest),data_base64:manifest.toString('base64')},fixture.read_output.frames[1]];
  let calls=0;
  const options = {worker,identity,registrar,now:()=>f.state.now,monotonic:()=>f.state.mono,
    resolveSource:async()=>{await f.state.resolve?.();return source;},reader:{async read(envelope){
      calls++; const raw=Buffer.from(envelope.payload_base64,'base64');
      assert.ok(verify(null,Buffer.concat([Buffer.from('mrn-content-management-source-read-v1:'),raw]),registrar.publicKey,Buffer.from(envelope.signature_base64,'base64')));
      const c=JSON.parse(raw); assert.equal(c.issuer,'mrn-content-qualification'); assert.equal(c.audience,'https://wpcontrol.mrndev.io');
      const value=structuredClone({schema:1,context:source.context,slot:source.slot,request_sha256:delegationDigest(c),batch_index:c.batch_index,frames});
      await f.state.backend?.(value); return value;
    }}};
  const broker=new ManagementSourceBroker(options), pair=generateKeyPairSync('x25519');
  const enrollment={...pointer,public_key_base64:pair.publicKey.export({type:'spki',format:'der'}).toString('base64'),nonce:'7'.repeat(64)};
  const enroll=async()=>broker.enroll(await token(),enrollment);
  const enrolled=bindRecipient ? JSON.parse(Buffer.from((await enroll()).signed_recipient.payload_base64,'base64')) : null;
  const request=()=>({...pointer,recipient_id:enrolled.recipient_id,batch_index:0,nonce:randomBytes(32).toString('hex')});
  const read=async(input=request())=>broker.read(await token(),input);
  return {...f,p,identity,registrar,worker,claims,source,frames,options,broker,pair,pointer,enrollment,enrolled,token,enroll,request,read,
    get calls(){return calls;}};
}
