# PreviewShare

PreviewShare (`previewshare`) is a required managed WordPress.org plugin on
MRN Stack sites. The bootstrap manifest pins the current qualified stable
release and activates it. Its own editor integrations support both Classic
Editor and Gutenberg; no MRN editor override is added.

## Site configuration

`site-bootstrap.sh` configures PreviewShare after activation through
`scripts/configure-previewshare.php`.

- `STACK_PREVIEWSHARE_DEFAULT_TTL_HOURS` defaults to `6`.
- `STACK_PREVIEWSHARE_POST_TYPES` defaults to `post,page`.
- A site may add its approved public, viewable custom post types, for example
  `STACK_PREVIEWSHARE_POST_TYPES=post,page,case_study`.

The configuration script rejects empty selections, attachments, revisions,
internal/non-public types, non-viewable types, and custom-type names that do
not exist on the target site. This keeps preview sharing off auto-drafts and
trashed items as well: PreviewShare only generates links for its supported
statuses (`publish`, `draft`, `pending`, `future`, and `private`).

Do not place site-specific custom post types in shared Stack or plugin code.
The site deployment configuration is the source of truth for the allow-list.
Changing the site setting does not alter the plugin's per-item controls:
editors can set an individual link expiry and explicitly revoke a link.

PreviewShare owns the preview response's `X-Robots-Tag` and WordPress robots
directives. The Stack must preserve its `noindex` and `nofollow` protections
(as well as its related noarchive/nosnippet/noimageindex directives); neither
the bootstrap script nor site configuration replaces those hooks.

## Release and child-site verification

Run the normal release/deployment verification with the intended site's
configuration values. `qa-rollout-contract.sh` verifies that the remote Stack
source has matching configuration and verification helpers, then, for a named
child-site root, invokes `verify-previewshare.php`. It fails unless the plugin
is active, its expiry matches, and the enabled list exactly matches the
configured public/viewable list.

For a targeted WordPress runtime check, use the same configuration values:

```bash
STACK_PREVIEWSHARE_POST_TYPES=post,page,case_study \
STACK_PREVIEWSHARE_DEFAULT_TTL_HOURS=6 \
wp eval-file /home/mrndev-stack-manager/stack/scripts/verify-previewshare.php \
  --path=/path/to/site
```

The command is read-only. A remote invocation remains subject to the normal
backup, deployment authorization, and MainWP-first rules; this document does
not authorize installing the plugin on an existing child site.

## Public Post Preview migration boundary

Existing Public Post Preview links cannot be migrated into PreviewShare. They
use a different token and metadata model, so regenerate a new PreviewShare
link for every still-needed review after the approved, backup-gated migration.
Do not promise continuity for old URLs; notify the recipient with the newly
generated link and revoke obsolete PreviewShare links when the review closes.
