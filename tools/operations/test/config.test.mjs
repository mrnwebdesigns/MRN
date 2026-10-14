import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync, rmSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { loadConfig } from '../src/config.mjs';
import { contentQaFixture } from './fixtures/content.mjs';

function fixture(t, modes = []) {
  const root = mkdtempSync(join(tmpdir(), 'mrn-config-'));
  t.after(() => rmSync(root, { recursive: true, force: true }));
  const config = JSON.parse(readFileSync(new URL('../config/service.example.json', import.meta.url), 'utf8'));
  delete config.recoveryEvidencePath;
  config.policyPath = join(root, 'permissions.json');
  config.stateDir = join(root, 'not-created');
  writeFileSync(config.policyPath, JSON.stringify({ version: 1, members: [] }));
  const content = contentQaFixture().policy;
  const code = { ...content, profiles: [{ siteUrl: 'https://example.test/', paths: ['wp-content/themes/fixture/style.css'],
    runtimeUrl: 'https://runtime.invalid', source: { mode: 'unversioned' } }] };
  if (modes.length) {
    config.auth.verifiedEmailClaims = true;
    config.auth.googleWorkspace = { connectionId: 'con_fixture' };
    config.delegation = { keyId: 'fixture', privateKeyPath: join(root, 'unread-identity-key') };
    config.github = { appId: '1', installationId: 1, privateKeyPath: join(root, 'unread-app-key'), repositories: { [code.repository]: 1 } };
  }
  for (const mode of modes) {
    const field = mode === 'content' ? 'contentEdits' : mode;
    config[field] = { policyPath: join(root, `${mode}.json`) };
    writeFileSync(config[field].policyPath, JSON.stringify(mode === 'content' ? content : code));
  }
  const file = join(root, 'service.json');
  const save = () => writeFileSync(file, JSON.stringify(config));
  save();
  const run = (entry = 'check-config.mjs') => {
    save();
    return spawnSync(process.execPath, [new URL(`../src/${entry}`, import.meta.url).pathname], {
      env: { ...process.env, MRN_OPERATIONS_CONFIG: file }, encoding: 'utf8', timeout: 10000,
    });
  };
  return { config, code, content, file, save, run };
}

for (const modes of [[], ['code'], ['content'], ['code', 'content']]) {
  test(`configuration preflight validates ${modes.join(' and ') || 'legacy read-only'} policies without credentials or runtime writes`, t => {
    const f = fixture(t, modes), result = f.run();
    assert.equal(result.status, 0, result.stderr);
    const report = JSON.parse(result.stdout), loaded = loadConfig(f.file);
    assert.equal(report.valid, true); assert.equal(report.hostingVerified, false);
    assert.equal(report.codeProfiles, modes.includes('code') ? 1 : 0);
    assert.equal(report.contentProfiles, modes.includes('content') ? 1 : 0);
    assert.deepEqual(loaded.workflowPolicies.code, modes.includes('code') ? f.code : null);
    assert.deepEqual(loaded.workflowPolicies.content, modes.includes('content') ? f.content : null);
    assert.equal(existsSync(f.config.stateDir), false);
    assert.doesNotMatch(result.stdout + result.stderr, /example\.test|runtime\.invalid|publicKeyBase64|unread-app-key/);
  });
}

for (const field of ['code', 'contentEdits']) {
  for (const failure of ['missing', 'invalid-json', 'invalid-schema', 'wrong-policy']) {
    test(`preflight and startup reject ${field} ${failure} before creating state`, t => {
      const f = fixture(t, ['code', 'content']);
      const sentinel = 'PRIVATE-CONFIG-SENTINEL';
      if (failure === 'missing') f.config[field].policyPath += sentinel;
      else writeFileSync(f.config[field].policyPath, failure === 'invalid-json' ? sentinel
        : JSON.stringify(failure === 'wrong-policy' ? (field === 'code' ? f.content : f.code) : { ...f[field === 'code' ? 'code' : 'content'], profiles: sentinel }));
      for (const entry of ['check-config.mjs', 'main.mjs']) {
        const result = f.run(entry);
        assert.equal(result.status, 1); assert.equal(result.stdout, '');
        assert.doesNotMatch(result.stderr, new RegExp(`${sentinel}|example\\.test|publicKeyBase64`));
        assert.equal(existsSync(f.config.stateDir), false);
      }
      assert.throws(() => loadConfig(f.file));
    });
  }
}

test('configured QA repositories require explicit central App repository pins', t => {
  for (const mode of ['code', 'content']) {
    const f = fixture(t, [mode]);
    f.config.github.repositories = { 'mrnwebdesigns/unrelated': 2 };
    assert.equal(f.run().status, 1);
    assert.throws(() => loadConfig(f.file), { code: 'GITHUB_CONFIG' });
  }
});

test('Git profiles require a central source repository pin; snapshot profiles need no source repository', t => {
  const f = fixture(t, ['code']);
  assert.equal(f.run().status, 0);
  f.code.profiles[0].source = { mode: 'git', repository: 'https://github.com/mrnwebdesigns/company-fixture',
    branch: 'main', paths: { 'wp-content/themes/fixture/': 'theme/' } };
  writeFileSync(f.config.code.policyPath, JSON.stringify(f.code));
  assert.equal(f.run().status, 1);
  assert.throws(() => loadConfig(f.file), { code: 'GITHUB_CONFIG' });
  f.config.github.repositories['mrnwebdesigns/company-fixture'] = 2;
  assert.equal(f.run().status, 0);
});
