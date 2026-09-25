# Config Helper startup performance and compatibility

Status: source merged at `1a326a5e9977dc2a45da5ed7444a58897fecd955` (0.1.66); Stack metadata and lock are merged (MRN PRs #129 and #130); clean-main candidate reconciliation and deterministic Fleet builds pass. This task has performed no remote deployment.

## Method

Final comparison: Config Helper 0.1.65 (`13d3138`) versus 0.1.66 (`dbede29`, same tree as merged `1a326a5`), current Stack parent 1.5.0 (`7033089`), WordPress 7.1.2, ACF Pro 6.8.10, Classic Editor and Reusable Block Library 0.2.0. WooCommerce 10.9.4 contains 25 synthetic HPOS orders. Fixtures use separate disposable tables and physical files; these are not copies of either client database.

HTTP: PHP 8.5.6 cli-server on loopback, OPcache enabled, no page cache, unique query strings and no-cache request headers, same missing/default Config Helper option, same database and cookies. ABBA blocks have five samples per route after warming each route; each block restarts its server. The MU timing probe is absent during these measurements. TTFB covers the document/endpoint, not a browser waterfall or complete Customers application load. AJAX is the expected HTTP 400 no-action startup response; cron is an empty scheduled-event endpoint response.

Diagnostic counts use two measured requests per variant and a temporary local MU probe, recording loaded-source hash and reader existence. Inclusive callback timings must not be summed. CLI uses five fresh processes per variant, OPcache disabled, and includes WP-CLI process startup. CLI timings do not predict web-request improvements.

The earlier exploratory run used 0.1.64 and an older local fixture. Its retained raw observations include mixed route timings; they are not pooled with this final comparison. Invalid initial symlink/OPcache runs were discarded because source-identity checks failed.

## Uninstrumented HTTP results

Milliseconds; negative reduction means the candidate was slower. Each version has n=10 per route. IQR shows run variability; this small local sample does not establish production or fleet-wide gains.

| Fixture | Route | Before median (IQR) | After median (IQR) | Reduction |
| --- | --- | ---: | ---: | ---: |
| standard | dashboard | 741.2 (701.5–894.7) | 199.3 (193.6–213.0) | 73.1% |
| standard | pages | 796.7 (708.3–863.8) | 195.0 (191.4–227.4) | 75.5% |
| standard | editor | 911.1 (765.3–935.3) | 969.8 (891.1–1067.2) | -6.4% |
| standard | settings | 725.1 (708.3–829.9) | 219.7 (201.9–238.5) | 69.7% |
| standard | rest | 283.8 (280.1–323.6) | 200.1 (193.3–211.9) | 29.5% |
| standard | ajax | 800.6 (720.9–845.7) | 181.5 (177.7–191.7) | 77.3% |
| standard | cron | 326.6 (292.5–398.6) | 183.3 (179.1–188.5) | 43.9% |
| standard | frontend | 380.4 (352.0–400.3) | 419.0 (406.9–448.6) | -10.2% |
| woo | dashboard | 904.6 (838.7–1003.7) | 344.9 (295.7–384.0) | 61.9% |
| woo | pages | 924.8 (879.4–1033.6) | 292.9 (283.2–341.6) | 68.3% |
| woo | orders | 965.3 (921.5–1040.4) | 360.8 (347.3–392.9) | 62.6% |
| woo | customers | 945.7 (922.9–956.6) | 333.9 (305.3–396.2) | 64.7% |
| woo | editor | 1398.4 (1350.6–1477.0) | 1229.6 (1163.6–1310.1) | 12.1% |
| woo | settings | 891.1 (865.5–977.7) | 934.4 (898.1–1055.7) | -4.9% |
| woo | rest | 371.9 (365.2–391.3) | 244.5 (220.1–286.6) | 34.3% |
| woo | ajax | 759.9 (733.2–777.7) | 229.3 (212.2–243.0) | 69.8% |
| woo | cron | 357.5 (346.9–403.9) | 241.6 (198.9–290.3) | 32.4% |
| woo | frontend | 551.0 (464.7–614.0) | 445.5 (425.3–477.9) | 19.1% |

The deterministic improvement is removal of unrelated layout discovery. Standard editor/frontend and Woo settings medians were slower in this run; other workload and local timing variation remain. No claim is made that every route becomes faster, or that these percentages apply to Gloves or the fleet.

## Diagnostic discovery and registration callback

| Fixture | Route | Discovery calls before → after | Registration callback median ms before → after |
| --- | --- | ---: | ---: |
| standard | dashboard | 226 → 0 | 410.53 → 1.18 |
| standard | pages | 229 → 0 | 412.81 → 1.32 |
| standard | editor | 1883 → 155 | 412.86 → 0.92 |
| standard | settings | 267 → 4 | 410.76 → 1.11 |
| standard | rest | 208 → 0 | 114.72 → 1.11 |
| standard | ajax | 208 → 0 | 410.43 → 1.16 |
| standard | cron | 208 → 0 | 92.80 → 0.94 |
| standard | frontend | 215 → 0 | 91.80 → 1.01 |
| woo | dashboard | 288 → 0 | 482.90 → 1.20 |
| woo | pages | 299 → 0 | 548.44 → 1.12 |
| woo | orders | 279 → 0 | 541.36 → 1.11 |
| woo | customers | 279 → 0 | 471.20 → 1.78 |
| woo | editor | 2085 → 155 | 520.78 → 1.16 |
| woo | settings | 327 → 4 | 474.37 → 1.39 |
| woo | rest | 268 → 0 | 110.51 → 1.33 |
| woo | ajax | 268 → 0 | 504.90 → 1.37 |
| woo | cron | 268 → 0 | 128.40 → 1.35 |
| woo | frontend | 275 → 0 | 106.00 → 1.40 |

Editor and Site Configurations discovery remains because those consumers need layouts. Remaining editor work is deliberately outside this release.

## Separate CLI measurements

| Fixture | Before median ms | After median ms |
| --- | ---: | ---: |
| standard | 664.8 | 487.7 |
| woo | 930.6 | 665.6 |

## Compatibility and QA

- 123 focused assertions in each of six contexts (738 total): frontend, admin, REST, AJAX, cron and CLI. Covers missing/malformed/default/configured settings, hidden content types, legacy disabled lists, explicit empty allow-lists, per-type intersections, early/during/after ACF timing, late registrations, option/filter changes, reentrancy and exception guard reset.
- Real WordPress/ACF integration passes in both fixtures: registered fields, settings normalization, ACF save/read/restore, REST and Woo product/order contracts.
- Authenticated Playwright checks pass: 16 standard and 18 Woo checks, zero JavaScript errors. Editor ACF save/reload and frontend output; desktop/tablet/mobile overflow checks; heartbeat AJAX; hidden Event toggle save/readback/restore; builder defaults remain selected.
- Woo Orders rows and Customers report both settle; Customers analytics REST and import-status requests return 200, with no JavaScript errors.
- MRN release QA passes against each named runtime: PHP lint/WPCS/security/PHP compatibility, REST, browser smoke, axe WCAG checks, page speed and CWV. PHPStan is advisory with 21 unavailable-symbol findings in test/runtime code. Semgrep has no configured ruleset and is skipped. Standalone runs skip Stack parity/rollout/license checks; those belong to the separate Stack release and per-site gates.
- No global ACF disablement, domain condition, cache, new plugin, theme/layout change, setting migration write, public signature change or Character Count change.

## Rollout and limits

Proposed sequence: Trilliant development, then Gloves production, each using a fresh exact selective Fleet plan for Config Helper only, verified remote database backup, owner-approved precondition hash, and signed post-write version/tree/active-state verification. Baseline remains the site’s signed release plus an explicit Config Helper overlay. Retain exact 0.1.64 and 0.1.65 packages; any rollback requires a fresh backup and its own confirmation.

Remote canary deployment, comparable authenticated production before/after measurements, deployed-runtime editor/save qualification and cohort rollout remain pending. Trilliant development is blocked by Updraft backup API readiness. Gloves has an exact ready selective plan, pending production confirmation and a fresh verified backup. See RELEASE-STATUS.md for current evidence. Do not mark the fleet current from this local report.

See ../../docs/MRN-CONFIG-HELPER-STARTUP.md for caller trace and responsibilities. Raw JSON and QA reports adjacent to this file provide the measured evidence.
