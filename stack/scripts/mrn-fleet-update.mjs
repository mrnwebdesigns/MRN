#!/usr/bin/env node

import crypto from "node:crypto";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { execFileSync, spawnSync } from "node:child_process";
import { fileURLToPath, pathToFileURL } from "node:url";

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const REPOSITORY_ROOT = path.resolve(SCRIPT_DIR, "../..");
const EXPECTED_DASHBOARD_HOST = "wpcontrol.mrndev.io";
const MAINWP_STATUS_URI = "mainwp://status";
const DEFAULT_CONFIG_PATH = path.join(os.homedir(), ".codex", "config.toml");
const DEFAULT_BACKUP_TIMEOUT_SECONDS = 600;
const DEFAULT_BACKUP_POLL_SECONDS = 5;
const SHA256_RE = /^[a-f0-9]{64}$/;
const COMPONENT_RE = /^mrn-[a-z0-9-]+$/;
const REQUIRED_TOOLS = [
  "get_site_v1",
  "sync_sites_v1",
  "mrn_mainwp__get_stack_runtime_report_v1",
  "mrn_mainwp__preflight_stack_plugin_update_v1",
  "mrn_mainwp__start_database_backup_v1",
  "mrn_mainwp__get_database_backup_status_v1",
  "mrn_mainwp__update_stack_plugin_v1",
];

export class FleetUpdateError extends Error {}

export function usage() {
  return `Usage:
  mrn fleet update --site <https://site.example> --component <mrn-plugin-slug>
  mrn fleet update --site <https://site.example> --component <mrn-plugin-slug> \\
    --execute --approve <precondition-sha256> --confirm-site <https://site.example>

The first command is the mandatory non-deploying planning pass. It exact-resolves
and syncs one MainWP site, verifies the signed Stack baseline, validates the
registered forward and rollback artifacts, and runs controller preflight.

Execution is a separate pass. It requires the exact precondition hash printed
by planning plus the exact site URL, re-runs every precondition, verifies that
MCP safe mode permits the write, creates a fresh remote database-only backup,
applies one standard-plugin update, and performs exact runtime readback.

Options:
  --site <url>                  Exact HTTPS child-site URL or hostname.
  --component <slug>           Platform-required standard-plugin slug.
  --execute                    Apply after a separately reviewed planning pass.
  --approve <sha256>           Exact precondition hash from planning output.
  --confirm-site <url>         Exact site URL; required with --execute.
  --smoke-path <path>          Extra public route to check after an update.
                               May be supplied more than once.
  --output-dir <path>          Evidence directory (default: .tmp/fleet/<plan>).
  --config <path>              Codex config containing [mcp_servers.mainwp].
  --backup-timeout <seconds>   Backup wait limit (default: 600).
  --json                       Print only the final JSON summary.
  -h, --help                   Show this help.

Boundaries:
  - One exact site and one exact component only.
  - Standard Stack plugins only; themes, MU components, and shared runtime
    still require the full Stack release workflow.
  - No new install, no downgrade, and no unregistered artifact.
  - The command never disables MainWP safe mode.
`;
}

function requireValue(argv, index, flag) {
  const value = argv[index + 1];
  if (!value || value.startsWith("--")) {
    throw new FleetUpdateError(`${flag} requires a value.`);
  }
  return value;
}

export function parseArgs(argv) {
  const args = {
    command: "",
    site: "",
    component: "",
    execute: false,
    approve: "",
    confirmSite: "",
    smokePaths: [],
    outputDir: "",
    configPath: DEFAULT_CONFIG_PATH,
    backupTimeoutSeconds: DEFAULT_BACKUP_TIMEOUT_SECONDS,
    json: false,
    help: false,
  };

  const values = [...argv];
  args.command = values.shift() || "";
  for (let index = 0; index < values.length; index += 1) {
    const flag = values[index];
    switch (flag) {
      case "--site":
        args.site = requireValue(values, index, flag);
        index += 1;
        break;
      case "--component":
        args.component = requireValue(values, index, flag);
        index += 1;
        break;
      case "--approve":
        args.approve = requireValue(values, index, flag).toLowerCase();
        index += 1;
        break;
      case "--confirm-site":
        args.confirmSite = requireValue(values, index, flag);
        index += 1;
        break;
      case "--smoke-path":
        args.smokePaths.push(requireValue(values, index, flag));
        index += 1;
        break;
      case "--output-dir":
        args.outputDir = requireValue(values, index, flag);
        index += 1;
        break;
      case "--config":
        args.configPath = requireValue(values, index, flag);
        index += 1;
        break;
      case "--backup-timeout": {
        const raw = requireValue(values, index, flag);
        const seconds = Number(raw);
        if (!Number.isInteger(seconds) || seconds < 30 || seconds > 3600) {
          throw new FleetUpdateError("--backup-timeout must be an integer between 30 and 3600 seconds.");
        }
        args.backupTimeoutSeconds = seconds;
        index += 1;
        break;
      }
      case "--execute":
        args.execute = true;
        break;
      case "--json":
        args.json = true;
        break;
      case "-h":
      case "--help":
        args.help = true;
        break;
      default:
        throw new FleetUpdateError(`Unknown option: ${flag}`);
    }
  }

  if (args.help) {
    return args;
  }
  if (args.command !== "update") {
    throw new FleetUpdateError("The supported Fleet command is: mrn fleet update.");
  }
  if (!args.site || !args.component) {
    throw new FleetUpdateError("--site and --component are required.");
  }
  if (!COMPONENT_RE.test(args.component)) {
    throw new FleetUpdateError("--component must be an MRN plugin directory slug such as mrn-config-helper.");
  }
  if (args.execute) {
    if (!SHA256_RE.test(args.approve)) {
      throw new FleetUpdateError("--execute requires --approve with the exact 64-character precondition hash.");
    }
    if (!args.confirmSite) {
      throw new FleetUpdateError("--execute requires --confirm-site with the exact reviewed site URL.");
    }
  } else if (args.approve || args.confirmSite) {
    throw new FleetUpdateError("--approve and --confirm-site are valid only with --execute.");
  }
  return args;
}

