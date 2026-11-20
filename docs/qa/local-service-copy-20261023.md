# Native list and quotation composition controls — October 23

Local unpublished continuation after `63e6ff0`; no version bump, managed parent
install, Fleet package, manifest update, publication or remote write.

Optional native Body Text fields govern list typography, before/after/item
spacing and paragraphs following lists. Existing responsive list columns,
reading order and marker controls remain in use. Plain lists remove inherited
horizontal margins. Body Text adds an optional 767px heading-spacing override
and 767px text breakpoint. Image adds maximum media width and a mobile maximum
height with its existing alignment preserved. Bounded Split accepts only numeric
percentage-minus-pixel expressions, limited to 0–100% and 0–200px. Existing
dimension, token and palette validation remains in force. Blank controls retain
previous behavior; all section spacing remains owned by row presets.

The site converter qualifies three service testimonials and two compact lists
on temporary native drafts. Exact raw rich text, headings, attribution, media,
image side and disabled selections survive native persistence/readback. Five
owned drafts are removed and all 63 authored source/native states stay exact.
60 new native and 56 saved-source checks pass, plus 152 existing quotation,
bounded Split, service composition and image-drift checks. Converter integration
passes 32 groups / 49 removed fixtures. Report, conditional asset, exclusive FAQ
and image-drift regressions pass.

Desktop/tablet list widths and typography match; native mobile row presets repair
inherited narrow legacy content. The two newly assessed remaining presentation
gaps are explicitly held: scroll-word color reveal on four rows and one adaptive
18-logo grid. Native timed fade is not an equivalent effect. Full-page spacing,
motion, editor acceptance and installed adoption remain required.

Per owner instruction, current runtime QA excludes only `color-contrast`; other
accessibility, WordPress/API/security and performance checks stay enabled. Final
QA results, exact receipts and hashes are retained by the site task in
`docs/stack-migration-2026-10-23/service-copy/` and explained in
`docs/stack-migration-2026-10-23/LOCAL_SERVICE_COPY.md`. This is task qualification,
not release approval or proof of a fully converted site.

Both full standard QA runs complete under `/bin/bash` after the installed
Homebrew Bash stalls in an internal here-document write. Configured PHP lint,
security/WPCS, compatibility, theme/security suites, public browser, REST and
accessibility checks pass; Stack Semgrep and Composer audit pass. PHPStan retains
64 site / 26 Stack advisory findings. Overall QA is Fail/Blocked by homepage
TTFB around 2.1 seconds. Blog preview CLS varies from 0.12440 to 0.00193; a legacy
Blog comparison also measures 0.12441. Current good CWV does not clear that
variance or the response-time failure. Only contrast is excluded, and no
performance threshold is relaxed. Remote parity and rollout checks are outside
this local-only task qualification.
