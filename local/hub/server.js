#!/usr/bin/env node
"use strict";

const childProcess = require("child_process");
const crypto = require("crypto");
const fs = require("fs");
const fsp = require("fs/promises");
const http = require("http");
const https = require("https");
const os = require("os");
const path = require("path");
const { Readable } = require("stream");
const { URL } = require("url");

const repoRoot = path.resolve(__dirname, "../..");
const publicRoot = path.join(__dirname, "public");
const homeDir = process.env.HOME || os.homedir();
const defaultSitesRoot = path.join(homeDir, "Development", "MRN-sites");
const defaultAppDataRoot = path.join(homeDir, ".mrn-local-hub");
const appDataRoot = path.resolve(process.env.MRN_LOCAL_HUB_DATA_ROOT || defaultAppDataRoot);
const appSettingsFile = path.join(appDataRoot, "settings.json");
const defaultAppSettings = {
	sitesRoot: defaultSitesRoot,
	runtimeMemoryGiB: 3,
	runtimeDiskGiB: 30,
	sitegroundIdentityFile: "",
};
let appSettings = readAppSettingsSync();
let sitesRoot = path.resolve(process.env.MRN_LOCAL_SITES_ROOT || appSettings.sitesRoot || defaultSitesRoot);
const host = process.env.MRN_LOCAL_HUB_HOST || "127.0.0.1";
const port = Number(process.env.MRN_LOCAL_HUB_PORT || "5678");
const commandTimeoutMs = Number(process.env.MRN_LOCAL_HUB_COMMAND_TIMEOUT_MS || "300000");
const maxOutputBytes = Number(process.env.MRN_LOCAL_HUB_MAX_OUTPUT_BYTES || String(1024 * 1024));
const maxZipListOutputBytes = Number(process.env.MRN_LOCAL_HUB_ZIP_LIST_MAX_OUTPUT_BYTES || String(16 * 1024 * 1024));
const diskUsageCacheTtlMs = Number(process.env.MRN_LOCAL_HUB_DISK_USAGE_TTL_MS || "300000");
const gitStatusCacheTtlMs = Number(process.env.MRN_LOCAL_HUB_GIT_STATUS_TTL_MS || "30000");
const memoryCacheTtlMs = Number(process.env.MRN_LOCAL_HUB_MEMORY_TTL_MS || "10000");
const runtimeInstanceName = process.env.MRN_LOCAL_RUNTIME_INSTANCE || "mrn-openlitespeed";
const runtimeWorkRoot = path.join(repoRoot, ".tmp", "local-hub", "runtime");
const runtimeBootstrapScript = path.join(runtimeWorkRoot, "bootstrap-mrn-openlitespeed.sh");
const updraftStagingRoot = path.join(runtimeWorkRoot, "updraft-staging");
const maxBackupUploadBytes = Number(process.env.MRN_LOCAL_HUB_MAX_BACKUP_UPLOAD_BYTES || String(4 * 1024 * 1024 * 1024));
const toolPathEntries = [
	"/opt/homebrew/opt/mysql-client/bin",
	"/opt/homebrew/opt/php/bin",
	"/opt/homebrew/bin",
	"/usr/local/bin",
	"/usr/bin",
	"/bin",
	"/usr/sbin",
	"/sbin",
];
process.env.PATH = [...toolPathEntries, process.env.PATH || ""]
	.filter(Boolean)
	.join(":");
const friendlyUrlEnabled = process.env.MRN_LOCAL_FRIENDLY_URLS !== "0";
const friendlyHttpPort = Number(process.env.MRN_LOCAL_FRIENDLY_HTTP_PORT || "80");
const friendlyHttpsPort = Number(process.env.MRN_LOCAL_FRIENDLY_HTTPS_PORT || "443");
const friendlyProxyTargetHost = process.env.MRN_LOCAL_FRIENDLY_TARGET_HOST || "127.0.0.1";
const friendlyProxyTargetPort = Number(process.env.MRN_LOCAL_FRIENDLY_TARGET_PORT || "8088");
const friendlyHubHostname = process.env.MRN_LOCAL_HUB_FRIENDLY_HOSTNAME || "thehub.localhost";
const friendlyHubTargetHost = process.env.MRN_LOCAL_HUB_FRIENDLY_TARGET_HOST || host;
const friendlyHubTargetPort = Number(process.env.MRN_LOCAL_HUB_FRIENDLY_TARGET_PORT || String(port));
const friendlyCertRoot = path.join(runtimeWorkRoot, "certs");
const friendlyCertPath = path.join(friendlyCertRoot, "mrn-localhost.pem");
const friendlyKeyPath = path.join(friendlyCertRoot, "mrn-localhost-key.pem");
const friendlyProxyHelperPath = path.join(__dirname, "friendly-proxy.js");
const friendlyProxyLabel = "io.mrn.localhub.friendly-proxy";
const friendlyProxyPlistName = `${friendlyProxyLabel}.plist`;
const friendlyProxySystemPlistPath = path.join("/Library/LaunchDaemons", friendlyProxyPlistName);
const friendlyProxyGeneratedPlistPath = path.join(runtimeWorkRoot, friendlyProxyPlistName);
const friendlyProxyInstallScriptPath = path.join(runtimeWorkRoot, "install-friendly-proxy.sh");
const friendlyProxyStdoutPath = path.join(runtimeWorkRoot, "friendly-proxy.out.log");
const friendlyProxyStderrPath = path.join(runtimeWorkRoot, "friendly-proxy.err.log");
const firefoxProfilesRoot = path.join(os.homedir(), "Library", "Application Support", "Firefox", "Profiles");
const firefoxEnterpriseRootsPref = "security.enterprise_roots.enabled";
const macosLoginKeychainPath = path.join(os.homedir(), "Library", "Keychains", "login.keychain-db");
const activeJobs = new Map();
const diskUsageCache = new Map();
const gitStatusCache = new Map();
const friendlyProxyServers = [];
const friendlyProxyState = {
	enabled: friendlyUrlEnabled,
	http: { desiredPort: friendlyHttpPort, status: "pending", listeners: [], errors: [] },
	https: { desiredPort: friendlyHttpsPort, status: "pending", listeners: [], errors: [] },
	cert: { certPath: friendlyCertPath, keyPath: friendlyKeyPath, status: "pending", message: "" },
	startedAt: "",
};
let memoryCache = null;
let nextJobId = 1;
let cpuSample = null;
let runtimeWpCliReady = false;

const defaultPhpVersion = "8.4";
const phpRuntimeVersions = ["7.4", "8.1", "8.2", "8.3", "8.4"];
const legacyPhpVersions = new Set(["7.4"]);
const phpRuntimeModuleNames = ["common", "mysql", "curl", "imagick", "intl", "opcache", "redis"];

const manifestFileName = ".mrn-site.json";
let providerRegistryFile = path.join(sitesRoot, ".mrn-provider-sites.json");
let credentialRegistryFile = path.join(sitesRoot, ".mrn-credentials.json");
const awsBackupIndexFile = path.join(appDataRoot, "aws-backup-index.json");
const allowedManifestFields = new Set([
	"slug",
	"title",
	"provider",
	"runtime",
	"runtimeStatus",
	"localRoot",
	"publicPath",
	"localUrl",
	"liveUrl",
	"remoteSsh",
	"remotePort",
	"remotePath",
	"phpVersion",
	"activePhpVersion",
	"activePhpHandler",
	"phpStatus",
	"phpCheckedAt",
	"dbName",
	"dbUser",
	"dbPassword",
	"dbHost",
	"dbPort",
	"webserver",
	"cache",
	"mail",
	"qaProjectRoot",
	"notes",
]);
const providerPresets = {
	generic: {
		label: "Generic SSH",
		remotePathPlaceholder: "/home/user/public_html",
		hint: "Use an SSH alias from ~/.ssh/config, or enter user@host when you know the connection details.",
	},
	mrndev: {
		label: "MRN Dev",
		remotePathPlaceholder: "/home/site-user/htdocs/site.mrndev.io",
		hint: "Use a *.mrndev.io live URL or slug. Resolve MRN Dev discovers the site owner and WordPress root through the mrndev SSH alias.",
	},
	runcloud: {
		label: "RunCloud",
		remotePathPlaceholder: "/home/runcloud/webapps/app/public",
		hint: "RunCloud apps usually live below /home/runcloud/webapps/<app>/public. SSH aliases work well here.",
	},
	siteground: {
		label: "SiteGround",
		remotePathPlaceholder: "public_html or /home/customer/www/domain.com/public_html",
		hint: "SiteGround SSH uses Site Tools credentials; the site root is commonly public_html.",
	},
	wpengine: {
		label: "WP Engine",
		remotePathPlaceholder: "sites/environment",
		hint: "WP Engine SSH Gateway uses an environment login and sites/<environment> as the WordPress root. An SSH alias keeps the key choice tidy.",
	},
	backup: {
		label: "Backup Restore",
		remotePathPlaceholder: "",
		hint: "Restore from local UpdraftPlus backup files or an AWS S3 backup set.",
	},
};

function expandHomePath(input) {
	const raw = String(input || "").trim();
	if (raw === "~") {
		return homeDir;
	}
	if (raw.startsWith("~/")) {
		return path.join(homeDir, raw.slice(2));
	}
	return raw;
}

function sanitizeStorageRoot(input, options = {}) {
	const raw = String(input || "").trim();
	if (!raw) {
		if (options.fallback) return path.resolve(options.fallback);
		throw httpError(400, "Site storage folder is required.");
	}
	if (/[\0\r\n]/.test(raw) || raw.length > 4096) {
		throw httpError(400, "Site storage folder contains unsupported characters.");
	}
	return path.resolve(expandHomePath(raw));
}

function sanitizeRuntimeGiB(input, label, options = {}) {
	const fallback = Number(options.fallback || 1);
	const value = Number(input || fallback);
	const min = Number(options.min || 1);
	const max = Number(options.max || 1024);
	if (!Number.isFinite(value) || value < min || value > max) {
		throw httpError(400, `${label} must be between ${min} and ${max} GiB.`);
	}
	return Math.round(value * 10) / 10;
}

function sanitizeOptionalIdentityFile(input, options = {}) {
	const raw = String(input || "").trim();
	if (!raw) {
		return options.fallback ? path.resolve(expandHomePath(options.fallback)) : "";
	}
	if (/[\0\r\n]/.test(raw) || raw.length > 4096) {
		throw httpError(400, "Identity file path contains unsupported characters.");
	}
	return path.resolve(expandHomePath(raw));
}

function sanitizeAppSettings(input = {}) {
	return {
		sitesRoot: sanitizeStorageRoot(input.sitesRoot || defaultAppSettings.sitesRoot, { fallback: defaultAppSettings.sitesRoot }),
		runtimeMemoryGiB: sanitizeRuntimeGiB(input.runtimeMemoryGiB, "Memory allowance", {
			fallback: defaultAppSettings.runtimeMemoryGiB,
			min: 1,
			max: 64,
		}),
		runtimeDiskGiB: sanitizeRuntimeGiB(input.runtimeDiskGiB, "Disk allowance", {
			fallback: defaultAppSettings.runtimeDiskGiB,
			min: 10,
			max: 1024,
		}),
		sitegroundIdentityFile: sanitizeOptionalIdentityFile(input.sitegroundIdentityFile || defaultAppSettings.sitegroundIdentityFile, {
			fallback: defaultAppSettings.sitegroundIdentityFile,
		}),
	};
}

function readAppSettingsSync() {
	try {
		const raw = fs.readFileSync(appSettingsFile, "utf8");
		return sanitizeAppSettings(JSON.parse(raw));
	} catch {
		return sanitizeAppSettings(defaultAppSettings);
	}
}

function applyAppSettings(nextSettings) {
	appSettings = sanitizeAppSettings(nextSettings);
	sitesRoot = path.resolve(process.env.MRN_LOCAL_SITES_ROOT || appSettings.sitesRoot || defaultSitesRoot);
	providerRegistryFile = path.join(sitesRoot, ".mrn-provider-sites.json");
	credentialRegistryFile = path.join(sitesRoot, ".mrn-credentials.json");
	diskUsageCache.clear();
	gitStatusCache.clear();
}

function appSettingsReport(extra = {}) {
	const envOverrides = {
		sitesRoot: Boolean(process.env.MRN_LOCAL_SITES_ROOT),
		dataRoot: Boolean(process.env.MRN_LOCAL_HUB_DATA_ROOT),
	};
	return {
		settings: appSettings,
		defaults: defaultAppSettings,
		active: {
			sitesRoot,
			providerRegistryFile,
			credentialRegistryFile,
			runtimeMemory: `${appSettings.runtimeMemoryGiB}GiB`,
			runtimeDisk: `${appSettings.runtimeDiskGiB}GiB`,
			sitegroundIdentityFile: appSettings.sitegroundIdentityFile || "",
		},
		storage: {
			appDataRoot,
			settingsFile: appSettingsFile,
		},
		envOverrides,
		...extra,
	};
}

async function saveAppSettings(input = {}) {
	const previous = { ...appSettings, activeSitesRoot: sitesRoot };
	const next = sanitizeAppSettings(input);
	await fsp.mkdir(appDataRoot, { recursive: true });
	await fsp.writeFile(appSettingsFile, `${JSON.stringify({ ...next, updatedAt: new Date().toISOString() }, null, 2)}\n`, "utf8");
	applyAppSettings(next);
	await fsp.mkdir(sitesRoot, { recursive: true });
	const sitesRootChanged = previous.activeSitesRoot !== sitesRoot;
	return appSettingsReport({
		result: {
			code: 0,
			command: "app-settings-save",
			args: [sitesRoot, `${appSettings.runtimeMemoryGiB}GiB`, `${appSettings.runtimeDiskGiB}GiB`, appSettings.sitegroundIdentityFile || ""],
			stdout: sitesRootChanged
				? "App settings saved. Site storage changed; existing sites are not moved automatically."
				: "App settings saved.",
			stderr: "",
			durationMs: 0,
			sitesRootChanged,
			requiresRuntimeBootstrap: previous.runtimeMemoryGiB !== appSettings.runtimeMemoryGiB
				|| previous.runtimeDiskGiB !== appSettings.runtimeDiskGiB,
		},
	});
}

function jsonResponse(res, statusCode, payload) {
	const body = JSON.stringify(payload, null, 2);
	res.writeHead(statusCode, {
		"content-type": "application/json; charset=utf-8",
		"cache-control": "no-store",
	});
	res.end(body);
}

function textResponse(res, statusCode, body, contentType = "text/plain; charset=utf-8") {
	res.writeHead(statusCode, {
		"content-type": contentType,
		"cache-control": "no-store",
	});
	res.end(body);
}

function isAllowedLocalOrigin(origin) {
	if (!origin) return false;
	try {
		const parsed = new URL(origin);
		const hostname = parsed.hostname.toLowerCase();
		return ["http:", "https:"].includes(parsed.protocol)
			&& (
				hostname === "localhost"
				|| hostname === "127.0.0.1"
				|| hostname === "::1"
				|| hostname.endsWith(".localhost")
			);
	} catch {
		return false;
	}
}

function applyCorsHeaders(req, res) {
	const origin = req.headers.origin || "";
	if (!isAllowedLocalOrigin(origin)) return;
	res.setHeader("access-control-allow-origin", origin);
	res.setHeader("access-control-allow-methods", "GET,POST,OPTIONS");
	res.setHeader("access-control-allow-headers", "content-type");
	res.setHeader("access-control-max-age", "600");
	res.setHeader("vary", "Origin");
	if (req.headers["access-control-request-private-network"]) {
		res.setHeader("access-control-allow-private-network", "true");
	}
}

function httpError(statusCode, message, details) {
	const error = new Error(message);
	error.statusCode = statusCode;
	error.details = details;
	return error;
}

function normalizeSlug(input) {
	const slug = String(input || "")
		.trim()
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, "-")
		.replace(/^-+|-+$/g, "");

	if (!slug || slug.length > 80) {
		throw httpError(400, "Site slug must contain letters or numbers and be 80 characters or fewer.");
	}

	return slug;
}

function normalizeProvider(input) {
	const provider = String(input || "generic").trim().toLowerCase().replace(/[^a-z0-9-]+/g, "");
	return providerPresets[provider] ? provider : "generic";
}

function assertInsideSitesRoot(localRoot) {
	const resolved = path.resolve(localRoot);
	const relative = path.relative(sitesRoot, resolved);
	if (relative.startsWith("..") || path.isAbsolute(relative)) {
		throw httpError(400, "Site path must stay inside MRN_LOCAL_SITES_ROOT.");
	}
	return resolved;
}

function manifestPathFor(siteRoot) {
	return path.join(siteRoot, manifestFileName);
}

function shellQuote(value) {
	return `'${String(value).replace(/'/g, `'\\''`)}'`;
}

function timestampSlug() {
	return new Date().toISOString().replace(/[-:]/g, "").replace(/\.\d{3}Z$/, "Z");
}

function delay(ms) {
	return new Promise((resolve) => setTimeout(resolve, ms));
}

function displayPath(filePath) {
	if (!filePath) {
		return "";
	}
	if (homeDir && filePath.startsWith(`${homeDir}/`)) {
		return `~/${path.relative(homeDir, filePath)}`;
	}
	return filePath;
}

function defaultLocalUrl(slug) {
	return `https://${slug}.localhost`;
}

function normalizeLocalUrlForSlug(value, slug) {
	const normalizedSlug = normalizeSlug(slug);
	const raw = String(value || "").trim().replace(/\/+$/, "");
	const legacyDefaults = new Set([
		`http://${normalizedSlug}.test`,
		`http://${normalizedSlug}.localhost:8088`,
		`https://${normalizedSlug}.localhost:8443`,
	]);
	if (!raw || legacyDefaults.has(raw)) {
		return defaultLocalUrl(normalizedSlug);
	}
	return sanitizeOptionalUrl(raw);
}

function defaultDbName(slug) {
	return `mrn_${slug.replace(/-/g, "_")}`;
}

function defaultDbUser(slug) {
	return `mrn_${slug.replace(/-/g, "_")}`;
}

function generateDbPassword() {
	return crypto.randomBytes(18).toString("base64url");
}

function sanitizeDbPort(value) {
	const raw = String(value || "").trim();
	if (!raw) {
		return 3307;
	}
	if (!/^[0-9]+$/.test(raw)) {
		throw httpError(400, "DB port must be a number.");
	}
	const portNumber = Number(raw);
	if (!Number.isInteger(portNumber) || portNumber < 1 || portNumber > 65535) {
		throw httpError(400, "DB port must be between 1 and 65535.");
	}
	return portNumber;
}

function assertSafeSqlName(value, label) {
	const text = String(value || "").trim();
	if (!text) {
		throw httpError(400, `${label} is required.`);
	}
	if (/[\0\r\n]/.test(text)) {
		throw httpError(400, `${label} must not contain control characters.`);
	}
	if (Buffer.byteLength(text, "utf8") > 64) {
		throw httpError(400, `${label} must be 64 bytes or fewer.`);
	}
	return text;
}

function mysqlIdentifier(value, label) {
	return `\`${assertSafeSqlName(value, label).replace(/`/g, "``")}\``;
}

function mysqlString(value) {
	const text = String(value || "");
	if (text.includes("\0")) {
		throw httpError(400, "SQL strings must not contain null bytes.");
	}
	return `'${text.replace(/\\/g, "\\\\").replace(/'/g, "''").replace(/\r/g, "\\r").replace(/\n/g, "\\n").replace(/\x1a/g, "\\Z")}'`;
}

function phpSingleQuotedString(value) {
	return String(value || "")
		.replace(/\\/g, "\\\\")
		.replace(/'/g, "\\'");
}

function normalizePhpVersion(value) {
	const version = String(value || defaultPhpVersion).trim();
	if (phpRuntimeVersions.includes(version)) {
		return version;
	}
	return defaultPhpVersion;
}

function phpVersionSuffix(version) {
	return normalizePhpVersion(version).replace(".", "");
}

function phpHandlerName(version) {
	return `lsphp${phpVersionSuffix(version)}`;
}

function phpBinaryPath(version) {
	return `/usr/local/lsws/${phpHandlerName(version)}/bin/php`;
}

function phpPackageNames(version) {
	const handler = phpHandlerName(version);
	return [
		handler,
		...phpRuntimeModuleNames.map((moduleName) => `${handler}-${moduleName}`),
	];
}

function phpVersionLabel(version) {
	const normalized = normalizePhpVersion(version);
	return legacyPhpVersions.has(normalized) ? `Legacy PHP ${normalized}` : `PHP ${normalized}`;
}

function sanitizeRemoteSsh(value) {
	const remoteSsh = String(value || "").trim();
	if (!remoteSsh) {
		throw httpError(400, "Remote SSH is required for this action.");
	}
	if (!/^[A-Za-z0-9_.@:%+-]+$/.test(remoteSsh)) {
		throw httpError(400, "Remote SSH may only contain an SSH alias or user@host characters, with no spaces.");
	}
	return remoteSsh;
}

function sanitizeSshPort(value) {
	const raw = String(value || "").trim();
	if (!raw) {
		return "";
	}
	if (!/^[0-9]+$/.test(raw)) {
		throw httpError(400, "SSH port must be a number.");
	}
	const portNumber = Number(raw);
	if (!Number.isInteger(portNumber) || portNumber < 1 || portNumber > 65535) {
		throw httpError(400, "SSH port must be between 1 and 65535.");
	}
	return String(portNumber);
}

function sanitizeRemotePath(value) {
	const remotePath = String(value || "").trim();
	if (!remotePath) {
		throw httpError(400, "Remote path is required for this action.");
	}
	if (/[\r\n\0]/.test(remotePath) || remotePath.startsWith("-")) {
		throw httpError(400, "Remote path must not contain control characters or shell flags.");
	}
	if (!/^\/?[A-Za-z0-9._/-]+$/.test(remotePath)) {
		throw httpError(400, "Remote path may only contain safe path characters.");
	}
	const parts = remotePath.split("/").filter(Boolean);
	if (parts.includes("..")) {
		throw httpError(400, "Remote path must not contain parent directory segments.");
	}
	return remotePath.replace(/\/+$/, "");
}

function sanitizeOptionalRemotePath(value) {
	const remotePath = String(value || "").trim();
	return remotePath ? sanitizeRemotePath(remotePath) : "";
}

function sanitizeOptionalUrl(value) {
	const raw = String(value || "").trim();
	if (!raw) {
		return "";
	}
	if (/[\0\r\n]/.test(raw)) {
		throw httpError(400, "URL values must not contain control characters.");
	}
	const candidate = /^https?:\/\//i.test(raw) ? raw : `https://${raw}`;
	let parsed;
	try {
		parsed = new URL(candidate);
	} catch {
		throw httpError(400, "URL values must be valid HTTP or HTTPS URLs.");
	}
	if (!["http:", "https:"].includes(parsed.protocol) || !parsed.hostname) {
		throw httpError(400, "URL values must be valid HTTP or HTTPS URLs.");
	}
	return `${parsed.protocol}//${parsed.hostname}${parsed.pathname === "/" ? "" : parsed.pathname}`.replace(/\/+$/, "");
}

function sanitizeOptionalProviderText(value, label, maxLength = 240) {
	const text = String(value || "").trim();
	if (!text) {
		return "";
	}
	if (text.includes("\0")) {
		throw httpError(400, `${label} must not contain null bytes.`);
	}
	if (text.length > maxLength) {
		throw httpError(400, `${label} must be ${maxLength} characters or fewer.`);
	}
	return text;
}

function sanitizeOptionalRemoteSsh(value) {
	const remoteSsh = String(value || "").trim();
	return remoteSsh ? sanitizeRemoteSsh(remoteSsh) : "";
}

function sanitizeRelativePath(value) {
	const relativePath = String(value || "").trim().replace(/^\/+/, "");
	if (!relativePath || relativePath.includes("\0")) {
		throw httpError(400, "A relative path is required.");
	}
	const normalized = path.posix.normalize(relativePath);
	if (normalized === "." || normalized.startsWith("../") || normalized === "..") {
		throw httpError(400, "Relative path must stay inside the WordPress public path.");
	}
	return normalized;
}

function sanitizeManifest(input, existing = null) {
	const base = existing ? { ...existing } : {};
	for (const [key, value] of Object.entries(input || {})) {
		if (allowedManifestFields.has(key)) {
			base[key] = typeof value === "string" ? value.trim() : value;
		}
	}

	const slug = normalizeSlug(base.slug);
	const localRoot = assertInsideSitesRoot(base.localRoot || path.join(sitesRoot, slug));
	const publicPath = path.resolve(base.publicPath || path.join(localRoot, "public"));
	const qaProjectRoot = path.resolve(base.qaProjectRoot || localRoot);

	if (!path.relative(localRoot, publicPath).startsWith("..") && !path.isAbsolute(path.relative(localRoot, publicPath))) {
		// OK: publicPath lives inside localRoot.
	} else {
		throw httpError(400, "Public path must stay inside the site root.");
	}

	if (!path.relative(localRoot, qaProjectRoot).startsWith("..") && !path.isAbsolute(path.relative(localRoot, qaProjectRoot))) {
		// OK: qaProjectRoot lives inside localRoot.
	} else {
		throw httpError(400, "QA project root must stay inside the site root.");
	}

	return {
		version: 1,
		slug,
		title: base.title || slug,
		provider: normalizeProvider(base.provider),
		runtime: base.runtime || "local-vm-openlitespeed",
		runtimeStatus: base.runtimeStatus || "planned",
		localRoot,
		publicPath,
		localUrl: normalizeLocalUrlForSlug(base.localUrl, slug),
		liveUrl: base.liveUrl || "",
		remoteSsh: base.remoteSsh || "",
		remotePort: sanitizeSshPort(base.remotePort || ""),
		remotePath: sanitizeOptionalRemotePath(base.remotePath || ""),
		phpVersion: normalizePhpVersion(base.phpVersion),
		activePhpVersion: base.activePhpVersion || "",
		activePhpHandler: base.activePhpHandler || "",
		phpStatus: base.phpStatus || "",
		phpCheckedAt: base.phpCheckedAt || "",
		dbName: base.dbName || defaultDbName(slug),
		dbUser: base.dbUser || defaultDbUser(slug),
		dbPassword: base.dbPassword || generateDbPassword(),
		dbHost: base.dbHost || "127.0.0.1",
		dbPort: sanitizeDbPort(base.dbPort || 3307),
		webserver: base.webserver || "openlitespeed",
		cache: base.cache || "redis",
		mail: base.mail || "mailpit",
		qaProjectRoot,
		notes: base.notes || "",
		createdAt: base.createdAt || new Date().toISOString(),
		updatedAt: new Date().toISOString(),
	};
}

async function ensureSiteDirectories(site) {
	await fsp.mkdir(site.localRoot, { recursive: true });
	await fsp.mkdir(site.publicPath, { recursive: true });
	await fsp.mkdir(path.join(site.localRoot, "dumps"), { recursive: true });
	await fsp.mkdir(path.join(site.localRoot, "backups"), { recursive: true });
	await fsp.mkdir(path.join(site.localRoot, "logs"), { recursive: true });
}

async function readManifest(siteRoot) {
	const file = manifestPathFor(siteRoot);
	const raw = await fsp.readFile(file, "utf8");
	return siteWithRuntimeDefaults(JSON.parse(raw));
}

async function writeManifest(site) {
	await ensureSiteDirectories(site);
	await fsp.writeFile(manifestPathFor(site.localRoot), `${JSON.stringify(site, null, 2)}\n`, "utf8");
	return site;
}

async function readProviderRegistry() {
	try {
		const raw = await fsp.readFile(providerRegistryFile, "utf8");
		const parsed = JSON.parse(raw);
		return {
			version: 1,
			siteground: Array.isArray(parsed.siteground) ? parsed.siteground : [],
		};
	} catch (error) {
		if (error.code === "ENOENT") {
			return { version: 1, siteground: [] };
		}
		throw error;
	}
}

async function writeProviderRegistry(registry) {
	await fsp.mkdir(sitesRoot, { recursive: true });
	const next = {
		version: 1,
		siteground: Array.isArray(registry.siteground) ? registry.siteground : [],
		updatedAt: new Date().toISOString(),
	};
	await fsp.writeFile(providerRegistryFile, `${JSON.stringify(next, null, 2)}\n`, "utf8");
	return next;
}

function sanitizeCredentialId(value) {
	const id = String(value || "").trim().toLowerCase();
	if (!/^[a-z0-9][a-z0-9-]{2,80}$/.test(id)) {
		throw httpError(400, "Credential ID is invalid.");
	}
	return id;
}

function makeCredentialId(provider, label) {
	const base = `${provider}-${String(label || "default")
		.trim()
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, "-")
		.replace(/^-+|-+$/g, "")
		.slice(0, 48) || "default"}`;
	return `${base}-${crypto.randomBytes(3).toString("hex")}`;
}

function sanitizeCredentialLabel(value) {
	const label = sanitizeOptionalProviderText(value || "", "Credential label", 120);
	if (!label) {
		throw httpError(400, "Credential label is required.");
	}
	return label;
}

function sanitizeAwsSecret(value, label, options = {}) {
	const secret = String(value || "").trim();
	if (!secret && options.required) {
		throw httpError(400, `${label} is required.`);
	}
	if (secret && (/[\0\r\n]/.test(secret) || secret.length > 4096)) {
		throw httpError(400, `${label} contains unsupported characters.`);
	}
	return secret;
}

async function readCredentialRegistry() {
	try {
		const raw = await fsp.readFile(credentialRegistryFile, "utf8");
		const parsed = JSON.parse(raw);
		return {
			version: 1,
			credentials: Array.isArray(parsed.credentials) ? parsed.credentials : [],
		};
	} catch (error) {
		if (error.code === "ENOENT") {
			return { version: 1, credentials: [] };
		}
		throw error;
	}
}

async function writeCredentialRegistry(registry) {
	await fsp.mkdir(sitesRoot, { recursive: true });
	const credentials = Array.isArray(registry.credentials)
		? registry.credentials.map((credential) => ({
			id: sanitizeCredentialId(credential.id),
			provider: String(credential.provider || "").trim().toLowerCase(),
			label: sanitizeCredentialLabel(credential.label),
			region: sanitizeAwsRegion(credential.region || ""),
			s3Bucket: credential.s3Bucket ? sanitizeS3Bucket(credential.s3Bucket) : "",
			s3Prefix: sanitizeS3Prefix(credential.s3Prefix || ""),
			storage: "macos-keychain",
			hasAccessKeyId: Boolean(credential.hasAccessKeyId),
			hasSecretAccessKey: Boolean(credential.hasSecretAccessKey),
			hasSessionToken: Boolean(credential.hasSessionToken),
			createdAt: credential.createdAt || new Date().toISOString(),
			updatedAt: credential.updatedAt || new Date().toISOString(),
		}))
		: [];
	const next = {
		version: 1,
		credentials,
		updatedAt: new Date().toISOString(),
	};
	await fsp.writeFile(credentialRegistryFile, `${JSON.stringify(next, null, 2)}\n`, "utf8");
	return next;
}

function credentialPublicSummary(credential) {
	return {
		id: credential.id,
		provider: credential.provider,
		label: credential.label,
		region: credential.region || "",
		s3Bucket: credential.s3Bucket || "",
		s3Prefix: credential.s3Prefix || "",
		storage: credential.storage || "macos-keychain",
		hasAccessKeyId: Boolean(credential.hasAccessKeyId),
		hasSecretAccessKey: Boolean(credential.hasSecretAccessKey),
		hasSessionToken: Boolean(credential.hasSessionToken),
		createdAt: credential.createdAt || "",
		updatedAt: credential.updatedAt || "",
	};
}

async function credentialSummary() {
	const registry = await readCredentialRegistry();
	return {
		registryPath: credentialRegistryFile,
		storage: process.platform === "darwin" ? "macos-keychain" : "unsupported",
		credentials: registry.credentials.map(credentialPublicSummary),
	};
}

async function readAwsBackupIndexRegistry() {
	try {
		const raw = await fsp.readFile(awsBackupIndexFile, "utf8");
		const parsed = JSON.parse(raw);
		return {
			version: 1,
			indexes: parsed.indexes && typeof parsed.indexes === "object" ? parsed.indexes : {},
			updatedAt: parsed.updatedAt || "",
		};
	} catch (error) {
		if (error.code === "ENOENT") {
			return { version: 1, indexes: {}, updatedAt: "" };
		}
		throw error;
	}
}

async function writeAwsBackupIndexRegistry(registry) {
	const next = {
		version: 1,
		indexes: registry.indexes && typeof registry.indexes === "object" ? registry.indexes : {},
		updatedAt: new Date().toISOString(),
	};
	await fsp.mkdir(appDataRoot, { recursive: true });
	await fsp.writeFile(awsBackupIndexFile, `${JSON.stringify(next, null, 2)}\n`, "utf8");
	return next;
}

function awsBackupIndexSource(body = {}, credential = null) {
	const credentialId = String(body.credentialId || body.awsCredentialId || "").trim();
	const profile = sanitizeAwsProfile(body.profile || "");
	const bucket = sanitizeS3Bucket(body.bucket || "");
	const prefix = sanitizeS3Prefix(body.prefix || "");
	const region = sanitizeAwsRegion(body.region || credential?.region || "");
	const identity = credentialId ? `credential:${sanitizeCredentialId(credentialId)}` : profile ? `profile:${profile}` : "aws-cli:default";
	return {
		key: [identity, region || "auto-region", bucket, prefix || "/"].join("|"),
		identity,
		credentialId: credentialId ? sanitizeCredentialId(credentialId) : "",
		profile,
		credentialLabel: credential?.label || "",
		bucket,
		prefix,
		region,
	};
}

function folderPrefixesFromFiles(files = [], rootPrefix = "") {
	const prefixes = new Set();
	const root = String(rootPrefix || "");
	for (const file of files) {
		const key = String(file.key || "");
		if (!key.startsWith(root)) continue;
		const relative = key.slice(root.length);
		const parts = relative.split("/");
		let current = root;
		for (let index = 0; index < parts.length - 1; index += 1) {
			current += `${parts[index]}/`;
			prefixes.add(current);
		}
	}
	return [...prefixes].sort((a, b) => a.localeCompare(b));
}

function compactAwsBackupIndex(index) {
	if (!index) return null;
	const groups = groupUpdraftS3Files(index.files || []);
	return {
		source: index.source,
		scannedAt: index.scannedAt,
		fileCount: index.files?.length || 0,
		folderCount: index.prefixes?.length || 0,
		groupCount: groups.length,
		truncated: Boolean(index.truncated),
	};
}

async function storeAwsBackupIndex(body, result, credential = null) {
	if (result.code !== 0) return null;
	const source = awsBackupIndexSource(body, credential);
	const files = Array.isArray(result.files) ? result.files : [];
	const prefixes = [...new Set([
		...(Array.isArray(result.prefixes) ? result.prefixes : []),
		...folderPrefixesFromFiles(files, source.prefix),
	])].sort((a, b) => a.localeCompare(b));
	const index = {
		source,
		scannedAt: new Date().toISOString(),
		region: result.region || source.region || "",
		files,
		prefixes,
		groups: Array.isArray(result.groups) ? result.groups : [],
		truncated: Boolean(result.truncated),
	};
	const registry = await readAwsBackupIndexRegistry();
	registry.indexes[source.key] = index;
	await writeAwsBackupIndexRegistry(registry);
	return index;
}

async function getStoredAwsBackupIndex(body = {}) {
	let credential = null;
	const credentialId = String(body.credentialId || body.awsCredentialId || "").trim();
	if (credentialId) {
		const registry = await readCredentialRegistry();
		credential = registry.credentials.find((item) => item.id === sanitizeCredentialId(credentialId) && item.provider === "aws") || null;
	}
	const source = awsBackupIndexSource(body, credential);
	const registry = await readAwsBackupIndexRegistry();
	return registry.indexes[source.key] || null;
}

function credentialKeychainAccount(credentialId, field) {
	return `aws:${sanitizeCredentialId(credentialId)}:${field}`;
}

async function assertKeychainAvailable() {
	if (process.platform !== "darwin") {
		throw httpError(400, "Secure credential storage currently requires macOS Keychain. The Tauri app can swap this for native OS key storage per platform.");
	}
	const security = await commandExists("security");
	if (!security.ok) {
		throw httpError(400, "macOS security command was not found, so Keychain storage is unavailable.");
	}
}

async function setKeychainSecret(credentialId, field, value) {
	await assertKeychainAvailable();
	const result = await runProcess("security", [
		"add-generic-password",
		"-a",
		credentialKeychainAccount(credentialId, field),
		"-s",
		"io.mrn.localhub.credentials",
		"-w",
		value,
		"-U",
	], { timeoutMs: 10000, trackJob: false });
	if (result.code !== 0) {
		throw httpError(500, `Could not save ${field} in macOS Keychain.`, result.stderr || result.stdout);
	}
}

async function getKeychainSecret(credentialId, field) {
	await assertKeychainAvailable();
	const result = await runProcess("security", [
		"find-generic-password",
		"-a",
		credentialKeychainAccount(credentialId, field),
		"-s",
		"io.mrn.localhub.credentials",
		"-w",
	], { timeoutMs: 10000, trackJob: false });
	if (result.code !== 0) {
		throw httpError(400, `Could not read ${field} from macOS Keychain. Re-save this credential.`);
	}
	return result.stdout.replace(/\r?\n$/, "");
}

async function deleteKeychainSecret(credentialId, field) {
	if (process.platform !== "darwin") return;
	const security = await commandExists("security");
	if (!security.ok) return;
	await runProcess("security", [
		"delete-generic-password",
		"-a",
		credentialKeychainAccount(credentialId, field),
		"-s",
		"io.mrn.localhub.credentials",
	], { timeoutMs: 10000, trackJob: false });
}

async function saveAwsCredential(input = {}) {
	const now = new Date().toISOString();
	const label = sanitizeCredentialLabel(input.label || input.name || "");
	const id = input.id ? sanitizeCredentialId(input.id) : makeCredentialId("aws", label);
	const region = sanitizeAwsRegion(input.region || "");
	const s3BucketRaw = String(input.s3Bucket || input.bucket || "").trim();
	const s3Bucket = s3BucketRaw ? sanitizeS3Bucket(s3BucketRaw) : "";
	const s3Prefix = sanitizeS3Prefix(input.s3Prefix || input.prefix || "");
	if (!s3Bucket && s3Prefix) {
		throw httpError(400, "Default S3 bucket is required when a default S3 prefix is set.");
	}
	const registry = await readCredentialRegistry();
	const existing = registry.credentials.find((credential) => credential.id === id);
	const accessKeyId = sanitizeAwsSecret(input.accessKeyId || input.awsAccessKeyId || "", "AWS access key ID", { required: !existing });
	const secretAccessKey = sanitizeAwsSecret(input.secretAccessKey || input.awsSecretAccessKey || "", "AWS secret access key", { required: !existing });
	const sessionToken = sanitizeAwsSecret(input.sessionToken || input.awsSessionToken || "", "AWS session token");

	if (accessKeyId) {
		await setKeychainSecret(id, "accessKeyId", accessKeyId);
	}
	if (secretAccessKey) {
		await setKeychainSecret(id, "secretAccessKey", secretAccessKey);
	}
	if (sessionToken) {
		await setKeychainSecret(id, "sessionToken", sessionToken);
	} else if (!existing) {
		await deleteKeychainSecret(id, "sessionToken");
	}

	const credential = {
		id,
		provider: "aws",
		label,
		region,
		s3Bucket,
		s3Prefix,
		storage: "macos-keychain",
		hasAccessKeyId: Boolean(accessKeyId || existing?.hasAccessKeyId),
		hasSecretAccessKey: Boolean(secretAccessKey || existing?.hasSecretAccessKey),
		hasSessionToken: Boolean(sessionToken || existing?.hasSessionToken),
		createdAt: existing?.createdAt || now,
		updatedAt: now,
	};
	await writeCredentialRegistry({
		credentials: [
			credential,
			...registry.credentials.filter((item) => item.id !== id),
		],
	});
	return {
		code: 0,
		command: "credential-save",
		args: ["aws", id],
		stdout: `Saved AWS credential "${label}"${s3Bucket ? ` with default source ${s3Uri(s3Bucket, s3Prefix)}` : ""}.`,
		stderr: "",
		durationMs: 0,
		credential: credentialPublicSummary(credential),
		credentials: (await credentialSummary()).credentials,
	};
}

async function deleteCredential(input = {}) {
	const id = sanitizeCredentialId(input.id || "");
	const registry = await readCredentialRegistry();
	const credential = registry.credentials.find((item) => item.id === id);
	if (!credential) {
		throw httpError(404, "Credential was not found.");
	}
	await Promise.all([
		deleteKeychainSecret(id, "accessKeyId"),
		deleteKeychainSecret(id, "secretAccessKey"),
		deleteKeychainSecret(id, "sessionToken"),
	]);
	const next = await writeCredentialRegistry({
		credentials: registry.credentials.filter((item) => item.id !== id),
	});
	return {
		code: 0,
		command: "credential-delete",
		args: [credential.provider, id],
		stdout: `Deleted ${credential.provider.toUpperCase()} credential "${credential.label}".`,
		stderr: "",
		durationMs: 0,
		credentials: next.credentials.map(credentialPublicSummary),
	};
}

async function awsEnvFromCredential(credentialId) {
	const id = sanitizeCredentialId(credentialId);
	const registry = await readCredentialRegistry();
	const credential = registry.credentials.find((item) => item.id === id && item.provider === "aws");
	if (!credential) {
		throw httpError(400, "Stored AWS credential was not found.");
	}
	const env = {
		AWS_ACCESS_KEY_ID: await getKeychainSecret(id, "accessKeyId"),
		AWS_SECRET_ACCESS_KEY: await getKeychainSecret(id, "secretAccessKey"),
	};
	if (credential.hasSessionToken) {
		env.AWS_SESSION_TOKEN = await getKeychainSecret(id, "sessionToken");
	}
	if (credential.region) {
		env.AWS_REGION = credential.region;
		env.AWS_DEFAULT_REGION = credential.region;
	}
	return { credential, env };
}

async function testAwsCredential(input = {}) {
	const id = sanitizeCredentialId(input.id || input.credentialId || "");
	const registry = await readCredentialRegistry();
	const credential = registry.credentials.find((item) => item.id === id && item.provider === "aws");
	if (!credential) {
		throw httpError(400, "Stored AWS credential was not found.");
	}
	const result = await testAwsBackupConnection({
		credentialId: id,
		region: input.region || credential.region || "",
		bucket: input.bucket || credential.s3Bucket || "",
		prefix: input.prefix || credential.s3Prefix || "",
	});
	return {
		...result,
		command: "credential-test",
		args: ["aws", credential.id, credential.s3Bucket ? s3Uri(credential.s3Bucket, credential.s3Prefix) : "no-default-bucket"],
	};
}

async function runCredentialAction(body) {
	const action = String(body.action || "");
	switch (action) {
		case "aws-save":
			return saveAwsCredential(body.credential || body);
		case "delete":
			return deleteCredential(body);
		case "aws-test":
			return testAwsCredential(body);
		default:
			throw httpError(400, `Unknown credential action: ${action}`);
	}
}

function providerSiteToSshFields(site) {
	return {
		provider: normalizeProvider(site.provider),
		slug: site.slug || "",
		liveUrl: site.liveUrl || "",
		remoteSsh: site.remoteSsh || "",
		remotePort: site.remotePort || "",
		remotePath: site.remotePath || "",
	};
}

function normalizeProviderSite(providerInput, input) {
	const provider = normalizeProvider(providerInput || input.provider || "generic");
	const liveUrl = sanitizeOptionalUrl(input.liveUrl || input.url || "");
	const name = sanitizeOptionalProviderText(input.name || input.title || input.slug || hostnameFromUrl(liveUrl), "Site name", 120);
	const slug = slugFromSiteIdentifier(input.slug || name || liveUrl);
	const remoteSsh = sanitizeOptionalRemoteSsh(input.remoteSsh || input.ssh || "");
	const remotePort = sanitizeSshPort(input.remotePort || input.port || "");
	const remotePath = sanitizeOptionalRemotePath(input.remotePath || input.path || "");
	const idBase = sanitizeOptionalProviderText(input.id || `${provider}:${slug}`, "Provider site ID", 160);
	const notes = sanitizeOptionalProviderText(input.notes || "", "Notes", 500);
	return {
		id: `${provider}:${idBase.replace(/^.*?:/, "")}`,
		provider,
		name: name || slug,
		slug,
		liveUrl,
		remoteSsh,
		remotePort,
		remotePath,
		notes,
		updatedAt: new Date().toISOString(),
		sshFields: providerSiteToSshFields({ provider, slug, liveUrl, remoteSsh, remotePort, remotePath }),
	};
}

function providerCredentialsSummary() {
	const wpEngineUser = process.env.WPENGINE_API_USER_ID
		|| process.env.WP_ENGINE_API_USER_ID
		|| process.env.WPE_API_USER_ID
		|| process.env.WPENGINE_USER_ID
		|| "";
	const wpEnginePassword = process.env.WPENGINE_API_PASSWORD
		|| process.env.WP_ENGINE_API_PASSWORD
		|| process.env.WPE_API_PASSWORD
		|| process.env.WPENGINE_PASSWORD
		|| "";
	return {
		wpengine: {
			envConfigured: Boolean(wpEngineUser && wpEnginePassword),
			envUserConfigured: Boolean(wpEngineUser),
			envPasswordConfigured: Boolean(wpEnginePassword),
			credentialSource: wpEngineUser && wpEnginePassword ? "environment" : "ephemeral",
		},
		aws: {
			envConfigured: Boolean(process.env.AWS_PROFILE || process.env.AWS_ACCESS_KEY_ID),
			profile: process.env.AWS_PROFILE || "",
			region: process.env.AWS_REGION || process.env.AWS_DEFAULT_REGION || "",
			credentialSource: process.env.AWS_PROFILE
				? "profile"
				: process.env.AWS_ACCESS_KEY_ID
					? "environment"
					: "aws-cli",
		},
	};
}

async function providerAccountSummary() {
	const registry = await readProviderRegistry();
	const credentials = await credentialSummary();
	return {
		registryPath: providerRegistryFile,
		accounts: {
			...providerCredentialsSummary(),
			siteground: {
				mode: "local-registry",
				count: registry.siteground.length,
			},
		},
		credentials,
		sites: {
			siteground: registry.siteground.map((site) => ({
				...site,
				sshFields: providerSiteToSshFields(site),
			})),
		},
		now: new Date().toISOString(),
	};
}

function wpEngineApiCredentials(input = {}) {
	const userId = String(input.userId || input.wpEngineUserId || process.env.WPENGINE_API_USER_ID || process.env.WP_ENGINE_API_USER_ID || process.env.WPE_API_USER_ID || process.env.WPENGINE_USER_ID || "").trim();
	const password = String(input.password || input.wpEnginePassword || process.env.WPENGINE_API_PASSWORD || process.env.WP_ENGINE_API_PASSWORD || process.env.WPE_API_PASSWORD || process.env.WPENGINE_PASSWORD || "").trim();
	if (!userId || !password) {
		throw httpError(400, "WP Engine API credentials are required. Enter them for this list call or set WPENGINE_API_USER_ID and WPENGINE_API_PASSWORD in the Hub environment.");
	}
	if (/[\0\r\n:]/.test(userId) || /[\0\r\n]/.test(password)) {
		throw httpError(400, "WP Engine API credentials contain unsupported characters.");
	}
	return { userId, password };
}

function wpEngineDomainFromInstall(install) {
	const fields = [
		install.primary_domain,
		install.primaryDomain,
		install.domain,
		install.cname,
		install.cname_url,
		install.environment?.primary_domain,
	];
	for (const field of fields) {
		const hostname = hostnameFromUrl(field);
		if (hostname) {
			return hostname;
		}
	}
	const domains = install.domains || install.domain_names || [];
	if (Array.isArray(domains)) {
		for (const domain of domains) {
			const hostname = hostnameFromUrl(typeof domain === "string" ? domain : domain.name || domain.domain || domain.hostname);
			if (hostname) {
				return hostname;
			}
		}
	}
	return "";
}

function normalizeWpEngineInstall(install) {
	const rawName = install.name || install.install_name || install.environment || install.id || "";
	const slug = normalizeSlug(rawName);
	const domain = wpEngineDomainFromInstall(install);
	const liveUrl = domain ? `https://${domain}` : "";
	const remoteSsh = `${slug}@${slug}.ssh.wpengine.net`;
	const remotePath = `sites/${slug}`;
	return {
		id: `wpengine:${install.id || slug}`,
		provider: "wpengine",
		name: sanitizeOptionalProviderText(install.name || install.display_name || slug, "WP Engine install name", 120) || slug,
		slug,
		liveUrl,
		remoteSsh,
		remotePort: "",
		remotePath,
		notes: sanitizeOptionalProviderText(install.account_name || install.account?.name || "", "WP Engine account name", 240),
		raw: {
			id: install.id || "",
			name: install.name || "",
			environment: install.environment || "",
			primaryDomain: domain,
		},
		updatedAt: new Date().toISOString(),
		sshFields: providerSiteToSshFields({ provider: "wpengine", slug, liveUrl, remoteSsh, remotePort: "", remotePath }),
	};
}

function wpEngineNextUrl(payload, currentUrl) {
	const next = payload.next || payload.next_page || payload.links?.next || payload._links?.next?.href || "";
	if (!next) {
		return "";
	}
	try {
		return new URL(next, currentUrl).toString();
	} catch {
		return "";
	}
}

async function listWpEngineInstalls(input = {}) {
	if (typeof fetch !== "function") {
		throw httpError(500, "This Node runtime does not provide fetch; upgrade Node before using WP Engine discovery.");
	}
	const credentials = wpEngineApiCredentials(input);
	const headers = {
		authorization: `Basic ${Buffer.from(`${credentials.userId}:${credentials.password}`).toString("base64")}`,
		accept: "application/json",
	};
	const installs = [];
	let pageUrl = "https://api.wpengineapi.com/v1/installs?limit=100";
	for (let page = 0; page < 10 && pageUrl; page += 1) {
		const response = await fetch(pageUrl, { headers });
		const text = await response.text();
		let payload = {};
		try {
			payload = text ? JSON.parse(text) : {};
		} catch {
			throw httpError(response.ok ? 502 : response.status, "WP Engine API returned a non-JSON response.");
		}
		if (!response.ok) {
			throw httpError(response.status, payload.message || payload.error || `WP Engine API request failed with status ${response.status}.`);
		}
		const pageInstalls = Array.isArray(payload.results)
			? payload.results
			: Array.isArray(payload.installs)
				? payload.installs
				: Array.isArray(payload)
					? payload
					: [];
		installs.push(...pageInstalls);
		pageUrl = wpEngineNextUrl(payload, pageUrl);
	}
	const sites = installs.map(normalizeWpEngineInstall).sort((a, b) => a.slug.localeCompare(b.slug));
	return {
		code: 0,
		command: "wpengine-list",
		args: [input.userId || input.wpEngineUserId ? "ephemeral-credentials" : "environment-credentials"],
		stdout: sites.length
			? `WP Engine returned ${sites.length} install${sites.length === 1 ? "" : "s"}. Use a result to fill SSH Import.`
			: "WP Engine returned no installs for these credentials.",
		stderr: "",
		durationMs: 0,
		sites,
	};
}

async function runProviderAccountAction(body) {
	const action = String(body.action || "");
	switch (action) {
		case "wpengine-list":
			return listWpEngineInstalls(body);
		case "siteground-list": {
			const summary = await providerAccountSummary();
			return {
				code: 0,
				command: "siteground-list",
				args: [providerRegistryFile],
				stdout: `${summary.sites.siteground.length} SiteGround site${summary.sites.siteground.length === 1 ? "" : "s"} in the local registry.`,
				stderr: "",
				durationMs: 0,
				sites: summary.sites.siteground,
			};
		}
		case "siteground-add": {
			const registry = await readProviderRegistry();
			const site = normalizeProviderSite("siteground", body.site || body);
			const nextSiteground = [
				site,
				...registry.siteground.filter((item) => item.id !== site.id && item.slug !== site.slug),
			].sort((a, b) => a.slug.localeCompare(b.slug));
			const next = await writeProviderRegistry({ ...registry, siteground: nextSiteground });
			return {
				code: 0,
				command: "siteground-add",
				args: [site.slug],
				stdout: `Saved SiteGround provider site: ${site.name} (${site.slug}).`,
				stderr: "",
				durationMs: 0,
				site,
				sites: next.siteground,
			};
		}
		case "siteground-remove": {
			const registry = await readProviderRegistry();
			const id = sanitizeOptionalProviderText(body.id || body.slug || "", "SiteGround registry ID", 160);
			if (!id) {
				throw httpError(400, "SiteGround registry ID or slug is required.");
			}
			const before = registry.siteground.length;
			const siteground = registry.siteground.filter((site) => site.id !== id && site.slug !== id);
			await writeProviderRegistry({ ...registry, siteground });
			return {
				code: before === siteground.length ? 1 : 0,
				command: "siteground-remove",
				args: [id],
				stdout: before === siteground.length ? `No SiteGround registry entry matched ${id}.` : `Removed SiteGround registry entry: ${id}.`,
				stderr: "",
				durationMs: 0,
				sites: siteground,
			};
		}
		case "provider-to-ssh-fields": {
			const site = normalizeProviderSite(body.provider || body.site?.provider || "generic", body.site || body);
			return {
				code: 0,
				command: "provider-to-ssh-fields",
				args: [site.provider, site.slug],
				stdout: `Prepared SSH Import fields for ${site.name}.`,
				stderr: "",
				durationMs: 0,
				site,
				sshFields: site.sshFields,
			};
		}
		default:
			throw httpError(400, `Unknown provider discovery action: ${action}`);
	}
}

function sanitizeBackupSessionId(value) {
	const session = String(value || "").trim().toLowerCase();
	if (!/^[a-z0-9][a-z0-9-]{7,80}$/.test(session)) {
		throw httpError(400, "Backup staging session is invalid.");
	}
	return session;
}

function newBackupSessionId() {
	return `updraft-${crypto.randomBytes(8).toString("hex")}`;
}

function updraftSessionDir(sessionId) {
	return path.join(updraftStagingRoot, sanitizeBackupSessionId(sessionId));
}

function sanitizeBackupFileName(value) {
	const basename = path.basename(String(value || "").trim()).replace(/[^\w .@()+,-]/g, "-");
	if (!basename || basename === "." || basename === "..") {
		throw httpError(400, "Backup file name is required.");
	}
	if (!/\.(zip|gz|sql|sql\.gz)$/i.test(basename)) {
		throw httpError(400, "Updraft restore accepts .zip, .gz, .sql, and .sql.gz files.");
	}
	return basename;
}

function sanitizeAwsProfile(value) {
	const profile = String(value || "").trim();
	if (!profile) return "";
	if (!/^[A-Za-z0-9_.@:+-]{1,120}$/.test(profile)) {
		throw httpError(400, "AWS profile contains unsupported characters.");
	}
	return profile;
}

function sanitizeAwsRegion(value) {
	const region = String(value || "").trim();
	if (!region) return "";
	if (!/^[A-Za-z0-9-]{1,60}$/.test(region)) {
		throw httpError(400, "AWS region contains unsupported characters.");
	}
	return region;
}

function sanitizeS3Bucket(value) {
	const bucket = String(value || "").trim();
	if (!/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/.test(bucket)) {
		throw httpError(400, "Enter a valid S3 bucket name.");
	}
	return bucket;
}

function sanitizeS3Key(value, label = "S3 key") {
	const key = String(value || "").trim().replace(/^\/+/, "");
	if (!key || key.includes("\0") || key.includes("..")) {
		throw httpError(400, `${label} is invalid.`);
	}
	return key;
}

function sanitizeS3Prefix(value) {
	const prefix = String(value || "").trim().replace(/^\/+/, "");
	if (!prefix) return "";
	if (prefix.includes("\0") || prefix.includes("..")) {
		throw httpError(400, "S3 prefix is invalid.");
	}
	return prefix.replace(/\/?$/, "/");
}

function awsCliArgs(input = {}) {
	const args = [];
	const profile = sanitizeAwsProfile(input.profile || "");
	const region = sanitizeAwsRegion(input.region || "");
	if (profile) args.push("--profile", profile);
	if (region) args.push("--region", region);
	return args;
}

async function awsCliContext(input = {}) {
	const credentialId = String(input.credentialId || input.awsCredentialId || "").trim();
	if (!credentialId) {
		return { args: awsCliArgs(input), env: {}, credential: null };
	}
	const { credential, env } = await awsEnvFromCredential(credentialId);
	const region = sanitizeAwsRegion(input.region || "");
	if (region) {
		env.AWS_REGION = region;
		env.AWS_DEFAULT_REGION = region;
	}
	return { args: [], env, credential };
}

function awsPercentEncode(value) {
	return encodeURIComponent(String(value))
		.replace(/[!'()*]/g, (char) => `%${char.charCodeAt(0).toString(16).toUpperCase()}`);
}

function awsCanonicalQuery(params = {}) {
	return Object.entries(params)
		.filter(([, value]) => typeof value !== "undefined" && value !== null && value !== "")
		.flatMap(([key, value]) => Array.isArray(value) ? value.map((item) => [key, item]) : [[key, value]])
		.map(([key, value]) => [awsPercentEncode(key), awsPercentEncode(value)])
		.sort(([aKey, aValue], [bKey, bValue]) => aKey === bKey ? aValue.localeCompare(bValue) : aKey.localeCompare(bKey))
		.map(([key, value]) => `${key}=${value}`)
		.join("&");
}

function awsCanonicalUri(value) {
	const pathname = String(value || "/");
	return pathname
		.split("/")
		.map((part) => awsPercentEncode(decodeURIComponent(part)))
		.join("/") || "/";
}

function sha256Hex(value) {
	return crypto.createHash("sha256").update(value).digest("hex");
}

function hmacSha256(key, value, encoding) {
	return crypto.createHmac("sha256", key).update(value).digest(encoding);
}

function awsSigningKey(secretAccessKey, dateStamp, region, service) {
	const kDate = hmacSha256(`AWS4${secretAccessKey}`, dateStamp);
	const kRegion = hmacSha256(kDate, region);
	const kService = hmacSha256(kRegion, service);
	return hmacSha256(kService, "aws4_request");
}

function awsDateParts(date = new Date()) {
	const iso = date.toISOString().replace(/[:-]|\.\d{3}/g, "");
	return {
		amzDate: iso,
		dateStamp: iso.slice(0, 8),
	};
}

async function awsStoredSecrets(credentialId) {
	const { credential, env } = await awsEnvFromCredential(credentialId);
	return {
		credential,
		accessKeyId: env.AWS_ACCESS_KEY_ID,
		secretAccessKey: env.AWS_SECRET_ACCESS_KEY,
		sessionToken: env.AWS_SESSION_TOKEN || "",
		region: env.AWS_REGION || env.AWS_DEFAULT_REGION || "",
	};
}

async function awsSignedFetch(options = {}) {
	const method = String(options.method || "GET").toUpperCase();
	const service = String(options.service || "");
	const region = sanitizeAwsRegion(options.region || "");
	const hostName = String(options.host || "").trim();
	const pathname = options.path || "/";
	const query = awsCanonicalQuery(options.query || {});
	const body = typeof options.body === "undefined" || options.body === null ? "" : options.body;
	const bodyBuffer = Buffer.isBuffer(body) ? body : Buffer.from(String(body));
	const payloadHash = sha256Hex(bodyBuffer);
	const { amzDate, dateStamp } = awsDateParts();
	const headers = {
		...(options.headers || {}),
		host: hostName,
		"x-amz-content-sha256": payloadHash,
		"x-amz-date": amzDate,
	};
	if (options.sessionToken) {
		headers["x-amz-security-token"] = options.sessionToken;
	}
	const normalizedHeaders = Object.fromEntries(
		Object.entries(headers).map(([key, value]) => [key.toLowerCase(), String(value).trim()]),
	);
	const signedHeaderNames = Object.keys(normalizedHeaders).sort();
	const canonicalHeaders = signedHeaderNames
		.map((key) => `${key}:${normalizedHeaders[key].replace(/\s+/g, " ")}\n`)
		.join("");
	const signedHeaders = signedHeaderNames.join(";");
	const canonicalRequest = [
		method,
		awsCanonicalUri(pathname),
		query,
		canonicalHeaders,
		signedHeaders,
		payloadHash,
	].join("\n");
	const credentialScope = `${dateStamp}/${region}/${service}/aws4_request`;
	const stringToSign = [
		"AWS4-HMAC-SHA256",
		amzDate,
		credentialScope,
		sha256Hex(canonicalRequest),
	].join("\n");
	const signature = hmacSha256(
		awsSigningKey(options.secretAccessKey, dateStamp, region, service),
		stringToSign,
		"hex",
	);
	const authorization = `AWS4-HMAC-SHA256 Credential=${options.accessKeyId}/${credentialScope}, SignedHeaders=${signedHeaders}, Signature=${signature}`;
	const url = `https://${hostName}${pathname}${query ? `?${query}` : ""}`;
	try {
		return await fetch(url, {
			method,
			headers: {
				...normalizedHeaders,
				authorization,
			},
			body: ["GET", "HEAD"].includes(method) ? undefined : bodyBuffer,
		});
	} catch (error) {
		throw httpError(502, `AWS request could not reach ${hostName}.`, {
			method,
			service,
			region,
			host: hostName,
			path: pathname,
			cause: error.message || String(error),
		});
	}
}

function xmlDecode(value) {
	return String(value || "")
		.replace(/&lt;/g, "<")
		.replace(/&gt;/g, ">")
		.replace(/&quot;/g, "\"")
		.replace(/&apos;/g, "'")
		.replace(/&amp;/g, "&");
}

function xmlTagText(block, tagName) {
	const match = String(block || "").match(new RegExp(`<${tagName}>([\\s\\S]*?)</${tagName}>`));
	return match ? xmlDecode(match[1]) : "";
}

async function detectS3BucketRegion(bucket) {
	try {
		const response = await fetch(`https://s3.amazonaws.com/${encodeURIComponent(bucket)}`, {
			method: "HEAD",
		});
		return response.headers.get("x-amz-bucket-region") || "";
	} catch {
		return "";
	}
}

async function nativeAwsCallerIdentity(credentialId, input = {}) {
	const secrets = await awsStoredSecrets(credentialId);
	const region = sanitizeAwsRegion(input.region || secrets.region || "us-east-1");
	const body = "Action=GetCallerIdentity&Version=2011-06-15";
	const response = await awsSignedFetch({
		...secrets,
		method: "POST",
		service: "sts",
		region,
		host: `sts.${region}.amazonaws.com`,
		path: "/",
		headers: {
			"content-type": "application/x-www-form-urlencoded; charset=utf-8",
		},
		body,
	});
	const text = await response.text();
	return {
		ok: response.ok,
		status: response.status,
		text,
		credential: secrets.credential,
		region,
	};
}

async function nativeS3ListObjects(credentialId, body = {}, options = {}) {
	const bucket = sanitizeS3Bucket(body.bucket || "");
	const prefix = sanitizeS3Prefix(body.prefix || "");
	const secrets = await awsStoredSecrets(credentialId);
	const detectedRegion = body.region || secrets.region ? "" : await detectS3BucketRegion(bucket);
	const region = sanitizeAwsRegion(body.region || secrets.region || detectedRegion || "us-east-1");
	const hostName = `s3.${region}.amazonaws.com`;
	const maxKeys = String(options.maxKeys || 1000);
	const delimiter = options.delimiter ? "/" : "";
	let continuationToken = "";
	const files = [];
	const prefixes = new Set();
	let durationMs = 0;
	let truncated = false;
	for (let page = 0; page < Number(process.env.MRN_LOCAL_HUB_AWS_MAX_LIST_PAGES || "25"); page += 1) {
		const startedAt = Date.now();
		const response = await awsSignedFetch({
			...secrets,
			method: "GET",
			service: "s3",
			region,
			host: hostName,
			path: `/${bucket}`,
			query: {
				"list-type": "2",
				prefix,
				"max-keys": maxKeys,
				delimiter,
				"continuation-token": continuationToken,
			},
		});
		durationMs += Date.now() - startedAt;
		const text = await response.text();
		if (!response.ok) {
			return {
				ok: false,
				status: response.status,
				text,
				files,
				prefixes: [...prefixes],
				bucket,
				prefix,
				region,
				durationMs,
				truncated,
				credential: secrets.credential,
			};
		}
		for (const match of text.matchAll(/<CommonPrefixes>([\s\S]*?)<\/CommonPrefixes>/g)) {
			const commonPrefix = xmlTagText(match[1], "Prefix");
			if (commonPrefix) {
				prefixes.add(commonPrefix);
			}
		}
		const contents = [...text.matchAll(/<Contents>([\s\S]*?)<\/Contents>/g)];
		for (const match of contents) {
			const block = match[1];
			const key = xmlTagText(block, "Key");
			if (!key) continue;
			const lastModified = xmlTagText(block, "LastModified");
			files.push({
				lastModified: lastModified.replace("T", " ").replace(/\.\d+Z$/, ""),
				sizeBytes: Number.parseInt(xmlTagText(block, "Size"), 10) || 0,
				key,
				name: path.basename(key),
				component: updraftComponentFromName(key),
			});
		}
		if (!/<IsTruncated>true<\/IsTruncated>/.test(text)) {
			break;
		}
		truncated = true;
		continuationToken = xmlTagText(text, "NextContinuationToken");
		if (!continuationToken) {
			break;
		}
	}
	return {
		ok: true,
		status: 200,
		text: "",
		files,
		prefixes: [...prefixes].sort((a, b) => a.localeCompare(b)),
		bucket,
		prefix,
		region,
		durationMs,
		truncated,
		credential: secrets.credential,
	};
}

async function nativeS3ScanFolderTree(credentialId, body = {}, options = {}) {
	const rootPrefix = sanitizeS3Prefix(body.prefix || "");
	const maxFolders = Number(options.maxFolders || process.env.MRN_LOCAL_HUB_AWS_MAX_SCAN_FOLDERS || "500");
	const queue = [rootPrefix];
	const seen = new Set();
	const discoveredPrefixes = new Set();
	const files = [];
	let durationMs = 0;
	let region = "";
	let credential = null;
	let bucket = "";
	let truncated = false;
	while (queue.length && seen.size < maxFolders) {
		const prefix = queue.shift();
		if (seen.has(prefix)) continue;
		seen.add(prefix);
		const result = await nativeS3ListObjects(credentialId, { ...body, prefix }, { delimiter: true });
		durationMs += result.durationMs || 0;
		region = result.region || region;
		credential = result.credential || credential;
		bucket = result.bucket || bucket;
		truncated = truncated || Boolean(result.truncated);
		if (!result.ok) {
			return {
				...result,
				files,
				prefixes: [...discoveredPrefixes].sort((a, b) => a.localeCompare(b)),
				durationMs,
			};
		}
		files.push(...result.files);
		for (const folder of result.prefixes || []) {
			discoveredPrefixes.add(folder);
			if (!seen.has(folder) && queue.length + seen.size < maxFolders) {
				queue.push(folder);
			}
		}
	}
	if (queue.length) {
		truncated = true;
	}
	return {
		ok: true,
		status: 200,
		text: "",
		files,
		prefixes: [...discoveredPrefixes].sort((a, b) => a.localeCompare(b)),
		bucket: bucket || sanitizeS3Bucket(body.bucket || ""),
		prefix: rootPrefix,
		region,
		durationMs,
		credential,
		truncated,
		foldersScanned: seen.size,
	};
}

async function nativeS3DownloadObject(credentialId, body = {}, key, dest) {
	const bucket = sanitizeS3Bucket(body.bucket || "");
	const s3Key = sanitizeS3Key(key);
	const secrets = await awsStoredSecrets(credentialId);
	const detectedRegion = body.region || secrets.region ? "" : await detectS3BucketRegion(bucket);
	const region = sanitizeAwsRegion(body.region || secrets.region || detectedRegion || "us-east-1");
	const hostName = `s3.${region}.amazonaws.com`;
	const response = await awsSignedFetch({
		...secrets,
		method: "GET",
		service: "s3",
		region,
		host: hostName,
		path: `/${bucket}/${s3Key.split("/").map(awsPercentEncode).join("/")}`,
	});
	if (!response.ok) {
		return {
			code: 1,
			stderr: await response.text(),
			durationMs: 0,
		};
	}
	await new Promise((resolve, reject) => {
		const out = fs.createWriteStream(dest, { flags: "w" });
		Readable.fromWeb(response.body).on("error", reject).pipe(out);
		out.on("finish", resolve);
		out.on("error", reject);
	});
	return {
		code: 0,
		stderr: "",
		durationMs: 0,
		region,
	};
}

function updraftComponentFromName(fileName) {
	const lower = String(fileName || "").toLowerCase();
	if (/(^|[-_])db(?:[._-]|\d)/.test(lower) && /\.(gz|sql|sql\.gz)$/i.test(lower)) return "db";
	if (/-plugins\d*\.zip$/i.test(lower)) return "plugins";
	if (/-themes\d*\.zip$/i.test(lower)) return "themes";
	if (/-uploads\d*\.zip$/i.test(lower)) return "uploads";
	if (/-mu-plugins\d*\.zip$/i.test(lower)) return "mu-plugins";
	if (/-others\d*\.zip$/i.test(lower)) return "others";
	if (/-(wpcore|core)\d*\.zip$/i.test(lower)) return "core";
	if (/\.zip$/i.test(lower)) return "zip";
	return "unknown";
}

async function classifyZipBackupComponent(filePath, fileName) {
	const component = updraftComponentFromName(fileName);
	if (component !== "zip") {
		return component;
	}
	const result = await runProcess("unzip", ["-Z1", filePath], {
		timeoutMs: Number(process.env.MRN_LOCAL_HUB_ZIP_LIST_TIMEOUT_MS || "120000"),
		trackJob: false,
		maxOutputBytes: maxZipListOutputBytes,
	});
	if (result.code !== 0) {
		return component;
	}
	const entries = result.stdout
		.split(/\r?\n/)
		.map((entry) => entry.trim().replace(/\\/g, "/"))
		.filter(Boolean);
	const hasManifest = entries.includes("manifest.json") || entries.includes("manifest.csv");
	const hasSql = entries.some((entry) => /(^|\/)[^/]+\.sql$/i.test(entry));
	const hasDatabaseDirectory = entries.some((entry) => /^(sql|database|db|mysql)\//i.test(entry));
	const hasWordPressFiles = entries.some((entry) => /(^|\/)wp-content\//i.test(entry))
		&& entries.some((entry) => /(^|\/)wp-admin\//i.test(entry))
		&& entries.some((entry) => /(^|\/)wp-includes\//i.test(entry));
	if (hasWordPressFiles && (hasSql || hasManifest)) {
		return "site-archive";
	}
	if (hasSql || hasDatabaseDirectory) {
		return "db-archive";
	}
	return component;
}

function updraftSetIdFromKey(key) {
	return String(key || "")
		.replace(/-(db\d*|plugins\d*|themes\d*|uploads\d*|mu-plugins\d*|others\d*|wpcore\d*|core\d*)\.(zip|gz|sql|sql\.gz)$/i, "")
		.replace(/\.(zip|gz|sql|sql\.gz)$/i, "");
}

function s3Uri(bucket, key) {
	return `s3://${bucket}/${key}`;
}

async function inspectUpdraftSession(sessionId) {
	const session = sanitizeBackupSessionId(sessionId);
	const dir = updraftSessionDir(session);
	await fsp.mkdir(dir, { recursive: true });
	const entries = await fsp.readdir(dir, { withFileTypes: true });
	const files = [];
	for (const entry of entries) {
		if (!entry.isFile()) continue;
		const name = sanitizeBackupFileName(entry.name);
		const filePath = path.join(dir, name);
		const stat = await fsp.stat(filePath);
		const component = await classifyZipBackupComponent(filePath, name);
		files.push({
			name,
			component,
			sizeBytes: stat.size,
			path: filePath,
			uploadedAt: stat.birthtime.toISOString(),
		});
	}
	files.sort((a, b) => a.name.localeCompare(b.name));
	const components = files.reduce((acc, file) => {
		acc[file.component] = (acc[file.component] || 0) + 1;
		return acc;
	}, {});
	return {
		session,
		stagingDir: dir,
		files,
		components,
		ready: files.some((file) => file.component === "db" || file.component !== "unknown"),
	};
}

async function stageUpdraftUpload(req, url, sessionId) {
	const session = sanitizeBackupSessionId(sessionId || newBackupSessionId());
	const fileName = sanitizeBackupFileName(url.searchParams.get("filename") || "");
	const dir = updraftSessionDir(session);
	await fsp.mkdir(dir, { recursive: true });
	const filePath = path.join(dir, fileName);
	const contentLength = Number(req.headers["content-length"] || 0);
	if (contentLength > maxBackupUploadBytes) {
		throw httpError(413, "Backup file is larger than the configured upload limit.");
	}

	let bytes = 0;
	await new Promise((resolve, reject) => {
		const out = fs.createWriteStream(filePath, { flags: "w" });
		let rejected = false;
		const fail = (error) => {
			if (rejected) return;
			rejected = true;
			out.destroy();
			reject(error);
		};
		req.on("data", (chunk) => {
			bytes += chunk.length;
			if (bytes > maxBackupUploadBytes) {
				fail(httpError(413, "Backup file is larger than the configured upload limit."));
				req.destroy();
			}
		});
		req.on("error", fail);
		out.on("error", fail);
		out.on("finish", () => {
			if (!rejected) resolve();
		});
		req.pipe(out);
	});

	return inspectUpdraftSession(session);
}

function parseAwsS3Ls(output) {
	return String(output || "")
		.split(/\r?\n/)
		.map((line) => line.trim())
		.filter(Boolean)
		.map((line) => {
			const match = line.match(/^(\d{4}-\d\d-\d\d)\s+(\d\d:\d\d:\d\d)\s+(\d+)\s+(.+)$/);
			if (!match) return null;
			const [, date, time, size, key] = match;
			return {
				lastModified: `${date} ${time}`,
				sizeBytes: Number.parseInt(size, 10) || 0,
				key,
				name: path.basename(key),
				component: updraftComponentFromName(key),
			};
		})
		.filter(Boolean);
}

function groupUpdraftS3Files(files) {
	const groups = new Map();
	for (const file of files.filter((item) => item.component !== "unknown")) {
		const id = updraftSetIdFromKey(file.key);
		if (!groups.has(id)) {
			const folder = path.posix.dirname(id) === "." ? "" : path.posix.dirname(id);
			groups.set(id, {
				id,
				label: path.posix.basename(id),
				folder,
				lastModified: file.lastModified,
				totalBytes: 0,
				components: {},
				files: [],
			});
		}
		const group = groups.get(id);
		group.files.push(file);
		group.totalBytes += file.sizeBytes || 0;
		group.components[file.component] = (group.components[file.component] || 0) + 1;
		if (file.lastModified > group.lastModified) group.lastModified = file.lastModified;
	}
	return [...groups.values()]
		.map((group) => ({
			...group,
			files: group.files.sort((a, b) => a.name.localeCompare(b.name)),
		}))
		.sort((a, b) => b.lastModified.localeCompare(a.lastModified));
}

async function listAwsUpdraftBackups(body = {}) {
	const bucket = sanitizeS3Bucket(body.bucket || "");
	const prefix = sanitizeS3Prefix(body.prefix || "");
	const credentialId = String(body.credentialId || body.awsCredentialId || "").trim();
	if (credentialId) {
		const nativeResult = await nativeS3ScanFolderTree(credentialId, body);
		const groups = nativeResult.ok ? groupUpdraftS3Files(nativeResult.files) : [];
		const result = {
			code: nativeResult.ok ? 0 : 1,
			command: "aws-s3-list",
			args: [bucket, prefix || "/", `stored:${nativeResult.credential?.label || credentialId}`, `region:${nativeResult.region}`],
			stdout: nativeResult.ok
				? groups.length
					? `Indexed ${groups.length} Updraft backup set${groups.length === 1 ? "" : "s"} across ${nativeResult.prefixes?.length || 0} folder${(nativeResult.prefixes?.length || 0) === 1 ? "" : "s"} in ${s3Uri(bucket, prefix)}.`
					: `No Updraft backup sets found while scanning ${nativeResult.prefixes?.length || 0} folder${(nativeResult.prefixes?.length || 0) === 1 ? "" : "s"} in ${s3Uri(bucket, prefix)}.`
				: `Could not list ${s3Uri(bucket, prefix)} with stored AWS key.`,
			stderr: nativeResult.ok ? "" : nativeResult.text,
			durationMs: nativeResult.durationMs,
			files: nativeResult.files,
			prefixes: nativeResult.prefixes || [],
			groups,
			region: nativeResult.region,
			truncated: nativeResult.truncated,
			foldersScanned: nativeResult.foldersScanned || 0,
		};
		const index = await storeAwsBackupIndex(body, result, nativeResult.credential);
		return {
			...result,
			index: compactAwsBackupIndex(index),
		};
	}
	const aws = await commandExists("aws");
	if (!aws.ok) {
		return {
			code: 1,
			command: "aws-s3-list",
			args: [bucket, prefix],
			stdout: "",
			stderr: "AWS CLI is not installed or not on PATH. Install/configure awscli before listing S3 backups.",
			durationMs: 0,
			files: [],
			prefixes: [],
			groups: [],
		};
	}
	const awsContext = await awsCliContext(body);
	const result = await runProcess("aws", [
		...awsContext.args,
		"s3",
		"ls",
		s3Uri(bucket, prefix),
		"--recursive",
	], {
		timeoutMs: Number(process.env.MRN_LOCAL_HUB_AWS_LIST_TIMEOUT_MS || "60000"),
		env: awsContext.env,
	});
	const files = result.code === 0 ? parseAwsS3Ls(result.stdout) : [];
	const prefixes = folderPrefixesFromFiles(files, prefix);
	const groups = groupUpdraftS3Files(files);
	const listResult = {
		...result,
		command: "aws-s3-list",
		args: [bucket, prefix || "/", awsContext.credential ? `stored:${awsContext.credential.label}` : "aws-cli"],
		stdout: result.code === 0
			? groups.length
				? `Indexed ${groups.length} Updraft backup set${groups.length === 1 ? "" : "s"} across ${prefixes.length} folder${prefixes.length === 1 ? "" : "s"} in ${s3Uri(bucket, prefix)}.`
				: `No Updraft backup sets found in ${s3Uri(bucket, prefix)}.`
			: result.stdout,
		files,
		prefixes,
		groups,
	};
	const index = await storeAwsBackupIndex(body, listResult, awsContext.credential);
	return {
		...listResult,
		index: compactAwsBackupIndex(index),
	};
}

async function testAwsBackupConnection(body = {}) {
	const credentialId = String(body.credentialId || body.awsCredentialId || "").trim();
	if (credentialId) {
		const checks = [];
		let durationMs = 0;
		const identityStartedAt = Date.now();
		const identity = await nativeAwsCallerIdentity(credentialId, body);
		durationMs += Date.now() - identityStartedAt;
		const source = `stored key "${identity.credential.label}"`;
		if (!identity.ok) {
			checks.push({ label: "AWS identity", status: "fail", detail: `AWS STS returned HTTP ${identity.status}.` });
			return {
				code: 1,
				command: "aws-connection-test",
				args: [source, `region:${identity.region}`],
				stdout: `AWS connection test failed using ${source}.`,
				stderr: identity.text,
				durationMs,
				checks,
			};
		}
		checks.push({ label: "AWS identity", status: "pass", detail: `Authenticated with ${source}.` });
		const bucketInput = String(body.bucket || "").trim();
		if (bucketInput) {
			const s3 = await nativeS3ListObjects(credentialId, body, { maxKeys: 1 });
			durationMs += s3.durationMs || 0;
			if (!s3.ok) {
				checks.push({ label: "S3 list access", status: "fail", detail: `Could not list ${s3Uri(s3.bucket, s3.prefix)}.` });
				return {
					code: 1,
					command: "aws-connection-test",
					args: [source, s3.bucket, s3.prefix || "/", `region:${s3.region}`],
					stdout: `AWS identity passed, but S3 list access failed for ${s3Uri(s3.bucket, s3.prefix)}.`,
					stderr: s3.text,
					durationMs,
					checks,
				};
			}
			checks.push({ label: "S3 list access", status: "pass", detail: `Can list ${s3Uri(s3.bucket, s3.prefix)}.` });
		}
		return {
			code: 0,
			command: "aws-connection-test",
			args: [source, bucketInput ? sanitizeS3Bucket(bucketInput) : "no-bucket"],
			stdout: [
				`AWS connection test passed using ${source}.`,
				bucketInput ? "S3 bucket access check passed." : "No bucket entered, so only AWS identity was checked.",
			].join("\n"),
			stderr: "",
			durationMs,
			checks,
		};
	}
	const aws = await commandExists("aws");
	if (!aws.ok) {
		return {
			code: 1,
			command: "aws-connection-test",
			args: ["aws-cli"],
			stdout: "",
			stderr: "AWS CLI is not installed or not on PATH.",
			durationMs: 0,
			checks: [{ label: "AWS CLI", status: "fail", detail: "aws command was not found." }],
		};
	}
	const awsContext = await awsCliContext(body);
	const profile = sanitizeAwsProfile(body.profile || "");
	const source = awsContext.credential
		? `stored key "${awsContext.credential.label}"`
		: profile
			? `AWS profile "${profile}"`
			: "AWS CLI default credentials";
	const checks = [];
	let durationMs = 0;
	const identity = await runProcess("aws", [
		...awsContext.args,
		"sts",
		"get-caller-identity",
		"--output",
		"json",
	], {
		timeoutMs: Number(process.env.MRN_LOCAL_HUB_AWS_TEST_TIMEOUT_MS || "30000"),
		trackJob: false,
		env: awsContext.env,
	});
	durationMs += identity.durationMs || 0;
	if (identity.code !== 0) {
		checks.push({ label: "AWS identity", status: "fail", detail: "Could not resolve caller identity." });
		return {
			code: identity.code || 1,
			command: "aws-connection-test",
			args: [source],
			stdout: `AWS connection test failed using ${source}.`,
			stderr: identity.stderr || identity.stdout,
			durationMs,
			checks,
		};
	}
	checks.push({ label: "AWS identity", status: "pass", detail: `Authenticated with ${source}.` });

	const bucketInput = String(body.bucket || "").trim();
	if (bucketInput) {
		const bucket = sanitizeS3Bucket(bucketInput);
		const prefix = sanitizeS3Prefix(body.prefix || "");
		const s3 = await runProcess("aws", [
			...awsContext.args,
			"s3api",
			"list-objects-v2",
			"--bucket",
			bucket,
			...(prefix ? ["--prefix", prefix] : []),
			"--max-items",
			"1",
			"--output",
			"json",
		], {
			timeoutMs: Number(process.env.MRN_LOCAL_HUB_AWS_TEST_TIMEOUT_MS || "30000"),
			trackJob: false,
			env: awsContext.env,
		});
		durationMs += s3.durationMs || 0;
		if (s3.code !== 0) {
			checks.push({ label: "S3 list access", status: "fail", detail: `Could not list ${s3Uri(bucket, prefix)}.` });
			return {
				code: s3.code || 1,
				command: "aws-connection-test",
				args: [source, bucket, prefix || "/"],
				stdout: `AWS identity passed, but S3 list access failed for ${s3Uri(bucket, prefix)}.`,
				stderr: s3.stderr || s3.stdout,
				durationMs,
				checks,
			};
		}
		checks.push({ label: "S3 list access", status: "pass", detail: `Can list ${s3Uri(bucket, prefix)}.` });
	}

	return {
		code: 0,
		command: "aws-connection-test",
		args: [source, bucketInput ? sanitizeS3Bucket(bucketInput) : "no-bucket"],
		stdout: [
			`AWS connection test passed using ${source}.`,
			bucketInput ? "S3 bucket access check passed." : "No bucket entered, so only AWS identity was checked.",
		].join("\n"),
		stderr: "",
		durationMs,
		checks,
	};
}

async function downloadAwsUpdraftFiles(body = {}) {
	const bucket = sanitizeS3Bucket(body.bucket || "");
	const keys = Array.isArray(body.keys) ? body.keys.map((key) => sanitizeS3Key(key)) : [];
	if (!keys.length) {
		throw httpError(400, "Choose at least one S3 backup file to download.");
	}
	const session = sanitizeBackupSessionId(body.session || newBackupSessionId());
	const dir = updraftSessionDir(session);
	await fsp.mkdir(dir, { recursive: true });
	const credentialId = String(body.credentialId || body.awsCredentialId || "").trim();
	if (credentialId) {
		const credential = (await awsStoredSecrets(credentialId)).credential;
		const outputs = [];
		const errors = [];
		let code = 0;
		let durationMs = 0;
		for (const key of keys) {
			const startedAt = Date.now();
			const fileName = sanitizeBackupFileName(path.basename(key));
			const dest = path.join(dir, fileName);
			const result = await nativeS3DownloadObject(credentialId, body, key, dest);
			durationMs += result.durationMs || Date.now() - startedAt;
			if (result.code !== 0) {
				code = result.code;
				errors.push(result.stderr || `Failed to download ${key}`);
				break;
			}
			outputs.push(`Downloaded ${s3Uri(bucket, key)} -> ${dest}`);
		}
		const inspection = await inspectUpdraftSession(session);
		return {
			code,
			command: "aws-s3-download",
			args: [bucket, `${keys.length} file${keys.length === 1 ? "" : "s"}`, session, `stored:${credential.label}`],
			stdout: outputs.join("\n"),
			stderr: errors.join("\n"),
			durationMs,
			session: inspection,
		};
	}
	const awsContext = await awsCliContext(body);
	const outputs = [];
	const errors = [];
	let code = 0;
	let durationMs = 0;
	for (const key of keys) {
		const fileName = sanitizeBackupFileName(path.basename(key));
		const dest = path.join(dir, fileName);
		const result = await runProcess("aws", [
			...awsContext.args,
			"s3",
			"cp",
			s3Uri(bucket, key),
			dest,
		], {
			timeoutMs: Number(process.env.MRN_LOCAL_HUB_AWS_DOWNLOAD_TIMEOUT_MS || "600000"),
			env: awsContext.env,
		});
		durationMs += result.durationMs || 0;
		if (result.code !== 0) {
			code = result.code;
			errors.push(result.stderr || result.stdout || `Failed to download ${key}`);
			break;
		}
		outputs.push(`Downloaded ${s3Uri(bucket, key)} -> ${dest}`);
	}
	const inspection = await inspectUpdraftSession(session);
	return {
		code,
		command: "aws-s3-download",
		args: [bucket, `${keys.length} file${keys.length === 1 ? "" : "s"}`, session, awsContext.credential ? `stored:${awsContext.credential.label}` : "aws-cli"],
		stdout: outputs.join("\n"),
		stderr: errors.join("\n"),
		durationMs,
		session: inspection,
	};
}

async function validateZipFile(zipFile) {
	const result = await runProcess("unzip", ["-Z1", zipFile], {
		timeoutMs: Number(process.env.MRN_LOCAL_HUB_ZIP_LIST_TIMEOUT_MS || "120000"),
		trackJob: false,
		maxOutputBytes: maxZipListOutputBytes,
	});
	if (result.code !== 0) return result;
	const unsafe = result.stdout
		.split(/\r?\n/)
		.map((line) => line.trim())
		.filter(Boolean)
		.find((entry) => {
			const normalized = entry.replace(/\\/g, "/");
			return normalized.startsWith("/") || normalized.split("/").includes("..");
		});
	if (unsafe) {
		return {
			...result,
			code: 1,
			stderr: `Unsafe zip entry blocked: ${unsafe}`,
		};
	}
	return result;
}

async function firstExistingDirectory(candidates) {
	for (const candidate of candidates.filter(Boolean)) {
		try {
			const stat = await fsp.stat(candidate);
			if (stat.isDirectory()) return candidate;
		} catch {
			// Try the next candidate.
		}
	}
	return "";
}

async function firstExistingWordPressDirectory(candidates) {
	for (const candidate of candidates.filter(Boolean)) {
		try {
			const stat = await fsp.stat(candidate);
			if (!stat.isDirectory()) continue;
			const hasContent = await pathExists(path.join(candidate, "wp-content"));
			const hasAdmin = await pathExists(path.join(candidate, "wp-admin"));
			const hasIncludes = await pathExists(path.join(candidate, "wp-includes"));
			if (hasContent && hasAdmin && hasIncludes) {
				return candidate;
			}
		} catch {
			// Try the next candidate.
		}
	}
	return "";
}

async function extractUpdraftZipToSite(site, file, component, index) {
	const validation = await validateZipFile(file.path);
	if (validation.code !== 0) {
		return { ...validation, command: "updraft-zip-validate", args: [file.name] };
	}
	const extractRoot = path.join(site.localRoot, "backups", `updraft-extract-${timestampSlug()}-${index}`);
	await fsp.mkdir(extractRoot, { recursive: true });
	const unzipResult = await runProcess("unzip", ["-q", file.path, "-d", extractRoot], {
		timeoutMs: Number(process.env.MRN_LOCAL_HUB_UPDRAFT_UNZIP_TIMEOUT_MS || "600000"),
	});
	if (unzipResult.code !== 0) return unzipResult;

	const wpContent = path.join(site.publicPath, "wp-content");
	let source = "";
	let dest = "";
	if (component === "plugins") {
		source = await firstExistingDirectory([path.join(extractRoot, "wp-content", "plugins"), path.join(extractRoot, "plugins")]) || extractRoot;
		dest = path.join(wpContent, "plugins");
	} else if (component === "themes") {
		source = await firstExistingDirectory([path.join(extractRoot, "wp-content", "themes"), path.join(extractRoot, "themes")]) || extractRoot;
		dest = path.join(wpContent, "themes");
	} else if (component === "uploads") {
		source = await firstExistingDirectory([path.join(extractRoot, "wp-content", "uploads"), path.join(extractRoot, "uploads")]) || extractRoot;
		dest = path.join(wpContent, "uploads");
	} else if (component === "mu-plugins") {
		source = await firstExistingDirectory([path.join(extractRoot, "wp-content", "mu-plugins"), path.join(extractRoot, "mu-plugins")]) || extractRoot;
		dest = path.join(wpContent, "mu-plugins");
	} else if (component === "others") {
		source = await firstExistingDirectory([path.join(extractRoot, "wp-content"), extractRoot]);
		dest = wpContent;
	} else if (component === "core") {
		source = await firstExistingDirectory([path.join(extractRoot, "wordpress"), extractRoot]);
		dest = site.publicPath;
	} else if (component === "site-archive") {
		source = await firstExistingWordPressDirectory([
			path.join(extractRoot, "www"),
			path.join(extractRoot, "public_html"),
			path.join(extractRoot, "htdocs"),
			extractRoot,
		]);
		dest = site.publicPath;
	}

	if (!source || !dest) {
		return {
			code: 1,
			command: "updraft-extract",
			args: [file.name, component],
			stdout: "",
			stderr: `Could not find extracted ${component} directory inside ${extractRoot}.`,
			durationMs: unzipResult.durationMs,
		};
	}
	await fsp.mkdir(dest, { recursive: true });
	const syncResult = await runProcess("rsync", ["-a", `${source.replace(/\/+$/, "")}/`, `${dest.replace(/\/+$/, "")}/`], {
		timeoutMs: Number(process.env.MRN_LOCAL_HUB_UPDRAFT_RSYNC_TIMEOUT_MS || "600000"),
	});
	return {
		...syncResult,
		command: "updraft-extract",
		args: [file.name, component],
		stdout: [
			`Restored ${component}: ${file.name}`,
			`Source: ${source}`,
			`Target: ${dest}`,
			syncResult.stdout,
		].filter(Boolean).join("\n"),
		stderr: [unzipResult.stderr, syncResult.stderr].filter(Boolean).join("\n"),
		durationMs: (unzipResult.durationMs || 0) + (syncResult.durationMs || 0),
	};
}

async function ensureWordPressCore(site) {
	if (await pathExists(path.join(site.publicPath, "wp-settings.php"))) {
		return {
			code: 0,
			command: "wp-core-download",
			args: [site.slug],
			stdout: "WordPress core already exists locally.",
			stderr: "",
			durationMs: 0,
		};
	}
	await fsp.mkdir(site.publicPath, { recursive: true });
	return runWpCli(site, [
		"core",
		"download",
		"--force",
		"--skip-content",
	], {
		cwd: site.localRoot,
		timeoutMs: Number(process.env.MRN_LOCAL_HUB_WP_CORE_DOWNLOAD_TIMEOUT_MS || "300000"),
	});
}

async function prepareUpdraftDbDump(site, file) {
	const dumpFile = path.join(site.localRoot, "dumps", `${timestampSlug()}-updraft.sql`);
	await fsp.mkdir(path.dirname(dumpFile), { recursive: true });
	if (/\.gz$/i.test(file.name)) {
		const result = await runProcess("/bin/sh", ["-lc", `gzip -cd ${shellQuote(file.path)} > ${shellQuote(dumpFile)}`], {
			timeoutMs: Number(process.env.MRN_LOCAL_HUB_UPDRAFT_GZIP_TIMEOUT_MS || "300000"),
		});
		return { ...result, dumpFile };
	}
	await fsp.copyFile(file.path, dumpFile);
	return {
		code: 0,
		command: "copy-db-dump",
		args: [file.name],
		stdout: `Prepared SQL dump: ${dumpFile}`,
		stderr: "",
		durationMs: 0,
		dumpFile,
	};
}

async function collectSqlFiles(rootDir) {
	const results = [];
	async function walk(dir) {
		const entries = await fsp.readdir(dir, { withFileTypes: true });
		for (const entry of entries) {
			const filePath = path.join(dir, entry.name);
			if (entry.isDirectory()) {
				await walk(filePath);
			} else if (/\.sql$/i.test(entry.name)) {
				results.push(filePath);
			}
		}
	}
	await walk(rootDir);
	return results.sort((a, b) => a.localeCompare(b));
}

async function firstSqlDumpDirectory(candidates) {
	for (const candidate of candidates.filter(Boolean)) {
		try {
			const stat = await fsp.stat(candidate);
			if (!stat.isDirectory()) continue;
			const files = await collectSqlFiles(candidate);
			if (files.length) {
				return candidate;
			}
		} catch {
			// Try the next candidate.
		}
	}
	return "";
}

async function prepareSqlDirectoryDump(site, sqlDir, sourceLabel) {
	const startedAt = Date.now();
	const files = await collectSqlFiles(sqlDir);
	const dumpFile = path.join(site.localRoot, "dumps", `${timestampSlug()}-archive.sql`);
	await fsp.mkdir(path.dirname(dumpFile), { recursive: true });
	if (!files.length) {
		return {
			code: 1,
			command: "prepare-sql-directory",
			args: [sourceLabel],
			stdout: "",
			stderr: `No .sql files found in ${sqlDir}.`,
			durationMs: Date.now() - startedAt,
			dumpFile,
		};
	}
	await fsp.writeFile(dumpFile, "SET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\n", "utf8");
	for (const file of files) {
		await fsp.appendFile(dumpFile, `\n-- Source: ${path.relative(sqlDir, file)}\n`, "utf8");
		await fsp.appendFile(dumpFile, await fsp.readFile(file));
		await fsp.appendFile(dumpFile, "\n", "utf8");
	}
	await fsp.appendFile(dumpFile, "SET UNIQUE_CHECKS=1;\nSET FOREIGN_KEY_CHECKS=1;\n", "utf8");
	return {
		code: 0,
		command: "prepare-sql-directory",
		args: [sourceLabel, `${files.length} table dump${files.length === 1 ? "" : "s"}`],
		stdout: `Prepared ${files.length} SQL table dump${files.length === 1 ? "" : "s"}: ${dumpFile}`,
		stderr: "",
		durationMs: Date.now() - startedAt,
		dumpFile,
	};
}

function originFromUrl(value) {
	try {
		const parsed = new URL(String(value || ""));
		if (!["http:", "https:"].includes(parsed.protocol) || !parsed.hostname) return "";
		return `${parsed.protocol}//${parsed.host}`.replace(/\/+$/, "");
	} catch {
		return "";
	}
}

function originHost(value) {
	try {
		return new URL(value).hostname.toLowerCase();
	} catch {
		return "";
	}
}

function sourceOriginVariants(origin) {
	const parsedOrigin = originFromUrl(origin);
	if (!parsedOrigin) return [];
	const parsed = new URL(parsedOrigin);
	const host = parsed.hostname.toLowerCase();
	const hosts = new Set([host]);
	if (host.startsWith("www.")) {
		hosts.add(host.replace(/^www\./, ""));
	} else {
		hosts.add(`www.${host}`);
	}
	const variants = [];
	for (const protocol of ["https:", "http:"]) {
		for (const variantHost of hosts) {
			const port = parsed.port ? `:${parsed.port}` : "";
			variants.push(`${protocol}//${variantHost}${port}`);
		}
	}
	return variants;
}

async function detectSourceOriginsFromDump(dumpFile, site) {
	const localOrigin = originFromUrl(site.localUrl);
	const liveOrigin = originFromUrl(site.liveUrl);
	const optionOrigins = new Set();
	const seenOrigins = new Map();
	let carry = "";
	await new Promise((resolve, reject) => {
		const stream = fs.createReadStream(dumpFile, { encoding: "utf8", highWaterMark: 1024 * 1024 });
		stream.on("data", (chunk) => {
			const text = `${carry}${chunk}`.replace(/\\\//g, "/");
			const urlPattern = /https?:\/\/[a-z0-9.-]+(?::\d+)?/gi;
			let match;
			while ((match = urlPattern.exec(text))) {
				const origin = originFromUrl(match[0]);
				if (origin) seenOrigins.set(origin, (seenOrigins.get(origin) || 0) + 1);
			}
			const optionPattern = /INSERT INTO `[^`]+_options`[\s\S]*?VALUES\s*\([^;]*?'(?:home|siteurl)'[^;]*?'(https?:\/\/[^']+)'/gi;
			while ((match = optionPattern.exec(text))) {
				const origin = originFromUrl(match[1]);
				if (origin) optionOrigins.add(origin);
			}
			carry = text.slice(-2048);
		});
		stream.on("error", reject);
		stream.on("end", resolve);
	});

	const primaryOrigins = new Set([...optionOrigins, liveOrigin].filter(Boolean));
	const primaryLabels = new Set(
		[...primaryOrigins]
			.map((origin) => originHost(origin).replace(/^www\./, "").split(".")[0])
			.filter(Boolean),
	);
	const detected = new Set();
	for (const origin of primaryOrigins) {
		for (const variant of sourceOriginVariants(origin)) detected.add(variant);
	}
	for (const origin of seenOrigins.keys()) {
		const host = originHost(origin);
		if (!host || host === "localhost" || host.endsWith(".localhost") || origin === localOrigin) continue;
		if (host.endsWith(".tempurl.host")) {
			const label = host.split(".")[0];
			if (!primaryLabels.size || primaryLabels.has(label)) {
				for (const variant of sourceOriginVariants(origin)) detected.add(variant);
			}
		}
	}
	return [...detected].filter((origin) => origin && origin !== localOrigin);
}

async function replaceImportedSourceUrls(site, dumpFile) {
	const localSite = siteWithRuntimeDefaults(site);
	const startedAt = Date.now();
	const sourceOrigins = await detectSourceOriginsFromDump(dumpFile, localSite);
	if (!sourceOrigins.length) {
		return {
			code: 0,
			command: "replace-imported-source-urls",
			args: [localSite.slug],
			stdout: "No source URLs detected in the database dump before local URL normalization.",
			stderr: "",
			durationMs: Date.now() - startedAt,
			sourceOrigins: [],
		};
	}
	const wpBaseArgs = ["--skip-plugins", "--skip-themes"];
	const replacements = [];
	for (const sourceOrigin of sourceOrigins) {
		const result = await runWpCli(
			localSite,
			[
				...wpBaseArgs,
				"search-replace",
				sourceOrigin,
				localSite.localUrl,
				"--all-tables-with-prefix",
				"--precise",
				"--recurse-objects",
				"--skip-columns=guid",
			],
			{ cwd: localSite.localRoot },
		);
		replacements.push({ sourceOrigin, result });
	}
	const failures = replacements.filter((item) => item.result.code !== 0);
	return {
		code: failures.length ? 1 : 0,
		command: "replace-imported-source-urls",
		args: [localSite.slug, localSite.localUrl],
		stdout: [
			`Detected source URL${sourceOrigins.length === 1 ? "" : "s"}: ${sourceOrigins.join(", ")}`,
			...replacements.map((item) => `Replaced ${item.sourceOrigin}: ${item.result.code === 0 ? "ok" : "failed"}`),
		].join("\n"),
		stderr: replacements.map((item) => item.result.stderr).filter(Boolean).join("\n"),
		durationMs: Date.now() - startedAt,
		sourceOrigins,
		replacements: replacements.map((item) => ({ oldUrl: item.sourceOrigin, code: item.result.code })),
		runtimeContext: firstRuntimeContext(...replacements.map((item) => item.result)),
	};
}

async function importPreparedDatabaseDump(site, prepResult, sourceLabel) {
	if (prepResult.code !== 0) return prepResult;
	const wpConfigResult = await writeLocalWpConfig(site);
	const importResult = await importDatabase(site, prepResult.dumpFile);
	let searchReplaceResult = null;
	if (importResult.code === 0 && site.liveUrl && site.localUrl) {
		searchReplaceResult = await runWpCli(site, [
			"search-replace",
			site.liveUrl,
			site.localUrl,
			"--all-tables",
			"--skip-columns=guid",
		], { cwd: site.localRoot });
	}
	const sourceUrlResult = importResult.code === 0
		&& (!searchReplaceResult || searchReplaceResult.code === 0)
		? await replaceImportedSourceUrls(site, prepResult.dumpFile)
		: null;
	const dbUrlResult = importResult.code === 0
		&& (!searchReplaceResult || searchReplaceResult.code === 0)
		&& (!sourceUrlResult || sourceUrlResult.code === 0)
		? await normalizeLocalDatabaseUrls(site)
		: null;
	const htaccessResult = importResult.code === 0 ? await ensureWordPressHtaccess(site) : null;
	const restartResult = htaccessResult && htaccessResult.updated ? await restartOpenLiteSpeedRuntime() : null;
	const smokeResult = importResult.code === 0
		&& (!searchReplaceResult || searchReplaceResult.code === 0)
		&& (!sourceUrlResult || sourceUrlResult.code === 0)
		&& (!dbUrlResult || dbUrlResult.code === 0)
		&& (!restartResult || restartResult.code === 0)
		? await runSmokeCheck(site)
		: null;
	const codeSyncResult = importResult.code === 0
		&& (!searchReplaceResult || searchReplaceResult.code === 0)
		&& (!sourceUrlResult || sourceUrlResult.code === 0)
		&& (!dbUrlResult || dbUrlResult.code === 0)
		? await checkDatabaseCodeSync(site)
		: null;
	const code = prepResult.code
		|| importResult.code
		|| (searchReplaceResult ? searchReplaceResult.code : 0)
		|| (sourceUrlResult ? sourceUrlResult.code : 0)
		|| (dbUrlResult ? dbUrlResult.code : 0)
		|| (restartResult ? restartResult.code : 0);
	return {
		code,
		command: "updraft-db-import",
		args: [site.slug, sourceLabel],
		stdout: [
			`Prepared database dump: ${prepResult.dumpFile}`,
			formatWpConfigResult(wpConfigResult),
			importResult.code === 0 ? `Imported ${prepResult.dumpFile} into ${site.dbName}.` : importResult.stdout,
			searchReplaceResult ? searchReplaceResult.stdout : "",
			sourceUrlResult ? sourceUrlResult.stdout : "",
			dbUrlResult ? dbUrlResult.stdout : "",
			htaccessResult ? formatHtaccessResult(htaccessResult) : "",
			restartResult ? restartResult.stdout : "",
			smokeResult ? smokeResult.stdout : "",
			codeSyncResult ? formatCodeSyncReport(codeSyncResult) : "",
		].filter(Boolean).join("\n"),
		stderr: [
			prepResult.stderr,
			importResult.stderr,
			searchReplaceResult ? searchReplaceResult.stderr : "",
			sourceUrlResult ? sourceUrlResult.stderr : "",
			dbUrlResult ? dbUrlResult.stderr : "",
			restartResult ? restartResult.stderr : "",
			smokeResult ? smokeResult.stderr : "",
		].filter(Boolean).join("\n"),
		durationMs: (prepResult.durationMs || 0)
			+ (importResult.durationMs || 0)
			+ (searchReplaceResult ? searchReplaceResult.durationMs : 0)
			+ (sourceUrlResult ? sourceUrlResult.durationMs : 0)
			+ (dbUrlResult ? dbUrlResult.durationMs : 0)
			+ (restartResult ? restartResult.durationMs : 0)
			+ (smokeResult ? smokeResult.durationMs : 0),
		runtimeContext: firstRuntimeContext(searchReplaceResult, sourceUrlResult, dbUrlResult, smokeResult),
		dumpFile: prepResult.dumpFile,
		wpConfig: wpConfigResult,
		sourceUrls: sourceUrlResult,
		dbUrls: dbUrlResult,
		htaccess: htaccessResult,
		restart: restartResult,
		smokeCode: smokeResult ? smokeResult.code : null,
		smoke: smokeResult ? smokeResult.smoke : null,
		codeSync: codeSyncResult,
	};
}

async function importUpdraftDatabase(site, dbFile) {
	return importPreparedDatabaseDump(site, await prepareUpdraftDbDump(site, dbFile), dbFile.name);
}

async function importZipArchiveDatabase(site, archiveFile) {
	const validation = await validateZipFile(archiveFile.path);
	if (validation.code !== 0) {
		return { ...validation, command: "zip-archive-db-validate", args: [archiveFile.name] };
	}
	const extractRoot = path.join(site.localRoot, "backups", `zip-archive-db-${timestampSlug()}`);
	await fsp.mkdir(extractRoot, { recursive: true });
	const unzipResult = await runProcess("unzip", ["-q", archiveFile.path, "-d", extractRoot], {
		timeoutMs: Number(process.env.MRN_LOCAL_HUB_UPDRAFT_UNZIP_TIMEOUT_MS || "600000"),
	});
	if (unzipResult.code !== 0) return unzipResult;
	const sqlDir = await firstSqlDumpDirectory([
		path.join(extractRoot, "sql"),
		path.join(extractRoot, "database"),
		path.join(extractRoot, "db"),
		path.join(extractRoot, "mysql"),
		extractRoot,
	]);
	if (!sqlDir) {
		return {
			code: 1,
			command: "zip-archive-db-import",
			args: [archiveFile.name],
			stdout: "",
			stderr: `No SQL directory found inside ${archiveFile.name}.`,
			durationMs: unzipResult.durationMs || 0,
		};
	}
	const prepResult = await prepareSqlDirectoryDump(site, sqlDir, archiveFile.name);
	prepResult.durationMs = (prepResult.durationMs || 0) + (unzipResult.durationMs || 0);
	prepResult.stdout = [unzipResult.stdout, prepResult.stdout].filter(Boolean).join("\n");
	prepResult.stderr = [unzipResult.stderr, prepResult.stderr].filter(Boolean).join("\n");
	return importPreparedDatabaseDump(site, prepResult, archiveFile.name);
}

async function createSiteFromUpdraftBackup(body = {}) {
	const session = await inspectUpdraftSession(body.session || "");
	const slug = normalizeSlug(body.slug || "");
	const siteRoot = assertInsideSitesRoot(path.join(sitesRoot, slug));
	if (await pathExists(manifestPathFor(siteRoot))) {
		throw httpError(409, `Site already exists: ${slug}`);
	}
	const restoreFiles = body.restoreFiles !== false;
	const restoreDb = body.restoreDb !== false;
	const includeUploads = body.includeUploads !== false;
	const title = sanitizeOptionalProviderText(body.title || slug, "Site title", 120) || slug;
	const liveUrl = sanitizeOptionalUrl(body.liveUrl || "");
	const site = sanitizeManifest({
		slug,
		title,
		provider: "backup",
		liveUrl,
		runtime: "local-vm-openlitespeed",
		runtimeStatus: "planned",
	});
	const fileComponents = new Set(["plugins", "themes", "mu-plugins", "others", "core", "site-archive"]);
	if (includeUploads) fileComponents.add("uploads");
	const zipFiles = session.files.filter((file) => restoreFiles && fileComponents.has(file.component));
	const dbFile = session.files.find((file) => file.component === "db");
	const archiveDbFile = session.files.find((file) => ["db-archive", "site-archive"].includes(file.component));
	if (restoreFiles && !zipFiles.length) {
		const hasDbOnlyBackup = Boolean(dbFile || archiveDbFile);
		throw httpError(
			400,
			hasDbOnlyBackup
				? "This backup only has database files selected. Turn off Restore files or choose a backup set with WordPress files."
				: "No supported WordPress backup zip files are selected for this restore.",
			{ components: session.components },
		);
	}
	if (restoreDb && !dbFile && !archiveDbFile) {
		throw httpError(400, "No database backup is selected for this restore.", { components: session.components });
	}

	const provisionResult = await provisionSite(site);
	const localSite = provisionResult.site || site;
	if (provisionResult.code !== 0) {
		return { ...provisionResult, command: "updraft-create-site", args: [slug], session };
	}

	const outputs = [provisionResult.stdout];
	const errors = [provisionResult.stderr];
	let code = 0;
	let durationMs = provisionResult.durationMs || 0;
	let gitSafety = null;
	let coreResult = null;
	let dbResult = null;
	let friendlyCertResult = provisionResult.friendlyCert || null;

	if (restoreFiles || restoreDb) {
		coreResult = await ensureWordPressCore(localSite);
		outputs.push(coreResult.stdout);
		errors.push(coreResult.stderr);
		durationMs += coreResult.durationMs || 0;
		code = code || coreResult.code;
		if (coreResult.code !== 0) {
			return {
				code,
				command: "updraft-create-site",
				args: [slug],
				stdout: outputs.filter(Boolean).join("\n"),
				stderr: errors.filter(Boolean).join("\n"),
				durationMs,
				runtimeContext: coreResult.runtimeContext || null,
				site: localSite,
				session,
				core: coreResult,
			};
		}
	}

	if (restoreFiles) {
		gitSafety = await gitSafetyReport(localSite.publicPath, {
			label: "Updraft restore",
			stopPath: localSite.publicPath,
		});
		if (gitSafety.dirty) {
			return {
				code: 1,
				command: "updraft-create-site",
				args: [slug],
				stdout: [
					...outputs,
					formatGitSafety(gitSafety),
					"Restore stopped before extracting files because the local Git repo has changes.",
				].filter(Boolean).join("\n"),
				stderr: errors.filter(Boolean).join("\n"),
				durationMs,
				site: localSite,
				session,
				gitSafety,
			};
		}
		let index = 0;
		for (const file of zipFiles) {
			index += 1;
			const result = await extractUpdraftZipToSite(localSite, file, file.component, index);
			outputs.push(result.stdout);
			errors.push(result.stderr);
			durationMs += result.durationMs || 0;
			code = code || result.code;
			if (result.code !== 0) break;
		}
		const wpConfigResult = await writeLocalWpConfig(localSite);
		outputs.push(formatWpConfigResult(wpConfigResult));
	}

	if (code === 0 && restoreDb && (dbFile || archiveDbFile)) {
		dbResult = dbFile
			? await importUpdraftDatabase(localSite, dbFile)
			: await importZipArchiveDatabase(localSite, archiveDbFile);
		outputs.push(dbResult.stdout);
		errors.push(dbResult.stderr);
		durationMs += dbResult.durationMs || 0;
		code = code || dbResult.code;
	}

	invalidateSiteDiskUsage(localSite);
	return {
		code,
		command: "updraft-create-site",
		args: [slug, session.session],
		stdout: outputs.filter(Boolean).join("\n"),
		stderr: errors.filter(Boolean).join("\n"),
		durationMs,
		runtimeContext: firstRuntimeContext(coreResult, dbResult),
		site: localSite,
		session,
		gitSafety,
		core: coreResult,
		friendlyCert: friendlyCertResult,
		friendlyUrls: friendlyCertResult ? friendlyCertResult.friendlyUrls : null,
		smokeCode: dbResult ? dbResult.smokeCode : null,
		smoke: dbResult ? dbResult.smoke : null,
		codeSync: dbResult ? dbResult.codeSync : null,
	};
}

async function runUpdraftBackupAction(body) {
	const action = String(body.action || "");
	switch (action) {
		case "inspect-session":
			return {
				code: 0,
				command: "updraft-inspect-session",
				args: [body.session || ""],
				stdout: "Updraft staging inspected.",
				stderr: "",
				durationMs: 0,
				session: await inspectUpdraftSession(body.session || ""),
			};
		case "aws-index-get": {
			const index = await getStoredAwsBackupIndex(body);
			const files = index?.files || [];
			const groups = groupUpdraftS3Files(files);
			return {
				code: 0,
				command: "aws-index-get",
				args: [body.bucket || "", body.prefix || ""],
				stdout: index
					? `Loaded stored S3 backup index from ${index.scannedAt}.`
					: "No stored S3 backup index exists for this source yet.",
				stderr: "",
				durationMs: 0,
				files,
				prefixes: index?.prefixes || [],
				groups,
				index: compactAwsBackupIndex(index),
			};
		}
		case "aws-list":
			return listAwsUpdraftBackups(body);
		case "aws-test-connection":
			return testAwsBackupConnection(body);
		case "aws-download-set":
			return downloadAwsUpdraftFiles(body);
		case "create-site-from-backup":
			return createSiteFromUpdraftBackup(body);
		default:
			throw httpError(400, `Unknown Updraft backup action: ${action}`);
	}
}

async function getSite(slugInput) {
	const slug = normalizeSlug(slugInput);
	const siteRoot = assertInsideSitesRoot(path.join(sitesRoot, slug));
	try {
		return await readManifest(siteRoot);
	} catch (error) {
		if (error.code === "ENOENT") {
			throw httpError(404, `Unknown site: ${slug}`);
		}
		throw error;
	}
}

async function listSites() {
	await fsp.mkdir(sitesRoot, { recursive: true });
	const entries = await fsp.readdir(sitesRoot, { withFileTypes: true });
	const sites = [];
	for (const entry of entries) {
		if (!entry.isDirectory()) {
			continue;
		}
		try {
			sites.push(await readManifest(path.join(sitesRoot, entry.name)));
		} catch (error) {
			if (error.code !== "ENOENT") {
				sites.push({
					slug: entry.name,
					title: entry.name,
					localRoot: path.join(sitesRoot, entry.name),
					runtimeStatus: "manifest-error",
					error: error.message,
				});
			}
		}
	}
	return sites.sort((a, b) => a.slug.localeCompare(b.slug));
}

function readBody(req) {
	return new Promise((resolve, reject) => {
		let body = "";
		req.on("data", (chunk) => {
			body += chunk;
			if (body.length > 1024 * 1024) {
				reject(httpError(413, "Request body is too large."));
				req.destroy();
			}
		});
		req.on("end", () => {
			if (!body) {
				resolve({});
				return;
			}
			try {
				resolve(JSON.parse(body));
			} catch (error) {
				reject(httpError(400, "Request body must be valid JSON."));
			}
		});
		req.on("error", reject);
	});
}

function runProcess(command, args, options = {}) {
	return new Promise((resolve) => {
		const startedAt = Date.now();
		let stdout = "";
		let stderr = "";
		const outputLimit = Number(options.maxOutputBytes || maxOutputBytes);
		const trackJob = options.trackJob !== false;
		const jobId = trackJob ? nextJobId++ : null;
		const child = childProcess.spawn(command, args, {
			cwd: options.cwd || repoRoot,
			env: { ...process.env, ...(options.env || {}) },
			stdio: options.input ? ["pipe", "pipe", "pipe"] : ["ignore", "pipe", "pipe"],
		});
		if (trackJob) {
			activeJobs.set(jobId, {
				id: jobId,
				command,
				args,
				cwd: options.cwd || repoRoot,
				siteSlug: options.siteSlug || "",
				startedAt: new Date(startedAt).toISOString(),
				pid: child.pid || null,
			});
		}

		const finishJob = () => {
			if (trackJob) {
				activeJobs.delete(jobId);
			}
		};

		const timeout = setTimeout(() => {
			child.kill("SIGTERM");
		}, options.timeoutMs || commandTimeoutMs);

		if (options.input) {
			child.stdin.end(options.input);
		}

		child.stdout.on("data", (chunk) => {
			stdout = appendLimited(stdout, chunk.toString(), outputLimit);
		});
		child.stderr.on("data", (chunk) => {
			stderr = appendLimited(stderr, chunk.toString(), outputLimit);
		});
		child.on("error", (error) => {
			clearTimeout(timeout);
			finishJob();
			resolve({
				command,
				args,
				code: 127,
				stdout,
				stderr: appendLimited(stderr, error.message, outputLimit),
				durationMs: Date.now() - startedAt,
			});
		});
		child.on("close", (code, signal) => {
			clearTimeout(timeout);
			finishJob();
			resolve({
				command,
				args,
				code,
				signal,
				stdout,
				stderr,
				durationMs: Date.now() - startedAt,
			});
		});
	});
}

function appendLimited(current, addition, limit = maxOutputBytes) {
	const combined = current + addition;
	if (Buffer.byteLength(combined) <= limit) {
		return combined;
	}
	return combined.slice(combined.length - limit);
}

async function commandExists(command) {
	const result = await runProcess("/bin/sh", ["-lc", `command -v ${command}`], { timeoutMs: 5000, trackJob: false });
	return {
		command,
		ok: result.code === 0,
		path: result.stdout.trim(),
	};
}

async function pathExists(filePath) {
	try {
		await fsp.access(filePath, fs.constants.F_OK);
		return true;
	} catch {
		return false;
	}
}

function isPathInside(parentPath, childPath) {
	const relative = path.relative(path.resolve(parentPath), path.resolve(childPath));
	return relative === "" || (!relative.startsWith("..") && !path.isAbsolute(relative));
}

async function preparePullDestinationDirectory(localDestPath, sitePublicPath, options = {}) {
	const replaceUnsafeSymlink = Boolean(options.replaceUnsafeSymlink);
	try {
		const stat = await fsp.lstat(localDestPath);
		if (stat.isSymbolicLink()) {
			const target = await fsp.readlink(localDestPath);
			const resolvedTarget = path.resolve(path.dirname(localDestPath), target);
			const targetExists = await pathExists(resolvedTarget);
			const targetIsLocal = targetExists && isPathInside(sitePublicPath, resolvedTarget);
			if (!targetIsLocal) {
				if (!replaceUnsafeSymlink) {
					return {
						changed: false,
						stdout: `Pull destination is a symlink to ${target}; it will be replaced with a local directory during the real pull.`,
					};
				}
				await fsp.rm(localDestPath, { force: true });
				await fsp.mkdir(localDestPath, { recursive: true });
				return {
					changed: true,
					stdout: `Replaced unsafe local symlink at ${displayPath(localDestPath)} with a directory before pulling.`,
				};
			}
			const targetStat = await fsp.stat(localDestPath);
			if (!targetStat.isDirectory()) {
				throw httpError(400, `Pull destination symlink does not point to a directory: ${displayPath(localDestPath)}`);
			}
			return { changed: false, stdout: "" };
		}
		if (!stat.isDirectory()) {
			throw httpError(400, `Pull destination exists but is not a directory: ${displayPath(localDestPath)}`);
		}
		return { changed: false, stdout: "" };
	} catch (error) {
		if (error.code !== "ENOENT") {
			throw error;
		}
		await fsp.mkdir(localDestPath, { recursive: true });
		return {
			changed: true,
			stdout: `Created pull destination directory: ${displayPath(localDestPath)}`,
		};
	}
}

async function healthReport() {
	const commands = await Promise.all(["node", "php", "wp", "ssh", "rsync", "mysql", "git", "aws", "unzip", "gzip", "redis-cli"].map(commandExists));
	const openLiteSpeedCandidates = [
		"/usr/local/lsws/bin/lswsctrl",
		"/opt/homebrew/opt/openlitespeed/bin/lswsctrl",
		"/opt/homebrew/bin/lswsctrl",
	];
	const openLiteSpeed = [];
	for (const candidate of openLiteSpeedCandidates) {
		openLiteSpeed.push({ path: candidate, ok: await pathExists(candidate) });
	}
	return {
		ok: true,
		repoRoot,
		sitesRoot,
		settings: appSettings,
		commands,
		openLiteSpeed,
		now: new Date().toISOString(),
	};
}

function stripHostPort(hostHeader) {
	const hostText = String(hostHeader || "").trim();
	if (!hostText) {
		return "";
	}
	if (hostText.startsWith("[") && hostText.includes("]")) {
		return hostText.slice(1, hostText.indexOf("]"));
	}
	return hostText.replace(/:\d+$/, "");
}

function friendlyHostWithPort(hostHeader, portNumber) {
	const hostname = stripHostPort(hostHeader) || "localhost";
	return portNumber === 443 ? hostname : `${hostname}:${portNumber}`;
}

function friendlyProxyTargetForHost(hostname) {
	if (hostname === friendlyHubHostname) {
		return {
			label: "MRN Local Hub",
			host: friendlyHubTargetHost,
			port: friendlyHubTargetPort,
		};
	}
	return {
		label: "OpenLiteSpeed",
		host: friendlyProxyTargetHost,
		port: friendlyProxyTargetPort,
	};
}

function createFriendlyHttpRedirectServer() {
	return http.createServer((req, res) => {
		const location = `https://${friendlyHostWithPort(req.headers.host, friendlyHttpsPort)}${req.url || "/"}`;
		res.writeHead(308, {
			location,
			"cache-control": "no-store",
		});
		res.end(`Redirecting to ${location}\n`);
	});
}

function proxyFriendlyRequest(req, res) {
	const originalHost = stripHostPort(req.headers.host) || `${normalizeSlug("site")}.localhost`;
	const target = friendlyProxyTargetForHost(originalHost);
	const headers = {
		...req.headers,
		host: originalHost,
		"x-forwarded-host": originalHost,
		"x-forwarded-port": String(friendlyHttpsPort),
		"x-forwarded-proto": "https",
		"x-real-ip": req.socket.remoteAddress || "127.0.0.1",
		connection: "close",
	};
	delete headers["proxy-connection"];

	const proxyReq = http.request(
		{
			hostname: target.host,
			port: target.port,
			method: req.method,
			path: req.url || "/",
			headers,
		},
		(proxyRes) => {
			res.writeHead(proxyRes.statusCode || 502, proxyRes.headers);
			proxyRes.pipe(res);
		},
	);

	proxyReq.on("error", (error) => {
		if (res.headersSent) {
			res.end();
			return;
		}
		res.writeHead(502, { "content-type": "text/plain; charset=utf-8", "cache-control": "no-store" });
		res.end(`MRN Local HTTPS proxy could not reach ${target.label} on ${target.host}:${target.port}.\n${error.message}\n`);
	});

	req.pipe(proxyReq);
}

function createFriendlyHttpsProxyServer(tlsOptions) {
	return https.createServer(tlsOptions, proxyFriendlyRequest);
}

async function friendlyCertificateHostnames() {
	const names = new Set(["localhost", "*.localhost", "127.0.0.1", "::1", friendlyHubHostname]);
	try {
		for (const site of await listSites()) {
			if (site.slug) {
				names.add(`${normalizeSlug(site.slug)}.localhost`);
			}
			const hostname = hostnameFromUrl(site.localUrl);
			if (hostname && (hostname === "localhost" || hostname.endsWith(".localhost"))) {
				names.add(hostname);
			}
		}
	} catch {
		// A cert with core localhost names is still useful when manifests are unavailable.
	}
	return Array.from(names);
}

async function friendlyCertificateCoversNames(certNames) {
	const result = await runProcess("openssl", ["x509", "-in", friendlyCertPath, "-noout", "-text"], {
		timeoutMs: 5000,
		trackJob: false,
	});
	if (result.code !== 0) {
		return false;
	}
	return certificateSanOutputCoversNames(result.stdout || "", certNames);
}

function certificateSanOutputCoversNames(output, certNames) {
	const dnsNames = certNames.filter((name) => !/^[0-9:.]+$/.test(name));
	return dnsNames.every((name) => output.includes(`DNS:${name}`));
}

async function liveFriendlyCertificateReport(certNames) {
	const serverName = certNames.find((name) => /^[a-z0-9-]+\.localhost$/i.test(name) && name !== friendlyHubHostname)
		|| friendlyHubHostname
		|| "localhost";
	const result = await runProcess("/bin/sh", [
		"-lc",
		`printf '' | openssl s_client -connect ${shellQuote(`127.0.0.1:${friendlyHttpsPort}`)} -servername ${shellQuote(serverName)} 2>/dev/null | openssl x509 -noout -text`,
	], {
		timeoutMs: 5000,
		trackJob: false,
	});
	if (result.code !== 0) {
		return {
			status: "unavailable",
			covers: null,
			serverName,
			message: "Live friendly HTTPS certificate could not be inspected.",
			error: result.stderr || result.stdout,
		};
	}
	const output = result.stdout || "";
	const covers = certificateSanOutputCoversNames(output, certNames);
	return {
		status: covers ? "ready" : "stale",
		covers,
		serverName,
		message: covers
			? "Live friendly HTTPS helper is serving the current certificate."
			: "Live friendly HTTPS helper is serving an old certificate.",
		subjectAltName: output.trim(),
	};
}

async function ensureFriendlyCertificate() {
	if (!friendlyUrlEnabled) {
		friendlyProxyState.cert.status = "disabled";
		friendlyProxyState.cert.message = "Friendly URLs are disabled by MRN_LOCAL_FRIENDLY_URLS=0.";
		return null;
	}
	const certExists = await pathExists(friendlyCertPath);
	const keyExists = await pathExists(friendlyKeyPath);
	const certNames = await friendlyCertificateHostnames();
	if (certExists && keyExists && await friendlyCertificateCoversNames(certNames)) {
		friendlyProxyState.cert.status = "ready";
		friendlyProxyState.cert.message = "mkcert certificate is ready.";
		return {
			cert: await fsp.readFile(friendlyCertPath),
			key: await fsp.readFile(friendlyKeyPath),
		};
	}
	if (certExists || keyExists) {
		await Promise.all([
			fsp.rm(friendlyCertPath, { force: true }),
			fsp.rm(friendlyKeyPath, { force: true }),
		]);
	}

	const mkcert = await commandExists("mkcert");
	if (!mkcert.ok) {
		friendlyProxyState.cert.status = "missing-mkcert";
		friendlyProxyState.cert.message = "mkcert is required to generate trusted local SSL certificates.";
		return null;
	}

	await fsp.mkdir(friendlyCertRoot, { recursive: true });
	const result = await runProcess(
		"mkcert",
		[
			"-cert-file",
			friendlyCertPath,
			"-key-file",
			friendlyKeyPath,
			...certNames,
		],
		{ timeoutMs: 60000, trackJob: false },
	);
	if (result.code !== 0) {
		friendlyProxyState.cert.status = "error";
		friendlyProxyState.cert.message = result.stderr || result.stdout || "mkcert failed to generate a localhost certificate.";
		return null;
	}

	friendlyProxyState.cert.status = "ready";
	friendlyProxyState.cert.message = `mkcert certificate generated for ${certNames.join(", ")}.`;
	return {
		cert: await fsp.readFile(friendlyCertPath),
		key: await fsp.readFile(friendlyKeyPath),
	};
}

function xmlEscape(value) {
	return String(value || "")
		.replace(/&/g, "&amp;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;")
		.replace(/"/g, "&quot;")
		.replace(/'/g, "&apos;");
}

function friendlyProxyPlist() {
	const nodePath = process.execPath || "/opt/homebrew/bin/node";
	const args = [
		nodePath,
		friendlyProxyHelperPath,
		"--cert",
		friendlyCertPath,
		"--key",
		friendlyKeyPath,
		"--target-host",
		friendlyProxyTargetHost,
		"--target-port",
		String(friendlyProxyTargetPort),
		"--hub-hostname",
		friendlyHubHostname,
		"--hub-target-host",
		friendlyHubTargetHost,
		"--hub-target-port",
		String(friendlyHubTargetPort),
		"--http-port",
		String(friendlyHttpPort),
		"--https-port",
		String(friendlyHttpsPort),
	];
	const argXml = args.map((item) => `        <string>${xmlEscape(item)}</string>`).join("\n");
	return `<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>${xmlEscape(friendlyProxyLabel)}</string>
    <key>ProgramArguments</key>
    <array>
${argXml}
    </array>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <true/>
    <key>StandardOutPath</key>
    <string>${xmlEscape(friendlyProxyStdoutPath)}</string>
    <key>StandardErrorPath</key>
    <string>${xmlEscape(friendlyProxyStderrPath)}</string>
</dict>
</plist>
`;
}

async function writeFriendlyProxyLaunchdFiles() {
	const tlsOptions = await ensureFriendlyCertificate();
	if (!tlsOptions) {
		throw httpError(400, friendlyProxyState.cert.message || "Friendly URL certificate is not ready.");
	}
	const carootResult = await runProcess("mkcert", ["-CAROOT"], { timeoutMs: 5000, trackJob: false });
	const rootCaPath = carootResult.code === 0 ? path.join(carootResult.stdout.trim(), "rootCA.pem") : "";
	await fsp.mkdir(runtimeWorkRoot, { recursive: true });
	await fsp.chmod(friendlyProxyHelperPath, 0o755);
	await fsp.writeFile(friendlyProxyGeneratedPlistPath, friendlyProxyPlist(), "utf8");
	const installScript = `#!/usr/bin/env bash
set -euo pipefail
${rootCaPath ? `if [ -f ${shellQuote(rootCaPath)} ]; then
  security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain ${shellQuote(rootCaPath)} >/dev/null 2>&1 || true
fi
` : ""}
install -d -m 0755 ${shellQuote(runtimeWorkRoot)}
cp ${shellQuote(friendlyProxyGeneratedPlistPath)} ${shellQuote(friendlyProxySystemPlistPath)}
chown root:wheel ${shellQuote(friendlyProxySystemPlistPath)}
chmod 0644 ${shellQuote(friendlyProxySystemPlistPath)}
launchctl bootout system ${shellQuote(friendlyProxySystemPlistPath)} >/dev/null 2>&1 || true
launchctl bootstrap system ${shellQuote(friendlyProxySystemPlistPath)}
launchctl enable system/${friendlyProxyLabel} >/dev/null 2>&1 || true
launchctl kickstart -k system/${friendlyProxyLabel} >/dev/null 2>&1 || true
`;
	await fsp.writeFile(friendlyProxyInstallScriptPath, installScript, "utf8");
	await fsp.chmod(friendlyProxyInstallScriptPath, 0o755);
	return {
		plistPath: friendlyProxyGeneratedPlistPath,
		systemPlistPath: friendlyProxySystemPlistPath,
		installScriptPath: friendlyProxyInstallScriptPath,
	};
}

async function friendlyProxyHelperHealth() {
	const result = await runProcess("curl", ["-kfsS", "--max-time", "2", "https://localhost/__mrn-local-health"], {
		timeoutMs: 5000,
		trackJob: false,
	});
	let payload = null;
	if (result.code === 0) {
		try {
			payload = JSON.parse(result.stdout);
		} catch {
			payload = null;
		}
	}
	return {
		ok: Boolean(payload?.ok && payload?.service === "mrn-local-friendly-proxy"),
		code: result.code,
		payload,
		error: result.code === 0 ? "" : result.stderr || result.stdout,
	};
}

async function friendlyProxyHelperReport() {
	const [health, launchctl, plistExists] = await Promise.all([
		friendlyProxyHelperHealth(),
		runProcess("launchctl", ["print", `system/${friendlyProxyLabel}`], { timeoutMs: 5000, trackJob: false }),
		pathExists(friendlyProxySystemPlistPath),
	]);
	const supportsHotReload = Boolean(health.payload && Object.prototype.hasOwnProperty.call(health.payload, "tlsLoadedAt"));
	return {
		label: friendlyProxyLabel,
		installed: plistExists,
		healthy: health.ok,
		supportsHotReload,
		health,
		launchctl: {
			code: launchctl.code,
			loaded: launchctl.code === 0,
			summary: (launchctl.stdout || launchctl.stderr || "").split("\n").slice(0, 12).join("\n"),
		},
		paths: {
			helper: friendlyProxyHelperPath,
			plist: friendlyProxySystemPlistPath,
			stdout: friendlyProxyStdoutPath,
			stderr: friendlyProxyStderrPath,
		},
	};
}

async function firefoxProfiles() {
	if (!(await pathExists(firefoxProfilesRoot))) {
		return [];
	}
	const entries = await fsp.readdir(firefoxProfilesRoot, { withFileTypes: true });
	const profiles = [];
	for (const entry of entries) {
		if (!entry.isDirectory()) continue;
		const profilePath = path.join(firefoxProfilesRoot, entry.name);
		if (await pathExists(path.join(profilePath, "cert9.db"))) {
			profiles.push({ name: entry.name, path: profilePath });
		}
	}
	return profiles;
}

async function mkcertRootCaPath() {
	const carootResult = await runProcess("mkcert", ["-CAROOT"], {
		timeoutMs: 5000,
		trackJob: false,
	});
	const rootCaPath = carootResult.code === 0 ? path.join(carootResult.stdout.trim(), "rootCA.pem") : "";
	return rootCaPath && await pathExists(rootCaPath) ? rootCaPath : "";
}

async function macosRootTrustReport(rootCaPath) {
	const security = await commandExists("security");
	if (!security.ok) {
		return {
			trusted: false,
			status: "missing-security",
			message: "macOS security command was not found.",
			security,
			rootCaPath,
		};
	}
	if (!rootCaPath) {
		return {
			trusted: false,
			status: "missing-root",
			message: "mkcert root CA was not found.",
			security,
			rootCaPath,
		};
	}
	const result = await runProcess("security", ["verify-cert", "-c", rootCaPath, "-p", "ssl"], {
		timeoutMs: 10000,
		trackJob: false,
	});
	return {
		trusted: result.code === 0,
		status: result.code === 0 ? "trusted" : "untrusted",
		message: result.code === 0
			? "macOS trusts the mkcert local CA for SSL."
			: "macOS does not trust the mkcert local CA for SSL yet.",
		security,
		rootCaPath,
		code: result.code,
		output: (result.stderr || result.stdout || "").trim(),
	};
}

async function trustMacosUserRoot(rootCaPath) {
	const before = await macosRootTrustReport(rootCaPath);
	if (before.trusted) {
		return {
			code: 0,
			changed: false,
			message: before.message,
			before,
			after: before,
		};
	}
	if (!before.security.ok || !rootCaPath) {
		return {
			code: 1,
			changed: false,
			message: before.message,
			before,
			after: before,
		};
	}
	const result = await runProcess("security", [
		"add-trusted-cert",
		"-r",
		"trustRoot",
		"-p",
		"ssl",
		"-p",
		"basic",
		"-k",
		macosLoginKeychainPath,
		rootCaPath,
	], {
		timeoutMs: 30000,
		trackJob: false,
	});
	const after = await macosRootTrustReport(rootCaPath);
	return {
		code: after.trusted ? 0 : result.code || 1,
		changed: after.trusted,
		message: after.trusted
			? "Trusted mkcert CA in the current user's login keychain."
			: (result.stderr || result.stdout || after.message || "Could not trust mkcert CA in the login keychain.").trim(),
		before,
		after,
	};
}

function regexEscape(value) {
	return String(value).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function firefoxPrefFromText(content, prefName) {
	const pattern = new RegExp(`user_pref\\("${regexEscape(prefName)}",\\s*(true|false|"[^"]*"|-?\\d+(?:\\.\\d+)?)\\s*\\);`, "g");
	let match;
	let value = null;
	while ((match = pattern.exec(content))) {
		const rawValue = match[1];
		if (rawValue === "true") {
			value = true;
		} else if (rawValue === "false") {
			value = false;
		} else if (rawValue.startsWith("\"")) {
			value = rawValue.slice(1, -1);
		} else {
			value = Number(rawValue);
		}
	}
	return value;
}

async function readFirefoxPref(profilePath, prefName) {
	const files = [
		{ name: "prefs.js", path: path.join(profilePath, "prefs.js") },
		{ name: "user.js", path: path.join(profilePath, "user.js") },
	];
	const values = {};
	for (const file of files) {
		try {
			values[file.name] = firefoxPrefFromText(await fsp.readFile(file.path, "utf8"), prefName);
		} catch (error) {
			if (error.code !== "ENOENT") {
				values[`${file.name}Error`] = error.message;
			}
			values[file.name] = null;
		}
	}
	return {
		prefsValue: values["prefs.js"],
		userValue: values["user.js"],
		effectiveValue: values["user.js"] ?? values["prefs.js"],
		prefsError: values["prefs.jsError"] || "",
		userError: values["user.jsError"] || "",
	};
}

async function writeFirefoxUserPref(profilePath, prefName, value) {
	const userJsPath = path.join(profilePath, "user.js");
	let content = "";
	try {
		content = await fsp.readFile(userJsPath, "utf8");
	} catch (error) {
		if (error.code !== "ENOENT") {
			throw error;
		}
	}
	const line = `user_pref("${prefName}", ${JSON.stringify(value)});`;
	const pattern = new RegExp(`^user_pref\\("${regexEscape(prefName)}",\\s*[^;]*\\);\\s*$`, "m");
	const nextContent = pattern.test(content)
		? content.replace(pattern, line)
		: `${content.replace(/\s*$/, "")}${content.trim() ? "\n" : ""}${line}\n`;
	if (nextContent !== content) {
		await fsp.writeFile(userJsPath, nextContent, "utf8");
	}
	return { path: userJsPath, changed: nextContent !== content };
}

async function firefoxRunningProfiles() {
	const result = await runProcess("ps", ["-axo", "pid=,etimes=,command="], {
		timeoutMs: 5000,
		trackJob: false,
	});
	if (result.code !== 0) {
		return [];
	}
	const profiles = new Map();
	const now = Date.now();
	for (const line of result.stdout.split(/\r?\n/)) {
		if (!line.includes("/Firefox.app/") || !line.includes(" -profile ")) {
			continue;
		}
		const processMatch = line.match(/^\s*(\d+)\s+(\d+)\s+(.*)$/);
		if (!processMatch) {
			continue;
		}
		const command = processMatch[3];
		const profileMatch = command.match(/ -profile (.+?)(?: org\.mozilla\.machname\.|$)/);
		if (profileMatch) {
			const profilePath = path.resolve(profileMatch[1].trim());
			const elapsedSeconds = Number(processMatch[2]);
			const current = profiles.get(profilePath);
			const startedAtMs = Number.isFinite(elapsedSeconds) ? now - elapsedSeconds * 1000 : 0;
			if (!current || startedAtMs < current.startedAtMs) {
				profiles.set(profilePath, {
					path: profilePath,
					pid: Number(processMatch[1]),
					startedAt: startedAtMs ? new Date(startedAtMs).toISOString() : "",
					startedAtMs,
				});
			}
		}
	}
	return Array.from(profiles.values());
}

async function fileMtimeMs(filePath) {
	try {
		return (await fsp.stat(filePath)).mtimeMs;
	} catch (error) {
		if (error.code !== "ENOENT") {
			throw error;
		}
		return 0;
	}
}

function profileNeedsFirefoxRestart(runningProfile, changedAtMs) {
	if (!runningProfile) {
		return false;
	}
	if (!runningProfile.startedAtMs || !changedAtMs) {
		return false;
	}
	return changedAtMs > runningProfile.startedAtMs + 1000;
}

async function firefoxTrustReport() {
	const [certutil, profiles, runningProfileReports, rootCaPath] = await Promise.all([
		commandExists("certutil"),
		firefoxProfiles(),
		firefoxRunningProfiles(),
		mkcertRootCaPath(),
	]);
	const runningProfiles = runningProfileReports.map((profile) => profile.path);
	const runningProfileMap = new Map(runningProfileReports.map((profile) => [profile.path, profile]));
	const macosRootTrust = await macosRootTrustReport(rootCaPath);
	if (!profiles.length) {
		return {
			detected: false,
			status: "not-detected",
			trusted: false,
			message: "Firefox profiles were not found.",
			certutil,
			profiles,
			runningProfiles,
			macosRootTrust,
		};
	}
	if (!certutil.ok) {
		return {
			detected: true,
			status: "missing-certutil",
			trusted: false,
			message: "Firefox needs NSS certutil before mkcert can trust the local CA there.",
			certutil,
			profiles,
			runningProfiles,
			macosRootTrust,
		};
	}
	const profileReports = await Promise.all(profiles.map(async (profile) => {
		const [result, enterpriseRoots] = await Promise.all([
			runProcess("certutil", ["-L", "-d", `sql:${profile.path}`], {
				timeoutMs: 5000,
				trackJob: false,
			}),
			readFirefoxPref(profile.path, firefoxEnterpriseRootsPref),
		]);
		const profilePath = path.resolve(profile.path);
		const runningProfile = runningProfileMap.get(profilePath);
		const [certDbMtimeMs, userJsMtimeMs] = await Promise.all([
			fileMtimeMs(path.join(profile.path, "cert9.db")),
			fileMtimeMs(path.join(profile.path, "user.js")),
		]);
		const trustChangedAtMs = Math.max(certDbMtimeMs, userJsMtimeMs);
		return {
			...profile,
			code: result.code,
			trusted: /mkcert/i.test(result.stdout),
			enterpriseRootsEnabled: enterpriseRoots.effectiveValue === true,
			enterpriseRootsActive: enterpriseRoots.prefsValue === true,
			enterpriseRoots,
			restartRequired: profileNeedsFirefoxRestart(runningProfile, trustChangedAtMs),
			running: Boolean(runningProfile),
			runningProfile,
			trustChangedAt: trustChangedAtMs ? new Date(trustChangedAtMs).toISOString() : "",
			error: result.code === 0 ? "" : (result.stderr || result.stdout).trim(),
		};
	}));
	const trustedCount = profileReports.filter((profile) => profile.trusted).length;
	const enterpriseCount = profileReports.filter((profile) => profile.enterpriseRootsEnabled).length;
	const restartRequired = profileReports.some((profile) => profile.restartRequired);
	const macosTrustNeeded = enterpriseCount > 0 && !macosRootTrust.trusted;
	const status = macosTrustNeeded
		? "macos-untrusted"
		: trustedCount === profileReports.length && restartRequired
		? "restart-required"
		: trustedCount === profileReports.length
			? "trusted"
			: trustedCount > 0 || enterpriseCount > 0
				? "partial"
				: "untrusted";
	return {
		detected: true,
		status,
		trusted: status === "trusted" || status === "restart-required",
		restartRequired,
		message: status === "trusted"
			? "Firefox trusts the mkcert local CA."
			: status === "macos-untrusted"
				? "Firefox has the mkcert CA, but macOS does not trust it for SSL yet."
			: status === "restart-required"
				? "Firefox trust is written. Fully quit and reopen Firefox to reload it."
			: status === "partial"
				? "Some Firefox profiles trust the mkcert local CA."
				: "Firefox does not trust the mkcert local CA yet.",
		certutil,
		profiles: profileReports,
		runningProfiles,
		macosRootTrust,
	};
}

async function trustFirefoxCertificate() {
	await ensureFriendlyCertificate();
	const before = await firefoxTrustReport();
	if (!before.detected) {
		return {
			code: 1,
			command: "runtime-firefox-trust",
			args: [firefoxProfilesRoot],
			stdout: "Firefox profiles were not found.",
			stderr: "",
			durationMs: 0,
		};
	}
	if (!before.certutil.ok) {
		return {
			code: 1,
			command: "runtime-firefox-trust",
			args: ["certutil"],
			stdout: [
				"Firefox uses its own certificate store.",
				"Install NSS first, then run Trust Firefox again:",
				"  brew install nss",
			].join("\n"),
			stderr: "",
			durationMs: 0,
		};
	}
	const rootCaPath = await mkcertRootCaPath();
	if (!rootCaPath || !(await pathExists(rootCaPath))) {
		return {
			code: 1,
			command: "runtime-firefox-trust",
			args: ["rootCA.pem"],
			stdout: "mkcert root CA was not found.",
			stderr: "",
			durationMs: 0,
		};
	}
	const macosTrustResult = await trustMacosUserRoot(rootCaPath);
	const importResults = [];
	for (const profile of before.profiles) {
		const deleteResult = await runProcess("certutil", ["-D", "-d", `sql:${profile.path}`, "-n", "mkcert local CA"], {
			timeoutMs: 10000,
			trackJob: false,
		});
		const importResult = await runProcess("certutil", ["-A", "-d", `sql:${profile.path}`, "-n", "mkcert local CA", "-t", "CT,C,C", "-a", "-i", rootCaPath], {
			timeoutMs: 10000,
			trackJob: false,
		});
		importResults.push({
			profile: profile.name,
			code: importResult.code,
			output: (importResult.stdout || importResult.stderr || deleteResult.stderr || "").trim(),
		});
		try {
			const enterpriseRoots = await writeFirefoxUserPref(profile.path, firefoxEnterpriseRootsPref, true);
			importResults[importResults.length - 1].enterpriseRoots = enterpriseRoots;
		} catch (error) {
			importResults[importResults.length - 1].enterpriseRootsError = error.message;
		}
	}
	const after = await firefoxTrustReport();
	const successStatuses = new Set(["trusted", "restart-required", "partial"]);
	return {
		code: successStatuses.has(after.status) ? 0 : 1,
		command: "runtime-firefox-trust",
		args: before.profiles.map((profile) => profile.name),
		stdout: [
			macosTrustResult.message,
			`Imported mkcert CA into ${importResults.filter((result) => result.code === 0).length}/${importResults.length} Firefox profile stores.`,
			`Enabled Firefox macOS root trust in ${importResults.filter((result) => result.enterpriseRoots && !result.enterpriseRootsError).length}/${importResults.length} profiles.`,
			...importResults.filter((result) => result.code !== 0).map((result) => `${result.profile}: ${result.output || "import failed"}`),
			...importResults.filter((result) => result.enterpriseRootsError).map((result) => `${result.profile}: ${result.enterpriseRootsError}`),
			after.message,
			after.trusted ? "Restart Firefox if the warning is still open." : "",
		].filter(Boolean).join("\n"),
		stderr: "",
		durationMs: 0,
		firefoxTrust: after,
	};
}

async function restartFriendlyProxyHelper() {
	const helper = await friendlyProxyHelperReport();
	if (!helper.installed) {
		return {
			code: 0,
			command: "restart-friendly-proxy-helper",
			args: [friendlyProxyLabel],
			stdout: "Friendly proxy helper is not installed.",
			stderr: "",
			durationMs: 0,
		};
	}
	return runProcess("launchctl", ["kickstart", "-k", `system/${friendlyProxyLabel}`], {
		timeoutMs: 30000,
	});
}

async function installNssForFirefoxTrust() {
	const brew = await commandExists("brew");
	if (!brew.ok) {
		return {
			code: 1,
			command: "runtime-install-nss",
			args: ["brew", "install", "nss"],
			stdout: "Homebrew is required to install NSS for Firefox certificate trust.",
			stderr: "",
			durationMs: 0,
		};
	}
	const result = await runProcess("brew", ["install", "nss"], {
		timeoutMs: 600000,
	});
	const certutil = await commandExists("certutil");
	return {
		...result,
		command: "runtime-install-nss",
		args: ["brew", "install", "nss"],
		code: certutil.ok ? 0 : result.code || 1,
		stdout: [
			result.stdout.trim(),
			result.stderr.trim(),
			certutil.ok ? "NSS certutil is ready. Run Trust Firefox next." : "NSS certutil was not found after install.",
		].filter(Boolean).join("\n"),
	};
}

async function installFriendlyProxyHelper() {
	const files = await writeFriendlyProxyLaunchdFiles();
	const appleScript = `do shell script ${JSON.stringify(`${friendlyProxyInstallScriptPath} 2>&1`)} with administrator privileges`;
	const result = await runProcess("osascript", ["-e", appleScript], {
		timeoutMs: Number(process.env.MRN_LOCAL_HUB_HELPER_INSTALL_TIMEOUT_MS || "300000"),
	});
	const helper = await friendlyProxyHelperReport();
	return {
		...result,
		command: "install-friendly-proxy-helper",
		args: [friendlyProxyLabel],
		stdout: [
			result.stdout,
			result.code !== 0 && helper.healthy ? "Installer returned a warning, but the friendly proxy helper is healthy." : "",
			helper.healthy ? "Friendly proxy helper is healthy." : "Friendly proxy helper is not healthy yet.",
			`Generated plist: ${files.plistPath}`,
			`System plist: ${files.systemPlistPath}`,
			helper.health.error ? `Health error: ${helper.health.error}` : "",
		].filter(Boolean).join("\n"),
		stderr: helper.healthy ? "" : result.stderr,
		helper,
		code: helper.healthy ? 0 : result.code || 1,
	};
}

function listenFriendlyServer(kind, createServer, portNumber, address) {
	return new Promise((resolve) => {
		const server = createServer();
		server.on("error", (error) => {
			resolve({ ok: false, address, port: portNumber, error: error.message, code: error.code || "" });
		});
		server.listen(portNumber, address, () => {
			friendlyProxyServers.push(server);
			resolve({ ok: true, address, port: portNumber, url: `${kind}://${address.includes(":") ? `[${address}]` : address}:${portNumber}` });
		});
	});
}

function summarizeFriendlyListenerResults(kind, results) {
	const state = friendlyProxyState[kind];
	state.listeners = results.filter((result) => result.ok);
	state.errors = results.filter((result) => !result.ok);
	state.status = state.listeners.length ? "running" : state.errors.length ? "blocked" : "stopped";
}

async function startFriendlyUrlProxy() {
	if (!friendlyUrlEnabled) {
		friendlyProxyState.http.status = "disabled";
		friendlyProxyState.https.status = "disabled";
		return friendlyProxyState;
	}
	if (friendlyProxyServers.length) {
		return friendlyProxyState;
	}
	friendlyProxyState.startedAt = new Date().toISOString();
	const tlsOptions = await ensureFriendlyCertificate();
	const addresses = ["127.0.0.1", "::1"];
	const httpResults = await Promise.all(
		addresses.map((address) => listenFriendlyServer("http", createFriendlyHttpRedirectServer, friendlyHttpPort, address)),
	);
	summarizeFriendlyListenerResults("http", httpResults);

	if (!tlsOptions) {
		friendlyProxyState.https.status = "cert-error";
		friendlyProxyState.https.listeners = [];
		friendlyProxyState.https.errors = [];
		return friendlyProxyState;
	}

	const httpsResults = await Promise.all(
		addresses.map((address) => listenFriendlyServer("https", () => createFriendlyHttpsProxyServer(tlsOptions), friendlyHttpsPort, address)),
	);
	summarizeFriendlyListenerResults("https", httpsResults);
	return friendlyProxyState;
}

async function portOwnerReport(portNumber) {
	const result = await runProcess("lsof", [`-iTCP:${portNumber}`, "-sTCP:LISTEN", "-n", "-P"], {
		timeoutMs: 5000,
		trackJob: false,
	});
	const lines = result.stdout.split("\n").filter(Boolean);
	return {
		port: portNumber,
		occupied: lines.length > 1,
		summary: lines.slice(1, 4).join("\n"),
	};
}

async function friendlyUrlReport() {
	const certNames = await friendlyCertificateHostnames();
	const [mkcert, httpOwner, httpsOwner, helper, firefox, liveCertificate] = await Promise.all([
		commandExists("mkcert"),
		portOwnerReport(friendlyHttpPort),
		portOwnerReport(friendlyHttpsPort),
		friendlyProxyHelperReport(),
		firefoxTrustReport(),
		liveFriendlyCertificateReport(certNames),
	]);
	const baseReady = friendlyProxyState.https.status === "running" || helper.healthy;
	const ready = baseReady && liveCertificate.covers !== false;
	const issues = [];
	if (!friendlyUrlEnabled) {
		issues.push("Friendly URLs are disabled.");
	}
	if (!mkcert.ok) {
		issues.push("mkcert is not installed.");
	}
	if (!ready && httpsOwner.occupied) {
		issues.push(`Port ${friendlyHttpsPort} is already in use.`);
	}
	if (!ready && friendlyProxyState.cert.status !== "ready") {
		issues.push(friendlyProxyState.cert.message || "Local SSL certificate is not ready.");
	}
	if (baseReady && liveCertificate.covers === false) {
		issues.push("Friendly HTTPS helper is serving an old certificate. Run Install HTTPS Helper to reload the system helper.");
	}
	if (helper.healthy && !helper.supportsHotReload) {
		issues.push("Friendly HTTPS helper is installed but needs one reinstall to auto-load new site certificates.");
	}
	if (!baseReady && (friendlyProxyState.https.errors || []).some((error) => error.code === "EACCES")) {
		issues.push("Install the macOS helper to bind ports 80/443 without running the hub as root.");
	}
	for (const error of friendlyProxyState.https.errors || []) {
		if (["EADDRINUSE", "EACCES"].includes(error.code)) {
			continue;
		}
		issues.push(`${error.address}:${error.port} ${error.error}`);
	}
	return {
		enabled: friendlyUrlEnabled,
		ready,
		pattern: "https://{slug}.localhost",
		hubUrl: `https://${friendlyHubHostname}`,
		target: `http://${friendlyProxyTargetHost}:${friendlyProxyTargetPort}`,
		hubTarget: `http://${friendlyHubTargetHost}:${friendlyHubTargetPort}`,
		cert: { ...friendlyProxyState.cert, mkcert },
		liveCertificate,
		browserTrust: { firefox },
		helper,
		http: { ...friendlyProxyState.http, owner: httpOwner },
		https: { ...friendlyProxyState.https, owner: httpsOwner },
		issues,
	};
}

async function waitForLiveFriendlyCertificate(certNames, timeoutMs = 5000) {
	const startedAt = Date.now();
	let report = await liveFriendlyCertificateReport(certNames);
	while (report.covers === false && Date.now() - startedAt < timeoutMs) {
		await delay(500);
		report = await liveFriendlyCertificateReport(certNames);
	}
	return report;
}

function siteUsesFriendlyHostname(site) {
	const hostname = hostnameFromUrl(site?.localUrl || "");
	return Boolean(hostname && (hostname === "localhost" || hostname.endsWith(".localhost")));
}

async function syncFriendlyCertificateAfterSiteChange(site, reason = "site-change") {
	const localSite = siteWithRuntimeDefaults(site);
	const startedAt = Date.now();
	if (!friendlyUrlEnabled || !siteUsesFriendlyHostname(localSite)) {
		return {
			code: 0,
			command: "friendly-cert-sync",
			args: [localSite.slug, reason],
			stdout: "Friendly HTTPS certificate sync skipped for this site URL.",
			stderr: "",
			durationMs: 0,
			skipped: true,
		};
	}

	await ensureFriendlyCertificate();
	const certNames = await friendlyCertificateHostnames();
	let liveCertificate = await waitForLiveFriendlyCertificate(certNames);
	let restartResult = null;
	if (liveCertificate.covers === false) {
		restartResult = await restartFriendlyProxyHelper();
		liveCertificate = await waitForLiveFriendlyCertificate(certNames);
	}
	const report = await friendlyUrlReport();
	const code = report.ready ? 0 : 1;
	return {
		code,
		command: "friendly-cert-sync",
		args: [localSite.slug, reason],
		stdout: [
			report.ready
				? `Friendly HTTPS certificate is ready for ${hostnameFromUrl(localSite.localUrl)}.`
				: `Friendly HTTPS certificate was regenerated for ${hostnameFromUrl(localSite.localUrl)}, but the live helper has not reloaded it yet.`,
			restartResult
				? restartResult.code === 0
					? "Friendly proxy helper reload requested."
					: "Run Install HTTPS Helper once so future site certs hot-reload automatically."
				: "",
			report.issues.length ? `Friendly HTTPS issues:\n${report.issues.map((issue) => `- ${issue}`).join("\n")}` : "",
		].filter(Boolean).join("\n"),
		stderr: restartResult && restartResult.code !== 0 ? restartResult.stderr : "",
		durationMs: Date.now() - startedAt,
		friendlyUrls: report,
		restart: restartResult,
		liveCertificate,
	};
}

function readCpuSample() {
	const totals = os.cpus().reduce((acc, cpu) => {
		const times = cpu.times || {};
		acc.idle += times.idle || 0;
		acc.total += Object.values(times).reduce((sum, value) => sum + value, 0);
		return acc;
	}, { idle: 0, total: 0 });
	return { ...totals, sampledAt: Date.now() };
}

function cpuPercentFromSamples(previous, next) {
	if (!previous) {
		return Math.min(100, Math.max(0, (os.loadavg()[0] / Math.max(1, os.cpus().length)) * 100));
	}
	const idle = next.idle - previous.idle;
	const total = next.total - previous.total;
	if (!total) {
		return 0;
	}
	return Math.min(100, Math.max(0, ((total - idle) / total) * 100));
}

function fallbackMemoryReport() {
	const totalBytes = os.totalmem();
	const freeBytes = os.freemem();
	const usedBytes = Math.max(0, totalBytes - freeBytes);
	return {
		totalBytes,
		usedBytes,
		freeBytes,
		availableBytes: freeBytes,
		percent: totalBytes ? (usedBytes / totalBytes) * 100 : 0,
		source: "os",
	};
}

function parseMemoryPressureReport(output) {
	const totalBytes = os.totalmem();
	const freePercentMatch = String(output || "").match(/System-wide memory free percentage:\s*(\d+(?:\.\d+)?)%/i);
	if (!freePercentMatch || !totalBytes) {
		return null;
	}
	const availablePercent = Math.max(0, Math.min(100, Number(freePercentMatch[1])));
	const availableBytes = Math.round(totalBytes * (availablePercent / 100));
	const usedBytes = Math.max(0, totalBytes - availableBytes);
	return {
		totalBytes,
		usedBytes,
		freeBytes: availableBytes,
		availableBytes,
		percent: (usedBytes / totalBytes) * 100,
		availablePercent,
		source: "macos-memory-pressure",
	};
}

async function memoryReport() {
	const now = Date.now();
	if (memoryCache && now - memoryCache.sampledAt < memoryCacheTtlMs) {
		return memoryCache.report;
	}
	if (process.platform !== "darwin") {
		const report = fallbackMemoryReport();
		memoryCache = { sampledAt: now, report };
		return report;
	}
	const result = await runProcess("memory_pressure", [], {
		timeoutMs: 3000,
		trackJob: false,
	});
	const report = result.code === 0
		? parseMemoryPressureReport(result.stdout) || fallbackMemoryReport()
		: fallbackMemoryReport();
	memoryCache = { sampledAt: Date.now(), report };
	return report;
}

async function diskUsageBytes(targetPath) {
	if (!targetPath || !(await pathExists(targetPath))) {
		return 0;
	}
	const result = await runProcess("du", ["-sk", targetPath], {
		timeoutMs: 10000,
		trackJob: false,
	});
	if (result.code !== 0) {
		return 0;
	}
	const kilobytes = Number.parseInt(String(result.stdout || "").trim().split(/\s+/)[0] || "0", 10);
	return Number.isFinite(kilobytes) ? kilobytes * 1024 : 0;
}

async function cachedDiskUsageBytes(targetPath) {
	const resolvedPath = targetPath ? path.resolve(targetPath) : "";
	if (!resolvedPath) {
		return 0;
	}
	const cached = diskUsageCache.get(resolvedPath);
	const now = Date.now();
	if (cached && now - cached.sampledAt < diskUsageCacheTtlMs) {
		return cached.bytes;
	}
	if (cached?.pending) {
		return cached.bytes || 0;
	}

	const pending = diskUsageBytes(resolvedPath)
		.then((bytes) => {
			diskUsageCache.set(resolvedPath, { bytes, sampledAt: Date.now(), pending: null });
			return bytes;
		})
		.catch(() => {
			diskUsageCache.set(resolvedPath, {
				bytes: cached?.bytes || 0,
				sampledAt: Date.now(),
				pending: null,
			});
			return cached?.bytes || 0;
		});
	diskUsageCache.set(resolvedPath, {
		bytes: cached?.bytes || 0,
		sampledAt: cached?.sampledAt || 0,
		pending,
	});

	if (cached) {
		return cached.bytes || 0;
	}
	return pending;
}

function emptyGitStatus(overrides = {}) {
	return {
		available: true,
		present: false,
		state: "none",
		dirty: false,
		branch: "",
		upstream: "",
		ahead: 0,
		behind: 0,
		totalChanges: 0,
		repoRoot: "",
		summary: "No Git repo detected.",
		checkedAt: new Date().toISOString(),
		...overrides,
	};
}

function parseGitAheadBehind(text) {
	const [ahead, behind] = String(text || "")
		.trim()
		.split(/\s+/)
		.map((value) => Number.parseInt(value, 10));
	return {
		ahead: Number.isFinite(ahead) ? ahead : 0,
		behind: Number.isFinite(behind) ? behind : 0,
	};
}

async function siteGitStatus(site, gitAvailable = true) {
	if (!gitAvailable) {
		return emptyGitStatus({
			available: false,
			state: "missing",
			summary: "Git is not installed.",
		});
	}

	const targetPath = site?.localRoot || site?.publicPath || "";
	const probePath = targetPath ? await nearestExistingPath(targetPath, sitesRoot) : "";
	if (!probePath) {
		return emptyGitStatus({
			summary: "Local site path does not exist yet.",
		});
	}

	const rootResult = await runProcess("git", ["-C", probePath, "rev-parse", "--show-toplevel"], {
		timeoutMs: 5000,
		trackJob: false,
	});
	if (rootResult.code !== 0) {
		return emptyGitStatus({
			checkedAt: new Date().toISOString(),
			summary: "No Git repo detected for this local site.",
		});
	}

	const gitRoot = rootResult.stdout.trim().split(/\r?\n/).pop();
	const [branchResult, upstreamResult, statusResult, hashResult] = await Promise.all([
		runProcess("git", ["-C", gitRoot, "branch", "--show-current"], { timeoutMs: 5000, trackJob: false }),
		runProcess("git", ["-C", gitRoot, "rev-parse", "--abbrev-ref", "--symbolic-full-name", "@{u}"], { timeoutMs: 5000, trackJob: false }),
		runProcess("git", ["-C", gitRoot, "status", "--short", "--untracked-files=all"], { timeoutMs: 10000, trackJob: false }),
		runProcess("git", ["-C", gitRoot, "rev-parse", "--short", "HEAD"], { timeoutMs: 5000, trackJob: false }),
	]);

	if (statusResult.code !== 0) {
		return {
			...emptyGitStatus({
				present: true,
				state: "error",
				repoRoot: gitRoot,
				summary: "Git status is unavailable for this site.",
			}),
			checkedAt: new Date().toISOString(),
		};
	}

	const branch = branchResult.stdout.trim() || (hashResult.stdout.trim() ? `detached ${hashResult.stdout.trim()}` : "detached");
	const upstream = upstreamResult.code === 0 ? upstreamResult.stdout.trim() : "";
	let aheadBehind = { ahead: 0, behind: 0 };
	if (upstream) {
		const aheadBehindResult = await runProcess("git", ["-C", gitRoot, "rev-list", "--left-right", "--count", "HEAD...@{u}"], {
			timeoutMs: 5000,
			trackJob: false,
		});
		if (aheadBehindResult.code === 0) {
			aheadBehind = parseGitAheadBehind(aheadBehindResult.stdout);
		}
	}

	const lines = gitStatusLines(statusResult.stdout);
	const dirty = lines.length > 0;
	const summary = dirty
		? `Git dirty: ${gitDisplayPath(gitRoot)} has ${lines.length} change${lines.length === 1 ? "" : "s"} on ${branch}.`
		: `Git clean: ${branch}${upstream ? ` tracking ${upstream}` : ""}.`;

	return {
		available: true,
		present: true,
		state: dirty ? "dirty" : "clean",
		dirty,
		branch,
		upstream,
		...aheadBehind,
		totalChanges: lines.length,
		repoRoot: gitRoot,
		summary,
		checkedAt: new Date().toISOString(),
	};
}

async function cachedSiteGitStatus(site, gitAvailable = true) {
	const targetPath = site?.localRoot || site?.publicPath || "";
	const resolvedPath = targetPath ? path.resolve(targetPath) : "";
	if (!resolvedPath) {
		return emptyGitStatus();
	}

	const cacheKey = `${resolvedPath}:${gitAvailable ? "git" : "missing"}`;
	const cached = gitStatusCache.get(cacheKey);
	const now = Date.now();
	if (cached && now - cached.sampledAt < gitStatusCacheTtlMs) {
		return cached.report;
	}
	if (cached?.pending) {
		return cached.report || emptyGitStatus();
	}

	const pending = siteGitStatus(site, gitAvailable)
		.then((report) => {
			gitStatusCache.set(cacheKey, { report, sampledAt: Date.now(), pending: null });
			return report;
		})
		.catch(() => {
			const report = cached?.report || emptyGitStatus({
				state: "error",
				summary: "Git status is unavailable for this site.",
			});
			gitStatusCache.set(cacheKey, { report, sampledAt: Date.now(), pending: null });
			return report;
		});
	gitStatusCache.set(cacheKey, {
		report: cached?.report || emptyGitStatus(),
		sampledAt: cached?.sampledAt || 0,
		pending,
	});

	return cached ? cached.report : pending;
}

function invalidateSiteDiskUsage(site) {
	if (site?.localRoot) {
		diskUsageCache.delete(path.resolve(site.localRoot));
		for (const key of gitStatusCache.keys()) {
			if (key.startsWith(`${path.resolve(site.localRoot)}:`)) {
				gitStatusCache.delete(key);
			}
		}
	}
}

function activeJobList() {
	return Array.from(activeJobs.values()).map((job) => ({
		...job,
		durationMs: Date.now() - Date.parse(job.startedAt),
	}));
}

function jobBelongsToSite(job, site) {
	if (job.siteSlug && job.siteSlug === site.slug) {
		return true;
	}
	const cwd = path.resolve(job.cwd || repoRoot);
	return [site.localRoot, site.publicPath]
		.filter(Boolean)
		.some((sitePath) => {
			const relative = path.relative(path.resolve(sitePath), cwd);
			return relative === "" || (!relative.startsWith("..") && !path.isAbsolute(relative));
		});
}

async function metricsReport() {
	const sites = await listSites();
	const jobs = activeJobList();
	const nextCpuSample = readCpuSample();
	const cpuPercent = cpuPercentFromSamples(cpuSample, nextCpuSample);
	cpuSample = nextCpuSample;
	const memory = await memoryReport();
	const git = await commandExists("git");

	const siteMetrics = await Promise.all(sites.map(async (site) => {
		const siteJobs = jobs.filter((job) => jobBelongsToSite(job, site));
		return {
			slug: site.slug,
			title: site.title || site.slug,
			provider: site.provider || "generic",
			localUrl: site.localUrl || "",
			localRoot: site.localRoot || "",
			publicPath: site.publicPath || "",
			runtimeStatus: site.runtimeStatus || "planned",
			running: site.runtimeStatus === "provisioned",
			diskBytes: await cachedDiskUsageBytes(site.localRoot),
			memoryBytes: null,
			memoryNote: "Shared runtime",
			jobs: siteJobs.length,
			git: await cachedSiteGitStatus(site, git.ok),
		};
	}));

	return {
		now: new Date().toISOString(),
		system: {
			hostname: os.hostname(),
			platform: os.platform(),
			arch: os.arch(),
			cpuCount: os.cpus().length,
			cpuPercent,
			loadAverage: os.loadavg(),
			memory,
			uptimeSeconds: os.uptime(),
		},
		limits: {
			sitesRoot,
			runtimeMemoryGiB: appSettings.runtimeMemoryGiB,
			runtimeDiskGiB: appSettings.runtimeDiskGiB,
			runtimeMemoryBytes: appSettings.runtimeMemoryGiB * 1024 * 1024 * 1024,
			runtimeDiskBytes: appSettings.runtimeDiskGiB * 1024 * 1024 * 1024,
		},
		jobs: {
			active: jobs.length,
			items: jobs,
		},
		sites: siteMetrics,
	};
}

function stripSshComment(line) {
	let output = "";
	let quote = "";
	let escaped = false;
	for (const char of String(line || "")) {
		if (escaped) {
			output += char;
			escaped = false;
			continue;
		}
		if (char === "\\") {
			output += char;
			escaped = true;
			continue;
		}
		if ((char === "'" || char === "\"") && !quote) {
			quote = char;
			output += char;
			continue;
		}
		if (char === quote) {
			quote = "";
			output += char;
			continue;
		}
		if (char === "#" && !quote) {
			break;
		}
		output += char;
	}
	return output.trim();
}

function splitSshConfigLine(line) {
	const stripped = stripSshComment(line);
	if (!stripped) {
		return [];
	}
	const matches = stripped.match(/(?:[^\s"']+|"[^"]*"|'[^']*')+/g) || [];
	return matches.map((token) => {
		if ((token.startsWith("\"") && token.endsWith("\"")) || (token.startsWith("'") && token.endsWith("'"))) {
			return token.slice(1, -1);
		}
		return token;
	});
}

function expandUserPath(input, baseDir = homeDir) {
	const value = String(input || "").trim();
	if (!value) {
		return "";
	}
	if (value === "~") {
		return homeDir;
	}
	if (value.startsWith("~/")) {
		return path.join(homeDir, value.slice(2));
	}
	if (path.isAbsolute(value)) {
		return value;
	}
	return path.resolve(baseDir, value);
}

function wildcardToRegex(pattern) {
	const escaped = String(pattern).replace(/[.+^${}()|[\]\\]/g, "\\$&");
	return new RegExp(`^${escaped.replace(/\*/g, ".*").replace(/\?/g, ".")}$`);
}

async function expandSshInclude(pattern, baseDir) {
	const expanded = expandUserPath(pattern, baseDir);
	if (!expanded || !/[*?]/.test(expanded)) {
		return (await pathExists(expanded)) ? [expanded] : [];
	}
	const dir = path.dirname(expanded);
	const namePattern = path.basename(expanded);
	if (/[*?]/.test(dir)) {
		return [];
	}
	try {
		const names = await fsp.readdir(dir);
		const matcher = wildcardToRegex(namePattern);
		const files = names
			.filter((name) => matcher.test(name))
			.map((name) => path.join(dir, name))
			.sort((a, b) => a.localeCompare(b));
		const existing = [];
		for (const file of files) {
			if (await pathExists(file)) {
				existing.push(file);
			}
		}
		return existing;
	} catch {
		return [];
	}
}

async function collectSshConfigFiles(filePath, seen = new Set()) {
	const resolved = path.resolve(filePath);
	if (seen.has(resolved) || !(await pathExists(resolved))) {
		return [];
	}
	seen.add(resolved);

	let text = "";
	try {
		text = await fsp.readFile(resolved, "utf8");
	} catch {
		return [];
	}

	const files = [resolved];
	const baseDir = path.dirname(resolved);
	for (const line of text.split("\n")) {
		const [directive, ...values] = splitSshConfigLine(line);
		if (String(directive || "").toLowerCase() !== "include") {
			continue;
		}
		for (const value of values) {
			const includes = await expandSshInclude(value, baseDir);
			for (const includeFile of includes) {
				files.push(...await collectSshConfigFiles(includeFile, seen));
			}
		}
	}
	return files;
}

function shouldExposeSshHostPattern(pattern) {
	return Boolean(pattern)
		&& !pattern.startsWith("!")
		&& !pattern.includes("*")
		&& !pattern.includes("?")
		&& !pattern.includes("[")
		&& !pattern.includes("]");
}

function ensureAlias(aliases, name, sourceFile, lineNumber) {
	if (!aliases.has(name)) {
		aliases.set(name, {
			name,
			hostName: "",
			user: "",
			port: "",
			identityFiles: [],
			identitiesOnly: "",
			identityAgent: "",
			proxyJump: "",
			proxyCommand: "",
			sourceFile,
			sourceFileDisplay: displayPath(sourceFile),
			lineNumber,
		});
	}
	return aliases.get(name);
}

async function formatIdentityFile(value, baseDir) {
	const resolved = expandUserPath(value, baseDir);
	return {
		path: resolved,
		displayPath: displayPath(resolved),
		exists: resolved ? await pathExists(resolved) : false,
	};
}

async function parseSshConfigAliases(files) {
	const aliases = new Map();
	let wildcardPatternCount = 0;
	for (const file of files) {
		let text = "";
		try {
			text = await fsp.readFile(file, "utf8");
		} catch {
			continue;
		}
		const baseDir = path.dirname(file);
		let currentAliases = [];
		const lines = text.split("\n");
		for (let index = 0; index < lines.length; index += 1) {
			const [directiveRaw, ...values] = splitSshConfigLine(lines[index]);
			const directive = String(directiveRaw || "").toLowerCase();
			if (!directive || !values.length) {
				continue;
			}
			if (directive === "host") {
				const expose = values.filter(shouldExposeSshHostPattern);
				wildcardPatternCount += values.length - expose.length;
				currentAliases = expose.map((name) => ensureAlias(aliases, name, file, index + 1));
				continue;
			}
			if (!currentAliases.length) {
				continue;
			}
			const value = values.join(" ");
			for (const alias of currentAliases) {
				if (directive === "hostname" && !alias.hostName) alias.hostName = value;
				if (directive === "user" && !alias.user) alias.user = value;
				if (directive === "port" && !alias.port) alias.port = value;
				if (directive === "identitiesonly" && !alias.identitiesOnly) alias.identitiesOnly = value;
				if (directive === "identityagent" && !alias.identityAgent) alias.identityAgent = value;
				if (directive === "proxyjump" && !alias.proxyJump) alias.proxyJump = value;
				if (directive === "proxycommand" && !alias.proxyCommand) alias.proxyCommand = value;
				if (directive === "identityfile") {
					alias.identityFiles.push(await formatIdentityFile(value, baseDir));
				}
			}
		}
	}
	return {
		aliases: [...aliases.values()].sort((a, b) => a.name.localeCompare(b.name)),
		wildcardPatternCount,
	};
}

function summarizeSshAgent(stdout, stderr, code) {
	const text = `${stdout || ""}${stderr || ""}`.trim();
	if (code === 0) {
		const keyCount = String(stdout || "").split("\n").filter(Boolean).length;
		return keyCount === 1 ? "1 key loaded" : `${keyCount} keys loaded`;
	}
	if (/agent has no identities/i.test(text)) {
		return "agent has no identities";
	}
	return text || "agent unavailable";
}

async function sshAliasReport() {
	const configRoot = homeDir ? path.join(homeDir, ".ssh", "config") : "";
	const files = configRoot ? await collectSshConfigFiles(configRoot) : [];
	const parsed = await parseSshConfigAliases(files);
	const agent = await runProcess("ssh-add", ["-l"], { timeoutMs: 5000 });
	return {
		configRoot: displayPath(configRoot),
		files: files.map((file) => ({ path: file, displayPath: displayPath(file) })),
		aliases: parsed.aliases.slice(0, 300),
		aliasCount: parsed.aliases.length,
		wildcardPatternCount: parsed.wildcardPatternCount,
		agent: {
			code: agent.code,
			ok: agent.code === 0,
			summary: summarizeSshAgent(agent.stdout, agent.stderr, agent.code),
		},
		now: new Date().toISOString(),
	};
}

function runtimePorts() {
	return [
		{ label: "Friendly HTTP", host: `localhost:${friendlyHttpPort}`, guest: `proxy -> sites:${friendlyProxyTargetPort}, hub:${friendlyHubTargetPort}` },
		{ label: "Friendly HTTPS", host: friendlyHttpsPort === 443 ? "https://*.localhost" : `localhost:${friendlyHttpsPort}`, guest: `sites -> ${friendlyProxyTargetPort}, ${friendlyHubHostname} -> ${friendlyHubTargetPort}` },
		{ label: "Local Hub", host: `https://${friendlyHubHostname}`, guest: String(friendlyHubTargetPort) },
		{ label: "HTTP", host: "127.0.0.1:8088", guest: "8088" },
		{ label: "OpenLiteSpeed Admin", host: "127.0.0.1:7080", guest: "7080" },
		{ label: "MariaDB", host: "127.0.0.1:3307", guest: "3306" },
	];
}

async function runtimeReport() {
	const [health, limactl, brew] = await Promise.all([
		healthReport(),
		commandExists("limactl"),
		commandExists("brew"),
	]);
	const friendlyUrls = await friendlyUrlReport();
	const homeDir = process.env.HOME || "";
	const limaConfigPath = homeDir ? path.join(homeDir, ".lima", runtimeInstanceName, "lima.yaml") : "";
	const scriptExists = await pathExists(runtimeBootstrapScript);
	const configExists = limaConfigPath ? await pathExists(limaConfigPath) : false;
	let limaList = null;
	let instanceState = "missing";

	if (limactl.ok) {
		limaList = await runProcess("limactl", ["list"], { timeoutMs: 10000 });
		if (limaList.stdout.includes(runtimeInstanceName)) {
			const line = limaList.stdout.split("\n").find((item) => item.includes(runtimeInstanceName)) || "";
			instanceState = /\bRunning\b/i.test(line) ? "running" : "created";
		}
	}

	const missing = [];
	if (!limactl.ok) missing.push("limactl");
	if (!brew.ok) missing.push("brew");

	return {
		adapter: "lima-openlitespeed",
		instanceName: runtimeInstanceName,
		status: instanceState,
		readyToBootstrap: limactl.ok,
		commands: { limactl, brew },
		core: health,
		paths: {
			workRoot: runtimeWorkRoot,
			bootstrapScript: runtimeBootstrapScript,
			limaConfig: limaConfigPath,
			sitesRoot,
		},
		settings: {
			sitesRoot,
			memory: `${appSettings.runtimeMemoryGiB}GiB`,
			disk: `${appSettings.runtimeDiskGiB}GiB`,
		},
		ports: runtimePorts(),
		files: {
			bootstrapScriptExists: scriptExists,
			limaConfigExists: configExists,
		},
		friendlyUrls,
		missing,
		limaList: limaList ? {
			code: limaList.code,
			stdout: limaList.stdout,
			stderr: limaList.stderr,
		} : null,
		now: new Date().toISOString(),
	};
}

function buildRuntimeBootstrapScript() {
	const sitesRootQuoted = shellQuote(sitesRoot);
	const instanceName = runtimeInstanceName;
	const phpInstallScript = buildPhpInstallBash(phpRuntimeVersions, { requiredVersion: defaultPhpVersion });
	const runtimeMemory = `${appSettings.runtimeMemoryGiB}GiB`;
	const runtimeDisk = `${appSettings.runtimeDiskGiB}GiB`;
	return `#!/usr/bin/env bash
set -euo pipefail

INSTANCE_NAME="${instanceName}"
SITES_ROOT="${sitesRoot}"
LIMA_DIR="\${HOME}/.lima/\${INSTANCE_NAME}"
LIMA_CONFIG="\${LIMA_DIR}/lima.yaml"
EXECUTE=0

usage() {
  cat <<'EOF'
Usage:
  bootstrap-mrn-openlitespeed.sh [--execute]

Creates a Lima Ubuntu VM definition for MRN's true-local OpenLiteSpeed runtime.
Without --execute, this script prints the plan only.

Host ports:
  http://127.0.0.1:8088  -> guest :8088
  https://127.0.0.1:7080 -> guest :7080 OpenLiteSpeed admin
  127.0.0.1:3307         -> guest :3306 MariaDB
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --execute)
      EXECUTE=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

cat <<EOF
MRN OpenLiteSpeed runtime plan

1. Ensure Lima is installed:
   brew install lima

2. Create/update:
   \${LIMA_CONFIG}

3. Start the VM:
   limactl start "\${LIMA_CONFIG}"

4. Inspect it:
   limactl shell \${INSTANCE_NAME} -- /usr/local/lsws/bin/lswsctrl status

Run with --execute to write the Lima config and start the VM.
EOF

if [[ "\${EXECUTE}" != "1" ]]; then
  exit 0
fi

command -v limactl >/dev/null 2>&1 || {
  echo "limactl is missing. Install it with: brew install lima" >&2
  exit 1
}

mkdir -p "\${LIMA_DIR}"

cat > "\${LIMA_CONFIG}" <<YAML
vmType: vz
cpus: 2
memory: ${runtimeMemory}
disk: ${runtimeDisk}
images:
  - location: "https://cloud-images.ubuntu.com/releases/24.04/release/ubuntu-24.04-server-cloudimg-arm64.img"
    arch: "aarch64"
  - location: "https://cloud-images.ubuntu.com/releases/24.04/release/ubuntu-24.04-server-cloudimg-amd64.img"
    arch: "x86_64"
mounts:
  - location: ${sitesRootQuoted}
    mountPoint: "/srv/mrn-sites"
    writable: true
portForwards:
  - guestPort: 8088
    hostPort: 8088
  - guestPort: 7080
    hostPort: 7080
  - guestPort: 3306
    hostPort: 3307
containerd:
  system: false
  user: false
provision:
  - mode: system
    script: |
      #!/usr/bin/env bash
      set -euxo pipefail
      export DEBIAN_FRONTEND=noninteractive
      apt-get update
      apt-get install -y ca-certificates curl gnupg lsb-release mariadb-server redis-server unzip wget
      wget -O - https://repo.litespeed.sh | bash
      apt-get update
      apt-get install -y openlitespeed
${phpInstallScript.split("\n").map((line) => `      ${line}`).join("\n")}
${buildRuntimeWpCliInstallBash().split("\n").map((line) => `      ${line}`).join("\n")}
      systemctl enable mariadb redis-server lsws || true
      systemctl restart mariadb redis-server || true
      /usr/local/lsws/bin/lswsctrl restart
YAML

limactl start --name="\${INSTANCE_NAME}" "\${LIMA_CONFIG}"
`;
}

async function ensureRuntimeBootstrapScript() {
	if (!(await pathExists(runtimeBootstrapScript))) {
		const script = buildRuntimeBootstrapScript();
		await fsp.mkdir(runtimeWorkRoot, { recursive: true });
		await fsp.writeFile(runtimeBootstrapScript, script, "utf8");
		await fsp.chmod(runtimeBootstrapScript, 0o755);
	}
	return runtimeBootstrapScript;
}

async function runtimeServiceCheck() {
	const report = await runtimeReport();
	if (!report.commands.limactl.ok) {
		throw httpError(400, "limactl is missing. Install Lima before checking runtime services.");
	}
	if (report.status === "missing") {
		return {
			code: 1,
			command: "runtime-check",
			args: [runtimeInstanceName],
			stdout: "Runtime instance is missing. Run Bootstrap Runtime first.",
			stderr: "",
			durationMs: 0,
		};
	}
	return runProcess(
		"limactl",
		[
			"shell",
			runtimeInstanceName,
			"--",
			"bash",
			"-lc",
			[
				"set -u",
				"printf 'OpenLiteSpeed: '; sudo /usr/local/lsws/bin/lswsctrl status 2>&1 || true",
				"printf '\\nMariaDB: '; systemctl is-active mariadb 2>&1 || true",
				"printf '\\nRedis: '; systemctl is-active redis-server 2>&1 || true",
				"printf '\\nSites mount: '; if [ -d /srv/mrn-sites ]; then echo /srv/mrn-sites; else echo missing; fi",
				`printf '\\nWP-CLI: '; if command -v wp >/dev/null 2>&1; then MRN_WP_CLI_PHP=${shellQuote(phpBinaryPath(defaultPhpVersion))} wp --version; else echo missing; fi`,
				...phpRuntimeVersions.map((version) => {
					const binaryPath = phpBinaryPath(version);
					return `printf '\\n${phpVersionLabel(version)}: '; if [ -x ${shellQuote(binaryPath)} ]; then ${shellQuote(binaryPath)} -v | head -n 1; else echo missing; fi`;
				}),
			].join("; "),
		],
		{ timeoutMs: 60000 },
	);
}

async function runtimeRepairInstall() {
	const report = await runtimeReport();
	if (!report.commands.limactl.ok) {
		throw httpError(400, "limactl is missing. Install Lima before repairing runtime services.");
	}
	if (report.status === "missing") {
		throw httpError(400, "Runtime instance is missing. Run Bootstrap Runtime first.");
	}
	return runProcess(
		"limactl",
		[
			"shell",
			runtimeInstanceName,
			"--",
			"bash",
			"-lc",
			[
				"set -euxo pipefail",
				"export DEBIAN_FRONTEND=noninteractive",
				"sudo apt-get update",
				"sudo apt-get install -y ca-certificates curl openlitespeed",
				buildPhpInstallBash(phpRuntimeVersions, { sudo: true, requiredVersion: defaultPhpVersion }),
				buildRuntimeWpCliInstallBash({ sudo: true }),
				"sudo systemctl enable mariadb redis-server lsws || true",
				"sudo systemctl restart mariadb redis-server || true",
				"sudo /usr/local/lsws/bin/lswsctrl restart",
			].join("\n"),
		],
		{ timeoutMs: Number(process.env.MRN_LOCAL_HUB_REPAIR_TIMEOUT_MS || "900000") },
	);
}

async function runRuntimeAction(body) {
	const action = String(body.action || "");
	switch (action) {
		case "runtime-status": {
			const report = await runtimeReport();
			return {
				code: 0,
				command: "runtime-status",
				args: [runtimeInstanceName],
				stdout: JSON.stringify(report, null, 2),
				stderr: "",
				durationMs: 0,
			};
		}
		case "runtime-plan": {
			const report = await runtimeReport();
			const script = buildRuntimeBootstrapScript();
			await fsp.mkdir(runtimeWorkRoot, { recursive: true });
			await fsp.writeFile(runtimeBootstrapScript, script, "utf8");
			await fsp.chmod(runtimeBootstrapScript, 0o755);
			const lines = [
				`Runtime adapter: ${report.adapter}`,
				`Instance: ${report.instanceName}`,
				`Status: ${report.status}`,
				`Bootstrap script: ${runtimeBootstrapScript}`,
				"",
				"Next commands:",
				report.commands.limactl.ok ? "" : "  brew install lima",
				`  ${runtimeBootstrapScript}`,
				`  ${runtimeBootstrapScript} --execute`,
				"",
				"Generated script is non-destructive unless run with --execute.",
			].filter((line) => line !== "");
			return {
				code: 0,
				command: "runtime-plan",
				args: [runtimeInstanceName],
				stdout: lines.join("\n"),
				stderr: "",
				durationMs: 0,
				scriptPath: runtimeBootstrapScript,
			};
		}
		case "runtime-bootstrap": {
			await ensureRuntimeBootstrapScript();
			return runProcess(runtimeBootstrapScript, ["--execute"], {
				timeoutMs: Number(process.env.MRN_LOCAL_HUB_BOOTSTRAP_TIMEOUT_MS || "1800000"),
			});
		}
		case "runtime-check":
			return runtimeServiceCheck();
		case "runtime-repair":
			return runtimeRepairInstall();
		case "runtime-friendly-start": {
			await startFriendlyUrlProxy();
			const report = await friendlyUrlReport();
			return {
				code: report.ready ? 0 : 1,
				command: "runtime-friendly-start",
				args: [`http:${friendlyHttpPort}`, `https:${friendlyHttpsPort}`],
				stdout: [
					report.ready ? "Friendly HTTPS URLs are active." : "Friendly HTTPS URLs are not active yet.",
					`Pattern: ${report.pattern}`,
					`Target: ${report.target}`,
					report.issues.length ? `Issues:\n${report.issues.map((issue) => `- ${issue}`).join("\n")}` : "",
				].filter(Boolean).join("\n"),
				stderr: "",
				durationMs: 0,
				friendlyUrls: report,
			};
		}
		case "runtime-friendly-cert": {
			try {
				await fsp.rm(friendlyCertPath, { force: true });
				await fsp.rm(friendlyKeyPath, { force: true });
			} catch {
				// Best effort; ensureFriendlyCertificate will report the real state.
			}
			await ensureFriendlyCertificate();
			const restartResult = await restartFriendlyProxyHelper();
			const report = await friendlyUrlReport();
			const liveCertificateReady = report.liveCertificate?.covers !== false;
			const helperReloaded = restartResult.code === 0 || /not installed/i.test(restartResult.stdout || "");
			return {
				code: report.cert.status === "ready" && liveCertificateReady && helperReloaded ? 0 : 1,
				command: "runtime-friendly-cert",
				args: [friendlyCertPath],
				stdout: [
					report.cert.message || "Friendly URL certificate check complete.",
					restartResult.code === 0 ? "Friendly proxy helper reloaded." : "Friendly proxy helper reload failed.",
					liveCertificateReady ? "" : "Live HTTPS helper is still serving the old certificate. Run Install HTTPS Helper to reload the system helper.",
					restartResult.stdout || "",
				].filter(Boolean).join("\n"),
				stderr: restartResult.code === 0 ? "" : restartResult.stderr || report.liveCertificate?.message || "",
				durationMs: restartResult.durationMs || 0,
				friendlyUrls: report,
			};
		}
		case "runtime-friendly-install-helper": {
			const result = await installFriendlyProxyHelper();
			const report = await friendlyUrlReport();
			return {
				...result,
				friendlyUrls: report,
			};
		}
		case "runtime-firefox-trust": {
			const result = await trustFirefoxCertificate();
			const report = await friendlyUrlReport();
			return {
				...result,
				friendlyUrls: report,
			};
		}
		case "runtime-install-nss": {
			const result = await installNssForFirefoxTrust();
			const report = await friendlyUrlReport();
			return {
				...result,
				friendlyUrls: report,
			};
		}
		case "runtime-open-http":
			return runProcess("open", ["http://127.0.0.1:8088"]);
		case "runtime-open-admin":
			return runProcess("open", ["https://127.0.0.1:7080"]);
		case "runtime-open-script": {
			if (!(await pathExists(runtimeBootstrapScript))) {
				throw httpError(404, "Generate the runtime plan before opening the bootstrap script.");
			}
			return runProcess("open", [runtimeBootstrapScript]);
		}
		default:
			throw httpError(400, `Unknown runtime action: ${action}`);
	}
}

function parseKeyValueOutput(output) {
	const parsed = {};
	for (const line of String(output || "").split("\n")) {
		const index = line.indexOf("=");
		if (index <= 0) {
			continue;
		}
		const key = line.slice(0, index).trim();
		const value = line.slice(index + 1).trim();
		if (key) {
			parsed[key] = value;
		}
	}
	return parsed;
}

function hostnameFromUrl(value) {
	const raw = String(value || "").trim();
	if (!raw) {
		return "";
	}
	try {
		return new URL(raw).hostname.replace(/^www\./, "");
	} catch {
		return raw.replace(/^https?:\/\//, "").split("/")[0].replace(/^www\./, "");
	}
}

function urlLikeHostname(value) {
	const hostname = hostnameFromUrl(value).toLowerCase();
	return hostname.includes(".") ? hostname : "";
}

function liveUrlFromIdentifier(value) {
	const hostname = urlLikeHostname(value);
	return hostname ? sanitizeOptionalUrl(value) : "";
}

function slugFromSiteIdentifier(value) {
	const raw = String(value || "").trim();
	if (!raw) {
		return "";
	}
	let candidate = urlLikeHostname(raw) || raw;
	candidate = candidate
		.toLowerCase()
		.replace(/^www\./, "")
		.replace(/\.mrndev\.io$/i, "")
		.replace(/\.wpenginepowered\.com$/i, "");
	return normalizeSlug(candidate);
}

function importIdentityFromInputs(body, providerInput) {
	const provider = normalizeProvider(providerInput || body.provider);
	const slugInput = String(body.slug || "").trim();
	const liveUrlInput = String(body.liveUrl || "").trim();
	const liveUrl = liveUrlInput || liveUrlFromIdentifier(slugInput);
	let slug = "";
	if (provider === "mrndev") {
		const hostname = mrnDevHostnameFromInputs({ ...body, liveUrl });
		if (hostname) {
			slug = slugFromMrnDevHostname(sanitizeMrnDevHostname(hostname));
		}
	}
	if (!slug) {
		slug = slugInput ? slugFromSiteIdentifier(slugInput) : slugFromSiteIdentifier(liveUrl);
	}
	return { provider, slug, liveUrl };
}

function mrnDevHostnameFromInputs({ liveUrl, remotePath, slug }) {
	const liveHost = hostnameFromUrl(liveUrl || "");
	if (liveHost.endsWith(".mrndev.io")) {
		return liveHost;
	}

	const pathMatch = String(remotePath || "").match(/\/([^/]+\.mrndev\.io)\/?$/i);
	if (pathMatch) {
		return pathMatch[1].toLowerCase();
	}

	const rawSlug = String(slug || "").trim().toLowerCase();
	const slugHost = hostnameFromUrl(rawSlug);
	if (slugHost.endsWith(".mrndev.io")) {
		return slugHost;
	}
	if (rawSlug.endsWith(".mrndev.io")) {
		return rawSlug.replace(/^https?:\/\//, "").split("/")[0];
	}
	if (rawSlug && /^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/i.test(rawSlug)) {
		return `${rawSlug}.mrndev.io`;
	}

	return "";
}

function sanitizeMrnDevHostname(value) {
	const hostname = String(value || "").trim().toLowerCase();
	if (!/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.mrndev\.io$/.test(hostname)) {
		throw httpError(400, "MRN Dev requires a safe *.mrndev.io hostname.");
	}
	return hostname;
}

function slugFromMrnDevHostname(hostname) {
	return normalizeSlug(String(hostname || "").replace(/\.mrndev\.io$/i, ""));
}

function wpEngineEnvironment(remoteSsh, liveUrl) {
	const ssh = String(remoteSsh || "").trim();
	const user = ssh.includes("@") ? ssh.split("@")[0] : "";
	const host = ssh.includes("@") ? ssh.split("@").slice(1).join("@") : ssh;
	const hostMatch = host.match(/^([A-Za-z0-9-]+)\.ssh\.wpengine\.net$/i);
	if (user && user !== "git") {
		return user;
	}
	if (hostMatch) {
		return hostMatch[1];
	}
	return hostnameFromUrl(liveUrl).split(".")[0] || "";
}

function addRemotePathCandidate(candidates, value) {
	const raw = String(value || "").trim();
	if (!raw) {
		return;
	}
	try {
		candidates.push(sanitizeRemotePath(raw));
	} catch {
		// Ignore guessed paths that do not pass the remote path safety contract.
	}
}

async function resolveMrnDevTarget(body) {
	const hostname = sanitizeMrnDevHostname(mrnDevHostnameFromInputs(body));
	const discoverySshHost = sanitizeRemoteSsh(body.discoverySshHost || "mrndev");
const remoteScript = `set -euo pipefail
site_hostname="$1"
find /home -mindepth 3 -maxdepth 3 -type d -path "/home/*/htdocs/\${site_hostname}" 2>/dev/null | sort || true
`;
	const result = await runProcess(
		"ssh",
		[
			"-o",
			"BatchMode=yes",
			"-o",
			"ConnectTimeout=10",
			discoverySshHost,
			"bash",
			"-s",
			"--",
			hostname,
		],
		{
			input: remoteScript,
			timeoutMs: Number(process.env.MRN_LOCAL_HUB_MRNDEV_RESOLVE_TIMEOUT_MS || "45000"),
		},
	);

	const matches = result.stdout.split("\n").map((line) => line.trim()).filter(Boolean);
	if (result.code !== 0) {
		return {
			result: {
				...result,
				command: "mrndev-resolve",
				args: [hostname, discoverySshHost],
				stdout: result.stdout,
			},
			context: null,
		};
	}
	if (!matches.length) {
		return {
			result: {
				...result,
				code: 1,
				command: "mrndev-resolve",
				args: [hostname, discoverySshHost],
				stdout: `No MRN Dev site root found for ${hostname} via ${discoverySshHost}.`,
			},
			context: null,
		};
	}
	if (matches.length > 1) {
		return {
			result: {
				...result,
				code: 1,
				command: "mrndev-resolve",
				args: [hostname, discoverySshHost],
				stdout: `Multiple MRN Dev site roots matched ${hostname}:\n${matches.map((item) => `- ${item}`).join("\n")}`,
			},
			context: null,
		};
	}

	const remotePath = matches[0];
	const siteUser = remotePath.split("/")[2] || "";
	if (!siteUser) {
		return {
			result: {
				...result,
				code: 1,
				command: "mrndev-resolve",
				args: [hostname, discoverySshHost],
				stdout: `MRN Dev site root did not include a site user: ${remotePath}`,
			},
			context: null,
		};
	}

	const context = {
		hostname,
		siteUser,
		remotePath,
		remoteSsh: `${siteUser}@mrndev-site-owner`,
		discoverySshHost,
		liveUrl: `https://${hostname}`,
		slug: slugFromMrnDevHostname(hostname),
	};
	return {
		result: {
			...result,
			code: 0,
			command: "mrndev-resolve",
			args: [hostname, discoverySshHost],
			stdout: [
				`Resolved MRN Dev site: ${hostname}`,
				`Site user: ${context.siteUser}`,
				`SSH target: ${context.remoteSsh}`,
				`WordPress root: ${context.remotePath}`,
				`Live URL: ${context.liveUrl}`,
			].join("\n"),
			sshFields: {
				provider: "mrndev",
				slug: context.slug,
				liveUrl: context.liveUrl,
				remoteSsh: context.remoteSsh,
				remotePort: "",
				remotePath: context.remotePath,
			},
			mrnDev: context,
		},
		context,
	};
}

async function prepareSshBody(body) {
	const provider = normalizeProvider(body.provider);
	const identity = importIdentityFromInputs(body, provider);
	const normalizedBody = {
		...body,
		provider,
		slug: identity.slug || body.slug || "",
		liveUrl: body.liveUrl || identity.liveUrl || "",
	};
	if (provider !== "mrndev") {
		return { body: normalizedBody, context: null, resolveResult: null };
	}
	if (String(normalizedBody.remoteSsh || "").trim() && String(normalizedBody.remotePath || "").trim()) {
		return { body: normalizedBody, context: null, resolveResult: null };
	}

	const resolved = await resolveMrnDevTarget(normalizedBody);
	if (!resolved.context) {
		return { body: normalizedBody, context: null, resolveResult: resolved.result };
	}

	return {
		body: {
			...normalizedBody,
			provider,
			slug: normalizedBody.slug || resolved.context.slug,
			liveUrl: normalizedBody.liveUrl || resolved.context.liveUrl,
			remoteSsh: resolved.context.remoteSsh,
			remotePort: "",
			remotePath: resolved.context.remotePath,
		},
		context: resolved.context,
		resolveResult: resolved.result,
	};
}

function remotePathCandidates({ provider, remotePath, liveUrl, remoteSsh, slug }) {
	const normalizedProvider = normalizeProvider(provider);
	const candidates = [];
	const host = hostnameFromUrl(liveUrl);
	const appName = slug || (host ? host.split(".")[0] : "");
	const add = (value) => addRemotePathCandidate(candidates, value);

	add(remotePath);
	if (normalizedProvider === "mrndev") {
		add(host.endsWith(".mrndev.io") && appName ? `/home/${appName}/htdocs/${host}` : "");
		add(host.endsWith(".mrndev.io") && appName ? `/home/${appName}-stack/htdocs/${host}` : "");
	} else if (normalizedProvider === "wpengine") {
		const environment = wpEngineEnvironment(remoteSsh, liveUrl);
		add(environment ? `sites/${environment}` : "");
		add(environment ? `/sites/${environment}` : "");
	} else if (normalizedProvider === "siteground") {
		add(host ? `/home/customer/www/${host}/public_html` : "");
		add(host ? `www/${host}/public_html` : "");
		add("public_html");
	} else if (normalizedProvider === "runcloud") {
		add(appName ? `/home/runcloud/webapps/${appName}/public` : "");
		add(appName ? `/home/runcloud/webapps/${appName}` : "");
	} else {
		add("public_html");
		add("www");
		add("htdocs");
		add("public");
	}

	return [...new Set(candidates)];
}

function buildWordPressInspectCommand({ provider, remotePath, candidates }) {
	const normalizedProvider = normalizeProvider(provider);
	const firstCandidate = candidates[0] || "";
	const candidateList = candidates.join("\n");
	return `set -eu
MRN_PROVIDER=${shellQuote(normalizedProvider)}
MRN_FIRST_CANDIDATE=${shellQuote(firstCandidate)}
MRN_CANDIDATES=${shellQuote(candidateList)}
resolve_wp_root() {
  if [ -f wp-config.php ]; then
    pwd
    return 0
  fi
  printf '%s\\n' "$MRN_CANDIDATES" | while IFS= read -r candidate; do
    [ -n "$candidate" ] || continue
    if [ -f "$candidate/wp-config.php" ]; then
      (cd "$candidate" && pwd)
      exit 0
    fi
    if [ -f "$candidate/public/wp-config.php" ]; then
      (cd "$candidate/public" && pwd)
      exit 0
    fi
  done
}
RESOLVED_PATH="$(resolve_wp_root || true)"
if [ -z "$RESOLVED_PATH" ]; then
  RESOLVED_PATH="$(find . -maxdepth 5 -name wp-config.php -type f 2>/dev/null | sed 's#/wp-config.php$##' | head -n 1 | while IFS= read -r found; do [ -n "$found" ] && (cd "$found" && pwd); done || true)"
fi
if [ -n "$RESOLVED_PATH" ]; then
  cd "$RESOLVED_PATH"
elif [ -n "$MRN_FIRST_CANDIDATE" ] && [ -d "$MRN_FIRST_CANDIDATE" ]; then
  cd "$MRN_FIRST_CANDIDATE"
fi
printf 'mrn_provider=%s\\n' "$MRN_PROVIDER"
printf 'candidate_count=%s\\n' "$(printf '%s\\n' "$MRN_CANDIDATES" | sed '/^$/d' | wc -l | tr -d ' ')"
printf 'requested_path=%s\\n' ${shellQuote(remotePath || "")}
printf 'resolved_path=%s\\n' "$RESOLVED_PATH"
printf 'remote_pwd=%s\\n' "$PWD"
printf 'remote_user=%s\\n' "$(whoami)"
printf 'remote_host=%s\\n' "$(hostname)"
if [ -f wp-config.php ]; then printf 'wp_config=1\\n'; else printf 'wp_config=0\\n'; fi
if command -v wp >/dev/null 2>&1; then printf 'wp_cli=1\\n'; else printf 'wp_cli=0\\n'; fi
if command -v php >/dev/null 2>&1; then
  printf 'php_cli=1\\n'
  php -r 'if (file_exists("wp-config.php")) { include "wp-config.php"; foreach (array("DB_NAME" => "db_name", "DB_USER" => "db_user", "DB_HOST" => "db_host") as $constant => $key) { if (defined($constant)) { echo $key . "=" . constant($constant) . PHP_EOL; } } }' 2>/dev/null || true
else
  printf 'php_cli=0\\n'
fi
if command -v wp >/dev/null 2>&1 && [ -f wp-config.php ]; then
  wp --path="$PWD" option get home --skip-plugins --skip-themes --quiet 2>/dev/null | sed 's/^/wp_home=/' || true
  wp --path="$PWD" option get siteurl --skip-plugins --skip-themes --quiet 2>/dev/null | sed 's/^/wp_siteurl=/' || true
  wp --path="$PWD" core version --quiet 2>/dev/null | sed 's/^/wp_version=/' || true
fi`;
}

function formatSshInspection(parsed) {
	const lines = [
		`Provider: ${providerPresets[normalizeProvider(parsed.mrn_provider)]?.label || "Generic SSH"}`,
		`Resolved root: ${parsed.resolved_path || parsed.remote_pwd || "unknown"}`,
		`Candidates checked: ${parsed.candidate_count || "0"}`,
		`Remote path: ${parsed.remote_pwd || "unknown"}`,
		`Remote user: ${parsed.remote_user || "unknown"}`,
		`Remote host: ${parsed.remote_host || "unknown"}`,
		`WordPress config: ${parsed.wp_config === "1" ? "found" : "missing"}`,
		`WP-CLI: ${parsed.wp_cli === "1" ? "available" : "missing"}`,
		`PHP CLI: ${parsed.php_cli === "1" ? "available" : "missing"}`,
	];
	if (parsed.wp_home) lines.push(`Home URL: ${parsed.wp_home}`);
	if (parsed.wp_siteurl) lines.push(`Site URL: ${parsed.wp_siteurl}`);
	if (parsed.wp_version) lines.push(`WordPress: ${parsed.wp_version}`);
	if (parsed.db_name) lines.push(`DB name: ${parsed.db_name}`);
	if (parsed.db_host) lines.push(`DB host: ${parsed.db_host}`);
	return lines.join("\n");
}

function parseSshConfigOutput(output) {
	const parsed = {};
	for (const line of String(output || "").split("\n")) {
		const trimmed = line.trim();
		if (!trimmed) {
			continue;
		}
		const parts = trimmed.split(/\s+/);
		const key = parts.shift();
		const value = parts.join(" ");
		if (!key || !value) {
			continue;
		}
		if (!parsed[key]) {
			parsed[key] = [];
		}
		parsed[key].push(value);
	}
	return parsed;
}

function firstSshConfigValue(config, key, fallback = "default") {
	return config[key]?.[0] || fallback;
}

function formatSshConfigPreview(remoteSsh, remotePort, config) {
	const identityFiles = config.identityfile || [];
	const lines = [
		"SSH config preview only; no connection was opened.",
		`Target: ${remoteSsh}`,
		`HostName: ${firstSshConfigValue(config, "hostname", remoteSsh)}`,
		`User: ${firstSshConfigValue(config, "user")}`,
		`Port: ${remotePort || firstSshConfigValue(config, "port", "22")}`,
		`IdentitiesOnly: ${firstSshConfigValue(config, "identitiesonly")}`,
		`IdentityAgent: ${firstSshConfigValue(config, "identityagent")}`,
	];
	if (identityFiles.length) {
		lines.push("Identity files:");
		for (const identityFile of identityFiles) {
			lines.push(`- ${identityFile}`);
		}
	}
	if (config.proxyjump?.[0] && config.proxyjump[0] !== "none") {
		lines.push(`ProxyJump: ${config.proxyjump[0]}`);
	}
	if (config.proxycommand?.[0] && config.proxycommand[0] !== "none") {
		lines.push(`ProxyCommand: ${config.proxycommand[0]}`);
	}
	return lines.join("\n");
}

function sitegroundIdentityFile() {
	return sanitizeOptionalIdentityFile(appSettings.sitegroundIdentityFile || defaultAppSettings.sitegroundIdentityFile, {
		fallback: defaultAppSettings.sitegroundIdentityFile,
	});
}

function sshIdentityFileForProvider(provider) {
	return normalizeProvider(provider) === "siteground" ? sitegroundIdentityFile() : "";
}

function sshIdentityFileForSite(site) {
	return sshIdentityFileForProvider(site?.provider || "");
}

function compactSshConfig(config) {
	const keys = [
		"hostname",
		"user",
		"port",
		"identitiesonly",
		"identityagent",
		"identityfile",
		"proxyjump",
		"proxycommand",
	];
	const compact = {};
	for (const key of keys) {
		if (config[key]) {
			compact[key] = config[key];
		}
	}
	return compact;
}

async function previewSshConfig(body) {
	const remoteSsh = sanitizeRemoteSsh(body.remoteSsh);
	const remotePort = sanitizeSshPort(body.remotePort || "");
	const identityFile = sshIdentityFileForProvider(body.provider);
	const args = ["-G", "-T", "-o", "BatchMode=yes"];
	if (remotePort) {
		args.push("-p", remotePort);
	}
	if (identityFile) {
		args.push("-o", "IdentitiesOnly=yes");
		args.push("-i", identityFile);
	}
	args.push(remoteSsh);
	const result = await runProcess("ssh", args, { timeoutMs: 10000 });
	const config = parseSshConfigOutput(result.stdout);
	return {
		...result,
		command: "ssh-config",
		args: [remoteSsh, remotePort || "default-port"],
		stdout: result.code === 0 ? formatSshConfigPreview(remoteSsh, remotePort, config) : result.stdout,
		stderr: result.code === 0 ? "" : result.stderr,
		sshConfig: compactSshConfig(config),
	};
}

async function inspectRemoteWordPress(body) {
	const prepared = await prepareSshBody(body);
	if (prepared.resolveResult && prepared.resolveResult.code !== 0) {
		return {
			result: {
				...prepared.resolveResult,
				command: "ssh-inspect",
				args: [normalizeProvider(body.provider), "mrndev-resolve"],
			},
			parsed: {},
			resolvedBody: prepared.body,
			mrnDevContext: null,
		};
	}
	const remoteSsh = sanitizeRemoteSsh(prepared.body.remoteSsh);
	const remotePort = sanitizeSshPort(prepared.body.remotePort || "");
	const provider = normalizeProvider(prepared.body.provider);
	const remotePath = sanitizeOptionalRemotePath(prepared.body.remotePath || "");
	const candidates = remotePathCandidates({
		provider,
		remotePath,
		liveUrl: prepared.body.liveUrl || "",
		remoteSsh,
		slug: prepared.body.slug || "",
	});
	const result = await runProcess(
		"ssh",
		sshArgs(remoteSsh, remotePort, buildWordPressInspectCommand({ provider, remotePath, candidates }), {
			identityFile: sshIdentityFileForProvider(provider),
		}),
		{ timeoutMs: 30000 },
	);
	const parsed = parseKeyValueOutput(result.stdout);
	return { result, parsed, resolvedBody: prepared.body, mrnDevContext: prepared.context, resolveResult: prepared.resolveResult };
}

async function runSshAction(body) {
	const action = String(body.action || "");
	switch (action) {
		case "mrndev-resolve": {
			const resolved = await resolveMrnDevTarget({ ...body, provider: "mrndev" });
			return resolved.result;
		}
		case "ssh-config":
			return previewSshConfig(body);
		case "ssh-test": {
			const prepared = await prepareSshBody(body);
			if (prepared.resolveResult && prepared.resolveResult.code !== 0) {
				return {
					...prepared.resolveResult,
					command: "ssh-test",
					args: [normalizeProvider(body.provider), "mrndev-resolve"],
				};
			}
			const remoteSsh = sanitizeRemoteSsh(prepared.body.remoteSsh);
			const remotePort = sanitizeSshPort(prepared.body.remotePort || "");
			const provider = normalizeProvider(prepared.body.provider);
			const command = "printf 'remote_user=%s\\nremote_host=%s\\nremote_pwd=%s\\n' \"$(whoami)\" \"$(hostname)\" \"$(pwd)\"";
			const result = await runProcess("ssh", sshArgs(remoteSsh, remotePort, command, {
				identityFile: sshIdentityFileForProvider(provider),
			}), { timeoutMs: 20000 });
			const parsed = parseKeyValueOutput(result.stdout);
			return {
				...result,
				command: "ssh-test",
				args: [remoteSsh, remotePort || "22"],
				stdout: result.code === 0
					? [
						"SSH connection succeeded.",
						`Remote user: ${parsed.remote_user || "unknown"}`,
						`Remote host: ${parsed.remote_host || "unknown"}`,
						`Remote pwd: ${parsed.remote_pwd || "unknown"}`,
					].join("\n")
					: result.stdout,
				sshFields: prepared.resolveResult?.sshFields,
				mrnDev: prepared.context,
			};
		}
		case "ssh-inspect": {
			const { result, parsed, resolvedBody, mrnDevContext, resolveResult } = await inspectRemoteWordPress(body);
			return {
				...result,
				command: "ssh-inspect",
				args: [
					resolvedBody.remoteSsh ? sanitizeRemoteSsh(resolvedBody.remoteSsh) : normalizeProvider(body.provider),
					normalizeProvider(resolvedBody.provider),
					sanitizeOptionalRemotePath(resolvedBody.remotePath || "") || "auto",
				],
				stdout: result.code === 0 ? formatSshInspection(parsed) : result.stdout,
				inspection: parsed,
				sshFields: resolveResult?.sshFields,
				mrnDev: mrnDevContext,
			};
		}
		case "ssh-create-site": {
			const provider = normalizeProvider(body.provider);
			const identity = importIdentityFromInputs(body, provider);
			const slug = normalizeSlug(identity.slug || (provider === "mrndev" ? slugFromMrnDevHostname(sanitizeMrnDevHostname(mrnDevHostnameFromInputs(body))) : ""));
			const siteRoot = assertInsideSitesRoot(path.join(sitesRoot, slug));
			if (await pathExists(manifestPathFor(siteRoot))) {
				throw httpError(409, `Site already exists: ${slug}`);
			}
			const { result, parsed, resolvedBody, mrnDevContext, resolveResult } = await inspectRemoteWordPress({
				...body,
				slug,
				liveUrl: body.liveUrl || identity.liveUrl,
			});
			if (result.code !== 0) {
				return {
					...result,
					command: "ssh-create-site",
					args: [slug],
					stdout: result.stdout,
					inspection: parsed,
					sshFields: resolveResult?.sshFields,
					mrnDev: mrnDevContext,
				};
			}
			if (parsed.wp_config !== "1") {
				throw httpError(400, "Remote path does not look like a WordPress root; wp-config.php was not found.");
			}

			const site = sanitizeManifest({
				slug,
				title: body.title || slug,
				provider,
				liveUrl: resolvedBody.liveUrl || body.liveUrl || identity.liveUrl || parsed.wp_home || parsed.wp_siteurl || "",
				remoteSsh: resolvedBody.remoteSsh,
				remotePort: resolvedBody.remotePort || "",
				remotePath: parsed.remote_pwd || parsed.resolved_path || resolvedBody.remotePath,
				dbName: parsed.db_name || "",
				dbHost: "127.0.0.1",
				dbPort: 3307,
				runtime: "local-vm-openlitespeed",
				runtimeStatus: "planned",
			});
			await writeManifest(site);
			const friendlyCertResult = await syncFriendlyCertificateAfterSiteChange(site, "ssh-create-site");
			return {
				code: 0,
				command: "ssh-create-site",
				args: [slug],
				stdout: [
					`Created local site manifest: ${site.slug}`,
					`Local root: ${site.localRoot}`,
					`Public path: ${site.publicPath}`,
					`Live URL: ${site.liveUrl || "not detected"}`,
					"",
					formatSshInspection(parsed),
					friendlyCertResult.stdout,
				].join("\n"),
				stderr: [result.stderr, friendlyCertResult.stderr].filter(Boolean).join("\n"),
				durationMs: (result.durationMs || 0) + (friendlyCertResult.durationMs || 0),
				site,
				inspection: parsed,
				sshFields: resolveResult?.sshFields,
				mrnDev: mrnDevContext,
				friendlyCert: friendlyCertResult,
				friendlyUrls: friendlyCertResult.friendlyUrls || null,
			};
			}
		default:
			throw httpError(400, `Unknown SSH action: ${action}`);
	}
}

function sshArgs(remoteSsh, remotePort, remoteCommand, options = {}) {
	const args = [
		"-o",
		"BatchMode=yes",
		"-o",
		"ConnectTimeout=10",
	];
	const port = sanitizeSshPort(remotePort || "");
	if (port) {
		args.push("-p", port);
	}
	if (options.identityFile) {
		args.push("-o", "IdentitiesOnly=yes");
		args.push("-i", sanitizeOptionalIdentityFile(options.identityFile));
	}
	args.push(sanitizeRemoteSsh(remoteSsh), remoteCommand);
	return args;
}

function rsyncArgs({ dryRun, deleteFiles, source, dest, sshPort, identityFile = "", excludes = [] }) {
	const args = ["-az", "--human-readable", "--itemize-changes"];
	if (dryRun) {
		args.push("--dry-run");
	}
	if (deleteFiles) {
		args.push("--delete");
	}
	const port = sanitizeSshPort(sshPort || "");
	const sshParts = ["ssh"];
	if (port) {
		sshParts.push("-p", port);
	}
	if (identityFile) {
		sshParts.push("-o", "IdentitiesOnly=yes");
		sshParts.push("-i", sanitizeOptionalIdentityFile(identityFile));
	}
	if (sshParts.length > 1) {
		args.push("-e", sshParts.map((part) => shellQuote(part)).join(" "));
	}
	args.push(
		"--exclude=.git/",
		"--exclude=node_modules/",
		"--exclude=wp-content/cache/",
		"--exclude=wp-content/uploads/cache/",
		"--exclude=wp-content/updraft/",
	);
	for (const exclude of excludes) {
		args.push(`--exclude=${exclude}`);
	}
	args.push(source, dest);
	return args;
}

const pullFileScopeLabels = {
	full: "Full site",
	"full-no-uploads": "Full site, skip uploads",
	core: "Core/root",
	"wp-content": "wp-content",
	"active-theme": "Child / active theme",
	"parent-theme": "Parent theme",
	themes: "All themes",
	plugins: "Plugins",
	"mu-plugins": "MU plugins",
	uploads: "Uploads",
	custom: "Custom directory",
};

const customPullPathScopes = ["wp-content", "wp-admin", "wp-includes"];

function normalizePullRelativePath(value) {
	return sanitizeRelativePath(value).replace(/\/+$/g, "");
}

function isAllowedCustomPullPath(relativePath) {
	return customPullPathScopes.some((scope) => relativePath === scope || relativePath.startsWith(`${scope}/`));
}

function sanitizeThemeDirectory(value) {
	const theme = String(value || "").trim();
	if (!/^[A-Za-z0-9_.-]+$/.test(theme)) {
		throw httpError(400, "Active theme slug contains unsupported characters. Choose All themes or Custom directory instead.");
	}
	return theme;
}

async function localThemeRelativePath(site, optionName, label) {
	if (!(await pathExists(path.join(site.publicPath, "wp-load.php")))) {
		throw httpError(400, "Local WordPress files are not present yet. Use Full site, All themes, or Custom directory first.");
	}
	const result = await runWpCli(
		site,
		[
			"option",
			"get",
			optionName,
			"--skip-plugins",
			"--skip-themes",
			"--quiet",
		],
		{ cwd: site.localRoot, timeoutMs: 30000, trackJob: false },
	);
	const theme = cleanWpCliScalarOutput(result.stdout);
	if (result.code !== 0 || !theme) {
		throw httpError(400, `Could not detect the local ${label}. Pull the database first, or choose All themes.`);
	}
	return `wp-content/themes/${sanitizeThemeDirectory(theme)}`;
}

function localActiveThemeRelativePath(site) {
	return localThemeRelativePath(site, "stylesheet", "child/active theme");
}

function localParentThemeRelativePath(site) {
	return localThemeRelativePath(site, "template", "parent theme");
}

function pullScopePostProcessing(scope, relativePath) {
	const touchesRoot = scope === "full" || scope === "full-no-uploads" || scope === "core";
	const mayContainSymlinks = ["full", "full-no-uploads", "wp-content", "themes", "active-theme", "parent-theme", "plugins", "mu-plugins"].includes(scope)
		|| relativePath.startsWith("wp-content/themes")
		|| relativePath.startsWith("wp-content/plugins")
		|| relativePath.startsWith("wp-content/mu-plugins");
	return { touchesRoot, mayContainSymlinks };
}

async function resolvePullFileScope(site, body = {}) {
	const scope = Object.prototype.hasOwnProperty.call(pullFileScopeLabels, body.fileScope) ? body.fileScope : "full";
	let relativePath = "";
	let label = pullFileScopeLabels[scope];
	const excludes = [];

	if (scope === "core") {
		excludes.push("wp-content/");
	} else if (scope === "full-no-uploads") {
		excludes.push("wp-content/uploads/");
	} else if (scope === "wp-content") {
		relativePath = "wp-content";
	} else if (scope === "active-theme") {
		relativePath = await localActiveThemeRelativePath(site);
		label = `Child / active theme: ${path.posix.basename(relativePath)}`;
	} else if (scope === "parent-theme") {
		relativePath = await localParentThemeRelativePath(site);
		label = `Parent theme: ${path.posix.basename(relativePath)}`;
	} else if (scope === "themes") {
		relativePath = "wp-content/themes";
	} else if (scope === "plugins") {
		relativePath = "wp-content/plugins";
	} else if (scope === "mu-plugins") {
		relativePath = "wp-content/mu-plugins";
	} else if (scope === "uploads") {
		relativePath = "wp-content/uploads";
	} else if (scope === "custom") {
		relativePath = normalizePullRelativePath(body.relativePath || "");
		if (!isAllowedCustomPullPath(relativePath)) {
			throw httpError(400, `Custom pull path must stay inside one of: ${customPullPathScopes.join(", ")}.`);
		}
		label = `Custom: ${relativePath}`;
	}

	const remotePath = sanitizeRemotePath(site.remotePath);
	const remoteRelative = relativePath ? `/${relativePath}` : "";
	const localDestPath = path.join(site.publicPath, relativePath);
	const postProcessing = pullScopePostProcessing(scope, relativePath);
	return {
		scope,
		label,
		relativePath,
		source: `${sanitizeRemoteSsh(site.remoteSsh)}:${remotePath}${remoteRelative}/`,
		dest: `${localDestPath}/`,
		localDestPath,
		excludes,
		...postProcessing,
	};
}

function gitStatusLines(text) {
	return String(text || "")
		.split(/\r?\n/)
		.map((line) => line.trimEnd())
		.filter(Boolean);
}

function gitDisplayPath(filePath) {
	return displayPath(filePath);
}

async function nearestExistingPath(filePath, stopPath) {
	let candidate = path.resolve(filePath);
	const stop = path.resolve(stopPath || sitesRoot);
	while (candidate.startsWith(stop)) {
		if (await pathExists(candidate)) {
			return candidate;
		}
		const next = path.dirname(candidate);
		if (next === candidate) {
			break;
		}
		candidate = next;
	}
	return "";
}

async function gitSafetyReport(targetPath, options = {}) {
	const label = options.label || "Selected path";
	const git = await commandExists("git");
	if (!git.ok) {
		return {
			available: false,
			present: false,
			dirty: false,
			targetDirty: false,
			label,
			targetPath,
			summary: "Git is not installed; Git-aware safety checks are unavailable.",
			warnings: ["Git is missing; use backup-only protection before writing files."],
			issues: [],
		};
	}

	const probePath = await nearestExistingPath(targetPath, options.stopPath || sitesRoot);
	if (!probePath) {
		return {
			available: true,
			present: false,
			dirty: false,
			targetDirty: false,
			label,
			targetPath,
			summary: `${label} does not exist locally yet; no Git repo was detected.`,
			warnings: ["No local Git repo was detected for this path yet."],
			issues: [],
		};
	}

	const rootResult = await runProcess("git", ["-C", probePath, "rev-parse", "--show-toplevel"], {
		timeoutMs: 10000,
		trackJob: false,
	});
	if (rootResult.code !== 0) {
		return {
			available: true,
			present: false,
			dirty: false,
			targetDirty: false,
			label,
			targetPath,
			probePath,
			summary: `${label} is not inside a Git repo.`,
			warnings: ["This path is unversioned; use backup-only protection before writing files."],
			issues: [],
		};
	}

	const repoRoot = rootResult.stdout.trim().split(/\r?\n/).pop();
	const relativePath = path.relative(repoRoot, path.resolve(targetPath)).split(path.sep).join("/") || ".";
	const pathspec = relativePath.startsWith("..") ? "." : relativePath;
	const [branchResult, upstreamResult, statusResult, scopedStatusResult] = await Promise.all([
		runProcess("git", ["-C", repoRoot, "branch", "--show-current"], { timeoutMs: 10000, trackJob: false }),
		runProcess("git", ["-C", repoRoot, "rev-parse", "--abbrev-ref", "--symbolic-full-name", "@{u}"], { timeoutMs: 10000, trackJob: false }),
		runProcess("git", ["-C", repoRoot, "status", "--short", "--untracked-files=all"], { timeoutMs: 10000, trackJob: false }),
		runProcess("git", ["-C", repoRoot, "status", "--short", "--untracked-files=all", "--", pathspec], { timeoutMs: 10000, trackJob: false }),
	]);
	const lines = gitStatusLines(statusResult.stdout);
	const scopedLines = gitStatusLines(scopedStatusResult.stdout);
	const dirty = lines.length > 0;
	const targetDirty = scopedLines.length > 0;
	const branch = branchResult.stdout.trim() || "detached";
	const upstream = upstreamResult.code === 0 ? upstreamResult.stdout.trim() : "";
	const summary = dirty
		? `Git dirty: ${gitDisplayPath(repoRoot)} has ${lines.length} change${lines.length === 1 ? "" : "s"} (${scopedLines.length} in ${label}).`
		: `Git clean: ${branch}${upstream ? ` tracking ${upstream}` : ""} at ${gitDisplayPath(repoRoot)}.`;
	return {
		available: true,
		present: true,
		dirty,
		targetDirty,
		label,
		targetPath,
		probePath,
		repoRoot,
		relativePath: pathspec,
		branch,
		upstream,
		totalChanges: lines.length,
		targetChanges: scopedLines.length,
		lines: lines.slice(0, 20),
		targetLines: scopedLines.slice(0, 20),
		summary,
		warnings: [],
		issues: dirty ? [summary] : [],
	};
}

function formatGitSafety(report) {
	if (!report) {
		return "";
	}
	const lines = [
		"Git safety:",
		`- ${report.summary}`,
	];
	if (report.present) {
		lines.push(`- Branch: ${report.branch}${report.upstream ? ` -> ${report.upstream}` : ""}`);
		if (report.dirty && report.lines.length) {
			lines.push("- Changes:");
			for (const line of report.lines) {
				lines.push(`  ${line}`);
			}
			if (report.totalChanges > report.lines.length) {
				lines.push(`  ... ${report.totalChanges - report.lines.length} more`);
			}
		}
	}
	return lines.join("\n");
}

const pushAllowedScopes = [
	"wp-content/themes",
	"wp-content/plugins",
	"wp-content/mu-plugins",
	"wp-content/uploads",
	"wp-content",
];

const blockedPushFileScopes = new Set(["full", "full-no-uploads", "core"]);

async function resolvePushFileScope(site, body = {}) {
	const hasFileScope = Object.prototype.hasOwnProperty.call(body, "fileScope");
	const scope = hasFileScope && Object.prototype.hasOwnProperty.call(pullFileScopeLabels, body.fileScope)
		? body.fileScope
		: "custom";
	let relativePath = "";
	let label = pullFileScopeLabels[scope] || "Custom directory";

	if (!hasFileScope) {
		relativePath = sanitizeRelativePath(body.relativePath || "wp-content/themes").replace(/\/+$/g, "");
		label = `Custom: ${relativePath}`;
	} else if (scope === "wp-content") {
		relativePath = "wp-content";
	} else if (scope === "active-theme") {
		relativePath = await localActiveThemeRelativePath(site);
		label = `Child / active theme: ${path.posix.basename(relativePath)}`;
	} else if (scope === "parent-theme") {
		relativePath = await localParentThemeRelativePath(site);
		label = `Parent theme: ${path.posix.basename(relativePath)}`;
	} else if (scope === "themes") {
		relativePath = "wp-content/themes";
	} else if (scope === "plugins") {
		relativePath = "wp-content/plugins";
	} else if (scope === "mu-plugins") {
		relativePath = "wp-content/mu-plugins";
	} else if (scope === "uploads") {
		relativePath = "wp-content/uploads";
	} else if (scope === "custom") {
		relativePath = sanitizeRelativePath(body.relativePath || "wp-content/themes").replace(/\/+$/g, "");
		label = `Custom: ${relativePath}`;
	}

	return { scope, label, relativePath };
}

function isAllowedPushScope(relativePath) {
	const normalized = path.posix.normalize(relativePath).replace(/^\/+|\/+$/g, "");
	return pushAllowedScopes.some((scope) => normalized === scope || normalized.startsWith(`${scope}/`));
}

function pushPathScope(relativePath) {
	const normalized = path.posix.normalize(relativePath).replace(/^\/+|\/+$/g, "");
	return pushAllowedScopes.find((scope) => normalized === scope || normalized.startsWith(`${scope}/`)) || "";
}

function pushAuditRecord(type, filePath, reason) {
	return { type, path: filePath, reason };
}

function classifyPushPath(relativePath, entryName, isDirectory = false) {
	const normalized = relativePath.split(path.sep).join(path.posix.sep);
	const parts = normalized.split("/").filter(Boolean);
	const lowerParts = parts.map((part) => part.toLowerCase());
	const lowerNormalized = lowerParts.join("/");
	const lowerName = String(entryName || parts[parts.length - 1] || "").toLowerCase();

	if (lowerName === ".mrn-site.json") {
		return pushAuditRecord("blocked", normalized, "local site manifest may contain DB/deploy metadata");
	}
	if (lowerName === "wp-config.php") {
		return pushAuditRecord("blocked", normalized, "wp-config.php contains local DB settings after pull");
	}
	if (lowerName.endsWith(".sql")) {
		return pushAuditRecord("blocked", normalized, "SQL dump files must never be pushed");
	}
	if (["logs", "dumps", "backups"].some((part) => lowerParts.includes(part))) {
		return pushAuditRecord("blocked", normalized, "local logs/dumps/backups are private workflow artifacts");
	}
	if (lowerName === ".env" || lowerName.endsWith(".env") || lowerName.includes(".env.")) {
		return pushAuditRecord("blocked", normalized, "environment files often contain secrets");
	}
	if (lowerName === "debug.log" || lowerName.endsWith(".log")) {
		return pushAuditRecord("blocked", normalized, "log files can contain private runtime data");
	}
	if (lowerName === ".ds_store" || lowerName === "thumbs.db") {
		return pushAuditRecord("warning", normalized, "local OS metadata should usually be excluded");
	}
	const generatedContentRoots = [
		"wp-content/cache",
		"wp-content/uploads/cache",
		"wp-content/updraft",
		"wp-content/upgrade",
	];
	if (isDirectory && generatedContentRoots.some((root) => lowerNormalized === root || lowerNormalized.startsWith(`${root}/`))) {
		return pushAuditRecord("warning", normalized, "generated WordPress directory; verify before pushing");
	}
	return null;
}

async function scanPushPath(rootPath, sitePublicPath, options = {}) {
	const maxEntries = options.maxEntries || 20000;
	const issues = [];
	const warnings = [];
	let scanned = 0;

	async function visit(currentPath) {
		if (scanned >= maxEntries) {
			warnings.push(pushAuditRecord("warning", path.relative(sitePublicPath, currentPath), `scan limit reached at ${maxEntries} entries`));
			return;
		}
		scanned += 1;
		const relative = path.relative(sitePublicPath, currentPath) || path.basename(currentPath);
		const stat = await fsp.lstat(currentPath);
		const classified = classifyPushPath(relative, path.basename(currentPath), stat.isDirectory());
		if (classified) {
			(classified.type === "blocked" ? issues : warnings).push(classified);
		}
		if (stat.isSymbolicLink()) {
			const target = await fsp.readlink(currentPath);
			const risky = path.isAbsolute(target) || target.split(/[\\/]+/).includes("..");
			(risky ? issues : warnings).push(pushAuditRecord(
				risky ? "blocked" : "warning",
				relative,
				`symlink target ${target}${risky ? " may not be valid or safe on the remote" : " should be reviewed"}`,
			));
			return;
		}
		if (!stat.isDirectory()) {
			return;
		}
		const entries = await fsp.readdir(currentPath, { withFileTypes: true });
		for (const entry of entries) {
			if (entry.name === ".git" || entry.name === "node_modules") {
				warnings.push(pushAuditRecord("warning", path.relative(sitePublicPath, path.join(currentPath, entry.name)), "development directory skipped by rsync"));
				continue;
			}
			await visit(path.join(currentPath, entry.name));
		}
	}

	await visit(rootPath);
	return { issues, warnings, scanned };
}

function formatPushAudit(audit) {
	const lines = [
		`Push audit: ${audit.slug}`,
		`File scope: ${audit.label || audit.relativePath || "selected path"}`,
		`Local path: ${audit.source}`,
		`Remote target: ${audit.dest}`,
		`Scope: ${audit.scope || "blocked"}`,
		`Delete remote extras: ${audit.deleteFiles ? "yes" : "no"}`,
		`Scanned entries: ${audit.scanned}`,
	];
	if (audit.gitSafety) {
		lines.push("", formatGitSafety(audit.gitSafety));
	}
	lines.push(
		"",
		audit.issues.length ? "Issues:" : "Issues: none",
		...audit.issues.map((issue) => `- ${issue.path}: ${issue.reason}`),
		audit.warnings.length ? "Warnings:" : "Warnings: none",
		...audit.warnings.map((warning) => `- ${warning.path}: ${warning.reason}`),
	);
	return lines.join("\n");
}

async function runPushAudit(site, body = {}) {
	const remoteSsh = sanitizeRemoteSsh(site.remoteSsh);
	const remotePath = sanitizeRemotePath(site.remotePath);
	const pushScope = await resolvePushFileScope(site, body);
	const relativePath = pushScope.relativePath;
	const deleteFiles = Boolean(body.deleteFiles);
	const source = path.join(site.publicPath, relativePath);
	const remoteRelative = relativePath ? `/${relativePath}` : "";
	const dest = `${remoteSsh}:${remotePath}${remoteRelative}`;
	const issues = [];
	const warnings = [];
	const scope = pushPathScope(relativePath);
	let scanned = 0;
	let sourceIsDirectory = false;
	let gitSafety = null;
	const allowedPushScope = relativePath && isAllowedPushScope(relativePath);

	if (!allowedPushScope) {
		issues.push(pushAuditRecord(
			"blocked",
			relativePath || ".",
			blockedPushFileScopes.has(pushScope.scope)
				? `${pushScope.label} pushes are blocked. Choose wp-content, child/active theme, parent theme, all themes, plugins, MU plugins, uploads, or a custom child path.`
				: `push path must stay inside one of: ${pushAllowedScopes.join(", ")}`,
		));
	}
	if (deleteFiles && pushAllowedScopes.includes(relativePath.replace(/\/+$/g, ""))) {
		issues.push(pushAuditRecord(
			"blocked",
			relativePath,
			"delete remote extras is too broad at a top-level deploy scope; choose a specific child path",
		));
	}
	if (deleteFiles && relativePath.startsWith("wp-content/uploads")) {
		issues.push(pushAuditRecord(
			"blocked",
			relativePath,
			"delete remote extras is disabled for uploads to avoid deleting production media",
		));
	}

	if (!allowedPushScope) {
		// Unsupported root/core scopes stop at audit without scanning the entire WordPress tree.
	} else if (!(await pathExists(source))) {
		issues.push(pushAuditRecord("blocked", relativePath, "local source path does not exist"));
	} else {
		const stat = await fsp.lstat(source);
		sourceIsDirectory = stat.isDirectory();
		gitSafety = await gitSafetyReport(source, {
			label: relativePath,
			stopPath: site.publicPath,
		});
		issues.push(...gitSafety.issues.map((issue) => pushAuditRecord("blocked", relativePath, issue)));
		warnings.push(...gitSafety.warnings.map((warning) => pushAuditRecord("warning", relativePath, warning)));
		const scan = await scanPushPath(source, site.publicPath);
		issues.push(...scan.issues);
		warnings.push(...scan.warnings);
		scanned = scan.scanned;
	}

	const audit = {
		slug: site.slug,
		fileScope: pushScope.scope,
		label: pushScope.label,
		relativePath,
		source,
		sourceIsDirectory,
		dest,
		scope,
		deleteFiles,
		scanned,
		issues,
		warnings,
		allowedScopes: pushAllowedScopes,
		gitSafety,
	};

	return {
		code: issues.length ? 1 : 0,
		command: "push-audit",
		args: [site.slug, relativePath || "."],
		stdout: formatPushAudit(audit),
		stderr: "",
		durationMs: 0,
		pushAudit: audit,
		site,
	};
}

async function appendPushHistory(site, entry) {
	const historyPath = path.join(site.localRoot, "logs", "push-history.jsonl");
	await fsp.mkdir(path.dirname(historyPath), { recursive: true });
	await fsp.appendFile(historyPath, `${JSON.stringify(entry)}\n`, "utf8");
	return historyPath;
}

function isMaterializableRemoteSymlinkTarget(target) {
	if (!path.isAbsolute(target)) {
		return false;
	}
	if (/[\r\n\0]/.test(target) || target.split("/").includes("..")) {
		return false;
	}
	return ["/home/", "/var/www/", "/nas/content/", "/mnt/", "/srv/"].some((prefix) => target.startsWith(prefix));
}

async function materializeRemoteWpSymlinks(site) {
	if (!site.remoteSsh) {
		return { code: 0, stdout: "", stderr: "", repairs: [] };
	}

	const roots = ["wp-content/themes", "wp-content/plugins", "wp-content/mu-plugins"];
	const repairs = [];
	const stdout = [];
	const stderr = [];
	const remoteSsh = sanitizeRemoteSsh(site.remoteSsh);

	for (const relativeRoot of roots) {
		const localRoot = path.join(site.publicPath, relativeRoot);
		let entries = [];
		try {
			entries = await fsp.readdir(localRoot, { withFileTypes: true });
		} catch (error) {
			if (error.code !== "ENOENT") {
				stderr.push(`${relativeRoot}: ${error.message}`);
			}
			continue;
		}

		for (const entry of entries) {
			if (!entry.isSymbolicLink()) {
				continue;
			}
			const localLink = path.join(localRoot, entry.name);
			const remoteTarget = await fsp.readlink(localLink);
			if (!isMaterializableRemoteSymlinkTarget(remoteTarget)) {
				continue;
			}

			const localRelativePath = path.relative(site.publicPath, localLink);
			await fsp.rm(localLink, { force: true });
			await fsp.mkdir(localLink, { recursive: true });

			const result = await runProcess(
				"rsync",
					rsyncArgs({
						dryRun: false,
						deleteFiles: true,
						source: `${remoteSsh}:${sanitizeRemotePath(remoteTarget)}/`,
						dest: `${localLink}/`,
						sshPort: site.remotePort || "",
						identityFile: sshIdentityFileForSite(site),
					}),
				{ cwd: site.localRoot },
			);

			repairs.push({
				localPath: localRelativePath,
				remoteTarget,
				code: result.code,
			});
			stdout.push(`Materialized remote symlink ${localRelativePath} from ${remoteTarget}.`);
			if (result.stdout) {
				stdout.push(result.stdout);
			}
			if (result.stderr) {
				stderr.push(result.stderr);
			}
			if (result.code !== 0) {
				return {
					code: result.code,
					stdout: stdout.join("\n"),
					stderr: stderr.join("\n"),
					repairs,
				};
			}
		}
	}

	return {
		code: 0,
		stdout: stdout.join("\n"),
		stderr: stderr.join("\n"),
		repairs,
	};
}

function commandPreview(command, args) {
	return [command, ...args].map((part) => {
		const text = String(part);
		return /^[A-Za-z0-9_./:=@%+-]+$/.test(text) ? text : shellQuote(text);
	}).join(" ");
}

function bashArray(values) {
	return values.map((value) => shellQuote(value)).join(" ");
}

function buildPhpInstallBash(versions, options = {}) {
	const sudo = options.sudo ? "sudo " : "";
	const requiredVersion = options.requiredVersion ? normalizePhpVersion(options.requiredVersion) : "";
	const normalizedVersions = [...new Set(versions.map(normalizePhpVersion))];
	const lines = [
		`for MRN_PHP_VERSION in ${bashArray(normalizedVersions)}; do`,
		`  MRN_PHP_SUFFIX="\${MRN_PHP_VERSION//./}"`,
		`  MRN_PHP_HANDLER="lsphp\${MRN_PHP_SUFFIX}"`,
		`  MRN_PHP_BIN="/usr/local/lsws/\${MRN_PHP_HANDLER}/bin/php"`,
		`  if [ -x "\${MRN_PHP_BIN}" ]; then`,
		`    echo "PHP \${MRN_PHP_VERSION} already installed: \${MRN_PHP_BIN}"`,
		`    continue`,
		`  fi`,
		`  MRN_PHP_PACKAGES=()`,
		`  for MRN_PHP_PACKAGE in "\${MRN_PHP_HANDLER}" "\${MRN_PHP_HANDLER}-common" "\${MRN_PHP_HANDLER}-mysql" "\${MRN_PHP_HANDLER}-curl" "\${MRN_PHP_HANDLER}-imagick" "\${MRN_PHP_HANDLER}-intl" "\${MRN_PHP_HANDLER}-opcache" "\${MRN_PHP_HANDLER}-redis"; do`,
		`    if apt-cache show "\${MRN_PHP_PACKAGE}" >/dev/null 2>&1; then`,
		`      MRN_PHP_PACKAGES+=("\${MRN_PHP_PACKAGE}")`,
		`    else`,
		`      echo "PHP \${MRN_PHP_VERSION} package unavailable: \${MRN_PHP_PACKAGE}"`,
		`    fi`,
		`  done`,
		`  if [ "\${#MRN_PHP_PACKAGES[@]}" -gt 0 ]; then`,
		`    ${sudo}apt-get install -y "\${MRN_PHP_PACKAGES[@]}"`,
		`  fi`,
		`  if [ -x "\${MRN_PHP_BIN}" ]; then`,
		`    "\${MRN_PHP_BIN}" -v | head -n 1`,
		`  else`,
		`    echo "PHP \${MRN_PHP_VERSION} binary missing after install attempt: \${MRN_PHP_BIN}"`,
		`  fi`,
		`done`,
	];
	if (requiredVersion) {
		lines.push(
			`MRN_REQUIRED_PHP_BIN=${shellQuote(phpBinaryPath(requiredVersion))}`,
			`if [ ! -x "\${MRN_REQUIRED_PHP_BIN}" ]; then`,
			`  echo "Required ${phpVersionLabel(requiredVersion)} is not installed at \${MRN_REQUIRED_PHP_BIN}." >&2`,
			`  exit 2`,
			`fi`,
		);
	}
	return lines.join("\n");
}

function buildRuntimeWpCliInstallBash(options = {}) {
	const sudo = options.sudo ? "sudo " : "";
	const defaultPhp = phpBinaryPath(defaultPhpVersion);
	return [
		`MRN_WP_CLI_PHP=${shellQuote(defaultPhp)}`,
		`if [ ! -x "$MRN_WP_CLI_PHP" ]; then`,
		`  echo "Required WP-CLI PHP binary is missing: $MRN_WP_CLI_PHP" >&2`,
		`  exit 2`,
		`fi`,
		`if [ ! -f /usr/local/bin/wp-cli.phar ]; then`,
		`  ${sudo}curl -fsSL -o /usr/local/bin/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar`,
		`fi`,
		`${sudo}tee /usr/local/bin/wp >/dev/null <<'MRN_WP_CLI_WRAPPER'`,
		`#!/usr/bin/env bash`,
		`set -e`,
		`PHP_BIN="\${MRN_WP_CLI_PHP:-/usr/local/lsws/lsphp84/bin/php}"`,
		`exec "$PHP_BIN" /usr/local/bin/wp-cli.phar "$@"`,
		`MRN_WP_CLI_WRAPPER`,
		`${sudo}chmod +x /usr/local/bin/wp /usr/local/bin/wp-cli.phar`,
		`MRN_WP_CLI_PHP="$MRN_WP_CLI_PHP" /usr/local/bin/wp --info >/dev/null`,
		`echo "WP-CLI ready in Lima runtime: /usr/local/bin/wp using $MRN_WP_CLI_PHP"`,
	].join("\n");
}

function sanitizeHostname(value) {
	const hostname = String(value || "").trim().toLowerCase();
	if (!hostname || hostname.length > 253 || !/^[a-z0-9.-]+$/.test(hostname) || hostname.includes("..")) {
		throw httpError(400, "Local URL must resolve to a safe hostname.");
	}
	return hostname;
}

function runtimeHostnameForSite(site) {
	return sanitizeHostname(hostnameFromUrl(site.localUrl) || `${normalizeSlug(site.slug)}.localhost`);
}

function siteWithRuntimeDefaults(site, options = {}) {
	const slug = normalizeSlug(site.slug);
	const localUrl = normalizeLocalUrlForSlug(site.localUrl, slug);
	const dbUser = !site.dbUser || site.dbUser === "root" ? defaultDbUser(slug) : site.dbUser;
	const next = {
		...site,
		localUrl,
		runtime: "local-vm-openlitespeed",
		runtimeStatus: options.runtimeStatus || site.runtimeStatus || "planned",
		dbName: site.dbName || defaultDbName(slug),
		dbUser,
		dbPassword: site.dbPassword || generateDbPassword(),
		dbHost: "127.0.0.1",
		dbPort: 3307,
		webserver: "openlitespeed",
	};
	return sanitizeManifest(next, site);
}

function vhostNameForSite(site) {
	return `mrn_${normalizeSlug(site.slug).replace(/-/g, "_")}`;
}

function guestPathForHostPath(hostPath) {
	const resolved = path.resolve(hostPath);
	const relative = path.relative(sitesRoot, resolved);
	if (relative.startsWith("..") || path.isAbsolute(relative)) {
		throw httpError(400, "Runtime paths must stay inside MRN_LOCAL_SITES_ROOT.");
	}
	const posixRelative = relative.split(path.sep).join(path.posix.sep);
	return path.posix.join("/srv/mrn-sites", posixRelative);
}

function siteUsesLimaOpenLiteSpeed(site) {
	const runtime = String(site.runtime || "").toLowerCase();
	const webserver = String(site.webserver || "").toLowerCase();
	return runtime.includes("local-vm") || runtime.includes("lima") || webserver === "openlitespeed";
}

function wpRuntimeContextForSite(site) {
	const localSite = siteWithRuntimeDefaults(site);
	const hostPath = localSite.publicPath;
	if (siteUsesLimaOpenLiteSpeed(localSite)) {
		const runtimePath = guestPathForHostPath(hostPath);
		return {
			mode: "lima",
			label: "Lima VM / OpenLiteSpeed",
			instanceName: runtimeInstanceName,
			webserver: "OpenLiteSpeed",
			siteSlug: localSite.slug,
			hostPath,
			runtimePath,
			commandPath: runtimePath,
			phpBinary: phpBinaryPath(localSite.phpVersion),
		};
	}
	return {
		mode: "host",
		label: "macOS host",
		siteSlug: localSite.slug,
		hostPath,
		runtimePath: hostPath,
		commandPath: hostPath,
		phpBinary: "",
	};
}

async function runtimeWpCliStatus(site) {
	const context = wpRuntimeContextForSite(site);
	if (context.mode !== "lima") {
		const wp = await commandExists("wp");
		return {
			ok: wp.ok,
			context,
			stdout: wp.ok ? `Host WP-CLI ready: ${wp.path}` : "Host WP-CLI is missing.",
			stderr: "",
		};
	}
	const script = [
		"set -u",
		`export MRN_WP_CLI_PHP=${shellQuote(context.phpBinary)}`,
		`if [ ! -x "$MRN_WP_CLI_PHP" ]; then echo "Runtime PHP missing: $MRN_WP_CLI_PHP" >&2; exit 2; fi`,
		`command -v wp >/dev/null 2>&1 || { echo "Runtime WP-CLI missing inside Lima VM." >&2; exit 127; }`,
		`wp --info >/dev/null`,
		`echo "Runtime WP-CLI ready: $(command -v wp)"`,
	].join("\n");
	const result = await runProcess(
		"limactl",
		["shell", runtimeInstanceName, "--", "bash", "-lc", script],
		{ cwd: "/", timeoutMs: 30000, trackJob: false },
	);
	return {
		ok: result.code === 0,
		context,
		stdout: result.stdout,
		stderr: result.stderr,
		result,
	};
}

async function ensureRuntimeWpCli(site) {
	const context = wpRuntimeContextForSite(site);
	if (context.mode !== "lima") {
		return runtimeWpCliStatus(site);
	}
	if (runtimeWpCliReady) {
		const status = await runtimeWpCliStatus(site);
		if (status.ok) {
			return status;
		}
		runtimeWpCliReady = false;
	}
	const before = await runtimeWpCliStatus(site);
	if (before.ok) {
		runtimeWpCliReady = true;
		return before;
	}
	const install = await runProcess(
		"limactl",
		["shell", runtimeInstanceName, "--", "bash", "-lc", ["set -euo pipefail", buildRuntimeWpCliInstallBash({ sudo: true })].join("\n")],
		{ cwd: "/", timeoutMs: Number(process.env.MRN_LOCAL_HUB_WP_CLI_INSTALL_TIMEOUT_MS || "120000"), trackJob: false },
	);
	runtimeWpCliReady = install.code === 0;
	return {
		ok: install.code === 0,
		context,
		stdout: install.stdout,
		stderr: install.stderr,
		result: install,
		installed: install.code === 0,
	};
}

function runtimeWpCliUnavailableResult(site, args, status, startedAt) {
	const context = wpRuntimeContextForSite(site);
	const stderr = [
		status?.stderr,
		status?.result?.stderr,
		"WP-CLI is not available inside the Lima/OpenLiteSpeed runtime, so the Hub did not fall back to host macOS.",
		"Run Runtime Repair or Bootstrap Runtime, then retry so WordPress status matches the browser-served site.",
	].filter(Boolean).join("\n");
	return {
		command: "wp",
		args: [`--path=${context.commandPath}`, ...args],
		code: status?.result?.code || 127,
		stdout: status?.stdout || status?.result?.stdout || "",
		stderr,
		durationMs: Date.now() - startedAt,
		runtimeContext: context,
	};
}

async function runWpCli(site, args, options = {}) {
	const localSite = siteWithRuntimeDefaults(site);
	const startedAt = Date.now();
	const context = wpRuntimeContextForSite(localSite);
	const wpArgs = [`--path=${context.commandPath}`, ...args];
	if (context.mode === "lima") {
		const runtime = options.ensureWpCli === false ? await runtimeWpCliStatus(localSite) : await ensureRuntimeWpCli(localSite);
		if (!runtime.ok) {
			return runtimeWpCliUnavailableResult(localSite, args, runtime, startedAt);
		}
		const result = await runProcess(
			"limactl",
			[
				"shell",
				runtimeInstanceName,
				"--",
				"env",
				`MRN_WP_CLI_PHP=${context.phpBinary}`,
				"wp",
				...wpArgs,
			],
			{
				cwd: options.hostCwd || "/",
				timeoutMs: options.timeoutMs,
				trackJob: options.trackJob,
				siteSlug: options.siteSlug || localSite.slug,
			},
		);
		return {
			...result,
			command: "wp",
			args: wpArgs,
			durationMs: Date.now() - startedAt,
			runtimeContext: context,
			runtimeSetup: runtime.installed ? {
				installed: true,
				stdout: runtime.stdout,
				stderr: runtime.stderr,
			} : null,
		};
	}
	const result = await runProcess("wp", wpArgs, {
		cwd: options.cwd || localSite.publicPath,
		timeoutMs: options.timeoutMs,
		trackJob: options.trackJob,
		siteSlug: options.siteSlug || localSite.slug,
	});
	return {
		...result,
		runtimeContext: context,
	};
}

function firstRuntimeContext(...results) {
	return results.find((result) => result?.runtimeContext)?.runtimeContext || null;
}

function buildPhpExtProcessorConfig(version) {
	const normalized = normalizePhpVersion(version);
	const handler = phpHandlerName(normalized);
	return `extProcessor ${handler}{
    type                            lsapi
    address                         uds://tmp/lshttpd/${handler}.sock
    maxConns                        10
    env                             PHP_LSAPI_CHILDREN=10
    env                             LSAPI_AVOID_FORK=200M
    initTimeout                     60
    retryTimeout                    0
    persistConn                     1
    respBuffer                      0
    autoStart                       1
    path                            ${handler}/bin/lsphp
    backlog                         100
    instances                       1
    priority                        0
    memSoftLimit                    0
    memHardLimit                    0
    procSoftLimit                   1400
    procHardLimit                   1500
}
`;
}

function buildPhpExtProcessorBundle(versions = phpRuntimeVersions) {
	return versions.map(buildPhpExtProcessorConfig).join("\n");
}

function buildVhostConfig({ site, hostname, guestPublicPath, guestLocalRoot }) {
	const docRoot = `${guestPublicPath.replace(/\/+$/, "")}/`;
	const logRoot = `${guestLocalRoot.replace(/\/+$/, "")}/logs`;
	const phpHandler = phpHandlerName(site.phpVersion);
	return `docRoot ${docRoot}
enableGzip 1

scriptHandler {
  add lsapi:${phpHandler} php
}

index {
  useServer 1
  indexFiles index.php, index.html
  autoIndex 0
}

context / {
  allowBrowse 1
  location $DOC_ROOT/
  rewrite  {
    enable 1
    RewriteFile .htaccess
  }
}

rewrite {
  enable 1
  autoLoadHtaccess 1
  logLevel 0
}

expires {
  enableExpires 1
}

errorlog ${logRoot}/ols-error.log{
  logLevel WARN
  rollingSize 10M
  useServer 0
}

accessLog ${logRoot}/ols-access.log{
  compressArchive 0
  logReferer 1
  keepDays 14
  rollingSize 10M
  logUserAgent 1
  useServer 0
}

accessControl {
  deny
  allow *
}

awstats {
  updateInterval 86400
  workingDir ${guestLocalRoot}/awstats
  updateOffset 0
  siteDomain ${hostname}
  siteAliases ${hostname}
  updateMode 0
  awstatsURI /awstats/
}

general {
  enableContextAC 0
}
`;
}

function buildProvisionSiteScript(site) {
	const phpVersion = normalizePhpVersion(site.phpVersion);
	const hostname = runtimeHostnameForSite(site);
	const vhostName = vhostNameForSite(site);
	const guestLocalRoot = guestPathForHostPath(site.localRoot);
	const guestPublicPath = guestPathForHostPath(site.publicPath);
	const vhostConfig = buildVhostConfig({ site, hostname, guestPublicPath, guestLocalRoot });
	const phpInstallScript = buildPhpInstallBash([phpVersion], { sudo: true, requiredVersion: phpVersion });
	const phpExtProcessors = buildPhpExtProcessorBundle();
	const vhostBlock = `virtualHost ${vhostName}{
    vhRoot                   ${guestLocalRoot}
    allowSymbolLink          1
    enableScript             1
    restrained               0
    maxKeepAliveReq
    smartKeepAlive
    setUIDMode               0
    chrootMode               0
    configFile               conf/vhosts/${vhostName}/vhconf.conf
}
`;
	const dbName = mysqlIdentifier(site.dbName, "DB name");
	const dbUser = mysqlString(site.dbUser);
	const dbPassword = mysqlString(site.dbPassword);

	return {
		hostname,
		vhostName,
		guestPublicPath,
		script: `#!/usr/bin/env bash
set -euo pipefail

VHOST_NAME=${shellQuote(vhostName)}
HOSTNAME_VALUE=${shellQuote(hostname)}
VHCONF="/usr/local/lsws/conf/vhosts/\${VHOST_NAME}/vhconf.conf"

export DEBIAN_FRONTEND=noninteractive
if [ ! -x ${shellQuote(phpBinaryPath(phpVersion))} ]; then
  sudo apt-get update
${phpInstallScript.split("\n").map((line) => `  ${line}`).join("\n")}
fi

sudo install -d -m 0755 "/usr/local/lsws/conf/vhosts/\${VHOST_NAME}"
sudo tee "\${VHCONF}" >/dev/null <<'MRN_VHOST_CONF'
${vhostConfig}
MRN_VHOST_CONF

sudo mysql <<'MRN_SQL'
CREATE DATABASE IF NOT EXISTS ${dbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS ${dbUser}@'localhost' IDENTIFIED BY ${dbPassword};
ALTER USER ${dbUser}@'localhost' IDENTIFIED BY ${dbPassword};
CREATE USER IF NOT EXISTS ${dbUser}@'%' IDENTIFIED BY ${dbPassword};
ALTER USER ${dbUser}@'%' IDENTIFIED BY ${dbPassword};
GRANT ALL PRIVILEGES ON ${dbName}.* TO ${dbUser}@'localhost';
GRANT ALL PRIVILEGES ON ${dbName}.* TO ${dbUser}@'%';
FLUSH PRIVILEGES;
MRN_SQL

export MRN_VHOST_NAME="\${VHOST_NAME}"
export MRN_HOSTNAME_VALUE="\${HOSTNAME_VALUE}"
export MRN_VHOST_BLOCK=${shellQuote(vhostBlock)}
export MRN_PHP_EXT_PROCESSORS=${shellQuote(phpExtProcessors)}
sudo -E python3 <<'PY'
import os
import re
from pathlib import Path

conf_path = Path("/usr/local/lsws/conf/httpd_config.conf")
text = conf_path.read_text()
vhost = os.environ["MRN_VHOST_NAME"]
hostname = os.environ["MRN_HOSTNAME_VALUE"]
vhost_block = os.environ["MRN_VHOST_BLOCK"].rstrip() + "\\n"
php_ext_processors = os.environ["MRN_PHP_EXT_PROCESSORS"].rstrip() + "\\n"

text = re.sub(r"\\n?virtualHost\\s+" + re.escape(vhost) + r"\\s*\\{.*?\\n\\}", "\\n", text, flags=re.S)
text = re.sub(r"(?m)^(\\s*path\\s+)lsphp[0-9]+/bin/lsphp\\s*$", r"\\1lsphp84/bin/lsphp", text)
text = re.sub(r"\\n?extProcessor\\s+lsphp(?:74|81|82|83|84)\\s*\\{.*?\\n\\}", "\\n", text, flags=re.S)

script_handler_match = re.search(r"\\nscriptHandler\\s*\\{", text)
rails_match = re.search(r"\\nrailsDefaults\\s*\\{", text)
insert_match = script_handler_match or rails_match
if insert_match:
    text = text[:insert_match.start() + 1] + php_ext_processors + "\\n" + text[insert_match.start() + 1:]
else:
    text = text.rstrip() + "\\n\\n" + php_ext_processors + "\\n"

listener_re = re.compile(r"listener\\s+Default\\s*\\{(?P<body>.*?)\\n\\}", re.S)
match = listener_re.search(text)
map_line = f"    map                      {vhost} {hostname}"
if match:
    body = match.group("body")
    lines = [
        line for line in body.splitlines()
        if not re.match(r"\\s*map\\s+" + re.escape(vhost) + r"\\s+", line)
    ]
    wildcard_index = next(
        (index for index, line in enumerate(lines) if re.match(r"\\s*map\\s+\\S+\\s+\\*\\s*$", line)),
        len(lines),
    )
    lines.insert(wildcard_index, map_line)
    listener = "listener Default{\\n" + "\\n".join(lines).rstrip() + "\\n}"
    text = text[:match.start()] + listener + text[match.end():]
else:
    text += "\\nlistener Default{\\n    address                  *:8088\\n    secure                   0\\n" + map_line + "\\n}\\n"

listener_match = re.search(r"\\nlistener\\s+Default\\s*\\{", text)
if listener_match:
    text = text[:listener_match.start() + 1] + vhost_block + "\\n" + text[listener_match.start() + 1:]
else:
    text = text.rstrip() + "\\n\\n" + vhost_block

conf_path.write_text(text)
PY

sudo /usr/local/lsws/bin/lswsctrl restart
printf 'Provisioned vhost: %s\\n' "\${VHOST_NAME}"
printf 'Mapped host: %s\\n' "\${HOSTNAME_VALUE}"
printf 'Document root: %s\\n' ${shellQuote(guestPublicPath)}
printf 'PHP target: %s\\n' ${shellQuote(phpVersion)}
${shellQuote(phpBinaryPath(phpVersion))} -r 'echo "PHP active binary: " . PHP_VERSION . PHP_EOL;' || true
printf 'Database: %s\\n' ${shellQuote(site.dbName)}
printf 'DB user: %s\\n' ${shellQuote(site.dbUser)}
`,
	};
}

function buildApplyPhpVersionScript(site) {
	const phpVersion = normalizePhpVersion(site.phpVersion);
	const hostname = runtimeHostnameForSite(site);
	const vhostName = vhostNameForSite(site);
	const guestLocalRoot = guestPathForHostPath(site.localRoot);
	const guestPublicPath = guestPathForHostPath(site.publicPath);
	const vhostConfig = buildVhostConfig({ site, hostname, guestPublicPath, guestLocalRoot });
	const phpInstallScript = buildPhpInstallBash([phpVersion], { sudo: true, requiredVersion: phpVersion });
	const phpExtProcessors = buildPhpExtProcessorBundle();
	const vhostBlock = `virtualHost ${vhostName}{
    vhRoot                   ${guestLocalRoot}
    allowSymbolLink          1
    enableScript             1
    restrained               0
    maxKeepAliveReq
    smartKeepAlive
    setUIDMode               0
    chrootMode               0
    configFile               conf/vhosts/${vhostName}/vhconf.conf
}
`;

	return {
		hostname,
		vhostName,
		guestPublicPath,
		script: `#!/usr/bin/env bash
set -euo pipefail

VHOST_NAME=${shellQuote(vhostName)}
HOSTNAME_VALUE=${shellQuote(hostname)}
VHCONF="/usr/local/lsws/conf/vhosts/\${VHOST_NAME}/vhconf.conf"

export DEBIAN_FRONTEND=noninteractive
if [ ! -x ${shellQuote(phpBinaryPath(phpVersion))} ]; then
  sudo apt-get update
${phpInstallScript.split("\n").map((line) => `  ${line}`).join("\n")}
fi

sudo install -d -m 0755 "/usr/local/lsws/conf/vhosts/\${VHOST_NAME}"
sudo tee "\${VHCONF}" >/dev/null <<'MRN_VHOST_CONF'
${vhostConfig}
MRN_VHOST_CONF

export MRN_VHOST_NAME="\${VHOST_NAME}"
export MRN_HOSTNAME_VALUE="\${HOSTNAME_VALUE}"
export MRN_VHOST_BLOCK=${shellQuote(vhostBlock)}
export MRN_PHP_EXT_PROCESSORS=${shellQuote(phpExtProcessors)}
sudo -E python3 <<'PY'
import os
import re
from pathlib import Path

conf_path = Path("/usr/local/lsws/conf/httpd_config.conf")
text = conf_path.read_text()
vhost = os.environ["MRN_VHOST_NAME"]
hostname = os.environ["MRN_HOSTNAME_VALUE"]
vhost_block = os.environ["MRN_VHOST_BLOCK"].rstrip() + "\\n"
php_ext_processors = os.environ["MRN_PHP_EXT_PROCESSORS"].rstrip() + "\\n"

text = re.sub(r"\\n?virtualHost\\s+" + re.escape(vhost) + r"\\s*\\{.*?\\n\\}", "\\n", text, flags=re.S)
text = re.sub(r"(?m)^(\\s*path\\s+)lsphp[0-9]+/bin/lsphp\\s*$", r"\\1lsphp84/bin/lsphp", text)
text = re.sub(r"\\n?extProcessor\\s+lsphp(?:74|81|82|83|84)\\s*\\{.*?\\n\\}", "\\n", text, flags=re.S)

script_handler_match = re.search(r"\\nscriptHandler\\s*\\{", text)
rails_match = re.search(r"\\nrailsDefaults\\s*\\{", text)
insert_match = script_handler_match or rails_match
if insert_match:
    text = text[:insert_match.start() + 1] + php_ext_processors + "\\n" + text[insert_match.start() + 1:]
else:
    text = text.rstrip() + "\\n\\n" + php_ext_processors + "\\n"

listener_re = re.compile(r"listener\\s+Default\\s*\\{(?P<body>.*?)\\n\\}", re.S)
match = listener_re.search(text)
map_line = f"    map                      {vhost} {hostname}"
if match:
    body = match.group("body")
    lines = [
        line for line in body.splitlines()
        if not re.match(r"\\s*map\\s+" + re.escape(vhost) + r"\\s+", line)
    ]
    wildcard_index = next(
        (index for index, line in enumerate(lines) if re.match(r"\\s*map\\s+\\S+\\s+\\*\\s*$", line)),
        len(lines),
    )
    lines.insert(wildcard_index, map_line)
    listener = "listener Default{\\n" + "\\n".join(lines).rstrip() + "\\n}"
    text = text[:match.start()] + listener + text[match.end():]
else:
    text += "\\nlistener Default{\\n    address                  *:8088\\n    secure                   0\\n" + map_line + "\\n}\\n"

listener_match = re.search(r"\\nlistener\\s+Default\\s*\\{", text)
if listener_match:
    text = text[:listener_match.start() + 1] + vhost_block + "\\n" + text[listener_match.start() + 1:]
else:
    text = text.rstrip() + "\\n\\n" + vhost_block

conf_path.write_text(text)
PY

sudo /usr/local/lsws/bin/lswsctrl restart
printf 'Applied PHP target: %s\\n' ${shellQuote(phpVersion)}
printf 'Vhost: %s\\n' "\${VHOST_NAME}"
printf 'Mapped host: %s\\n' "\${HOSTNAME_VALUE}"
printf 'Handler: %s\\n' ${shellQuote(phpHandlerName(phpVersion))}
${shellQuote(phpBinaryPath(phpVersion))} -r 'echo "PHP active binary: " . PHP_VERSION . PHP_EOL;' || true
`,
	};
}

function replaceWpDefine(content, name, value, options = {}) {
	const renderedValue = options.raw ? String(value) : `'${phpSingleQuotedString(value)}'`;
	const line = `define( '${name}', ${renderedValue} );`;
	const pattern = new RegExp(`^\\s*define\\s*\\(\\s*['"]${name}['"]\\s*,.*?\\)\\s*;\\s*$`, "m");
	if (pattern.test(content)) {
		return {
			content: content.replace(pattern, line),
			inserted: false,
		};
	}
	return {
		content,
		inserted: line,
	};
}

function localDbHostExpression(site) {
	const hostDb = `${site.dbHost}:${site.dbPort}`;
	return `( PHP_SAPI === 'cli' && strpos( __DIR__, '/Users/' ) === 0 ? '${phpSingleQuotedString(hostDb)}' : '127.0.0.1' )`;
}

async function writeLocalWpConfig(site) {
	const configPath = path.join(site.publicPath, "wp-config.php");
	if (!(await pathExists(configPath))) {
		return {
			updated: false,
			configPath,
			message: "wp-config.php was not present yet; DB constants will be patched after files are pulled.",
		};
	}

	const marker = "// MRN Local Hub local DB settings.";
	const httpsProxyMarker = "// MRN Local Hub forwarded HTTPS handling.";
	const original = await fsp.readFile(configPath, "utf8");
	let content = original;
	const missing = [];
	const definitions = [
		["DB_NAME", site.dbName],
		["DB_USER", site.dbUser],
		["DB_PASSWORD", site.dbPassword],
		["DB_HOST", localDbHostExpression(site), { raw: true }],
	];

	for (const [name, value, options] of definitions) {
		const result = replaceWpDefine(content, name, value, options);
		content = result.content;
		if (result.inserted) {
			missing.push(result.inserted);
		}
	}

	if (missing.length) {
		const block = `${marker}\n${missing.join("\n")}\n\n`;
		const stopMatch = content.match(/^.*That's all, stop editing!.*$/im);
		const requireMatch = content.match(/^.*require_once\s+ABSPATH.*$/im);
		const insertAt = stopMatch ? stopMatch.index : requireMatch ? requireMatch.index : content.length;
		content = `${content.slice(0, insertAt)}${block}${content.slice(insertAt)}`;
	} else if (!content.includes(marker)) {
		content = content.replace(
			/^(\s*define\s*\(\s*['"]DB_NAME['"]\s*,.*?\)\s*;\s*)$/m,
			`${marker}\n$1`,
		);
	}

	if (!content.includes(httpsProxyMarker)) {
		const httpsProxyBlock = `${httpsProxyMarker}\nif ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {\n\t$_SERVER['HTTPS'] = 'on';\n}\n\n`;
		const stopMatch = content.match(/^.*That's all, stop editing!.*$/im);
		const requireMatch = content.match(/^.*require_once\s+ABSPATH.*$/im);
		const insertAt = stopMatch ? stopMatch.index : requireMatch ? requireMatch.index : content.length;
		content = `${content.slice(0, insertAt)}${httpsProxyBlock}${content.slice(insertAt)}`;
	}

	if (content === original) {
		return {
			updated: false,
			configPath,
			message: "wp-config.php already uses the local DB settings.",
		};
	}

	let backupPath = "";
	if (!original.includes(marker)) {
		backupPath = path.join(site.localRoot, "backups", `${timestampSlug()}-wp-config-live.php`);
		await fsp.mkdir(path.dirname(backupPath), { recursive: true });
		await fsp.copyFile(configPath, backupPath);
	}
	await fsp.writeFile(configPath, content, "utf8");
	return {
		updated: true,
		configPath,
		backupPath,
		message: backupPath
			? `Patched wp-config.php and saved live backup to ${backupPath}.`
			: "Patched wp-config.php with local DB settings.",
	};
}

async function ensureWordPressHtaccess(site) {
	const htaccessPath = path.join(site.publicPath, ".htaccess");
	const indexPath = path.join(site.publicPath, "index.php");
	if (!(await pathExists(indexPath))) {
		return {
			updated: false,
			htaccessPath,
			message: "WordPress index.php was not present yet; .htaccess will be created after files are pulled.",
		};
	}
	if (await pathExists(htaccessPath)) {
		return {
			updated: false,
			htaccessPath,
			message: ".htaccess already exists.",
		};
	}

	const content = `# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
`;
	await fsp.writeFile(htaccessPath, content, "utf8");
	return {
		updated: true,
		htaccessPath,
		message: `Created ${htaccessPath} for WordPress pretty permalinks.`,
	};
}

function formatWpConfigResult(result) {
	return result.updated ? result.message : `wp-config.php: ${result.message}`;
}

function formatHtaccessResult(result) {
	return result.updated ? result.message : `.htaccess: ${result.message}`;
}

function siteUrl(site, pathname = "/") {
	const base = new URL(site.localUrl || defaultLocalUrl(site.slug));
	return new URL(pathname, `${base.origin}/`).toString();
}

async function probeSitePhpVersion(site) {
	const localSite = siteWithRuntimeDefaults(site);
	const token = crypto.randomBytes(12).toString("hex");
	const filename = `mrn-local-php-probe-${token}.php`;
	const probePath = path.join(localSite.publicPath, filename);
	const startedAt = Date.now();
	const hostname = runtimeHostnameForSite(localSite);
	const probeUrl = `http://127.0.0.1:8088/${filename}`;
	const content = `<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode(array(
	'version' => PHP_VERSION,
	'sapi' => PHP_SAPI,
	'binary' => PHP_BINARY,
));
`;
	await ensureSiteDirectories(localSite);
	await fsp.writeFile(probePath, content, "utf8");
	try {
		const response = await runProcess(
			"limactl",
			[
				"shell",
				runtimeInstanceName,
				"--",
				"curl",
				"-sS",
				"-L",
				"-H",
				`Host: ${hostname}`,
				probeUrl,
			],
			{ timeoutMs: 30000 },
		);
		let payload = null;
		try {
			payload = JSON.parse(response.stdout || "{}");
		} catch {
			payload = null;
		}
		const version = payload?.version ? String(payload.version) : "";
		const majorMinor = version.match(/^\d+\.\d+/)?.[0] || "";
		const ok = response.code === 0 && Boolean(majorMinor);
		return {
			code: ok ? 0 : 1,
			command: "php-probe",
			args: [localSite.slug, hostname, probeUrl],
			stdout: ok
				? `Active web PHP: ${version} (${payload.sapi || "unknown SAPI"})`
				: `Active web PHP could not be detected through OpenLiteSpeed for ${hostname}.`,
			stderr: ok ? "" : [response.stderr, response.stdout].filter(Boolean).join("\n").slice(0, 2000),
			durationMs: Date.now() - startedAt,
			url: probeUrl,
			status: response.code,
			version,
			majorMinor,
			sapi: payload?.sapi || "",
			binary: payload?.binary || "",
		};
	} finally {
		await fsp.rm(probePath, { force: true }).catch(() => {});
	}
}

function phpStatusForProbe(targetVersion, probeResult) {
	const target = normalizePhpVersion(targetVersion);
	if (!probeResult || probeResult.code !== 0) {
		return "unknown";
	}
	return probeResult.majorMinor === target ? "applied" : "mismatch";
}

async function applySitePhpVersion(site, requestedPhpVersion) {
	const targetPhpVersion = normalizePhpVersion(requestedPhpVersion || site.phpVersion);
	const localSite = siteWithRuntimeDefaults({ ...site, phpVersion: targetPhpVersion });
	await writeManifest({
		...localSite,
		phpStatus: "applying",
	});
	const applyScript = buildApplyPhpVersionScript(localSite);
	const applyResult = await runProcess("limactl", ["shell", runtimeInstanceName, "--", "bash", "-s"], {
		input: applyScript.script,
		timeoutMs: Number(process.env.MRN_LOCAL_HUB_PHP_APPLY_TIMEOUT_MS || "900000"),
	});
	if (applyResult.code !== 0) {
		const failedSite = {
			...localSite,
			phpStatus: "missing",
			phpCheckedAt: new Date().toISOString(),
		};
		await writeManifest(failedSite);
		return {
			...applyResult,
			command: "apply-php-version",
			args: [localSite.slug, targetPhpVersion],
			stdout: [
				`Target PHP: ${phpVersionLabel(targetPhpVersion)}`,
				applyResult.stdout,
				legacyPhpVersions.has(targetPhpVersion)
					? "Legacy PHP 7.4 may require a legacy runtime channel if the current LiteSpeed repo does not provide lsphp74."
					: "",
			].filter(Boolean).join("\n"),
			site: failedSite,
			phpRuntime: {
				target: targetPhpVersion,
				status: "missing",
				legacy: legacyPhpVersions.has(targetPhpVersion),
				handler: phpHandlerName(targetPhpVersion),
				binary: phpBinaryPath(targetPhpVersion),
			},
		};
	}

	const appliedSite = siteWithRuntimeDefaults(localSite, { runtimeStatus: "provisioned" });
	const probe = await probeSitePhpVersion(appliedSite);
	const phpStatus = phpStatusForProbe(targetPhpVersion, probe);
	const finalSite = siteWithRuntimeDefaults({
		...appliedSite,
		activePhpVersion: probe.majorMinor || "",
		activePhpHandler: phpStatus === "applied" ? phpHandlerName(targetPhpVersion) : "",
		phpStatus,
		phpCheckedAt: new Date().toISOString(),
	});
	await writeManifest(finalSite);
	return {
		...applyResult,
		code: applyResult.code || probe.code,
		command: "apply-php-version",
		args: [finalSite.slug, targetPhpVersion],
		stdout: [
			`Target PHP: ${phpVersionLabel(targetPhpVersion)}`,
			applyResult.stdout,
			probe.stdout,
			phpStatus === "applied"
				? `PHP target confirmed for ${finalSite.slug}.`
				: `PHP target mismatch: expected ${targetPhpVersion}, detected ${probe.majorMinor || "unknown"}.`,
			legacyPhpVersions.has(targetPhpVersion)
				? "Legacy PHP 7.4 is for temporary upgrade testing only."
				: "",
		].filter(Boolean).join("\n"),
		stderr: [applyResult.stderr, probe.stderr].filter(Boolean).join("\n"),
		durationMs: (applyResult.durationMs || 0) + (probe.durationMs || 0),
		site: finalSite,
		vhost: {
			name: applyScript.vhostName,
			hostname: applyScript.hostname,
			documentRoot: applyScript.guestPublicPath,
		},
		phpRuntime: {
			target: targetPhpVersion,
			active: probe.majorMinor || "",
			fullVersion: probe.version || "",
			status: phpStatus,
			legacy: legacyPhpVersions.has(targetPhpVersion),
			handler: phpHandlerName(targetPhpVersion),
			binary: phpBinaryPath(targetPhpVersion),
			checkedAt: finalSite.phpCheckedAt,
		},
	};
}

function legacyLocalUrlsForSite(site) {
	const slug = normalizeSlug(site.slug);
	return [
		`http://${slug}.test`,
		`http://${slug}.localhost`,
		`http://${slug}.localhost:8088`,
		`https://${slug}.localhost:8088`,
		`https://${slug}.localhost:8443`,
	].filter((value, index, items) => value !== site.localUrl && items.indexOf(value) === index);
}

async function normalizeLocalDatabaseUrls(site) {
	const localSite = siteWithRuntimeDefaults(site);
	const wpConfigPath = path.join(localSite.publicPath, "wp-config.php");
	if (!(await pathExists(wpConfigPath))) {
		return {
			code: 0,
			command: "normalize-local-db-urls",
			args: [localSite.slug],
			stdout: "Local DB URL normalization skipped; wp-config.php is not present yet.",
			stderr: "",
			durationMs: 0,
			skipped: true,
		};
	}

	const startedAt = Date.now();
	const wpBaseArgs = ["--skip-plugins", "--skip-themes"];
	const homeCheck = await runWpCli(localSite, [...wpBaseArgs, "option", "get", "home"], {
		cwd: localSite.localRoot,
		timeoutMs: 30000,
	});
	if (homeCheck.code !== 0) {
		return {
			code: 0,
			command: "normalize-local-db-urls",
			args: [localSite.slug],
			stdout: "Local DB URL normalization skipped; the WordPress database is not ready yet.",
			stderr: homeCheck.stderr,
			durationMs: Date.now() - startedAt,
			skipped: true,
		};
	}

	const replacements = [];
	for (const oldUrl of legacyLocalUrlsForSite(localSite)) {
		const result = await runWpCli(
			localSite,
			[
				...wpBaseArgs,
				"search-replace",
				oldUrl,
				localSite.localUrl,
				"--all-tables",
				"--skip-columns=guid",
			],
			{ cwd: localSite.localRoot },
		);
		replacements.push({ oldUrl, result });
	}

	const homeUpdate = await runWpCli(localSite, [...wpBaseArgs, "option", "update", "home", localSite.localUrl], {
		cwd: localSite.localRoot,
		timeoutMs: 30000,
	});
	const siteUrlUpdate = await runWpCli(localSite, [...wpBaseArgs, "option", "update", "siteurl", localSite.localUrl], {
		cwd: localSite.localRoot,
		timeoutMs: 30000,
	});
	const failures = [
		...replacements.map((item) => item.result).filter((result) => result.code !== 0),
		homeUpdate.code !== 0 ? homeUpdate : null,
		siteUrlUpdate.code !== 0 ? siteUrlUpdate : null,
	].filter(Boolean);

	return {
		code: failures.length ? 1 : 0,
		command: "normalize-local-db-urls",
		args: [localSite.slug, localSite.localUrl],
		stdout: [
			`Normalized local WordPress URL to ${localSite.localUrl}.`,
			...replacements.map((item) => `Checked ${item.oldUrl}: ${item.result.code === 0 ? "ok" : "failed"}`),
			homeUpdate.stdout,
			siteUrlUpdate.stdout,
		].filter(Boolean).join("\n"),
		stderr: [
			...replacements.map((item) => item.result.stderr).filter(Boolean),
			homeUpdate.stderr,
			siteUrlUpdate.stderr,
		].filter(Boolean).join("\n"),
		durationMs: Date.now() - startedAt,
		replacements: replacements.map((item) => ({ oldUrl: item.oldUrl, code: item.result.code })),
		home: homeUpdate.code,
		siteurl: siteUrlUpdate.code,
		runtimeContext: firstRuntimeContext(homeCheck, ...replacements.map((item) => item.result), homeUpdate, siteUrlUpdate),
	};
}

function smokeTitle(text) {
	const match = String(text || "").match(/<title[^>]*>([\s\S]*?)<\/title>/i);
	return match ? match[1].replace(/\s+/g, " ").trim() : "";
}

function smokeCommonFailure(text) {
	const body = String(text || "");
	if (body.includes("Error establishing a database connection")) {
		return "WordPress reports a database connection error.";
	}
	if (body.includes("Proudly powered by LiteSpeed Web Server") && body.includes("404 Not Found")) {
		return "OpenLiteSpeed returned its generic 404 page.";
	}
	if (/<title>\s*404 Not Found/i.test(body)) {
		return "Response title is 404 Not Found.";
	}
	return "";
}

let smokeLocalCaLoaded = false;
let smokeLocalCa = null;

function smokeLocalCaForUrl(url) {
	let parsed = null;
	try {
		parsed = new URL(url);
	} catch {
		return null;
	}
	if (parsed.protocol !== "https:" || (parsed.hostname !== "localhost" && !parsed.hostname.endsWith(".localhost"))) {
		return null;
	}
	if (!smokeLocalCaLoaded) {
		smokeLocalCaLoaded = true;
		const caroot = childProcess.spawnSync("mkcert", ["-CAROOT"], {
			encoding: "utf8",
			timeout: 5000,
		});
		if (caroot.status === 0 && caroot.stdout.trim()) {
			const rootCa = path.join(caroot.stdout.trim(), "rootCA.pem");
			try {
				smokeLocalCa = fs.readFileSync(rootCa);
			} catch {
				smokeLocalCa = null;
			}
		}
	}
	return smokeLocalCa;
}

async function fetchSmokeTarget(url, options = {}) {
	const timeoutMs = options.timeoutMs || 15000;
	const maxTextChars = options.maxTextChars || 250000;
	const redirects = options.redirects || 0;
	const startedAt = Date.now();
	return new Promise((resolve) => {
		let parsed = null;
		try {
			parsed = new URL(url);
		} catch (error) {
			resolve({
				ok: false,
				url,
				finalUrl: url,
				status: 0,
				contentType: "",
				text: "",
				error: error.message,
				durationMs: Date.now() - startedAt,
			});
			return;
		}

		const requestOptions = {
			headers: {
				"user-agent": "MRN Local Hub Smoke Check",
			},
		};
		if (parsed.protocol === "https:") {
			const ca = smokeLocalCaForUrl(url);
			if (ca) {
				requestOptions.ca = ca;
			}
		}

		const transport = parsed.protocol === "https:" ? https : http;
		const request = transport.request(parsed, requestOptions, (response) => {
			const status = response.statusCode || 0;
			const location = response.headers.location || "";
			if (status >= 300 && status < 400 && location && redirects < 5) {
				response.resume();
				const nextUrl = new URL(location, parsed).toString();
				fetchSmokeTarget(nextUrl, { ...options, redirects: redirects + 1 }).then((result) => {
					resolve({
						...result,
						url,
						durationMs: Date.now() - startedAt,
					});
				});
				return;
			}

			const chunks = [];
			let collected = 0;
			response.on("data", (chunk) => {
				if (collected < maxTextChars) {
					const remaining = maxTextChars - collected;
					const slice = chunk.length > remaining ? chunk.subarray(0, remaining) : chunk;
					chunks.push(slice);
					collected += slice.length;
				}
			});
			response.on("end", () => {
				const text = Buffer.concat(chunks).toString("utf8");
				resolve({
					ok: status >= 200 && status < 400,
					url,
					finalUrl: url,
					status,
					contentType: response.headers["content-type"] || "",
					text,
					durationMs: Date.now() - startedAt,
				});
			});
		});
		request.setTimeout(timeoutMs, () => {
			request.destroy(new Error(`Timed out after ${timeoutMs}ms.`));
		});
		request.on("error", (error) => {
			resolve({
				ok: false,
				url,
				finalUrl: url,
				status: 0,
				contentType: "",
				text: "",
				error: error.message,
				durationMs: Date.now() - startedAt,
			});
		});
		request.end();
	});
}

function smokeCheck(status, label, detail, extra = {}) {
	return {
		status,
		label,
		detail,
		...extra,
	};
}

function formatSmokeLine(check) {
	const prefix = check.status === "pass" ? "PASS" : check.status === "warn" ? "WARN" : "FAIL";
	return `${prefix} ${check.label}: ${check.detail}`;
}

const adminAccessPluginCatalog = new Map([
	["wp-defender", "WPMU DEV Defender"],
	["defender-security", "WPMU DEV Defender"],
	["wordfence", "Wordfence Security"],
	["better-wp-security", "Solid Security / iThemes Security"],
	["solid-security-basic", "Solid Security"],
	["solid-security-pro", "Solid Security Pro"],
	["all-in-one-wp-security-and-firewall", "All-In-One Security"],
	["wp-cerber", "WP Cerber"],
	["wp-cerber-security", "WP Cerber"],
	["sucuri-scanner", "Sucuri Security"],
	["bulletproof-security", "BulletProof Security"],
	["shield-security", "Shield Security"],
	["wp-simple-firewall", "Shield Security"],
	["malcare-security", "MalCare Security"],
	["loginizer", "Loginizer"],
	["limit-login-attempts-reloaded", "Limit Login Attempts Reloaded"],
	["limit-login-attempts", "Limit Login Attempts"],
	["wps-hide-login", "WPS Hide Login"],
	["rename-wp-login", "Rename wp-login.php"],
	["change-wp-admin-login", "Change wp-admin Login"],
	["hide-my-wp", "Hide My WP"],
	["hide-my-wp-security", "Hide My WP Security"],
	["wp-2fa", "WP 2FA"],
	["two-factor", "Two-Factor"],
	["two-factor-authentication", "Two-Factor Authentication"],
	["miniorange-2-factor-authentication", "miniOrange 2FA"],
]);

function adminAccessSecurityBlocked(text) {
	return String(text || "").includes("temporarily forbidden for security reasons")
		|| String(text || "").includes("This feature is temporarily forbidden");
}

function classifyAdminAccessPlugin(plugin) {
	const slug = String(plugin.name || "").trim();
	const normalized = slug.toLowerCase();
	if (!normalized) {
		return null;
	}
	if (adminAccessPluginCatalog.has(normalized)) {
		return {
			name: slug,
			status: plugin.status || "",
			label: adminAccessPluginCatalog.get(normalized),
			confidence: "high",
			reason: "known security/login hardening plugin",
			disable: true,
		};
	}
	if (/(wordfence|defender|cerber|firewall|sucuri|shield|malcare|bulletproof|loginizer|limit-login|hide.*login|two-factor|2fa|mfa|otp)/i.test(normalized)) {
		return {
			name: slug,
			status: plugin.status || "",
			label: slug,
			confidence: "high",
			reason: "plugin slug looks like a login/security blocker",
			disable: true,
		};
	}
	if (/(security|captcha|recaptcha|authenticator|login|access|lockdown)/i.test(normalized)) {
		return {
			name: slug,
			status: plugin.status || "",
			label: slug,
			confidence: "review",
			reason: "possible login/security plugin; review before disabling",
			disable: false,
		};
	}
	return null;
}

async function listAdminAccessPlugins(site) {
	const result = await runWpCli(
		site,
		[
			"plugin",
			"list",
			"--status=active",
			"--format=json",
			"--skip-plugins",
			"--skip-themes",
		],
		{ cwd: site.localRoot, timeoutMs: 30000 },
	);
	if (result.code !== 0) {
		return {
			...result,
			plugins: [],
			candidates: [],
		};
	}
	let plugins = [];
	try {
		plugins = parseWpCliJsonOutput(result.stdout);
		if (!Array.isArray(plugins)) {
			throw new Error("WP-CLI plugin list did not return an array.");
		}
	} catch (error) {
		return {
			...result,
			code: 1,
			stderr: appendLimited(result.stderr, `Could not parse WP-CLI plugin JSON: ${error.message}`),
			plugins: [],
			candidates: [],
		};
	}
	const candidates = plugins
		.map(classifyAdminAccessPlugin)
		.filter(Boolean)
		.sort((a, b) => Number(b.disable) - Number(a.disable) || a.name.localeCompare(b.name));
	return {
		...result,
		stderr: cleanWpCliDiagnosticOutput(result.stderr),
		plugins,
		candidates,
	};
}

async function probeAdminAccess(site) {
	const localSite = siteWithRuntimeDefaults(site);
	const admin = await fetchSmokeTarget(siteUrl(localSite, "/wp-admin/"));
	const login = await fetchSmokeTarget(siteUrl(localSite, "/wp-login.php"));
	const adminProblem = smokeCommonFailure(admin.text);
	const loginProblem = smokeCommonFailure(login.text);
	const securityBlocked = adminAccessSecurityBlocked(admin.text) || adminAccessSecurityBlocked(login.text);
	const reachable = (admin.ok && !adminProblem && !securityBlocked) || (login.ok && !loginProblem && !securityBlocked);
	return {
		status: reachable ? "reachable" : securityBlocked ? "security-blocked" : "blocked",
		admin: {
			url: admin.finalUrl,
			statusCode: admin.status,
			title: smokeTitle(admin.text),
			error: admin.error || adminProblem || "",
		},
		login: {
			url: login.finalUrl,
			statusCode: login.status,
			title: smokeTitle(login.text),
			error: login.error || loginProblem || "",
		},
		securityBlocked,
	};
}

function formatAdminAccessProbe(probe) {
	return [
		`Admin status: ${probe.status}`,
		`wp-admin: HTTP ${probe.admin.statusCode || 0}${probe.admin.title ? `, title "${probe.admin.title}"` : ""}${probe.admin.error ? `, ${probe.admin.error}` : ""}`,
		`wp-login.php: HTTP ${probe.login.statusCode || 0}${probe.login.title ? `, title "${probe.login.title}"` : ""}${probe.login.error ? `, ${probe.login.error}` : ""}`,
	].join("\n");
}

function formatAdminAccessCandidates(candidates) {
	if (!candidates.length) {
		return "Security/login plugin candidates: none detected.";
	}
	return [
		"Security/login plugin candidates:",
		...candidates.map((candidate) => {
			const action = candidate.disable ? "unlock target" : "review only";
			return `- ${candidate.name} (${candidate.confidence}, ${action}): ${candidate.reason}`;
		}),
	].join("\n");
}

async function runAdminAccessCheck(site) {
	const localSite = siteWithRuntimeDefaults(site);
	const startedAt = Date.now();
	const [probe, pluginResult] = await Promise.all([
		probeAdminAccess(localSite),
		listAdminAccessPlugins(localSite),
	]);
	const highConfidence = pluginResult.candidates.filter((candidate) => candidate.disable);
	const stdout = [
		`Local admin check: ${localSite.slug}`,
		`Local URL: ${localSite.localUrl}`,
		formatAdminAccessProbe(probe),
		"",
		formatAdminAccessCandidates(pluginResult.candidates),
		"",
		highConfidence.length
			? `Local Admin Unlock will deactivate ${highConfidence.length} high-confidence blocker${highConfidence.length === 1 ? "" : "s"} locally.`
			: "No high-confidence blockers are queued for Local Admin Unlock.",
	].join("\n");
	return {
		code: pluginResult.code === 0 ? 0 : pluginResult.code,
		command: "admin-check",
		args: [localSite.slug],
		stdout,
		stderr: pluginResult.stderr,
		durationMs: Date.now() - startedAt + (pluginResult.durationMs || 0),
		runtimeContext: pluginResult.runtimeContext || null,
		adminAccess: {
			...probe,
			candidates: pluginResult.candidates,
		},
		site: localSite,
	};
}

async function runAdminAccessUnlock(site) {
	const localSite = siteWithRuntimeDefaults(site);
	const startedAt = Date.now();
	const before = await probeAdminAccess(localSite);
	const pluginResult = await listAdminAccessPlugins(localSite);
	if (pluginResult.code !== 0) {
		return {
			code: pluginResult.code,
			command: "admin-unlock",
			args: [localSite.slug],
			stdout: [
				`Local admin unlock: ${localSite.slug}`,
				"Could not inspect active plugins; no changes were made.",
			].join("\n"),
			stderr: pluginResult.stderr,
			durationMs: Date.now() - startedAt,
			runtimeContext: pluginResult.runtimeContext || null,
			adminAccess: {
				before,
				candidates: [],
				deactivated: [],
			},
			site: localSite,
		};
	}
	const targets = pluginResult.candidates.filter((candidate) => candidate.disable);
	if (!targets.length) {
		return {
			code: before.status === "reachable" ? 0 : 1,
			command: "admin-unlock",
			args: [localSite.slug],
			stdout: [
				`Local admin unlock: ${localSite.slug}`,
				formatAdminAccessProbe(before),
				"",
				formatAdminAccessCandidates(pluginResult.candidates),
				"",
				"No high-confidence security/login blockers were found, so no plugins were deactivated.",
			].join("\n"),
			stderr: "",
			durationMs: Date.now() - startedAt,
			runtimeContext: pluginResult.runtimeContext || null,
			adminAccess: {
				before,
				after: before,
				candidates: pluginResult.candidates,
				deactivated: [],
			},
			site: localSite,
		};
	}

	const deactivated = [];
	const stdout = [];
	const stderr = [];
	for (const target of targets) {
		const result = await runWpCli(
			localSite,
			[
				"plugin",
				"deactivate",
				target.name,
				"--skip-plugins",
				"--skip-themes",
			],
			{ cwd: localSite.localRoot, timeoutMs: 30000 },
		);
		stdout.push(result.stdout);
		stderr.push(result.stderr);
		if (result.code === 0) {
			deactivated.push(target);
		} else {
			return {
				code: result.code,
				command: "admin-unlock",
				args: [localSite.slug],
				stdout: [
					`Local admin unlock: ${localSite.slug}`,
					`Stopped after ${target.name} failed to deactivate.`,
					...stdout.filter(Boolean),
				].join("\n"),
				stderr: stderr.filter(Boolean).join("\n"),
				durationMs: Date.now() - startedAt,
				runtimeContext: result.runtimeContext || pluginResult.runtimeContext || null,
				adminAccess: {
					before,
					candidates: pluginResult.candidates,
					deactivated,
				},
				site: localSite,
			};
		}
	}

	const backupPath = path.join(localSite.localRoot, "backups", `${timestampSlug()}-local-admin-unlock.json`);
	await fsp.mkdir(path.dirname(backupPath), { recursive: true });
	await fsp.writeFile(backupPath, JSON.stringify({
		createdAt: new Date().toISOString(),
		site: localSite.slug,
		deactivated,
	}, null, 2), "utf8");

	const after = await probeAdminAccess(localSite);
	return {
		code: after.status === "reachable" ? 0 : 1,
		command: "admin-unlock",
		args: [localSite.slug],
		stdout: [
			`Local admin unlock: ${localSite.slug}`,
			`Deactivated ${deactivated.length} local plugin${deactivated.length === 1 ? "" : "s"}: ${deactivated.map((item) => item.name).join(", ")}`,
			`Saved unlock record: ${backupPath}`,
			"",
			"Before:",
			formatAdminAccessProbe(before),
			"",
			"After:",
			formatAdminAccessProbe(after),
			"",
			...stdout.filter(Boolean),
		].join("\n"),
		stderr: stderr.filter(Boolean).join("\n"),
		durationMs: Date.now() - startedAt,
		runtimeContext: pluginResult.runtimeContext || null,
		adminAccess: {
			before,
			after,
			candidates: pluginResult.candidates,
			deactivated,
			backupPath,
		},
		site: localSite,
	};
}

async function cleanupLocalAdminLoginFiles(site) {
	const entries = await fsp.readdir(site.publicPath, { withFileTypes: true }).catch((error) => {
		if (error.code === "ENOENT") {
			return [];
		}
		throw error;
	});
	await Promise.all(entries
		.filter((entry) => entry.isFile() && /^mrn-local-login-[a-f0-9]{32}\.php$/.test(entry.name))
		.map((entry) => fsp.rm(path.join(site.publicPath, entry.name), { force: true })));
}

function localAdminLoginPhp(tokenHash, expiresAt) {
	return `<?php
declare(strict_types=1);

$expected = '${phpSingleQuotedString(tokenHash)}';
$expires_at = ${Number(expiresAt)};
$token = isset($_GET['t']) ? (string) $_GET['t'] : '';

if (time() > $expires_at) {
\t@unlink(__FILE__);
\thttp_response_code(410);
\techo 'Local admin login link expired.';
\texit;
}

if (!hash_equals($expected, hash('sha256', $token))) {
\thttp_response_code(403);
\techo 'Invalid local admin login token.';
\texit;
}

$wp_load = __DIR__ . '/wp-load.php';
if (!file_exists($wp_load)) {
\t@unlink(__FILE__);
\thttp_response_code(500);
\techo 'wp-load.php not found.';
\texit;
}

require_once $wp_load;

if (!function_exists('get_users') || !function_exists('wp_set_auth_cookie')) {
\t@unlink(__FILE__);
\thttp_response_code(500);
\techo 'WordPress login APIs are unavailable.';
\texit;
}

$users = get_users(array(
\t'role' => 'administrator',
\t'number' => 1,
\t'orderby' => 'ID',
\t'order' => 'ASC',
));

if (empty($users)) {
\t@unlink(__FILE__);
\thttp_response_code(500);
\techo 'No administrator user was found.';
\texit;
}

$user = $users[0];
wp_clear_auth_cookie();
wp_set_current_user($user->ID);
wp_set_auth_cookie($user->ID, true, is_ssl());
do_action('wp_login', $user->user_login, $user);
@unlink(__FILE__);
wp_safe_redirect(admin_url());
exit;
`;
}

async function createLocalAdminLogin(site) {
	const localSite = siteWithRuntimeDefaults(site);
	const startedAt = Date.now();
	const wpLoadPath = path.join(localSite.publicPath, "wp-load.php");
	if (!(await pathExists(wpLoadPath))) {
		throw httpError(400, `WordPress files are not present at ${displayPath(localSite.publicPath)} yet. Pull files before using Login to Admin.`);
	}

	await cleanupLocalAdminLoginFiles(localSite);
	const fileToken = crypto.randomBytes(16).toString("hex");
	const urlToken = crypto.randomBytes(32).toString("base64url");
	const tokenHash = crypto.createHash("sha256").update(urlToken).digest("hex");
	const expiresAt = Math.floor(Date.now() / 1000) + 600;
	const filename = `mrn-local-login-${fileToken}.php`;
	const loginPath = path.join(localSite.publicPath, filename);
	const loginUrl = siteUrl(localSite, `/${filename}?t=${encodeURIComponent(urlToken)}`);

	await fsp.writeFile(loginPath, localAdminLoginPhp(tokenHash, expiresAt), { encoding: "utf8", mode: 0o644 });
	const openResult = await runProcess("open", [loginUrl], { timeoutMs: 10000, trackJob: false });
	return {
		code: openResult.code,
		command: "admin-login",
		args: [localSite.slug],
		stdout: [
			`Created a one-time local wp-admin login for ${localSite.slug}.`,
			`Temporary bridge: ${displayPath(loginPath)}`,
			"The bridge expires in 10 minutes and deletes itself after a successful login.",
		].join("\n"),
		stderr: openResult.stderr,
		durationMs: Date.now() - startedAt,
		openUrl: loginUrl,
		site: localSite,
	};
}

function parseThemeStyleSlug(text) {
	const matches = [...String(text || "").matchAll(/\/wp-content\/themes\/([^/"'>\s]+)\/style\.css/gi)];
	if (!matches.length) {
		return "";
	}
	return matches[matches.length - 1][1];
}

function localizeSiteLink(site, value) {
	try {
		const target = new URL(value);
		const local = new URL(site.localUrl || defaultLocalUrl(site.slug));
		target.protocol = local.protocol;
		target.host = local.host;
		return target.toString();
	} catch {
		return "";
	}
}

function cleanWpCliScalarOutput(value) {
	const lines = String(value || "")
		.split(/\r?\n/)
		.map((line) => line.trim())
		.filter(Boolean)
		.filter((line) => !/^(deprecated|notice|warning|php\s+(deprecated|notice|warning)):/i.test(line));
	return lines.length ? lines[lines.length - 1] : "";
}

function cleanWpCliDiagnosticOutput(value) {
	return String(value || "")
		.split(/\r?\n/)
		.map((line) => line.trim())
		.filter(Boolean)
		.filter((line) => !/^(deprecated|notice|warning|php\s+(deprecated|notice|warning)):/i.test(line))
		.join("\n");
}

function parseWpCliJsonOutput(value) {
	const output = String(value || "").trim();
	const candidates = [];
	if (output) {
		candidates.push(output);
		const arrayStart = output.indexOf("[");
		const arrayEnd = output.lastIndexOf("]");
		if (arrayStart !== -1 && arrayEnd > arrayStart) {
			candidates.push(output.slice(arrayStart, arrayEnd + 1));
		}
		const objectStart = output.indexOf("{");
		const objectEnd = output.lastIndexOf("}");
		if (objectStart !== -1 && objectEnd > objectStart) {
			candidates.push(output.slice(objectStart, objectEnd + 1));
		}
	}
	const lines = String(value || "")
		.split(/\r?\n/)
		.map((line) => line.trim())
		.filter(Boolean);
	for (let index = lines.length - 1; index >= 0; index -= 1) {
		candidates.push(lines[index]);
	}
	for (const candidate of candidates) {
		try {
			return JSON.parse(candidate);
		} catch {
			// Keep walking through possible WP-CLI notices and PHP warnings.
		}
	}
	throw new Error("Could not parse WP-CLI JSON output.");
}

function rsyncChangeLines(value) {
	return String(value || "")
		.split(/\r?\n/)
		.map((line) => line.trim())
		.filter((line) => /^[<>ch.*]/.test(line) || line.startsWith("*deleting "));
}

function codeSyncWarning(title, detail, action = {}) {
	return {
		level: "warning",
		title,
		detail,
		...action,
	};
}

async function localCodePathStatus(site, relativePath, options = {}) {
	const localPath = path.join(site.publicPath, relativePath);
	const allowFile = Boolean(options.allowFile);
	try {
		const stat = await fsp.lstat(localPath);
		if (stat.isSymbolicLink()) {
			const target = await fsp.readlink(localPath);
			const resolvedTarget = path.resolve(path.dirname(localPath), target);
			const targetExists = await pathExists(resolvedTarget);
			const targetIsLocal = targetExists && isPathInside(site.publicPath, resolvedTarget);
			return {
				ok: targetExists && targetIsLocal,
				localPath,
				relativePath,
				symlink: true,
				target,
				targetExists,
				targetIsLocal,
				reason: targetExists && targetIsLocal
					? ""
					: `local path is a ${targetExists ? "non-local" : "broken"} symlink to ${target}`,
			};
		}
		if (!stat.isDirectory() && !(allowFile && stat.isFile())) {
			return {
				ok: false,
				localPath,
				relativePath,
				reason: allowFile ? "local path exists but is not a file or directory" : "local path exists but is not a directory",
			};
		}
		return { ok: true, localPath, relativePath, reason: "" };
	} catch (error) {
		if (error.code === "ENOENT") {
			return {
				ok: false,
				localPath,
				relativePath,
				reason: "local path is missing",
			};
		}
		throw error;
	}
}

async function remoteCodePathStatuses(site, relativePaths) {
	const uniquePaths = [...new Set(relativePaths.filter(Boolean).map(sanitizeRelativePath))];
	if (!uniquePaths.length || !site.remoteSsh || !site.remotePath) {
		return new Map();
	}
	const script = [
		`cd ${shellQuote(sanitizeRemotePath(site.remotePath))} || exit 2`,
		`for p in ${uniquePaths.map(shellQuote).join(" ")}; do`,
		`  if [ -L "$p" ]; then printf 'SYMLINK\\t%s\\t%s\\n' "$p" "$(readlink "$p")";`,
		`  elif [ -e "$p" ]; then printf 'EXISTS\\t%s\\t\\n' "$p";`,
		`  else printf 'MISSING\\t%s\\t\\n' "$p"; fi`,
		"done",
	].join("\n");
	const result = await runProcess(
		"ssh",
		sshArgs(site.remoteSsh, site.remotePort || "", script, {
			identityFile: sshIdentityFileForSite(site),
		}),
		{ timeoutMs: 30000, trackJob: false },
	);
	const statuses = new Map();
	if (result.code !== 0) {
		return statuses;
	}
	for (const line of String(result.stdout || "").split(/\r?\n/)) {
		const [status, relativePath, target = ""] = line.split("\t");
		if (status && relativePath) {
			statuses.set(relativePath, { status, relativePath, target });
		}
	}
	return statuses;
}

async function localWordPressCodeState(site) {
	const result = await runWpCli(
		site,
		[
			"eval",
			[
				"$data = [",
				"'stylesheet' => get_option('stylesheet'),",
				"'template' => get_option('template'),",
				"'active_plugins' => array_values((array) get_option('active_plugins', [])),",
				"];",
				"echo wp_json_encode($data);",
			].join(" "),
			"--skip-plugins",
			"--skip-themes",
		],
		{ cwd: site.localRoot, timeoutMs: 30000, trackJob: false },
	);
	if (result.code !== 0) {
		throw new Error(result.stderr || result.stdout || "Could not inspect local WordPress code state.");
	}
	return parseWpCliJsonOutput(result.stdout);
}

function pluginRelativePathFromPluginFile(pluginFile) {
	const normalized = sanitizeRelativePath(pluginFile);
	const parts = normalized.split("/");
	return parts.length > 1 ? `wp-content/plugins/${parts[0]}` : `wp-content/plugins/${normalized}`;
}

async function checkDatabaseCodeSync(site) {
	const warnings = [];
	const startedAt = Date.now();
	const checkedPaths = [];
	try {
		const state = await localWordPressCodeState(site);
		const stylesheet = state.stylesheet ? sanitizeThemeDirectory(state.stylesheet) : "";
		const activePlugins = Array.isArray(state.active_plugins) ? state.active_plugins : [];
		const themeRelativePath = stylesheet ? `wp-content/themes/${stylesheet}` : "";
		const pluginRelativePaths = [...new Set(activePlugins.map(pluginRelativePathFromPluginFile).filter(Boolean))];
		const relativePaths = [themeRelativePath, ...pluginRelativePaths].filter(Boolean);
		const remoteStatuses = await remoteCodePathStatuses(site, relativePaths);

		if (themeRelativePath) {
			checkedPaths.push(themeRelativePath);
			const status = await localCodePathStatus(site, themeRelativePath);
			const remote = remoteStatuses.get(themeRelativePath);
			if (!status.ok) {
				warnings.push(codeSyncWarning(
					"Active theme files need attention",
					`${themeRelativePath}: ${status.reason}${remote?.status === "SYMLINK" ? `; remote uses deploy symlink ${remote.target}` : ""}. Pull the active theme before trusting rendered pages.`,
					{ action: "pull-files", fileScope: "active-theme", relativePath: themeRelativePath },
				));
			} else {
				const source = `${sanitizeRemoteSsh(site.remoteSsh)}:${sanitizeRemotePath(site.remotePath)}/${themeRelativePath}/`;
				const dest = `${status.localPath}/`;
				const dryRun = await runProcess(
					"rsync",
					rsyncArgs({
						dryRun: true,
						deleteFiles: true,
						source,
						dest,
						sshPort: site.remotePort || "",
						identityFile: sshIdentityFileForSite(site),
					}),
					{ cwd: site.localRoot, timeoutMs: 120000, trackJob: false },
				);
				const changes = rsyncChangeLines(dryRun.stdout);
				if (dryRun.code === 0 && changes.length) {
					warnings.push(codeSyncWarning(
						"Active theme differs from remote",
						`${themeRelativePath} has ${changes.length} pending file change${changes.length === 1 ? "" : "s"} from the remote site. Pull the active theme so local rendering matches dev.`,
						{ action: "pull-files", fileScope: "active-theme", relativePath: themeRelativePath, changes: changes.slice(0, 12) },
					));
				} else if (dryRun.code !== 0) {
					warnings.push(codeSyncWarning(
						"Active theme parity check failed",
						`${themeRelativePath}: ${dryRun.stderr || dryRun.stdout || "rsync dry run failed"}`,
						{ action: "pull-files-dry-run", fileScope: "active-theme", relativePath: themeRelativePath },
					));
				}
			}
		}

		for (const relativePath of pluginRelativePaths) {
			checkedPaths.push(relativePath);
			const status = await localCodePathStatus(site, relativePath, { allowFile: relativePath.endsWith(".php") });
			const remote = remoteStatuses.get(relativePath);
			if (!status.ok) {
				warnings.push(codeSyncWarning(
					"Active plugin files need attention",
					`${relativePath}: ${status.reason}${remote?.status === "SYMLINK" ? `; remote uses deploy symlink ${remote.target}` : ""}. Pull or materialize this active plugin locally.`,
					{ action: "pull-files", fileScope: "custom", relativePath },
				));
			}
		}

		return {
			checked: true,
			warningCount: warnings.length,
			warnings,
			checkedPaths,
			durationMs: Date.now() - startedAt,
		};
	} catch (error) {
		warnings.push(codeSyncWarning(
			"Code parity check did not finish",
			error.message || "Local Hub could not inspect theme/plugin parity after the database pull.",
		));
		return {
			checked: false,
			warningCount: warnings.length,
			warnings,
			checkedPaths,
			durationMs: Date.now() - startedAt,
		};
	}
}

function formatCodeSyncReport(report) {
	if (!report) {
		return "";
	}
	const lines = [
		report.warningCount
			? `Code parity warnings: ${report.warningCount}`
			: "Code parity warnings: none",
	];
	for (const warning of report.warnings || []) {
		lines.push(`- ${warning.title}: ${warning.detail}`);
		if (Array.isArray(warning.changes) && warning.changes.length) {
			for (const change of warning.changes.slice(0, 6)) {
				lines.push(`  ${change}`);
			}
			if (warning.changes.length > 6) {
				lines.push(`  ... ${warning.changes.length - 6} more`);
			}
		}
	}
	return lines.join("\n");
}

async function runSmokeCheck(site) {
	const localSite = siteWithRuntimeDefaults(site);
	const startedAt = Date.now();
	const checks = [];
	const stderr = [];
	const localUrl = siteUrl(localSite, "/");

	const home = await fetchSmokeTarget(localUrl);
	const homeProblem = smokeCommonFailure(home.text);
	checks.push(smokeCheck(
		home.ok && !homeProblem ? "pass" : "fail",
		"Home",
		home.error || homeProblem || `HTTP ${home.status}${smokeTitle(home.text) ? `, title "${smokeTitle(home.text)}"` : ""}`,
		{ url: home.finalUrl, statusCode: home.status },
	));

	const wpJsonUrl = siteUrl(localSite, "/wp-json/");
	const wpJson = await fetchSmokeTarget(wpJsonUrl, { maxTextChars: 5000000 });
	let wpJsonPayload = null;
	let wpJsonProblem = smokeCommonFailure(wpJson.text);
	if (!wpJsonProblem && wpJson.ok) {
		try {
			wpJsonPayload = JSON.parse(wpJson.text);
		} catch {
			wpJsonProblem = "REST API returned a non-JSON response.";
		}
	}
	checks.push(smokeCheck(
		wpJson.ok && !wpJsonProblem && wpJsonPayload && wpJsonPayload.routes ? "pass" : "fail",
		"REST API",
		wpJson.error || wpJsonProblem || `HTTP ${wpJson.status}, ${Object.keys(wpJsonPayload.routes || {}).length} routes`,
		{ url: wpJson.finalUrl, statusCode: wpJson.status },
	));

	const adminUrl = siteUrl(localSite, "/wp-admin/");
	const admin = await fetchSmokeTarget(adminUrl);
	const adminProblem = smokeCommonFailure(admin.text);
	if (admin.ok && !adminProblem) {
		checks.push(smokeCheck(
			"pass",
			"Admin",
			`HTTP ${admin.status}${smokeTitle(admin.text) ? `, title "${smokeTitle(admin.text)}"` : ""}`,
			{ url: admin.finalUrl, statusCode: admin.status },
		));
	} else {
		const login = await fetchSmokeTarget(siteUrl(localSite, "/wp-login.php"));
		const loginProblem = smokeCommonFailure(login.text);
		const securityBlocked = [admin.text, login.text].some((text) => String(text || "").includes("temporarily forbidden for security reasons"));
		checks.push(smokeCheck(
			(login.ok && !loginProblem) || securityBlocked ? "warn" : "fail",
			"Admin",
			securityBlocked
				? `Login/admin are blocked by a WordPress security plugin locally (HTTP ${admin.status || login.status}).`
				: login.ok && !loginProblem
				? `wp-admin returned HTTP ${admin.status || 0}, but wp-login.php is reachable.`
				: admin.error || adminProblem || login.error || loginProblem || `HTTP ${admin.status || login.status}`,
			{ url: login.ok ? login.finalUrl : admin.finalUrl, statusCode: login.ok ? login.status : admin.status },
		));
	}

	let themeSlug = "";
	const stylesheetResult = await runWpCli(
		localSite,
		[
			"option",
			"get",
			"stylesheet",
			"--skip-plugins",
			"--skip-themes",
			"--quiet",
		],
		{ cwd: localSite.localRoot, timeoutMs: 30000 },
	);
	if (stylesheetResult.code === 0) {
		themeSlug = cleanWpCliScalarOutput(stylesheetResult.stdout);
	} else {
		stderr.push(stylesheetResult.stderr);
		themeSlug = parseThemeStyleSlug(home.text);
	}
	const themeUrl = themeSlug ? siteUrl(localSite, `/wp-content/themes/${themeSlug}/style.css`) : "";
	if (themeUrl) {
		const theme = await fetchSmokeTarget(themeUrl);
		checks.push(smokeCheck(
			theme.ok && /text\/css|\/css/i.test(theme.contentType) ? "pass" : "fail",
			"Active Theme CSS",
			theme.error || `HTTP ${theme.status}, ${themeSlug}/style.css`,
			{ url: theme.finalUrl, statusCode: theme.status, theme: themeSlug },
		));
	} else {
		checks.push(smokeCheck("fail", "Active Theme CSS", "Could not determine the active stylesheet slug."));
	}

	const pagesUrl = siteUrl(localSite, "/wp-json/wp/v2/pages?per_page=10&_fields=id,link,slug,title");
	const pages = await fetchSmokeTarget(pagesUrl);
	let internalUrl = "";
	if (pages.ok && !smokeCommonFailure(pages.text)) {
		try {
			const pageItems = JSON.parse(pages.text);
			if (Array.isArray(pageItems)) {
				const homeOrigin = new URL(localUrl).origin;
				const candidate = pageItems
					.map((item) => localizeSiteLink(localSite, item.link || ""))
					.find((link) => {
						if (!link) return false;
						const parsed = new URL(link);
						return parsed.origin === homeOrigin && parsed.pathname.replace(/\/+$/, "") !== "";
					});
				internalUrl = candidate || "";
			}
		} catch {
			// The REST API check above reports JSON problems; keep this as a warning.
		}
	}
	if (internalUrl) {
		const internal = await fetchSmokeTarget(internalUrl);
		const internalProblem = smokeCommonFailure(internal.text);
		checks.push(smokeCheck(
			internal.ok && !internalProblem ? "pass" : "fail",
			"Internal Page",
			internal.error || internalProblem || `HTTP ${internal.status}${smokeTitle(internal.text) ? `, title "${smokeTitle(internal.text)}"` : ""}`,
			{ url: internal.finalUrl, statusCode: internal.status },
		));
	} else {
		checks.push(smokeCheck(
			"warn",
			"Internal Page",
			pages.error || "No published internal page was found through the REST API.",
			{ url: pages.finalUrl, statusCode: pages.status },
		));
	}

	const failed = checks.filter((check) => check.status === "fail");
	const warned = checks.filter((check) => check.status === "warn");
	const passed = checks.filter((check) => check.status === "pass");
	const stdout = [
		`Smoke check: ${localSite.slug}`,
		`Local URL: ${localUrl}`,
		`Summary: ${passed.length} passed, ${failed.length} failed, ${warned.length} warning${warned.length === 1 ? "" : "s"}.`,
		"",
		...checks.map(formatSmokeLine),
	].join("\n");

	return {
		code: failed.length ? 1 : 0,
		command: "smoke-check",
		args: [localSite.slug],
		stdout,
		stderr: stderr.filter(Boolean).join("\n"),
		durationMs: Date.now() - startedAt,
		runtimeContext: stylesheetResult.runtimeContext || null,
		smoke: {
			passed: passed.length,
			failed: failed.length,
			warnings: warned.length,
			checks,
		},
		site: localSite,
	};
}

async function ensureRuntimeIsRunning() {
	const report = await runtimeReport();
	if (!report.commands.limactl.ok) {
		throw httpError(400, "limactl is missing. Install Lima before provisioning a site.");
	}
	if (report.status !== "running") {
		throw httpError(400, "The OpenLiteSpeed runtime is not running. Use Bootstrap Runtime first.");
	}
	return report;
}

async function restartOpenLiteSpeedRuntime() {
	const report = await runtimeReport();
	if (!report.commands.limactl.ok) {
		return {
			code: 0,
			command: "restart-openlitespeed",
			args: [runtimeInstanceName],
			stdout: "OpenLiteSpeed restart skipped; limactl is not installed.",
			stderr: "",
			skipped: true,
		};
	}
	if (report.status !== "running") {
		return {
			code: 0,
			command: "restart-openlitespeed",
			args: [runtimeInstanceName],
			stdout: "OpenLiteSpeed restart skipped; the Lima runtime is not running.",
			stderr: "",
			skipped: true,
		};
	}

	const result = await runProcess(
		"limactl",
		["shell", runtimeInstanceName, "--", "sudo", "/usr/local/lsws/bin/lswsctrl", "restart"],
		{ timeoutMs: 120000 },
	);
	return {
		...result,
		command: "restart-openlitespeed",
		args: [runtimeInstanceName],
		stdout: result.code === 0
			? [result.stdout, "Restarted OpenLiteSpeed so rewrite rules are loaded."].filter(Boolean).join("\n")
			: result.stdout,
	};
}

async function provisionSite(site) {
	await ensureRuntimeIsRunning();
	let nextSite = siteWithRuntimeDefaults(site, { runtimeStatus: "provisioning" });
	await ensureSiteDirectories(nextSite);
	await writeManifest(nextSite);

	const provision = buildProvisionSiteScript(nextSite);
	const result = await runProcess("limactl", ["shell", runtimeInstanceName, "--", "bash", "-s"], {
		input: provision.script,
		timeoutMs: Number(process.env.MRN_LOCAL_HUB_PROVISION_TIMEOUT_MS || "120000"),
	});

	let wpConfigResult = null;
	let dbUrlResult = null;
	let friendlyCertResult = null;
	if (result.code === 0) {
		nextSite = siteWithRuntimeDefaults(nextSite, { runtimeStatus: "provisioned" });
		wpConfigResult = await writeLocalWpConfig(nextSite);
		dbUrlResult = await normalizeLocalDatabaseUrls(nextSite);
		await writeManifest(nextSite);
		friendlyCertResult = await syncFriendlyCertificateAfterSiteChange(nextSite, "provision-site");
	} else {
		nextSite = siteWithRuntimeDefaults(nextSite, { runtimeStatus: "provision-error" });
		await writeManifest(nextSite);
	}
	invalidateSiteDiskUsage(nextSite);

	return {
		...result,
		command: "provision-site",
		args: [site.slug],
		stdout: [
			result.stdout,
			result.code === 0 ? `Local URL: ${nextSite.localUrl}` : "",
			result.code === 0 ? `MariaDB: ${nextSite.dbName} as ${nextSite.dbUser} on ${nextSite.dbHost}:${nextSite.dbPort}` : "",
			wpConfigResult ? formatWpConfigResult(wpConfigResult) : "",
			dbUrlResult ? dbUrlResult.stdout : "",
			friendlyCertResult ? friendlyCertResult.stdout : "",
		].filter(Boolean).join("\n"),
		stderr: [
			result.stderr,
			dbUrlResult ? dbUrlResult.stderr : "",
			friendlyCertResult ? friendlyCertResult.stderr : "",
		].filter(Boolean).join("\n"),
		durationMs: (result.durationMs || 0) + (dbUrlResult ? dbUrlResult.durationMs || 0 : 0) + (friendlyCertResult ? friendlyCertResult.durationMs || 0 : 0),
		runtimeContext: dbUrlResult ? dbUrlResult.runtimeContext : null,
		site: nextSite,
		wpConfig: wpConfigResult,
		dbUrls: dbUrlResult,
		friendlyCert: friendlyCertResult,
		friendlyUrls: friendlyCertResult ? friendlyCertResult.friendlyUrls : null,
		vhost: {
			name: provision.vhostName,
			hostname: provision.hostname,
			documentRoot: provision.guestPublicPath,
		},
	};
}

function buildDeleteSiteRuntimeScript(site) {
	const hostname = runtimeHostnameForSite(site);
	const vhostName = vhostNameForSite(site);
	const dbName = mysqlIdentifier(site.dbName, "DB name");
	const dbUser = mysqlString(site.dbUser);

	return `#!/usr/bin/env bash
set -euo pipefail

VHOST_NAME=${shellQuote(vhostName)}
HOSTNAME_VALUE=${shellQuote(hostname)}

sudo mysql <<'MRN_SQL'
DROP DATABASE IF EXISTS ${dbName};
DROP USER IF EXISTS ${dbUser}@'localhost';
DROP USER IF EXISTS ${dbUser}@'%';
FLUSH PRIVILEGES;
MRN_SQL

sudo rm -rf "/usr/local/lsws/conf/vhosts/\${VHOST_NAME}"

export MRN_VHOST_NAME="\${VHOST_NAME}"
export MRN_HOSTNAME_VALUE="\${HOSTNAME_VALUE}"
sudo -E python3 <<'PY'
import os
import re
from pathlib import Path

conf_path = Path("/usr/local/lsws/conf/httpd_config.conf")
text = conf_path.read_text()
vhost = os.environ["MRN_VHOST_NAME"]
hostname = os.environ["MRN_HOSTNAME_VALUE"]

text = re.sub(r"\\n?virtualHost\\s+" + re.escape(vhost) + r"\\s*\\{.*?\\n\\}", "\\n", text, flags=re.S)

listener_re = re.compile(r"listener\\s+Default\\s*\\{(?P<body>.*?)\\n\\}", re.S)
match = listener_re.search(text)
if match:
    body = match.group("body")
    lines = [
        line for line in body.splitlines()
        if not re.match(r"\\s*map\\s+" + re.escape(vhost) + r"\\s+", line)
        and not re.match(r"\\s*map\\s+\\S+\\s+" + re.escape(hostname) + r"\\s*$", line)
    ]
    listener = "listener Default{\\n" + "\\n".join(lines).rstrip() + "\\n}"
    text = text[:match.start()] + listener + text[match.end():]

conf_path.write_text(text.rstrip() + "\\n")
PY

sudo /usr/local/lsws/bin/lswsctrl restart
printf 'Deleted vhost: %s\\n' "\${VHOST_NAME}"
printf 'Unmapped host: %s\\n' "\${HOSTNAME_VALUE}"
printf 'Dropped database: %s\\n' ${shellQuote(site.dbName)}
printf 'Dropped DB user: %s\\n' ${shellQuote(site.dbUser)}
`;
}

async function deleteRuntimeSite(site) {
	await ensureRuntimeIsRunning();
	return runProcess("limactl", ["shell", runtimeInstanceName, "--", "bash", "-s"], {
		input: buildDeleteSiteRuntimeScript(siteWithRuntimeDefaults(site)),
		timeoutMs: Number(process.env.MRN_LOCAL_HUB_DELETE_TIMEOUT_MS || "120000"),
	});
}

function siteMayHaveRuntimeResources(site) {
	return site.runtime === "local-vm-openlitespeed" && site.runtimeStatus && site.runtimeStatus !== "planned";
}

async function deleteSite(site, body = {}) {
	const confirmation = String(body.confirm || "");
	if (confirmation !== site.slug) {
		throw httpError(400, `Delete requires confirm: ${site.slug}.`);
	}

	let runtimeResult = null;
	if (siteMayHaveRuntimeResources(site)) {
		runtimeResult = await deleteRuntimeSite(site);
		if (runtimeResult.code !== 0) {
			return {
				...runtimeResult,
				command: "delete-site",
				args: [site.slug],
				stdout: [
					runtimeResult.stdout,
					"",
					"Local files were not deleted because runtime cleanup failed.",
				].filter(Boolean).join("\n"),
				site,
				runtimeCleanup: runtimeResult,
			};
		}
	}

	const localRoot = assertInsideSitesRoot(site.localRoot);
	await fsp.rm(localRoot, { recursive: true, force: true });
	invalidateSiteDiskUsage(site);
	return {
		code: 0,
		command: "delete-site",
		args: [site.slug],
		stdout: [
			runtimeResult ? runtimeResult.stdout : "Runtime cleanup skipped; this site was not marked provisioned locally.",
			`Deleted local site root: ${localRoot}`,
			"Remote server was not touched.",
		].filter(Boolean).join("\n"),
		stderr: runtimeResult ? runtimeResult.stderr : "",
		durationMs: runtimeResult ? runtimeResult.durationMs : 0,
		deleted: {
			slug: site.slug,
			localRoot,
			runtime: Boolean(runtimeResult),
		},
		runtimeCleanup: runtimeResult,
	};
}

async function stopSite(site) {
	const nextSite = siteWithRuntimeDefaults(site, { runtimeStatus: "stopped" });
	await writeManifest(nextSite);
	return {
		code: 0,
		command: "stop-site",
		args: [site.slug],
		stdout: `${site.slug} marked stopped in Local Hub. The shared OpenLiteSpeed runtime remains available.`,
		stderr: "",
		durationMs: 0,
		site: nextSite,
	};
}

async function pullPreflight(site, body = {}) {
	const remoteSsh = sanitizeRemoteSsh(site.remoteSsh);
	const remotePath = sanitizeRemotePath(site.remotePath);
	const identityFile = sshIdentityFileForSite(site);
	await ensureSiteDirectories(site);
	const pullScope = await resolvePullFileScope(site, body);
	const gitSafety = await gitSafetyReport(pullScope.localDestPath, {
		label: pullScope.label,
		stopPath: site.publicPath,
	});

	const [health, runtime, inspection, runtimeWpCli] = await Promise.all([
		healthReport(),
		runtimeReport(),
		inspectRemoteWordPress(site),
		runtimeWpCliStatus(siteWithRuntimeDefaults(site)),
	]);
	const parsed = inspection.parsed;
	const localRequired = ["ssh", "rsync", "mysql"];
	const missingLocal = health.commands
		.filter((item) => localRequired.includes(item.command) && !item.ok)
		.map((item) => item.command);
	const issues = [];
	const warnings = [];

	if (missingLocal.length) {
		issues.push(`Missing local tools: ${missingLocal.join(", ")}`);
	}
	if (inspection.result.code !== 0) {
		issues.push("SSH inspection failed; dry-run the connection before pulling.");
	}
	if (inspection.result.code === 0 && parsed.wp_config !== "1") {
		issues.push("Remote path does not contain wp-config.php.");
	}
	if (inspection.result.code === 0 && parsed.wp_cli !== "1") {
		warnings.push("Remote WP-CLI is missing; file pull can work, but DB pull is blocked.");
	}
	if (!runtimeWpCli.ok) {
		warnings.push("Runtime WP-CLI is not ready inside the Lima/OpenLiteSpeed VM; local WordPress status/search-replace will install or require Runtime Repair before running.");
	}
	issues.push(...gitSafety.issues);
	warnings.push(...gitSafety.warnings);

	const dryRunArgs = rsyncArgs({
		dryRun: true,
		deleteFiles: true,
		source: pullScope.source,
		dest: pullScope.dest,
		sshPort: site.remotePort || "",
		identityFile,
		excludes: pullScope.excludes,
	});
	const pullArgs = rsyncArgs({
		dryRun: false,
		deleteFiles: true,
		source: pullScope.source,
		dest: pullScope.dest,
		sshPort: site.remotePort || "",
		identityFile,
		excludes: pullScope.excludes,
	});
	const dbExportCommand = commandPreview("ssh", sshArgs(remoteSsh, site.remotePort || "", `cd ${shellQuote(remotePath)} && wp db export -`, { identityFile }));
	const status = issues.length ? "blocked" : warnings.length ? "ready with warnings" : "ready";
	const stdout = [
		`Pull preflight: ${site.slug}`,
		`Status: ${status}`,
		`Local root: ${site.localRoot}`,
		`Public path: ${site.publicPath}`,
		`Remote: ${remoteSsh}:${remotePath}`,
		`File scope: ${pullScope.label}`,
		`Runtime: ${runtime.adapter} / ${runtime.status}`,
		`Runtime WP-CLI: ${runtimeWpCli.ok ? "ready" : "not ready"}`,
		`Local tools: ${missingLocal.length ? `missing ${missingLocal.join(", ")}` : "ready"}`,
		"",
		"Remote WordPress:",
		formatSshInspection(parsed),
		"",
		formatGitSafety(gitSafety),
		"",
		"Commands preview:",
		`Files dry run: ${commandPreview("rsync", dryRunArgs)}`,
		`Files pull: ${commandPreview("rsync", pullArgs)}`,
		`DB export: ${dbExportCommand}`,
		"",
		issues.length ? "Issues:" : "Issues: none",
		...issues.map((issue) => `- ${issue}`),
		warnings.length ? "Warnings:" : "",
		...warnings.map((warning) => `- ${warning}`),
	].join("\n");

	return {
		code: inspection.result.code === 0 && !missingLocal.length && parsed.wp_config === "1" && !issues.length ? 0 : 1,
		command: "pull-preflight",
		args: [site.slug],
		stdout,
		stderr: inspection.result.stderr,
		durationMs: inspection.result.durationMs,
		preflight: {
			status,
			issues,
			warnings,
			remote: parsed,
			runtime: {
				adapter: runtime.adapter,
				status: runtime.status,
				missing: runtime.missing,
			},
			runtimeWpCli: {
				ok: runtimeWpCli.ok,
				context: runtimeWpCli.context,
				stdout: runtimeWpCli.stdout,
				stderr: runtimeWpCli.stderr,
			},
			missingLocal,
			pullScope: {
				scope: pullScope.scope,
				label: pullScope.label,
				relativePath: pullScope.relativePath,
			},
			gitSafety,
		},
	};
}

async function exportDatabase(site) {
	const remoteSsh = sanitizeRemoteSsh(site.remoteSsh);
	const remotePath = sanitizeRemotePath(site.remotePath);
	const identityFile = sshIdentityFileForSite(site);
	const dumpFile = path.join(site.localRoot, "dumps", `${timestampSlug()}-live.sql`);
	await fsp.mkdir(path.dirname(dumpFile), { recursive: true });

	return new Promise((resolve) => {
		const startedAt = Date.now();
		let stderr = "";
		const out = fs.createWriteStream(dumpFile);
		const child = childProcess.spawn("ssh", sshArgs(remoteSsh, site.remotePort || "", `cd ${shellQuote(remotePath)} && wp db export -`, { identityFile }), {
			cwd: repoRoot,
			stdio: ["ignore", "pipe", "pipe"],
		});
		const timeout = setTimeout(() => child.kill("SIGTERM"), commandTimeoutMs);
		child.stdout.pipe(out);
		child.stderr.on("data", (chunk) => {
			stderr = appendLimited(stderr, chunk.toString());
		});
		child.on("error", (error) => {
			clearTimeout(timeout);
			out.end();
			resolve({
				code: 127,
				stderr: appendLimited(stderr, error.message),
				dumpFile,
				durationMs: Date.now() - startedAt,
			});
		});
		child.on("close", (code, signal) => {
			clearTimeout(timeout);
			out.end();
			resolve({
				code,
				signal,
				stderr,
				dumpFile,
				durationMs: Date.now() - startedAt,
			});
		});
	});
}

async function importDatabase(site, dumpFile) {
	const dbName = assertSafeSqlName(site.dbName, "DB name");
	const dbUser = assertSafeSqlName(site.dbUser, "DB user");
	const dbPort = sanitizeDbPort(site.dbPort || 3307);
	const dbHost = String(site.dbHost || "127.0.0.1").trim();
	const dbPassword = String(site.dbPassword || "");
	return new Promise((resolve) => {
		const startedAt = Date.now();
		let stdout = "";
		let stderr = "";
		const args = [
			"--protocol=tcp",
			"--binary-mode=1",
			`--host=${dbHost}`,
			`--port=${dbPort}`,
			`--user=${dbUser}`,
			"--default-character-set=utf8mb4",
			"--init-command=SET SESSION time_zone='+00:00'; SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION,ALLOW_INVALID_DATES';",
			dbName,
		];
		const child = childProcess.spawn("mysql", args, {
			cwd: site.publicPath,
			env: { ...process.env, MYSQL_PWD: dbPassword },
			stdio: ["pipe", "pipe", "pipe"],
		});
		const input = fs.createReadStream(dumpFile);
		const timeout = setTimeout(() => child.kill("SIGTERM"), commandTimeoutMs);
		input.pipe(child.stdin);
		input.on("error", (error) => {
			child.stdin.destroy(error);
		});
		child.stdout.on("data", (chunk) => {
			stdout = appendLimited(stdout, chunk.toString());
		});
		child.stderr.on("data", (chunk) => {
			stderr = appendLimited(stderr, chunk.toString());
		});
		child.on("error", (error) => {
			clearTimeout(timeout);
			resolve({
				code: 127,
				command: "mysql",
				args: [
					"--protocol=tcp",
					"--binary-mode=1",
					`--host=${dbHost}`,
					`--port=${dbPort}`,
					`--user=${dbUser}`,
					dbName,
				],
				stdout,
				stderr: appendLimited(stderr, error.message),
				durationMs: Date.now() - startedAt,
			});
		});
		child.on("close", (code, signal) => {
			clearTimeout(timeout);
			resolve({
				code,
				signal,
				command: "mysql",
				args: [
					"--protocol=tcp",
					"--binary-mode=1",
					`--host=${dbHost}`,
					`--port=${dbPort}`,
					`--user=${dbUser}`,
					dbName,
				],
				stdout,
				stderr,
				durationMs: Date.now() - startedAt,
			});
		});
	});
}

async function runSiteAction(site, body) {
	const action = String(body.action || "");
	switch (action) {
		case "open-local":
			return runProcess("open", [site.localUrl]);
		case "open-admin":
			return runProcess("open", [`${site.localUrl.replace(/\/+$/, "")}/wp-admin/`]);
		case "provision-site":
		case "start-site":
			return provisionSite(site);
		case "stop-site":
			return stopSite(site);
		case "pull-preflight":
			return pullPreflight(site, body);
		case "admin-check":
			return runAdminAccessCheck(site);
		case "admin-login":
			return createLocalAdminLogin(site);
		case "admin-unlock":
			return runAdminAccessUnlock(site);
		case "apply-php-version":
			return applySitePhpVersion(site, body.phpVersion);
		case "normalize-local-url": {
			const localSite = siteWithRuntimeDefaults(site);
			const wpConfigResult = await writeLocalWpConfig(localSite);
			const dbUrlResult = await normalizeLocalDatabaseUrls(localSite);
			await writeManifest(localSite);
			return {
				code: dbUrlResult.code,
				command: "normalize-local-url",
				args: [localSite.slug, localSite.localUrl],
				stdout: [
					formatWpConfigResult(wpConfigResult),
					dbUrlResult.stdout,
				].filter(Boolean).join("\n"),
				stderr: dbUrlResult.stderr,
				durationMs: (dbUrlResult.durationMs || 0),
				runtimeContext: dbUrlResult.runtimeContext || null,
				site: localSite,
				wpConfig: wpConfigResult,
				dbUrls: dbUrlResult,
			};
		}
		case "pull-files-dry-run":
		case "pull-files": {
			await ensureSiteDirectories(site);
			const pullScope = await resolvePullFileScope(site, body);
			const gitSafety = await gitSafetyReport(pullScope.localDestPath, {
				label: pullScope.label,
				stopPath: site.publicPath,
			});
			if (action === "pull-files" && gitSafety.dirty) {
				return {
					code: 1,
					command: action,
					args: [site.slug, pullScope.relativePath || "."],
					stdout: [
						`Pull scope: ${pullScope.label}`,
						`Local target: ${pullScope.dest}`,
						formatGitSafety(gitSafety),
						"",
						"Pull stopped before rsync because the containing Git repo has local changes.",
						"Commit, stash, or otherwise back up the local changes before pulling files.",
					].filter(Boolean).join("\n"),
					stderr: "",
					durationMs: 0,
					pullScope: {
						scope: pullScope.scope,
						label: pullScope.label,
						relativePath: pullScope.relativePath,
					},
					gitSafety,
				};
			}
			const destinationPrep = await preparePullDestinationDirectory(pullScope.localDestPath, site.publicPath, {
				replaceUnsafeSymlink: action === "pull-files",
			});
			const result = await runProcess(
				"rsync",
				rsyncArgs({
					dryRun: action.endsWith("dry-run"),
					deleteFiles: true,
					source: pullScope.source,
					dest: pullScope.dest,
					sshPort: site.remotePort || "",
					identityFile: sshIdentityFileForSite(site),
					excludes: pullScope.excludes,
				}),
				{ cwd: site.localRoot },
			);
			const scopedResult = {
				...result,
				stdout: [
					`Pull scope: ${pullScope.label}`,
					`Remote source: ${pullScope.source}`,
					`Local target: ${pullScope.dest}`,
					formatGitSafety(gitSafety),
					destinationPrep.stdout,
					result.stdout,
				].filter(Boolean).join("\n"),
				pullScope: {
					scope: pullScope.scope,
					label: pullScope.label,
					relativePath: pullScope.relativePath,
				},
				gitSafety,
			};
			if (action === "pull-files" && result.code === 0) {
				const nextSite = siteWithRuntimeDefaults(site);
				const symlinkResult = pullScope.mayContainSymlinks ? await materializeRemoteWpSymlinks(nextSite) : null;
				const wpConfigResult = pullScope.touchesRoot ? await writeLocalWpConfig(nextSite) : null;
				const htaccessResult = pullScope.touchesRoot ? await ensureWordPressHtaccess(nextSite) : null;
				const restartResult = pullScope.touchesRoot ? await restartOpenLiteSpeedRuntime() : null;
				await writeManifest(nextSite);
				invalidateSiteDiskUsage(nextSite);
				const code = [
					result.code,
					symlinkResult ? symlinkResult.code : 0,
					restartResult ? restartResult.code : 0,
				].find((item) => item !== 0) || 0;
				return {
					...scopedResult,
					code,
					stdout: [
						scopedResult.stdout,
						symlinkResult ? symlinkResult.stdout : "",
						wpConfigResult ? formatWpConfigResult(wpConfigResult) : "",
						htaccessResult ? formatHtaccessResult(htaccessResult) : "",
						restartResult ? restartResult.stdout : "",
					].filter(Boolean).join("\n"),
					stderr: [
						result.stderr,
						symlinkResult ? symlinkResult.stderr : "",
						restartResult ? restartResult.stderr : "",
					].filter(Boolean).join("\n"),
					site: nextSite,
					symlinkRepair: symlinkResult,
					htaccess: htaccessResult,
					restart: restartResult,
					wpConfig: wpConfigResult,
				};
			}
			return scopedResult;
		}
		case "pull-db": {
			const localSite = siteWithRuntimeDefaults(site);
			await ensureSiteDirectories(localSite);
			const wpConfigResult = await writeLocalWpConfig(localSite);
			await writeManifest(localSite);
			const exportResult = await exportDatabase(localSite);
			if (exportResult.code !== 0) {
				invalidateSiteDiskUsage(localSite);
				return {
					code: exportResult.code,
					command: "ssh",
					args: ["<remote>", "wp db export -"],
					stdout: "",
					stderr: exportResult.stderr,
					dumpFile: exportResult.dumpFile,
					durationMs: exportResult.durationMs,
					site: localSite,
					wpConfig: wpConfigResult,
				};
			}

			const importResult = await importDatabase(localSite, exportResult.dumpFile);
			let searchReplaceResult = null;
			if (importResult.code === 0 && localSite.liveUrl && localSite.localUrl) {
				searchReplaceResult = await runWpCli(
					localSite,
					[
						"search-replace",
						localSite.liveUrl,
						localSite.localUrl,
						"--all-tables",
						"--skip-columns=guid",
					],
					{ cwd: localSite.localRoot },
				);
			}
			const dbUrlResult = importResult.code === 0 ? await normalizeLocalDatabaseUrls(localSite) : null;
			const htaccessResult = importResult.code === 0 ? await ensureWordPressHtaccess(localSite) : null;
			const restartResult = htaccessResult && htaccessResult.updated ? await restartOpenLiteSpeedRuntime() : null;
			const smokeResult = importResult.code === 0
				&& (!searchReplaceResult || searchReplaceResult.code === 0)
				&& (!dbUrlResult || dbUrlResult.code === 0)
				&& (!restartResult || restartResult.code === 0)
				? await runSmokeCheck(localSite)
				: null;
			const codeSyncResult = importResult.code === 0
				&& (!searchReplaceResult || searchReplaceResult.code === 0)
				&& (!dbUrlResult || dbUrlResult.code === 0)
				? await checkDatabaseCodeSync(localSite)
				: null;
			const dbResultCode = importResult.code
				|| (searchReplaceResult ? searchReplaceResult.code : 0)
				|| (dbUrlResult ? dbUrlResult.code : 0)
				|| (restartResult ? restartResult.code : 0);
			invalidateSiteDiskUsage(localSite);
			return {
				code: dbResultCode,
				command: "pull-db",
				args: [localSite.slug],
				stdout: [
					formatWpConfigResult(wpConfigResult),
					`Exported to ${exportResult.dumpFile}`,
					importResult.code === 0 ? `Imported ${exportResult.dumpFile} into ${localSite.dbName}.` : importResult.stdout,
					searchReplaceResult ? searchReplaceResult.stdout : "",
					dbUrlResult ? dbUrlResult.stdout : "",
					htaccessResult ? formatHtaccessResult(htaccessResult) : "",
					restartResult ? restartResult.stdout : "",
					smokeResult ? smokeResult.stdout : "",
					codeSyncResult ? formatCodeSyncReport(codeSyncResult) : "",
				].filter(Boolean).join("\n"),
				stderr: [
					exportResult.stderr,
					importResult.stderr,
					searchReplaceResult ? searchReplaceResult.stderr : "",
					dbUrlResult ? dbUrlResult.stderr : "",
					restartResult ? restartResult.stderr : "",
					smokeResult ? smokeResult.stderr : "",
				]
					.filter(Boolean)
					.join("\n"),
				dumpFile: exportResult.dumpFile,
				durationMs: exportResult.durationMs
					+ importResult.durationMs
					+ (searchReplaceResult ? searchReplaceResult.durationMs : 0)
					+ (dbUrlResult ? dbUrlResult.durationMs : 0)
					+ (restartResult ? restartResult.durationMs : 0)
					+ (smokeResult ? smokeResult.durationMs : 0)
					+ (codeSyncResult ? codeSyncResult.durationMs : 0),
				runtimeContext: firstRuntimeContext(searchReplaceResult, dbUrlResult, smokeResult),
				site: localSite,
				htaccess: htaccessResult,
				restart: restartResult,
				dbUrls: dbUrlResult,
				smokeCode: smokeResult ? smokeResult.code : null,
				smoke: smokeResult ? smokeResult.smoke : null,
				codeSync: codeSyncResult,
				wpConfig: wpConfigResult,
			};
		}
		case "smoke-check":
			return runSmokeCheck(site);
		case "push-audit":
			return runPushAudit(site, body);
		case "push-path-dry-run":
		case "push-path": {
			const confirm = String(body.confirm || "");
			if (action === "push-path" && confirm !== "PUSH") {
				throw httpError(400, "Push requires confirm: PUSH.");
			}
			const auditResult = await runPushAudit(site, body);
			const audit = auditResult.pushAudit;
			if (auditResult.code !== 0) {
				return {
					...auditResult,
					command: action,
					args: [site.slug, audit.relativePath],
					stdout: `${auditResult.stdout}\n\nPush stopped before rsync. Resolve audit issues first.`,
				};
			}
			const source = audit.sourceIsDirectory && !audit.source.endsWith("/") ? `${audit.source}/` : audit.source;
			const dest = audit.sourceIsDirectory && !audit.dest.endsWith("/") ? `${audit.dest}/` : audit.dest;
			const result = await runProcess(
				"rsync",
				rsyncArgs({
					dryRun: action.endsWith("dry-run"),
					deleteFiles: audit.deleteFiles,
					source,
					dest,
					sshPort: site.remotePort || "",
					identityFile: sshIdentityFileForSite(site),
				}),
				{ cwd: site.localRoot },
			);
			let historyPath = "";
			if (action === "push-path") {
				historyPath = await appendPushHistory(site, {
					createdAt: new Date().toISOString(),
					slug: site.slug,
					relativePath: audit.relativePath,
					source: audit.source,
					dest: audit.dest,
					deleteFiles: audit.deleteFiles,
					code: result.code,
					durationMs: result.durationMs,
					auditIssueCount: audit.issues.length,
					auditWarningCount: audit.warnings.length,
					rollbackNote: "Use the host backup/snapshot or restore the affected remote path from its previous release artifact before pushing again.",
				});
			}
			return {
				...result,
				stdout: [
					auditResult.stdout,
					result.stdout,
					historyPath ? `Push history: ${historyPath}` : "",
					historyPath ? "Rollback note: use the host backup/snapshot or restore the affected remote path from its previous release artifact before pushing again." : "",
				].filter(Boolean).join("\n\n"),
				pushAudit: audit,
				pushHistoryPath: historyPath,
				site,
			};
		}
		case "run-qa": {
			const qaBinary = (await pathExists("/Users/khofmeyer/Development/MRN-qa-engine/bin/mrn-qa"))
				? "/Users/khofmeyer/Development/MRN-qa-engine/bin/mrn-qa"
				: "mrn-qa";
			return runProcess(qaBinary, ["run", "--project-root", site.qaProjectRoot || site.localRoot], {
				cwd: site.qaProjectRoot || site.localRoot,
				timeoutMs: Number(process.env.MRN_LOCAL_HUB_QA_TIMEOUT_MS || "900000"),
			});
		}
		default:
			throw httpError(400, `Unknown action: ${action}`);
	}
}

const qaScreenshotExtensions = new Set([".png", ".jpg", ".jpeg", ".webp"]);

function qaScreenshotDir(site) {
	const root = path.resolve(site.qaProjectRoot || site.localRoot);
	return path.join(root, "outputs", "qa", "screenshots");
}

function sanitizeQaScreenshotName(value) {
	const name = String(value || "").trim();
	if (!name || name !== path.basename(name) || /[\0\r\n]/.test(name)) {
		throw httpError(400, "Screenshot filename is invalid.");
	}
	if (!qaScreenshotExtensions.has(path.extname(name).toLowerCase())) {
		throw httpError(400, "Screenshot file type is not supported.");
	}
	return name;
}

async function qaArtifactList(site) {
	const dir = qaScreenshotDir(site);
	let entries = [];
	try {
		entries = await fsp.readdir(dir, { withFileTypes: true });
	} catch (error) {
		if (error.code === "ENOENT") {
			return { root: dir, artifacts: [] };
		}
		throw error;
	}
	const artifacts = [];
	for (const entry of entries) {
		if (!entry.isFile() || !qaScreenshotExtensions.has(path.extname(entry.name).toLowerCase())) {
			continue;
		}
		const filePath = path.join(dir, entry.name);
		const stat = await fsp.stat(filePath);
		artifacts.push({
			name: entry.name,
			size: stat.size,
			modifiedAt: stat.mtime.toISOString(),
			url: `/api/sites/${encodeURIComponent(site.slug)}/qa-artifacts/files/${encodeURIComponent(entry.name)}`,
		});
	}
	artifacts.sort((a, b) => new Date(b.modifiedAt) - new Date(a.modifiedAt) || a.name.localeCompare(b.name));
	return { root: dir, artifacts };
}

async function clearQaArtifacts(site) {
	const dir = qaScreenshotDir(site);
	let entries = [];
	try {
		entries = await fsp.readdir(dir, { withFileTypes: true });
	} catch (error) {
		if (error.code === "ENOENT") {
			return { root: dir, removed: 0 };
		}
		throw error;
	}

	let removed = 0;
	for (const entry of entries) {
		if (!entry.isFile() || !qaScreenshotExtensions.has(path.extname(entry.name).toLowerCase())) {
			continue;
		}
		await fsp.rm(path.join(dir, entry.name), { force: true });
		removed += 1;
	}
	return { root: dir, removed };
}

async function serveQaScreenshot(res, site, rawName) {
	const dir = qaScreenshotDir(site);
	const name = sanitizeQaScreenshotName(rawName);
	const filePath = path.resolve(dir, name);
	const relative = path.relative(dir, filePath);
	if (relative.startsWith("..") || path.isAbsolute(relative)) {
		textResponse(res, 403, "Forbidden");
		return;
	}
	try {
		const body = await fsp.readFile(filePath);
		res.writeHead(200, {
			"content-type": contentTypeFor(filePath),
			"cache-control": "no-store",
		});
		res.end(body);
	} catch (error) {
		if (error.code === "ENOENT") {
			textResponse(res, 404, "Not found");
			return;
		}
		throw error;
	}
}

function contentTypeFor(filePath) {
	if (filePath.endsWith(".html")) return "text/html; charset=utf-8";
	if (filePath.endsWith(".css")) return "text/css; charset=utf-8";
	if (filePath.endsWith(".js")) return "application/javascript; charset=utf-8";
	if (filePath.endsWith(".json")) return "application/json; charset=utf-8";
	if (filePath.endsWith(".svg")) return "image/svg+xml";
	if (filePath.endsWith(".png")) return "image/png";
	if (filePath.endsWith(".jpg") || filePath.endsWith(".jpeg")) return "image/jpeg";
	if (filePath.endsWith(".webp")) return "image/webp";
	return "application/octet-stream";
}

async function serveStatic(req, res, url) {
	let filePath = url.pathname === "/" ? path.join(publicRoot, "index.html") : path.join(publicRoot, url.pathname);
	filePath = path.resolve(filePath);
	const relative = path.relative(publicRoot, filePath);
	if (relative.startsWith("..") || path.isAbsolute(relative)) {
		textResponse(res, 403, "Forbidden");
		return;
	}
	try {
		const body = await fsp.readFile(filePath);
		res.writeHead(200, {
			"content-type": contentTypeFor(filePath),
			"cache-control": "no-store",
		});
		res.end(body);
	} catch (error) {
		if (error.code === "ENOENT") {
			textResponse(res, 404, "Not found");
			return;
		}
		throw error;
	}
}

async function route(req, res) {
	const url = new URL(req.url, `http://${req.headers.host || `${host}:${port}`}`);
	const parts = url.pathname.split("/").filter(Boolean);

	applyCorsHeaders(req, res);
	if (req.method === "OPTIONS" && parts[0] === "api") {
		res.writeHead(204, { "cache-control": "no-store" });
		res.end();
		return;
	}

	if (parts[0] !== "api") {
		await serveStatic(req, res, url);
		return;
	}

	if (req.method === "GET" && url.pathname === "/api/health") {
		jsonResponse(res, 200, await healthReport());
		return;
	}

	if (req.method === "GET" && url.pathname === "/api/app-settings") {
		jsonResponse(res, 200, appSettingsReport());
		return;
	}

	if (req.method === "POST" && url.pathname === "/api/app-settings") {
		const body = await readBody(req);
		const report = await saveAppSettings(body);
		jsonResponse(res, 200, report);
		return;
	}

	if (req.method === "GET" && url.pathname === "/api/runtime") {
		jsonResponse(res, 200, await runtimeReport());
		return;
	}

	if (req.method === "GET" && url.pathname === "/api/metrics") {
		jsonResponse(res, 200, await metricsReport());
		return;
	}

	if (req.method === "GET" && url.pathname === "/api/providers") {
		jsonResponse(res, 200, { providers: providerPresets });
		return;
	}

	if (req.method === "GET" && url.pathname === "/api/credentials") {
		jsonResponse(res, 200, await credentialSummary());
		return;
	}

	if (req.method === "POST" && url.pathname === "/api/credentials/actions") {
		const body = await readBody(req);
		const result = await runCredentialAction(body);
		jsonResponse(res, result.code === 0 ? 200 : 500, { result });
		return;
	}

	if (req.method === "POST" && parts.length === 5 && parts[1] === "backups" && parts[2] === "updraft" && parts[3] === "uploads") {
		jsonResponse(res, 200, { session: await stageUpdraftUpload(req, url, parts[4]) });
		return;
	}

	if (req.method === "GET" && parts.length === 5 && parts[1] === "backups" && parts[2] === "updraft" && parts[3] === "sessions") {
		jsonResponse(res, 200, { session: await inspectUpdraftSession(parts[4]) });
		return;
	}

	if (req.method === "POST" && url.pathname === "/api/backups/updraft/actions") {
		const body = await readBody(req);
		const result = await runUpdraftBackupAction(body);
		jsonResponse(res, result.code === 0 ? 200 : 500, { result });
		return;
	}

	if (req.method === "GET" && url.pathname === "/api/provider-accounts") {
		jsonResponse(res, 200, await providerAccountSummary());
		return;
	}

	if (req.method === "POST" && url.pathname === "/api/provider-accounts/actions") {
		const body = await readBody(req);
		const result = await runProviderAccountAction(body);
		jsonResponse(res, result.code === 0 ? 200 : 500, { result });
		return;
	}

	if (req.method === "GET" && url.pathname === "/api/ssh/aliases") {
		jsonResponse(res, 200, await sshAliasReport());
		return;
	}

	if (req.method === "POST" && url.pathname === "/api/runtime/actions") {
		const body = await readBody(req);
		const result = await runRuntimeAction(body);
		jsonResponse(res, result.code === 0 ? 200 : 500, { result });
		return;
	}

	if (req.method === "POST" && url.pathname === "/api/ssh/actions") {
		const body = await readBody(req);
		const result = await runSshAction(body);
		jsonResponse(res, result.code === 0 ? 200 : 500, { result });
		return;
	}

	if (req.method === "GET" && url.pathname === "/api/sites") {
		jsonResponse(res, 200, { sites: await listSites() });
		return;
	}

	if (req.method === "POST" && url.pathname === "/api/sites") {
		const body = await readBody(req);
		const site = sanitizeManifest(body);
		if (await pathExists(manifestPathFor(site.localRoot))) {
			throw httpError(409, `Site already exists: ${site.slug}`);
		}
		const savedSite = await writeManifest(site);
		const friendlyCertResult = await syncFriendlyCertificateAfterSiteChange(savedSite, "create-site");
		jsonResponse(res, 201, {
			site: savedSite,
			friendlyCert: friendlyCertResult,
			friendlyUrls: friendlyCertResult.friendlyUrls || null,
		});
		return;
	}

	if (parts[0] === "api" && parts[1] === "sites" && parts[2]) {
		const slug = normalizeSlug(parts[2]);
		const site = await getSite(slug);

		if (req.method === "GET" && parts.length === 3) {
			jsonResponse(res, 200, { site });
			return;
		}

		if (req.method === "PUT" && parts.length === 3) {
			const body = await readBody(req);
			const previousPhpVersion = normalizePhpVersion(site.phpVersion);
			const nextPhpVersion = normalizePhpVersion(body.phpVersion || site.phpVersion);
			const updated = sanitizeManifest({ ...site, ...body, slug });
			if (nextPhpVersion !== previousPhpVersion) {
				updated.phpStatus = updated.activePhpVersion === nextPhpVersion ? "applied" : "pending";
				if (updated.phpStatus !== "applied") {
					updated.activePhpHandler = "";
				}
			}
			const savedSite = await writeManifest(updated);
			const friendlyCertResult = savedSite.localUrl !== site.localUrl
				? await syncFriendlyCertificateAfterSiteChange(savedSite, "update-site")
				: null;
			jsonResponse(res, 200, {
				site: savedSite,
				friendlyCert: friendlyCertResult,
				friendlyUrls: friendlyCertResult ? friendlyCertResult.friendlyUrls : null,
			});
			return;
		}

		if (req.method === "DELETE" && parts.length === 3) {
			const body = await readBody(req);
			const result = await deleteSite(site, body);
			jsonResponse(res, result.code === 0 ? 200 : 500, { result });
			return;
		}

		if (req.method === "GET" && parts.length === 4 && parts[3] === "qa-artifacts") {
			jsonResponse(res, 200, await qaArtifactList(site));
			return;
		}

		if (req.method === "DELETE" && parts.length === 4 && parts[3] === "qa-artifacts") {
			jsonResponse(res, 200, await clearQaArtifacts(site));
			return;
		}

		if (req.method === "GET" && parts.length === 6 && parts[3] === "qa-artifacts" && parts[4] === "files") {
			await serveQaScreenshot(res, site, decodeURIComponent(parts[5]));
			return;
		}

		if (req.method === "POST" && parts.length === 4 && parts[3] === "actions") {
			const body = await readBody(req);
			const result = await runSiteAction(site, body);
			jsonResponse(res, result.code === 0 ? 200 : 500, { result });
			return;
		}
	}

	jsonResponse(res, 404, { error: "Not found" });
}

const server = http.createServer((req, res) => {
	route(req, res).catch((error) => {
		const statusCode = error.statusCode || 500;
		jsonResponse(res, statusCode, {
			error: error.message || "Internal server error",
			details: error.details || null,
		});
	});
});
server.requestTimeout = 0;
server.timeout = 0;
server.keepAliveTimeout = 0;

if (process.argv.includes("--doctor")) {
	healthReport()
		.then((report) => {
			process.stdout.write(`${JSON.stringify(report, null, 2)}\n`);
		})
		.catch((error) => {
			process.stderr.write(`${error.stack || error.message}\n`);
			process.exit(1);
		});
} else {
	server.listen(port, host, () => {
		process.stdout.write(`MRN Local Hub listening at http://${host}:${port}\n`);
		process.stdout.write(`Sites root: ${sitesRoot}\n`);
		startFriendlyUrlProxy()
			.then((state) => {
				const status = state.https.status === "running" ? `https://*.localhost:${friendlyHttpsPort}` : state.https.status;
				process.stdout.write(`Friendly HTTPS: ${status}\n`);
			})
			.catch((error) => {
				friendlyProxyState.https.status = "error";
				friendlyProxyState.https.errors.push({ address: "loopback", port: friendlyHttpsPort, error: error.message });
				process.stdout.write(`Friendly HTTPS: ${error.message}\n`);
			});
	});
}
