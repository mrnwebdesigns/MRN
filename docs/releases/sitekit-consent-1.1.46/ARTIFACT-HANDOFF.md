# Cookie Consent immutable release artifacts

The moving `zip/mrn-cookie-consent.zip` alias is never a durable Fleet identity.
Each approved release keeps its exact bytes at a versioned path, and Stack/Fleet
manifests must reference that versioned file.

## Verified current state

Cookie Consent PR #4 merged the Site Kit integration and artifact repair to
plugin `main` as `e3e1737f1db325f3076025c341af55a1cd7318bc`. The 1.1.46
package was built twice from clean release-source commit
`9d9af85d05bce7aabd35112ee9d9dd99af435219`; both builds were
byte-identical.

| Version | Repository path | Bytes | SHA-256 |
| --- | --- | ---: | --- |
| 1.1.43 | `zip/mrn-cookie-consent-1.1.43.zip` | 74,037 | `98a6703e6e2053ab60bdadc0506e174a72b60371a0b6736a01bf2ea13d731419` |
| 1.1.45 | `zip/mrn-cookie-consent-1.1.45.zip` | 62,161 | `59aa760ea99d34d9b5eb084247ce1c2ec650fb9ff6cc001a7bf1972c8fa498a5` |
| 1.1.46 | `zip/mrn-cookie-consent-1.1.46.zip` | 67,706 | `1f7604ab913ef4468813270e7a4701845169725f754a4cdf7d946c3959c50275` |
| Latest alias | `zip/mrn-cookie-consent.zip` | 67,706 | `1f7604ab913ef4468813270e7a4701845169725f754a4cdf7d946c3959c50275` |

ZIP integrity, internal plugin versions, release-source ancestry, and alias
byte identity were verified. MRN PR #102 already points the registered 1.1.43
release at its versioned package.

## Promotion rule

When the 1.1.46 canary is separately authorized and passes, advance the
optional-plugin registry and component catalog directly to the versioned
1.1.46 path. Do not merge MRN PR #96 as the current release: its 1.1.45
metadata is superseded, although the 1.1.45 package remains available for the
existing canary and rollback.

Artifact completion does not authorize a child-site write, waive safe mode,
replace the fresh labeled remote database backup, or satisfy the Tharrington
Smith live canary. The optional-plugin policy remains upgrade-only: update
sites where Cookie Consent is already installed, and never install it on an
absent site without separate authorization.
