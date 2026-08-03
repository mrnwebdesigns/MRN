# MRN Environment Runtime

This must-use plugin exposes the deployment-managed WordPress environment contract without adding frontend queries, assets, or remote calls.

Infrastructure may set these constants in `wp-config.php`:

- `MRN_SITE_PROFILE`: `plain` or `stack`
- `MRN_WORKLOAD_CLASS`: `standard` or `dynamic`
- `MRN_PAGE_CACHE_POLICY`: `disabled` or the resolved workload cache mode
- `MRN_OBJECT_CACHE_POLICY`: `disabled`, `review_required`, or `enabled`
- `MRN_DEPLOY_CACHE_PURGE`: `object` or `all`
- `MRN_SEARCHWP_POLICY`: `disabled` or `configured`
- `MRN_IMPORT_TOOLS_POLICY`: `disabled` or `temporary`
- `MRN_ASSET_VERSION_SOURCE`: currently `commit_sha`
- `MRN_DEPLOYMENT_REF`: optional safe commit or release reference

`WP_ENVIRONMENT_TYPE` remains WordPress's canonical environment value. The plugin reports the resolved contract in Site Health and warns administrators when SearchWP is active in an environment where policy disables it. It does not activate/deactivate plugins, clear provider caches, or perform deployments.
