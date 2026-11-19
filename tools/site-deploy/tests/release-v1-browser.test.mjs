/** Browser/resource proof against disposable loopback pages with no client target. */
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { once } from 'node:events';
import { createServer } from 'node:http';
import { performance } from 'node:perf_hooks';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

const proofRoot = process.env.MRN_RELEASE_TEST_PLAYWRIGHT_ROOT;
const script = fileURLToPath(new URL('../release_v1/examples/header-proof.mjs', import.meta.url));
const release = '5'.repeat(64);

test('paced browser resources, provider 429, and clean retry', { skip: !proofRoot, timeout: 45_000 }, async () => {
  let limited = true;
  const requests = [];
  const server = createServer((req, res) => {
    requests.push([req.url, performance.now()]);
    if (req.url === '/') {
      res.writeHead(200, { 'Content-Type': 'text/html', 'X-MRN-Site-Release': release });
      res.end('<link rel="stylesheet" href="/hero.css"><script src="/hero.js"></script><h1>Fixture</h1>');
    } else if (req.url === '/hero.css' && limited) {
      res.writeHead(429, { 'Retry-After': '300' });
      res.end('Provider rate limit');
    } else {
      res.writeHead(200, { 'Content-Type': req.url === '/hero.css' ? 'text/css' : 'application/javascript' });
      res.end(req.url === '/hero.css' ? 'h1 { color: red; }' : 'window.fixture = true;');
    }
  });
  try {
    server.listen(0, '127.0.0.1');
    await once(server, 'listening');
    const urls = [`http://127.0.0.1:${server.address().port}/`];
    const run = async () => {
      const processArgs = [script];
      const child = spawn(process.execPath, processArgs, { env: { ...process.env, MRN_QA_ENGINE_ROOT: proofRoot } });
      let output = '', error = '';
      child.stdout.on('data', data => { output += data; });
      child.stderr.on('data', data => { error += data; });
      child.stdin.end(JSON.stringify({ release_id: release, urls }));
      const [code] = await once(child, 'close');
      assert.equal(code, 0, error);
      return JSON.parse(output);
    };

    const throttled = await run();
    assert.equal(throttled.passed, false);
    assert.deepEqual(throttled.throttle, { status: 429, retry_after: '300' });
    assert.deepEqual(requests.map(([path]) => path), ['/', '/hero.css']);
    requests.length = 0;
    limited = false;
    const proof = await run();
    assert.deepEqual(proof, { passed: true, release_id: release, urls });
    assert.deepEqual(requests.slice(0, 3).map(([path]) => path), ['/', '/hero.css', '/hero.js']);
    for (let index = 1; index < requests.length; index++) {
      assert.ok(requests[index][1] - requests[index - 1][1] >= 200, 'same-origin activity was not paced');
    }
  } finally {
    server.closeAllConnections();
    if (server.listening) await new Promise(resolve => server.close(resolve));
  }
});
