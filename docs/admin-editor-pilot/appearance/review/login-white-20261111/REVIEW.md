# White MRN login treatment — 11 November 2026

Installed for review on Local Hub and `https://admin-editor-pilot.mrndev.io/site-login/`.
Source commit `583544e0`; review component version 3.3.7. Dark and System-dark use
the existing white knockout artwork from `stack/configs/exports/mrn-logo.svg`, copied
unchanged into the component. Light/System-light retain the existing AME logo.
AME still owns the link and dimensions; the override is limited to MRN-branded
login links, rather than recoloring all client branding. No form or authentication
code, account preference, editor behavior or JavaScript changed.

- [Dark desktop](installed-dark-light-login.png)
- [Unchanged Light desktop](installed-light-dark-login.png)
- [System-dark at 320px](installed-system-dark-320.png)
- [Dark recovery](installed-dark-light-recovery.png)

Pre-deploy candidate, installed Local Hub and installed review matrices each pass
8 login/recovery screens: explicit Light on a dark OS, explicit Dark on a light OS,
and both System states. System switches the logo live when the OS scheme changes.
Light logo screenshots remain byte-identical with previous CSS; MRN link and 90px
geometry remain intact. Desktop, 768px and 320px checks pass without overflow.
Axe WCAG A/AA/2.1AA reports zero violations in the scoped scans. No browser errors
or failed white-logo requests. Tests used isolated anonymous headless contexts;
no user session or internal browser tab was accessed.

The exact-staged MRN QA Engine gate passed. The static gate skips browser,
accessibility, performance and API runtime rows; runtime visual/axe checks above
supply the relevant coverage, and the review REST index returned HTTP 200.
Full editor CPU/save testing, authentication submission, Fleet/parity and release
qualification were not repeated for this CSS/asset-only change. Existing blockers
remain open. Source PHP change is only the version header.

The additional SVG is 933 bytes (482 bytes gzip), with 551 added CSS bytes and no
added JavaScript. Geometry is unchanged; no speedup or editor performance claim.
[Deployment receipt](deploy-receipt.txt) records the fresh verified remote DB backup,
installed checksums and private original-file rollback. Only login.css, the white
SVG and the versioned loader were installed remotely. Local Hub received only the
stylesheet and SVG, preserving its older loader and unrelated admin code.

[Review results](installed-results.json), [Local results](local-installed-results.json),
[candidate results](candidate-results.json), [source QA](source-qa.txt).
No production release, Fleet activation or Git push. Await visual review.
