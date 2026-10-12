# Automatic website discovery and existing MRN knowledge

The website directory comes from MainWP. Team members do not register those
websites again in Operations. Source access and individual permissions are service
configuration; they are independent of whether a website is ready for a change.

## Sources and identity

With `discovery` enabled, `list_websites` returns `{websites, sources, issues}`.
It validates the approved MainWP Dashboard and tool directory, then reads every
page of `get_sites_basic_v1` within a bounded, consistent result. Only ID, exact
HTTPS URL and name are retained. The directory is not a health report and never
syncs all sites. `inspect_website` separately resolves the exact URL, takes the
current ID, performs a one-site sync and reads runtime evidence. Directory IDs
are observations, never operational authority.

Existing Local Hub files are read from configured `localHubRoots`, each pointing
to an existing `MRN-sites` parent directory. Only direct child `.mrn-site.json`
files are considered. There is no recursive crawl of WordPress, uploads or Git.
Directory symlinks are ignored; manifest symlinks, escaped paths and excessive
file/directory sizes are rejected. The importer does not copy secrets, database
fields, SSH destinations, arbitrary notes or executable paths into the registry.
The host must provide read-only source access using the established MRN route;
this implementation does not synchronize a laptop or call Production Hub APIs.

Imported fields are title/folder alias, `liveUrl`, `localUrl`, explicit
`deploymentEnvironment`, provider, repository, branch, workflow, method and saved
update time. `liveUrl` means the recorded remote destination: it does **not** prove
production. Blank environment fields stay `unknown`. Neither the hostname, name,
provider nor presence in MainWP is used to guess production or a backup provider.
Provider and deployment fields describe intended configuration; they are not
runtime verification or permission to execute a deployment.

Facts join only on normalized exact URLs. A manifest explicitly links its local
URL to its remote URL. Similar names/slugs do not link unrelated clients or
invent development/production pairings. Conflicting environment classifications
remain visible and block changes; exact URL selection resolves name ambiguity.
Unsupported HTTP/credential-bearing directory URLs are reported as source issues
and never silently upgraded or used for probes. Missing source roots fail
discovery; a deliberately empty Local Hub roots list reports `not_configured`.

Each fact includes source, observed/update time, expiry and intended/observed
classification. Basic directory facts expire after five minutes; saved manifest
facts are marked stale one day after their recorded update time. Neither expiry
causes a source write. Inspection returns the saved facts and their staleness next
to live evidence. Invalid local manifests are reported in `issues`, with no raw
content reflected into tool responses.

## Permissions

An operator assigns immutable team identities once. For directory-wide read
access, use this member template in the existing permissions policy:

```json
{
  "subject": "<immutable team identity>",
  "enabled": true,
  "portfolio": {"sources": ["mainwp", "local-hub"], "actions": ["read"]},
  "grants": []
}
```

That explicit source-wide grant includes future sites in those directories.
`portfolio.actions` supports `read` and `test`; it cannot contain repair or release
permissions. Source access is checked before opening the downstream connection,
before every metadata/page call, after responses, and before returning records.
Inventory failures, inconsistent pages, changed identity or revoked permissions
do not trigger a credential/REST/UI/SSH fallback or substitute a cached success.

A restricted member can use an exact URL grant without a website registry entry:

```json
{
  "subject": "<immutable restricted identity>",
  "enabled": true,
  "grants": [{
    "website": "https://example.invalid",
    "environments": ["unknown", "development", "production"],
    "actions": ["read"]
  }]
}
```

Without MainWP portfolio access, only these exact URL grants are queried through
`get_site_v1`; the basic full-directory tool is not called. An environment-specific
grant still requires that environment to be known. Legacy website-ID grants and
explicit records remain supported. Dedicated environments can be discovered from
the Local Hub source or retained explicit records without being added to MainWP.
Per-site workflow sessions cannot invoke directory-wide tools.

Discovery snapshots are isolated by subject and current policy. `list_websites`
refreshes the directory; other top-level actions reuse a matching snapshot for at
most 30 seconds before refreshing. An in-progress workflow can retain its stable
knowledge snapshot for at most one hour, while every downstream call rechecks
current permissions and site workflows independently refresh live identity/state.
Completed snapshots replace earlier ones atomically. A failed refresh invalidates
the old snapshot; partial results are not published. No process restart can turn
an absent source into a successful cached inventory.

## Missing facts and change readiness

`registryPath` is now optional. Its empty example is for explicit corrections or
unavailable facts, never a second mandatory inventory. A matching exact-URL record
retains its legacy identity and reviewed operational settings while gaining
discovered facts. Conflicting source environment data still blocks writes.
Refreshing observation timestamps does not change an approved operational binding;
site identity, environment, management, backup, conflict state and writer
coordination remain protected.

Unknown environment or backup route does not stop inspection. A requested change
still needs the exact environment, explicit repair/release permission, an approved
backup route, artifact/source QA, coordinated writers and immutable approval.
Discovery imports none of those privileges from MainWP's service credential and
does not infer them from a repository name or hosting provider. If a requested
operation lacks a fact or qualified adapter, report that specific prerequisite.
Do not ask the user to enroll the website to answer ordinary website questions.

This increment imports existing Local Hub records and the MainWP directory.
Automatic Production Hub/provider knowledge, site deployment configuration/receipt
imports, form inventories/procedures and provider-native change adapters remain
future integrations; those fields are not fabricated or treated as verified.
