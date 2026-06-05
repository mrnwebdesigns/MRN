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
	credentials: [],
	backupImportSessionId: "",
	backupSession: null,
	awsBackupGroups: [],
	busy: false,
	operationLabel: "",
	operationStartedAt: 0,
	operationTimer: null,
	qaArtifactTimer: null,
	pullReadiness: {},
	siteWarnings: {},
	adminReadiness: {},
	addSiteStep: "source",
	addSiteMode: "ssh",
	siteFilters: {
		query: "",
		status: "all",
		provider: "all",
	},
	selectedSiteSlugs: new Set(),
	tooltipElement: null,
	tooltipAnchor: null,
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
	backup: {
		label: "Backup Restore",
		remoteSshPlaceholder: "",
		remotePathPlaceholder: "",
		hint: "Restore from staged UpdraftPlus backup files or AWS S3.",
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
	backupRestoreForm: document.querySelector("#backupRestoreForm"),
	updraftDropzone: document.querySelector("#updraftDropzone"),
	updraftFileInput: document.querySelector("#updraftFileInput"),
	chooseUpdraftFilesButton: document.querySelector("#chooseUpdraftFilesButton"),
	updraftStagedSummary: document.querySelector("#updraftStagedSummary"),
	updraftStagedFiles: document.querySelector("#updraftStagedFiles"),
	awsBackupForm: document.querySelector("#awsBackupForm"),
	awsBackupCredentialSelect: document.querySelector("#awsBackupCredentialSelect"),
	listAwsBackupsButton: document.querySelector("#listAwsBackupsButton"),
	awsBackupStatus: document.querySelector("#awsBackupStatus"),
	awsBackupResults: document.querySelector("#awsBackupResults"),
	awsCredentialForm: document.querySelector("#awsCredentialForm"),
	saveAwsCredentialButton: document.querySelector("#saveAwsCredentialButton"),
	awsCredentialStatus: document.querySelector("#awsCredentialStatus"),
	credentialSummary: document.querySelector("#credentialSummary"),
	credentialResults: document.querySelector("#credentialResults"),
	backupRestoreFiles: document.querySelector("#backupRestoreFiles"),
	backupIncludeUploads: document.querySelector("#backupIncludeUploads"),
	backupRestoreDb: document.querySelector("#backupRestoreDb"),
	backupRestoreSummary: document.querySelector("#backupRestoreSummary"),
	createBackupSiteButton: document.querySelector("#createBackupSiteButton"),
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
	phpRuntimeStatus: document.querySelector("#phpRuntimeStatus"),
	deleteSiteButton: document.querySelector("#deleteSiteButton"),
	openLocalButton: document.querySelector("#openLocalButton"),
	openAdminButton: document.querySelector("#openAdminButton"),
	runtimeOperationStatus: document.querySelector("#runtimeOperationStatus"),
	provisionSiteButton: document.querySelector("#provisionSiteButton"),
	siteWarningsCard: document.querySelector("#siteWarningsCard"),
	siteWarningsSummary: document.querySelector("#siteWarningsSummary"),
	siteWarningsList: document.querySelector("#siteWarningsList"),
	adminStatus: document.querySelector("#adminStatus"),
	adminResultList: document.querySelector("#adminResultList"),
	pullFilesDbButton: document.querySelector("#pullFilesDbButton"),
	pullResultList: document.querySelector("#pullResultList"),
	pullFileScope: document.querySelector("#pullFileScope"),
	pullCustomPathField: document.querySelector("#pullCustomPathField"),
	pullCustomPath: document.querySelector("#pullCustomPath"),
	pushFileScope: document.querySelector("#pushFileScope"),
	pushCustomPathField: document.querySelector("#pushCustomPathField"),
	pushSummary: document.querySelector("#pushSummary"),
	pushPath: document.querySelector("#pushPath"),
	deleteFiles: document.querySelector("#deleteFiles"),
	outputConsole: document.querySelector("#outputConsole"),
	clearOutputButton: document.querySelector("#clearOutputButton"),
	siteActivityPanel: document.querySelector(".site-activity-panel"),
	siteActivityConsole: document.querySelector("#siteActivityConsole"),
	siteActivityStatus: document.querySelector("#siteActivityStatus"),
	clearSiteActivityButton: document.querySelector("#clearSiteActivityButton"),
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
const siteDefaultTab = "sites-sync";
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

const pushScopePreviewPaths = {
	"wp-content": "wp-content",
	"parent-theme": "parent theme, resolved from local WordPress",
	themes: "wp-content/themes",
	plugins: "wp-content/plugins",
	"mu-plugins": "wp-content/mu-plugins",
	uploads: "wp-content/uploads",
};

const legacyTabMap = {
	overview: ["dashboard", "dashboard-overview"],
	site: ["sites", siteDefaultTab],
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

function writeSiteConsole(text, className) {
	writeConsole(els.outputConsole, text, className);
	writeConsole(els.siteActivityConsole, text, className);
}

function setSiteActivityStatus(text = "Idle", stateName = "idle") {
	if (!els.siteActivityStatus) return;
	els.siteActivityStatus.textContent = text;
	els.siteActivityStatus.dataset.state = stateName;
}

function revealSiteActivity() {
	if (!els.siteActivityPanel || state.activeTab !== "sites-sync") return;
	window.requestAnimationFrame(() => {
		els.siteActivityPanel.scrollIntoView({
			block: "nearest",
			behavior: "smooth",
		});
	});
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
	writeSiteConsole(text, className);
	if (!options.qa) {
		setSiteActivityStatus(isError ? `${actionLabel(title)} failed` : `${actionLabel(title)} finished`, isError ? "error" : "ok");
	}
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
	writeSiteConsole(text, "output-running");
	if (!options.qa) {
		setSiteActivityStatus(`${title} running`, "running");
	}
	if (options.revealActivity) {
		revealSiteActivity();
	}
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
		"apply-php-version": "Apply PHP Version",
		"normalize-local-url": "Normalize Local URL",
		"push-audit": "Push Audit",
		"push-path-dry-run": "Push Dry Run",
		"push-path": "Push Path",
		"run-qa": "Run MRN QA",
		"credential-save": "Save Credential",
		"credential-test": "Test Credential",
		"credential-delete": "Delete Credential",
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
	if (action === "apply-php-version") {
		return `Applying PHP ${payload.phpVersion || site.phpVersion || "8.4"} to the local OpenLiteSpeed vhost for ${site.slug}, then probing the active web PHP version.`;
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
		return `Auditing ${payload.pushScopeLabel || payload.relativePath || "selected path"} for ${site.remoteSsh}:${site.remotePath}.`;
	}
	return `Running ${actionLabel(action)} for ${site.slug}.`;
}

const buttonIdTooltips = {
	refreshButton: "Reloads site manifests, runtime health, local tool checks, provider account status, and dashboard metrics. Useful after editing files outside the Hub or after a runtime command finishes.",
	themeToggle: "Switches only the Hub UI theme. It does not change any local site, WordPress theme, or browser setting.",
	selectVisibleSitesButton: "Selects every site currently visible after search and filters. Hidden filtered-out sites are not selected.",
	clearSiteSelectionButton: "Clears the bulk selection so Start Selected and Stop Selected no longer affect any sites.",
	bulkStartSitesButton: "Runs Start/Provision for every selected site. This can create missing local DB/vhost resources, but it does not contact remote hosting.",
	bulkStopSitesButton: "Marks selected sites stopped in the Hub. The shared Lima/OpenLiteSpeed runtime stays online, and remote hosting is not touched.",
	wpEngineListButton: "Calls the configured WP Engine account credentials to list installs/environments, then lets you hand one off to the SSH import flow.",
	siteGroundAddButton: "Stores this SiteGround SSH site entry locally so it can be selected later. It does not validate SSH until the Add Site connection step.",
	saveAwsCredentialButton: "Stores the AWS access key, secret key, and optional session token in macOS Keychain. The Hub only keeps non-secret metadata like label and region.",
	refreshProviderAccountsButton: "Reloads saved provider registries and environment-backed account credentials without changing any local site.",
	addSiteCancelButton: "Exits the Add Site wizard and returns to All Sites. Anything already created by a completed step remains in place.",
	addSiteBackButton: "Moves one wizard step back so you can revise the source or connection details before creating/importing.",
	addSiteNextButton: "Validates the current wizard step. On the connection step it also prepares SSH by inspecting the remote WordPress root before continuing.",
		createSiteButton: "Creates a blank local WordPress-style workspace only. Use this for new local work, not for cloning an existing remote site.",
		sshCreateSiteButton: "Creates the local manifest from SSH details only. You will still need to provision, pull files, and pull the database afterward.",
		sshCreateImportButton: "Runs the full selected first-import flow: create manifest, provision runtime, pull selected files, optionally pull DB, then optionally smoke check.",
		createBackupSiteButton: "Creates a new local site from staged Updraft backup parts. It provisions the runtime, restores files/database based on the selected options, and never writes to remote hosting.",
		chooseUpdraftFilesButton: "Opens a file picker for local UpdraftPlus backup parts. Files are staged in the Hub runtime temp area, not inside a public WordPress folder.",
		listAwsBackupsButton: "Lists UpdraftPlus backup sets in the selected S3 bucket. If a stored key is selected, the Hub reads it from Keychain for this AWS CLI call; otherwise it uses the profile/environment.",
		refreshSshAliasesButton: "Re-reads ~/.ssh/config and included SSH config files so new aliases appear in the chooser.",
	openLocalButton: "Opens the selected local site URL. If friendly HTTPS is not active, the button stays blocked with the reason shown here.",
	openAdminButton: "Creates a one-time local admin login URL and opens wp-admin. This changes only the local WordPress database.",
	saveSiteButton: "Saves editable settings on this page, such as title, URLs, SSH details, notes, and target PHP version.",
	deleteSiteButton: "Deletes this local site folder, local DB/user, and OpenLiteSpeed mapping. Remote hosting is never deleted.",
	pullFilesDbButton: "Runs the selected file pull first, then imports the remote database into local MariaDB. Local files in scope and the local DB are changed; remote is read-only.",
	provisionSiteButton: "Creates or repairs the local database, DB user, wp-config.php settings, and OpenLiteSpeed vhost for this site.",
	refreshQaScreenshotsButton: "Reloads screenshot artifacts from the latest MRN QA run so new captures appear without rerunning QA.",
	clearQaOutputButton: "Clears the visible QA text log only. Saved QA screenshots are kept.",
	clearQaAllButton: "Clears the visible QA log and removes saved QA screenshots for this site.",
	clearOutputButton: "Clears the deployment log panel in the UI. It does not delete site backups, dumps, or runtime logs on disk.",
	installHttpsHelperButton: "Installs or reinstalls the launchd helper that lets local HTTPS use ports 80 and 443 without running the Hub as root.",
	friendlyStartButton: "Checks whether no-port HTTPS URLs like https://site.localhost are live through the helper/proxy.",
	refreshSslCertButton: "Regenerates or verifies the mkcert certificate used by all *.localhost friendly HTTPS sites.",
	trustFirefoxButton: "Imports the mkcert local CA into Firefox profiles when possible. Firefox may need a full quit/reopen afterward.",
	confirmCancelButton: "Cancels the pending confirmed action. No files, database, or remote target will be changed.",
	confirmSubmitButton: "Runs the pending action only if the required confirmation token matches exactly.",
};

const actionTooltips = {
	"provision-site": "Builds or repairs local-only runtime pieces: DB name/user/password, wp-config.php constants, .htaccess, and OpenLiteSpeed vhost. Does not pull remote files or DB.",
	"apply-php-version": "Rewires this site's local OpenLiteSpeed handler to the selected PHP version and probes the web response to confirm the active PHP.",
	"normalize-local-url": "Rewrites the local WordPress home/siteurl and wp-config.php URL settings to match this Hub local URL. Remote DB and files are not touched.",
	"pull-preflight": "Checks SSH reachability, remote WordPress root, required tools, Git safety, and the exact rsync command before files are changed.",
	"pull-files-dry-run": "Runs rsync in preview mode for the selected pull scope. It shows what would change locally, but writes nothing.",
	"pull-files": "Copies the selected remote file scope into the local public path. Stops when local Git changes would be overwritten.",
	"pull-db": "Exports the remote DB through WP-CLI, imports it into the local DB, runs URL search-replace, then reports smoke/code-sync warnings.",
	"smoke-check": "Runs quick local checks for the homepage, REST API, wp-admin response, active theme CSS, and an internal page.",
	"push-audit": "Scans the selected push scope before any deploy: Git cleanliness, blocked private files, symlinks, generated folders, and delete safety.",
	"push-path-dry-run": "Runs Push Audit, then rsync dry-run to show what remote files would change. No remote writes happen.",
	"push-path": "Deploys the selected scope to remote over rsync after audit and confirmation. Can change remote files; use dry-run first.",
	"admin-check": "Checks local wp-admin/wp-login responses and identifies security/login plugins that may be blocking local admin access.",
	"admin-login": "Creates a one-time local wp-admin login URL for this site. It does not create remote users or touch remote data.",
	"admin-unlock": "Locally deactivates high-confidence security/login blockers so wp-admin works. These plugin changes are not pushed back.",
	"run-qa": "Runs MRN QA against this local site, including static/runtime gates where available, and refreshes screenshot artifacts during the run.",
};

const runtimeActionTooltips = {
	"runtime-status": "Rechecks Lima state, OpenLiteSpeed, MariaDB, forwarded ports, friendly HTTPS helper, certificate, and Firefox trust status.",
	"runtime-check": "Runs service checks inside the local runtime so you can see whether OLS, MariaDB, Redis, mounts, and PHP handlers are healthy.",
	"runtime-open-http": "Opens the raw HTTP runtime endpoint on the forwarded port. Useful when friendly HTTPS is not working yet.",
	"runtime-open-admin": "Opens OpenLiteSpeed WebAdmin for the local VM. This is runtime administration, not WordPress wp-admin.",
	"runtime-friendly-install-helper": "Installs the macOS launchd helper that binds ports 80/443 and proxies friendly *.localhost URLs to the Hub runtime.",
	"runtime-friendly-start": "Verifies that the helper/proxy is live and no-port HTTPS URLs resolve. It cannot work until the helper is installed.",
	"runtime-friendly-cert": "Creates or refreshes the mkcert wildcard certificate for *.localhost used by friendly HTTPS.",
	"runtime-firefox-trust": "Attempts to trust the mkcert CA in Firefox profiles. Close Firefox fully first if trust keeps reporting blocked.",
	"runtime-install-nss": "Installs the NSS tooling Firefox needs for local certificate trust management.",
	"runtime-plan": "Regenerates the Lima config and bootstrap script without starting the VM. Good for reviewing what will change.",
	"runtime-bootstrap": "Creates or updates the Lima VM and installs OpenLiteSpeed, PHP handlers, MariaDB, Redis, and mounted site paths.",
	"runtime-repair": "Re-runs package/service repair inside the VM. Use when tools are missing, PHP handlers changed, or services are unhealthy.",
	"runtime-open-script": "Opens the generated bootstrap script on disk so you can inspect the runtime setup commands.",
};

const sshActionTooltips = {
	"mrndev-resolve": "For *.mrndev.io sites, derives the site-owner SSH target and likely /home/.../htdocs WordPress root from the URL or slug.",
	"ssh-config": "Shows which SSH alias, host, port, identity files, and proxy settings the Hub sees before it connects.",
	"ssh-test": "Runs a simple SSH connection test. No files or databases are read, written, pulled, or pushed.",
	"ssh-inspect": "Connects over SSH and checks the remote path for wp-config.php, WP-CLI, DB constants, WordPress version, and URLs.",
	"ssh-create-site": "Creates the local Hub manifest from the current SSH fields. It does not provision runtime or import content.",
};

const siteActionTooltips = {
	"open-local": "Open this site's local URL. If HTTPS helper/proxy is not ready, the button explains what is blocking it.",
	"admin-login": "Generate a local one-time admin login and open wp-admin. Remote users and remote DB are untouched.",
	"start-site": "Start/provision this site locally so it appears in running-site metrics and OpenLiteSpeed routing.",
	"stop-site": "Hide/mark this site stopped in the Hub. The shared runtime and remote hosting remain untouched.",
};

const sectionTooltips = {
	dashboard: "Server overview: CPU, memory, job activity, runtime health, and running-site cards.",
	sites: "Site inventory: add sites, filter/bulk manage, open a site page, then pull, push, configure, or QA it.",
	runtime: "Runtime controls: Lima/OpenLiteSpeed status, friendly HTTPS setup, maintenance, and generated config details.",
	logs: "Command log: review output from pulls, pushes, DB imports, runtime actions, and QA commands.",
};

const siteViewTooltips = {
	"sites-sync": "Pull from remote into local, push selected local scopes back to remote, and review site warnings.",
	"sites-settings": "Edit site metadata, SSH target, paths, local URL, PHP target, and delete the local site.",
	"sites-qa": "Run MRN QA and review logs/screenshots for the selected local WordPress site.",
};

const tabTooltips = {
	"dashboard-overview": "Show server metrics and running-site cards.",
	"sites-list": "Show all local sites with filters and bulk actions.",
	"sites-add": "Start the guided Add Site flow.",
	"sites-settings": "Edit selected site settings.",
	"sites-sync": "Manage Pull and Push actions for the selected site.",
	"sites-qa": "Run QA for the selected site.",
	"runtime-status": "Show local runtime status.",
	"runtime-https": "Configure friendly local HTTPS URLs.",
	"runtime-maintenance": "Run runtime setup and repair actions.",
	"runtime-details": "Show generated runtime paths and config details.",
	"logs-console": "Show deployment command output.",
};

const chartTooltips = {
	cpu: "Expands the CPU sparkline so you can see recent local machine/runtime load samples.",
	memory: "Expands the memory sparkline so you can see recent used/available memory samples.",
	jobs: "Expands the job sparkline so you can see recent Hub command activity.",
};

const buttonTextTooltips = {
	"Add Site": "Starts the guided flow for either a blank local site or an SSH import from MRN Dev, RunCloud, SiteGround, WP Engine, or another host.",
	Use: "Copies this discovered provider site's slug, live URL, SSH target, and path into the Add Site SSH form.",
	Remove: "Removes this saved provider site entry from the local provider registry. It does not delete a site.",
	Cancel: "Closes the current flow/dialog without running the pending action.",
	Confirm: "Runs the pending action after the required token has been typed exactly.",
};

function normalizedButtonText(button) {
	return (button?.textContent || "").trim().replace(/\s+/g, " ");
}

function safeCurrentPullSelection() {
	try {
		return currentPullFileSelection();
	} catch {
		return null;
	}
}

function safeCurrentPushSelection() {
	try {
		return currentPushFileSelection();
	} catch {
		return null;
	}
}

function selectedSiteLabel(site = currentSite()) {
	return site?.title || site?.slug || "the selected site";
}

function detailedTooltipForButton(button) {
	const site = currentSite();
	const siteName = selectedSiteLabel(site);
	if (button.id === "pullFilesDbButton") {
		const selection = safeCurrentPullSelection();
		const scope = selection?.pullScopeLabel || "the selected file scope";
		return `Imports a working local copy in one run: pulls ${scope}, then overwrites the local DB with the remote DB and search-replaces URLs for ${siteName}. Remote files and DB are read-only.`;
	}
	if (button.dataset.action === "pull-preflight") {
		const selection = safeCurrentPullSelection();
		return `Preflight for ${selection?.pullScopeLabel || "the selected pull scope"}: checks SSH, remote WordPress path, local tools, Git safety, and the exact rsync command before anything is changed.`;
	}
	if (button.dataset.action === "pull-files-dry-run") {
		const selection = safeCurrentPullSelection();
		return `Preview pull for ${selection?.pullScopeLabel || "the selected scope"}. Shows what would change under the local public path, but writes no files.`;
	}
	if (button.dataset.action === "pull-files") {
		const selection = safeCurrentPullSelection();
		return `Pulls ${selection?.pullScopeLabel || "the selected scope"} from remote into local. This can overwrite local files in that scope, and it stops if Git says the target is dirty.`;
	}
	if (button.dataset.action === "pull-db") {
		return `Overwrites the local database for ${siteName} with a fresh remote export, then runs local URL search-replace and reports smoke/code-sync warnings. Files are not changed.`;
	}
	if (button.dataset.action === "push-audit") {
		const selection = safeCurrentPushSelection();
		return `Safety scan for pushing ${selection?.pushScopeLabel || "the selected scope"}: checks Git cleanliness, local-only/private files, symlinks, generated folders, delete safety, and blocked broad scopes. No remote writes.`;
	}
	if (button.dataset.action === "push-path-dry-run") {
		const selection = safeCurrentPushSelection();
		return `Runs Push Audit, then rsync dry-run for ${selection?.pushScopeLabel || "the selected scope"}. Shows what remote files would change without writing to remote.`;
	}
	if (button.dataset.action === "push-path") {
		const selection = safeCurrentPushSelection();
		return `Deploys ${selection?.pushScopeLabel || "the selected scope"} to remote after audit and PUSH confirmation. This writes remote files; use Push Audit and Dry Run Push first.`;
	}
	if (button.id === "openLocalButton" || button.dataset.siteAction === "open-local") {
		return siteOpenBlockedReason(site) || `Opens ${siteName} at its local URL. Uses friendly HTTPS when the helper/proxy is active.`;
	}
	if (button.id === "openAdminButton" || button.dataset.siteAction === "admin-login") {
		return siteOpenBlockedReason(site) || `Creates a one-time local wp-admin login for ${siteName}. It does not change remote users, remote files, or remote DB.`;
	}
	if (button.dataset.siteAction === "start-site") {
		return `Starts or provisions this site locally so its vhost, DB settings, and dashboard metrics are active. Remote hosting is not touched.`;
	}
	if (button.dataset.siteAction === "stop-site") {
		return `Stops this site from the Hub's local running list. The shared Lima/OpenLiteSpeed runtime and remote hosting keep running.`;
	}
	if (button.id === "addSiteNextButton" && state.addSiteStep === "connection") {
		return "Prepares the SSH connection now: resolves MRN Dev when needed, tests the remote WordPress root, and blocks the next step if wp-config.php is not found.";
	}
	if (button.id === "addSiteNextButton" && state.addSiteStep === "create") {
		return "Moves through the final import choices. Use Create & Import on the next panel to actually create/provision/pull.";
	}
	if (button.dataset.chartCard) {
		const metric = button.dataset.chartCard.toUpperCase();
		return `Expands the ${metric} chart to make the recent sample history easier to inspect. This is display-only.`;
	}
	if (button.classList.contains("site-card")) {
		const title = button.querySelector("strong")?.textContent?.trim() || "this site";
		return `Opens ${title}'s site page with Pull & Push first, plus settings, QA, warnings, and local actions.`;
	}
	if (button.classList.contains("site-row-main")) {
		const title = button.querySelector("strong")?.textContent?.trim() || "this site";
		return `Opens ${title}'s management view. Use the row buttons for quick open/admin/start/stop without leaving the list.`;
	}
	if (button.classList.contains("ssh-alias-button")) {
		const alias = button.querySelector("strong")?.textContent?.trim() || "this alias";
		return `Copies ${alias} into the SSH target field and infers the likely provider. It does not connect until you test or prepare the connection.`;
	}
	return "";
}

function tooltipTextForButton(button) {
	if (!button) return "";
	const nativeTitle = button.getAttribute("title") || button.dataset.nativeTitle || "";
	if (nativeTitle && button.disabled) return nativeTitle;
	const detailed = detailedTooltipForButton(button);
	if (detailed) return detailed;
	if (button.id && buttonIdTooltips[button.id]) return buttonIdTooltips[button.id];
	if (button.dataset.action && actionTooltips[button.dataset.action]) return actionTooltips[button.dataset.action];
	if (button.dataset.runtimeAction && runtimeActionTooltips[button.dataset.runtimeAction]) return runtimeActionTooltips[button.dataset.runtimeAction];
	if (button.dataset.sshAction && sshActionTooltips[button.dataset.sshAction]) return sshActionTooltips[button.dataset.sshAction];
	if (button.dataset.siteAction && siteActionTooltips[button.dataset.siteAction]) return siteActionTooltips[button.dataset.siteAction];
	if (button.dataset.section && sectionTooltips[button.dataset.section]) return sectionTooltips[button.dataset.section];
	if (button.dataset.siteView && siteViewTooltips[button.dataset.siteView]) return siteViewTooltips[button.dataset.siteView];
	if (button.dataset.tabJump && tabTooltips[button.dataset.tabJump]) return tabTooltips[button.dataset.tabJump];
	if (button.dataset.tab && tabTooltips[button.dataset.tab]) return tabTooltips[button.dataset.tab];
	if (button.dataset.chartCard && chartTooltips[button.dataset.chartCard]) return chartTooltips[button.dataset.chartCard];
	const aria = button.getAttribute("aria-label");
	if (aria) return aria;
	return nativeTitle || buttonTextTooltips[normalizedButtonText(button)] || button.dataset.tooltip || "";
}

function primeButtonTooltip(button) {
	const text = tooltipTextForButton(button);
	if (text) {
		button.dataset.tooltip = text;
	} else {
		delete button.dataset.tooltip;
	}
}

function primeButtonTooltips(root = document) {
	root.querySelectorAll?.("button").forEach(primeButtonTooltip);
	if (root.matches?.("button")) {
		primeButtonTooltip(root);
	}
}

function ensureTooltipElement() {
	if (state.tooltipElement) return state.tooltipElement;
	const tooltip = document.createElement("div");
	tooltip.className = "hub-tooltip";
	tooltip.setAttribute("role", "tooltip");
	tooltip.hidden = true;
	document.body.append(tooltip);
	state.tooltipElement = tooltip;
	return tooltip;
}

function positionTooltip(anchor) {
	const tooltip = state.tooltipElement;
	if (!tooltip || !anchor || tooltip.hidden) return;
	const anchorRect = anchor.getBoundingClientRect();
	if (!anchorRect.width && !anchorRect.height) {
		hideButtonTooltip();
		return;
	}
	const tooltipRect = tooltip.getBoundingClientRect();
	const margin = 10;
	let placement = "top";
	let top = anchorRect.top - tooltipRect.height - margin;
	if (top < 8) {
		placement = "bottom";
		top = anchorRect.bottom + margin;
	}
	let left = anchorRect.left + (anchorRect.width / 2) - (tooltipRect.width / 2);
	left = Math.max(8, Math.min(left, window.innerWidth - tooltipRect.width - 8));
	const anchorCenter = anchorRect.left + (anchorRect.width / 2);
	const arrowLeft = Math.max(14, Math.min(anchorCenter - left, tooltipRect.width - 14));
	tooltip.dataset.placement = placement;
	tooltip.style.left = `${Math.round(left)}px`;
	tooltip.style.top = `${Math.round(top)}px`;
	tooltip.style.setProperty("--tooltip-arrow-left", `${Math.round(arrowLeft)}px`);
}

function showButtonTooltip(button) {
	if (!button) return;
	const existingTitle = button.getAttribute("title");
	if (existingTitle) {
		button.dataset.nativeTitle = existingTitle;
		button.removeAttribute("title");
	}
	const text = tooltipTextForButton(button);
	if (!text) return;
	button.dataset.tooltip = text;
	const tooltip = ensureTooltipElement();
	state.tooltipAnchor = button;
	tooltip.textContent = text;
	tooltip.hidden = false;
	tooltip.classList.remove("visible");
	window.requestAnimationFrame(() => {
		positionTooltip(button);
		tooltip.classList.add("visible");
	});
}

function hideButtonTooltip() {
	const anchor = state.tooltipAnchor;
	const tooltip = state.tooltipElement;
	if (anchor?.dataset.nativeTitle) {
		anchor.setAttribute("title", anchor.dataset.nativeTitle);
		delete anchor.dataset.nativeTitle;
	}
	state.tooltipAnchor = null;
	if (!tooltip) return;
	tooltip.classList.remove("visible");
	tooltip.hidden = true;
}

function setupButtonTooltips() {
	primeButtonTooltips();
	const observer = new MutationObserver((records) => {
		for (const record of records) {
			if (record.type === "childList") {
				record.addedNodes.forEach((node) => {
					if (node.nodeType === Node.ELEMENT_NODE) {
						primeButtonTooltips(node);
					}
				});
			} else if (record.type === "attributes" && record.target?.matches?.("button")) {
				primeButtonTooltip(record.target);
			}
		}
	});
	observer.observe(document.body, {
		attributes: true,
		attributeFilter: ["data-action", "data-runtime-action", "data-section", "data-site-action", "data-site-view", "data-ssh-action", "data-tab", "data-tab-jump", "disabled", "id", "title"],
		childList: true,
		subtree: true,
	});
	document.addEventListener("mouseover", (event) => {
		const button = event.target.closest?.("button");
		const relatedTarget = event.relatedTarget instanceof Node ? event.relatedTarget : null;
		if (!button || button.contains(relatedTarget)) return;
		showButtonTooltip(button);
	});
	document.addEventListener("mouseout", (event) => {
		const button = event.target.closest?.("button");
		const relatedTarget = event.relatedTarget instanceof Node ? event.relatedTarget : null;
		if (!button || button !== state.tooltipAnchor || button.contains(relatedTarget)) return;
		hideButtonTooltip();
	});
	document.addEventListener("focusin", (event) => {
		const button = event.target.closest?.("button");
		if (button) showButtonTooltip(button);
	});
	document.addEventListener("focusout", (event) => {
		if (event.target === state.tooltipAnchor) hideButtonTooltip();
	});
	document.addEventListener("keydown", (event) => {
		if (event.key === "Escape") hideButtonTooltip();
	});
	window.addEventListener("scroll", () => hideButtonTooltip(), true);
	window.addEventListener("resize", hideButtonTooltip);
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

function gitSyncLabel(git) {
	const parts = [];
	if (Number(git?.ahead || 0) > 0) {
		parts.push(`ahead ${git.ahead}`);
	}
	if (Number(git?.behind || 0) > 0) {
		parts.push(`behind ${git.behind}`);
	}
	return parts.join(" / ");
}

function gitStatusLabel(git) {
	if (!git?.present) {
		return "";
	}
	if (git.state === "error") {
		return "Git unavailable";
	}
	const branch = git.branch || "detached";
	const sync = gitSyncLabel(git);
	const changeCount = Number(git.totalChanges || 0);
	const state = git.dirty
		? `${changeCount} change${changeCount === 1 ? "" : "s"}`
		: "clean";
	return [`Git ${branch}`, state, sync].filter(Boolean).join(" · ");
}

function gitStatusBadge(git) {
	const label = gitStatusLabel(git);
	if (!label) {
		return "";
	}
	const state = git.state === "clean" || git.state === "dirty" || git.state === "error"
		? git.state
		: "unknown";
	const title = git.summary || label;
	return `<span class="git-status git-${state}" title="${escapeHtml(title)}">${escapeHtml(label)}</span>`;
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

function backupFormData() {
	const data = {};
	if (!els.backupRestoreForm) return data;
	new FormData(els.backupRestoreForm).forEach((value, key) => {
		data[key] = String(value || "").trim();
	});
	return data;
}

function awsBackupFormData() {
	const data = {};
	if (!els.awsBackupForm) return data;
	new FormData(els.awsBackupForm).forEach((value, key) => {
		data[key] = String(value || "").trim();
	});
	if (data.credentialId) {
		delete data.profile;
	}
	return data;
}

function selectedAddSiteMode() {
	const selected = [...els.addSiteModeInputs].find((input) => input.checked);
	return selected?.value || state.addSiteMode || "ssh";
}

function setSelectedAddSiteMode(mode) {
	state.addSiteMode = ["blank", "backup"].includes(mode) ? mode : "ssh";
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
	if (state.addSiteMode === "backup") {
		const values = backupFormData();
		return validLocalSlug(values.slug) && Boolean(liveUrlFromIdentifier(values.liveUrl)) && Boolean(state.backupSession?.files?.length);
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
	if (state.addSiteMode === "backup") {
		const values = backupFormData();
		if (!validLocalSlug(values.slug)) {
			return "Enter a lowercase local slug using letters, numbers, and hyphens.";
		}
		if (!liveUrlFromIdentifier(values.liveUrl)) {
			return "Enter the live URL so the database can be search-replaced to local.";
		}
		if (!state.backupSession?.files?.length) {
			return "Drop Updraft backup files or stage an AWS S3 backup set before continuing.";
		}
		return state.addSiteStep === "create"
			? "Ready to create the local site from the staged Updraft backup."
			: "Backup files are staged. Review the restore options next.";
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
	} else if (state.addSiteMode === "backup") {
		const values = backupFormData();
		const components = state.backupSession?.components || {};
		rows.push(
			["Type", "Restore Updraft backup"],
			["Local slug", values.slug || "Required"],
			["Live URL", values.liveUrl || "Required"],
			["Staged files", state.backupSession?.files?.length ? `${state.backupSession.files.length} file${state.backupSession.files.length === 1 ? "" : "s"}` : "Required"],
			["Backup parts", Object.entries(components).map(([key, count]) => `${key}: ${count}`).join(", ") || "None"],
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
		if (els.createBackupSiteButton) {
			els.createBackupSiteButton.hidden = state.addSiteMode !== "backup";
			els.createBackupSiteButton.disabled = state.busy || state.addSiteMode !== "backup" || !addSiteStepComplete("create");
		}
		if (els.addSiteValidation) {
			els.addSiteValidation.textContent = addSiteValidationMessage();
		}
		renderInitialImportOptions();
		renderBackupRestoreOptions();
		renderUpdraftSession();
		renderAwsBackupResults();
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
	renderCredentials();

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

function awsStoredCredentials() {
	return (state.credentials || []).filter((credential) => credential.provider === "aws");
}

function renderAwsCredentialOptions() {
	if (!els.awsBackupCredentialSelect) return;
	const selected = els.awsBackupCredentialSelect.value;
	els.awsBackupCredentialSelect.textContent = "";
	const defaultOption = document.createElement("option");
	defaultOption.value = "";
	defaultOption.textContent = "Use AWS CLI profile or environment";
	els.awsBackupCredentialSelect.append(defaultOption);
	for (const credential of awsStoredCredentials()) {
		const option = document.createElement("option");
		option.value = credential.id;
		option.textContent = `${credential.label}${credential.region ? ` · ${credential.region}` : ""}`;
		els.awsBackupCredentialSelect.append(option);
	}
	if ([...els.awsBackupCredentialSelect.options].some((option) => option.value === selected)) {
		els.awsBackupCredentialSelect.value = selected;
	}
}

function renderCredentials() {
	renderAwsCredentialOptions();
	if (!els.credentialResults || !els.credentialSummary) return;
	const credentials = state.credentials || [];
	const awsCredentials = awsStoredCredentials();
	els.credentialSummary.textContent = credentials.length
		? `${credentials.length} secure credential${credentials.length === 1 ? "" : "s"} stored in macOS Keychain.`
		: "No stored API keys yet.";
	if (els.awsCredentialStatus) {
		els.awsCredentialStatus.textContent = awsCredentials.length
			? `${awsCredentials.length} AWS key${awsCredentials.length === 1 ? "" : "s"} available for S3 backups.`
			: "Saved keys use macOS Keychain. Secrets are not written to site manifests.";
	}
	els.credentialResults.textContent = "";
	if (!credentials.length) {
		const empty = document.createElement("p");
		empty.className = "mini-muted";
		empty.textContent = "Save an AWS key to use S3 backups without exporting environment variables.";
		els.credentialResults.append(empty);
		return;
	}
	for (const credential of credentials) {
		const item = document.createElement("article");
		item.className = "provider-result credential-result";
		const meta = [
			credential.region || "no default region",
			credential.storage || "secure storage",
			credential.hasSessionToken ? "session token" : "long-lived key",
		].join(" · ");
		item.innerHTML = `
			<div>
				<span class="provider-badge">${escapeHtml(String(credential.provider || "").toUpperCase())}</span>
				<strong>${escapeHtml(credential.label || credential.id)}</strong>
				<span>${escapeHtml(meta)}</span>
			</div>
			<div class="provider-result-actions"></div>
		`;
		const actions = item.querySelector(".provider-result-actions");
		if (credential.provider === "aws") {
			const testButton = document.createElement("button");
			testButton.type = "button";
			testButton.className = "ghost-button";
			testButton.textContent = "Test";
			testButton.addEventListener("click", () => testAwsCredential(credential.id));
			actions.append(testButton);
		}
		const deleteButton = document.createElement("button");
		deleteButton.type = "button";
		deleteButton.className = "ghost-button danger-button";
		deleteButton.textContent = "Delete";
		deleteButton.addEventListener("click", () => deleteCredential(credential.id));
		actions.append(deleteButton);
		els.credentialResults.append(item);
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

function ensureBackupSessionId() {
	if (!state.backupImportSessionId) {
		const random = window.crypto?.randomUUID ? window.crypto.randomUUID() : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
		state.backupImportSessionId = `updraft-${random.toLowerCase().replace(/[^a-z0-9-]/g, "-").slice(0, 48)}`;
	}
	return state.backupImportSessionId;
}

function backupComponentLabel(component) {
	const labels = {
		db: "Database",
		plugins: "Plugins",
		themes: "Themes",
		uploads: "Uploads",
		"mu-plugins": "MU plugins",
		others: "Other wp-content",
		core: "WordPress core",
		zip: "ZIP",
		unknown: "Unknown",
	};
	return labels[component] || component;
}

function renderUpdraftSession() {
	if (!els.updraftStagedSummary || !els.updraftStagedFiles) return;
	const files = state.backupSession?.files || [];
	const components = state.backupSession?.components || {};
	if (!files.length) {
		els.updraftStagedSummary.className = "operation-status";
		els.updraftStagedSummary.textContent = "No Updraft files staged yet.";
		els.updraftStagedFiles.textContent = "";
		return;
	}
	els.updraftStagedSummary.className = "operation-status ok";
	els.updraftStagedSummary.textContent = `${files.length} file${files.length === 1 ? "" : "s"} staged · ${Object.entries(components).map(([key, count]) => `${backupComponentLabel(key)} ${count}`).join(" · ")}`;
	els.updraftStagedFiles.textContent = "";
	for (const file of files) {
		const li = document.createElement("li");
		li.innerHTML = `
			<strong>${escapeHtml(backupComponentLabel(file.component))}</strong>
			<span>${escapeHtml(file.name)} · ${escapeHtml(formatBytes(file.sizeBytes || 0))}</span>
		`;
		els.updraftStagedFiles.append(li);
	}
}

function renderBackupRestoreOptions() {
	if (!els.backupRestoreSummary) return;
	const files = state.backupSession?.files || [];
	if (!files.length) {
		els.backupRestoreSummary.className = "operation-status";
		els.backupRestoreSummary.textContent = "Stage Updraft files before creating from backup.";
		return;
	}
	const restoreFiles = els.backupRestoreFiles?.checked !== false;
	const restoreDb = els.backupRestoreDb?.checked !== false;
	const includeUploads = els.backupIncludeUploads?.checked === true;
	const parts = [];
	if (restoreFiles) {
		parts.push(includeUploads ? "files including uploads" : "files without uploads");
	}
	if (restoreDb) {
		parts.push("database");
	}
	els.backupRestoreSummary.className = "operation-status ok";
	els.backupRestoreSummary.textContent = parts.length
		? `Create From Backup will restore ${parts.join(" and ")} into a new local site.`
		: "Choose at least one restore option.";
}

function renderAwsBackupResults() {
	if (!els.awsBackupResults || !els.awsBackupStatus) return;
	const groups = state.awsBackupGroups || [];
	els.awsBackupResults.textContent = "";
	if (!groups.length) {
		els.awsBackupStatus.textContent = "AWS S3 backup source is optional.";
		return;
	}
	els.awsBackupStatus.className = "operation-status ok";
	els.awsBackupStatus.textContent = `${groups.length} Updraft backup set${groups.length === 1 ? "" : "s"} found.`;
	for (const group of groups.slice(0, 20)) {
		const item = document.createElement("article");
		item.className = "provider-result";
		const components = Object.entries(group.components || {})
			.map(([key, count]) => `${backupComponentLabel(key)} ${count}`)
			.join(" · ");
		item.innerHTML = `
			<div>
				<span class="provider-badge">AWS S3</span>
				<strong>${escapeHtml(group.label || group.id)}</strong>
				<span>${escapeHtml(group.lastModified || "unknown date")} · ${escapeHtml(formatBytes(group.totalBytes || 0))} · ${escapeHtml(components)}</span>
			</div>
			<div class="provider-result-actions"></div>
		`;
		const actions = item.querySelector(".provider-result-actions");
		const stageCodeButton = document.createElement("button");
		stageCodeButton.type = "button";
		stageCodeButton.className = "ghost-button";
		stageCodeButton.textContent = "Stage No Uploads";
		stageCodeButton.addEventListener("click", () => stageAwsBackupGroup(group, { includeUploads: false }));
		const stageAllButton = document.createElement("button");
		stageAllButton.type = "button";
		stageAllButton.className = "action";
		stageAllButton.textContent = "Stage Set";
		stageAllButton.addEventListener("click", () => stageAwsBackupGroup(group, { includeUploads: true }));
		actions.append(stageCodeButton, stageAllButton);
		els.awsBackupResults.append(item);
	}
}

async function uploadUpdraftFiles(fileList) {
	const files = [...fileList].filter((file) => /\.(zip|gz|sql)$/i.test(file.name));
	if (!files.length) {
		showToast("Drop Updraft .zip, .gz, or .sql files.", "error");
		return;
	}
	const session = ensureBackupSessionId();
	setBusy(true, "Stage Updraft");
	try {
		for (const file of files) {
			els.updraftStagedSummary.textContent = `Uploading ${file.name}...`;
			const response = await fetch(`/api/backups/updraft/uploads/${encodeURIComponent(session)}?filename=${encodeURIComponent(file.name)}`, {
				method: "POST",
				body: file,
			});
			const data = await response.json();
			if (!response.ok) {
				throw new Error(data.error || `Upload failed: ${file.name}`);
			}
			state.backupSession = data.session;
		}
		showToast("Updraft files staged.", "success");
		renderAddSiteWizard();
	} catch (error) {
		showToast(error.message, "error");
		appendMessage(error.message, true);
	} finally {
		setBusy(false);
	}
}

async function listAwsBackups() {
	const form = awsBackupFormData();
	if (!form.bucket) {
		showToast("Enter an S3 bucket before listing backups.", "error");
		return;
	}
	setBusy(true, "List S3 Backups");
	try {
		els.awsBackupStatus.className = "operation-status";
		els.awsBackupStatus.textContent = "Listing S3 backups...";
		const response = await api("/api/backups/updraft/actions", {
			method: "POST",
			body: JSON.stringify({ action: "aws-list", ...form }),
		});
		appendOutput("aws-s3-list", response.result, response.result.code !== 0);
		state.awsBackupGroups = response.result.groups || [];
		renderAwsBackupResults();
		renderChecklist();
	} catch (error) {
		els.awsBackupStatus.className = "operation-status warn";
		els.awsBackupStatus.textContent = error.message;
		showToast(error.message, "error");
	} finally {
		setBusy(false);
	}
}

async function stageAwsBackupGroup(group, options = {}) {
	const form = awsBackupFormData();
	const includeUploads = options.includeUploads !== false;
	const keys = (group.files || [])
		.filter((file) => includeUploads || file.component !== "uploads")
		.map((file) => file.key);
	if (!keys.length) {
		showToast("This backup set has no files to stage with those options.", "error");
		return;
	}
	const session = ensureBackupSessionId();
	setBusy(true, "Stage S3 Backup");
	try {
		els.awsBackupStatus.className = "operation-status";
		els.awsBackupStatus.textContent = `Downloading ${keys.length} S3 file${keys.length === 1 ? "" : "s"}...`;
		const response = await api("/api/backups/updraft/actions", {
			method: "POST",
			body: JSON.stringify({
				action: "aws-download-set",
				...form,
				session,
				keys,
			}),
		});
		appendOutput("aws-s3-download", response.result, response.result.code !== 0);
		state.backupSession = response.result.session;
		showToast(response.result.code === 0 ? "S3 backup staged." : "S3 staging failed.", response.result.code === 0 ? "success" : "error");
		renderAddSiteWizard();
	} catch (error) {
		showToast(error.message, "error");
		appendMessage(error.message, true);
	} finally {
		setBusy(false);
	}
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
	const backupSetCount = state.awsBackupGroups.length;
	const credentialCount = state.credentials.length;
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
					: backupSetCount
						? `${backupSetCount} S3 backup set${backupSetCount === 1 ? "" : "s"} ready to stage for restore.`
						: credentialCount
							? `${credentialCount} secure API credential${credentialCount === 1 ? "" : "s"} stored for external services.`
							: "WP Engine, SiteGround, AWS S3 backup discovery, and secure key storage are wired in.",
				status: state.providerAccounts || backupSetCount || credentialCount ? "success" : "current",
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

function pushFileSelectionFromControls({ requireCustomPath = false } = {}) {
	const scope = els.pushFileScope?.value || "active-theme";
	const customPath = (els.pushPath?.value || "").trim().replace(/^\/+|\/+$/g, "");
	const label = scope === "custom"
		? customPath
			? `Custom: ${customPath}`
			: "Custom directory"
		: pullFileScopeLabels[scope] || "Child / active theme";
	if (scope === "custom" && requireCustomPath && !customPath) {
		appendMessage("Enter a custom push path before running the push action.", true);
		return null;
	}
	return {
		fileScope: scope,
		relativePath: scope === "custom" ? customPath : "",
		pushScopeLabel: label,
	};
}

function currentPushFileSelection({ requireCustomPath = false } = {}) {
	return pushFileSelectionFromControls({ requireCustomPath });
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

function renderPushScopeControls() {
	const isCustom = (els.pushFileScope?.value || "active-theme") === "custom";
	if (els.pushCustomPathField) {
		els.pushCustomPathField.hidden = !isCustom;
	}
	if (els.pushPath) {
		els.pushPath.disabled = !currentSite() || !isCustom;
	}
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
		["apply-php-version", "normalize-local-url"],
		!runtimeReady,
		"Provision the site before applying runtime settings.",
	);
	setActionButtons(
		["push-audit", "push-path-dry-run", "push-path"],
		!canUseRemoteOps,
		runtimeReady ? "Add remote SSH and WordPress root before pushing." : "Provision the site before pushing.",
	);
	if (els.pushFileScope) {
		els.pushFileScope.disabled = !site;
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
	renderPushScopeControls();
	renderPushSummary(site);
}

function fillForm(site) {
	els.siteForm.querySelectorAll("[name]").forEach((field) => {
		field.value = site[field.name] ?? "";
	});
	els.siteTitle.textContent = site.title || site.slug;
	els.siteSubtitle.textContent = site.localUrl || site.localRoot || "Local WordPress workspace";
	els.runtimeLabel.textContent = `${providerLabel(site.provider)} / ${site.webserver || "openlitespeed"} / ${site.runtimeStatus || "planned"}`;
	renderSiteWarnings(site);
	renderPhpRuntimeStatus(site);
	renderPullSummary(site);
	renderPushSummary(site);
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
}

function renderPushSummary(site) {
	if (!els.pushSummary) return;
	if (!site) {
		els.pushSummary.className = "operation-status";
		els.pushSummary.textContent = "Select a site with SSH details to run a push audit.";
		return;
	}

	if (!site.remoteSsh || !site.remotePath) {
		els.pushSummary.className = "operation-status warn";
		els.pushSummary.textContent = "Add remote SSH and WordPress root before pushing.";
		return;
	}

	const selection = currentPushFileSelection();
	const blockedScopes = new Set(["full", "full-no-uploads", "core"]);
	if (blockedScopes.has(selection.fileScope)) {
		els.pushSummary.className = "operation-status warn";
		els.pushSummary.textContent = `${selection.pushScopeLabel} pushes are blocked by audit. Choose wp-content, theme, plugins, uploads, or a custom child path.`;
		return;
	}

	const previewPath = selection.fileScope === "custom"
		? selection.relativePath || "choose a custom directory"
		: selection.fileScope === "active-theme"
			? "child/active theme, resolved from local WordPress"
			: pushScopePreviewPaths[selection.fileScope] || selection.pushScopeLabel;
	const broadScope = selection.fileScope === "wp-content" || selection.fileScope === "uploads";
	els.pushSummary.className = `operation-status${broadScope ? " warn" : ""}`;
	if (selection.fileScope === "active-theme") {
		els.pushSummary.textContent = `${selection.pushScopeLabel}: resolves the local WordPress stylesheet/child theme, then deploys it to ${site.remoteSsh}:${summarizePath(site.remotePath)}. Run Push Audit first.`;
		return;
	}
	if (selection.fileScope === "parent-theme") {
		els.pushSummary.textContent = `${selection.pushScopeLabel}: resolves the local WordPress template/parent theme, then deploys it to ${site.remoteSsh}:${summarizePath(site.remotePath)}. Run Push Audit first.`;
		return;
	}
	const localPreview = selection.relativePath || pushScopePreviewPaths[selection.fileScope] || previewPath;
	const targetPreview = localPreview && !localPreview.startsWith("choose ")
		? `${site.remoteSsh}:${summarizePath(site.remotePath)}/${localPreview}`
		: `${site.remoteSsh}:${summarizePath(site.remotePath)}`;
	els.pushSummary.textContent = `${selection.pushScopeLabel}: ${summarizePath(site.publicPath)}/${previewPath} -> ${targetPreview}. Run Push Audit first.`;
}

function formatAdminAccessStatus(status) {
	if (status === "reachable") return "wp-admin reachable";
	if (status === "security-blocked") return "security plugin appears to block admin";
	if (status === "redirected") return "wp-admin redirected";
	if (status === "http-error") return "wp-admin returned an HTTP error";
	return status || "unknown admin state";
}

function adminPluginName(candidate) {
	if (!candidate) return "";
	if (candidate.label && candidate.label !== candidate.name) {
		return `${candidate.label} (${candidate.name})`;
	}
	return candidate.name || candidate.label || "";
}

function adminPluginNames(candidates) {
	return candidates.map(adminPluginName).filter(Boolean).join(", ");
}

function conciseProcessText(value) {
	const lines = String(value || "")
		.split(/\r?\n/)
		.map((line) => line.trim())
		.filter(Boolean)
		.filter((line) => !/^PHP\s+Deprecated:/i.test(line));
	return lines.slice(-2).join(" ");
}

function adminFinalStatus(result) {
	const access = result.adminAccess || {};
	const after = access.after || null;
	return after?.status || access.status || access.before?.status || "unknown";
}

function summarizeAdminResult(action, result) {
	const access = result.adminAccess || {};
	const finalStatus = adminFinalStatus(result);
	const candidates = access.candidates || [];
	const blockers = candidates.filter((candidate) => candidate.disable);
	const deactivated = access.deactivated || [];
	const processError = result.code !== 0 ? conciseProcessText(result.stderr || result.stdout) : "";
	const items = [];

	if (action === "admin-unlock") {
		if (deactivated.length) {
			items.push(`Deactivated locally: ${adminPluginNames(deactivated)}.`);
		} else {
			items.push("No local plugins were deactivated.");
		}
		if (access.backupPath) {
			items.push(`Unlock record saved: ${access.backupPath}`);
		}
	} else {
		items.push(blockers.length
			? `High-confidence blocker${blockers.length === 1 ? "" : "s"} found: ${adminPluginNames(blockers)}.`
			: "No high-confidence blockers found in the active plugin list.");
	}

	if (candidates.length) {
		items.push(`Security/login candidate${candidates.length === 1 ? "" : "s"} inspected: ${adminPluginNames(candidates)}.`);
	} else if (result.code !== 0) {
		items.push("Active plugin inspection did not complete.");
	} else {
		items.push("No security/login plugin candidates detected.");
	}
	if (processError) {
		items.push(`Command detail: ${processError}`);
	}
	items.push(`Final state: ${formatAdminAccessStatus(finalStatus)}.`);

	const adminReachable = finalStatus === "reachable";
	const changedPlugins = deactivated.length ? ` Deactivated ${deactivated.length} local blocker${deactivated.length === 1 ? "" : "s"}.` : "";
	return {
		ok: result.code === 0 && adminReachable,
		message: action === "admin-unlock"
			? adminReachable
				? `Unlock complete for ${result.args?.[0] || "site"}; wp-admin is reachable.`
				: `Unlock still needs review for ${result.args?.[0] || "site"}.${changedPlugins}`
			: adminReachable
				? "Admin check complete: wp-admin is reachable."
				: `Admin check found a blocker: ${formatAdminAccessStatus(finalStatus)}.`,
		items,
	};
}

function clearAdminAccessWarnings(site) {
	if (!site?.slug) return;
	const existing = state.siteWarnings[site.slug] || [];
	state.siteWarnings[site.slug] = existing.filter((item) => item.source !== "admin-access" && item.action !== "admin-unlock");
}

function adminAccessWarningItems(action, result) {
	const access = result.adminAccess || {};
	const finalStatus = adminFinalStatus(result);
	if (finalStatus === "reachable") {
		return [];
	}

	const candidates = access.candidates || [];
	const blockers = candidates.filter((candidate) => candidate.disable);
	const deactivated = access.deactivated || [];
	const processError = conciseProcessText(result.stderr || "");

	if (result.code !== 0 && processError && !candidates.length) {
		return [{
			level: "issue",
			title: "Admin plugin inspection failed",
			detail: `wp-admin is still blocked, and the Hub could not inspect active plugins. ${processError}`,
			suggestion: "Suggested action: run Admin Check again. If this repeats, inspect WP-CLI/PHP warnings before unlocking plugins.",
		}];
	}

	if (action === "admin-check" && blockers.length) {
		return [{
			level: "warning",
			title: "Admin blocker detected",
			detail: `wp-admin/wp-login.php are blocked locally. High-confidence blocker${blockers.length === 1 ? "" : "s"} detected: ${adminPluginNames(blockers)}.`,
			action: "admin-unlock",
			suggestion: "Suggested action: run Local Admin Unlock if you need wp-admin access. This only changes the local database.",
		}];
	}

	if (action === "admin-unlock" && deactivated.length) {
		return [{
			level: "issue",
			title: "Admin still blocked after unlock",
			detail: `The Hub deactivated ${adminPluginNames(deactivated)} locally, but wp-admin/wp-login.php still return ${formatAdminAccessStatus(finalStatus)}.`,
			suggestion: "Suggested action: run Admin Check again, then review mu-plugins, .htaccess, or other local security rules if no new blocker appears.",
		}];
	}

	if (action === "admin-unlock" && blockers.length) {
		return [{
			level: "issue",
			title: "Admin unlock did not change local plugins",
			detail: `wp-admin/wp-login.php are still blocked. Detected blocker${blockers.length === 1 ? "" : "s"}: ${adminPluginNames(blockers)}, but none were deactivated.`,
			action: "admin-unlock",
			suggestion: "Suggested action: try Local Admin Unlock again, then review the log if plugin deactivation fails.",
		}];
	}

	return [{
		level: action === "admin-unlock" ? "issue" : "warning",
		title: action === "admin-unlock" ? "Admin still blocked" : "Admin access blocked",
		detail: "wp-admin/wp-login.php are blocked locally, but no high-confidence active plugin blocker was detected.",
		suggestion: "Suggested action: review mu-plugins, .htaccess, and local-only security rules; the database import can still be valid if you do not need wp-admin.",
	}];
}

function updateAdminReadiness(site, action, result) {
	if (!site || !["admin-check", "admin-unlock"].includes(action)) return;
	state.adminReadiness[site.slug] = summarizeAdminResult(action, result);
	clearAdminAccessWarnings(site);
	setSiteWarnings(site, "admin-access", adminAccessWarningItems(action, result));
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

function warningKey(source, title, detail) {
	return `${source || "warning"}:${title || "Warning"}:${detail || ""}`;
}

function normalizeWarningItem(source, warning) {
	if (typeof warning === "string") {
		return {
			source,
			level: "warning",
			title: source === "smoke" ? "Smoke check warning" : "Warning",
			detail: warning,
		};
	}
	return {
		source,
		level: warning?.level || "warning",
		title: warning?.title || "Warning",
		detail: warning?.detail || warning?.message || "",
		action: warning?.action || "",
		fileScope: warning?.fileScope || "",
		relativePath: warning?.relativePath || "",
		suggestion: warning?.suggestion || "",
	};
}

function setSiteWarnings(site, source, warnings = []) {
	if (!site?.slug) return;
	const existing = state.siteWarnings[site.slug] || [];
	const next = existing.filter((item) => item.source !== source);
	for (const warning of warnings) {
		const item = normalizeWarningItem(source, warning);
		if (item.detail || item.title) {
			next.push(item);
		}
	}
	const seen = new Set();
	state.siteWarnings[site.slug] = next.filter((item) => {
		const key = warningKey(item.source, item.title, item.detail);
		if (seen.has(key)) return false;
		seen.add(key);
		return true;
	});
	renderSiteWarnings(site);
}

function codeSyncWarningItems(result) {
	return result.codeSync?.warnings || [];
}

function actionFailureDetail(result, fallback = "Review the deployment log for the command output.") {
	const lines = String([result.stderr, result.stdout].filter(Boolean).join("\n") || "")
		.split(/\r?\n/)
		.map((line) => line.trim())
		.filter(Boolean)
		.filter((line) => !/^Exit:\s*\d+$/i.test(line))
		.filter((line) => !/^(Pull scope|Remote source|Local target):/i.test(line));
	return lines.length ? lines.slice(-5).join(" ") : fallback;
}

function pullFailureWarningItems(action, result) {
	if (!result || result.code === 0) {
		return [];
	}
	const scopeLabel = result.pullScope?.label || "Selected files";
	if (action === "pull-files" || action === "pull-files-dry-run") {
		const dryRun = action === "pull-files-dry-run";
		return [{
			level: "issue",
			title: dryRun ? "File dry run failed" : "File pull failed",
			detail: `${scopeLabel} could not be ${dryRun ? "previewed" : "pulled"}. ${actionFailureDetail(result)}`,
			suggestion: dryRun
				? "Suggested action: review the file dry-run output, then fix SSH/path/Git issues before pulling."
				: "Suggested action: review the pull output, then fix SSH/path/Git issues before trying Pull Files & DB again.",
		}];
	}
	if (action === "pull-db") {
		return [{
			level: "issue",
			title: "Database pull failed",
			detail: `The remote database was not imported locally. ${actionFailureDetail(result)}`,
			suggestion: "Suggested action: review the database pull output, then retry Pull DB after fixing the remote export or local import issue.",
		}];
	}
	return [];
}

function gitSafetyWarningItems(action, result) {
	const gitSafety = result?.gitSafety;
	if (!gitSafety?.targetDirty || action !== "pull-files-dry-run") {
		return [];
	}
	return [{
		level: "warning",
		title: "Git safety will block file pull",
		detail: gitSafety.summary || "The local Git repo has changes in the selected pull scope.",
		suggestion: "Suggested action: commit, stash, or back up local changes before running Pull Files or Pull Files & DB.",
	}];
}

function smokeWarningItems(smoke) {
	const checks = smoke?.checks || [];
	return checks
		.filter((check) => check.status && check.status !== "pass")
		.map((check) => {
			let detail = `${check.label}: ${check.detail}`;
			if (check.label === "Admin" && /blocked by a WordPress security plugin/i.test(check.detail || "")) {
				detail += " Run Local Admin Unlock if you need wp-admin access; otherwise the database import is okay.";
			}
			return {
				level: check.status === "fail" ? "issue" : "warning",
				title: check.status === "warn" ? "Smoke check warning" : "Smoke check issue",
				detail,
				action: check.label === "Admin" ? "admin-unlock" : "",
			};
		});
}

function renderSiteWarnings(site = currentSite()) {
	if (!els.siteWarningsCard || !els.siteWarningsSummary || !els.siteWarningsList) return;
	els.siteWarningsList.textContent = "";
	const warnings = site?.slug ? state.siteWarnings[site.slug] || [] : [];
	els.siteWarningsCard.hidden = !site;
	if (!site) {
		els.siteWarningsCard.dataset.state = "clean";
		els.siteWarningsSummary.textContent = "No site selected.";
		return;
	}
	if (!warnings.length) {
		els.siteWarningsCard.dataset.state = "clean";
		els.siteWarningsSummary.textContent = "No issues or warnings for this site.";
		const li = document.createElement("li");
		li.className = "clean";
		const title = document.createElement("strong");
		const detail = document.createElement("p");
		title.textContent = "[ok] Sync checks clear";
		detail.textContent = "Warnings from preflight, database pulls, smoke checks, and code parity checks will appear here.";
		li.append(title, detail);
		els.siteWarningsList.append(li);
		return;
	}

	const issueCount = warnings.filter((warning) => warning.level === "issue").length;
	const warningCount = warnings.length - issueCount;
	const totalCount = issueCount + warningCount;
	els.siteWarningsCard.dataset.state = issueCount ? "issue" : "warning";
	els.siteWarningsSummary.textContent = [
		issueCount ? `${issueCount} issue${issueCount === 1 ? "" : "s"}` : "",
		warningCount ? `${warningCount} warning${warningCount === 1 ? "" : "s"}` : "",
	].filter(Boolean).join(" and ") + ` ${totalCount === 1 ? "needs" : "need"} review before treating local as fully in sync.`;
	for (const warning of warnings) {
		const li = document.createElement("li");
		if (warning.level === "issue") {
			li.className = "issue";
		}
		const title = document.createElement("strong");
		const detail = document.createElement("p");
		title.textContent = warning.title || "Warning";
		detail.textContent = warning.detail || "";
		li.append(title, detail);
		const suggestion = [];
		if (warning.suggestion) {
			suggestion.push(warning.suggestion);
		} else if (warning.action === "pull-files" && warning.fileScope === "active-theme") {
			suggestion.push("Suggested action: Pull Child / Active Theme.");
		} else if (warning.action === "pull-files" && warning.relativePath) {
			suggestion.push(`Suggested action: Pull ${warning.relativePath}.`);
		} else if (warning.action === "admin-unlock") {
			suggestion.push("Suggested action: Local Admin Unlock, only if you need wp-admin access.");
		}
		if (warning.source) {
			suggestion.push(`Source: ${warning.source.replace(/-/g, " ")}.`);
		}
		if (suggestion.length) {
			const small = document.createElement("small");
			small.textContent = suggestion.join(" ");
			li.append(small);
		}
		els.siteWarningsList.append(li);
	}
}

function phpVersionLabel(version) {
	const normalized = String(version || "8.4");
	return normalized === "7.4" ? "Legacy PHP 7.4" : `PHP ${normalized}`;
}

function renderPhpRuntimeStatus(site = currentSite()) {
	if (!els.phpRuntimeStatus) return;
	if (!site) {
		els.phpRuntimeStatus.className = "php-runtime-status";
		els.phpRuntimeStatus.textContent = "Select a site to view PHP runtime status.";
		return;
	}
	const target = String(site.phpVersion || "8.4");
	const active = String(site.activePhpVersion || "");
	const status = String(site.phpStatus || "");
	const isLegacy = target === "7.4";
	const isConfirmed = status === "applied" && active === target;
	const isMissing = status === "missing";
	const isMismatch = status === "mismatch" || (active && active !== target);
	const stateClass = isMissing || isMismatch ? "issue" : isLegacy || !isConfirmed ? "warn" : "ok";
	const checked = site.phpCheckedAt
		? new Date(site.phpCheckedAt).toLocaleString()
		: "Not checked yet";
	const note = isMissing
		? "Target PHP is not installed in the local runtime. Repair Install or Apply PHP Version will try to install it."
		: isMismatch
			? "Target and active PHP do not match. Apply PHP Version again and review the log if it stays mismatched."
			: isLegacy
				? "PHP 7.4 is legacy and should be used only for temporary upgrade testing."
				: isConfirmed
					? "The local web handler matches the target PHP version."
					: "Click Apply PHP Version to update the local OpenLiteSpeed vhost and verify the active web handler.";
	els.phpRuntimeStatus.className = `php-runtime-status ${stateClass}`;
	els.phpRuntimeStatus.innerHTML = `
		<div class="php-runtime-row">
			<span>Target</span>
			<strong>${escapeHtml(phpVersionLabel(target))}</strong>
			${isLegacy ? '<em class="mini-pill warn">Legacy</em>' : ""}
		</div>
		<div class="php-runtime-row">
			<span>Active</span>
			<strong>${escapeHtml(active ? `PHP ${active}` : "Unknown")}</strong>
			<em class="mini-pill ${isConfirmed ? "ok" : isMissing || isMismatch ? "bad" : "warn"}">${escapeHtml(isConfirmed ? "Confirmed" : isMissing ? "Missing" : isMismatch ? "Mismatch" : "Needs apply")}</em>
		</div>
		<p>${escapeHtml(note)}</p>
		<small>Last checked: ${escapeHtml(checked)}</small>
	`;
}

function updatePullReadiness(site, action, result) {
	if (!site) return;
	if (action === "pull-preflight") {
		const issues = result.preflight?.issues || [];
		const warnings = result.preflight?.warnings || [];
		const notes = [...issues, ...warnings];
		const scopeLabel = result.preflight?.pullScope?.label || result.pullScope?.label || "Pull";
		state.pullReadiness[site.slug] = {
			ok: result.code === 0 && !issues.length,
			message: issues.length
				? "Preflight blocked. Review Issues & Warnings."
				: warnings.length
					? "Preflight completed with warnings. Review Issues & Warnings."
					: `${scopeLabel} preflight ready. Run a file dry run before pulling.`,
			items: [],
		};
		setSiteWarnings(site, "pull-preflight", [
			...issues.map((issue) => ({
				level: "issue",
				title: "Preflight issue",
				detail: issue,
			})),
			...warnings.map((warning) => ({
				level: "warning",
				title: "Preflight warning",
				detail: warning,
			})),
		]);
	} else if (action === "pull-files-dry-run") {
		const scopeLabel = result.pullScope?.label || "File";
		state.pullReadiness[site.slug] = {
			ok: result.code === 0,
			message: result.code === 0
				? result.gitSafety?.targetDirty
					? `${scopeLabel} dry run completed, but Git safety will block the real pull.`
					: `${scopeLabel} dry run completed. Pull Files is ready when you are.`
				: `${scopeLabel} dry run failed; review the deployment log.`,
		};
		setSiteWarnings(site, "pull-files-dry-run", [
			...pullFailureWarningItems(action, result),
			...gitSafetyWarningItems(action, result),
		]);
	} else if (action === "pull-files") {
		const scopeLabel = result.pullScope?.label || "Files";
		state.pullReadiness[site.slug] = {
			ok: result.code === 0,
			message: result.code === 0 ? `${scopeLabel} pulled into the local public path.` : `${scopeLabel} pull failed; review the deployment log.`,
		};
		if (result.code === 0) {
			setSiteWarnings(site, "pull-preflight", []);
			setSiteWarnings(site, "pull-files", []);
		} else {
			setSiteWarnings(site, "pull-files", pullFailureWarningItems(action, result));
		}
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
		const warningCount = smokeWarningItems(smoke).length + codeSyncWarningItems(result).length;
		state.pullReadiness[site.slug] = {
			ok: result.code === 0,
			message: smoke
				? result.code === 0
					? smokeFailed
						? `Database pulled. ${warningCount} follow-up item${warningCount === 1 ? "" : "s"} moved to Issues & Warnings.`
						: smokeWarned
							? `Database pulled. ${warningCount} warning${warningCount === 1 ? "" : "s"} moved to Issues & Warnings.`
							: codeSyncWarningItems(result).length
								? `Database pulled. ${warningCount} warning${warningCount === 1 ? "" : "s"} moved to Issues & Warnings.`
								: `Database pulled. Smoke check passed (${smoke.passed} passed).`
					: `Database import failed before smoke check finished.`
				: result.code === 0
					? codeSyncWarningItems(result).length
						? `Database pulled. ${warningCount} warning${warningCount === 1 ? "" : "s"} moved to Issues & Warnings.`
						: "Database pulled and search-replaced for local."
					: "Database pull failed; review the deployment log.",
			items: [],
		};
		setSiteWarnings(site, "pull-db", [
			...pullFailureWarningItems(action, result),
			...smokeWarningItems(smoke),
			...codeSyncWarningItems(result),
		]);
	} else if (action === "smoke-check") {
		const smoke = result.smoke;
		const smokeWarned = Boolean(smoke && smoke.warnings);
		const warningCount = smokeWarningItems(smoke).length;
		state.pullReadiness[site.slug] = {
			ok: result.code === 0,
			message: smoke
				? result.code === 0
					? smokeWarned
						? `Smoke check passed. ${warningCount} warning${warningCount === 1 ? "" : "s"} moved to Issues & Warnings.`
						: `Smoke check passed (${smoke.passed} passed).`
					: `Smoke check found ${warningCount || smoke.failed} follow-up item${(warningCount || smoke.failed) === 1 ? "" : "s"} in Issues & Warnings.`
				: result.code === 0
					? "Smoke check passed."
					: "Smoke check failed; review the deployment log.",
			items: [],
		};
		setSiteWarnings(site, "smoke-check", smokeWarningItems(smoke));
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
		const gitBadge = gitStatusBadge(siteMetrics.git);

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
				${gitBadge ? `<span class="site-card-git">${gitBadge}</span>` : ""}
			</span>
		`;
			card.addEventListener("click", () => selectSite(site.slug, true));
			els.dashboardSiteList.append(card);
		}
	}

	for (const site of visibleSites) {
		const status = site.runtimeStatus || "planned";
		const siteMetrics = metricsBySlug.get(site.slug) || {};
		const gitBadge = gitStatusBadge(siteMetrics.git);
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
							${gitBadge ? `<small class="site-row-git">${gitBadge}</small>` : ""}
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
	const liveCertStale = friendly?.liveCertificate?.covers === false;
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
		liveCertStale ? "Helper cert stale" : certReady ? "Cert ready" : "Cert needed",
		liveCertStale ? "warn" : certReady ? "ok" : "bad",
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
		els.refreshSslCertButton.className = certReady && !liveCertStale ? "ghost-button success-button" : "ghost-button";
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
		friendly?.liveCertificate ? `live cert: ${friendly.liveCertificate.status} ${friendly.liveCertificate.message || ""}` : "",
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
	const site = currentSite();
	els.emptyState.hidden = true;
	els.siteDetail.hidden = !site;
	if (site) {
		fillForm(site);
	} else {
		renderPhpRuntimeStatus(null);
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
		setActiveTab(siteDefaultTab, "sites");
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
			credentials: { credentials: [] },
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
	state.credentials = providerAccountsResponse.credentials?.credentials || [];
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
		state.credentials = response.credentials?.credentials || [];
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

async function saveAwsCredential() {
	if (!els.awsCredentialForm) return;
	const payload = providerFormData(els.awsCredentialForm);
	setBusy(true, "Save AWS Key");
	try {
		const response = await api("/api/credentials/actions", {
			method: "POST",
			body: JSON.stringify({
				action: "aws-save",
				credential: payload,
			}),
		});
		state.credentials = response.result.credentials || [];
		["accessKeyId", "secretAccessKey", "sessionToken"].forEach((name) => {
			const field = els.awsCredentialForm.elements[name];
			if (field) field.value = "";
		});
		appendOutput("credential-save", response.result, response.result.code !== 0);
		showToast("AWS key saved to Keychain.", "success");
		renderCredentials();
		renderChecklist();
	} catch (error) {
		showToast(error.message, "error");
		appendMessage(error.message, true);
	} finally {
		setBusy(false);
	}
}

async function testAwsCredential(id) {
	setBusy(true, "Test AWS Key");
	try {
		const response = await api("/api/credentials/actions", {
			method: "POST",
			body: JSON.stringify({
				action: "aws-test",
				id,
			}),
		});
		appendOutput("credential-test", response.result, response.result.code !== 0);
		showToast(response.result.code === 0 ? "AWS key works." : "AWS key test failed.", response.result.code === 0 ? "success" : "error");
	} catch (error) {
		showToast(error.message, "error");
		appendMessage(error.message, true);
	} finally {
		setBusy(false);
	}
}

async function deleteCredential(id) {
	const credential = (state.credentials || []).find((item) => item.id === id);
	const label = credential?.label || id;
	if (!window.confirm(`Delete the stored credential "${label}" from MRN Local Hub and macOS Keychain?`)) {
		return;
	}
	setBusy(true, "Delete Key");
	try {
		const response = await api("/api/credentials/actions", {
			method: "POST",
			body: JSON.stringify({
				action: "delete",
				id,
			}),
		});
		state.credentials = response.result.credentials || [];
		appendOutput("credential-delete", response.result, response.result.code !== 0);
		showToast("Credential deleted.", "success");
		renderCredentials();
		renderChecklist();
	} catch (error) {
		showToast(error.message, "error");
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
		setActiveTab(siteDefaultTab, "sites");
		appendMessage(`Created ${response.site.slug}`);
	} catch (error) {
		appendMessage(error.message, true);
	} finally {
		setBusy(false);
	}
}

async function runSiteActionStep(site, action, payload = {}, options = {}) {
	appendPending(actionLabel(action), actionPendingMessage(action, site, payload), { revealActivity: options.revealActivity !== false });
	const response = await api(`/api/sites/${encodeURIComponent(site.slug)}/actions`, {
		method: "POST",
		body: JSON.stringify({ action, ...payload }),
	});
	appendOutput(action, response.result, response.result.code !== 0, options.notify !== false);
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
			const result = await runSiteActionStep(site, step.action, step.payload, { notify: false });
			if (result.site?.slug) {
				site = result.site;
			}
			if (result.code !== 0) {
				throw new Error(`${actionLabel(step.action)} failed. Review the deployment log.`);
			}
		}
		await refresh();
		selectSite(site.slug);
		setActiveTab(siteDefaultTab, "sites");
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

async function createSiteFromBackup() {
	if (state.addSiteMode !== "backup") {
		return;
	}
	const values = backupFormData();
	if (!addSiteConnectionComplete()) {
		showToast(addSiteValidationMessage(), "error");
		return;
	}
	const restoreFiles = els.backupRestoreFiles?.checked !== false;
	const restoreDb = els.backupRestoreDb?.checked !== false;
	if (!restoreFiles && !restoreDb) {
		showToast("Choose files, database, or both before restoring.", "error");
		return;
	}
	const confirmation = await requestConfirmation({
		title: "Create From Updraft Backup",
		message: `This will create ${values.slug}, provision local runtime, and restore the staged Updraft backup into the new local site.`,
		token: "RESTORE",
	});
	if (confirmation !== "RESTORE") {
		appendMessage("Backup restore cancelled");
		return;
	}

	setBusy(true, "Create From Backup");
	appendPending("Create From Backup", `Restoring staged Updraft backup into ${values.slug}.`, { revealActivity: true });
	try {
		const response = await api("/api/backups/updraft/actions", {
			method: "POST",
			body: JSON.stringify({
				action: "create-site-from-backup",
				session: state.backupImportSessionId,
				slug: values.slug,
				title: values.title,
				liveUrl: values.liveUrl,
				restoreFiles,
				restoreDb,
				includeUploads: els.backupIncludeUploads?.checked === true,
			}),
		});
		appendOutput("updraft-create-site", response.result, response.result.code !== 0);
		if (response.result.site?.slug) {
			state.currentSlug = response.result.site.slug;
			await refresh();
			selectSite(response.result.site.slug);
			setActiveTab(siteDefaultTab, "sites");
			const site = currentSite() || response.result.site;
			setSiteWarnings(site, "backup-restore", [
				...smokeWarningItems(response.result.smoke),
				...codeSyncWarningItems(response.result),
			]);
		}
		const warned = Boolean(response.result.smoke?.warnings || response.result.smoke?.failed || response.result.codeSync?.warningCount);
		if (response.result.code === 0) {
			state.backupImportSessionId = "";
			state.backupSession = null;
			state.awsBackupGroups = [];
		}
		showToast(
			response.result.code === 0
				? warned
					? "Backup restored. Warnings need review."
					: "Backup restored."
				: "Backup restore failed.",
			response.result.code === 0 ? (warned ? "info" : "success") : "error",
		);
	} catch (error) {
		appendMessage(error.message, true);
		showToast(error.message, "error");
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
	appendPending(label, actionPendingMessage("pull-files-db", site, selection), { revealActivity: true });
	try {
		const filesResult = await runSiteActionStep(activeSite, "pull-files", selection, { notify: false });
		if (filesResult.site?.slug) {
			activeSite = filesResult.site;
		}
		if (filesResult.code !== 0) {
			throw new Error("File pull failed. Database pull was skipped.");
		}
		const dbResult = await runSiteActionStep(activeSite, "pull-db", {}, { notify: false });
		if (dbResult.site?.slug) {
			activeSite = dbResult.site;
		}
		if (dbResult.code !== 0) {
			throw new Error("Database pull failed. Review the deployment log.");
		}
		await refresh();
		selectSite(activeSite.slug);
		const smokeFailed = Boolean(dbResult.smoke && dbResult.smoke.failed);
		const smokeWarned = Boolean(dbResult.smoke && dbResult.smoke.warnings);
		const codeSyncWarned = Boolean(dbResult.codeSync?.warningCount);
		showToast(
			smokeFailed || smokeWarned || codeSyncWarned ? "Files and database pulled. Warnings need review." : "Files and database pulled.",
			smokeFailed || smokeWarned || codeSyncWarned ? "info" : "success",
		);
	} catch (error) {
		appendMessage(error.message, true);
		showToast(error.message, "error");
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
	if (action === "apply-php-version") {
		payload.phpVersion = els.siteForm?.elements.phpVersion?.value || site.phpVersion || "8.4";
	}
	if (action.startsWith("push-")) {
		const selection = currentPushFileSelection({ requireCustomPath: true });
		if (!selection) {
			return;
		}
		payload.fileScope = selection.fileScope;
		payload.relativePath = selection.relativePath;
		payload.pushScopeLabel = selection.pushScopeLabel;
		payload.deleteFiles = els.deleteFiles.checked;
		if (action === "push-path") {
			const confirmation = await requestConfirmation({
				title: "Push Files",
				message: `This will deploy ${payload.pushScopeLabel} back to ${site.remoteSsh}.`,
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
	appendPending(label, actionPendingMessage(action, site, payload), { qa: isQaRun, revealActivity: !isQaRun });
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
				setActiveTab(siteDefaultTab, "sites");
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
els.createBackupSiteButton?.addEventListener("click", () => {
	createSiteFromBackup().catch((error) => appendMessage(error.message, true));
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
els.backupRestoreForm?.addEventListener("input", renderAddSiteWizard);
els.chooseUpdraftFilesButton?.addEventListener("click", () => els.updraftFileInput?.click());
els.updraftFileInput?.addEventListener("change", () => {
	uploadUpdraftFiles(els.updraftFileInput.files).catch((error) => appendMessage(error.message, true));
	els.updraftFileInput.value = "";
});
if (els.updraftDropzone) {
	["dragenter", "dragover"].forEach((eventName) => {
		els.updraftDropzone.addEventListener(eventName, (event) => {
			event.preventDefault();
			els.updraftDropzone.classList.add("dragging");
		});
	});
	["dragleave", "drop"].forEach((eventName) => {
		els.updraftDropzone.addEventListener(eventName, (event) => {
			event.preventDefault();
			if (eventName === "drop") {
				uploadUpdraftFiles(event.dataTransfer.files).catch((error) => appendMessage(error.message, true));
			}
			els.updraftDropzone.classList.remove("dragging");
		});
	});
}
els.listAwsBackupsButton?.addEventListener("click", () => {
	listAwsBackups().catch((error) => appendMessage(error.message, true));
});
els.awsBackupForm?.addEventListener("submit", (event) => {
	event.preventDefault();
	listAwsBackups().catch((error) => appendMessage(error.message, true));
});
[
	els.backupRestoreFiles,
	els.backupIncludeUploads,
	els.backupRestoreDb,
].forEach((control) => {
	control?.addEventListener("input", renderBackupRestoreOptions);
	control?.addEventListener("change", renderBackupRestoreOptions);
});
els.saveSiteButton.addEventListener("click", saveSite);
els.siteForm?.elements.phpVersion?.addEventListener("change", () => {
	const site = currentSite();
	if (site) {
		renderPhpRuntimeStatus({
			...site,
			phpVersion: els.siteForm.elements.phpVersion.value,
		});
	}
});
els.openLocalButton.addEventListener("click", () => runAction("open-local"));
els.openAdminButton.addEventListener("click", () => runAction("admin-login"));
els.pullFileScope?.addEventListener("change", () => {
	renderPullScopeControls();
	renderPullSummary(currentSite());
});
els.pullCustomPath?.addEventListener("input", () => {
	renderPullSummary(currentSite());
});
els.pushFileScope?.addEventListener("change", () => {
	renderPushScopeControls();
	renderPushSummary(currentSite());
});
els.pushPath?.addEventListener("input", () => {
	renderPushSummary(currentSite());
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
els.saveAwsCredentialButton?.addEventListener("click", saveAwsCredential);
els.wpEngineDiscoveryForm.addEventListener("submit", (event) => {
	event.preventDefault();
	listWpEngineSites();
});
els.siteGroundRegistryForm.addEventListener("submit", (event) => {
	event.preventDefault();
	saveSiteGroundSite();
});
els.awsCredentialForm?.addEventListener("submit", (event) => {
	event.preventDefault();
	saveAwsCredential();
});
els.clearOutputButton.addEventListener("click", () => {
	els.outputConsole.textContent = "";
	els.outputConsole.className = "";
	if (els.siteActivityConsole) {
		els.siteActivityConsole.textContent = "";
		els.siteActivityConsole.className = "";
	}
	setSiteActivityStatus();
});
els.clearSiteActivityButton?.addEventListener("click", () => {
	els.siteActivityConsole.textContent = "";
	els.siteActivityConsole.className = "";
	setSiteActivityStatus();
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

setupButtonTooltips();
applyTheme(window.localStorage.getItem("mrn-local-hub-theme") || "light");
updateProviderHint();
refresh().then(() => {
	if (!state.metricsTimer) {
		state.metricsTimer = window.setInterval(() => {
			refreshMetrics().catch(() => {});
		}, 5000);
	}
}).catch((error) => appendMessage(error.message, true));