export function normalizeSiteUrl(value) {
  const raw = String(value || "").trim();
  if (!raw) {
    throw new FleetUpdateError("The site URL is empty.");
  }
  const withScheme = /^[a-z][a-z0-9+.-]*:\/\//i.test(raw) ? raw : `https://${raw}`;
  let parsed;
  try {
    parsed = new URL(withScheme);
  } catch {
    throw new FleetUpdateError(`Invalid site URL: ${raw}`);
  }
  if (
    parsed.protocol !== "https:" ||
    !parsed.hostname ||
    parsed.username ||
    parsed.password ||
    parsed.search ||
    parsed.hash
  ) {
    throw new FleetUpdateError("The site must be one credential-free HTTPS URL without query or fragment data.");
  }
  const pathname = parsed.pathname.replace(/\/+$/, "");
  return `${parsed.origin.toLowerCase()}${pathname}`;
}

export function compareVersions(left, right) {
  const parse = (value) => {
    const match = String(value || "").match(/^([0-9]+(?:\.[0-9]+){1,3})(?:[-+].*)?$/);
    if (!match) {
      throw new FleetUpdateError(`Invalid release version: ${value}`);
    }
    return match[1].split(".").map((part) => Number(part));
  };
  const a = parse(left);
  const b = parse(right);
  const length = Math.max(a.length, b.length);
  for (let index = 0; index < length; index += 1) {
    const delta = (a[index] || 0) - (b[index] || 0);
    if (delta !== 0) {
      return delta < 0 ? -1 : 1;
    }
  }
  return 0;
}

export function parseMainwpServerConfig(text) {
  const lines = String(text || "").split(/\r?\n/);
  let inSection = false;
  let command = "";
  let args = [];
  for (const rawLine of lines) {
    const line = rawLine.trim();
    if (line.startsWith("[") && line.endsWith("]")) {
      if (inSection) {
        break;
      }
      inSection = line === "[mcp_servers.mainwp]";
      continue;
    }
    if (!inSection || !line || line.startsWith("#")) {
      continue;
    }
    const commandMatch = line.match(/^command\s*=\s*"((?:[^"\\]|\\.)*)"\s*$/);
    if (commandMatch) {
      command = JSON.parse(`"${commandMatch[1]}"`);
      continue;
    }
    const argsMatch = line.match(/^args\s*=\s*(\[.*\])\s*$/);
    if (argsMatch) {
      try {
        args = JSON.parse(argsMatch[1]);
      } catch {
        throw new FleetUpdateError("Could not parse mcp_servers.mainwp args from Codex config.");
      }
    }
  }
  if (!command || !Array.isArray(args) || args.length < 1 || args.some((item) => typeof item !== "string")) {
    throw new FleetUpdateError("Codex config does not contain a usable [mcp_servers.mainwp] command and args list.");
  }
  return { command, args };
}

function parseJsonText(text, context) {
  try {
    return JSON.parse(String(text || ""));
  } catch {
    throw new FleetUpdateError(`${context} did not return valid JSON.`);
  }
}

export function parseToolResult(result, context, { allowError = false } = {}) {
  const block = Array.isArray(result?.content)
    ? result.content.find((item) => item && item.type === "text" && typeof item.text === "string")
    : null;
  if (!block) {
    throw new FleetUpdateError(`${context} returned no JSON text payload.`);
  }
  const payload = parseJsonText(block.text, context);
  const payloadError = payload?.error;
  if (!allowError && (result?.isError || payloadError)) {
    const message =
      (typeof payloadError === "object" && payloadError?.message) ||
      payload?.message ||
      (typeof payloadError === "string" ? payloadError : "unknown MainWP error");
    throw new FleetUpdateError(`${context} failed: ${message}`);
  }
  return { payload, isError: Boolean(result?.isError || payloadError) };
}

export function parseResourceResult(result, context) {
  const block = Array.isArray(result?.contents)
    ? result.contents.find((item) => item && typeof item.text === "string")
    : null;
  if (!block) {
    throw new FleetUpdateError(`${context} returned no JSON resource payload.`);
  }
  return parseJsonText(block.text, context);
}

function readJson(filePath) {
  try {
    return JSON.parse(fs.readFileSync(filePath, "utf8"));
  } catch (error) {
    throw new FleetUpdateError(`Could not read JSON from ${filePath}: ${error.message}`);
  }
}

function writeJson(filePath, value) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, `${JSON.stringify(value, null, 2)}\n`, { encoding: "utf8", mode: 0o600 });
}

function sha256File(filePath) {
  const hash = crypto.createHash("sha256");
  hash.update(fs.readFileSync(filePath));
  return hash.digest("hex");
}

function canonicalRepositoryRoot() {
  try {
    const common = execFileSync("git", ["rev-parse", "--path-format=absolute", "--git-common-dir"], {
      cwd: REPOSITORY_ROOT,
      encoding: "utf8",
      stdio: ["ignore", "pipe", "pipe"],
    }).trim();
    return path.dirname(common);
  } catch {
    return REPOSITORY_ROOT;
  }
}

