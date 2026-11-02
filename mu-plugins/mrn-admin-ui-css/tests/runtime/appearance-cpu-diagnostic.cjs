'use strict';

// Browser-only attribution: never installs a baseline or changes user preferences.
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');
const req = require('node:module').createRequire('/Users/khofmeyer/Development/MRN-qa-engine/package.json');
const { chromium } = req('@playwright/test');
const origin = 'https://admin-editor-pilot.localhost';
const output = path.resolve('outputs/admin-appearance');
const source = path.resolve('mu-plugins/mrn-admin-ui-css');
const runtime = '/Users/khofmeyer/Development/MRN-sites/admin-editor-pilot/public/wp-content/mu-plugins/mrn-admin-ui-css';
const files = ['mrn-admin-ui-css.php', 'modern-admin.php', 'appearance.php', 'appearance.js', 'mrn-admin-tokens.css', 'mrn-admin-shell.css', 'mrn-modern-admin.css', 'mrn-editor-workspace.css'];
const hash = file => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const digest = () => files.map(file => ({ file, source: hash(path.join(source, file)), installed: hash(path.join(runtime, file)) }));

function memory(label) {
  const program = `import json,pathlib,subprocess
p=pathlib.Path('/proc')/subprocess.check_output(['systemctl','show','lima-guestagent','-p','MainPID','--value'],text=True).strip()
s=dict(line.split(':',1) for line in (p/'status').read_text().splitlines() if ':' in line)
m=dict(line.split(':',1) for line in pathlib.Path('/proc/meminfo').read_text().splitlines())
print(json.dumps({'agent_pid':int(p.name),'agent_descriptors':len(list((p/'fd').iterdir())),'agent_rss_kib':int(s['VmRSS'].split()[0]),'available_kib':int(m['MemAvailable'].split()[0]),'swap_free_kib':int(m['SwapFree'].split()[0]),'load':pathlib.Path('/proc/loadavg').read_text().split()[:3],'pressure':pathlib.Path('/proc/pressure/memory').read_text().strip()}))`;
  const result = JSON.parse(execFileSync('limactl', ['shell', 'mrn-openlitespeed', '--', 'sudo', 'python3', '-'], { input: program, encoding: 'utf8', timeout: 30000 }));
  if (result.available_kib < 1500000) throw Error('Stop: VM available memory below 1.5 GB');
  return { label, checkedAt: new Date().toISOString(), ...result };
}

function aggregateProfile(profile) {
  const nodes = new Map(profile.nodes.map(node => [node.id, node]));
  const totals = new Map();
  for (let i = 0; i < (profile.samples || []).length; i++) {
    const frame = nodes.get(profile.samples[i])?.callFrame;
    if (!frame) continue;
    let group = frame.url || '(' + (frame.functionName || 'native') + ')';
    if (frame.url) {
      try { group = new URL(frame.url).pathname; } catch { group = '(browser-internal)'; }
    }
    totals.set(group, (totals.get(group) || 0) + (profile.timeDeltas?.[i] || 0));
  }
  return [...totals].map(([script, microseconds]) => ({ script, sampled_self_ms: microseconds / 1000 }))
    .sort((a, b) => b.sampled_self_ms - a.sampled_self_ms);
}

