import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync } from 'node:crypto';
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { ReadableStream } from 'node:stream/web';
import { setImmediate } from 'node:timers';
import { digest } from '../src/contracts.mjs';
import { delegationDigest } from '../src/delegation.mjs';
import { GithubApp } from '../src/github.mjs';
import { ManagementSourceCacheRegistry, managementSourceRepository as repository } from '../src/management-source-cache.mjs';

const key=generateKeyPairSync('rsa',{modulusLength:2048});
const json=(value,status=200)=>new Response(JSON.stringify(value),{status,headers:{'content-type':'application/json'}});
const directories=()=>new Set(readdirSync(tmpdir()).filter(name=>name.startsWith('mrn-source-cache-')));
function fixture(options={}){
  const now=1800000000000,state={now,denied:false},calls=[],raw=Buffer.alloc(options.size||2097152,65);
  const catalog={artifact_id:'approved-cache-fixture',root_kind:'directory',relative_inventory_sha256:'a'.repeat(64),
    archive_sha256:digest('distinct inner source archive'),archive_format:'zip',maximum_archive_bytes:raw.length-100};
  const entry={catalog_artifact:catalog,github_artifact_id:'456',github_artifact_sha256:digest(raw),github_artifact_bytes:raw.length,
    source_archive_bytes:raw.length-100,run_id:'123',source_sha:'b'.repeat(40),created_at:now/1000-60,expires_at:now/1000+3600};
  const producer={workflow_id:'321',workflow_path:'.github/workflows/management-source-cache.yml',app_actor_id:'987'};
  const registry={schema:2,repository,repository_id:'789',producer,entries:[entry]};let pin=delegationDigest(registry);
  const run={id:123,repository:{id:789,full_name:repository,private:true},head_repository:{id:789,full_name:repository},
    workflow_id:321,path:producer.workflow_path,head_sha:entry.source_sha,head_branch:'main',event:'workflow_dispatch',
    run_attempt:1,status:'completed',conclusion:'success',actor:{id:987},triggering_actor:{id:987},
    created_at:new Date(now-120000).toISOString(),updated_at:new Date(now-30000).toISOString()};
  const metadata={id:456,expired:false,digest:'sha256:'+digest(raw),size_in_bytes:raw.length,
    workflow_run:{id:123,repository_id:789,head_sha:entry.source_sha},created_at:new Date(entry.created_at*1000).toISOString(),expires_at:new Date(entry.expires_at*1000).toISOString()};
  const fetchImpl=async(input,request)=>{
    const url=String(input);calls.push({url,request});
    if(url.endsWith('/access_tokens')){
      assert.deepEqual(JSON.parse(request.body),{repository_ids:[789],permissions:{contents:'read',actions:'read'}});
      return options.grant?.(state)||json({token:'fixture-installation-token',expires_at:new Date(now+3600000).toISOString(),
        repositories:[{id:789,full_name:repository}],permissions:{contents:'read',actions:'read',metadata:'read'}},201);
    }
    if(options.fetch){const response=await options.fetch(url,request,state,raw,metadata);if(response)return response;}
    if(url===`https://api.github.com/repos/${repository}/actions/runs/123`){assert.equal(request.headers.Authorization,'Bearer fixture-installation-token');return json(run);}
    if(url===`https://api.github.com/repos/${repository}/actions/artifacts/456`){assert.equal(request.headers.Authorization,'Bearer fixture-installation-token');return json(metadata);}
    if(url.endsWith('/zip'))return new Response('',{status:302,headers:{location:'https://fixture.blob.core.windows.net/data?signature=synthetic'}});
    assert.equal(url,'https://fixture.blob.core.windows.net/data?signature=synthetic');assert.deepEqual(request.headers,{'Accept-Encoding':'identity'});
    let offset=0;return new Response(new ReadableStream({pull(controller){if(offset===raw.length){controller.close();return;}
      const end=Math.min(offset+65536,raw.length);controller.enqueue(raw.subarray(offset,end));offset=end;}}),{headers:{'Content-Length':String(raw.length)}});
  };
  const github=new GithubApp({appId:1,installationId:2,privateKey:key.privateKey,repositories:{[repository]:789},fetchImpl,now:()=>state.now});
  const cache=new ManagementSourceCacheRegistry({current:()=>registry,expectedSha256:()=>pin,github,now:()=>state.now});
  const acquire=(expiresAt=state.now+60000)=>cache.acquire(catalog,{expiresAt,authorize:async()=>{if(state.denied)throw new Error('original access revoked');}});
  return{catalog,entry,registry,producer,run,metadata,cache,github,state,calls,raw,acquire,repin:()=>{pin=delegationDigest(registry);}};
}

