# MRN site release contract v1

`mrn-site-release` coordinates one WordPress target and one immutable release per run. Its adapter uses the existing site-owner transport, verified backup, atomic activation and public QA paths. Adoption is per adapter and environment. Existing workflow callers can keep their current tooling pin until qualification is complete.

## Build from the reviewed commit

Pin the shared tooling commit and Python 3.11 or newer. Install `release_v1/requirements.txt` in the build and deployment runner environments. Build the site's existing immutable code artifact from an exact committed SHA. For enrolled child themes, use [build_release.py](../build_release.py). Pass the resulting code artifact to the v1 packer. The code archive's format and source provenance are enforced by the adapter.

```sh
mrn-site-release pack --repo . --source-sha "$SOURCE_SHA" --code code.tar --output release.tar
mrn-site-release inspect --archive release.tar --sha256 "$ARTIFACT_SHA256" --source-sha "$SOURCE_SHA"
```

Pass the outer SHA-256 from the trusted build result to the deployment runner via its authenticated job or broker. Retain the exact artifact privately for resumed activation and migrations. The v1 tar contains `manifest.json`, `code.tar`, and private `migrations/` members. Only the code payload is passed to the code store. Content scripts and approved inputs stay outside the public tree.

For a repository retaining a previous migration contract, the builder can
reserve `migrations.json`, `media/` and top-level scripts beginning with an
explicit `--migration-prefix v1-...` in `scripts/content/`. Every member of
that scope must still be declared. Other paths stay with the legacy workflow.
This changes build selection only. The output is the same v1 envelope and is
verified by the original v1 runner.

Keep all deployment inputs under `scripts/content/` in the site Git repository. Start with [the sample declaration](examples/migrations.json) and [WordPress migration](examples/hero-v1.php). Every listed script and image must be an exact regular Git blob. Each migration has an immutable ID, a same-named PHP script, an explicit environment allowlist, an image list and the exact public paths its edits affect. Preserve declaration order for dependent migrations. Use a new ID for a correction. A code-only release can omit the directory. Unreferenced files, symlinks, unsafe paths, duplicate members, invalid image content and limit violations block packaging or preflight. All image paths declared by a migration must be used through the supplied import function.

Defaults are 8 MiB per image, 32 MiB total migration files, 4,096 pixels on either edge, and 16 million pixels per image. V1 accepts single-frame JPEG, PNG and WebP with matching extensions. Reports include file and bundle bytes, image dimensions, detected format and SHA-256. The default optimization suggestions are 500,000 bytes or 2,000 pixels on the longest edge. Use a JSON policy with keys such as `file_bytes`, `bundle_bytes`, `width`, `height`, `pixels`, `formats`, `recommend_bytes` and `recommend_edge` with `--limits` for build inspection. Set the receiving target's protected `Limits` independently. Hard ceilings are 32 MiB per image, 128 MiB per migration bundle, 8,192 pixels on either edge, 32 million pixels per image, 256 KiB per script and 512 content files.

## Install and qualify an adapter

Use the [reference host adapter](examples/adapter.py) for an already enrolled atomic child-theme store. Its provider interface identifies the functions every site workflow must supply. Install a protected Python configuration module whose `configure()` returns `adapter`, a `Target`, an absolute owner-only `journal` directory and an optional `policy` dictionary for the shared controller. Put the journal outside all public trees, on a durable filesystem with reliable exclusive locks and atomic file replacement. Give each target a stable journal and provision a persistent 32-byte hex-encoded `MRN_RELEASE_RECEIPT_KEY` from the runner's secret store. Keep the key, receipts, provider credentials, backup artifacts and WP-CLI logs private. Back up the key and journal together. HMAC authenticates receipts to operators who trust this runner and key.

