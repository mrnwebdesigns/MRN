# Kinsta child-theme deployment adapter candidate

Status: source candidate; not runtime-qualified or enabled for a site.

The adapter uses the existing immutable child-theme package, private release store,
atomic pointer, public asset verification and code-only rollback. It does not copy
WordPress content, databases, plugins, parent themes or configuration between sites.

## Native identity and backup

`DEPLOY_HOST_PROVIDER=kinsta` requires `DEPLOY_BACKUP_PROVIDER=kinsta`. The native
API validates the site/environment pair, primary domain, public root, SSH host,
port and user. Private storage must be the exact environment directory beneath
`/www/<site>/private-backups/mrn-site-deploy/`.

The proposed native policy creates one labeled manual snapshot immediately before
the deployment transaction's first remote write. Completion and the exact manual
backup are both verified. Every adoption, stage, activation, rollback test and
recovery step reads back the same snapshot before proceeding; a missing, mismatched
or hour-old receipt stops the operation. A separate deployment or later rollback
creates a fresh snapshot. No existing backup is deleted to make room.

This transaction-scoped exception needs owner approval before Kinsta activation.
Activation is disabled unless `DEPLOY_KINSTA_TRANSACTION_BACKUP_APPROVED=1` records
that approval; preflight remains read-only and does not require the exception.
The existing Updraft adapter continues creating fresh remote database backups at
each step. Kinsta permits five manual backups and snapshots the entire environment;
requiring six independent snapshots for one first-adoption transaction cannot fit
that provider's available slots. Native full-site restoration is an emergency
manual operation because it would also rewind content and configuration. Routine
code rollback only changes the release pointer.

API tokens travel only over the pinned SSH connection's stdin. They are not stored
in plans, receipts, remote job files, shell arguments or logs. Native SSH passwords
remain in the runner process environment. Both automatic rollback paths receive
the native token and resolve fresh verified connection identity.

## Scoped HTML cache handling

The adapter uses the local v2 immediate purge interface shipped in Kinsta's official
MU plugin, with `single|N` URL entries. It never sends `group` entries, clear-all,
CDN-cache or object-cache requests. The fixed loopback HTTPS interface follows
Kinsta's certificate handling, sends no credentials and rejects redirects. Only
validated HTTPS HTML URLs on the selected site are eligible. Normal public HTML
and asset checksums must pass after the request, including warm responses.

No Kinsta plugin is installed by this adapter. The native endpoint must pass host
qualification; absence or failure stops deployment. The official plugin source
was inspected locally for its protocol, not copied into the site's runtime.

## Qualification still required

- Owner approval for the native transaction-scoped snapshot exception.
- Review and reconcile the exact Live source without promoting unfinished Dev work.
- Verify private PHP readability, native backup availability and scoped purge replies.
- Exercise activation, code rollback and reactivation with immutable asset proof.
- Pass applicable browser, API, accessibility and performance acceptance.
- Enable `DEPLOY_READY` only after the target's receipts establish qualification.

Sources: [Kinsta backups](https://kinsta.com/docs/wordpress-hosting/wordpress-backups-and-disaster-recovery/wordpress-backups/),
[Kinsta MU plugin](https://kinsta.com/docs/wordpress-hosting/kinsta-mu-plugin/),
[official provider package](https://kinsta.com/kinsta-tools/kinsta-mu-plugins.zip).
