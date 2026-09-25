import assert from "node:assert/strict";
import crypto from "node:crypto";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import test from "node:test";

import {
  FleetUpdateError,
  assertConfirmationGate,
  buildInventory,
  buildPreflightInput,
  buildWriteInput,
  compareVersions,
  normalizeSiteUrl,
  parseArgs,
  parseMainwpServerConfig,
  parseResourceResult,
  parseToolResult,
  runFleetUpdate,
  selectReleaseContext,
  verifyPostUpdateRuntime,
  verifyRegisteredArtifact,
} from "../scripts/mrn-fleet-update.mjs";

const SHA_A = "a".repeat(64);
const SHA_B = "b".repeat(64);
const SHA_C = "c".repeat(64);
const COMPONENT_CATALOG = JSON.parse(
  fs.readFileSync(new URL("../manifests/component-catalog.json", import.meta.url), "utf8"),
);
const CONFIG_HELPER_VERSION = COMPONENT_CATALOG.components.find(
  (component) => component.slug === "mrn-config-helper",
)?.version;

test("planning is the default and execution requires two exact confirmations", () => {
  const planned = parseArgs([
    "update",
    "--site",
    "example.com",
    "--component",
    "mrn-config-helper",
  ]);
  assert.equal(planned.execute, false);

  assert.throws(
    () => parseArgs([
      "update",
      "--site",
      "example.com",
      "--component",
      "mrn-config-helper",
      "--execute",
    ]),
    FleetUpdateError,
  );

  const executable = parseArgs([
    "update",
    "--site",
    "https://example.com/",
    "--component",
    "mrn-config-helper",
    "--execute",
    "--approve",
    SHA_A,
    "--confirm-site",
    "https://example.com",
  ]);
  assert.equal(executable.execute, true);
  assert.equal(executable.approve, SHA_A);
});

test("site URLs normalize to one credential-free HTTPS identity", () => {
  assert.equal(normalizeSiteUrl("Example.COM/"), "https://example.com");
  assert.equal(normalizeSiteUrl("https://example.com/subsite/"), "https://example.com/subsite");
  assert.throws(() => normalizeSiteUrl("http://example.com"), FleetUpdateError);
  assert.throws(() => normalizeSiteUrl("https://user:pass@example.com"), FleetUpdateError);
  assert.throws(() => normalizeSiteUrl("https://example.com/?target=other"), FleetUpdateError);
});

test("version comparison is numeric and downgrade-safe", () => {
  assert.equal(compareVersions("0.1.9", "0.1.10"), -1);
  assert.equal(compareVersions("1.2.0", "1.2"), 0);
  assert.equal(compareVersions("2.0.0", "1.9.9"), 1);
  assert.throws(() => compareVersions("latest", "1.0.0"), FleetUpdateError);
});

test("Codex config parser reads only the named MainWP server command", () => {
  const parsed = parseMainwpServerConfig(`
[mcp_servers.other]
command = "wrong"
args = ["/wrong.js"]

[mcp_servers.mainwp]
command = "node"
args = ["/opt/mainwp/dist/index.js"]

[features]
enabled = true
`);
  assert.deepEqual(parsed, { command: "node", args: ["/opt/mainwp/dist/index.js"] });
});

test("MCP response parsers preserve JSON and fail closed on errors", () => {
  assert.deepEqual(
    parseToolResult({ content: [{ type: "text", text: '{"ready":true}' }] }, "tool").payload,
    { ready: true },
  );
  assert.throws(
    () => parseToolResult({ content: [{ type: "text", text: '{"error":{"message":"blocked"}}' }], isError: true }, "tool"),
    /blocked/,
  );
  assert.deepEqual(
    parseResourceResult({ contents: [{ text: '{"connected":true}' }] }, "resource"),
    { connected: true },
  );
});

