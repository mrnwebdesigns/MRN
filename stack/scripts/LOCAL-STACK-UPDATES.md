# Locked Stack updates on Local Hub clones

`update-local-stack.py` applies one existing immutable Stack payload to one
Local Hub Lima site. It checks the exact `.localhost` identity, safety guard,
independently supplied lock checksum, and every component tree before writing.
The default invocation only previews the update. Execution binds the preview's
SHA-256 to the unchanged runtime, child files, component files and artifact bytes.

The adapter updates only lock-owned MU components, shared runtime, installed
standard plugins and the canonical parent. It preserves child code, the selected
private child generation, active plugins, settings and content. It does not
install a missing standard plugin or perform the separate existing-site
retirement/provider migration in `migrations/README.md`.

Existing excluded development/cache directories are preserved as filesystem
nodes without traversing their contents. Unmanaged artifact extras are not
staged, and a nested component source Git checkout is refused.

An imported parent-only immutable release can be detached with
`--detach-imported-parent`. On a local clone, the exact locked physical parent
then becomes the selected template. The imported early bootstrap is retained in
the code recovery journal; its private releases and published asset generations
are preserved. This explicit local mode does not authorize remote unadoption.
Mixed parent/plugin component selections and unrecognized loaders are refused.

```bash
python3 stack/scripts/update-local-stack.py \
  --site-manifest /absolute/MRN-sites/example/.mrn-site.json \
  --release-lock /private/release/stack-release.lock.json \
  --lock-sha256 <independently-verified-lock-sha256> \
  --artifact-root /private/release/assembled \
  --detach-imported-parent \
  --plan-out /private/review/local-plan.json
```

Review the resulting release ID, changed paths, parent detachment and
`plan_sha256`. Repeat those arguments with:

```bash
--execute --approve-plan-sha256 <reviewed-plan-sha256>
```

The implementation runs WP-CLI as the OpenLiteSpeed `nobody` user in the Lima
guest, retaining access to a preserved private child release without loosening
its permissions. It performs no SSH operation. Remote URLs, different runtimes,
aliased paths and an inactive local safety guard fail closed. Private runtime
diagnostic output and manifest credentials are excluded from errors and plans.

One exclusive local writer lock protects a persisted code journal under
`<site-root>/output/local-fleet-updates/<operation>/`. Changed paths move to
same-filesystem before-state slots, allowing code-only rollback. The child tree
is never staged or moved. Successful execution requires runtime lock/hash,
loaded component and physical-parent readback while preserving the active set
and exact child selection. Full MRN site QA remains a separate required check.

A caught failure restores and verifies the original code and identity. An
interruption retains its journal and blocks a subsequent update. Recover with:

```bash
python3 stack/scripts/update-local-stack.py \
  --rollback /absolute/site/output/local-fleet-updates/<operation>/journal.json
```

Rollback first inspects every recovery slot. Modified components or recovery
files block restoration rather than overwriting subsequent owner work. It
restores code, never a database snapshot. Local runtime writes follow the Local
Development Exception in `stack/BACKUP_POLICY.md`; remote backup gates and
deployment adapters are unchanged.

Run the target, preservation, artifact-race, interruption and recovery tests:

```bash
python3 -m unittest discover -s stack/tests -p test_update_local_stack.py -v
```
