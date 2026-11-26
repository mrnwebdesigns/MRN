import test from 'node:test';
import assert from 'node:assert/strict';
import { parseExistingBindingAccess } from './runtime/existing-binding-access.mjs';

const root = '/tmp/disposable-mainwp';
const fixture = () => ({ schema: 1, mode: 'disposable-existing-management-access-v2', production_credentials: false,
  production_activation: false, registrar_key_id: 'fixture-registrar', registrar_signer: `${root}/existing-binding-v2/registrar.der`,
  callback_path: '/internal/mainwp/management-existing-access-v2',
  callback_socket: `${root}/existing-binding-v2/callback-socket/callback.sock`, route_switch: `${root}/existing-binding-v2/switch-callback` });
const parse = (value, directory = root) => parseExistingBindingAccess(Buffer.from(JSON.stringify(value)), directory);

test('the disposable access sidecar admits only its separate fixed socket, signer and routing command', () => {
  assert.deepEqual(parse(fixture()), fixture());
  for (const key of Object.keys(fixture())) {
    const value = fixture(); delete value[key]; assert.throws(() => parse(value), key);
  }
  assert.throws(() => parse({ ...fixture(), owner_control: `${root}/owner` }));
  for (const [key, value] of [['schema', 2], ['mode', 'production'], ['production_credentials', true],
    ['production_activation', true], ['registrar_key_id', 'another-key'], ['callback_path', '/internal/mainwp/management-access-veto']])
    assert.throws(() => parse({ ...fixture(), [key]: value }), key);
});

test('traversal, substituted parent paths, excessive input and ambiguous fixture roots fail before any I/O', () => {
  for (const key of ['registrar_signer', 'callback_socket', 'route_switch']) for (const path of [
    '/etc/passwd', `${root}/existing-binding-v2/../identity.der`, fixture()[key] + '/extra', fixture()[key].replace('existing-binding-v2/', ''),
  ]) assert.throws(() => parse({ ...fixture(), [key]: path }), key);
  for (const directory of ['', 'relative', `${root}/../disposable-mainwp`, root + '/', undefined])
    assert.throws(() => parseExistingBindingAccess(Buffer.from(JSON.stringify(fixture())), directory));
  assert.throws(() => parseExistingBindingAccess(Buffer.alloc(4097), root));
  assert.throws(() => parseExistingBindingAccess(Buffer.from('{broken'), root));
});