test("registered artifact bytes must match size and SHA-256", () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), "mrn-fleet-artifact-"));
  const relative = "releases/stack-plugins/mrn-test-1.0.0.zip";
  const absolute = path.join(root, relative);
  fs.mkdirSync(path.dirname(absolute), { recursive: true });
  fs.writeFileSync(absolute, "exact artifact bytes");
  const checksum = crypto.createHash("sha256").update("exact artifact bytes").digest("hex");
  const release = {
    slug: "mrn-test",
    version: "1.0.0",
    package: {
      path: relative,
      filename: path.basename(absolute),
      size_bytes: fs.statSync(absolute).size,
      sha256: checksum,
    },
    tree: { hash_algorithm: "sha256-tree-v1", sha256: SHA_A, file_count: 2 },
  };
  const verified = verifyRegisteredArtifact(release, [root]);
  assert.equal(verified.package_sha256, checksum);
  assert.equal(Buffer.from(verified.package_base64, "base64").toString(), "exact artifact bytes");

  release.package.sha256 = SHA_B;
  assert.throws(() => verifyRegisteredArtifact(release, [root]), FleetUpdateError);
  fs.rmSync(root, { recursive: true, force: true });
});

test("selective release selection refuses nonstandard components and recognizes no-change", () => {
  const catalog = {
    components: [{
      slug: "mrn-test",
      version: "1.1.0",
      runtime_type: "standard-plugin",
      target_tier: "platform-required",
      current_distribution: "standard-bootstrap",
    }],
  };
  const runtimeReport = {
    components: [{
      slug: "mrn-test",
      version: "1.1.0",
      runtime_type: "standard-plugin",
      loaded: true,
      path: "plugins/mrn-test",
      hash_algorithm: "sha256-tree-v1",
      sha256: SHA_A,
      file_count: 2,
    }],
  };
  const selected = selectReleaseContext({
    catalog,
    registry: { releases: [] },
    runtimeReport,
    component: "mrn-test",
    artifactRoots: [],
  });
  assert.equal(selected.noChange, true);

  catalog.components[0].runtime_type = "mu-component";
  assert.throws(
    () => selectReleaseContext({ catalog, registry: { releases: [] }, runtimeReport, component: "mrn-test", artifactRoots: [] }),
    /not eligible/,
  );
});

test("inventory and ability inputs preserve the exact site, baseline, and artifacts", () => {
  const site = { id: 117, url: "https://example.com" };
  const report = {
    release_lock: { present: true, valid: true, release_id: "release-1", sha256: SHA_A },
  };
  const component = {
    slug: "mrn-test",
    version: "1.0.0",
    runtime_type: "standard-plugin",
    loaded: true,
    path: "plugins/mrn-test",
    hash_algorithm: "sha256-tree-v1",
    sha256: SHA_B,
    file_count: 3,
  };
  const preflight = {
    installed: { installed: true, active: true, version: "1.0.0" },
    backup_readiness: { ready: true, remote_destination_configured: true, backup_api_available: true },
  };
  const inventory = buildInventory({
    site,
    runtimeReport: report,
    runtimeComponent: component,
    preflight,
    syncedAt: "2026-09-25T12:00:00Z",
    mainFile: "mrn-test/mrn-test.php",
  });
  assert.equal(inventory.site.site_id, 117);
  assert.equal(inventory.site.release.lock_sha256, SHA_A);
  assert.equal(inventory.site.plugin.tree_sha256, SHA_B);

  const preflightInput = buildPreflightInput({
    planId: "mrn-test-1.1.0-site-117",
    site,
    releaseLock: report.release_lock,
    mainFile: "mrn-test/mrn-test.php",
    target: { version: "1.1.0", filename: "target.zip", package_sha256: SHA_A, package_base64: "dGFyZ2V0", tree_sha256: SHA_B, file_count: 4 },
    rollback: { version: "1.0.0", filename: "rollback.zip", package_sha256: SHA_B, package_base64: "cm9sbGJhY2s=", tree_sha256: SHA_C, file_count: 3 },
  });
  const write = buildWriteInput(preflightInput, SHA_C, { site_id: 117, nonce: "abc", receipt: "receipt" });
  assert.equal(write.operation, undefined);
  assert.equal(write.precondition_hash, SHA_C);
  assert.equal(write.confirm, true);
});

test("fresh runtime verification requires exact target identity", () => {
  const target = { version: "1.1.0", tree_sha256: SHA_A, file_count: 4 };
  const report = {
    components: [{ slug: "mrn-test", loaded: true, version: "1.1.0", sha256: SHA_A, file_count: 4 }],
  };
  assert.equal(verifyPostUpdateRuntime(report, "mrn-test", target).version, "1.1.0");
  report.components[0].sha256 = SHA_B;
  assert.throws(() => verifyPostUpdateRuntime(report, "mrn-test", target), /does not match/);
});

