# Qualify a Kinsta-promoted Live child theme

Use this manual operation only when Kinsta has promoted a previously qualified
staging site and the copied public loader still selects its staging-bound private
store. It does not launch a site, copy a database, publish tracking, or change
WordPress environment/indexing options. Finish those launch operations first.

The native API must verify the exact Live environment, primary domain, public
root and SSH endpoint. The reviewed source environment must be a distinct native
staging environment on that same Kinsta site. WordPress must report `production`.

Preserve the original build archive whose checksum is the imported release ID.
Private metadata alone is insufficient provenance. Create a reviewed JSON manifest:

```json
{
  "schema": 1,
  "source_url": "https://stg-example.kinsta.cloud",
  "source_environment_id": "11111111-2222-3333-4444-555555555555",
  "source_sha": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
  "pointer": {
    "schema": 1,
    "slug": "example-child",
    "release_id": "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb",
    "public_path": "mrn-assets/example-child/cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc"
  },
  "public_tree": "dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd"
}
```

Use the normal deterministic build and `deploy.py --mode preflight` command with
`--environment live`, `--promote-kinsta-staging-manifest <reviewed.json>` and
`--promoted-source-artifact <original.tar>`. The normal artifact arguments identify
the qualified candidate. The candidate must differ from the imported release so
activation and rollback select genuinely distinct pointers. A version-only site
release can qualify the store without changing content or presentation.

The destination must be the unused, private, physical directory
`/www/<site>/deployments/mrn-site-deploy/live`. The source is its `staging` sibling;
arbitrary source paths and existing Live bindings are rejected. Do not use the
older staging enrollment flags or overwrite a staging binding to make it Live.

After reviewing preflight and source/runtime QA, explicitly authorize qualification
and the native transaction backup policy. `deploy.py --mode deploy` creates and
verifies a fresh labeled native backup before its first remote write. It rechecks
that snapshot before importing, rebinding, staging, activating and rolling back.
No existing Kinsta backup is deleted to make space.

The controller locks both stores, verifies the exact source pointer, canonical
loader, binding, original artifact, private code and immutable public assets. It
rehydrates the imported release from that verified archive into the separate Live
store. The source store remains unchanged. It saves the original loader before
atomically rebinding it, then activates the candidate, verifies the imported
release on rollback, and reactivates the candidate through the normal controller.

On failure, exact loader recovery restores the original promoted store, retains
both source/code snapshots, and quarantines incomplete Live binding metadata.
Unexplained public or source drift blocks recovery rather than overwriting other
work. An incomplete or failed operation requires inspection; it is not permission
to retry or delete retained evidence.

Only installed/public asset checks, distinct-pointer rollback/reactivation and
applicable hosted QA establish qualification. Then routine Live deployments use
`DEPLOY_READY=1` and the separate Live store, without these one-time import flags.
Source acceptance, Fleet publication and site qualification are separate proofs.

## Native HTML cache completion

Routine native releases retain the MU plugin's exact-URL origin purge. Before
public acceptance, the controller also clears each reviewed verification page
through Kinsta's [Edge Cache API](https://api-docs.kinsta.com/api-reference/wordpress-edge-caching/clear-site-edge-cache),
with the exact native environment ID, canonical URL, and
`clear_subdirectories: false`. Each returned operation must complete; pending,
missing or failed operations block acceptance. No full-environment, descendant,
CDN asset or object-cache purge is submitted.

This closes the gap where an origin purge returns HTTP 200 while Edge Cache
still serves the preceding generation. The same cache step runs after recovery
and qualification rollback. Canonical first/warm HTML, immutable asset checksums,
unchanged public files and the selected private release remain blocking checks;
an API acknowledgement is not deployment success. Retain failed receipts and
their fresh snapshots. A later deployment still requires a new verified native
backup and separate approval before deleting any restore point to make space.
