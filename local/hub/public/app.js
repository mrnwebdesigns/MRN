"use strict";

const state = {
	sites: [],
	currentSlug: null,
	health: null,
	runtime: null,
	sshAliasReport: null,
	sshAliases: [],
	providerAccounts: null,
	metrics: null,
	metricHistory: {
		cpu: [],
		memory: [],
		jobs: [],
	},
	metricsTimer: null,
	activeSection: "dashboard",
	activeTab: "dashboard-overview",
	toastId: 0,
	discoveredSites: {
		wpengine: [],
		siteground: [],
	},
	busy: false,
	operationLabel: "",
	operationStartedAt: 0,
	operationTimer: null,
	qaArtifactTimer: null,
	pullReadiness: {},
	adminReadiness: {},
	pullWizardStage: "idle",
	addSiteStep: "source",
	addSiteMode: "ssh",
	siteFilters: {
		query: "",
		status: "all",
		provider: "all",
	},
	selectedSiteSlugs: new Set(),
};

const providerPresets = {
	generic: {
		label: "Generic SSH",
		remoteSshPlaceholder: "client-alias or user@server",
		remotePathPlaceholder: "/home/user/public_html",
		hint: "Use an SSH alias from ~/.ssh/config, then Prepare Connection tests SSH and inspects the WordPress root.",
	},
	mrndev: {
		label: "MRN Dev",
		remoteSshPlaceholder: "auto: site-user@mrndev-site-owner",
		remotePathPlaceholder: "/home/site-user/htdocs/site.mrndev.io",
		hint: "Enter a *.mrndev.io live URL or slug. Prepare Connection resolves the SSH target, finds the WordPress root, and inspects the site.",
	},
	runcloud: {
		label: "RunCloud",
		remoteSshPlaceholder: "client-runcloud or siteuser@server",
		remotePathPlaceholder: "/home/runcloud/webapps/app/public",
		hint: "Use a RunCloud SSH alias or user@host. Prepare Connection tries common RunCloud roots and inspects WordPress.",
	},
	siteground: {
		label: "SiteGround",
		remoteSshPlaceholder: "client-siteground",
		remotePathPlaceholder: "public_html or /home/customer/www/domain.com/public_html",
		hint: "Use a SiteGround SSH alias from Site Tools. Prepare Connection can try public_html and domain roots.",
	},
	wpengine: {
		label: "WP Engine",
		remoteSshPlaceholder: "client-wpengine",
		remotePathPlaceholder: "sites/environment",
		hint: "Use a WP Engine SSH Gateway alias. Prepare Connection can try sites/<environment> automatically.",
	},
};

const els = {
	refreshButton: document.querySelector("#refreshButton"),
	createSiteButton: document.querySelector("#createSiteButton"),
	newSlug: document.querySelector("#newSlug"),
	addSiteCancelButton: document.querySelector("#addSiteCancelButton"),
	addSiteBackButton: document.querySelector("#addSiteBackButton"),
	addSiteNextButton: document.querySelector("#addSiteNextButton"),
	addSiteSummary: document.querySelector("#addSiteSummary"),
	addSiteValidation: document.querySelector("#addSiteValidation"),
	addSiteModeInputs: document.querySelectorAll('input[name="addSiteMode"]'),
	addSiteStepPanels: document.querySelectorAll("[data-add-step-panel]"),
	addSiteStepItems: document.querySelectorAll("[data-add-step]"),
	sshCreateSiteButton: document.querySelector("#sshCreateSiteButton"),
	sshCreateImportButton: document.querySelector("#sshCreateImportButton"),
	addSitePullFiles: document.querySelector("#addSitePullFiles"),
	addSiteFileScope: document.querySelector("#addSiteFileScope"),
	addSiteCustomPathField: document.querySelector("#addSiteCustomPathField"),
	addSiteCustomPath: document.querySelector("#addSiteCustomPath"),
	addSitePullDb: document.querySelector("#addSitePullDb"),
	addSiteSmokeCheck: document.querySelector("#addSiteSmokeCheck"),
	addSiteImportSummary: document.querySelector("#addSiteImportSummary"),
	healthStrip: document.querySelector("#healthStrip"),
	dashboardSiteList: document.querySelector("#dashboardSiteList"),
	siteInventory: document.querySelector("#siteInventory"),
	siteSearchInput: document.querySelector("#siteSearchInput"),
	siteStatusFilter: document.querySelector("#siteStatusFilter"),
	siteProviderFilter: document.querySelector("#siteProviderFilter"),
	bulkSelectionSummary: document.querySelector("#bulkSelectionSummary"),
	selectVisibleSitesButton: document.querySelector("#selectVisibleSitesButton"),
	clearSiteSelectionButton: document.querySelector("#clearSiteSelectionButton"),
	bulkStartSitesButton: document.querySelector("#bulkStartSitesButton"),
	bulkStopSitesButton: document.querySelector("#bulkStopSitesButton"),
	tabbar: document.querySelector("#tabbar"),
	emptyState: document.querySelector("#emptyState"),
	siteDetail: document.querySelector("#siteDetail"),
	siteTitle: document.querySelector("#siteTitle"),
	siteSubtitle: document.querySelector("#siteSubtitle"),
	runtimeLabel: document.querySelector("#runtimeLabel"),
	siteForm: document.querySelector("#siteForm"),
	sshForm: document.querySelector("#sshForm"),
	saveSiteButton: document.querySelector("#saveSiteButton"),
	deleteSiteButton: document.querySelector("#deleteSiteButton"),
	openLocalButton: document.querySelector("#openLocalButton"),
	openAdminButton: document.querySelector("#openAdminButton"),
	runtimeOperationStatus: document.querySelector("#runtimeOperationStatus"),
	provisionSiteButton: document.querySelector("#provisionSiteButton"),
	adminStatus: document.querySelector("#adminStatus"),
	adminResultList: document.querySelector("#adminResultList"),
	pullFilesDbButton: document.querySelector("#pullFilesDbButton"),
	pullResultList: document.querySelector("#pullResultList"),
	pullWizardSteps: document.querySelectorAll("[data-pull-step]"),
	pullFileScope: document.querySelector("#pullFileScope"),
	pullCustomPathField: document.querySelector("#pullCustomPathField"),
	pullCustomPath: document.querySelector("#pullCustomPath"),
	pushPath: document.querySelector("#pushPath"),
	deleteFiles: document.querySelector("#deleteFiles"),
	outputConsole: document.querySelector("#outputConsole"),
	clearOutputButton: document.querySelector("#clearOutputButton"),
	qaOutputConsole: document.querySelector("#qaOutputConsole"),
	clearQaOutputButton: document.querySelector("#clearQaOutputButton"),
	clearQaAllButton: document.querySelector("#clearQaAllButton"),
	refreshQaScreenshotsButton: document.querySelector("#refreshQaScreenshotsButton"),
	qaScreenshotGrid: document.querySelector("#qaScreenshotGrid"),
	qaScreenshotSummary: document.querySelector("#qaScreenshotSummary"),
	siteCount: document.querySelector("#siteCount"),
	siteMetricSummary: document.querySelector("#siteMetricSummary"),
	cpuMetricSummary: document.querySelector("#cpuMetricSummary"),
	cpuMetricDetail: document.querySelector("#cpuMetricDetail"),
	memoryMetricSummary: document.querySelector("#memoryMetricSummary"),
	memoryMetricDetail: document.querySelector("#memoryMetricDetail"),
	jobMetricSummary: document.querySelector("#jobMetricSummary"),
	jobMetricDetail: document.querySelector("#jobMetricDetail"),
	cpuChart: document.querySelector("#cpuChart"),
	cpuChartValue: document.querySelector("#cpuChartValue"),
	memoryChart: document.querySelector("#memoryChart"),
	memoryChartValue: document.querySelector("#memoryChartValue"),
	jobsChart: document.querySelector("#jobsChart"),
	jobsChartValue: document.querySelector("#jobsChartValue"),
	runtimeSummary: document.querySelector("#runtimeSummary"),
	runtimeMetricSummary: document.querySelector("#runtimeMetricSummary"),
	sshMetricSummary: document.querySelector("#sshMetricSummary"),
	toolMetricSummary: document.querySelector("#toolMetricSummary"),
	toolStatus: document.querySelector("#toolStatus"),
	gitStatus: document.querySelector("#gitStatus"),
	olsStatus: document.querySelector("#olsStatus"),
	redisStatus: document.querySelector("#redisStatus"),
	operationStatus: document.querySelector("#operationStatus"),
	userMenu: document.querySelector("#userMenu"),
	themeToggle: document.querySelector("#themeToggle"),
	runtimeIsolation: document.querySelector("#runtimeIsolation"),
	runtimeInstance: document.querySelector("#runtimeInstance"),
	runtimeState: document.querySelector("#runtimeState"),
	friendlyUrlState: document.querySelector("#friendlyUrlState"),
	friendlyUrlStateMirror: document.querySelector("#friendlyUrlStateMirror"),
	httpsHelperStatusPill: document.querySelector("#httpsHelperStatusPill"),
	httpsCertStatusPill: document.querySelector("#httpsCertStatusPill"),
	httpsProxyStatusPill: document.querySelector("#httpsProxyStatusPill"),
	firefoxTrustStatusPill: document.querySelector("#firefoxTrustStatusPill"),
	httpsInstallStepCard: document.querySelector("#httpsInstallStepCard"),
	friendlyUrlStepCard: document.querySelector("#friendlyUrlStepCard"),
	httpsCertStepCard: document.querySelector("#httpsCertStepCard"),
	friendlyStartButton: document.querySelector("#friendlyStartButton"),
	installHttpsHelperButton: document.querySelector("#installHttpsHelperButton"),
	refreshSslCertButton: document.querySelector("#refreshSslCertButton"),
	trustFirefoxButton: document.querySelector("#trustFirefoxButton"),
	runtimePaths: document.querySelector("#runtimePaths"),
	pullSummary: document.querySelector("#pullSummary"),
	providerHint: document.querySelector("#providerHint"),
	checklist: document.querySelector("#checklist"),
	checklistSummary: document.querySelector("#checklistSummary"),
	sshAliasOptions: document.querySelector("#sshAliasOptions"),
	sshAliasSummary: document.querySelector("#sshAliasSummary"),
	sshAliasList: document.querySelector("#sshAliasList"),
	refreshSshAliasesButton: document.querySelector("#refreshSshAliasesButton"),
	wpEngineDiscoveryForm: document.querySelector("#wpEngineDiscoveryForm"),
	wpEngineListButton: document.querySelector("#wpEngineListButton"),
	wpEngineStatus: document.querySelector("#wpEngineStatus"),
	siteGroundRegistryForm: document.querySelector("#siteGroundRegistryForm"),
	siteGroundAddButton: document.querySelector("#siteGroundAddButton"),
	siteGroundStatus: document.querySelector("#siteGroundStatus"),
	refreshProviderAccountsButton: document.querySelector("#refreshProviderAccountsButton"),
	providerDiscoverySummary: document.querySelector("#providerDiscoverySummary"),
	providerResults: document.querySelector("#providerResults"),
	confirmOverlay: document.querySelector("#confirmOverlay"),
	confirmTitle: document.querySelector("#confirmTitle"),
	confirmMessage: document.querySelector("#confirmMessage"),
	confirmTokenLabel: document.querySelector("#confirmTokenLabel"),
	confirmInput: document.querySelector("#confirmInput"),
	confirmSubmitButton: document.querySelector("#confirmSubmitButton"),
	confirmCancelButton: document.querySelector("#confirmCancelButton"),
	toastRegion: document.querySelector("#toastRegion"),
};

const sectionTabs = {
	dashboard: [
		{ id: "dashboard-overview", label: "Overview" },
	],
	sites: [
		{ id: "sites-list", label: "All Sites" },
		{ id: "sites-add", label: "Add Site", hidden: true },
		{ id: "sites-settings", label: "Settings", requiresSite: true, hidden: true },
		{ id: "sites-sync", label: "Pull & Push", requiresSite: true, hidden: true },
		{ id: "sites-qa", label: "QA", requiresSite: true, hidden: true },
	],
	runtime: [
		{ id: "runtime-status", label: "Status" },
		{ id: "runtime-https", label: "HTTPS" },
		{ id: "runtime-maintenance", label: "Maintenance" },
		{ id: "runtime-details", label: "Details" },
	],
	logs: [
		{ id: "logs-console", label: "Deployment Log" },
	],
};

const addSiteSteps = ["source", "connection", "create"];
const sparklinePointCount = 28;
const siteDetailTabs = new Set(["sites-settings", "sites-sync", "sites-qa"]);
const pullFileScopeLabels = {
	full: "Full site",
	"full-no-uploads": "Full site, skip uploads",
	core: "Core/root",
	"wp-content": "wp-content",
	"active-theme": "Active theme",
	themes: "All themes",
	plugins: "Plugins",
	"mu-plugins": "MU plugins",
	uploads: "Uploads",
	custom: "Custom directory",
};

const legacyTabMap = {
	overview: ["dashboard", "dashboard-overview"],
	site: ["sites", "sites-settings"],
	"sites-overview": ["dashboard", "dashboard-overview"],
	"connect-import": ["sites", "sites-add"],
	"connect-providers": ["sites", "sites-add"],
	sync: ["sites", "sites-sync"],
	qa: ["sites", "sites-qa"],
	connect: ["sites", "sites-add"],
	runtime: ["runtime", "runtime-status"],
	logs: ["logs", "logs-console"],
};

function applyTheme(theme) {
	const nextTheme = theme === "dark" ? "dark" : "light";
	document.documentElement.dataset.theme = nextTheme;
	els.themeToggle.textContent = nextTheme === "dark" ? "Light mode" : "Dark mode";
	window.localStorage.setItem("mrn-local-hub-theme", nextTheme);
}

function closeUserMenu() {
	if (els.userMenu) {
		els.userMenu.open = false;
	}
}

function tabConfig(tab) {
	for (const [section, tabs] of Object.entries(sectionTabs)) {
		const config = tabs.find((item) => item.id === tab);
		if (config) {
			return { ...config, section };
		}
	}
	return null;
}

function sectionDefaultTab(section) {
	return sectionTabs[section]?.[0]?.id || "dashboard-overview";
}

function normalizeRoute(section, tab) {
	const site = currentSite();
	let nextSection = sectionTabs[section] ? section : "sites";
	let nextTab = tab || sectionDefaultTab(nextSection);
	if (legacyTabMap[nextTab]) {
		[nextSection, nextTab] = legacyTabMap[nextTab];
	}
	const config = tabConfig(nextTab);
	if (!config || config.section !== nextSection) {
		nextTab = sectionDefaultTab(nextSection);
	}
	const resolvedConfig = tabConfig(nextTab);
	if (!site && resolvedConfig?.requiresSite) {
		nextTab = sectionDefaultTab(nextSection);
	}
	return { section: nextSection, tab: nextTab };
}