(async () => {
  fs.mkdirSync(output, { recursive: true });
  const before = digest();
  if (before.some(row => row.source !== row.installed)) throw Error('Installed source differs from task source');
  const cookies = JSON.parse(fs.readFileSync('/Users/khofmeyer/Development/MRN-sites/admin-editor-pilot/logs/administrator-auth.json'));
  const records = [], snapshots = [memory('before')], browser = await chromium.launch();
  try {
    // Balanced current-candidate samples, plus two warm-ups and a JS-only ablation.
    const plan = [
      ['light', false, true], ['dark', false, true],
      ...['light', 'dark', 'dark', 'light', 'light', 'dark', 'dark', 'light'].map((mode, i) => [mode, false, false, i >= 6]),
      ...['light', 'dark', 'dark', 'light'].map(mode => [mode, true, false, false]),
    ];
    for (let index = 0; index < plan.length; index++) {
      const [mode, omitAppearanceJS, warmup, profileCPU] = plan[index];
      const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1600, height: 1100 }, colorScheme: mode });
      await context.addCookies(cookies);
      await context.route('**/*', async route => {
        const request = route.request(), url = new URL(request.url());
        if (url.origin !== origin) return route.abort();
        if (omitAppearanceJS && url.pathname.endsWith('/mrn-admin-ui-css/appearance.js')) {
          return route.fulfill({ status: 200, contentType: 'application/javascript', body: '/* diagnostic: appearance.js omitted in this browser only */' });
        }
        if (request.isNavigationRequest() && url.pathname === '/wp-admin/post.php') {
          const response = await route.fetch();
          const html = await response.text();
          if (!/data-mrn-appearance="(?:light|dark|system)"/.test(html)) throw Error('Authenticated candidate appearance marker missing');
          // In-memory response override before first paint; no server preference write.
          return route.fulfill({ response, body: html.replace(/data-mrn-appearance="(?:light|dark|system)"/, 'data-mrn-appearance="' + mode + '"') });
        }
        return route.continue();
      });
      const page = await context.newPage(), cdp = await context.newCDPSession(page);
      await cdp.send('Performance.enable');
      if (profileCPU) { await cdp.send('Profiler.enable'); await cdp.send('Profiler.start'); }
      const started = performance.now();
      const response = await page.goto(origin + '/wp-admin/post.php?post=2523&action=edit', { waitUntil: 'networkidle', timeout: 120000 });
      if (response.status() !== 200 || !page.url().startsWith(origin + '/wp-admin/post.php')) throw Error('Editor authentication/load failed');
      await page.waitForFunction(() => window.tinymce?.editors.length === 4 && tinymce.editors.every(editor => editor.initialized), null, { timeout: 60000 });
      await page.waitForFunction(() => document.body.classList.contains('mrn-editor-page-ready'), null, { timeout: 30000 });
      const wall = performance.now() - started;
      const metrics = (await cdp.send('Performance.getMetrics')).metrics;
      const metric = name => metrics.find(row => row.name === name)?.value;
      const profile = profileCPU ? aggregateProfile((await cdp.send('Profiler.stop')).profile) : undefined;
      const form = await page.evaluate(async () => {
        const fields = [...new FormData(document.querySelector('#post'))].filter(([name]) => name.startsWith('acf['));
        const bytes = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(JSON.stringify(fields)));
        return { acf_form_sha256: [...new Uint8Array(bytes)].map(n => n.toString(16).padStart(2, '0')).join(''), sections: document.querySelectorAll('.mrn-section-link').length, tinyMCE: tinymce.editors.length, appearance: document.documentElement.dataset.mrnAppearance };
      });
      if (form.sections !== 19 || form.tinyMCE !== 4 || form.appearance !== mode) throw Error('Editor initialization mismatch');
      await cdp.send('HeapProfiler.collectGarbage');
      const record = { sequence: index, mode, omitAppearanceJS, warmup, profiled: !!profileCPU, wall_ms: wall, cpu_ms: metric('TaskDuration') * 1000, script_ms: metric('ScriptDuration') * 1000, layout_ms: metric('LayoutDuration') * 1000, style_ms: metric('RecalcStyleDuration') * 1000, retained_heap_mb: (await cdp.send('Runtime.getHeapUsage')).usedSize / 1024 / 1024, ...form, profile };
      records.push(record);
      await context.close();
      if (index === 1 || index === 5 || index === 9 || index === 13) snapshots.push(memory('after sequence ' + index));
      console.log('CPU diagnostic', index + 1, mode, omitAppearanceJS ? 'appearance JS omitted' : 'full candidate', Math.round(record.cpu_ms) + 'ms');
    }
  } finally {
    await browser.close();
  }
  const after = digest();
  if (JSON.stringify(before) !== JSON.stringify(after)) throw Error('Source/runtime changed during diagnosis');
  if (new Set(records.map(row => row.acf_form_sha256)).size !== 1) throw Error('Native ACF form differs across diagnostic variants');
  fs.writeFileSync(path.join(output, 'cpu-diagnostic-20261102.json'), JSON.stringify({ checkedAt: new Date().toISOString(), candidate: 'c1f249f1', browser: browser.version(), protocol: 'Serial Chromium fresh contexts; two warm-ups; balanced Light/Dark samples; four initialized TinyMCE instances and 19 sections. Modes are in-memory HTML response overrides; last four runs omit appearance.js only in the browser. No installed baseline, account preference, content or settings changes. Profiled samples are diagnostic and excluded from timing comparisons. This is attribution, not prior/candidate release qualification.', installed_before: before, installed_after: after, snapshots, records }, null, 2) + '\n');
})().catch(error => { console.error(error.message); process.exitCode = 1; });