- Authenticate the deployment principal and bind the exact repository, reviewed source, trusted artifact digest, target and requested action in `authorize`. Derive the owner and deployment ID from the authenticated workflow or operator. Verify push or promotion order, and require an explicit production approval independent of staging. Pin the adapter, provider configuration and shared tooling outside artifact-controlled paths.
- In `identify`, verify the actual WordPress origin and environment, account, site or environment ID, owner path and site-owned code surface via the enrolled provider. `current` reads the installed immutable code ID. The forward ID is the enclosed code SHA-256 and each baseline must have a retained, verifiable rollback ID. Checks and backup evidence must come from the exact environment. `preflight` verifies the code payload and returns `passed`, `source_sha`, `code_sha256`, `target` and an `evidence_id` for the existing source and runtime qualification gates. Local adapters supply `local_health_options()` for an optional service-state function and health thresholds.
- In `backup`, use the qualified database-only remote Updraft path for shared MRN WordPress, or the explicitly approved provider alternative under its stricter rules. Existing [backup.php](../backup.php) can issue and verify an Updraft receipt. Derive its label or provider idempotency key from the deployment challenge. Return exact `target`, `challenge`, `nonce`, `verified_at` as a Unix timestamp, `database_verified: true` and, for shared environments, `remote_verified: true`. Recheck recoverability by exact nonce in `verify_backup`. V1 also requires a verified database snapshot for an adapter adopting this combined workflow locally. The broader Local Hub remote backup exception remains available in other workflows.
- Delegate activation and rollback to an immutable store with compare-and-switch semantics. Every mutating provider call must be synchronous and fenced, or expose durable job identity and reconciliation. Pass the supplied lock descriptor into child processes that can outlive the command. `quiescent` must check that earlier writes are settled before recovery. Retire competing deploy entry points or route them through the same persistent target lease. Recheck the target at the provider boundary for each write.
- Use the site QA plan for browser URLs, selectors, viewports, loaded asset checksums, accessibility and performance. The example [browser proof](examples/header-proof.mjs) shows the release-header check and [pacing helper](browser.mjs) protocol. Include the provider's qualified read-only cache bypass if needed to prove the newly active release before refresh. Approve only required resource origins and block service workers. A proof returns `{passed, release_id, urls}` for the exact requested pages. A provider 429 or a 503 with `Retry-After` is persisted as throttling, with a resume time. The controller permits up to three browser attempts and 30 seconds of provider wait per invocation by default.
- Use exact HTML cache keys/variants for the union of the code paths and selected migration paths. Reuse [cache_policy.py](../cache_policy.py) and the provider's qualified cache adapter where applicable. `refresh` acknowledges the exact list. Final `verify` reads normal public URLs and must verify expected migration data, media delivery, code identity and applicable site QA before returning the same result shape as proof.
- Qualify the target in a disposable or expressly approved environment before switching traffic. Exercise the interrupted activation, stale ownership, backup recovery, throttled browser proof, replay, cache scope and retained-code rollback paths. Test WordPress uploads and generated image sizes under the provider's actual filesystem, image editor, CDN and offload configuration. Authorize and qualify production as a separate target.

Trusted site migration closures resolve a stable WordPress record or fail on ambiguity, use WordPress or ACF APIs, read back the resulting field, and return `true` only when the intended state is verified. Keep reruns safe if a command stops mid-script. Use runtime URLs from WordPress. Retain replaced attachments. The WP-CLI runner verifies committed image bytes and uses a migration-owned attachment that can be found again after a stop. It verifies generated metadata and records a per-target WordPress fingerprint and attachment IDs. Completed replay rechecks its attachments and skips the closure. A different fingerprint for the same migration ID blocks. General-purpose PHP must be reviewed before approval because it executes with the WordPress owner's privileges.

## Run and recover

The protected configuration and CI authorization govern each command. Example request:

```json
{
  "deployment_id": "run-123",
  "owner": "oidc:approved-release-runner",
  "target": {
    "repository": "example/site",
    "environment": "staging",
    "site_id": "qualified-provider-site",
    "origin": "https://staging.example.com"
  },
  "source_sha": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
  "artifact_sha256": "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb",
  "paths": ["/"]
}
```

```sh
mrn-site-release status --adapter /private/config/site_adapter.py
mrn-site-release preflight --adapter /private/config/site_adapter.py --request request.json --archive release.tar
```

Follow `next_command` in status, substituting the original trusted request and artifact where shown. The phase order is **preflight → backup → activate → proof → migrate → refresh → verify**. Deploy and recovery commands return a nonzero exit when blocked. The journal retains authenticated attempt and completion receipts with the target, SHA pair, owner and lock, backup nonce, proof results, migration records, verification URLs and rollback state. The status command reads the actual target and journal without acquiring a write lock. Production commands require `--confirm-target` with the configured origin in addition to authorization.

| State | Action |
| --- | --- |
| Proof failed or throttled | Fix the proof condition or wait until the recorded `retry_not_before` Unix timestamp, then rerun `proof` with the same request. It needs no artifact. |
| Stale lock or interrupted phase | The original run owner runs `recover --confirm-target <origin>` after checking provider jobs. Recovery keeps the lock ID. An already switched release proceeds to proof. |
| Backup expired or disappeared before activation | Run `backup` for the same request before activating. After an interrupted activation, `recover` checks that the provider has settled and reads the active release again. The baseline can proceed with a new verified backup. An already switched release still requires its original restore point. |
| Target drift or identity unavailable | Reconcile provider identity and the pointer against the retained receipts. The journal stays locked. |
| Cancel before activation | Run `abort --confirm-target <origin>` once status shows no interrupted work. |
| Rollback before any selected content attempt | Run `rollback --confirm-target <origin>`. It takes a fresh rollback backup, compare-and-switches to the retained release, refreshes scoped paths and verifies the previous release. |
| Rollback after a content attempt | Keep the evidence and use the site's approved compensation or provider restore runbook. A shared whole-database restore could overwrite unrelated WordPress writes. Record the external recovery and reconcile the target before re-enrollment. |

Treat a stopped local web server, upstream 502, host saturation or unstable performance samples as a failed preflight. Stabilize Local Hub and rerun preflight before continuing.
