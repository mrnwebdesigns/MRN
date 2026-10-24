import { Registry } from '../../src/contracts.mjs';

// Disposable service configuration; never supplied by a callback or team tool.
export function accessRegistry(url) {
  const registryData = { version: 1, websites: [{ id: 'access-fixture', name: 'Disposable access site',
    environments: [{ name: 'production', url, management: 'mainwp', backup: 'updraft', sources: ['mainwp'] }] }] };
  const policy = { version: 1, members: [], emailDomains: [{ domain: 'mrnwebdesigns.com', enabled: true,
    portfolio: { sources: ['mainwp'], actions: ['read', 'content'] } }] };
  const registry = new Registry(() => registryData, () => policy);
  return { registry, registryData, policy };
}
