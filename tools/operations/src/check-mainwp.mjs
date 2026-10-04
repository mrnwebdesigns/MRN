import { loadConfig } from './config.mjs';
import { connectMainwp, validateStatus } from './mainwp.mjs';
import { safeError } from './contracts.mjs';
let client;
try {
  const { config } = loadConfig(process.env.MRN_OPERATIONS_CONFIG || '/etc/mrn-operations/service.json');
  client = await connectMainwp(config.mainwp);
  const status = validateStatus(await client.readResource({ uri: 'mainwp://status' }));
  let cursor; const tools = [];
  do { const page = await client.listTools(cursor ? { cursor } : {}); tools.push(...page.tools.map(t => t.name)); cursor = page.nextCursor; } while (cursor);
  process.stdout.write(JSON.stringify({ ...status, discoveredTools: tools.length, siteCalls: 0, writes: 0 }) + '\n');
} catch (error) { process.stderr.write(JSON.stringify(safeError(error)) + '\n'); process.exitCode = 1; }
finally { await client?.close(); }
