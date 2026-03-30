# Stack Changelog

## 2026.03.29-theme-foundation
- Expanded `mrn-base-stack` to `1.0.2`.
- Added a source-controlled `Business Information` options page to the canonical theme.
- Added a source-controlled `Theme Header/Footer` options page to the canonical theme.
- Added starter header and footer rendering contracts backed by theme options, native menu locations, and business-information helpers.
- Added canonical business-logo priority and logo variants for header/footer usage.
- Added canonical business phone, text/SMS, address, weekday hours, and holiday hours data to the theme layer.
- Added theme-owned business JSON-LD output sourced from the canonical business-information contract.
- Added a curated developer handoff doc plus theme roadmap/tasklist docs for backend/frontend delivery.

## 2026.03.27-foundation
- Established a stack-wide release record with a current baseline file and changelog.
- Packaged and synced the current builder foundation baseline to the stack server.
- Updated `mrn-base-stack` to `1.0.1`.
- Updated `mrn-reusable-block-library` to `0.1.3`.
- Updated `mrn-site-colors` to `0.1.2` while presenting in admin as `Site Styles`.
- Kept `mrn-editor-tools` at packaged baseline `1.8.13`.
- Added a dedicated Hero field group above Content for posts and pages.
- Capped Hero rows at one while keeping layout-based hero growth possible later.
- Added a shared bottom-accent contract for theme layouts and reusable blocks.
- Added Site Styles graphic element storage and accent spacing overrides.
- Normalized reusable block editors so WYSIWYG/media support works on the intended block types.
- Cleaned the tracked duplicate nested files from `mrn-reusable-block-library`.
- Manually refreshed `default-configs.mrndev.io` from the corrected stack baseline.

## Format
- Use one release heading per stack baseline or rollout milestone.
- Keep entries short and outcome-focused.
- Record stack-wide changes here even when the detailed implementation history lives in `THREAD_MEMORY.md`.
