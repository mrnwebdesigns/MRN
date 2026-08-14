# MRN Environment Runtime

This must-use plugin exposes the deployment-managed WordPress environment contract without adding frontend queries, assets, or remote calls.

Infrastructure may set these constants in `wp-config.php`:

- `MRN_SITE_PROFILE`: `plain` or `stack`
- `MRN_WORKLOAD_CLASS`: `standard` or `dynamic`
- `MRN_PAGE_CACHE_POLICY`: `disabled` or the resolved workload cache mode
- `MRN_OBJECT_CACHE_POLICY`: `disabled`, `review_required`, or `enabled`
- `MRN_DEPLOY_CACHE_PURGE`: `object` or `all`
- `MRN_SEARCHWP_POLICY`: `disabled`, `frontend_only`, or `configured`
- `MRN_SEO_INDEXING_POLICY`: `disabled` or `configured`
- `MRN_IMPORT_TOOLS_POLICY`: `disabled` or `temporary`
- `MRN_ASSET_VERSION_SOURCE`: currently `commit_sha`
- `MRN_DEPLOYMENT_REF`: optional safe commit or release reference

`WP_ENVIRONMENT_TYPE` remains WordPress's canonical environment value. The plugin reports the resolved contract in Site Health. It warns administrators when SearchWP is active under a disabled policy, when SearchWP core is inactive under `frontend_only`/`configured`, or when an SEO indexing plugin is active where policy disables it.

`frontend_only` keeps SearchWP forms and frontend search available while deployment tooling pauses the indexer and removes scheduled indexing/telemetry work. The runtime reports this contract but remains read-only. Site Health expects the indexer to be paused under `frontend_only` and unpaused under `configured`; promotion tooling must set `configured`, unpause SearchWP, and rebuild the index before production release.

Site Health also reports PHP-FPM OPcache capacity, free memory, fullness, cached script count, and hit rate. An administrator notice appears when the shared cache is full. These checks run only in `wp-admin`; the public frontend still receives no database reads, remote requests, or assets from this runtime.

The plugin does not activate/deactivate plugins, change PHP configuration, clear provider caches, or perform deployments. Infrastructure remains responsible for capacity changes and reconciliation.
