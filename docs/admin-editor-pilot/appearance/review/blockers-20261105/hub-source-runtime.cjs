'use strict';
const fs=require('fs'),Module=require('module'),path=require('path');
const root='/Users/khofmeyer/Development/MRN/.worktrees/hub-pilot-performance-20261105';
const filename=path.join(root,'server.js'),source=fs.readFileSync(filename,'utf8');
const stop=source.indexOf('const server = http.createServer(');if(stop<0)throw Error('Hub entry point changed');
const m=new Module(filename,module);m.filename=filename;m.paths=[...Module._nodeModulePaths(root),'/Users/khofmeyer/Development/MRN-local-hub/node_modules'];
// Exercise the unmodified production functions without starting a second Hub,
// publishing an endpoint, or replacing the installed application.
m._compile(source.slice(0,stop)+'\nmodule.exports={readManifest,provisionSite};',filename);
(async()=>{
 const s=await m.exports.readManifest('/Users/khofmeyer/Development/MRN-sites/admin-editor-pilot');
 if(s.slug!=='admin-editor-pilot'||s.localUrl!=='https://admin-editor-pilot.localhost')throw Error('Pilot identity mismatch');
 const start=Date.now();const r=await m.exports.provisionSite(s);const stored=JSON.parse(fs.readFileSync(path.join(s.localRoot,'.mrn-site.json')));
 const out={sourceCommit:'8d0ce21',installedAppChanged:false,elapsedMs:Date.now()-start,code:r.code,command:r.command,stdout:r.stdout,stderr:r.stderr,status:stored.runtimeStatus,updatedAt:stored.updatedAt,dbUrls:r.dbUrls?{code:r.dbUrls.code,skipped:r.dbUrls.skipped,stdout:r.dbUrls.stdout,durationMs:r.dbUrls.durationMs}:null};
 fs.writeFileSync('/private/tmp/mrn-blockers-20261105/hub-source-runtime.json',JSON.stringify(out,null,2));console.log(JSON.stringify(out));if(r.code!==0)process.exitCode=1;
})().catch(e=>{console.error(e.message);process.exitCode=1});