export function resolveArtifactPath(packagePath, roots) {
  const raw = String(packagePath || "");
  if (!raw) {
    throw new FleetUpdateError("A registered package path is missing.");
  }
  const candidates = path.isAbsolute(raw)
    ? [raw]
    : roots.filter(Boolean).map((root) => path.resolve(root, raw));
  const match = candidates.find((candidate) => {
    try {
      return fs.statSync(candidate).isFile() && !fs.lstatSync(candidate).isSymbolicLink();
    } catch {
      return false;
    }
  });
  if (!match) {
    throw new FleetUpdateError(`Registered package is missing. Checked: ${candidates.join(", ")}`);
  }
  return match;
}

export function verifyRegisteredArtifact(release, roots) {
  const packageRecord = release?.package || {};
  const tree = release?.tree || {};
  const filePath = resolveArtifactPath(packageRecord.path, roots);
  const stat = fs.statSync(filePath);
  const checksum = sha256File(filePath);
  if (
    path.basename(filePath) !== packageRecord.filename ||
    stat.size !== packageRecord.size_bytes ||
    checksum !== packageRecord.sha256 ||
    tree.hash_algorithm !== "sha256-tree-v1" ||
    !SHA256_RE.test(String(tree.sha256 || "")) ||
    !Number.isInteger(tree.file_count) ||
    tree.file_count < 1
  ) {
    throw new FleetUpdateError(`Registered artifact verification failed for ${release?.slug || "unknown"} ${release?.version || "unknown"}.`);
  }
  return {
    path: filePath,
    version: release.version,
    filename: packageRecord.filename,
    package_sha256: checksum,
    package_base64: fs.readFileSync(filePath).toString("base64"),
    tree_sha256: tree.sha256,
    file_count: tree.file_count,
  };
}

function uniqueMatch(records, predicate, message) {
  const matches = records.filter((record) => record && typeof record === "object" && predicate(record));
  if (matches.length !== 1) {
    throw new FleetUpdateError(message);
  }
  return matches[0];
}

export function selectReleaseContext({ catalog, registry, runtimeReport, component, artifactRoots }) {
  const catalogEntry = uniqueMatch(
    Array.isArray(catalog?.components) ? catalog.components : [],
    (entry) => entry.slug === component,
    `Component catalog must contain exactly one ${component} entry.`,
  );
  if (
    catalogEntry.runtime_type !== "standard-plugin" ||
    catalogEntry.target_tier !== "platform-required" ||
    catalogEntry.current_distribution !== "standard-bootstrap"
  ) {
    throw new FleetUpdateError(
      `${component} is not eligible for selective deployment. Themes, MU components, shared runtime, and nonstandard components require their documented release path.`,
    );
  }
  const runtimeComponent = uniqueMatch(
    Array.isArray(runtimeReport?.components) ? runtimeReport.components : [],
    (entry) => entry.slug === component,
    `The live Stack runtime must report exactly one ${component} component.`,
  );
  if (runtimeComponent.runtime_type !== "standard-plugin" || runtimeComponent.loaded !== true) {
    throw new FleetUpdateError(`${component} is not a loaded standard plugin on the target site.`);
  }
  const targetVersion = String(catalogEntry.version || "");
  const currentVersion = String(runtimeComponent.version || "");
  const comparison = compareVersions(currentVersion, targetVersion);
  const releases = Array.isArray(registry?.releases) ? registry.releases : [];
  if (comparison === 0) {
    if (runtimeComponent.matches_release === true) {
      return { noChange: true, catalogEntry, runtimeComponent, currentVersion, targetVersion };
    }
    if (runtimeComponent.matches_release !== false) {
      throw new FleetUpdateError(`${component} does not report whether it matches the immutable Stack baseline.`);
    }
    const targetRelease = uniqueMatch(
      releases,
      (entry) => entry.slug === component && entry.version === targetVersion,
      `No unique selective target release is registered for ${component} ${targetVersion}.`,
    );
    const target = verifyRegisteredArtifact(targetRelease, artifactRoots);
    return {
      noChange: true,
      catalogEntry,
      runtimeComponent,
      currentVersion,
      targetVersion,
      targetRelease,
      target,
    };
  }
  if (comparison > 0) {
    throw new FleetUpdateError(
      `The site has ${component} ${currentVersion}, which is newer than catalog target ${targetVersion}; selective updates never downgrade.`,
    );
  }
  const targetRelease = uniqueMatch(
    releases,
    (entry) => entry.slug === component && entry.version === targetVersion,
    `No unique selective target release is registered for ${component} ${targetVersion}.`,
  );
  const rollbackRelease = uniqueMatch(
    releases,
    (entry) => entry.slug === component && entry.version === currentVersion,
    `No unique rollback release is registered for live ${component} ${currentVersion}.`,
  );
  const target = verifyRegisteredArtifact(targetRelease, artifactRoots);
  const rollback = verifyRegisteredArtifact(rollbackRelease, artifactRoots);
  const mainFile = String(targetRelease?.package?.main_file || "");
  if (!mainFile || mainFile !== rollbackRelease?.package?.main_file) {
    throw new FleetUpdateError("Target and rollback releases do not use the same registered plugin main file.");
  }
  return {
    noChange: false,
    catalogEntry,
    runtimeComponent,
    currentVersion,
    targetVersion,
    targetRelease,
    rollbackRelease,
    target,
    rollback,
    mainFile,
  };
}

