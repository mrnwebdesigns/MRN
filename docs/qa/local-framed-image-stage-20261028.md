# Local native framed image stage — October 28

Local continuation after Stack **25bc5404** and site **71a2f87**, with shared
Stack work in this chat explicitly authorized. No push, release/version/pin/
catalog/package change, publication, managed installation or remote mutation.

Optional **Framed Viewport Zoom** preserves an image's original responsive
proportions and clips at the outer row. Media height includes native row padding;
the figure reserves the remainder, keeping all outer spacing in row presets.
Existing content-frame controls provide width and responsive inner gutters.
The selected profile consumes native padding once and removes the empty text
shell even without custom inner controls. Site-specific spacing is not authored
in CSS. Standard/Scroll Zoom/Rotated Gallery behavior stays unchanged when the
new mode and optional 767px media breakpoint are unset; 999px remains the default.

The mode exposes a bounded integer viewport inset (0–240px; blank/default zero).
The existing presentation scheduler expands the image to viewport width minus
that total inset, then returns along cubic half-curves. Invalid inputs cannot
emit styles or attributes; native field keys remain isolated between root and
nested rows. The effect resets immediately for initial/live reduced motion and
handles responsive/zero-width media. No new library, timer, font, route, handler
or site-specific frontend copy is added.

**21 native assertions**, **32 saved-source assertions**, both image-motion JS
regressions, report regressions and **32 converter groups / 49 removed fixtures**
pass. All owned drafts are removed; all 63 authored source/native states remain
exact. The site mapper retains selected media, alternative text, visibility and
source labels in editable native Image fields and refuses unreviewed content.

Source/native comparisons cover 1280/1024/768/767/390px. Desktop stage geometry,
image ratio and settled zoom curve match. Native phone presets expand 121px
image width to 313px. Full-page/motion/Classic Editor acceptance remains open:
the legacy GSAP effect uses a 1.2s scrub catch-up, while native updates on scroll
frames. Identical settled geometry does not certify transient timing. The legacy
768px footer overflow and narrow subsequent phone copy remain outside this row.
Installed parent stays 1.6.9; actual authored conversion stays 1/63. The assessed
boxed-image capability gap is cleared; no authored record is converted here.

Final standard site-only MRN QA passes for both isolated task repositories:
PHP lint, security/WPCS, compatibility, theme/security, public browser, REST
HTTP 200, homepage/preview accessibility and unchanged page-timing thresholds.
Stack Semgrep and Composer audit pass. PHPStan retains **34 Stack / 38 site
advisory findings**, so static analysis is not clean; changed source scope also
prevents direct comparison with the previous batch's counts. Contrast alone is
disabled by owner instruction with the same WCAG tags. No regions/selectors,
other rules or performance thresholds are excluded or relaxed. Final preview
CWV is good: LCP **72 / 68ms**, CLS **0.001968**, INP **8 / 16ms**. Homepage
samples are good (LCP 544 / 508ms, CLS 0.005146, INP 8 / 16ms). Earlier retained
performance failures remain part of full-site acceptance. Remote parity,
license/rollout and deploy checks are excluded by the explicit local scope.
Inherited legacy ACF spacing warnings and the duplicate mail-constant warning
remain separate from this native field/renderer proof.
