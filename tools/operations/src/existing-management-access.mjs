import { createAccessControl } from './access-control.mjs';
import { requireThat } from './contracts.mjs';
import { existingManagementTypes, existingManagementWires } from './management-existing-contracts.mjs';
import { OriginalManagementAuthorizationLedger } from './original-management-authorization.mjs';

export const existingManagementAccessPath = '/internal/mainwp/management-existing-access-v2';
export const existingManagementAccessReadbackTool = 'mrn_mainwp__read_management_access_veto_query_existing_v2';
export const {
  AccessSigner: ExistingManagementAccessSigner,
  AccessQueryReader: ExistingManagementAccessQueryReader,
  ContentAccessVeto: ExistingManagementAccessVeto,
} = createAccessControl({ contract: existingManagementWires.qualification,
  compile: name => existingManagementTypes.qualification[name],
  // Frozen v2 keeps schema1 for the stored query, but signs schema2 claims.
  readbackTool: existingManagementAccessReadbackTool, readbackSchemaVersion: 2, vetoSchemaVersion: 2 });

// No callback-created map and no legacy fallback. The provider independently
// checks current Directory state; this answers from the original local policy,
// durable queue and shared deny-first principal history within one transaction.
export class ExistingManagementAccessLedger {
  constructor(original) {
    requireThat(original instanceof OriginalManagementAuthorizationLedger,
      'ACCESS_MAPPING', 'The service-owned original qualification ledger is required.');
    this.original = original;
  }
  answer(query, digest, signAnswer) { return this.original.answerQueueAccess(query, digest, signAnswer); }
  recordPendingRevocation(jobId) { return this.original.recordQueuePendingRevocation(jobId); }
}
