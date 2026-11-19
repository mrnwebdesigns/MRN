// Existing disposable Dashboard/Child read harness only; never a production hook.
import assert from 'node:assert/strict';
import { createHash, randomUUID } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { delegationCanonical } from '../../src/delegation.mjs';
import { payload } from '../../src/mainwp.mjs';

const website = 'https://child.fixture.example.invalid';
function flatten(definitions) {
  const out = [];
  const walk = (field, path, kind) => {
    const { sub_fields, layouts, ...definition } = field;
    out.push({ ordinal: out.length, kind, path, definition, sub_fields: sub_fields?.length ?? null, layouts: layouts?.length ?? null });
    sub_fields?.forEach((item, i) => walk(item, [...path, 'sub_fields', i], 'field'));
    layouts?.forEach((item, i) => walk(item, [...path, 'layouts', i], 'layout'));
  };
  definitions.forEach((field, i) => walk(field, [i], 'field'));
  return out;
}
export async function checkContentInspection({ privateDirectory, runtimeCli, token, call, toolResult, network, store, report, pass, service }) {
  const manifest = JSON.parse(readFileSync(join(privateDirectory, 'content-inspection-seed.json'), 'utf8'));
  assert.equal(manifest.acf, '6.8.7'); assert.equal(manifest.production_data_used, false);
  assert.equal(manifest.native_clone_key_registered, true);
  const alice = await token('111111'), bob = await token('222222');
  const input = id => ({ website, kind: 'pages', recordId: id });
  const request = (bearer, args) => call(bearer, 'tools/call', { name: 'read_website_content', arguments: args });
  // Unchanged frozen reader must still refuse both original failure fixtures.
  // This comparison uses the same signed MainWP transport and fixture identity.
  const person = { subject: 'google-oauth2|111111', googleSubject: '111111', verifiedEmail: 'alice@mrnwebdesigns.com',
    identityProvider: 'google-oauth2', googleConnection: 'con_RuntimeFixture', hostedDomain: 'mrnwebdesigns.com',
    scopes: ['mrn:operations'], expiresAt: Date.now() + 600000 };
  const legacy = await service.connect(person);
  try {
    await legacy.verifyStaff();
    for (const name of ['clone', 'bounds']) {
      const before = network().length;
      await assert.rejects(() => legacy.callTool({ name: 'mrn_mainwp__read_content_record_v1', arguments: {
        site_id: JSON.parse(readFileSync(join(privateDirectory, 'native-site.json'), 'utf8')).site_id,
        site_url: website, post_type: 'page', post_id: manifest.records[name] } }).then(payload));
      assert.ok(network().slice(before).some(row => row.ability === 'mrn-mainwp/read-content-record-v1' && row.errorCode === 'mrn_content_fields'));
    }
  } finally { await legacy.close(); }
  pass('Both original page-20 failures reproduce through unchanged signed legacy reads');
  const cases = [];
  for (const name of ['clone', 'bounds', 'combined']) {
    const expected = manifest.expected[name];
    const all = []; let cursor, inspectionId, snapshot, values, count = 0;
    const receiptIds = [];
    do {
      const before = network().length;
      const result = toolResult(await request(alice, { ...input(expected.record_id), definitionLimit: 50,
        ...(cursor ? { cursor, inspectionId } : {}) }));
      assert.ok(Buffer.byteLength(JSON.stringify(result)) <= 196608);
      assert.equal(result.inspection.offset, all.length);
      assert.equal(result.inspection.totalDefinitions, expected.entries.length);
      assert.equal(result.inspection.rootCount, expected.root_count);
      assert.equal(result.inspection.definitionsSha256, expected.definitions_sha256);
      if (snapshot) assert.equal(result.inspection.snapshotSha256, snapshot);
      if (values) assert.deepEqual(result.acf.values, values);
      snapshot = result.inspection.snapshotSha256; values = result.acf.values;
      inspectionId = result.inspection.inspectionId;
      const entries = result.acf.definitionEntries ?? flatten(result.acf.definitions);
      assert.equal(entries.length, result.inspection.returned);
      assert.deepEqual(entries, expected.entries.slice(all.length, all.length + entries.length));
      all.push(...entries); cursor = result.inspection.nextCursor; count++;
      assert.ok(count <= expected.entries.length + 1);
      assert.equal(result.inspection.complete, count === 1 && cursor === null);
      if (cursor) assert.equal(result.acf.definitions, null);
      const native = network().slice(before).filter(row => row.ability === 'mrn-mainwp/read-content-inspection-v1' && row.status === 200);
      assert.equal(native.length, 1);
      const id = native[0].requestId;
      assert.equal(native[0].subject, person.subject);
      const receipt = JSON.parse(execFileSync(runtimeCli, ['option', 'get', 'mrn_delegation_audit_' + id, '--format=json'], { encoding: 'utf8', timeout: 15000 }));
      assert.equal(receipt.actor.oauth_subject, person.subject);
      assert.equal(receipt.state, 'returned'); assert.equal(receipt.ability, native[0].ability);
      const seq = Number(result.auditReceipt.split(':')[1]);
      const audit = store.db.prepare('SELECT subject,identity,outcome FROM audit WHERE seq=?').get(seq);
      assert.equal(audit.subject, person.subject); assert.equal(audit.outcome, 'returned');
      assert.ok(JSON.parse(audit.identity).providerReadIds.includes(id)); receiptIds.push(id);
    } while (cursor);
    assert.deepEqual(all, expected.entries);
    assert.equal(new Set(all.map(node => JSON.stringify(node.path))).size, all.length);
    const hash = createHash('sha256'); for (const node of all) hash.update(delegationCanonical(node) + '\n');
    assert.equal(hash.digest('hex'), expected.definitions_sha256);
    if (name === 'combined') {
      assert.ok(Buffer.byteLength(delegationCanonical(all)) > 1000000);
      assert.equal(all.length, 513);
      // The pre-existing native group applies to every page. Its six entries
      // are part of the complete manifest, in addition to the new 507.
      const existingKeys = ['field_mrn_native_rows', 'layout_mrn_native_row', 'field_mrn_native_group',
        'field_mrn_native_copy', 'field_mrn_native_keep', 'field_mrn_native_other'];
      assert.deepEqual(all.filter(node => existingKeys.includes(node.definition.key))
        .map(node => node.definition.key), existingKeys);
      const rows = values.field_sanitized_combined_rows;
      assert.equal(rows.length, 2);
      for (const [index, row] of rows.entries()) {
        assert.equal(row.acf_fc_layout, 'sanitized_row');
        assert.equal(row.after_content_field_sanitized_group.after_content_field_sanitized_text, `Sanitized copy ${index} é雪`);
        assert.equal(row.after_content_field_sanitized_group.after_content_field_sanitized_sibling, `Unchanged sibling ${index}`);
        for (let i = 0; i < 500; i++) assert.equal(row[`field_sanitized_reader_${i}`], `choice_${index + 1}`);
      }
      assert.equal(rows[1].acf_fc_layout_disabled, true);
      assert.equal(rows[1].acf_fc_layout_custom_label, 'Kept disabled custom label');
      assert.equal(values.field_sanitized_outside, 'Unchanged outside value');
    }
    cases.push({ name, pages: count, definitionEntries: all.length, definitionsSha256: expected.definitions_sha256,
      exactOrderAndCompleteness: true, independentProviderAuditRecords: receiptIds.length });
  }
  pass('Complete cloned and page-20-equivalent nested schemas retrieved in bounded pages without omissions, duplication or reordering');
  pass('Licensed Flexible Content values, disabled rows, custom labels and outside siblings independently verified');
  const fresh = toolResult(await request(alice, { ...input(manifest.records.combined), definitionLimit: 50 }));
  const follow = { ...input(manifest.records.combined), inspectionId: fresh.inspection.inspectionId,
    cursor: fresh.inspection.nextCursor, definitionLimit: 50 };
  for (const [bearer, args, nativeCode] of [
    [bob, follow, 'mrn_content_cursor'],
    [alice, { ...follow, inspectionId: randomUUID() }, null],
    [alice, { ...follow, recordId: manifest.records.bounds }, null],
    [alice, { ...follow, website: 'https://wrong.fixture.example.invalid' }, null],
    [alice, { ...follow, definitionLimit: 49 }, null],
    [alice, { ...follow, cursor: 'malformed' }, null],
    [alice, { ...follow, cursor: follow.cursor.slice(0, -1) + (follow.cursor.endsWith('0') ? '1' : '0') }, 'mrn_content_cursor'],
  ]) {
    const before = network().length;
    const rejected = await request(bearer, args);
    assert.ok(rejected.body.result?.isError || rejected.body.error);
    const packets = network().slice(before).filter(row => row.ability === 'mrn-mainwp/read-content-inspection-v1');
    if (nativeCode) assert.ok(packets.some(row => row.errorCode === nativeCode));
    else assert.equal(packets.length, 0);
  }
  pass('Cross-identity, cross-request, cross-record, cross-site, altered-limit and malformed/tampered cursors fail closed');
  const child = readFileSync(join(privateDirectory, 'inspection-child-container'), 'utf8').trim();
  assert.match(child, /^mrn-api-[0-9]+-[0-9]+-child-wp$/);
  try {
    execFileSync('docker', ['exec', child, 'php', '-r', 'if(file_put_contents("/tmp/mrn-inspection-stale-fixture","fixture-only")===false)exit(1);'], { timeout: 15000, stdio: 'pipe' });
    const before = network().length;
    const changed = await request(alice, follow);
    assert.ok(changed.body.result?.isError);
    assert.ok(network().slice(before).some(row => row.ability === 'mrn-mainwp/read-content-inspection-v1' && row.errorCode === 'mrn_content_stale'));
  } finally {
    execFileSync('docker', ['exec', child, 'php', '-r', 'if(is_file("/tmp/mrn-inspection-stale-fixture")&&!unlink("/tmp/mrn-inspection-stale-fixture"))exit(1);'], { timeout: 15000, stdio: 'pipe' });
  }
  pass('Actual changed registered schema rejects the previous cursor without WordPress content/meta mutation');
  assert.ok(network().every(row => row.method === 'GET' || /\/(get-delegation-challenge|get-team-access|read-delegated-operation)-v1\/run$/.test(row.path)));
  report.inspection = { contract: 'content-inspection-v1', cases, staleSchemaRejected: true,
    fullTraversalVerified: true, responseBytesMaximum: 196608, nodeLimit: 20000,
    productionDataUsed: false, guardedWritesQualified: false };
}
