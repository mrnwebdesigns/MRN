# Kinsta staging deployment qualification

The operator CLI accepts `--environment staging` only with the native Kinsta
provider and backup adapter. The native API must identify a staging environment,
its exact primary URL, web root, and SSH identity. This does not add automatic
staging or Live deployment to existing GitHub workflows.

Use `/www/<site>/deployments/mrn-site-deploy/staging` for new staging release
storage. Kinsta permits PHP to read the `deployments` sibling of `public`.
Existing Dev/Live `private-backups` mappings remain accepted for compatibility;
new deployments may use the PHP-readable `deployments` mapping instead.

An already migrated private release may lack a `site.json` target binding. One
explicit manual deployment can supply `--bind-imported-source <retained-source-sha>`
and `--bind-imported-public-tree <reviewed-public-tree-sha256>`.
Enrollment verifies the exact current pointer, retained source and theme checksums,
public loader snapshot, immutable public assets, private storage, native target,
and a fresh verified native backup before writing the binding. If the migrated
store lacks an adoption record, enrollment also requires the exact canonical
bootstrap for that private path before recording the reviewed public snapshot. It never re-adopts
or overwrites the public theme. Existing bindings and ordered deployment histories
cannot be replaced by this operation. The first enrollment exercises rollback and
reactivation through the same normal controller and native backup checks.

Build a deterministic release from the reviewed, pushed source. Run the existing
`deploy.py` CLI in `preflight` mode first, with environment-specific `DEPLOY_*`
configuration and the native API credential resolved at runtime. Invoke `deploy`
only after reviewing that preflight, passing source QA, and authorizing the exact
staging write. The one-time imported-release qualification requires no existing
target binding or deployment order and always exercises rollback, even before
DEPLOY_READY is enabled for routine deployments. Native transaction backup
approval remains mandatory. An explicitly configured agent key validates the
native API identity without retrieving an SSH password. Hosted
secrets stay in memory/stdin; receipts contain target identity and checksums only.

After activation, verify the installed release and immutable asset checksums,
public release header, REST health, scoped HTML invalidation, browser behavior,
accessibility, performance, and rollback receipts. Qualifying staging does not
qualify production or authorize a Live promotion.
