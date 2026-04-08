# Releases

This folder contains built artifacts only.

- `plugins/`
  - Fresh zip builds from `../plugins`
- `mu-plugins/`
  - Fresh zip builds from `../mu-plugins`
- `stack/`
  - Fresh stack/theme release artifacts
- `clone/`
  - Fresh clone-tool release artifacts

These files are disposable build output and stay out of version control.

Build fresh plugin, MU plugin, and stack theme zip artifacts with:

```bash
/Users/khofmeyer/Development/MRN/stack/scripts/build-release-zips.sh all
```

Do not edit files here as source.