export function buildPreflightInput({ planId, site, releaseLock, mainFile, target, rollback }) {
  return {
    operation: "update",
    plan_id: planId,
    site_id: site.id,
    site_url: site.url,
    plugin_slug: mainFile,
    baseline: {
      release_id: releaseLock.release_id,
      lock_sha256: releaseLock.sha256,
    },
    target: {
      version: target.version,
      filename: target.filename,
      package_sha256: target.package_sha256,
      package_base64: target.package_base64,
      tree_sha256: target.tree_sha256,
      file_count: target.file_count,
    },
    rollback: {
      version: rollback.version,
      filename: rollback.filename,
      package_sha256: rollback.package_sha256,
      package_base64: rollback.package_base64,
      tree_sha256: rollback.tree_sha256,
      file_count: rollback.file_count,
    },
  };
}

export function buildInventory({ site, runtimeReport, runtimeComponent, preflight, syncedAt, mainFile }) {
  const release = runtimeReport?.release_lock || {};
  const installed = preflight?.installed || {};
  return {
    schema_version: 1,
    site: {
      site_id: site.id,
      site_url: site.url,
      inventory_synced_at: syncedAt,
      release: {
        present: release.present === true,
        valid: release.valid === true,
        release_id: release.release_id,
        lock_sha256: release.sha256,
      },
      plugin: {
        slug: runtimeComponent.slug,
        main_file: mainFile,
        installed: installed.installed === true,
        active: installed.active === true,
        loaded: runtimeComponent.loaded === true,
        version: runtimeComponent.version,
        runtime_type: runtimeComponent.runtime_type,
        path: runtimeComponent.path,
        hash_algorithm: runtimeComponent.hash_algorithm,
        tree_sha256: runtimeComponent.sha256,
        file_count: runtimeComponent.file_count,
      },
      backup_readiness: preflight.backup_readiness,
    },
  };
}

export function buildAbilityInputFromPlan(plan) {
  const artifact = (record) => ({
    version: record.version,
    filename: record.package.filename,
    package_sha256: record.package.sha256,
    package_base64: fs.readFileSync(record.package.path).toString("base64"),
    tree_sha256: record.tree.sha256,
    file_count: record.tree.file_count,
  });
  return {
    operation: "update",
    plan_id: plan.plan_id,
    site_id: plan.site.site_id,
    site_url: plan.site.site_url,
    plugin_slug: plan.plugin.main_file,
    baseline: {
      release_id: plan.baseline.release_id,
      lock_sha256: plan.baseline.lock_sha256,
    },
    target: artifact(plan.plugin.target),
    rollback: artifact(plan.plugin.rollback),
  };
}

export function buildWriteInput(preflightInput, preconditionHash, backupReceipt) {
  const { operation: _operation, ...base } = preflightInput;
  return {
    ...base,
    precondition_hash: preconditionHash,
    confirm: true,
    backup_receipt: backupReceipt,
  };
}

function planIdFor(component, version, siteId) {
  return `${component}-${version}-site-${siteId}`;
}

function evidenceRunId() {
  return new Date().toISOString().replace(/[-:.TZ]/g, "");
}

function runPlanBuilder({ inventoryPath, planPath, component, planId, targetPath, rollbackPath, asOf }) {
  const script = path.join(REPOSITORY_ROOT, "stack", "scripts", "build-mainwp-stack-plugin-plan.py");
  const result = spawnSync(
    "python3",
    [
      script,
      "--inventory",
      inventoryPath,
      "--plugin-slug",
      component,
      "--plan-id",
      planId,
      "--target-artifact",
      targetPath,
      "--rollback-artifact",
      rollbackPath,
      "--as-of",
      asOf,
      "--output",
      planPath,
    ],
    {
      cwd: REPOSITORY_ROOT,
      encoding: "utf8",
      maxBuffer: 10 * 1024 * 1024,
    },
  );
  if (result.status !== 0) {
    const detail = String(result.stderr || result.stdout || "").trim();
    throw new FleetUpdateError(`Selective plan builder failed: ${detail || `exit ${result.status}`}`);
  }
  return readJson(planPath);
}

async function connectMainwp(configPath) {
  let configText;
  try {
    configText = fs.readFileSync(configPath, "utf8");
  } catch (error) {
    throw new FleetUpdateError(`Could not read Codex config ${configPath}: ${error.message}`);
  }
  const server = parseMainwpServerConfig(configText);
  const entryPath = server.args.find((item) => path.isAbsolute(item) && fs.existsSync(item));
  if (!entryPath) {
    throw new FleetUpdateError("Could not resolve the configured MainWP MCP server entry file.");
  }
  const packageRoot = path.dirname(path.dirname(entryPath));
  const clientModule = path.join(packageRoot, "node_modules", "@modelcontextprotocol", "sdk", "dist", "esm", "client", "index.js");
  const transportModule = path.join(packageRoot, "node_modules", "@modelcontextprotocol", "sdk", "dist", "esm", "client", "stdio.js");
  if (!fs.existsSync(clientModule) || !fs.existsSync(transportModule)) {
    throw new FleetUpdateError("The configured MainWP MCP pin does not include its client SDK runtime.");
  }
  const [{ Client }, { StdioClientTransport }] = await Promise.all([
    import(pathToFileURL(clientModule).href),
    import(pathToFileURL(transportModule).href),
  ]);
  const client = new Client({ name: "mrn-fleet-update", version: "1.0.0" }, { capabilities: {} });
  const transport = new StdioClientTransport({
    command: server.command,
    args: server.args,
    cwd: REPOSITORY_ROOT,
    stderr: "pipe",
    maxBufferSize: 64 * 1024 * 1024,
  });
  if (transport.stderr) {
    transport.stderr.on("data", () => {});
  }
  await client.connect(transport);
  return client;
}

