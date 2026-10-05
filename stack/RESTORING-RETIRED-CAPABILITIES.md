# Restoring retired capabilities

Removal from the default Stack must remain reversible. The source repositories,
their full Git history, and existing settings are retained. Nothing in this
cleanup uninstalls plugins on existing sites, deletes saved metadata or options,
revokes provider credentials, or changes provider accounts or DNS.

## Recovery record

[`manifests/retired-capabilities.json`](manifests/retired-capabilities.json)
records the exact repository, commit, tree, archive checksum, bundle checksum,
dependencies and data ownership for SEO Helper, ACF Character Count, AI Assist,
Editor Enhancements (`mrn-editor-tools`), Cookie Consent, GTM Injector and
SendGrid Provisioning. The seven repositories remain available on GitHub.

Verified source archives and full Git bundles are retained outside this repo in
`/Users/khofmeyer/Development/MRN-release-artifacts/2026-10-05-stack-finalization`.
That directory also holds `MRN-before.bundle` and
`mrn-config-helper-before.bundle`. Retain these files with release recovery
storage; the manifest alone does not contain source. No client database or
provider secrets are included in the manifest.

## Recover source before deciding where to install

1. Select the capability from the manifest. Verify the bundle or archive SHA-256
   against its recorded checksum. Stop on any mismatch.
2. Clone the verified bundle into a new isolated directory, then check out its
   recorded `source_commit`. A remote clone at the same commit is an alternative.
   Verify `git rev-parse HEAD^{tree}` matches `source_tree`. Never overwrite a
   current checkout to recover old functionality.
3. For source-only inspection, extract the verified ZIP into an empty directory.
   This is a recovery artifact, not a newly qualified deployable package.
4. Review current provider ownership and dependencies. Restoring an old SEO,
   tracking or consent integration while its replacement remains enabled can
   duplicate output. Preserve any newer provider data when planning reversal.
5. Port only the needed functionality into an isolated task, version it and run
   current component, integration and release QA. Old source remains recoverable;
   compatibility with future WordPress and vendor versions must be requalified.
6. Build and lock the accepted release. Any later site installation or activation
   requires its own exact target, approved plan, verified pre-write backup,
   runtime verification and rollback receipt. This document authorizes none.

For example, after verifying the recorded bundle checksum:

```bash
git clone /path/to/mrn-sendgrid-provisioning-recovery.bundle /path/to/new-recovery-checkout
git -C /path/to/new-recovery-checkout checkout --detach 7465010230c23c1d901ba382b88be36e93dc0022
git -C /path/to/new-recovery-checkout rev-parse 'HEAD^{tree}'
```

## SendGrid integration recovery

The connector repository preserves provisioning and sender synchronization.
The removed bootstrap/key-delivery integration is at MRN commit
`ba7a06150b2ba8249ac50610c830344ecd4d005f`, in
`stack/scripts/site-bootstrap.sh`. The removed settings card and sync hook are
at Config Helper commit `f16bedf0f87b5aeb7516f286e38fc4b70f569bef`, in
`mrn-config-helper.php`. These commits are also recorded in the manifest.

Do not roll back the entire modern Stack to restore SendGrid. Port the selected
integration blocks, review credential boundaries and the chosen mail plugin,
then qualify the resulting release. Config Helper's generic sender wrappers and
saved identity settings remain; the retired connector's options, existing mail
settings and external resources have not been deleted by this cleanup.

## Editor and schema recovery

Historical SEO and schema values remain stored. The guarded editor-retirement
migration also provides conflict-aware rollback; see
[`scripts/migrations/README.md`](scripts/migrations/README.md). Source recovery
does not undo a later content edit. Use the migration's recorded before/after
values and refuse conflicts rather than restoring an old whole-site database.
