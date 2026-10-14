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
import { ProtectedCodeQa, codePolicySchema } from './code-qa.mjs';
import { CodeWorkflow } from './code-workflow.mjs';

process.umask(0o077);
try {
  const { config, registry } = loadConfig(process.env.MRN_OPERATIONS_CONFIG || '/etc/mrn-operations/service.json');
  const store = new Store(join(config.stateDir, 'operations.sqlite'));
  const qualifications = new Qualifications(() => readJson(config.qualificationsPath));
  const fleet = new FleetAdapter({ root: config.repositoryRoot, stateDir: config.stateDir, artifactRoots: config.artifactRoots, sourceRepositories: config.sourceRepositories, publicProbe: probe, qualifications });
  const qa = config.qa ? new QaAdapter({ ...config.qa, stateDir: config.stateDir, registry, store }) : null;
  const recoveryEvidence = config.recoveryEvidencePath ? new RecoveryEvidence(() => readJson(config.recoveryEvidencePath)) : null;
  const signer = config.delegation ? new IdentitySigner({ ...config.delegation, privateKey: readIdentityKey(config.delegation.privateKeyPath), connectionId: config.auth.googleWorkspace.connectionId }) : null;
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
  if (config.code) {
    const github = new GithubApp({ ...config.github, privateKey: readGithubKey(config.github.privateKeyPath) });
    service.codeWorkflow = new CodeWorkflow(service, { source: new CodeSource(github), qa: new ProtectedCodeQa(github, codePolicySchema.parse(readJson(config.code.policyPath))) });
  }
  const server = createHttpServer({ service, authenticate: createAuthenticator(config.auth, undefined, verifyStaff), publicUrl: config.publicUrl, issuer: config.auth.issuer, origins: config.allowedOrigins });
  // Durable running records/locks are deliberately not reset on restart.
  // The operator must reconcile interrupted work before releasing the lock.
  server.listen(config.listen.port, config.listen.host, () => process.stderr.write('MRN Operations listening; hosted acceptance remains an independent gate.\n'));
  for (const signal of ['SIGTERM', 'SIGINT']) process.once(signal, () => {
    server.close(async () => { await Promise.allSettled([...service.jobs.values()]); store.close(); process.exit(0); });
  });
} catch { process.stderr.write('MRN Operations could not start. Validate the non-secret service configuration and runtime prerequisites.\n'); process.exitCode = 1; }