async function callTool(client, name, args, options = {}) {
  const result = await client.callTool(
    { name, arguments: args },
    undefined,
    { timeout: options.timeout || 120000 },
  );
  return parseToolResult(result, name, { allowError: options.allowError === true });
}

async function assertMainwpControlPlane(client) {
  const statusResult = await client.readResource({ uri: MAINWP_STATUS_URI });
  const status = parseResourceResult(statusResult, MAINWP_STATUS_URI);
  if (
    status.connected !== true ||
    status.dashboardHost !== EXPECTED_DASHBOARD_HOST ||
    !Number.isInteger(status.abilitiesCount) ||
    status.abilitiesCount < 1
  ) {
    throw new FleetUpdateError(
      `MainWP control-plane check failed (connected=${String(status.connected)}, host=${String(status.dashboardHost)}, abilities=${String(status.abilitiesCount)}).`,
    );
  }
  const listed = await client.listTools();
  const names = new Set((listed.tools || []).map((tool) => tool.name));
  const missing = REQUIRED_TOOLS.filter((name) => !names.has(name));
  if (missing.length) {
    throw new FleetUpdateError(`MainWP is missing required Fleet tools: ${missing.join(", ")}`);
  }
  return status;
}

async function resolveExactSite(client, requestedUrl) {
  const { payload } = await callTool(client, "get_site_v1", {
    site_id_or_domain: requestedUrl,
    include_stats: false,
  });
  const id = Number(payload?.id ?? payload?.site_id);
  const url = normalizeSiteUrl(payload?.url ?? payload?.site_url ?? "");
  if (!Number.isInteger(id) || id < 1 || url !== requestedUrl) {
    throw new FleetUpdateError("MainWP did not exact-resolve the requested site URL to one matching child site.");
  }
  if (String(payload?.status || "").toLowerCase() !== "connected") {
    throw new FleetUpdateError(`MainWP site ${id} is not connected.`);
  }
  return { id, url, name: String(payload?.name || ""), lastSync: String(payload?.last_sync || "") };
}

async function freshSync(client, site, startedAt) {
  let syncTimeout = false;
  try {
    await callTool(client, "sync_sites_v1", { site_ids: [site.id] }, { timeout: 180000 });
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    if (!/time(?:d?\s*out|out)/i.test(message)) {
      throw error;
    }
    syncTimeout = true;
  }
  const refreshed = await resolveExactSite(client, site.url);
  const lastSync = Date.parse(refreshed.lastSync);
  const startedAtSecond = startedAt.getTime() - (startedAt.getTime() % 1000);
  if (!Number.isFinite(lastSync) || lastSync < startedAtSecond) {
    throw new FleetUpdateError(
      syncTimeout
        ? "MainWP sync timed out and the exact-site readback did not prove a fresh last_sync."
        : "MainWP sync completed without a fresh exact-site last_sync readback.",
    );
  }
  return { ...refreshed, syncRecoveredFromTimeout: syncTimeout };
}

async function readRuntimeReport(client, site) {
  const { payload } = await callTool(client, "mrn_mainwp__get_stack_runtime_report_v1", { site_id: site.id });
  if (Number(payload?.site_id) !== site.id || normalizeSiteUrl(payload?.site_url || "") !== site.url) {
    throw new FleetUpdateError("Stack runtime report identity does not match the exact MainWP site.");
  }
  const report = payload?.report;
  const release = report?.release_lock;
  if (
    report?.schema_version !== 1 ||
    release?.present !== true ||
    release?.valid !== true ||
    !release?.release_id ||
    !SHA256_RE.test(String(release?.sha256 || ""))
  ) {
    throw new FleetUpdateError("The child site does not report a valid signed Stack release baseline.");
  }
  if (
    !["current", "current_with_approved_overlays", "drifted"].includes(report?.fleet_state) ||
    !Array.isArray(report?.approved_overlays) ||
    !Array.isArray(report?.drifted_required) ||
    !Array.isArray(report?.unknown_drifted_required) ||
    !Array.isArray(report?.stale_approved_overlays)
  ) {
    throw new FleetUpdateError("MainWP Operations API 0.9.8 or newer is required for approved-overlay Fleet state.");
  }
  return report;
}

async function runControllerPreflight(client, input) {
  const { payload } = await callTool(client, "mrn_mainwp__preflight_stack_plugin_update_v1", input, {
    timeout: 180000,
  });
  const baselineMatches =
    payload?.baseline?.release_id === input.baseline.release_id &&
    payload?.baseline?.lock_sha256 === input.baseline.lock_sha256;
  const targetMatches =
    payload?.target?.version === input.target.version &&
    payload?.target?.package_sha256 === input.target.package_sha256 &&
    payload?.target?.tree_sha256 === input.target.tree_sha256 &&
    payload?.target?.file_count === input.target.file_count;
  const rollbackMatches =
    payload?.rollback_readiness?.version === input.rollback.version &&
    payload?.rollback_readiness?.package_sha256 === input.rollback.package_sha256 &&
    payload?.rollback_readiness?.tree_sha256 === input.rollback.tree_sha256 &&
    payload?.rollback_readiness?.file_count === input.rollback.file_count;
  const exactIdentity =
    payload?.operation === input.operation &&
    payload?.operation_type === "selective-stack-plugin" &&
    payload?.plan_id === input.plan_id &&
    Number(payload?.site_id) === input.site_id &&
    normalizeSiteUrl(payload?.site_url || "") === input.site_url &&
    payload?.plugin_slug === input.plugin_slug &&
    baselineMatches &&
    targetMatches &&
    rollbackMatches;
  if (
    payload?.ready !== true ||
    exactIdentity !== true ||
    payload?.installed?.installed !== true ||
    payload?.installed?.active !== true ||
    payload?.backup_readiness?.ready !== true ||
    payload?.rollback_readiness?.ready !== true ||
    !Array.isArray(payload?.blockers) ||
    payload.blockers.length !== 0 ||
    !SHA256_RE.test(String(payload?.precondition_hash || ""))
  ) {
    const blockers = Array.isArray(payload?.blockers) ? payload.blockers.join(", ") : "unknown";
    throw new FleetUpdateError(`Controller preflight is not exact and ready: ${blockers || "identity/readiness mismatch"}.`);
  }
  return payload;
}

