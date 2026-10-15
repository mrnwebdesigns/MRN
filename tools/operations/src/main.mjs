import { join } from 'node:path';
import { loadConfig } from './config.mjs';
import { Store } from './store.mjs';
import { connectMainwp } from './mainwp.mjs';
import { FleetAdapter } from './fleet.mjs';
import { Operations } from './service.mjs';
import { probe } from './probe.mjs';
import { QaAdapter } from './qa.mjs';
import { createAuthenticator } from './auth.mjs';
import { createHttpServer } from './http.mjs';
import { Qualifications } from './qualification.mjs';
import { readJson } from './contracts.mjs';
import { RecoveryEvidence } from './recovery.mjs';
import { Discovery } from './discovery.mjs';
import { IdentitySigner, readIdentityKey } from './delegation.mjs';
import { DelegatedMainwpClient } from './delegated-mainwp.mjs';
import { NativeContentReader } from './content.mjs';
import { GithubApp, CodeSource, readGithubKey } from './github.mjs';
import { ProtectedCodeQa } from './code-qa.mjs';
import { CodeWorkflow } from './code-workflow.mjs';
import { ContentWorkflow } from './content-workflow.mjs';
import { ProtectedContentQa } from './content-qa.mjs';
import { createContentAccessRuntime } from './content-access-runtime.mjs';

process.umask(0o077);
let store, contentAccess;
try {
  const { config, registry, workflowPolicies } = loadConfig(process.env.MRN_OPERATIONS_CONFIG || '/etc/mrn-operations/service.json');
  store = new Store(join(config.stateDir, 'operations.sqlite'));
  const qualifications = new Qualifications(() => readJson(config.qualificationsPath));
  const fleet = new FleetAdapter({ root: config.repositoryRoot, stateDir: config.stateDir, artifactRoots: config.artifactRoots, sourceRepositories: config.sourceRepositories, publicProbe: probe, qualifications });
  const qa = config.qa ? new QaAdapter({ ...config.qa, stateDir: config.stateDir, registry, store }) : null;
  const recoveryEvidence = config.recoveryEvidencePath ? new RecoveryEvidence(() => readJson(config.recoveryEvidencePath)) : null;
  const identity = config.delegation ? { ...config.delegation, privateKey: readIdentityKey(config.delegation.privateKeyPath) } : null;
  const signer = identity ? new IdentitySigner({ ...identity, connectionId: config.auth.googleWorkspace.connectionId }) : null;
  const connect = async actor => {
    const client = await connectMainwp(config.mainwp);
    return signer ? new DelegatedMainwpClient(client, actor, signer) : client;
  };
  const verifyStaff = async actor => {
    registry.member(actor); // Explicit local revocations still override the domain grant.
    const client = await connect(actor);
    try { await client.verifyStaff(); } finally { await client.close(); }
  };
  const discovery = config.discovery ? new Discovery({ registry, store, connect, ...config.discovery }) : null;
  const content = config.content ? new NativeContentReader({ token: () => process.env[config.content.restKeyEnv] }) : null;
  const service = new Operations({ registry, store, fleet, qa, recoveryEvidence, discovery, content, verifyStaff: signer ? verifyStaff : null, publicProbe: probe, writesEnabled: config.writesEnabled, connect });
  if (config.github) {
    const github = new GithubApp({ ...config.github, privateKey: readGithubKey(config.github.privateKeyPath) });
    if (workflowPolicies.code) service.codeWorkflow = new CodeWorkflow(service, { source: new CodeSource(github), qa: new ProtectedCodeQa(github, workflowPolicies.code) });
    if (workflowPolicies.content) service.contentWorkflow = new ContentWorkflow(service, { qa: new ProtectedContentQa(github, workflowPolicies.content) });
  }
  contentAccess = await createContentAccessRuntime({ enabled: config.contentAccess, store, registry, identity,
    connectionId: config.auth.googleWorkspace?.connectionId, connect: () => connectMainwp(config.mainwp) });
  const server = createHttpServer({ service, authenticate: createAuthenticator(config.auth, undefined, verifyStaff), publicUrl: config.publicUrl, issuer: config.auth.issuer, origins: config.allowedOrigins, accessVeto: contentAccess?.veto });
  // Durable running records/locks are deliberately not reset on restart.
  // The operator must reconcile interrupted work before releasing the lock.
  await new Promise((resolve, reject) => {
    const failed = error => reject(error);
    server.once('error', failed);
    server.listen(config.listen.port, config.listen.host, () => { server.off('error', failed); resolve(); });
  });
  process.stderr.write('MRN Operations listening; hosted acceptance remains an independent gate.\n');
  for (const signal of ['SIGTERM', 'SIGINT']) process.once(signal, () => {
    server.close(async () => {
      await Promise.allSettled([...service.jobs.values()]);
      let exitCode = 0;
      try { await contentAccess?.close(); } catch { exitCode = 1; }
      store.close(); process.exit(exitCode);
    });
  });
} catch {
  await contentAccess?.close().catch(() => {});
  store?.close();
  process.stderr.write('MRN Operations could not start. Validate the non-secret service configuration and runtime prerequisites.\n'); process.exitCode = 1;
}
