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

process.umask(0o077);
try {
  const { config, registry } = loadConfig(process.env.MRN_OPERATIONS_CONFIG || '/etc/mrn-operations/service.json');
  const store = new Store(join(config.stateDir, 'operations.sqlite'));
  const qualifications = new Qualifications(() => readJson(config.qualificationsPath));
  const fleet = new FleetAdapter({ root: config.repositoryRoot, stateDir: config.stateDir, artifactRoots: config.artifactRoots, sourceRepositories: config.sourceRepositories, publicProbe: probe, qualifications });
  const qa = config.qa ? new QaAdapter({ ...config.qa, stateDir: config.stateDir, registry, store }) : null;
  const service = new Operations({ registry, store, fleet, qa, publicProbe: probe, writesEnabled: config.writesEnabled, connect: () => connectMainwp(config.mainwp) });
  const server = createHttpServer({ service, authenticate: createAuthenticator(config.auth), publicUrl: config.publicUrl, issuer: config.auth.issuer, origins: config.allowedOrigins });
  // Durable running records/locks are deliberately not reset on restart.
  // The operator must reconcile interrupted work before releasing the lock.
  server.listen(config.listen.port, config.listen.host, () => process.stderr.write('MRN Operations listening; hosted acceptance remains an independent gate.\n'));
  for (const signal of ['SIGTERM', 'SIGINT']) process.once(signal, () => {
    server.close(async () => { await Promise.allSettled([...service.jobs.values()]); store.close(); process.exit(0); });
  });
} catch { process.stderr.write('MRN Operations could not start. Validate the non-secret service configuration and runtime prerequisites.\n'); process.exitCode = 1; }