test('empty or missing cache fails explicitly without acquiring credentials or accepting caller repository selectors',async()=>{
  const f=fixture();assert.throws(()=>new ManagementSourceCacheRegistry().resolve(f.catalog),{code:'MANAGEMENT_SOURCE_MISSING_CACHE'});
  f.registry.entries=[];f.repin();await assert.rejects(f.acquire,{code:'MANAGEMENT_SOURCE_MISSING_CACHE'});assert.equal(f.calls.length,0);
  for(const extra of [{repository:'outsider/repo'},{url:'https://untrusted.test'},{token:'caller'}])assert.throws(()=>f.cache.resolve({...f.catalog,...extra}));
});

test('approved opaque artifact streams to private storage with actual hash and exact metadata, without extraction or source admission',async()=>{
  const f=fixture({size:8388608}),result=await f.acquire();
  try{
    assert.equal(result.archiveSha256,digest(f.raw));assert.deepEqual(readFileSync(result.archivePath),f.raw);assert.equal(result.bytes,f.raw.length);
    assert.notEqual(result.archiveSha256,f.catalog.archive_sha256);
    assert.equal(result.sourceArchiveSha256,f.catalog.archive_sha256);assert.equal(result.sourceArchiveBytes,f.entry.source_archive_bytes);
    assert.equal(statSync(result.archivePath).mode&0o777,0o600);assert.equal(result.metadataSha256,digest(JSON.stringify(f.metadata)));
    assert.equal(result.producerRunSha256,digest(JSON.stringify(f.run)));assert.equal(result.producerRunVerified,true);
    assert.equal(result.inventoryVerified,false);assert.equal(result.sourceBytesVerified,false);assert.equal(result.admitted,false);
    assert.equal(f.calls.length,6);assert.equal(f.calls.filter(c=>c.request.method==='POST').length,1);
  }finally{result.cleanup();}assert.equal(existsSync(result.archivePath),false);
});

test('outer artifact and inner source metadata cannot be substituted or inferred from legacy registry entries',async()=>{
  for(const change of [f=>{delete f.entry.github_artifact_sha256;f.repin();},
    f=>{delete f.entry.github_artifact_bytes;f.repin();},f=>{delete f.entry.source_archive_bytes;f.repin();},
    f=>{f.entry.source_archive_bytes=f.catalog.maximum_archive_bytes+1;f.repin();}]){
    const f=fixture();change(f);await assert.rejects(f.acquire);assert.equal(f.calls.length,0);
  }
  for(const change of [f=>{f.metadata.digest='sha256:'+f.catalog.archive_sha256;},
    f=>{f.metadata.size_in_bytes=f.entry.source_archive_bytes;},
    f=>{f.entry.github_artifact_sha256=f.catalog.archive_sha256;f.repin();}]){
    const f=fixture();change(f);await assert.rejects(f.acquire);assert.equal(f.calls.length,3);
  }
});

test('unapproved registry edits, duplicate IDs and changed catalog hashes cannot obtain an App grant',async()=>{
  for(const change of [f=>{f.entry.source_sha='f'.repeat(40);},f=>{f.registry.entries.push(structuredClone(f.entry));f.repin();},
    f=>{f.catalog.relative_inventory_sha256='f'.repeat(64);},f=>{f.entry.expires_at=f.state.now/1000;f.repin();},
    f=>{f.registry.repository='outsider/repo';f.repin();}]){
    const f=fixture();change(f);await assert.rejects(f.acquire);assert.equal(f.calls.length,0);
  }
});

test('legacy or incomplete producer configuration cannot request a cache grant',async()=>{
  for(const change of [f=>{f.registry.schema=1;},f=>{delete f.registry.producer;},f=>{delete f.producer.app_actor_id;},
    f=>{f.producer.workflow_path='.github/workflows/unreviewed.yml';},f=>{f.producer.url='https://untrusted.test';}]){
    const f=fixture();change(f);f.repin();await assert.rejects(f.acquire);assert.equal(f.calls.length,0);
  }
});

test('unreviewed or incomplete producer runs stop before artifact metadata and bytes',async()=>{
  for(const change of [r=>{r.id++;},r=>{r.workflow_id++;},r=>{r.path='.github/workflows/other.yml';},
    r=>{r.repository.id++;},r=>{r.repository.full_name='outsider/repo';},r=>{r.repository.private=false;},
    r=>{r.head_repository.id++;},r=>{r.head_repository.full_name='outsider/fork';},r=>{r.head_sha='f'.repeat(40);},
    r=>{r.head_branch='candidate';},r=>{r.event='pull_request';},r=>{r.run_attempt=2;},r=>{r.actor.id++;},
    r=>{r.triggering_actor.id++;},r=>{r.status='in_progress';},r=>{r.conclusion='failure';},
    r=>{r.created_at=r.updated_at;},r=>{r.updated_at='2099-01-01T00:00:00Z';}]){
    const f=fixture();change(f.run);await assert.rejects(f.acquire);assert.equal(f.calls.length,2);
    assert.equal(f.calls.some(c=>c.url.includes('/artifacts/')),false);
  }
});

