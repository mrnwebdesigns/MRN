#!/usr/bin/env node
"use strict";

const fs = require("fs");
const http = require("http");
const https = require("https");

const defaults = {
	cert: "/Users/khofmeyer/Development/MRN/.tmp/local-hub/runtime/certs/mrn-localhost.pem",
	key: "/Users/khofmeyer/Development/MRN/.tmp/local-hub/runtime/certs/mrn-localhost-key.pem",
	targetHost: "127.0.0.1",
	targetPort: 8088,
	hubHostname: "thehub.localhost",
	hubTargetHost: "127.0.0.1",
	hubTargetPort: 5678,
	httpPort: 80,
	httpsPort: 443,
};

function argValue(name, fallback) {
	const index = process.argv.indexOf(`--${name}`);
	if (index === -1 || index + 1 >= process.argv.length) {
		return fallback;
	}
	return process.argv[index + 1];
}

const config = {
	cert: argValue("cert", defaults.cert),
	key: argValue("key", defaults.key),
	targetHost: argValue("target-host", defaults.targetHost),
	targetPort: Number(argValue("target-port", String(defaults.targetPort))),
	hubHostname: argValue("hub-hostname", defaults.hubHostname),
	hubTargetHost: argValue("hub-target-host", defaults.hubTargetHost),
	hubTargetPort: Number(argValue("hub-target-port", String(defaults.hubTargetPort))),
	httpPort: Number(argValue("http-port", String(defaults.httpPort))),
	httpsPort: Number(argValue("https-port", String(defaults.httpsPort))),
};

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

function hostWithPort(hostHeader, portNumber) {
	const hostname = stripHostPort(hostHeader) || "localhost";
	return portNumber === 443 ? hostname : `${hostname}:${portNumber}`;
}

function proxyTargetForHost(hostname) {
	if (hostname === config.hubHostname) {
		return {
			label: "MRN Local Hub",
			host: config.hubTargetHost,
			port: config.hubTargetPort,
		};
	}
	return {
		label: "OpenLiteSpeed",
		host: config.targetHost,
		port: config.targetPort,
	};
}

function proxyRequest(req, res) {
	if (req.url === "/__mrn-local-health") {
		res.writeHead(200, { "content-type": "application/json; charset=utf-8", "cache-control": "no-store" });
		res.end(JSON.stringify({
			ok: true,
			service: "mrn-local-friendly-proxy",
			target: `http://${config.targetHost}:${config.targetPort}`,
			hub: `https://${config.hubHostname}`,
			hubTarget: `http://${config.hubTargetHost}:${config.hubTargetPort}`,
			now: new Date().toISOString(),
		}));
		return;
	}

	const originalHost = stripHostPort(req.headers.host) || "localhost";
	const target = proxyTargetForHost(originalHost);
	const headers = {
		...req.headers,
		host: originalHost,
		"x-forwarded-host": originalHost,
		"x-forwarded-port": String(config.httpsPort),
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
		res.end(`MRN Local friendly proxy could not reach ${target.label} on ${target.host}:${target.port}.\n${error.message}\n`);
	});

	req.pipe(proxyReq);
}

function createHttpRedirectServer() {
	return http.createServer((req, res) => {
		const location = `https://${hostWithPort(req.headers.host, config.httpsPort)}${req.url || "/"}`;
		res.writeHead(308, { location, "cache-control": "no-store" });
		res.end(`Redirecting to ${location}\n`);
	});
}

function createHttpsProxyServer() {
	return https.createServer(
		{
			cert: fs.readFileSync(config.cert),
			key: fs.readFileSync(config.key),
		},
		proxyRequest,
	);
}

function listen(server, port, address) {
	return new Promise((resolve) => {
		server.on("error", (error) => {
			resolve({ ok: false, port, address, error: error.message, code: error.code || "" });
		});
		server.listen(port, address, () => {
			resolve({ ok: true, port, address });
		});
	});
}

async function main() {
	const servers = [];
	const addresses = ["127.0.0.1", "::1"];
	const httpResults = [];
	const httpsResults = [];

	for (const address of addresses) {
		const httpServer = createHttpRedirectServer();
		const result = await listen(httpServer, config.httpPort, address);
		httpResults.push(result);
		if (result.ok) {
			servers.push(httpServer);
		}
	}

	for (const address of addresses) {
		const httpsServer = createHttpsProxyServer();
		const result = await listen(httpsServer, config.httpsPort, address);
		httpsResults.push(result);
		if (result.ok) {
			servers.push(httpsServer);
		}
	}

	process.stdout.write(`${JSON.stringify({ service: "mrn-local-friendly-proxy", httpResults, httpsResults }, null, 2)}\n`);
	if (!httpsResults.some((result) => result.ok)) {
		process.stderr.write("No HTTPS listener started. Is another app using port 443?\n");
		process.exit(1);
	}

	const shutdown = () => {
		for (const server of servers) {
			server.close();
		}
		process.exit(0);
	};
	process.on("SIGTERM", shutdown);
	process.on("SIGINT", shutdown);
}

main().catch((error) => {
	process.stderr.write(`${error.stack || error.message}\n`);
	process.exit(1);
});
