# MRN Template Inspector (Testing)

Standalone local-only WordPress plugin for template/CSS inspection and VS Code open actions.

## Features
- Admin bar "Template Inspector" menu
- Template tree for the active request
- Selection mode: click an element, map to template context and matched CSS files
- Stack spacing overlay: toggle browser-inspector-style margin/padding/content highlights with rendered values
- One-click open in VS Code via Local listener (`127.0.0.1:17777`)

## Notes
- Intended for local development/testing only.
- Requires the Local add-on opener service running on `127.0.0.1:17777`.
