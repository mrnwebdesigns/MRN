import { loadConfig } from './config.mjs';
import { policySchema, readJson } from './contracts.mjs';
try {
  const { config, registry } = loadConfig(process.env.MRN_OPERATIONS_CONFIG || '/etc/mrn-operations/service.json');
  const policy = policySchema.parse(readJson(config.policyPath));
  process.stdout.write(JSON.stringify({ valid: true, websites: registry.data().websites.length, members: policy.members.length,
    writesEnabled: config.writesEnabled, hostingVerified: false }) + '\n');
} catch { process.stderr.write('Configuration validation failed. Check required fields, absolute paths, exact environment identities and issuer URLs.\n'); process.exitCode = 1; }
