# MRN deployment update — site release contract v1

Site repositories can adopt the shared `mrn-site-release` contract to deploy immutable code and tracked WordPress content together. Preflight, verified database backup, activation, browser proof, content replay, scoped cache refresh and final verification each have a resumable, signed receipt. Run `status` to see who owns the target, what passed and which command comes next. A browser failure or provider rate limit can be retried without repeating deployment writes.

To adopt, pin the shared tooling commit, declare migrations and approved images under `scripts/content/`, and wire the site's qualified adapter to the contract. Qualify the backup, scoped cache, replay and rollback paths in a disposable environment before enabling the workflow on a site.

Site owners retain approval, target credentials and identity, migration intent, affected URLs, site QA evidence and provider recovery procedures. Production requires its own authorization. Rollout starts when each site's adapter is qualified and its owner authorizes adoption.

[Adoption guide and reference adapter](../tools/site-deploy/release_v1/README.md)