export async function assertConfirmationGate(client, writeInput) {
  const probe = {
    ...writeInput,
    backup_receipt: { site_id: writeInput.site_id, nonce: "0", receipt: "confirmation-gate-probe" },
  };
  const { payload } = await callTool(client, "mrn_mainwp__update_stack_plugin_v1", probe, { allowError: true });
  if (payload?.error === "SAFE_MODE_BLOCKED") {
    throw new FleetUpdateError(
      "MainWP safe mode is on. Disable it deliberately for the deployment window, then rerun the exact approved command; this command will not change safe mode.",
    );
  }
  if (payload?.status !== "CONFIRMATION_REQUIRED" || !payload?.confirmation_token) {
    throw new FleetUpdateError("The MainWP two-step confirmation gate is not available; no backup or update was started.");
  }
}

function delay(milliseconds) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

async function createVerifiedBackup(client, siteId, timeoutSeconds) {
  const { payload: started } = await callTool(client, "mrn_mainwp__start_database_backup_v1", { site_id: siteId });
  const nonce = String(started?.nonce || "");
  if (Number(started?.site_id) !== siteId || !/^[0-9a-f]+$/i.test(nonce)) {
    throw new FleetUpdateError("UpdraftPlus did not return a valid exact-site backup nonce.");
  }
  const deadline = Date.now() + timeoutSeconds * 1000;
  while (Date.now() < deadline) {
    const { payload: status } = await callTool(
      client,
      "mrn_mainwp__get_database_backup_status_v1",
      { site_id: siteId, nonce },
      { timeout: 120000 },
    );
    if (status?.status === "complete" && typeof status?.receipt === "string" && status.receipt) {
      return { site_id: siteId, nonce, receipt: status.receipt };
    }
    if (status?.status === "failed") {
      throw new FleetUpdateError(`Remote database backup failed: ${status?.message || "unknown failure"}`);
    }
    await delay(DEFAULT_BACKUP_POLL_SECONDS * 1000);
  }
  throw new FleetUpdateError(`Remote database backup did not complete within ${timeoutSeconds} seconds.`);
}

async function executeConfirmedUpdate(client, writeInput) {
  const preview = await callTool(client, "mrn_mainwp__update_stack_plugin_v1", writeInput);
  const token = String(preview.payload?.confirmation_token || "");
  if (preview.payload?.status !== "CONFIRMATION_REQUIRED" || !token) {
    throw new FleetUpdateError("MainWP did not issue the required update confirmation token.");
  }
  const confirmed = await callTool(client, "mrn_mainwp__update_stack_plugin_v1", {
    ...writeInput,
    user_confirmed: true,
    confirmation_token: token,
  }, { timeout: 300000 });
  if (confirmed.payload?.success !== true) {
    throw new FleetUpdateError("The selective Stack-plugin update did not return verified success.");
  }
  return confirmed.payload;
}

export function verifyDeploymentResult(deployment, input, currentVersion, target, active) {
  const baselineMatches =
    deployment?.baseline?.release_id === input.baseline.release_id &&
    deployment?.baseline?.lock_sha256 === input.baseline.lock_sha256;
  if (
    deployment?.success !== true ||
    deployment?.operation !== "update" ||
    deployment?.plan_id !== input.plan_id ||
    Number(deployment?.site_id) !== input.site_id ||
    normalizeSiteUrl(deployment?.site_url || "") !== input.site_url ||
    deployment?.plugin_slug !== input.plugin_slug ||
    deployment?.from_version !== currentVersion ||
    deployment?.to_version !== target.version ||
    deployment?.active !== active ||
    deployment?.package_sha256 !== target.package_sha256 ||
    deployment?.tree_sha256 !== target.tree_sha256 ||
    deployment?.file_count !== target.file_count ||
    baselineMatches !== true ||
    deployment?.baseline_component_match !== false ||
    deployment?.matches_component_plan !== true ||
    deployment?.approved_overlay_recorded !== true ||
    !["current_with_approved_overlays", "drifted"].includes(deployment?.fleet_state) ||
    deployment?.receipt_consumed !== true
  ) {
    throw new FleetUpdateError("The child write returned success, but its exact deployment evidence did not match the approved plan.");
  }
  return deployment;
}