function renderTabbar() {
	if (!els.tabbar) return;
	const site = currentSite();
	const items = (sectionTabs[state.activeSection] || sectionTabs.sites)
		.filter((item) => !item.hidden);
	els.tabbar.textContent = "";
	els.tabbar.hidden = items.length <= 1;
	for (const item of items) {
		const button = document.createElement("button");
		button.type = "button";
		button.dataset.tab = item.id;
		button.textContent = item.label;
		button.className = item.id === state.activeTab ? "active" : "";
		button.disabled = Boolean(item.requiresSite && !site);
		button.setAttribute("aria-selected", item.id === state.activeTab ? "true" : "false");
		button.addEventListener("click", () => {
			setActiveTab(item.id, state.activeSection);
		});
		els.tabbar.append(button);
	}
}

function renderSiteSubnav() {
	document.querySelectorAll("[data-site-view]").forEach((button) => {
		const isActive = button.dataset.siteView === state.activeTab;
		button.classList.toggle("active", isActive);
		button.setAttribute("aria-selected", isActive ? "true" : "false");
	});
}

function setActiveSection(section, preferredTab = "") {
	const existingTabIsInSection = tabConfig(state.activeTab)?.section === section;
	setActiveTab(preferredTab || (existingTabIsInSection ? state.activeTab : sectionDefaultTab(section)), section);
}

function setActiveTab(tab, section = "") {
	const inferred = section || tabConfig(tab)?.section || legacyTabMap[tab]?.[0] || state.activeSection || "sites";
	const route = normalizeRoute(inferred, tab);
	const nextSection = route.section;
	const nextTab = route.tab;
	state.activeSection = nextSection;
	state.activeTab = nextTab;
	window.localStorage.setItem("mrn-local-hub-section", nextSection);
	window.localStorage.setItem("mrn-local-hub-tab", nextTab);
	document.querySelector(".app")?.setAttribute("data-active-section", nextSection);
	document.querySelector(".app")?.setAttribute("data-active-tab", nextTab);
	document.querySelectorAll("[data-section]").forEach((button) => {
		const isActive = button.dataset.section === nextSection;
		button.classList.toggle("active", isActive);
		button.setAttribute("aria-selected", isActive ? "true" : "false");
	});
	renderTabbar();
	document.querySelectorAll("[data-tab-panel]").forEach((panel) => {
		const targets = String(panel.dataset.tabPanel || "").split(/\s+/);
		panel.classList.toggle("active", targets.includes(nextTab));
	});
	if (els.siteDetail) {
		els.siteDetail.hidden = !(currentSite() && siteDetailTabs.has(nextTab));
	}
	renderSiteSubnav();
	renderAddSiteWizard();
}

function showToast(message, type = "info") {
	if (!els.toastRegion || !message) return;
	const toast = document.createElement("div");
	const id = `toast-${++state.toastId}`;
	toast.id = id;
	toast.className = `toast toast-${type}`;
	toast.setAttribute("role", type === "error" ? "alert" : "status");
	const text = document.createElement("span");
	text.textContent = message;
	const dismiss = document.createElement("button");
	dismiss.type = "button";
	dismiss.setAttribute("aria-label", "Dismiss notification");
	dismiss.textContent = "×";
	const remove = () => {
		toast.classList.add("leaving");
		window.setTimeout(() => toast.remove(), 160);
	};
	dismiss.addEventListener("click", remove);
	toast.append(text, dismiss);
	els.toastRegion.append(toast);
	window.setTimeout(remove, type === "error" ? 7600 : 4600);
}

async function api(path, options = {}) {
	const response = await fetch(path, {
		headers: {
			"content-type": "application/json",
			...(options.headers || {}),
		},
		...options,
	});
	const data = await response.json();
	if (!response.ok && !data.result) {
		throw new Error(data.error || `Request failed: ${response.status}`);
	}
	return data;
}

function currentSite() {
	return state.sites.find((site) => site.slug === state.currentSlug) || null;
}

function formatElapsed(startedAt) {
	if (!startedAt) return "0:00";
	const seconds = Math.max(0, Math.floor((Date.now() - startedAt) / 1000));
	const minutes = Math.floor(seconds / 60);
	return `${minutes}:${String(seconds % 60).padStart(2, "0")}`;
}

function renderOperationStatus() {
	if (!els.operationStatus) return;
	if (!state.busy) {
		els.operationStatus.className = "pill operation-pill";
		els.operationStatus.textContent = "Idle";
		return;
	}
	els.operationStatus.className = "pill operation-pill busy";
	els.operationStatus.textContent = `${state.operationLabel || "Working"} ${formatElapsed(state.operationStartedAt)}`;
}

function setPill(element, text, status = "") {
	if (!element) return;
	element.textContent = text;
	element.className = `pill ${status}`.trim();
}

function firefoxTrustUi(firefoxTrust) {
	if (!firefoxTrust) {
		return { label: "Firefox checking", status: "", button: "Trust Firefox", action: "runtime-firefox-trust", disabled: true, title: "Firefox trust status has not loaded yet." };
	}
	if (!firefoxTrust.detected) {
		return { label: "Firefox not found", status: "", button: "Trust Firefox", action: "runtime-firefox-trust", disabled: true, title: firefoxTrust.message || "Firefox profiles were not found." };
	}
	if (firefoxTrust.status === "trusted") {
		return { label: "Firefox trusted", status: "ok", button: "Re-trust Firefox", action: "runtime-firefox-trust", disabled: false, title: "Firefox already trusts the local mkcert CA." };
	}
	if (firefoxTrust.status === "restart-required") {
		return { label: "Firefox restart", status: "warn", button: "Re-trust Firefox", action: "runtime-firefox-trust", disabled: false, title: firefoxTrust.message || "Fully quit and reopen Firefox to reload local certificate trust." };
	}
	if (firefoxTrust.status === "partial") {
		return { label: "Firefox partial", status: "warn", button: "Trust Firefox", action: "runtime-firefox-trust", disabled: false, title: firefoxTrust.message || "Some Firefox profiles still need the local CA." };
	}
	if (firefoxTrust.status === "missing-certutil") {
		return { label: "Firefox needs NSS", status: "bad", button: "Install NSS", action: "runtime-install-nss", disabled: false, title: "Install Homebrew NSS so Firefox can trust mkcert." };
	}
	return { label: "Firefox blocked", status: "bad", button: "Trust Firefox", action: "runtime-firefox-trust", disabled: false, title: firefoxTrust.message || "Firefox does not trust the local CA yet." };
}

function setBusy(isBusy, label = "Working") {
	state.busy = isBusy;
	if (state.operationTimer) {
		window.clearInterval(state.operationTimer);
		state.operationTimer = null;
	}
	if (isBusy) {
		state.operationLabel = label;
		state.operationStartedAt = Date.now();
		state.operationTimer = window.setInterval(renderOperationStatus, 1000);
	} else {
		state.operationLabel = "";
		state.operationStartedAt = 0;
	}
	document.body.classList.toggle("is-busy", isBusy);
	document.body.setAttribute("aria-busy", isBusy ? "true" : "false");
	document.querySelectorAll("button:not(#confirmSubmitButton):not(#confirmCancelButton)").forEach((button) => {
		button.disabled = isBusy;
	});
	if (!isBusy) {
		renderSiteOperationState(currentSite());
	}
	renderOperationStatus();
}

function setConfirmationOpen(isOpen) {
	els.confirmOverlay.hidden = !isOpen;
	document.body.classList.toggle("has-confirm-dialog", isOpen);
}

function requestConfirmation({ title, message, token }) {
	return new Promise((resolve) => {
		let settled = false;
		const cleanup = () => {
			els.confirmInput.removeEventListener("input", syncSubmitState);
			els.confirmSubmitButton.removeEventListener("click", confirm);
			els.confirmCancelButton.removeEventListener("click", cancel);
			els.confirmOverlay.removeEventListener("click", cancelFromBackdrop);
			document.removeEventListener("keydown", cancelFromEscape);
			setConfirmationOpen(false);
		};
		const finish = (value) => {
			if (settled) return;
			settled = true;
			cleanup();
			resolve(value);
		};
		const syncSubmitState = () => {
			els.confirmSubmitButton.disabled = els.confirmInput.value.trim() !== token;
		};
		const confirm = () => {
			if (els.confirmInput.value.trim() === token) {
				finish(token);
			}
		};
		const cancel = () => finish("");
		const cancelFromBackdrop = (event) => {
			if (event.target === els.confirmOverlay) {
				cancel();
			}
		};
		const cancelFromEscape = (event) => {
			if (event.key === "Escape") {
				cancel();
			}
		};

		els.confirmTitle.textContent = title;
		els.confirmMessage.textContent = message;
		els.confirmTokenLabel.textContent = `Type ${token} to continue`;
		els.confirmInput.value = "";
		els.confirmInput.placeholder = token;
		els.confirmSubmitButton.disabled = true;
		els.confirmInput.addEventListener("input", syncSubmitState);
		els.confirmSubmitButton.addEventListener("click", confirm);
		els.confirmCancelButton.addEventListener("click", cancel);
		els.confirmOverlay.addEventListener("click", cancelFromBackdrop);
		document.addEventListener("keydown", cancelFromEscape);
		setConfirmationOpen(true);
		els.confirmInput.focus();
	});
}

function writeConsole(consoleEl, text, className) {
	if (!consoleEl) return;
	consoleEl.textContent += text;
	consoleEl.className = className;
	consoleEl.scrollTop = consoleEl.scrollHeight;
}

function appendOutput(title, result, isError = false, notify = true, options = {}) {
	const stamp = new Date().toLocaleTimeString();
	const lines = [
		`[${stamp}] ${title}`,
		result.command ? `$ ${[result.command, ...(result.args || [])].join(" ")}` : "",
		result.stdout || "",
		result.stderr ? `STDERR:\n${result.stderr}` : "",
		typeof result.code === "number" ? `Exit: ${result.code}` : "",
		"",
	].filter(Boolean);
	const text = `${lines.join("\n")}\n`;
	const className = isError ? "output-error" : "output-ok";
	writeConsole(els.outputConsole, text, className);
	if (options.qa) {
		writeConsole(els.qaOutputConsole, text, className);
	}
	if (notify) {
		showToast(`${actionLabel(title)} ${isError ? "failed" : "finished"}`, isError ? "error" : "success");
	}
}

function appendPending(title, message, options = {}) {
	const stamp = new Date().toLocaleTimeString();
	const text = [
		`[${stamp}] ${title}`,
		message,
		"",
	].join("\n");
	writeConsole(els.outputConsole, text, "output-running");
	if (options.qa) {
		writeConsole(els.qaOutputConsole, text, "output-running");
	}
	showToast(`${title} started`, "info");
}

function appendMessage(message, isError = false) {
	appendOutput(message, { code: isError ? 1 : 0 }, isError, false);
	showToast(message, isError ? "error" : "success");
}

function renderQaScreenshots(artifacts = []) {
	if (!els.qaScreenshotGrid || !els.qaScreenshotSummary) return;
	els.qaScreenshotGrid.textContent = "";
	els.qaScreenshotSummary.textContent = artifacts.length
		? `${artifacts.length} screenshot${artifacts.length === 1 ? "" : "s"}`
		: "No screenshots found";
	if (!artifacts.length) {
		const empty = document.createElement("div");
		empty.className = "qa-screenshot-empty";
		empty.textContent = "No screenshots found";
		els.qaScreenshotGrid.append(empty);
		return;
	}
	for (const artifact of artifacts) {
		const link = document.createElement("a");
		link.className = "qa-screenshot-card";
		link.href = artifact.url;
		link.target = "_blank";
		link.rel = "noreferrer";
		link.innerHTML = `
			<img src="${escapeHtml(artifact.url)}" alt="${escapeHtml(artifact.name)}">
			<span>${escapeHtml(artifact.name)}</span>
		`;
		els.qaScreenshotGrid.append(link);
	}
}

async function refreshQaArtifacts(slug = state.currentSlug) {
	if (!els.qaScreenshotGrid || !slug) {
		renderQaScreenshots([]);
		return;
	}
	const response = await api(`/api/sites/${encodeURIComponent(slug)}/qa-artifacts`);
	renderQaScreenshots(response.artifacts || []);
}

async function clearQaArtifacts() {
	const site = currentSite();
	if (!site) {
		renderQaScreenshots([]);
		return;
	}
	const response = await api(`/api/sites/${encodeURIComponent(site.slug)}/qa-artifacts`, {
		method: "DELETE",
	});
	renderQaScreenshots([]);
	showToast(`Cleared ${response.removed || 0} QA screenshot${response.removed === 1 ? "" : "s"}`, "success");
}

function stopQaArtifactPolling() {
	if (state.qaArtifactTimer) {
		window.clearInterval(state.qaArtifactTimer);
		state.qaArtifactTimer = null;
	}
}

function startQaArtifactPolling(slug) {
	stopQaArtifactPolling();
	refreshQaArtifacts(slug).catch(() => renderQaScreenshots([]));
	state.qaArtifactTimer = window.setInterval(() => {
		refreshQaArtifacts(slug).catch(() => {});
	}, 3000);
}

function actionLabel(action) {
	const labels = {
		"open-local": "Open Site",
		"open-admin": "WP Admin",
		"provision-site": "Provision Site",
		"start-site": "Start Site",
		"stop-site": "Stop Site",
		"pull-preflight": "Pull Preflight",
		"pull-files-dry-run": "File Dry Run",
		"pull-files": "Pull Files",
		"pull-db": "Pull DB",
		"pull-files-db": "Pull Files & DB",
		"smoke-check": "Smoke Check",
		"delete-site": "Delete Site",
		"admin-check": "Admin Check",
		"admin-login": "Login to Admin",
		"admin-unlock": "Local Admin Unlock",
		"normalize-local-url": "Normalize Local URL",
		"push-audit": "Push Audit",
		"push-path-dry-run": "Push Dry Run",
		"push-path": "Push Path",
		"run-qa": "Run MRN QA",
	};
	return labels[action] || action;
}

function actionPendingMessage(action, site, payload = {}) {
	if (action === "pull-files") {
		return `Syncing ${payload.pullScopeLabel || "files"} from ${site.remoteSsh}:${site.remotePath} into ${site.publicPath}. This can take a while.`;
	}
	if (action === "pull-files-dry-run") {
		return `Checking ${payload.pullScopeLabel || "remote file"} changes for ${site.slug} before writing local files.`;
	}
	if (action === "pull-files-db") {
		return `Pulling ${payload.pullScopeLabel || "files"} first, then importing the remote database for ${site.slug}.`;
	}
	if (action === "pull-db") {
		return `Exporting the remote database, importing it locally, then running search-replace for ${site.slug}.`;
	}
	if (action === "smoke-check") {
		return `Testing home, REST API, admin, active theme CSS, and one internal page for ${site.slug}.`;
	}
	if (action === "run-qa") {
		return `Running MRN QA against ${site.qaProjectRoot || site.localRoot}. Screenshots refresh while it runs; command output appears when the QA command finishes.`;
	}
	if (action === "admin-check") {
		return `Checking wp-admin, wp-login.php, and active security/login plugins for ${site.slug}.`;
	}
	if (action === "admin-login") {
		return `Creating a one-time local wp-admin login for ${site.slug}. Remote files and remote database are not touched.`;
	}
	if (action === "admin-unlock") {
		return `Disabling high-confidence local security/login blockers for ${site.slug}. Remote files and remote database are not touched.`;
	}
	if (action === "normalize-local-url") {
		return `Updating the local WordPress database and wp-config.php for ${site.localUrl}. Remote files and remote database are not touched.`;
	}
	if (action === "pull-preflight") {
		return `Checking SSH, remote WordPress, local tools, and ${payload.pullScopeLabel || "pull"} commands for ${site.slug}.`;
	}
	if (action === "provision-site") {
		return `Creating or updating the local database and OpenLiteSpeed vhost for ${site.slug}.`;
	}
	if (action === "start-site") {
		return `Starting or provisioning the local site runtime for ${site.slug}.`;
	}
	if (action === "stop-site") {
		return `Marking ${site.slug} stopped in Local Hub. The shared runtime stays online.`;
	}
	if (action.startsWith("push-")) {
		return `Auditing ${payload.relativePath || "selected path"} for ${site.remoteSsh}:${site.remotePath}.`;
	}
	return `Running ${actionLabel(action)} for ${site.slug}.`;
}

