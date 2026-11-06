import test from 'node:test';
import assert from 'node:assert/strict';
import { receiptFromZip } from '../src/receipt-zip.mjs';
const fixture = Buffer.from('UEsDBBQAAAAIAGA8Tl2qxyeBHAAAABoAAAAMAAAAcmVjZWlwdC5qc29uq1YqSS0uUbJSSsovzUtJTVEoSk1OzSwoUaoFAFBLAQIUAxQAAAAIAGA8Tl2qxyeBHAAAABoAAAAMAAAAAAAAAAAAAACAAQAAAAByZWNlaXB0Lmpzb25QSwUGAAAAAAEAAQA6AAAARgAAAAAA', 'base64');
test('receipt archive is parsed in memory and rejects alternate entries, corruption and inflation claims', () => {
  assert.deepEqual(receiptFromZip(fixture), { test: 'bounded receipt' });
  const end = fixture.length - 22; const directory = fixture.readUInt32LE(end + 16);
  for (const alter of [b => b.writeUInt16LE(2, end + 10), b => b.writeUInt32LE(0xffffffff, directory + 24), b => b.writeUInt32LE(0, directory + 16),
    b => b.writeUInt16LE(1, directory + 8), b => b.writeUInt32LE(1, directory + 42), b => { b[directory + 46] = 46; }, b => { b[45] ^= 1; }]) {
    const bad = Buffer.from(fixture); alter(bad); assert.throws(() => receiptFromZip(bad));
  }
});
