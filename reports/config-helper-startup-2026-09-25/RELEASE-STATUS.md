# Startup release status — 2026-09-25

## Completed source and release preparation

- Config Helper **0.1.66**, merged plugin PR #8 at `1a326a5e9977dc2a45da5ed7444a58897fecd955`.
- Stack metadata/report PR #129 and immutable lock PR #130 are merged; current lock merge is `fac8a1a`.
- The initial immutable candidate `2026.09.25-config-helper-startup-fleet` has lock SHA-256 `bb23ce499f18a21387c20761fb5dfdec95126c2e65e53da21425957f247f5792` and is archived unchanged. Final publication seal `2026.09.25-config-helper-startup-fleet-r2` includes the completed qualification documentation; the authoritative identity is `stack/manifests/stack-release.lock.json`. No deployable component changes between these seals.
- Clean-main audit and candidate reconciliation pass for the initial seal. Final reconciliation and artifact checks run after the `r2` lock is merged; their generated receipts remain beside the release artifacts. Config Helper is the only changed component version/tree/file count from the predecessor; theme and Character Count remain unchanged.
- Two complete initial-seal Fleet builds match byte-for-byte: package SHA-256 `35c9e69bb69774839d07f99e6936ad4f3d4336bad780d12fa2a63bea03080c6b`, 2,468,761 bytes. The final seal is rebuilt and compared independently because lock bytes are part of the cumulative package; the proposed startup canaries use the unchanged narrower selective plugin artifact and plan.
- Selective Config Helper ZIP: SHA-256 `7073f358b13d2a83e8350e586e532d3370d3914149f17059029c4cefa85c93df`, 108,826 bytes. Deployable tree `1c5882823dfddcbe24b3a018ca95c726602e557ab15f79d136e276071dc74dd8`, 19 files. Exact 0.1.64 and 0.1.65 rollback bytes verified.

## Qualification

738 focused plugin assertions, both real WordPress/ACF integration fixtures and authenticated save/browser checks pass. Component release QA passes on both standard and Woo runtimes. Full Stack release QA passes, including 142 PHP lint targets, WPCS/security, PHPStan, Semgrep, REST, browser smoke, axe and performance.

The Stack suite passes 74 tests with one optional cross-repository test initially skipped because validator roots were not supplied. Rerunning its six-test module with both explicit source roots passes without skips. Fleet orchestration has 13 passing Node tests. Both release PR Code gates pass.

The Stack QA report has advisory development-site parity differences against the existing remote 0.1.64 package; it is not a 0.1.66 fleet-readiness assertion. Its worktree-relative sticky-toolbar path warning is resolved by a successful canonical-checkout rollout-contract run. That run checks shared package/tooling contracts and explicitly skips an absent default-configs live target. Target-specific Fleet checks remain mandatory. Standalone component QA discloses PHPStan symbol warnings and inapplicable rows; the tracked reports preserve those details.

The full results report includes slower route medians as well as improvements. Local PHP cli-server results and CLI results do not establish production or fleet gains.

## Read-only canary preflight

MainWP status was reverified: connected to `wpcontrol.mrndev.io`, 84 abilities. CLI planning encountered intermittent exact-site read timeouts. Direct calls through the same configured MainWP MCP, followed by the canonical Python plan builder, supplied the retained evidence; no SSH or direct upload fallback was used for deployment.

### Trilliant development: blocked

Exact URL: `https://trilliant.mrndev.io/`, fresh resolved site 109. During this task a separate rollout advanced its signed baseline to `2026.09.25-content-filters-fleet` and Config Helper 0.1.65. The startup plan therefore requires exact 0.1.65 rollback, not the earlier observed 0.1.64.

Controller preflight at 15:11:15 UTC reports `ready=false`, blocker `backup_not_ready`. Updraft is installed and active, but `backup_api_available=false`; the controller cannot verify a configured remote destination. This does not prove storage credentials are absent. Read-only inventory shows MainWP Child 6.1.8 and Updraft 2.26.7.0; no causal conclusion or plugin update is inferred from those versions. Agent/storage/schema readiness and the signed current release otherwise pass. No backup or mutation was started.

Restore and reverify the supported Updraft backup-data path before this development canary. Do not bypass the backup gate or bundle a backup-integration change into the startup release.

### Gloves production: exact plan ready, execution unapproved

Exact URL: `https://gloves-online.com/`, freshly resolved site 117. The targeted sync timed out, but readback proves `last_sync=2026-09-25T15:11:46+00:00`. Its signed baseline remains `2026.09.24-sendgrid-optional-fleet` with Config Helper 0.1.64.

The canonical planner validates the retained inventory, target and exact rollback artifacts. Repeated controller preflight after plan construction at 15:14:40 UTC returns `ready=true`, S3 remote backup readiness, active 0.1.64, target 0.1.66, and no blockers. Precondition hash: `fb633de299ad689f76ce8c726ff17098b165163d727829990db0b07fd9b764eb`.

Plan SHA-256: `74fe501f892177e5e54f96217e71d011efcbb1f1fd8eaacc21dc89264ebe93d8`. `gloves-selective-plan.json` binds the exact baseline, forward package, rollback package, source commits and active-state policy. Readiness does not equal a completed backup or authorization. The standard development canary should pass first unless the owner explicitly changes the sequence.

## Remaining work

1. Resolve/reverify Trilliant's supported backup API readiness, then produce a fresh ready selective plan.
2. Obtain the exact Fleet execution confirmation for the named canaries. The Fleet workflow requires review of the current site, versions, baseline and precondition hash; broad release preparation does not supply that hash confirmation.
3. Capture comparable authenticated web measurements before each approved canary write. Immediately create and verify its labeled remote database-only Updraft backup, then apply only Config Helper through the approved selective workflow.
4. Verify signed installed version/tree/file count, loaded/active state, unchanged other components, frontend/REST, editor/ACF allow-lists and saves; repeat web measurements under comparable conditions. Keep each site's results separate.
5. On any regression, stop the cohort; use a fresh backup and separately confirmed exact prior package, then verify restored runtime and behavior. Expand only after the established canary/cohort approval gates.

No site received 0.1.66 in this task. No fleet-wide performance or deployment-completion claim is made. Character Count asset scoping and further ACF/Woo startup work remain separate.

## Publication evidence ordering

The promotion gate treats all post-lock documentation as source drift. Qualification receipts above refer to the initial immutable seal and are retained without modification. The final `r2` lock is generated only after this report is committed, and its CI/promotion/build receipts are generated outside the source tree to avoid another post-lock source change. The final assistant handoff reports their results and checksums.
