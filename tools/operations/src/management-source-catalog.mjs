import { delegationDigest } from './delegation.mjs';
import { compileManagementEvidenceSchema } from './content-management-schema.mjs';
import { ManagementEvidenceLedger } from './management-evidence-store.mjs';
import { managementSourceWire as wire } from './management-source-transfer.mjs';
import { createSourceCatalogValidator, requireSourceCatalog as fail } from './management-source-catalog-validation.mjs';

const shape = name => compileManagementEvidenceSchema({ $ref: `#/$defs/${name}`, $defs: wire.$defs });
const validate = createSourceCatalogValidator(wire, shape('source_catalog'), shape('envelope'));

// Private service configuration only, not a workspace tool or owner approval.
// The expected catalog hash must come from authenticated provider readback.
// Configuration is absent by default; a response cannot supply keys/catalogs.
export class ManagementSourceCatalogRegistry {
  constructor({ ledger, current, expectedSha256, now = () => Date.now() } = {}) {
    fail(ledger instanceof ManagementEvidenceLedger
      && ((current === undefined && expectedSha256 === undefined)
        || (typeof current === 'function' && typeof expectedSha256 === 'function')));
    Object.assign(this, { ledger, current, expectedSha256, now });
  }
  resolve(jobId, expectedCatalogSha256) {
    const original = this.ledger.captured(jobId), startedAt = this.now();
    fail(this.current, 'MANAGEMENT_SOURCE_MISSING_CATALOG');
    const current = this.current(), pin = this.expectedSha256();
    const result = validate(current, pin, expectedCatalogSha256, original.context.binding, startedAt);
    const binding = original.context.binding;
    // Re-read both independent authorities at return. A current configuration
    // update, pending revocation or original expiry invalidates this resolution.
    fail(delegationDigest(this.ledger.captured(jobId).context) === delegationDigest(original.context)
      && this.expectedSha256() === pin && delegationDigest(this.current()) === pin
      && this.now() >= startedAt && this.now() - startedAt < 5000 && this.now() < binding.expires_at * 1000);
    return result;
  }
}
