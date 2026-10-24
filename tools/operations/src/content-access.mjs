import { readFileSync } from 'node:fs';
import { compileContentSchema } from './content-schema.mjs';
import { createAccessControl } from './access-control.mjs';

export const accessContract = JSON.parse(readFileSync(new URL('../contracts/content-current-access-v1.json', import.meta.url), 'utf8'));
export const accessVetoPath = '/internal/mainwp/access-veto';
// Fixed paths select their protocol before reading an opaque query pointer.
export const managementAccessVetoPath = '/internal/mainwp/management-access-veto';
export const accessReadbackTool = 'mrn_mainwp__read_access_veto_query_v1';
const compile = name => compileContentSchema({ $ref: `#/definitions/${name}` }, accessContract.definitions);
export const { AccessSigner, AccessQueryReader, ContentAccessVeto } = createAccessControl({
  contract: accessContract, compile, readbackTool: accessReadbackTool,
});
