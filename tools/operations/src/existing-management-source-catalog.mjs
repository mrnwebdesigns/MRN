import { requireThat } from './contracts.mjs';
import { delegationDigest } from './delegation.mjs';
import { existingManagementTypes, existingManagementWires } from './management-existing-contracts.mjs';
import { OriginalManagementAuthorizationLedger } from './original-management-authorization.mjs';
import { parseExistingManagementSourceOwner } from './existing-management-source-context.mjs';
import { createSourceCatalogValidator, requireSourceCatalog } from './management-source-catalog-validation.mjs';

const types = existingManagementTypes.source_transfer;
const validate = createSourceCatalogValidator(existingManagementWires.source_transfer, types.source_catalog, types.envelope);
const same = (a, b) => delegationDigest(a) === delegationDigest(b);
const check = value => requireThat(value, 'MANAGEMENT_EXISTING_SOURCE_CATALOG',
  'The original captured job or independent source catalog configuration is unavailable, changed or expired.');

// Service-owned configuration only. No caller-selected catalog, key, protocol,
// identity or owner policy. Resolving a catalog neither prepares owner state
// nor creates a source slot; capture and protected source delivery remain gated.
export class ExistingManagementSourceCatalogRegistry {
  constructor({ ledger, owner, current, expectedSha256, now = () => Date.now(), monotonic = () => performance.now() } = {}) {
    check(ledger instanceof OriginalManagementAuthorizationLedger
      && ((owner === undefined && current === undefined && expectedSha256 === undefined)
        || (typeof owner === 'function' && typeof current === 'function' && typeof expectedSha256 === 'function')));
    Object.assign(this, { ledger, owner, current, expectedSha256, now, monotonic });
  }
  async resolve(jobId) {
    requireSourceCatalog(this.current, 'MANAGEMENT_SOURCE_MISSING_CATALOG');
    const owner = parseExistingManagementSourceOwner(this.owner()), startedAt = this.now(), started = this.monotonic();
    const limit = existingManagementWires.source_context.limits.lookup_deadline_seconds * 1000;
    const within = () => check(same(parseExistingManagementSourceOwner(this.owner()), owner)
      && this.now() >= startedAt && this.now() - startedAt < limit
      && this.monotonic() >= started && this.monotonic() - started < limit);
    const bounded = async perform => {
      within(); let timer;
      try {
        const result = await Promise.race([Promise.resolve().then(perform), new Promise((_, reject) => {
          timer = setTimeout(() => reject(new Error('MANAGEMENT_EXISTING_SOURCE_CATALOG_TIMEOUT')),
            Math.max(1, limit - (this.monotonic() - started)));
        })]);
        within(); return result;
      } finally { clearTimeout(timer); }
    };
    const original = await bounded(() => this.ledger.currentQueuedJob(jobId));
    const captured = await bounded(() => this.ledger.readCapture(jobId)), queued = original.queue.context;
    check(captured.state === 'captured' && captured.receipt?.bindingSha256 === queued.binding_sha256);
    const configuration = this.current(), pin = this.expectedSha256(), validationStarted = this.monotonic();
    const catalog = validate(configuration, pin, owner.source_recipe_catalog_sha256, queued.binding, this.now());
    check(this.monotonic() >= validationStarted && this.monotonic() - validationStarted < 5000);
    within();
    const current = await bounded(() => this.ledger.currentQueuedJob(jobId));
    const after = await bounded(() => this.ledger.readCapture(jobId));
    check(current.original.digest === original.original.digest && current.revision === original.revision
      && current.queue.digest === original.queue.digest && same(current.queue.context, queued) && same(after, captured)
      && this.expectedSha256() === pin && delegationDigest(this.current()) === pin
      && this.now() < Math.min(queued.binding.expires_at * 1000, original.original.mapping.authorization.expiresAt));
    within(); return catalog;
  }
}
