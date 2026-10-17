import { createAccessControl } from './access-control.mjs';
import { compileManagementSchema } from './content-management-schema.mjs';
import { managementCaptureContract } from './content-management-capture.mjs';

// The small callback envelope keeps the public parser bounds. Large capture
// budgets do not apply to control ingress. This source-selected protocol has
// no fallback to the single-field reader, signature purpose or job map.
export const managementAccessReadbackTool = 'mrn_mainwp__read_management_access_veto_query_v1';
const compile = name => compileManagementSchema({ $ref: `#/$defs/${name}`, $defs: managementCaptureContract.$defs });
export const {
  AccessSigner: ManagementAccessSigner,
  AccessQueryReader: ManagementAccessQueryReader,
  ContentAccessVeto: ManagementAccessVeto,
} = createAccessControl({ contract: managementCaptureContract, compile, readbackTool: managementAccessReadbackTool });
