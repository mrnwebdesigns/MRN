// Opt-in disposable profiler preload. The ordinary fixture never imports this.
import './existing-binding-network.mjs';
import { writeFileSync } from 'node:fs';
import { claimProfileRequest } from './access-profile-trigger.mjs';
const restrictedFetch = globalThis.fetch;
globalThis.fetch = async (input, options = {}) => {
  const selected = claimProfileRequest(new URL('.', import.meta.url), input, options, process.env.MAINWP_PROFILE_DIAGNOSTIC);
  const response = await restrictedFetch(input, selected);
  if (selected !== options) writeFileSync(new URL('./access-profile-response.json', import.meta.url), JSON.stringify({
    profile_header: /^\/dev\/shm\/mrn-access-profile\/cachegrind\.out\.[1-9][0-9]*$/.test(response.headers.get('x-xdebug-profile-filename') || ''),
    status: response.status,
  }), { mode: 0o600, flag: 'wx' });
  return response;
};