export function verifyPostUpdateRuntime(report, component, target, baseline) {
  const runtime = uniqueMatch(
    Array.isArray(report?.components) ? report.components : [],
    (entry) => entry.slug === component,
    `Fresh runtime report must contain exactly one ${component} component.`,
  );
  const approvedOverlay = uniqueMatch(
    Array.isArray(report?.approved_overlays) ? report.approved_overlays : [],
    (entry) => entry.component_slug === component,
    `Fresh runtime report must contain exactly one approved ${component} overlay.`,
  );
  if (
    runtime.loaded !== true ||
    runtime.version !== target.version ||
    runtime.sha256 !== target.tree_sha256 ||
    runtime.file_count !== target.file_count ||
    runtime.matches_release !== false ||
    !Array.isArray(report?.drifted_required) ||
    !report.drifted_required.includes(component) ||
    !Array.isArray(report?.unknown_drifted_required) ||
    report.unknown_drifted_required.includes(component) ||
    !["current_with_approved_overlays", "drifted"].includes(report?.fleet_state) ||
    approvedOverlay?.baseline?.release_id !== baseline.release_id ||
    approvedOverlay?.baseline?.lock_sha256 !== baseline.lock_sha256 ||
    approvedOverlay?.version !== target.version ||
    approvedOverlay?.package_sha256 !== target.package_sha256 ||
    approvedOverlay?.tree_sha256 !== target.tree_sha256 ||
    approvedOverlay?.file_count !== target.file_count
  ) {
    throw new FleetUpdateError("The write completed, but the fresh runtime report does not match the exact target and approved-overlay record.");
  }
  return runtime;
}

function normalizeSmokePath(value) {
  const raw = String(value || "").trim();
  if (!raw || raw.includes("?") || raw.includes("#") || /^https?:\/\//i.test(raw)) {
    throw new FleetUpdateError(`Invalid --smoke-path: ${value}`);
  }
  return raw.startsWith("/") ? raw : `/${raw}`;
}

async function smokeCheck(siteUrl, paths) {
  const requested = ["/", ...paths.map(normalizeSmokePath)];
  const unique = [...new Set(requested)];
  const results = [];
  for (const smokePath of unique) {
    const target = new URL(smokePath, `${siteUrl}/`).toString();
    const response = await fetch(target, {
      redirect: "follow",
      signal: AbortSignal.timeout(30000),
      headers: { "User-Agent": "MRN-Fleet-Update/1.0" },
    });
    results.push({ path: smokePath, status: response.status, final_url: response.url, ok: response.status >= 200 && response.status < 400 });
  }
  const failed = results.filter((entry) => !entry.ok);
  if (failed.length) {
    throw new FleetUpdateError(`The update was applied, but public smoke checks failed: ${failed.map((entry) => `${entry.path} (${entry.status})`).join(", ")}.`);
  }
  return results;
}

function displaySummary(summary, jsonOnly) {
  if (jsonOnly) {
    process.stdout.write(`${JSON.stringify(summary)}\n`);
    return;
  }
  if (summary.status === "no_change") {
    process.stdout.write(
      `NO CHANGE\nSite: ${summary.site_url} (MainWP ${summary.site_id})\nComponent: ${summary.component} ${summary.current_version}\nFleet state: ${summary.fleet_state}\nThe site already matches the registered selective target.\n`,
    );
    return;
  }
  if (summary.status === "approval_required") {
    process.stdout.write(
      `READY FOR APPROVAL\nSite: ${summary.site_url} (MainWP ${summary.site_id})\nComponent: ${summary.component} ${summary.current_version} -> ${summary.target_version}\nBaseline: ${summary.baseline_release_id}\nPrecondition: ${summary.precondition_hash}\nEvidence: ${summary.evidence_dir}\n\nAfter reviewing this exact scope and authorizing the site write, run:\nmrn fleet update --site ${summary.site_url} --component ${summary.component} --execute --approve ${summary.precondition_hash} --confirm-site ${summary.site_url}\n`,
    );
    return;
  }
  process.stdout.write(
    `UPDATE VERIFIED\nSite: ${summary.site_url} (MainWP ${summary.site_id})\nComponent: ${summary.component} ${summary.from_version} -> ${summary.to_version}\nFleet state: ${summary.fleet_state}\nBackup: verified and receipt consumed\nRuntime: exact target version/tree/file count loaded with an approved overlay\nEvidence: ${summary.evidence_dir}\n`,
  );
}

