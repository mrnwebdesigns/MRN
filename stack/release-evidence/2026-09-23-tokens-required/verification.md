# Required Tokens verification

MRN Tokens `0.1.4` source was accepted in standalone PR #1 at merged commit
`f257964af13d2b5f1e428c65ca2311c8f008f9e1`. The PHP runtime class and toolbar
loader are unchanged from `0.1.3`; release metadata and package boundaries are
updated. The GitHub PHP lint and both focused test suites passed.

The complete Stack contract suite passed: 71 tests, including the generated
builder/controller/child package contract, required Tokens policy, immutable
historical lock checksums, assembly, deployment and rollback tests.

## Local runtime

Target: `https://platform.localhost`, with its matching local WordPress root.
The existing Tokens directory was backed up before installing the deterministic
four-file `0.1.4` ZIP. No remote site was changed.

Real WordPress checks passed after installation:

- `MRN_TOKENS_VERSION` is `0.1.4`; the plugin remains active.
- The serialized saved-token option has the same SHA-256 before and after.
- Stack mode connects to the canonical business-information API.
- Anonymous registry reads are denied; administrator reads return HTTP 200 and
  the registry count matches the PHP API.
- The administrator page renders the custom-token controls, WordPress settings
  nonce and `options.php` action.
- An in-memory custom token renders escaped shortcode text; an unknown token
  renders an empty string. The check writes no test tokens to the database.

Whole-plugin source QA and clean release metadata/API QA pass; see the adjacent
reports. The latter is explicitly component source/API coverage: browser,
accessibility, performance and Core Web Vitals rows were excluded from that
component run and are not being represented as passed.

## Remaining qualification

The full reference-site run is retained as `reference-site-release-qa.md`.
It recorded existing accessibility and performance/Core Web Vitals failures,
including HTTP 503 responses from the reference frontend. A subsequent direct
browser attempt also timed out before the Tokens screen could be inspected.
Authenticated save/reload browser verification remains required on the intended
rollout target. The new package must not be reported as production-ready on the
strength of component tests alone.

The existing Fleet contract requires required standard plugins to be active
before preflight. Gloves Online was confirmed missing Tokens, so use the
same-source prerequisite ZIP with the approved backup/confirmation/activation
flow before the full Fleet package. Re-resolve its exact current MainWP identity
when rollout begins. No production installation has occurred.
