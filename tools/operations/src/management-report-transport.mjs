import { requireThat } from './contracts.mjs';
import { delegationCanonical } from './delegation.mjs';
import { createPrivateQueryReader } from './access-control.mjs';
import { payload } from './mainwp.mjs';
import { managementReportTypes as types, managementReportWire as wire } from './management-evidence-contract.mjs';

const retainTool = 'mrn_mainwp__retain_content_edit_qualification_report_v1';
const readTool = 'mrn_mainwp__read_content_edit_qualification_report_v1';
const check = value => requireThat(value, 'MANAGEMENT_REPORT_TRANSPORT',
  'The private original-report connection or closed report contract is unavailable.');
const parse = (schema, value) => { const result = schema.safeParse(value); check(result.success); return result.data; };

// Internal transport only. The protected registrar supplies original-bound
// signed controls; this class neither creates identities nor admits reports.
export class ManagementReportTransport {
  constructor(client) {
    this.client = client;
    this.ready = false;
    this.retainDirectory = new (createPrivateQueryReader(retainTool))(client);
    this.readDirectory = new (createPrivateQueryReader(readTool))(client);
  }
  async initialize() {
    this.ready = false;
    await this.retainDirectory.initialize();
    await this.readDirectory.initialize();
    this.ready = true;
  }
  async #invoke(name, schema, input, timeout) {
    check(this.ready);
    const request = parse(schema, input);
    check(Buffer.byteLength(delegationCanonical(request)) <= wire.limits.input_bytes);
    const result = parse(types.retained_summary, payload(await this.client.callTool(
      { name, arguments: request }, undefined, { timeout })));
    check(this.ready && Buffer.byteLength(delegationCanonical(result)) <= wire.limits.readback_bytes);
    return result;
  }
  retain(input) {
    return this.#invoke(retainTool, types.retain_input, input, wire.limits.retention_seconds * 1000);
  }
  read(input) {
    // The provider's fresh independent read has its own fixed twenty-second
    // bound. This never retries or restarts an uncertain acquisition.
    return this.#invoke(readTool, types.read_input, input, 20000);
  }
}

// Opt-in source adapter only: production main.mjs does not construct this.
// Retention waits for evidence/current-access callbacks on other connections.
export async function createManagementReportTransport({ enabled = false, connect, callbackClient } = {}) {
  check(typeof enabled === 'boolean');
  if (!enabled) return null;
  check(typeof connect === 'function' && typeof callbackClient?.callTool === 'function');
  const client = await connect();
  check(client !== callbackClient);
  const transport = new ManagementReportTransport(client);
  let closing;
  const close = () => {
    if (!closing) { transport.ready = false; closing = Promise.resolve().then(() => client.close()); }
    return closing;
  };
  try {
    await transport.initialize();
    return { retain: input => transport.retain(input), read: input => transport.read(input), close };
  } catch (error) { await close().catch(() => {}); throw error; }
}