export async function runFleetUpdate(args, dependencies = {}) {
  const requestedUrl = normalizeSiteUrl(args.site);
  if (args.execute && normalizeSiteUrl(args.confirmSite) !== requestedUrl) {
    throw new FleetUpdateError("--confirm-site does not exactly match --site.");
  }
  const catalogPath = path.join(REPOSITORY_ROOT, "stack", "manifests", "component-catalog.json");
  const registryPath = path.join(REPOSITORY_ROOT, "stack", "manifests", "stack-plugin-releases.json");
  const artifactRoots = dependencies.artifactRoots || [
    REPOSITORY_ROOT,
    process.env.MRN_RELEASE_ARTIFACT_ROOT || "",
    canonicalRepositoryRoot(),
  ];
  const client = dependencies.client || (await connectMainwp(args.configPath));
  const ownsClient = !dependencies.client;
  try {
    const status = await assertMainwpControlPlane(client);
    const site = await resolveExactSite(client, requestedUrl);
    const syncStarted = new Date();
    const refreshed = await freshSync(client, site, syncStarted);
    const runtimeReport = await readRuntimeReport(client, refreshed);
    const catalog = dependencies.catalog || readJson(catalogPath);
    const registry = dependencies.registry || readJson(registryPath);
    const context = selectReleaseContext({
      catalog,
      registry,
      runtimeReport,
      component: args.component,
      artifactRoots,
    });
    if (context.noChange) {
      const selectedUnknownDrift = runtimeReport.unknown_drifted_required.includes(args.component);
      const selectedStaleOverlay = runtimeReport.stale_approved_overlays.some(
        (entry) => entry?.component_slug === args.component,
      );
      if (selectedUnknownDrift || selectedStaleOverlay) {
        throw new FleetUpdateError(
          `${args.component} is already at the catalog version, but its Fleet state is unknown or stale; reconcile it before reporting no change.`,
        );
      }
      if (context.runtimeComponent.matches_release === false) {
        verifyPostUpdateRuntime(
          runtimeReport,
          args.component,
          context.target,
          {
            release_id: runtimeReport.release_lock.release_id,
            lock_sha256: runtimeReport.release_lock.sha256,
          },
        );
      }
      return {
        status: "no_change",
        dashboard_host: status.dashboardHost,
        site_id: refreshed.id,
        site_url: refreshed.url,
        component: args.component,
        current_version: context.currentVersion,
        target_version: context.targetVersion,
        fleet_state: runtimeReport.fleet_state,
        approved_overlays: runtimeReport.approved_overlays,
      };
    }

    const planId = planIdFor(args.component, context.targetVersion, refreshed.id);
    const evidenceDir = path.resolve(
      args.outputDir || path.join(REPOSITORY_ROOT, ".tmp", "fleet", `${planId}-${evidenceRunId()}`),
    );
    fs.mkdirSync(evidenceDir, { recursive: true, mode: 0o700 });
    const preliminaryInput = buildPreflightInput({
      planId,
      site: refreshed,
      releaseLock: runtimeReport.release_lock,
      mainFile: context.mainFile,
      target: context.target,
      rollback: context.rollback,
    });
    const preliminaryPreflight = await runControllerPreflight(client, preliminaryInput);
    const syncedAt = new Date().toISOString().replace(/\.\d{3}Z$/, "Z");
    const inventory = buildInventory({
      site: refreshed,
      runtimeReport,
      runtimeComponent: context.runtimeComponent,
      preflight: preliminaryPreflight,
      syncedAt,
      mainFile: context.mainFile,
    });
    const inventoryPath = path.join(evidenceDir, "inventory.json");
    const planPath = path.join(evidenceDir, "plan.json");
    writeJson(inventoryPath, inventory);
    const plan = (dependencies.runPlanBuilder || runPlanBuilder)({
      inventoryPath,
      planPath,
      component: args.component,
      planId,
      targetPath: context.target.path,
      rollbackPath: context.rollback.path,
      asOf: syncedAt,
    });
    const verifiedInput = buildAbilityInputFromPlan(plan);
    const preflight = await runControllerPreflight(client, verifiedInput);
    writeJson(path.join(evidenceDir, "preflight.json"), preflight);
    const summaryBase = {
      dashboard_host: status.dashboardHost,
      site_id: refreshed.id,
      site_url: refreshed.url,
      component: args.component,
      current_version: context.currentVersion,
      target_version: context.targetVersion,
      baseline_release_id: plan.baseline.release_id,
      precondition_hash: preflight.precondition_hash,
      fleet_state_before: runtimeReport.fleet_state,
      evidence_dir: evidenceDir,
    };
    if (!args.execute) {
      const summary = { status: "approval_required", ...summaryBase };
      writeJson(path.join(evidenceDir, "summary.json"), summary);
      return summary;
    }
    if (!crypto.timingSafeEqual(Buffer.from(args.approve), Buffer.from(preflight.precondition_hash))) {
      throw new FleetUpdateError("The approved precondition hash does not match the fresh controller preflight. Review the new plan before any write.");
    }

    const writeWithoutReceipt = buildWriteInput(
      verifiedInput,
      preflight.precondition_hash,
      { site_id: refreshed.id, nonce: "0", receipt: "confirmation-gate-probe" },
    );
    await assertConfirmationGate(client, writeWithoutReceipt);
    const backupReceipt = await createVerifiedBackup(client, refreshed.id, args.backupTimeoutSeconds);
    const writeInput = buildWriteInput(verifiedInput, preflight.precondition_hash, backupReceipt);
    const deployment = await executeConfirmedUpdate(client, writeInput);
    verifyDeploymentResult(
      deployment,
      verifiedInput,
      context.currentVersion,
      context.target,
      preflight.installed.active,
    );
    writeJson(path.join(evidenceDir, "deployment.json"), deployment);

    const postSyncStarted = new Date();
    const postSite = await freshSync(client, refreshed, postSyncStarted);
    const postRuntime = await readRuntimeReport(client, postSite);
    verifyPostUpdateRuntime(postRuntime, args.component, context.target, verifiedInput.baseline);
    writeJson(path.join(evidenceDir, "post-runtime.json"), postRuntime);
    const smoke = await (dependencies.smokeCheck || smokeCheck)(refreshed.url, args.smokePaths);
    writeJson(path.join(evidenceDir, "smoke.json"), smoke);
    const summary = {
      status: "verified",
      ...summaryBase,
      from_version: deployment.from_version,
      to_version: deployment.to_version,
      backup_verified: true,
      receipt_consumed: deployment.receipt_consumed === true,
      approved_overlay_recorded: deployment.approved_overlay_recorded === true,
      fleet_state: postRuntime.fleet_state,
      runtime_verified: true,
      smoke_verified: true,
    };
    writeJson(path.join(evidenceDir, "summary.json"), summary);
    return summary;
  } finally {
    if (ownsClient) {
      await client.close();
    }
  }
}

async function main() {
  try {
    const args = parseArgs(process.argv.slice(2));
    if (args.help) {
      process.stdout.write(usage());
      return;
    }
    const summary = await runFleetUpdate(args);
    displaySummary(summary, args.json);
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    process.stderr.write(`ERROR: ${message}\n`);
    process.exitCode = 2;
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  await main();
}