function summarizePath(value) {
	if (!value) return "";
	const text = String(value);
	if (text.length <= 62) return text;
	return `...${text.slice(-59)}`;
}

function siteStatus(site) {
	return site.runtimeStatus || "planned";
}

function siteUsesFriendlyProxy(site) {
	if (!site?.localUrl) return false;
	try {
		const url = new URL(site.localUrl);
		return url.protocol === "https:" && url.hostname.endsWith(".localhost") && !url.port;
	} catch {
		return false;
	}
}

function friendlyUrlsReady() {
	return Boolean(state.runtime?.friendlyUrls?.ready);
}

function siteOpenBlockedReason(site) {
	if (!site) return "Select a site first.";
	if (site.runtimeStatus !== "provisioned") return "Start this site before opening it.";
	if (siteUsesFriendlyProxy(site) && !friendlyUrlsReady()) {
		const issue = state.runtime?.friendlyUrls?.issues?.[0] || "Friendly HTTPS URLs are not active.";
		return issue;
	}
	return "";
}

function siteSearchText(site) {
	return [
		site.slug,
		site.title,
		site.localUrl,
		site.liveUrl,
		site.localRoot,
		site.publicPath,
		site.remoteSsh,
		site.remotePath,
		siteStatus(site),
		providerLabel(site.provider),
		site.provider,
	].filter(Boolean).join(" ").toLowerCase();
}

function siteMatchesFilters(site) {
	const query = state.siteFilters.query.trim().toLowerCase();
	const status = state.siteFilters.status;
	const provider = state.siteFilters.provider;
	if (query && !siteSearchText(site).includes(query)) {
		return false;
	}
	if (status !== "all" && siteStatus(site) !== status) {
		return false;
	}
	if (provider !== "all" && (site.provider || "generic") !== provider) {
		return false;
	}
	return true;
}

function filteredSites() {
	return state.sites.filter(siteMatchesFilters);
}

function selectedSites() {
	return state.sites.filter((site) => state.selectedSiteSlugs.has(site.slug));
}

function pruneSelectedSites() {
	const existing = new Set(state.sites.map((site) => site.slug));
	for (const slug of state.selectedSiteSlugs) {
		if (!existing.has(slug)) {
			state.selectedSiteSlugs.delete(slug);
		}
	}
}

function renderProviderFilterOptions() {
	if (!els.siteProviderFilter) return;
	const currentValue = state.siteFilters.provider;
	const providers = [...new Set(state.sites.map((site) => site.provider || "generic"))]
		.sort((a, b) => providerLabel(a).localeCompare(providerLabel(b)));
	els.siteProviderFilter.textContent = "";
	const allOption = document.createElement("option");
	allOption.value = "all";
	allOption.textContent = "All providers";
	els.siteProviderFilter.append(allOption);
	for (const provider of providers) {
		const option = document.createElement("option");
		option.value = provider;
		option.textContent = providerLabel(provider);
		els.siteProviderFilter.append(option);
	}
	els.siteProviderFilter.value = providers.includes(currentValue) ? currentValue : "all";
	state.siteFilters.provider = els.siteProviderFilter.value;
}

function renderBulkControls(visibleSites = filteredSites()) {
	const selected = selectedSites();
	const visibleSelectedCount = visibleSites.filter((site) => state.selectedSiteSlugs.has(site.slug)).length;
	const allVisibleSelected = Boolean(visibleSites.length) && visibleSelectedCount === visibleSites.length;
	if (els.bulkSelectionSummary) {
		els.bulkSelectionSummary.textContent = `${selected.length} selected · ${visibleSites.length} shown`;
	}
	if (els.selectVisibleSitesButton) {
		els.selectVisibleSitesButton.disabled = state.busy || !visibleSites.length;
		els.selectVisibleSitesButton.textContent = allVisibleSelected ? "Deselect Visible" : "Select Visible";
	}
	if (els.clearSiteSelectionButton) {
		els.clearSiteSelectionButton.disabled = state.busy || !selected.length;
	}
	const canStart = selected.some((site) => siteStatus(site) !== "provisioned");
	const canStop = selected.some((site) => siteStatus(site) === "provisioned");
	if (els.bulkStartSitesButton) {
		els.bulkStartSitesButton.disabled = state.busy || !canStart;
	}
	if (els.bulkStopSitesButton) {
		els.bulkStopSitesButton.disabled = state.busy || !canStop;
	}
}

function toggleVisibleSiteSelection() {
	const visibleSites = filteredSites();
	if (!visibleSites.length) return;
	const allVisibleSelected = visibleSites.every((site) => state.selectedSiteSlugs.has(site.slug));
	for (const site of visibleSites) {
		if (allVisibleSelected) {
			state.selectedSiteSlugs.delete(site.slug);
		} else {
			state.selectedSiteSlugs.add(site.slug);
		}
	}
	renderSites();
}

function clearSiteSelection() {
	state.selectedSiteSlugs.clear();
	renderSites();
}

async function runBulkSiteAction(action) {
	const selected = selectedSites();
	const targets = selected.filter((site) => {
		const status = siteStatus(site);
		if (action === "start-site") {
			return status !== "provisioned";
		}
		if (action === "stop-site") {
			return status === "provisioned";
		}
		return false;
	});
	if (!targets.length) {
		showToast(`No selected sites can run ${actionLabel(action)}.`, "error");
		return;
	}

	const label = `Bulk ${actionLabel(action)}`;
	setBusy(true, label);
	appendPending(label, `${targets.length} selected site${targets.length === 1 ? "" : "s"} queued. Actions run one at a time so the log stays readable.`);
	let failed = 0;
	try {
		for (const site of targets) {
			const response = await api(`/api/sites/${encodeURIComponent(site.slug)}/actions`, {
				method: "POST",
				body: JSON.stringify({ action }),
			});
			const didFail = response.result.code !== 0;
			if (didFail) {
				failed += 1;
			}
			appendOutput(`${actionLabel(action)} ${site.slug}`, response.result, didFail, false);
			updatePullReadiness(site, action, response.result);
		}
		await refresh();
		showToast(
			failed
				? `${label} finished with ${failed} failure${failed === 1 ? "" : "s"}`
				: `${label} finished`,
			failed ? "error" : "success",
		);
	} catch (error) {
		appendMessage(error.message, true);
	} finally {
		setBusy(false);
		renderSites();
	}
}

function formatPercent(value) {
	const number = Number(value || 0);
	return `${Math.max(0, Math.min(100, number)).toFixed(number >= 10 ? 0 : 1)}%`;
}

function formatBytes(bytes) {
	const value = Number(bytes || 0);
	if (value < 1024) return `${value} B`;
	const units = ["KB", "MB", "GB", "TB"];
	let current = value / 1024;
	let unitIndex = 0;
	while (current >= 1024 && unitIndex < units.length - 1) {
		current /= 1024;
		unitIndex += 1;
	}
	return `${current.toFixed(current >= 10 ? 1 : 2)} ${units[unitIndex]}`;
}

function pushMetricHistory(key, value) {
	const list = state.metricHistory[key] || [];
	list.push(Number(value || 0));
	state.metricHistory[key] = list.slice(-28);
}

function renderSparkline(element, values, maxValue = 100) {
	if (!element) return;
	const safeMaxValue = Math.max(1, Number(maxValue || 1));
	const visibleValues = values.slice(-sparklinePointCount);
	const safeValues = [
		...Array.from({ length: Math.max(0, sparklinePointCount - visibleValues.length) }, () => null),
		...visibleValues,
	];
	element.textContent = "";
	for (const value of safeValues) {
		const bar = document.createElement("span");
		if (value === null) {
			bar.className = "empty";
			bar.style.height = "8%";
			bar.title = "No sample yet";
		} else {
			bar.style.height = `${Math.max(8, Math.min(100, (Number(value || 0) / safeMaxValue) * 100))}%`;
			bar.title = String(value);
		}
		element.append(bar);
	}
}