test("safe mode is detected before a backup or update can start", async () => {
  const client = {
    async callTool() {
      return {
        content: [{ type: "text", text: JSON.stringify({ error: "SAFE_MODE_BLOCKED" }) }],
        isError: true,
      };
    },
  };
  await assert.rejects(
    () => assertConfirmationGate(client, { site_id: 117 }),
    /safe mode is on/i,
  );
});

test("top-level no-change flow exact-resolves, syncs, and reads the signed runtime", async () => {
  const toolNames = [
    "get_site_v1",
    "sync_sites_v1",
    "mrn_mainwp__get_stack_runtime_report_v1",
    "mrn_mainwp__preflight_stack_plugin_update_v1",
    "mrn_mainwp__start_database_backup_v1",
    "mrn_mainwp__get_database_backup_status_v1",
    "mrn_mainwp__update_stack_plugin_v1",
  ];
  const calls = [];
  const client = {
    async readResource() {
      return { contents: [{ text: JSON.stringify({ connected: true, dashboardHost: "wpcontrol.mrndev.io", abilitiesCount: 84 }) }] };
    },
    async listTools() {
      return { tools: toolNames.map((name) => ({ name })) };
    },
    async callTool({ name, arguments: input }) {
      calls.push({ name, input });
      let payload;
      if (name === "get_site_v1") {
        payload = {
          id: 117,
          url: "https://example.com/",
          name: "Example",
          status: "connected",
          last_sync: new Date().toISOString(),
        };
      } else if (name === "sync_sites_v1") {
        payload = { success: true, site_ids: [117] };
      } else if (name === "mrn_mainwp__get_stack_runtime_report_v1") {
        payload = {
          site_id: 117,
          site_url: "https://example.com/",
          report: {
            schema_version: 1,
            release_lock: { present: true, valid: true, release_id: "release-1", sha256: SHA_A },
            components: [{
              slug: "mrn-config-helper",
              runtime_type: "standard-plugin",
              version: CONFIG_HELPER_VERSION,
              loaded: true,
              path: "plugins/mrn-config-helper",
              hash_algorithm: "sha256-tree-v1",
              sha256: SHA_B,
              file_count: 17,
            }],
          },
        };
      } else {
        throw new Error(`Unexpected tool ${name}`);
      }
      return { content: [{ type: "text", text: JSON.stringify(payload) }] };
    },
  };

  const summary = await runFleetUpdate({
    site: "example.com",
    component: "mrn-config-helper",
    execute: false,
    approve: "",
    confirmSite: "",
    smokePaths: [],
    outputDir: "",
    configPath: "",
    backupTimeoutSeconds: 60,
    json: true,
  }, { client });

  assert.equal(summary.status, "no_change");
  assert.equal(summary.site_id, 117);
  assert.deepEqual(calls.find((entry) => entry.name === "sync_sites_v1")?.input, { site_ids: [117] });
  assert.equal(calls.filter((entry) => entry.name === "get_site_v1").length, 2);
});

test("a MainWP sync timeout is accepted only after fresh exact-site readback", async () => {
  const toolNames = [
    "get_site_v1",
    "sync_sites_v1",
    "mrn_mainwp__get_stack_runtime_report_v1",
    "mrn_mainwp__preflight_stack_plugin_update_v1",
    "mrn_mainwp__start_database_backup_v1",
    "mrn_mainwp__get_database_backup_status_v1",
    "mrn_mainwp__update_stack_plugin_v1",
  ];
  let siteReads = 0;
  const client = {
    async readResource() {
      return { contents: [{ text: JSON.stringify({ connected: true, dashboardHost: "wpcontrol.mrndev.io", abilitiesCount: 84 }) }] };
    },
    async listTools() {
      return { tools: toolNames.map((name) => ({ name })) };
    },
    async callTool({ name }) {
      if (name === "get_site_v1") {
        siteReads += 1;
        return {
          content: [{ type: "text", text: JSON.stringify({
            id: 117,
            url: "https://example.com/",
            name: "Example",
            status: "connected",
            last_sync: new Date().toISOString(),
          }) }],
        };
      }
      if (name === "sync_sites_v1") {
        return {
          content: [{ type: "text", text: JSON.stringify({ error: { message: "Request timeout after 30000ms" } }) }],
          isError: true,
        };
      }
      if (name === "mrn_mainwp__get_stack_runtime_report_v1") {
        return {
          content: [{ type: "text", text: JSON.stringify({
            site_id: 117,
            site_url: "https://example.com/",
            report: {
              schema_version: 1,
              release_lock: { present: true, valid: true, release_id: "release-1", sha256: SHA_A },
              components: [{
                slug: "mrn-config-helper",
                runtime_type: "standard-plugin",
                version: CONFIG_HELPER_VERSION,
                loaded: true,
                path: "plugins/mrn-config-helper",
                hash_algorithm: "sha256-tree-v1",
                sha256: SHA_B,
                file_count: 17,
              }],
            },
          }) }],
        };
      }
      throw new Error(`Unexpected tool ${name}`);
    },
  };

  const summary = await runFleetUpdate({
    site: "example.com",
    component: "mrn-config-helper",
    execute: false,
    approve: "",
    confirmSite: "",
    smokePaths: [],
    outputDir: "",
    configPath: "",
    backupTimeoutSeconds: 60,
    json: true,
  }, { client });

  assert.equal(summary.status, "no_change");
  assert.equal(siteReads, 2);
});

