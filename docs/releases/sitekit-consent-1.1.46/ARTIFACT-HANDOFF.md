# Preserve versioned Cookie Consent release artifacts

The dedicated Stack task identified that the registered 1.1.43 package path pointed at the moving `zip/mrn-cookie-consent.zip`, whose contents had advanced to 1.1.45. Keep this semantic repair when the 1.1.45 release draft or this Site Kit candidate is eventually rebased. A clean textual merge is insufficient if a registry entry again resolves to a different version's bytes.

## Verified reference state

Cookie Consent [PR #5](https://github.com/mrnwebdesigns/mrn-cookie-consent/pull/5) merged as `0752fd4e2899ffa86decee7da6a276e8c06f843c`. At that immutable commit, both ZIPs were fetched read-only and their SHA-256, size, archive integrity and plugin header were verified:

| Version | Repository path | Bytes | SHA-256 |
| --- | --- | ---: | --- |
| 1.1.43 | `zip/mrn-cookie-consent-1.1.43.zip` | 74,037 | `98a6703e6e2053ab60bdadc0506e174a72b60371a0b6736a01bf2ea13d731419` |
| 1.1.45 | `zip/mrn-cookie-consent.zip` (moving alias; not a durable release reference) | 62,161 | `59aa760ea99d34d9b5eb084247ce1c2ec650fb9ff6cc001a7bf1972c8fa498a5` |

MRN [PR #102](https://github.com/mrnwebdesigns/MRN/pull/102), inspected at `5a6c3096cc3bd2cbf57d37613b1553aa6b30441d`, changes the 1.1.43 package path in `stack/manifests/optional-plugin-releases.json` and both references in `stack/COOKIE_CONSENT_GTM_RELEASE_CANDIDATE.md` to the versioned 1.1.43 file. It also rebases the Fleet lock. That work remains owned by the dedicated Stack task.

## Requirements for the eventual PR #96 rebase

1. Recheck the merged state of PR #102 and the plugin's current main before resolving the release metadata. Do not restore the moving ZIP path from the older branch.
2. Retain `zip/mrn-cookie-consent-1.1.43.zip` with its exact existing bytes and checksum. Do not replace, rename away or delete it when packaging a later release.
3. Preserve the already-reviewed 1.1.45 bytes at a separate durable path, `zip/mrn-cookie-consent-1.1.45.zip`, in the plugin repository. Copy the exact approved artifact rather than rebuilding it from a newer checkout. Its expected size and SHA-256 are the 1.1.45 values above.
4. Make the 1.1.45 registry and release documentation refer to `/Users/khofmeyer/Development/MRN-plugins/mrn-cookie-consent/zip/mrn-cookie-consent-1.1.45.zip`, with matching version, source identity, size and checksum. Preserve the earlier release artifact even if the active registry entry advances.
5. Verify ZIP integrity, internal plugin version, exact checksum and registry path resolution after the rebase. A checksum for one version paired with another version's bytes is a release blocker. The unversioned ZIP can remain a convenience alias, but must not be the durable identity for a pinned release.
6. Keep MRN PR #96 and the 1.1.45 release under their existing draft/qualification gates. Rebase or packaging work does not waive the 24-hour observation, the unresolved Site Kit canary, safe mode, a fresh labeled remote backup or explicit deployment confirmation.

The Site Kit 1.1.46 candidate already has a versioned review ZIP (`mrn-cookie-consent-1.1.46.zip`, SHA-256 `238b071465b527b7120813e8afd8f8b2aef7e8a41693d571f6bef68de74c3788`). Its package and source commit remain unchanged. A future promotion must retain prior versioned packages and use a durable versioned 1.1.46 release path as well.

This handoff records a future release/rebase requirement only. It does not create or replace a plugin ZIP, rebase PR #96, merge PR #102, change the registry/lock, grant fleet readiness, or authorize a site mutation.
