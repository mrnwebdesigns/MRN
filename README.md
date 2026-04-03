# MRN Workspace

This is the reorganized MRN development workspace.

## Source Of Truth

- `plugins/`
  - Normal WordPress plugin source.
  - Each plugin should live in its own folder and, where possible, its own git repo.
- `mu-plugins/`
  - Shared MU plugin source used by stack and non-stack sites.
  - This is the source of truth for MRN MU plugins.
- `shared/`
  - Shared source files intentionally copied into more than one plugin.
  - Current example: sticky settings toolbar shared helper.
- `stack/`
  - Stack-specific orchestration only.
  - Manifests, starter themes, exports/configs, scripts, and stack-facing MU wrappers live here.
- `clone/`
  - Site cloning and import/export tooling.
- `server/`
  - Server-side helpers, deployment support, and ops scripts.
- `releases/`
  - Built artifacts only.
  - Do not treat this folder as source.

## Important Rules

- Do not edit installed plugin copies in `wp-content/plugins` or `wp-content/mu-plugins` unless it is an emergency.
- Build and edit from this workspace, then symlink local sites back to these source folders.
- `mu-plugins/` is canonical for MU plugin source.
- `stack/mu-plugins/` is a stack-facing wrapper layer and may contain loader files, root stubs, or symlinks back to `mu-plugins/`.
- Zips in `releases/` should be rebuilt from source, not used as the source of truth.

## QA Toolkit

- Repo QA scripts live in:
  - `/Users/khofmeyer/Development/MRN/stack/scripts`
- QA reference doc:
  - `/Users/khofmeyer/Development/MRN/stack/QA.md`

## Current Layout Notes

- `stack/mu-plugins/mrn-loader.php` currently uses an explicit allowlist of MU plugin entry files.
- Reusable block and page/post builder work remains split by responsibility:
  - MU/plugin layer owns reusable block data, field groups, and render helpers.
  - Theme layer owns page/post content builder rendering.