function hostnameFromInput(value) {
	const raw = String(value || "").trim();
	if (!raw) return "";
	try {
		return new URL(raw).hostname.replace(/^www\./, "");
	} catch {
		return raw.replace(/^https?:\/\//, "").split("/")[0].replace(/^www\./, "");
	}
}

function liveUrlFromIdentifier(value) {
	const raw = String(value || "").trim();
	const hostname = hostnameFromInput(raw);
	if (!hostname.includes(".")) return "";
	try {
		const parsed = new URL(/^https?:\/\//i.test(raw) ? raw : `https://${raw}`);
		return `${parsed.protocol}//${parsed.hostname}${parsed.pathname === "/" ? "" : parsed.pathname}`.replace(/\/+$/, "");
	} catch {
		return "";
	}
}

function syncImportIdentifier() {
	const identifier = els.sshForm.elements.slug;
	const liveUrl = els.sshForm.elements.liveUrl;
	if (!identifier || !liveUrl || liveUrl.value.trim()) return;
	const inferredLiveUrl = liveUrlFromIdentifier(identifier.value);
	if (inferredLiveUrl) {
		liveUrl.value = inferredLiveUrl;
	}
}

function formData() {
	const data = {};
	new FormData(els.siteForm).forEach((value, key) => {
		data[key] = value;
	});
	return data;
}

function sshFormData() {
	syncImportIdentifier();
	const data = {};
	new FormData(els.sshForm).forEach((value, key) => {
		data[key] = value;
	});
	return data;
}

function providerPreset(provider) {
	return providerPresets[provider] || providerPresets.generic;
}

function providerLabel(provider) {
	return providerPreset(provider).label;
}

function updateProviderHint() {
	const provider = els.sshForm.elements.provider?.value || "generic";
	const preset = providerPreset(provider);
	const remoteSsh = els.sshForm.elements.remoteSsh;
	const remotePath = els.sshForm.elements.remotePath;
	if (remoteSsh) {
		remoteSsh.placeholder = preset.remoteSshPlaceholder;
	}
	if (remotePath) {
		remotePath.placeholder = preset.remotePathPlaceholder;
	}
	els.providerHint.textContent = preset.hint;
}

function inferProviderFromAlias(alias) {
	const text = `${alias.name || ""} ${alias.hostName || ""}`.toLowerCase();
	if (text.includes("mrndev")) return "mrndev";
	if (text.includes("wpengine")) return "wpengine";
	if (text.includes("siteground") || text.includes("sg-host")) return "siteground";
	if (text.includes("runcloud")) return "runcloud";
	return "generic";
}

function applySshFields(fields) {
	if (!fields) return;
	for (const [key, value] of Object.entries(fields)) {
		const field = els.sshForm.elements[key];
		if (field && typeof value !== "undefined") {
			field.value = value;
		}
	}
	updateProviderHint();
	renderAddSiteWizard();
}

function applyInspectionFields(result) {
	const inspection = result?.inspection;
	const remotePath = inspection?.resolved_path || inspection?.remote_pwd || "";
	if (inspection?.wp_config === "1" && remotePath) {
		applySshFields({ remotePath });
	}
}

function providerFormData(form) {
	const data = {};
	new FormData(form).forEach((value, key) => {
		data[key] = value;
	});
	return data;
}

function selectedAddSiteMode() {
	const selected = [...els.addSiteModeInputs].find((input) => input.checked);
	return selected?.value || state.addSiteMode || "ssh";
}

function setSelectedAddSiteMode(mode) {
	state.addSiteMode = mode === "blank" ? "blank" : "ssh";
	els.addSiteModeInputs.forEach((input) => {
		input.checked = input.value === state.addSiteMode;
	});
}

function validLocalSlug(value) {
	return /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/.test(String(value || "").trim());
}

function addSiteSshValues() {
	const form = els.sshForm;
	return {
		provider: form?.elements.provider?.value || "generic",
		slug: form?.elements.slug?.value.trim() || "",
		liveUrl: form?.elements.liveUrl?.value.trim() || "",
		remoteSsh: form?.elements.remoteSsh?.value.trim() || "",
		remotePort: form?.elements.remotePort?.value.trim() || "",
		remotePath: form?.elements.remotePath?.value.trim() || "",
	};
}

function addSiteConnectionComplete() {
	if (state.addSiteMode === "blank") {
		return validLocalSlug(els.newSlug?.value || "");
	}
	const values = addSiteSshValues();
	const hasSiteIdentity = Boolean(values.slug || values.liveUrl || liveUrlFromIdentifier(values.slug));
	if (values.provider === "mrndev") {
		return hasSiteIdentity;
	}
	return hasSiteIdentity && Boolean(values.remoteSsh);
}

function addSiteStepComplete(step) {
	if (step === "source") {
		return Boolean(state.addSiteMode);
	}
	if (step === "connection" || step === "create") {
		return addSiteConnectionComplete();
	}
	return false;
}

function addSiteValidationMessage() {
	if (state.addSiteMode === "blank") {
		return validLocalSlug(els.newSlug?.value || "")
			? "Ready to create a blank local manifest."
			: "Enter a lowercase local slug using letters, numbers, and hyphens.";
	}
	const values = addSiteSshValues();
	const hasSiteIdentity = Boolean(values.slug || values.liveUrl || liveUrlFromIdentifier(values.slug));
	if (!hasSiteIdentity) {
		return "Enter the site URL or slug.";
	}
	if (values.provider !== "mrndev" && !values.remoteSsh) {
		return "Enter an SSH alias or target before continuing.";
	}
	if (state.addSiteStep === "create") {
		return "Ready to create the manifest and run the selected first import.";
	}
	return values.provider === "mrndev"
		? "Prepare Connection will resolve MRN Dev and inspect WordPress before review."
		: "Prepare Connection will test SSH and inspect WordPress before review.";
}

function renderAddSiteSummary() {
	if (!els.addSiteSummary) return;
	const rows = [];
	if (state.addSiteMode === "blank") {
		rows.push(
			["Type", "Blank local site"],
			["Local slug", els.newSlug?.value.trim() || "Required"],
			["Runtime", "Local VM OpenLiteSpeed"],
		);
	} else {
		const values = addSiteSshValues();
		rows.push(
			["Type", "Pull from SSH"],
			["Provider", providerLabel(values.provider)],
			["Site", values.liveUrl || values.slug || "Required"],
			["SSH", values.remoteSsh || (values.provider === "mrndev" ? "Auto-resolved from MRN Dev" : "Required")],
			["Remote root", values.remotePath || "Auto-detect during inspection"],
		);
	}

	els.addSiteSummary.textContent = "";
	for (const [label, value] of rows) {
		const term = document.createElement("dt");
		term.textContent = label;
		const description = document.createElement("dd");
		description.textContent = value;
		els.addSiteSummary.append(term, description);
	}
}

function renderAddSiteWizard() {
	const app = document.querySelector(".app");
	state.addSiteMode = selectedAddSiteMode();
	if (app) {
		app.dataset.addStep = state.addSiteStep;
		app.dataset.addMode = state.addSiteMode;
	}

	els.addSiteModeInputs.forEach((input) => {
		input.closest(".source-card")?.classList.toggle("selected", input.checked);
	});

	els.addSiteStepPanels.forEach((panel) => {
		panel.hidden = panel.dataset.addStepPanel !== state.addSiteStep;
	});
	document.querySelectorAll("[data-add-requires]").forEach((panel) => {
		panel.hidden = panel.dataset.addRequires !== state.addSiteMode;
	});

	const currentIndex = addSiteSteps.indexOf(state.addSiteStep);
	els.addSiteStepItems.forEach((item) => {
		const itemIndex = addSiteSteps.indexOf(item.dataset.addStep);
		item.classList.toggle("active", item.dataset.addStep === state.addSiteStep);
		item.classList.toggle("complete", itemIndex >= 0 && itemIndex < currentIndex);
	});

	if (els.addSiteBackButton) {
		els.addSiteBackButton.disabled = state.busy || currentIndex <= 0;
	}
	if (els.addSiteNextButton) {
		els.addSiteNextButton.textContent = state.addSiteStep === "connection" && state.addSiteMode === "ssh"
			? "Prepare Connection"
			: "Next";
		els.addSiteNextButton.hidden = state.addSiteStep === "create";
		els.addSiteNextButton.disabled = state.busy || !addSiteStepComplete(state.addSiteStep);
	}
	if (els.createSiteButton) {
		els.createSiteButton.hidden = state.addSiteMode !== "blank";
		els.createSiteButton.disabled = state.busy || state.addSiteMode !== "blank" || !addSiteStepComplete("create");
	}
	if (els.sshCreateSiteButton) {
		els.sshCreateSiteButton.hidden = state.addSiteMode !== "ssh";
		els.sshCreateSiteButton.disabled = state.busy || state.addSiteMode !== "ssh" || !addSiteStepComplete("create");
	}
	if (els.sshCreateImportButton) {
		els.sshCreateImportButton.hidden = state.addSiteMode !== "ssh";
		els.sshCreateImportButton.disabled = state.busy || state.addSiteMode !== "ssh" || !addSiteStepComplete("create");
	}
	if (els.addSiteValidation) {
		els.addSiteValidation.textContent = addSiteValidationMessage();
	}
	renderInitialImportOptions();
	renderAddSiteSummary();
}

function setAddSiteStep(step) {
	if (!addSiteSteps.includes(step)) return;
	state.addSiteStep = step;
	renderAddSiteWizard();
}

async function prepareAddSiteConnection() {
	if (state.addSiteMode !== "ssh") {
		return true;
	}

	const result = await runSshAction("ssh-inspect", { label: "Prepare Connection" });
	if (!result) {
		showToast("Connection prep failed. Check the log for details.", "error");
		return false;
	}
	if (result.code !== 0) {
		showToast("SSH inspection failed. Check the SSH target, key, or path.", "error");
		return false;
	}
	if (result.inspection?.wp_config !== "1") {
		showToast("WordPress root was not found. Add a remote root or use Advanced SSH checks.", "error");
		return false;
	}
	showToast("Connection prepared.", "success");
	return true;
}

async function advanceAddSiteStep() {
	if (!addSiteStepComplete(state.addSiteStep)) {
		showToast(addSiteValidationMessage(), "error");
		return;
	}
	const currentIndex = addSiteSteps.indexOf(state.addSiteStep);
	if (state.addSiteStep === "connection") {
		const prepared = await prepareAddSiteConnection();
		if (!prepared) {
			return;
		}
	}
	setAddSiteStep(addSiteSteps[Math.min(addSiteSteps.length - 1, currentIndex + 1)]);
}

function retreatAddSiteStep() {
	const currentIndex = addSiteSteps.indexOf(state.addSiteStep);
	setAddSiteStep(addSiteSteps[Math.max(0, currentIndex - 1)]);
}

function startAddSiteFlow() {
	setSelectedAddSiteMode("ssh");
	setAddSiteStep("source");
	setActiveTab("sites-add", "sites");
}

function cancelAddSiteFlow() {
	setAddSiteStep("source");
	setActiveTab("sites-list", "sites");
}

function providerSiteFields(site) {
	return site.sshFields || {
		provider: site.provider || "generic",
		slug: site.slug || "",
		liveUrl: site.liveUrl || "",
		remoteSsh: site.remoteSsh || "",
		remotePort: site.remotePort || "",
		remotePath: site.remotePath || "",
	};
}

function renderProviderDiscovery() {
	if (!els.providerResults) return;

	const accounts = state.providerAccounts?.accounts || {};
	const wpEngine = accounts.wpengine || {};
	if (els.wpEngineStatus) {
		els.wpEngineStatus.textContent = wpEngine.envConfigured
			? "Environment credentials are ready; UI credentials remain optional."
			: "Enter credentials for one list call, or set Hub env vars.";
	}

	const sitegroundCount = state.discoveredSites.siteground.length;
	if (els.siteGroundStatus) {
		els.siteGroundStatus.textContent = `${sitegroundCount} saved local SiteGround entr${sitegroundCount === 1 ? "y" : "ies"}.`;
	}

	const sites = [
		...state.discoveredSites.wpengine,
		...state.discoveredSites.siteground,
	].sort((a, b) => `${a.provider}:${a.slug}`.localeCompare(`${b.provider}:${b.slug}`));

	els.providerDiscoverySummary.textContent = sites.length
		? `${sites.length} provider site${sites.length === 1 ? "" : "s"} ready to hand off to SSH Import.`
		: "No provider sites loaded yet.";

	els.providerResults.textContent = "";
	if (!sites.length) {
		const empty = document.createElement("p");
		empty.className = "mini-muted";
		empty.textContent = "List WP Engine installs or save SiteGround SSH details to start.";
		els.providerResults.append(empty);
		return;
	}

	for (const site of sites) {
		const item = document.createElement("article");
		item.className = "provider-result";
		const fields = providerSiteFields(site);
		const meta = [
			site.liveUrl || "no live URL",
			fields.remoteSsh || "SSH target not set",
			fields.remotePath || "auto path",
		].join(" · ");
		item.innerHTML = `
			<div>
				<span class="provider-badge">${escapeHtml(providerLabel(site.provider))}</span>
				<strong>${escapeHtml(site.name || site.slug)}</strong>
				<span>${escapeHtml(meta)}</span>
			</div>
			<div class="provider-result-actions"></div>
		`;
		const actions = item.querySelector(".provider-result-actions");
		const useButton = document.createElement("button");
		useButton.type = "button";
		useButton.className = "action";
		useButton.textContent = "Use";
		useButton.addEventListener("click", () => useProviderSite(site));
		actions.append(useButton);
		if (site.provider === "siteground") {
			const removeButton = document.createElement("button");
			removeButton.type = "button";
			removeButton.className = "ghost-button";
			removeButton.textContent = "Remove";
			removeButton.addEventListener("click", () => removeProviderSite(site));
			actions.append(removeButton);
		}
		els.providerResults.append(item);
	}
}

function useProviderSite(site) {
	setSelectedAddSiteMode("ssh");
	applySshFields(providerSiteFields(site));
	appendMessage(`Loaded ${providerLabel(site.provider)} site ${site.slug} into SSH Import`);
	setActiveTab("sites-add", "sites");
	setAddSiteStep("connection");
	document.querySelector(".ssh-panel")?.scrollIntoView({ behavior: "smooth", block: "start" });
}

function renderSshAliasOptions() {
	if (!els.sshAliasOptions) return;
	els.sshAliasOptions.textContent = "";
	for (const alias of state.sshAliases) {
		const option = document.createElement("option");
		option.value = alias.name;
		option.label = [alias.user, alias.hostName].filter(Boolean).join("@") || alias.name;
		els.sshAliasOptions.append(option);
	}
}

function formatAliasMeta(alias) {
	const target = [alias.user, alias.hostName].filter(Boolean).join("@") || alias.hostName || "target from config";
	const port = alias.port ? `:${alias.port}` : "";
	const keys = alias.identityFiles?.length
		? alias.identityFiles.map((item) => `${item.displayPath}${item.exists ? "" : " missing"}`).join(", ")
		: "agent/default key";
	return `${target}${port} · ${keys}`;
}

function renderSshAliases() {
	renderSshAliasOptions();
	if (!els.sshAliasList || !els.sshAliasSummary) return;

	const report = state.sshAliasReport;
	if (!report) {
		els.sshAliasSummary.textContent = "SSH aliases have not loaded yet.";
		if (els.sshMetricSummary) {
			els.sshMetricSummary.textContent = "Checking aliases";
		}
		els.sshAliasList.textContent = "";
		return;
	}

	els.sshAliasSummary.textContent = [
		`${report.aliasCount || 0} aliases`,
		report.agent?.summary || "agent unknown",
		report.files?.length ? `${report.files.length} config files` : "no config file",
	].join(" · ");
	if (els.sshMetricSummary) {
		els.sshMetricSummary.textContent = `${report.aliasCount || 0} aliases`;
	}

	els.sshAliasList.textContent = "";
	if (!state.sshAliases.length) {
		const empty = document.createElement("p");
		empty.className = "mini-muted";
		empty.textContent = "No direct Host aliases found in ~/.ssh/config.";
		els.sshAliasList.append(empty);
		return;
	}

	for (const alias of state.sshAliases.slice(0, 24)) {
		const button = document.createElement("button");
		button.type = "button";
		button.className = "ssh-alias-button";
		button.dataset.sshAlias = alias.name;
		button.innerHTML = `<strong>${escapeHtml(alias.name)}</strong><span>${escapeHtml(formatAliasMeta(alias))}</span>`;
		button.addEventListener("click", () => selectSshAlias(alias.name));
		els.sshAliasList.append(button);
	}
}

function renderChecklist() {
	if (!els.checklist || !els.checklistSummary) return;

	const runtimeRunning = state.runtime?.status === "running";
	const hasSites = state.sites.length > 0;
	const hasProvisionedSite = state.sites.some((site) => site.runtimeStatus === "provisioned");
	const hasPulledSite = state.sites.some((site) => site.runtimeStatus === "provisioned" && site.remoteSsh && site.remotePath);
	const providerSiteCount = state.discoveredSites.wpengine.length + state.discoveredSites.siteground.length;
	const items = [
		{
			label: "Manifest control plane",
			detail: "Site records, local paths, and guarded actions are in place.",
			status: "success",
		},
		{
			label: "SSH profiles and import shell",
			detail: `${state.sshAliasReport?.aliasCount || 0} local aliases detected; MRN Dev, RunCloud, SiteGround, and WP Engine presets are ready.`,
			status: "success",
		},
		{
			label: "Provider discovery",
			detail: providerSiteCount
				? `${providerSiteCount} provider site${providerSiteCount === 1 ? "" : "s"} ready to load into SSH Import.`
				: "WP Engine account listing and SiteGround local registry are wired in.",
			status: state.providerAccounts ? "success" : "current",
		},
		{
			label: "OpenLiteSpeed runtime",
			detail: runtimeRunning ? "Lima runtime is running with HTTP, admin, and MariaDB ports forwarded." : "Bootstrap or start the Lima runtime.",
			status: runtimeRunning ? "success" : "current",
		},
		{
			label: "Per-site provisioner",
			detail: runtimeRunning ? "Provision Site can create DB users and OpenLiteSpeed vhosts." : "Runtime must be running before site provisioning.",
			status: runtimeRunning ? "success" : "pending",
		},
		{
			label: "First real site import",
			detail: hasSites
				? hasProvisionedSite
					? hasPulledSite
						? "Run preflight, pull files, then pull the database for a real site."
						: "Add SSH details, then preflight and pull the first site."
					: "Provision the first manifest, then pull files and database."
				: "Choose an SSH alias, inspect WordPress, and create the first manifest.",
			status: hasPulledSite ? "success" : runtimeRunning ? "current" : "pending",
		},
		{
			label: "Push-back hardening",
			detail: "Push Audit, guarded approvals, blocked local artifacts, rollback notes, and deploy history are in place.",
			status: "success",
		},
	];

	const completed = items.filter((item) => item.status === "success").length;
	const current = items.find((item) => item.status === "current");
	els.checklistSummary.textContent = `${completed}/${items.length} complete. Next: ${current?.label || "ready for real-site workflow"}.`;
	els.checklist.textContent = "";
	for (const item of items) {
		const li = document.createElement("li");
		li.className = item.status === "success" ? "success" : item.status === "current" ? "current" : "";
		li.innerHTML = `<strong>${escapeHtml(item.label)}</strong><span>${escapeHtml(item.detail)}</span>`;
		els.checklist.append(li);
	}
}

function selectSshAlias(aliasName) {
	const alias = state.sshAliases.find((item) => item.name === aliasName);
	if (!alias) return;
	els.sshForm.elements.remoteSsh.value = alias.name;
	els.sshForm.elements.remotePort.value = "";
	els.sshForm.elements.provider.value = inferProviderFromAlias(alias);
	updateProviderHint();
	appendMessage(`Selected SSH alias ${alias.name}`);
}

function setActionButtons(actions, disabled, title = "") {
	for (const action of actions) {
		document.querySelectorAll(`[data-action="${action}"]`).forEach((button) => {
			button.disabled = disabled;
			button.title = disabled ? title : "";
		});
	}
}

function pullFileSelectionFromControls(scopeControl, customPathControl, { requireCustomPath = false } = {}) {
	const scope = scopeControl?.value || "full";
	const customPath = (customPathControl?.value || "").trim().replace(/^\/+|\/+$/g, "");
	const label = scope === "custom"
		? customPath
			? `Custom: ${customPath}`
			: "Custom directory"
		: pullFileScopeLabels[scope] || "Full site";
	if (scope === "custom" && requireCustomPath && !customPath) {
		appendMessage("Enter a custom pull path before running the file pull.", true);
		return null;
	}
	return {
		fileScope: scope,
		relativePath: scope === "custom" ? customPath : "",
		pullScopeLabel: label,
	};
}

function currentPullFileSelection({ requireCustomPath = false } = {}) {
	return pullFileSelectionFromControls(els.pullFileScope, els.pullCustomPath, { requireCustomPath });
}

function renderPullScopeControls() {
	const isCustom = (els.pullFileScope?.value || "full") === "custom";
	if (els.pullCustomPathField) {
		els.pullCustomPathField.hidden = !isCustom;
	}
	if (els.pullCustomPath) {
		els.pullCustomPath.disabled = !currentSite() || !isCustom;
	}
}

function setPullWizardStage(stage) {
	state.pullWizardStage = stage || "idle";
	renderPullWizardSteps(currentSite());
}

function renderPullWizardSteps(site = currentSite()) {
	const stage = state.pullWizardStage || "idle";
	const current = stage === "idle" && site ? "files" : stage;
	const completeSteps = new Set();
	if (stage === "database") {
		completeSteps.add("files");
	} else if (stage === "verify") {
		completeSteps.add("files");
		completeSteps.add("database");
	} else if (stage === "complete") {
		completeSteps.add("files");
		completeSteps.add("database");
		completeSteps.add("verify");
	}
	els.pullWizardSteps.forEach((item) => {
		const step = item.dataset.pullStep;
		item.classList.toggle("complete", completeSteps.has(step));
		item.classList.toggle("current", Boolean(site) && current === step);
		item.classList.toggle("disabled", !site);
	});
}

function initialImportOptions({ requireCustomPath = false } = {}) {
	const pullFiles = Boolean(els.addSitePullFiles?.checked);
	const pullDb = Boolean(els.addSitePullDb?.checked);
	const smokeCheck = Boolean(els.addSiteSmokeCheck?.checked);
	const selection = pullFileSelectionFromControls(els.addSiteFileScope, els.addSiteCustomPath, { requireCustomPath: requireCustomPath && pullFiles });
	if (!selection) {
		return null;
	}
	return {
		pullFiles,
		pullDb,
		smokeCheck,
		...selection,
	};
}

function renderInitialImportOptions() {
	const isCustom = (els.addSiteFileScope?.value || "full") === "custom";
	const pullFiles = Boolean(els.addSitePullFiles?.checked);
	if (els.addSiteCustomPathField) {
		els.addSiteCustomPathField.hidden = !isCustom;
	}
	if (els.addSiteFileScope) {
		els.addSiteFileScope.disabled = !pullFiles;
	}
	if (els.addSiteCustomPath) {
		els.addSiteCustomPath.disabled = !pullFiles || !isCustom;
	}
	if (!els.addSiteImportSummary) {
		return;
	}
	const options = initialImportOptions();
	if (!options) {
		els.addSiteImportSummary.className = "operation-status warn";
		els.addSiteImportSummary.textContent = "Choose a custom path before importing.";
		return;
	}
	const steps = ["provision local runtime"];
	if (options.pullFiles) {
		steps.push(`pull ${options.pullScopeLabel}`);
	}
	if (options.pullDb) {
		steps.push("pull database");
	}
	if (options.smokeCheck) {
		steps.push("run final smoke check");
	}
	const missingFilesForDb = options.pullDb && !options.pullFiles;
	const needsCoreFiles = options.pullDb && options.pullFiles && !["full", "full-no-uploads", "core"].includes(options.fileScope);
	els.addSiteImportSummary.className = `operation-status ${missingFilesForDb || needsCoreFiles ? "warn" : ""}`;
	els.addSiteImportSummary.textContent = missingFilesForDb
		? "Database import needs WordPress files. Keep file pull on for the first import."
		: needsCoreFiles
			? "Database import needs WordPress core. Choose Full site, Full site without uploads, or Core/root."
		: `Create & Import will ${steps.join(", ")}.`;
}

function renderSiteOperationState(site) {
	const status = site?.runtimeStatus || "planned";
	const provisioned = status === "provisioned";
	const stopped = status === "stopped";
	const hasRemote = Boolean(site?.remoteSsh && site?.remotePath);
	const runtimeReady = Boolean(site && provisioned);
	const canUseRemoteOps = runtimeReady && hasRemote;
	const openBlockedReason = siteOpenBlockedReason(site);

	if (els.runtimeOperationStatus) {
		els.runtimeOperationStatus.className = `operation-status ${provisioned ? "ok" : site ? "warn" : ""}`;
		if (!site) {
			els.runtimeOperationStatus.textContent = "Select a site to provision the local runtime.";
		} else if (provisioned) {
			els.runtimeOperationStatus.textContent = "This site is already provisioned in the local OpenLiteSpeed runtime.";
		} else if (stopped) {
			els.runtimeOperationStatus.textContent = "This site is stopped. Start it to make local operations available again.";
		} else if (status === "provision-error") {
			els.runtimeOperationStatus.textContent = "Provisioning failed previously. Retry after reviewing the deployment log.";
		} else {
			els.runtimeOperationStatus.textContent = "Provision this site before pulling files, checking admin access, or pushing changes.";
		}
	}

	if (els.provisionSiteButton) {
		els.provisionSiteButton.disabled = !site || provisioned;
		els.provisionSiteButton.dataset.action = stopped ? "start-site" : "provision-site";
		els.provisionSiteButton.textContent = provisioned
			? "Provisioned"
			: stopped
				? "Start Site"
				: status === "provision-error"
					? "Retry Provision"
					: "Provision Site";
		els.provisionSiteButton.className = provisioned ? "ghost-button" : "action";
		els.provisionSiteButton.title = provisioned ? "This site is already provisioned." : "";
	}

	if (els.openLocalButton) {
		els.openLocalButton.disabled = Boolean(openBlockedReason);
		els.openLocalButton.title = openBlockedReason || "Open the local site.";
	}
	if (els.openAdminButton) {
		els.openAdminButton.disabled = Boolean(openBlockedReason);
		els.openAdminButton.title = openBlockedReason || "Open wp-admin with a one-time local login.";
	}

	setActionButtons(
		["pull-preflight", "pull-files-dry-run", "pull-files", "pull-db"],
		!canUseRemoteOps,
		runtimeReady ? "Add remote SSH and WordPress root before pulling." : "Provision the site before pulling.",
	);
	if (els.pullFilesDbButton) {
		els.pullFilesDbButton.disabled = !canUseRemoteOps;
		els.pullFilesDbButton.title = canUseRemoteOps ? "" : runtimeReady ? "Add remote SSH and WordPress root before pulling." : "Provision the site before pulling.";
	}
	setActionButtons(
		["smoke-check", "admin-check", "admin-unlock"],
		!runtimeReady,
		"Provision the site before running local checks.",
	);
	setActionButtons(
		["normalize-local-url"],
		!runtimeReady,
		"Provision the site before normalizing local URLs.",
	);
	setActionButtons(
		["push-audit", "push-path-dry-run", "push-path"],
		!canUseRemoteOps,
		runtimeReady ? "Add remote SSH and WordPress root before pushing." : "Provision the site before pushing.",
	);
	if (els.pushPath) {
		els.pushPath.disabled = !canUseRemoteOps;
	}
	if (els.deleteFiles) {
		els.deleteFiles.disabled = !canUseRemoteOps;
	}
	if (els.pullFileScope) {
		els.pullFileScope.disabled = !site;
	}
	if (els.deleteSiteButton) {
		els.deleteSiteButton.disabled = !site;
		els.deleteSiteButton.title = site ? "Delete this local site and runtime resources." : "Select a site before deleting.";
	}
	renderPullScopeControls();
}

function fillForm(site) {
	els.siteForm.querySelectorAll("[name]").forEach((field) => {
		field.value = site[field.name] ?? "";
	});
	els.siteTitle.textContent = site.title || site.slug;
	els.siteSubtitle.textContent = site.localUrl || site.localRoot || "Local WordPress workspace";
	els.runtimeLabel.textContent = `${providerLabel(site.provider)} / ${site.webserver || "openlitespeed"} / ${site.runtimeStatus || "planned"}`;
	renderPullSummary(site);
	renderPullWizardSteps(site);
	renderAdminSummary(site);
	renderSiteOperationState(site);
}

function renderPullSummary(site) {
	if (els.pullResultList) {
		els.pullResultList.textContent = "";
	}
	if (!site) {
		els.pullSummary.className = "operation-status";
		els.pullSummary.textContent = "Select a site with SSH details to run preflight.";
		renderPullWizardSteps(null);
		return;
	}

	const readiness = state.pullReadiness[site.slug];
	if (readiness) {
		els.pullSummary.className = `operation-status ${readiness.ok ? "ok" : "warn"}`;
		els.pullSummary.textContent = readiness.message;
		if (els.pullResultList && readiness.items) {
			for (const item of readiness.items) {
				const li = document.createElement("li");
				li.textContent = item;
				els.pullResultList.append(li);
			}
		}
		return;
	}

	if (!site.remoteSsh || !site.remotePath) {
		els.pullSummary.className = "operation-status warn";
		els.pullSummary.textContent = "Add remote SSH and WordPress root before pulling.";
		return;
	}

	const selection = currentPullFileSelection();
	const scopeText = selection.fileScope === "custom" && !selection.relativePath ? "choose a custom directory" : selection.pullScopeLabel;
	els.pullSummary.className = "operation-status";
	els.pullSummary.textContent = `${scopeText}: ${site.remoteSsh}:${summarizePath(site.remotePath)} -> ${summarizePath(site.publicPath)}`;
	renderPullWizardSteps(site);
}

function formatAdminAccessStatus(status) {
	if (status === "reachable") return "wp-admin reachable";
	if (status === "security-blocked") return "security plugin appears to block admin";
	if (status === "redirected") return "wp-admin redirected";
	if (status === "http-error") return "wp-admin returned an HTTP error";
	return status || "unknown admin state";
}

function summarizeAdminResult(action, result) {
	const access = result.adminAccess || {};
	const after = access.after || null;
	const finalStatus = after?.status || access.status || access.before?.status || "unknown";
	const candidates = access.candidates || [];
	const blockers = candidates.filter((candidate) => candidate.disable);
	const deactivated = access.deactivated || [];
	const items = [];

	if (action === "admin-unlock") {
		items.push(`${deactivated.length} local plugin${deactivated.length === 1 ? "" : "s"} deactivated.`);
		if (access.backupPath) {
			items.push(`Unlock record saved: ${access.backupPath}`);
		}
	} else {
		items.push(`${blockers.length} high-confidence blocker${blockers.length === 1 ? "" : "s"} found.`);
	}

	if (candidates.length) {
		items.push(`${candidates.length} security/login plugin candidate${candidates.length === 1 ? "" : "s"} inspected.`);
	}
	items.push(`Final state: ${formatAdminAccessStatus(finalStatus)}.`);

	return {
		ok: result.code === 0,
		message: action === "admin-unlock"
			? result.code === 0
				? `Unlock complete for ${result.args?.[0] || "site"}.`
				: `Unlock finished with issues for ${result.args?.[0] || "site"}.`
			: result.code === 0
				? `Admin check complete: ${formatAdminAccessStatus(finalStatus)}.`
				: `Admin check found issues: ${formatAdminAccessStatus(finalStatus)}.`,
		items,
	};
}

function updateAdminReadiness(site, action, result) {
	if (!site || !["admin-check", "admin-unlock"].includes(action)) return;
	state.adminReadiness[site.slug] = summarizeAdminResult(action, result);
	renderAdminSummary(site);
}

function renderAdminSummary(site = currentSite()) {
	if (!els.adminStatus || !els.adminResultList) return;
	els.adminResultList.textContent = "";
	if (!site) {
		els.adminStatus.className = "operation-status";
		els.adminStatus.textContent = "Select a site to inspect local wp-admin access.";
		return;
	}

	const readiness = state.adminReadiness[site.slug];
	if (!readiness) {
		els.adminStatus.className = "operation-status";
		els.adminStatus.textContent = "Run Admin Check to inspect local wp-admin access and plugin blockers.";
		return;
	}

	els.adminStatus.className = `operation-status ${readiness.ok ? "ok" : "warn"}`;
	els.adminStatus.textContent = readiness.message;
	for (const item of readiness.items) {
		const li = document.createElement("li");
		li.textContent = item;
		els.adminResultList.append(li);
	}
}

function smokeFollowUpItems(smoke) {
	const checks = smoke?.checks || [];
	return checks
		.filter((check) => check.status && check.status !== "pass")
		.map((check) => {
			const status = check.status === "warn" ? "Warning" : "Issue";
			const detail = `${check.label} ${status.toLowerCase()}: ${check.detail}`;
			if (check.label === "Admin" && /blocked by a WordPress security plugin/i.test(check.detail || "")) {
				return `${detail} Run Local Admin Unlock if you need wp-admin access; otherwise the database import is okay.`;
			}
			if (check.status === "warn") {
				return `${detail} Review only if it blocks the work you are doing locally.`;
			}
			return `${detail} Correct this before treating the local site as ready.`;
		});
}

function updatePullReadiness(site, action, result) {
	if (!site) return;
	if (action === "pull-preflight") {
		const issues = result.preflight?.issues || [];
		const warnings = result.preflight?.warnings || [];
		const notes = [...issues, ...warnings];
		const scopeLabel = result.preflight?.pullScope?.label || result.pullScope?.label || "Pull";
		state.pullReadiness[site.slug] = {
			ok: result.code === 0 && !notes.length,
			message: issues.length
				? `Preflight blocked: ${issues.join(" ")}`
				: warnings.length
					? `Preflight warning: ${warnings.join(" ")}`
					: `${scopeLabel} preflight ready. Run a file dry run before pulling.`,
			items: notes,
		};
	} else if (action === "pull-files-dry-run") {
		const scopeLabel = result.pullScope?.label || "File";
		state.pullWizardStage = result.code === 0 ? "files" : "idle";
		state.pullReadiness[site.slug] = {
			ok: result.code === 0,
			message: result.code === 0 ? `${scopeLabel} dry run completed. Pull Files is ready when you are.` : `${scopeLabel} dry run failed; review the deployment log.`,
		};
	} else if (action === "pull-files") {
		const scopeLabel = result.pullScope?.label || "Files";
		state.pullWizardStage = result.code === 0 ? "database" : "files";
		state.pullReadiness[site.slug] = {
			ok: result.code === 0,
			message: result.code === 0 ? `${scopeLabel} pulled into the local public path.` : `${scopeLabel} pull failed; review the deployment log.`,
		};
	} else if (action === "provision-site") {
		state.pullReadiness[site.slug] = {
			ok: result.code === 0,
			message: result.code === 0 ? "Runtime provisioned. Pull files next, then pull the database." : "Runtime provisioning failed; review the deployment log.",
		};
	} else if (action === "start-site") {
		state.pullReadiness[site.slug] = {
			ok: result.code === 0,
			message: result.code === 0 ? "Site is running locally." : "Site start failed; review the deployment log.",
		};
	} else if (action === "stop-site") {
		state.pullReadiness[site.slug] = {
			ok: result.code === 0,
			message: result.code === 0 ? "Site marked stopped in Local Hub." : "Site stop failed; review the deployment log.",
		};
	} else if (action === "pull-db") {
		const smoke = result.smoke;
		const smokeFailed = Boolean(smoke && smoke.failed);
		const smokeWarned = Boolean(smoke && smoke.warnings);
		const followUps = smokeFollowUpItems(smoke);
		state.pullWizardStage = result.code === 0 ? "complete" : "verify";
		state.pullReadiness[site.slug] = {
			ok: result.code === 0 && !smokeFailed && !smokeWarned,
			message: smoke
				? result.code === 0
					? smokeFailed
						? `Database pulled. Smoke check found ${smoke.failed} follow-up issue${smoke.failed === 1 ? "" : "s"}; review the deployment log.`
						: smokeWarned
							? `Database pulled. Smoke check passed with ${smoke.warnings} warning${smoke.warnings === 1 ? "" : "s"}.`
							: `Database pulled. Smoke check passed (${smoke.passed} passed).`
					: `Database import failed before smoke check finished.`
				: result.code === 0
					? "Database pulled and search-replaced for local."
					: "Database pull failed; review the deployment log.",
			items: followUps,
		};
	} else if (action === "smoke-check") {
		const smoke = result.smoke;
		const smokeWarned = Boolean(smoke && smoke.warnings);
		state.pullWizardStage = result.code === 0 ? "complete" : "verify";
		state.pullReadiness[site.slug] = {
			ok: result.code === 0 && !smokeWarned,
			message: smoke
				? result.code === 0
					? `Smoke check passed (${smoke.passed} passed${smoke.warnings ? `, ${smoke.warnings} warning${smoke.warnings === 1 ? "" : "s"}` : ""}).`
					: `Smoke check found ${smoke.failed} failure${smoke.failed === 1 ? "" : "s"}; review the deployment log.`
				: result.code === 0
					? "Smoke check passed."
					: "Smoke check failed; review the deployment log.",
			items: smokeFollowUpItems(smoke),
		};
	}
	renderPullSummary(site);
}

function renderSites() {
	if (els.dashboardSiteList) {
		els.dashboardSiteList.textContent = "";
	}
	if (els.siteInventory) {
		els.siteInventory.textContent = "";
	}
	pruneSelectedSites();
	renderProviderFilterOptions();
	const provisionedCount = state.sites.filter((site) => site.runtimeStatus === "provisioned").length;
	const metricsBySlug = new Map((state.metrics?.sites || []).map((site) => [site.slug, site]));
	const visibleSites = filteredSites();

	for (const site of state.sites) {
		const status = site.runtimeStatus || "planned";
		const siteMetrics = metricsBySlug.get(site.slug) || {};

		if (els.dashboardSiteList && status === "provisioned") {
			const card = document.createElement("button");
			card.type = "button";
			card.className = `site-tab site-card${site.slug === state.currentSlug ? " active" : ""}`;
			card.dataset.slug = site.slug;
			card.innerHTML = `
			<span class="site-status ${status === "provisioned" ? "ok" : "planned"}" aria-hidden="true"></span>
			<span class="site-tab-main">
				<strong>${escapeHtml(site.title || site.slug)}</strong>
				<span>${escapeHtml(site.localUrl || site.localRoot || "")}</span>
				<span>Disk ${escapeHtml(formatBytes(siteMetrics.diskBytes || 0))} · Memory ${escapeHtml(siteMetrics.memoryNote || "Shared runtime")} · Jobs ${escapeHtml(String(siteMetrics.jobs || 0))}</span>
			</span>
		`;
			card.addEventListener("click", () => selectSite(site.slug, true));
			els.dashboardSiteList.append(card);
		}
	}

	for (const site of visibleSites) {
		const status = site.runtimeStatus || "planned";
		const siteMetrics = metricsBySlug.get(site.slug) || {};
		const remoteLabel = site.remoteSsh && site.remotePath
			? `${site.remoteSsh}:${summarizePath(site.remotePath)}`
			: "Remote not configured";
		if (els.siteInventory) {
			const row = document.createElement("article");
			const openBlockedReason = siteOpenBlockedReason(site);
			const openDisabled = openBlockedReason ? "disabled" : "";
			const openTitle = openBlockedReason ? ` title="${escapeHtml(openBlockedReason)}"` : "";
			row.className = `site-inventory-row${site.slug === state.currentSlug ? " active" : ""}${state.selectedSiteSlugs.has(site.slug) ? " selected" : ""}`;
			row.dataset.slug = site.slug;
			row.innerHTML = `
				<label class="site-row-select">
					<input data-site-select="${escapeHtml(site.slug)}" type="checkbox" ${state.selectedSiteSlugs.has(site.slug) ? "checked" : ""} aria-label="Select ${escapeHtml(site.title || site.slug)}">
				</label>
				<button type="button" class="site-row-main">
					<span class="site-status ${status === "provisioned" ? "ok" : "planned"}" aria-hidden="true"></span>
					<span>
						<strong>${escapeHtml(site.title || site.slug)}</strong>
						<small class="site-row-meta">${escapeHtml(providerLabel(site.provider))} · ${escapeHtml(status)} · ${escapeHtml(site.localUrl || site.localRoot || "")}</small>
						<small class="site-row-stats">Disk ${escapeHtml(formatBytes(siteMetrics.diskBytes || 0))} · Memory ${escapeHtml(siteMetrics.memoryNote || "Shared runtime")} · Jobs ${escapeHtml(String(siteMetrics.jobs || 0))} · ${escapeHtml(remoteLabel)}</small>
					</span>
				</button>
				<div class="site-row-actions">
					${status === "provisioned" ? `<button data-site-action="open-local" data-site-slug="${escapeHtml(site.slug)}" type="button" class="ghost-button" ${openDisabled}${openTitle}>Open</button>` : ""}
					${status === "provisioned" ? `<button data-site-action="admin-login" data-site-slug="${escapeHtml(site.slug)}" type="button" class="ghost-button" ${openDisabled}${openTitle}>WP Admin</button>` : ""}
					${status === "provisioned"
						? `<button data-site-action="stop-site" data-site-slug="${escapeHtml(site.slug)}" type="button" class="ghost-button danger-button">Stop</button>`
						: `<button data-site-action="start-site" data-site-slug="${escapeHtml(site.slug)}" type="button" class="ghost-button">Start</button>`}
				</div>
			`;
			row.querySelector("[data-site-select]").addEventListener("change", (event) => {
				if (event.target.checked) {
					state.selectedSiteSlugs.add(site.slug);
				} else {
					state.selectedSiteSlugs.delete(site.slug);
				}
				renderSites();
			});
			row.querySelector(".site-row-main").addEventListener("click", () => selectSite(site.slug, true));
			row.querySelectorAll("[data-site-action]").forEach((button) => {
				button.addEventListener("click", (event) => {
					event.stopPropagation();
					const targetSite = state.sites.find((item) => item.slug === button.dataset.siteSlug);
					if (targetSite) {
						runAction(button.dataset.siteAction, targetSite);
					}
				});
			});
			els.siteInventory.append(row);
		}
	}

	if (els.dashboardSiteList && !els.dashboardSiteList.children.length) {
		const empty = document.createElement("div");
		empty.className = "empty-dashboard-card";
		empty.innerHTML = "<strong>No running sites</strong><span>Start a site from the Sites section to see live per-site metrics here.</span>";
		els.dashboardSiteList.append(empty);
	}

	if (els.siteInventory && !state.sites.length) {
		const empty = document.createElement("div");
		empty.className = "empty-dashboard-card";
		empty.innerHTML = "<strong>No local sites yet</strong><span>Use Add Site to inspect SSH details and create a manifest.</span>";
		els.siteInventory.append(empty);
	} else if (els.siteInventory && state.sites.length && !visibleSites.length) {
		const empty = document.createElement("div");
		empty.className = "empty-dashboard-card";
		empty.innerHTML = "<strong>No sites match</strong><span>Adjust search, status, or provider filters.</span>";
		els.siteInventory.append(empty);
	}

	els.siteCount.textContent = `${state.sites.length} configured`;
	if (els.siteMetricSummary) {
		els.siteMetricSummary.textContent = state.sites.length
			? `${visibleSites.length} shown of ${state.sites.length} · ${provisionedCount} provisioned · ${state.sites.length - provisionedCount} planned or pending`
			: "No local site manifests loaded.";
	}
	renderBulkControls(visibleSites);
}

function renderHealth() {
	if (els.redisStatus) {
		els.redisStatus.className = "pill bad";
		els.redisStatus.textContent = "Redis Not Complete";
		els.redisStatus.title = "Redis cache integration is tracked as a Local Hub TODO.";
	}

	if (!state.health) {
		els.healthStrip.textContent = "Checking local tools...";
		els.healthStrip.className = "health-strip";
		els.toolStatus.className = "pill";
		els.toolStatus.textContent = "Checking";
		if (els.gitStatus) {
			els.gitStatus.className = "pill";
			els.gitStatus.textContent = "Git checking";
			els.gitStatus.title = "Checking whether Git-aware pull/push safety is available.";
		}
		els.olsStatus.className = "pill bad";
		els.olsStatus.textContent = "OLS missing";
		if (els.toolMetricSummary) {
			els.toolMetricSummary.textContent = "Checking tools";
		}
		return;
	}

	const required = ["node", "php", "wp", "ssh", "rsync", "mysql"];
	const missing = state.health.commands
		.filter((item) => required.includes(item.command) && !item.ok)
		.map((item) => item.command);
	const git = state.health.commands.find((item) => item.command === "git");
	const hasOpenLiteSpeed = state.health.openLiteSpeed.some((item) => item.ok);
	if (els.gitStatus) {
		els.gitStatus.className = git?.ok ? "pill ok" : "pill warn";
		els.gitStatus.textContent = git?.ok ? "Git ready" : "Git missing";
		els.gitStatus.title = git?.ok
			? `Git-aware file safety is available at ${git.path}.`
			: "Git-aware file safety is unavailable; pulls can still use backup-only protection.";
	}

	if (missing.length) {
		els.healthStrip.className = "health-strip warn";
		els.healthStrip.textContent = `Missing: ${missing.join(", ")}`;
		els.toolStatus.className = "pill bad";
		els.toolStatus.textContent = "Tools missing";
		els.olsStatus.className = hasOpenLiteSpeed ? "pill ok" : "pill bad";
		els.olsStatus.textContent = hasOpenLiteSpeed ? "OLS ready" : "OLS missing";
		els.runtimeSummary.textContent = hasOpenLiteSpeed ? "OpenLiteSpeed detected" : "OpenLiteSpeed pending";
		if (els.toolMetricSummary) {
			els.toolMetricSummary.textContent = `${missing.length} missing`;
		}
		if (els.runtimeMetricSummary) {
			els.runtimeMetricSummary.textContent = hasOpenLiteSpeed ? "Server binary detected." : "Server binary not detected.";
		}
		return;
	}

	els.healthStrip.className = hasOpenLiteSpeed ? "health-strip ok" : "health-strip";
	els.healthStrip.textContent = hasOpenLiteSpeed ? "OpenLiteSpeed detected" : "Core tools ready; OLS not detected";
	els.toolStatus.className = "pill ok";
	els.toolStatus.textContent = "Tools ready";
	els.olsStatus.className = hasOpenLiteSpeed ? "pill ok" : "pill bad";
	els.olsStatus.textContent = hasOpenLiteSpeed ? "OLS ready" : "OLS missing";
	els.runtimeSummary.textContent = hasOpenLiteSpeed ? "OpenLiteSpeed detected" : "OpenLiteSpeed pending";
	if (els.toolMetricSummary) {
		els.toolMetricSummary.textContent = "Core tools ready";
	}
	if (els.runtimeMetricSummary) {
		els.runtimeMetricSummary.textContent = hasOpenLiteSpeed ? "OpenLiteSpeed available locally." : "Lima runtime may provide OpenLiteSpeed.";
	}
}

function renderRuntime() {
	if (!state.runtime) {
		els.runtimeState.textContent = "Checking";
		if (els.friendlyUrlState) {
			els.friendlyUrlState.textContent = "Checking";
		}
		setPill(els.httpsHelperStatusPill, "Helper checking");
		setPill(els.httpsCertStatusPill, "Cert checking");
		setPill(els.httpsProxyStatusPill, "Proxy checking");
		setPill(els.firefoxTrustStatusPill, "Firefox checking");
		if (els.trustFirefoxButton) {
			els.trustFirefoxButton.disabled = true;
			els.trustFirefoxButton.textContent = "Trust Firefox";
			els.trustFirefoxButton.title = "Firefox trust status has not loaded yet.";
		}
		els.runtimePaths.textContent = "Runtime status has not loaded yet.";
		return;
	}

	const friendly = state.runtime.friendlyUrls || null;
	const friendlyReady = Boolean(friendly?.ready);
	const friendlyIssue = friendly?.issues?.[0] || "";
	const helperInstalled = Boolean(friendly?.helper?.installed);
	const helperHealthy = Boolean(friendly?.helper?.healthy);
	const certReady = friendly?.cert?.status === "ready";
	const firefoxTrust = friendly?.browserTrust?.firefox || null;
	const firefoxUi = firefoxTrustUi(firefoxTrust);
	els.runtimeIsolation.textContent = state.runtime.adapter === "lima-openlitespeed" ? "Lima VM" : state.runtime.adapter;
	els.runtimeInstance.textContent = state.runtime.instanceName;
	els.runtimeState.textContent = state.runtime.status;
	if (els.friendlyUrlState) {
		els.friendlyUrlState.textContent = friendlyReady ? "SSL active" : friendlyIssue || "Blocked";
	}
	if (els.friendlyUrlStateMirror) {
		els.friendlyUrlStateMirror.textContent = friendlyReady ? "SSL active" : friendlyIssue || "Blocked";
	}
	setPill(
		els.httpsHelperStatusPill,
		helperHealthy ? "Helper active" : helperInstalled ? "Helper installed" : "Helper missing",
		helperHealthy ? "ok" : helperInstalled ? "" : "bad",
	);
	setPill(
		els.httpsCertStatusPill,
		certReady ? "Cert ready" : "Cert needed",
		certReady ? "ok" : "bad",
	);
	setPill(
		els.httpsProxyStatusPill,
		friendlyReady ? "Proxy active" : helperHealthy ? "Proxy blocked" : "Proxy waiting",
		friendlyReady ? "ok" : helperHealthy ? "bad" : "",
	);
	setPill(els.firefoxTrustStatusPill, firefoxUi.label, firefoxUi.status);
	if (els.trustFirefoxButton) {
		els.trustFirefoxButton.disabled = firefoxUi.disabled;
		els.trustFirefoxButton.textContent = firefoxUi.button;
		els.trustFirefoxButton.title = firefoxUi.title;
		els.trustFirefoxButton.dataset.runtimeAction = firefoxUi.action;
		els.trustFirefoxButton.className = firefoxUi.status === "ok" ? "ghost-button success-button" : "ghost-button";
	}
	if (els.friendlyStartButton) {
		els.friendlyStartButton.disabled = !helperInstalled && !friendlyReady;
		els.friendlyStartButton.textContent = friendlyReady
			? "Verify"
			: helperInstalled
				? "Start"
				: "Required";
		els.friendlyStartButton.title = helperInstalled || friendlyReady
			? "Verify the no-port HTTPS proxy."
			: "No-port HTTPS needs the macOS helper first.";
		els.friendlyStartButton.className = friendlyReady ? "ghost-button success-button" : helperInstalled ? "action" : "ghost-button";
	}
	if (els.installHttpsHelperButton) {
		els.installHttpsHelperButton.textContent = helperInstalled ? "Reinstall" : "Install Helper";
		els.installHttpsHelperButton.className = helperInstalled ? "ghost-button success-button" : "action";
	}
	if (els.refreshSslCertButton) {
		els.refreshSslCertButton.className = certReady ? "ghost-button success-button" : "ghost-button";
	}
	if (els.httpsInstallStepCard) {
		els.httpsInstallStepCard.classList.toggle("done", helperInstalled);
		els.httpsInstallStepCard.classList.toggle("active", !helperInstalled);
		els.httpsInstallStepCard.classList.toggle("blocked", false);
	}
	if (els.friendlyUrlStepCard) {
		els.friendlyUrlStepCard.classList.toggle("done", friendlyReady);
		els.friendlyUrlStepCard.classList.toggle("active", helperInstalled && !friendlyReady);
		els.friendlyUrlStepCard.classList.toggle("blocked", !helperInstalled && !friendlyReady);
	}
	if (els.httpsCertStepCard) {
		els.httpsCertStepCard.classList.toggle("done", certReady);
		els.httpsCertStepCard.classList.toggle("active", helperInstalled && !certReady);
		els.httpsCertStepCard.classList.toggle("blocked", false);
	}
	els.runtimePaths.textContent = [
		`script: ${state.runtime.paths.bootstrapScript}`,
		`config: ${state.runtime.paths.limaConfig || "n/a"}`,
		`ports: ${state.runtime.ports.map((port) => `${port.label} ${port.host}->${port.guest}`).join(", ")}`,
		friendly ? `friendly urls: ${friendly.ready ? "ready" : "not ready"} (${friendly.pattern})` : "",
		friendly?.cert ? `ssl cert: ${friendly.cert.status} ${friendly.cert.certPath || ""}` : "",
		firefoxTrust ? `firefox trust: ${firefoxTrust.status} ${firefoxTrust.message || ""}` : "",
		friendly?.helper ? `https helper: ${friendly.helper.healthy ? "healthy" : friendly.helper.installed ? "installed but not healthy" : "not installed"} (${friendly.helper.label})` : "",
		friendly?.https?.owner?.occupied ? `port ${friendly.https.owner.port}: ${friendly.https.owner.summary}` : "",
		friendly?.issues?.length ? `friendly issues: ${friendly.issues.join(" | ")}` : "",
		state.runtime.missing.length ? `missing: ${state.runtime.missing.join(", ")}` : "prerequisites: ready",
	].filter(Boolean).join("\n");
	if (state.runtime.status === "running") {
		els.runtimeSummary.textContent = "OpenLiteSpeed running in Lima";
		els.olsStatus.className = "pill ok";
		els.olsStatus.textContent = "OLS running";
		els.healthStrip.className = "health-strip ok";
		els.healthStrip.textContent = friendlyReady ? "Local runtime + SSL ready" : "Local runtime running";
		if (els.runtimeMetricSummary) {
			els.runtimeMetricSummary.textContent = friendlyReady
				? `${state.runtime.instanceName} · ${friendly.pattern}`
				: `${state.runtime.instanceName} · Friendly URLs blocked${friendlyIssue ? `: ${friendlyIssue}` : ""}`;
		}
	} else {
		els.runtimeSummary.textContent = `OpenLiteSpeed ${state.runtime.status}`;
		if (els.runtimeMetricSummary) {
			els.runtimeMetricSummary.textContent = `Instance ${state.runtime.instanceName} is ${state.runtime.status}.`;
		}
	}
}

function renderMetrics() {
	const metrics = state.metrics;
	if (!metrics) {
		return;
	}
	const cpuPercent = metrics.system?.cpuPercent || 0;
	const memoryPercent = metrics.system?.memory?.percent || 0;
	const activeJobs = metrics.jobs?.active || 0;
	pushMetricHistory("cpu", cpuPercent);
	pushMetricHistory("memory", memoryPercent);
	pushMetricHistory("jobs", activeJobs);

	if (els.cpuMetricSummary) {
		els.cpuMetricSummary.textContent = formatPercent(cpuPercent);
	}
	if (els.cpuMetricDetail) {
		const load = metrics.system?.loadAverage || [];
		els.cpuMetricDetail.textContent = `${metrics.system?.cpuCount || 1} cores · load ${Number(load[0] || 0).toFixed(2)}`;
	}
	if (els.memoryMetricSummary) {
		els.memoryMetricSummary.textContent = formatPercent(memoryPercent);
	}
	if (els.memoryMetricDetail) {
		const memory = metrics.system?.memory || {};
		els.memoryMetricDetail.textContent = memory.availableBytes
			? `${formatBytes(memory.usedBytes || 0)} used · ${formatBytes(memory.availableBytes)} available`
			: `${formatBytes(memory.usedBytes || 0)} of ${formatBytes(memory.totalBytes || 0)} used`;
	}
	if (els.jobMetricSummary) {
		els.jobMetricSummary.textContent = `${activeJobs} running`;
	}
	if (els.jobMetricDetail) {
		els.jobMetricDetail.textContent = activeJobs
			? `${activeJobs} Hub command${activeJobs === 1 ? "" : "s"} currently active.`
			: "Hub command queue is idle.";
	}
	if (els.cpuChartValue) {
		els.cpuChartValue.textContent = formatPercent(cpuPercent);
	}
	if (els.memoryChartValue) {
		els.memoryChartValue.textContent = formatPercent(memoryPercent);
	}
	if (els.jobsChartValue) {
		els.jobsChartValue.textContent = String(activeJobs);
	}
	renderSparkline(els.cpuChart, state.metricHistory.cpu, 100);
	renderSparkline(els.memoryChart, state.metricHistory.memory, 100);
	renderSparkline(els.jobsChart, state.metricHistory.jobs, Math.max(4, ...state.metricHistory.jobs));
}

function selectSite(slug, openSiteView = false) {
	const previousSlug = state.currentSlug;
	state.currentSlug = slug;
	if (previousSlug !== slug) {
		state.pullWizardStage = "idle";
	}
	const site = currentSite();
	els.emptyState.hidden = true;
	els.siteDetail.hidden = !site;
	if (site) {
		fillForm(site);
	} else {
		renderPullSummary(null);
		renderSiteOperationState(null);
	}
	if (site) {
		refreshQaArtifacts(site.slug).catch(() => renderQaScreenshots([]));
	} else {
		renderQaScreenshots([]);
	}
	renderSites();
	if (site && (openSiteView || previousSlug !== slug)) {
		setActiveTab("sites-settings", "sites");
	} else {
		setActiveTab(state.activeTab, state.activeSection);
	}
}

async function refresh() {
	const [health, runtime, sitesResponse, metricsResponse, sshAliasResponse, providerAccountsResponse] = await Promise.all([
		api("/api/health"),
		api("/api/runtime"),
		api("/api/sites"),
		api("/api/metrics").catch(() => null),
		api("/api/ssh/aliases").catch((error) => ({
			aliasCount: 0,
			aliases: [],
			error: error.message,
			agent: { summary: "SSH aliases unavailable" },
			files: [],
		})),
		api("/api/provider-accounts").catch((error) => ({
			accounts: {
				wpengine: { envConfigured: false, credentialSource: "unavailable" },
				siteground: { mode: "local-registry", count: 0 },
			},
			sites: { siteground: [] },
			error: error.message,
		})),
	]);
	state.health = health;
	state.runtime = runtime;
	state.sites = sitesResponse.sites;
	state.metrics = metricsResponse;
	state.sshAliasReport = sshAliasResponse;
	state.sshAliases = sshAliasResponse.aliases || [];
	state.providerAccounts = providerAccountsResponse;
	state.discoveredSites.siteground = providerAccountsResponse.sites?.siteground || [];
	if (state.currentSlug && !currentSite()) {
		state.currentSlug = null;
	}
	renderHealth();
	renderRuntime();
	renderMetrics();
	renderSites();
	renderSshAliases();
	renderProviderDiscovery();
	renderChecklist();
	renderAddSiteWizard();
	selectSite(state.currentSlug);
}

async function refreshMetrics() {
	const metrics = await api("/api/metrics");
	state.metrics = metrics;
	renderMetrics();
	renderSites();
}

async function refreshSshAliases() {
	setBusy(true);
	try {
		const response = await api("/api/ssh/aliases");
		state.sshAliasReport = response;
		state.sshAliases = response.aliases || [];
		renderSshAliases();
		renderChecklist();
		appendMessage(`Loaded ${response.aliasCount || 0} SSH aliases`);
	} catch (error) {
		appendMessage(error.message, true);
	} finally {
		setBusy(false);
	}
}

async function refreshProviderAccounts(showMessage = true) {
	setBusy(true);
	try {
		const response = await api("/api/provider-accounts");
		state.providerAccounts = response;
		state.discoveredSites.siteground = response.sites?.siteground || [];
		renderProviderDiscovery();
		renderChecklist();
		if (showMessage) {
			appendMessage("Provider discovery refreshed");
		}
	} catch (error) {
		appendMessage(error.message, true);
	} finally {
		setBusy(false);
	}
}

async function listWpEngineSites() {
	setBusy(true);
	try {
		const payload = {
			action: "wpengine-list",
			...providerFormData(els.wpEngineDiscoveryForm),
		};
		const response = await api("/api/provider-accounts/actions", {
			method: "POST",
			body: JSON.stringify(payload),
		});
		state.discoveredSites.wpengine = response.result.sites || [];
		els.wpEngineDiscoveryForm.elements.password.value = "";
		appendOutput("wpengine-list", response.result, response.result.code !== 0);
		renderProviderDiscovery();
		renderChecklist();
	} catch (error) {
		appendMessage(error.message, true);
	} finally {
		setBusy(false);
	}
}

async function saveSiteGroundSite() {
	setBusy(true);
	try {
		const response = await api("/api/provider-accounts/actions", {
			method: "POST",
			body: JSON.stringify({
				action: "siteground-add",
				site: providerFormData(els.siteGroundRegistryForm),
			}),
		});
		state.discoveredSites.siteground = response.result.sites || [];
		els.siteGroundRegistryForm.reset();
		appendOutput("siteground-add", response.result, response.result.code !== 0);
		renderProviderDiscovery();
		renderChecklist();
	} catch (error) {
		appendMessage(error.message, true);
	} finally {
		setBusy(false);
	}
}

async function removeProviderSite(site) {
	setBusy(true);
	try {
		const response = await api("/api/provider-accounts/actions", {
			method: "POST",
			body: JSON.stringify({
				action: "siteground-remove",
				id: site.id || site.slug,
			}),
		});
		state.discoveredSites.siteground = response.result.sites || [];
		appendOutput("siteground-remove", response.result, response.result.code !== 0);
		renderProviderDiscovery();
		renderChecklist();
	} catch (error) {
		appendMessage(error.message, true);
	} finally {
		setBusy(false);
	}
}

async function createSite() {
	if (!els.newSlug) return;
	const slug = els.newSlug.value.trim();
	if (!slug) {
		els.newSlug.focus();
		return;
	}

	setBusy(true);
	try {
		const response = await api("/api/sites", {
			method: "POST",
			body: JSON.stringify({ slug }),
		});
		state.currentSlug = response.site.slug;
		els.newSlug.value = "";
		await refresh();
		setActiveTab("sites-settings", "sites");
		appendMessage(`Created ${response.site.slug}`);
	} catch (error) {
		appendMessage(error.message, true);
	} finally {
		setBusy(false);
	}
}

async function runSiteActionStep(site, action, payload = {}) {
	appendPending(actionLabel(action), actionPendingMessage(action, site, payload));
	const response = await api(`/api/sites/${encodeURIComponent(site.slug)}/actions`, {
		method: "POST",
		body: JSON.stringify({ action, ...payload }),
	});
	appendOutput(action, response.result, response.result.code !== 0);
	applySshFields(response.result.sshFields);
	if (response.result.site?.slug) {
		state.currentSlug = response.result.site.slug;
	}
	if (action.startsWith("pull-") || action === "provision-site" || action === "smoke-check") {
		updatePullReadiness(response.result.site || site, action, response.result);
	}
	return response.result;
}

async function createAndImportSite() {
	if (state.addSiteMode !== "ssh") {
		return;
	}
	const options = initialImportOptions({ requireCustomPath: true });
	if (!options) {
		return;
	}
	if (options.pullDb && !options.pullFiles) {
		showToast("Pull files before pulling the database on a first import.", "error");
		return;
	}
	if (options.pullDb && !["full", "full-no-uploads", "core"].includes(options.fileScope)) {
		showToast("Database import needs WordPress core. Choose Full site, Full site without uploads, or Core/root.", "error");
		return;
	}

	const createResult = await runSshAction("ssh-create-site", {
		label: "Create Site Manifest",
		navigateOnSite: false,
	});
	if (!createResult || createResult.code !== 0 || !createResult.site?.slug) {
		showToast("Site manifest was not created. Check the log for details.", "error");
		return;
	}

	state.currentSlug = createResult.site.slug;
	await refresh();
	let site = currentSite() || createResult.site;
	const filePayload = {
		fileScope: options.fileScope,
		relativePath: options.relativePath,
		pullScopeLabel: options.pullScopeLabel,
	};
	const steps = [
		{ action: "provision-site", payload: {} },
	];
	if (options.pullFiles) {
		steps.push({ action: "pull-files", payload: filePayload });
	}
	if (options.pullDb) {
		steps.push({ action: "pull-db", payload: {} });
	}
	if (options.smokeCheck) {
		steps.push({ action: "smoke-check", payload: {} });
	}

	setBusy(true, "Initial Import");
	try {
		for (const step of steps) {
			const result = await runSiteActionStep(site, step.action, step.payload);
			if (result.site?.slug) {
				site = result.site;
			}
			if (result.code !== 0) {
				throw new Error(`${actionLabel(step.action)} failed. Review the deployment log.`);
			}
		}
		await refresh();
		selectSite(site.slug);
		setActiveTab("sites-settings", "sites");
		showToast("Initial import finished.", "success");
	} catch (error) {
		await refresh().catch(() => {});
		selectSite(site.slug);
		showToast(error.message, "error");
		appendMessage(error.message, true);
	} finally {
		setBusy(false);
	}
}

async function pullFilesAndDatabase() {
	const site = currentSite();
	if (!site) return;
	const selection = currentPullFileSelection({ requireCustomPath: true });
	if (!selection) {
		return;
	}
	const confirmation = await requestConfirmation({
		title: "Pull Files & Database",
		message: `This will sync ${selection.pullScopeLabel} from the remote site, then import the remote database into ${site.dbName}.`,
		token: "IMPORT",
	});
	if (confirmation !== "IMPORT") {
		appendMessage("Pull Files & DB cancelled");
		return;
	}

	const label = actionLabel("pull-files-db");
	let activeSite = site;
	setBusy(true, label);
	setPullWizardStage("files");
	appendPending(label, actionPendingMessage("pull-files-db", site, selection));
	try {
		const filesResult = await runSiteActionStep(activeSite, "pull-files", selection);
		if (filesResult.site?.slug) {
			activeSite = filesResult.site;
		}
		if (filesResult.code !== 0) {
			throw new Error("File pull failed. Database pull was skipped.");
		}
		setPullWizardStage("database");
		const dbResult = await runSiteActionStep(activeSite, "pull-db");
		if (dbResult.site?.slug) {
			activeSite = dbResult.site;
		}
		if (dbResult.code !== 0) {
			throw new Error("Database pull failed. Review the deployment log.");
		}
		setPullWizardStage("complete");
		await refresh();
		selectSite(activeSite.slug);
		const smokeFailed = Boolean(dbResult.smoke && dbResult.smoke.failed);
		const smokeWarned = Boolean(dbResult.smoke && dbResult.smoke.warnings);
		showToast(
			smokeFailed || smokeWarned ? "Files and database pulled. Smoke check needs review." : "Files and database pulled.",
			smokeFailed || smokeWarned ? "info" : "success",
		);
	} catch (error) {
		appendMessage(error.message, true);
		showToast(error.message, "error");
		await refresh().catch(() => {});
		selectSite(activeSite.slug);
	} finally {
		setBusy(false);
	}
}

async function saveSite() {
	const site = currentSite();
	if (!site) return;
	setBusy(true);
	try {
		const response = await api(`/api/sites/${encodeURIComponent(site.slug)}`, {
			method: "PUT",
			body: JSON.stringify(formData()),
		});
		const index = state.sites.findIndex((item) => item.slug === site.slug);
		state.sites[index] = response.site;
		selectSite(response.site.slug);
		appendMessage(`Saved ${response.site.slug}`);
	} catch (error) {
		appendMessage(error.message, true);
	} finally {
		setBusy(false);
	}
}

async function deleteSite(siteOverride = null) {
	const site = siteOverride || currentSite();
	if (!site) return;
	const confirmation = await requestConfirmation({
		title: "Delete Local Site",
		message: `This permanently deletes ${site.slug} from MRN Local: local files, local database/user, and local OpenLiteSpeed mapping. The remote host is not touched.`,
		token: site.slug,
	});
	if (confirmation !== site.slug) {
		appendMessage("Delete site cancelled");
		return;
	}

	const label = actionLabel("delete-site");
	setBusy(true, label);
	appendPending(label, `Deleting local site ${site.slug}. Remote hosting is not touched.`);
	try {
		const response = await api(`/api/sites/${encodeURIComponent(site.slug)}`, {
			method: "DELETE",
			body: JSON.stringify({ confirm: site.slug }),
		});
		appendOutput("delete-site", response.result, response.result.code !== 0);
		if (response.result.code !== 0) {
			showToast(`${site.slug} was not deleted. Review the log.`, "error");
			return;
		}
		state.selectedSiteSlugs.delete(site.slug);
		delete state.pullReadiness[site.slug];
		delete state.adminReadiness[site.slug];
		if (state.currentSlug === site.slug) {
			state.currentSlug = null;
		}
		await refresh();
		setActiveTab("sites-list", "sites");
		showToast(`Deleted ${site.slug}`, "success");
	} catch (error) {
		appendMessage(error.message, true);
		showToast(error.message, "error");
	} finally {
		setBusy(false);
	}
}

async function runAction(action, siteOverride = null) {
	const site = siteOverride || currentSite();
	if (!site) return;

	const payload = { action };
	if (action === "pull-preflight" || action === "pull-files" || action === "pull-files-dry-run") {
		const selection = currentPullFileSelection({ requireCustomPath: true });
		if (!selection) {
			return;
		}
		payload.fileScope = selection.fileScope;
		payload.relativePath = selection.relativePath;
		payload.pullScopeLabel = selection.pullScopeLabel;
	}
	if (action === "pull-files") {
		const confirmation = await requestConfirmation({
			title: "Pull Files",
			message: `This will sync ${payload.pullScopeLabel} from the remote site into ${site.publicPath}.`,
			token: "PULL",
		});
		if (confirmation !== "PULL") {
			appendMessage("File pull cancelled");
			return;
		}
	}
	if (action === "pull-db") {
		const confirmation = await requestConfirmation({
			title: "Pull Database",
			message: `This will import the remote database into ${site.dbName}.`,
			token: "DB",
		});
		if (confirmation !== "DB") {
			appendMessage("Database pull cancelled");
			return;
		}
	}
	if (action === "admin-unlock") {
		const confirmation = await requestConfirmation({
			title: "Local Admin Unlock",
			message: "This will deactivate high-confidence security/login plugins in the local WordPress database only.",
			token: "ADMIN",
		});
		if (confirmation !== "ADMIN") {
			appendMessage("Local admin unlock cancelled");
			return;
		}
	}
	if (action.startsWith("push-")) {
		payload.relativePath = els.pushPath.value;
		payload.deleteFiles = els.deleteFiles.checked;
		if (action === "push-path") {
			const confirmation = await requestConfirmation({
				title: "Push Path",
				message: `This will deploy ${payload.relativePath} back to ${site.remoteSsh}.`,
				token: "PUSH",
			});
			if (confirmation !== "PUSH") {
				appendMessage("Push cancelled");
				return;
			}
			payload.confirm = confirmation;
		}
	}

	const label = actionLabel(action);
	const isQaRun = action === "run-qa";
	if (isQaRun) {
		startQaArtifactPolling(site.slug);
	}
	setBusy(true, label);
	appendPending(label, actionPendingMessage(action, site, payload), { qa: isQaRun });
	try {
		const response = await api(`/api/sites/${encodeURIComponent(site.slug)}/actions`, {
			method: "POST",
			body: JSON.stringify(payload),
		});
		appendOutput(action, response.result, response.result.code !== 0, true, { qa: isQaRun });
		applySshFields(response.result.sshFields);
		if (response.result.site?.slug) {
			state.currentSlug = response.result.site.slug;
			await refresh();
		}
		if (action.startsWith("pull-") || action === "provision-site" || action === "start-site" || action === "stop-site" || action === "smoke-check") {
			updatePullReadiness(site, action, response.result);
		}
		if (action === "admin-check" || action === "admin-unlock") {
			updateAdminReadiness(site, action, response.result);
		}
	} catch (error) {
		if (isQaRun) {
			appendOutput(action, { code: 1, stderr: error.message }, true, false, { qa: true });
			showToast(error.message, "error");
		} else {
			appendMessage(error.message, true);
		}
	} finally {
		if (isQaRun) {
			stopQaArtifactPolling();
			refreshQaArtifacts(site.slug).catch(() => {});
		}
		setBusy(false);
	}
}

async function runRuntimeAction(action) {
	const label = action.replace(/^runtime-/, "Runtime ");
	setBusy(true, label);
	appendPending(label, "Running runtime command. The status will update when the command returns.");
	try {
		const response = await api("/api/runtime/actions", {
			method: "POST",
			body: JSON.stringify({ action }),
		});
		appendOutput(action, response.result, response.result.code !== 0);
		state.runtime = await api("/api/runtime");
		renderRuntime();
		renderChecklist();
	} catch (error) {
		appendMessage(error.message, true);
	} finally {
		setBusy(false);
	}
}

async function runSshAction(action, options = {}) {
	const payload = { action, ...sshFormData() };
	const label = options.label || (action === "mrndev-resolve" ? "Resolve MRN Dev" : action.replace(/^ssh-/, "SSH "));
	setBusy(true, label);
	appendPending(label, "Running SSH command. Approve your SSH agent if prompted.");
	try {
		const response = await api("/api/ssh/actions", {
			method: "POST",
			body: JSON.stringify(payload),
		});
		appendOutput(action, response.result, response.result.code !== 0);
		applySshFields(response.result.sshFields);
		applyInspectionFields(response.result);
		if (response.result.site?.slug) {
			state.currentSlug = response.result.site.slug;
			await refresh();
			if (options.navigateOnSite !== false) {
				setActiveTab("sites-settings", "sites");
			}
		}
		return response.result;
	} catch (error) {
		appendMessage(error.message, true);
		return null;
	} finally {
		setBusy(false);
		renderAddSiteWizard();
	}
}

function escapeHtml(value) {
	return String(value)
		.replace(/&/g, "&amp;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;")
		.replace(/"/g, "&quot;");
}

els.refreshButton.addEventListener("click", () => {
	closeUserMenu();
	refresh().catch((error) => appendMessage(error.message, true));
});
els.createSiteButton?.addEventListener("click", createSite);
els.sshCreateImportButton?.addEventListener("click", () => {
	createAndImportSite().catch((error) => appendMessage(error.message, true));
});
els.addSiteCancelButton?.addEventListener("click", cancelAddSiteFlow);
els.addSiteBackButton?.addEventListener("click", retreatAddSiteStep);
els.addSiteNextButton?.addEventListener("click", () => {
	advanceAddSiteStep().catch((error) => appendMessage(error.message, true));
});
els.addSiteModeInputs.forEach((input) => {
	input.addEventListener("change", () => {
		setSelectedAddSiteMode(input.value);
		renderAddSiteWizard();
	});
});
els.newSlug?.addEventListener("keydown", (event) => {
	if (event.key === "Enter") {
		event.preventDefault();
		if (state.addSiteStep === "create" && state.addSiteMode === "blank" && addSiteStepComplete("create")) {
			createSite();
		} else if (state.addSiteStep === "connection") {
			advanceAddSiteStep().catch((error) => appendMessage(error.message, true));
		}
	}
});
els.newSlug?.addEventListener("input", renderAddSiteWizard);
els.saveSiteButton.addEventListener("click", saveSite);
els.openLocalButton.addEventListener("click", () => runAction("open-local"));
els.openAdminButton.addEventListener("click", () => runAction("admin-login"));
els.pullFileScope?.addEventListener("change", () => {
	state.pullWizardStage = "idle";
	renderPullScopeControls();
	renderPullSummary(currentSite());
});
els.pullCustomPath?.addEventListener("input", () => {
	state.pullWizardStage = "idle";
	renderPullSummary(currentSite());
});
els.pullFilesDbButton?.addEventListener("click", () => {
	pullFilesAndDatabase().catch((error) => appendMessage(error.message, true));
});
els.deleteSiteButton?.addEventListener("click", () => {
	deleteSite().catch((error) => appendMessage(error.message, true));
});
[
	els.addSitePullFiles,
	els.addSiteFileScope,
	els.addSiteCustomPath,
	els.addSitePullDb,
	els.addSiteSmokeCheck,
].forEach((control) => {
	control?.addEventListener("input", renderInitialImportOptions);
	control?.addEventListener("change", renderInitialImportOptions);
});
els.siteSearchInput?.addEventListener("input", () => {
	state.siteFilters.query = els.siteSearchInput.value;
	renderSites();
});
els.siteStatusFilter?.addEventListener("change", () => {
	state.siteFilters.status = els.siteStatusFilter.value;
	renderSites();
});
els.siteProviderFilter?.addEventListener("change", () => {
	state.siteFilters.provider = els.siteProviderFilter.value;
	renderSites();
});
els.selectVisibleSitesButton?.addEventListener("click", toggleVisibleSiteSelection);
els.clearSiteSelectionButton?.addEventListener("click", clearSiteSelection);
els.bulkStartSitesButton?.addEventListener("click", () => runBulkSiteAction("start-site"));
els.bulkStopSitesButton?.addEventListener("click", () => runBulkSiteAction("stop-site"));
els.sshForm?.addEventListener("input", renderAddSiteWizard);
els.sshForm?.elements.provider.addEventListener("change", () => {
	updateProviderHint();
	renderAddSiteWizard();
});
els.sshForm?.elements.slug.addEventListener("blur", () => {
	syncImportIdentifier();
	renderAddSiteWizard();
});
els.refreshSshAliasesButton?.addEventListener("click", refreshSshAliases);
els.refreshProviderAccountsButton?.addEventListener("click", () => refreshProviderAccounts());
els.wpEngineListButton?.addEventListener("click", listWpEngineSites);
els.siteGroundAddButton?.addEventListener("click", saveSiteGroundSite);
els.wpEngineDiscoveryForm.addEventListener("submit", (event) => {
	event.preventDefault();
	listWpEngineSites();
});
els.siteGroundRegistryForm.addEventListener("submit", (event) => {
	event.preventDefault();
	saveSiteGroundSite();
});
els.clearOutputButton.addEventListener("click", () => {
	els.outputConsole.textContent = "";
	els.outputConsole.className = "";
});
els.clearQaOutputButton?.addEventListener("click", () => {
	els.qaOutputConsole.textContent = "";
	els.qaOutputConsole.className = "";
});
els.clearQaAllButton?.addEventListener("click", () => {
	els.qaOutputConsole.textContent = "";
	els.qaOutputConsole.className = "";
	clearQaArtifacts().catch((error) => appendMessage(error.message, true));
});
els.refreshQaScreenshotsButton?.addEventListener("click", () => {
	refreshQaArtifacts(currentSite()?.slug).catch((error) => appendMessage(error.message, true));
});
els.themeToggle.addEventListener("click", () => {
	const current = document.documentElement.dataset.theme === "dark" ? "dark" : "light";
	applyTheme(current === "dark" ? "light" : "dark");
	closeUserMenu();
});
document.addEventListener("click", (event) => {
	if (els.userMenu?.open && !els.userMenu.contains(event.target)) {
		closeUserMenu();
	}
});
document.addEventListener("keydown", (event) => {
	if (event.key === "Escape") {
		closeUserMenu();
	}
});
document.querySelectorAll("[data-section]").forEach((button) => {
	button.addEventListener("click", () => {
		setActiveSection(button.dataset.section, sectionDefaultTab(button.dataset.section));
		closeUserMenu();
		document.querySelector(".topbar")?.scrollIntoView({ behavior: "smooth", block: "start" });
	});
});
document.querySelectorAll("[data-tab-jump]").forEach((button) => {
	button.addEventListener("click", () => {
		if (button.dataset.tabJump === "sites-add") {
			startAddSiteFlow();
		} else {
			setActiveTab(button.dataset.tabJump);
		}
	});
});
document.querySelectorAll("[data-site-view]").forEach((button) => {
	button.addEventListener("click", () => {
		setActiveTab(button.dataset.siteView, "sites");
	});
});
document.querySelectorAll("[data-chart-card]").forEach((button) => {
	button.addEventListener("click", () => {
		const isExpanded = button.classList.toggle("expanded");
		button.setAttribute("aria-pressed", isExpanded ? "true" : "false");
	});
});
document.querySelectorAll("[data-action]").forEach((button) => {
	button.addEventListener("click", () => {
		runAction(button.dataset.action);
	});
});
document.querySelectorAll("[data-runtime-action]").forEach((button) => {
	button.addEventListener("click", () => {
		runRuntimeAction(button.dataset.runtimeAction);
	});
});
document.querySelectorAll("[data-ssh-action]").forEach((button) => {
	button.addEventListener("click", () => {
		runSshAction(button.dataset.sshAction);
	});
});

applyTheme(window.localStorage.getItem("mrn-local-hub-theme") || "light");
updateProviderHint();
refresh().then(() => {
	if (!state.metricsTimer) {
		state.metricsTimer = window.setInterval(() => {
			refreshMetrics().catch(() => {});
		}, 5000);
	}
}).catch((error) => appendMessage(error.message, true));
