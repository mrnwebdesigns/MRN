# MRN Local Hub Moved

MRN Local Hub now lives in its own repository:

```text
/Users/khofmeyer/Development/MRN-local-hub
```

The MRN stack repo no longer carries the Hub app source. This keeps the stack repo focused on WordPress stack, theme, plugin, and deploy workflow code.

## Start The Hub

From the standalone repo:

```bash
cd /Users/khofmeyer/Development/MRN-local-hub
npm start
```

Or through the MRN wrapper:

```bash
mrn local-hub
```

The wrapper defaults to the sibling `../MRN-local-hub/server.js` path. Override it when needed:

```bash
MRN_LOCAL_HUB_HOME=/path/to/MRN-local-hub mrn local-hub
MRN_LOCAL_HUB_SERVER=/path/to/MRN-local-hub/server.js mrn local-hub
```

Runtime state, generated certificates, backup staging, and app settings belong outside this repo, under the standalone Hub defaults such as `~/.mrn-local-hub/` and `~/Development/MRN-sites/`.
