import test from 'node:test';
import assert from 'node:assert/strict';
import { absoluteUrl } from '../src/contracts.mjs';
import { codePolicySchema } from '../src/code-qa.mjs';

test('absolute URL validation preserves exact signed bytes without trimming or normalization', () => {
  for (const value of ['https://EXAMPLE.invalid:443/a%2fb', 'https://example.invalid/a/../b',
    ' https://example.invalid/', 'https://example.invalid/\tpath', 'https://example.invalid/\npath',
    'https://example.invalid/a b', 'urn:mrn:fixture']) {
    assert.equal(absoluteUrl.parse(value), value);
  }
  for (const value of ['/relative', '//example.invalid/', 'https://', null, 1, {}])
    assert.equal(absoluteUrl.safeParse(value).success, false);
});

test('code policy exact-site constraints see the original URL, including whitespace', () => {
  const siteUrl = codePolicySchema.shape.profiles.element.shape.siteUrl;
  assert.equal(siteUrl.parse('https://fixture.example.invalid/'), 'https://fixture.example.invalid/');
  for (const value of [' https://fixture.example.invalid/', 'https://fixture.example.invalid/ ',
    'https://fixture.example.invalid/\n', 'https://fixture.example.invalid/\t'])
    assert.equal(siteUrl.safeParse(value).success, false);
});