test('producer rerun or changed completed observation after download removes private bytes',async()=>{
  for(const change of [r=>{r.run_attempt=2;},r=>{r.status='in_progress';},r=>{r.actor.id++;},
    r=>{r.updated_at=new Date(Date.parse(r.updated_at)+1000).toISOString();}]){
    const before=directories();let reads=0;
    const f=fixture({fetch:url=>{
      if(url.endsWith('/actions/runs/123')&&++reads===2){const changed=structuredClone(f.run);change(changed);return json(changed);}
    }});
    await assert.rejects(f.acquire);assert.equal(reads,2);assert.deepEqual(directories(),before);
  }
});

test('original access loss during producer observation prevents source artifact access',async()=>{
  const f=fixture({fetch:(url,request,state)=>{if(url.endsWith('/actions/runs/123'))state.denied=true;}});
  await assert.rejects(f.acquire);assert.equal(f.calls.length,2);assert.equal(f.calls.some(c=>c.url.includes('/artifacts/')),false);
});

test('foreign, expired, oversized and mismatched GitHub artifact observations stop before archive download',async()=>{
  for(const change of [m=>{m.id++;},m=>{m.workflow_run.repository_id++;},m=>{m.workflow_run.id++;},m=>{m.workflow_run.head_sha='f'.repeat(40);},
    m=>{m.expired=true;},m=>{m.digest='sha256:'+'f'.repeat(64);},m=>{m.size_in_bytes++;},m=>{m.expires_at='2099-01-01T00:00:00Z';}]){
    const f=fixture();change(f.metadata);await assert.rejects(f.acquire);assert.equal(f.calls.length,3);
  }
});

test('corrupt, compressed, partial and oversized archive bytes leave no private temp files',async()=>{
  for(const response of [()=>new Response('changed'),()=>new Response('compressed',{headers:{'content-encoding':'gzip'}}),
    ()=>new Response('partial',{headers:{'content-length':'100'}}),()=>new Response('huge',{headers:{'content-length':'536870913'}})]){
    const before=directories(),f=fixture({fetch:url=>url.startsWith('https://fixture.blob')?response():null});
    await assert.rejects(f.acquire);assert.deepEqual(directories(),before);
  }
});

test('storage redirects strip authorization and reject foreign origins, chained redirects and rejected App scopes',async()=>{
  for(const location of ['https://outsider.test/data','http://fixture.blob.core.windows.net/data','https://u:p@fixture.blob.core.windows.net/data',
    'https://fixture.blob.core.windows.net:444/data','https://fixture.blob.core.windows.net/data#fragment']){
    const f=fixture({fetch:url=>url.endsWith('/zip')?new Response('',{status:302,headers:{location}}):null});
    await assert.rejects(f.acquire);assert.equal(f.calls.length,4);
  }
  const f=fixture({grant:()=>json({message:'denied'},403)});await assert.rejects(f.acquire);assert.equal(f.calls.length,1);
});

test('late responses and original access revoked during downloads cannot leave files or emit archive paths',async()=>{
  const before=directories();let finish;
  const f=fixture({fetch:url=>url.startsWith('https://fixture.blob')?new Promise(resolve=>{finish=resolve;}):null});
  await assert.rejects(()=>f.acquire(f.state.now+50));assert.equal(typeof finish,'function');
  finish(new Response(f.raw));await new Promise(resolve=>setTimeout(resolve,20));assert.deepEqual(directories(),before);
  const g=fixture({fetch:(url,request,state,raw)=>{if(!url.startsWith('https://fixture.blob'))return null;state.denied=true;return new Response(raw);}});
  await assert.rejects(g.acquire);assert.deepEqual(directories(),before);
});

test('cache acquisition has two process-wide slots before any installation grant',async()=>{
  const releases=[];
  const options={fetch:(url,request,state,raw)=>url.startsWith('https://fixture.blob')
    ?new Promise(resolve=>{releases.push(()=>resolve(new Response(raw)));}):null};
  const a=fixture(options),b=fixture(options),c=fixture(),first=a.acquire(),second=b.acquire();
  while(releases.length<2)await new Promise(resolve=>setImmediate(resolve));
  await assert.rejects(c.acquire);assert.equal(c.calls.length,0);
  releases.forEach(release=>release());for(const result of await Promise.all([first,second]))result.cleanup();
});