test("execution rechecks, probes safe mode, backs up, confirms, and verifies in order", async () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), "mrn-fleet-execute-"));
  try {
    const targetPath = path.join(root, "mrn-test-1.1.0.zip");
    const rollbackPath = path.join(root, "mrn-test-1.0.0.zip");
    fs.writeFileSync(targetPath, "target package");
    fs.writeFileSync(rollbackPath, "rollback package");
    const packageRecord = (filePath) => ({
      path: filePath,
      filename: path.basename(filePath),
      size_bytes: fs.statSync(filePath).size,
      sha256: crypto.createHash("sha256").update(fs.readFileSync(filePath)).digest("hex"),
      main_file: "mrn-test/mrn-test.php",
    });
    const targetPackage = packageRecord(targetPath);
    const rollbackPackage = packageRecord(rollbackPath);
    const targetTree = { hash_algorithm: "sha256-tree-v1", sha256: SHA_C, file_count: 4 };
    const rollbackTree = { hash_algorithm: "sha256-tree-v1", sha256: SHA_B, file_count: 3 };
    const catalog = {
      components: [{
        slug: "mrn-test",
        version: "1.1.0",
        runtime_type: "standard-plugin",
        target_tier: "platform-required",
        current_distribution: "standard-bootstrap",
      }],
    };
    const registry = {
      releases: [
        { slug: "mrn-test", version: "1.1.0", package: targetPackage, tree: targetTree },
        { slug: "mrn-test", version: "1.0.0", package: rollbackPackage, tree: rollbackTree },
      ],
    };
    const toolNames = [
      "get_site_v1",
      "sync_sites_v1",
      "mrn_mainwp__get_stack_runtime_report_v1",
      "mrn_mainwp__preflight_stack_plugin_update_v1",
      "mrn_mainwp__start_database_backup_v1",
      "mrn_mainwp__get_database_backup_status_v1",
      "mrn_mainwp__update_stack_plugin_v1",
    ];
    const calls = [];
    let updated = false;
    const response = (payload) => ({ content: [{ type: "text", text: JSON.stringify(payload) }] });
    const client = {
      async readResource() {
        return { contents: [{ text: JSON.stringify({ connected: true, dashboardHost: "wpcontrol.mrndev.io", abilitiesCount: 84 }) }] };
      },
      async listTools() {
        return { tools: toolNames.map((name) => ({ name })) };
      },
      async callTool({ name, arguments: input }) {
        calls.push({ name, input });
        if (name === "get_site_v1") {
          return response({
            id: 117,
            url: "https://example.com/",
            name: "Example",
            status: "connected",
            last_sync: new Date().toISOString(),
          });
        }
        if (name === "sync_sites_v1") {
          return response({ success: true, site_ids: [117] });
        }
        if (name === "mrn_mainwp__get_stack_runtime_report_v1") {
          return response({
            site_id: 117,
            site_url: "https://example.com/",
            report: {
              schema_version: 1,
              release_lock: { present: true, valid: true, release_id: "release-1", sha256: SHA_A },
              components: [{
                slug: "mrn-test",
                runtime_type: "standard-plugin",
                version: updated ? "1.1.0" : "1.0.0",
                loaded: true,
                path: "plugins/mrn-test",
                hash_algorithm: "sha256-tree-v1",
                sha256: updated ? SHA_C : SHA_B,
                file_count: updated ? 4 : 3,
              }],
            },
          });
        }
        if (name === "mrn_mainwp__preflight_stack_plugin_update_v1") {
          return response({
            ready: true,
            operation: input.operation,
            operation_type: "selective-stack-plugin",
            plan_id: input.plan_id,
            site_id: input.site_id,
            site_url: input.site_url,
            plugin_slug: input.plugin_slug,
            baseline: input.baseline,
            precondition_hash: SHA_A,
            installed: { installed: true, active: true, version: "1.0.0" },
            target: {
              version: input.target.version,
              package_sha256: input.target.package_sha256,
              tree_sha256: input.target.tree_sha256,
              file_count: input.target.file_count,
            },
            backup_readiness: { ready: true, remote_destination_configured: true, backup_api_available: true },
            rollback_readiness: {
              ready: true,
              version: input.rollback.version,
              package_sha256: input.rollback.package_sha256,
              tree_sha256: input.rollback.tree_sha256,
              file_count: input.rollback.file_count,
            },
            blockers: [],
          });
        }
        if (name === "mrn_mainwp__start_database_backup_v1") {
          return response({ site_id: 117, nonce: "abc123" });
        }
        if (name === "mrn_mainwp__get_database_backup_status_v1") {
          return response({ status: "complete", receipt: "verified-receipt" });
        }
        if (name === "mrn_mainwp__update_stack_plugin_v1") {
          if (input.user_confirmed === true) {
            updated = true;
            return response({
              success: true,
              operation: "update",
              plan_id: input.plan_id,
              site_id: input.site_id,
              site_url: input.site_url,
              plugin_slug: input.plugin_slug,
              from_version: "1.0.0",
              to_version: "1.1.0",
              active: true,
              package_sha256: input.target.package_sha256,
              tree_sha256: input.target.tree_sha256,
              file_count: input.target.file_count,
              baseline: input.baseline,
              baseline_component_match: true,
              matches_component_plan: true,
              receipt_consumed: true,
            });
          }
          return response({ status: "CONFIRMATION_REQUIRED", confirmation_token: input.backup_receipt.receipt === "confirmation-gate-probe" ? "probe-token" : "write-token" });
        }
        throw new Error(`Unexpected tool ${name}`);
      },
    };
    const runPlanBuilder = ({ planPath, planId }) => {
      const plan = {
        plan_id: planId,
        site: { site_id: 117, site_url: "https://example.com" },
        baseline: { release_id: "release-1", lock_sha256: SHA_A },
        plugin: {
          slug: "mrn-test",
          main_file: "mrn-test/mrn-test.php",
          target: { version: "1.1.0", package: { path: targetPath, filename: targetPackage.filename, sha256: targetPackage.sha256 }, tree: targetTree },
          rollback: { version: "1.0.0", package: { path: rollbackPath, filename: rollbackPackage.filename, sha256: rollbackPackage.sha256 }, tree: rollbackTree },
        },
      };
      fs.writeFileSync(planPath, `${JSON.stringify(plan)}\n`);
      return plan;
    };
    const summary = await runFleetUpdate({
      site: "example.com",
      component: "mrn-test",
      execute: true,
      approve: SHA_A,
      confirmSite: "https://example.com",
      smokePaths: ["/health/"],
      outputDir: path.join(root, "evidence"),
      configPath: "",
      backupTimeoutSeconds: 60,
      json: true,
    }, {
      client,
      catalog,
      registry,
      artifactRoots: [root],
      runPlanBuilder,
      smokeCheck: async () => [{ path: "/", status: 200, ok: true }],
    });

    assert.equal(summary.status, "verified");
    assert.equal(summary.from_version, "1.0.0");
    assert.equal(summary.to_version, "1.1.0");
    const writeIndexes = calls
      .map((call, index) => (call.name === "mrn_mainwp__update_stack_plugin_v1" ? index : -1))
      .filter((index) => index >= 0);
    const backupIndex = calls.findIndex((call) => call.name === "mrn_mainwp__start_database_backup_v1");
    assert.equal(writeIndexes.length, 3);
    assert.ok(writeIndexes[0] < backupIndex);
    assert.ok(backupIndex < writeIndexes[1]);
    assert.ok(writeIndexes[1] < writeIndexes[2]);
    assert.equal(calls.filter((call) => call.name === "mrn_mainwp__preflight_stack_plugin_update_v1").length, 2);
  } finally {
    fs.rmSync(root, { recursive: true, force: true });
  }
});
