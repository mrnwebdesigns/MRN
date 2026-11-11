# Disposable editorial workflow

The three new tools produce exact provider previews for the existing
`https://child.fixture.example.invalid` development fixture. They return
`blocked / EDITORIAL_QA_UNQUALIFIED` and no approval digest. The existing
protected runner admits published ACF scalar edits, not the core editorial
lifecycle. Never use a legacy or ACF receipt to approve an editorial preview.
The production team plugin and the Phase 3 ACF/CSS acceptance gate are unchanged.

## What to demonstrate now

1. On the local gateway fixture, call `search_website_content` with
   `kind: "pages"` and `statuses: ["draft"]`. Use the returned ID with
   `read_website_content`. Save the snapshot SHA-256 and check the status, core
   fields, and registered ACF definitions/values.
2. Call `prepare_content_draft` with `postType: "page"`, a distinct title and
   slug, reason, and request key. Expect a draft-only preview with no record ID
   assigned. A retry of identical input returns the same operation.
3. On the existing draft, call `prepare_content_core_edit` with the exact
   record ID and snapshot SHA-256. Change only the body. The candidate must
   remain draft and retain the ACF snapshot. Test `prepare_content_status` with
   `status: "publish"` as a separate operation. A stale hash or any attempt to
   mix status into the core edit fails.
4. Expect approval and execution of the unqualified preview to fail without
   asking for a backup or dispatching a write. Read the record again and
   compare the original hash, status and ACF values.

Run from `tools/operations`:

```sh
node --test test/content-editorial.test.mjs test/delegated-mainwp.test.mjs test/content-workflow.test.mjs
```

## Native Child adapter reproduction

This runs actual WordPress, PHP and InnoDB, without MainWP transport, protected
QA, an ACF runtime, a remote backup, or a recovery executor. The script starts
a scratch DB on a private Unix socket with TCP disabled, uses a cached
WordPress 7.1.3 ZIP and PHP 8.2, and removes its own scratch files. Run from the
gateway worktree alongside the unchanged `enhanced-mcp-provider` worktree.
The provider pin is `b6f6750904b734877953a57c521825424394a5cb`.

```bash
set -eu
provider="$(cd ../enhanced-mcp-provider && pwd -P)"
test "$(git -C "$provider" rev-parse HEAD)" = b6f6750904b734877953a57c521825424394a5cb
test -z "$(git -C "$provider" status --porcelain)"
test_file="$PWD/tools/operations/test/runtime/editorial-wordpress.php"
scratch="$(mktemp -d /tmp/mrn-editorial-wp.XXXXXXXX)"
cleanup() {
  if [ -S "$scratch/db.sock" ]; then
    mysqladmin --no-defaults --protocol=SOCKET --socket="$scratch/db.sock" -u root shutdown >/dev/null 2>&1 || true
  fi
  python3 -c 'import pathlib,re,shutil,sys; p=pathlib.Path(sys.argv[1]); assert p.parent == pathlib.Path("/tmp") and re.fullmatch(r"mrn-editorial-wp\.[A-Za-z0-9]{8}", p.name) and not p.is_symlink(); shutil.rmtree(p)' "$scratch"
}
trap cleanup EXIT
chmod 700 "$scratch"
cd "$scratch"
unzip -q /Users/khofmeyer/.wp-cli/cache/core/wordpress-7.1.3-en_US.zip
mkdir "$scratch/data"
mysqld --no-defaults --initialize-insecure --datadir="$scratch/data" >"$scratch/init.log" 2>&1
mysqld --no-defaults --skip-networking --mysqlx=OFF --socket="$scratch/db.sock" --datadir="$scratch/data" --pid-file="$scratch/db.pid" --log-error="$scratch/mysql.log" &
for i in $(seq 1 60); do
  if mysqladmin --no-defaults --protocol=SOCKET --socket="$scratch/db.sock" -u root ping >/dev/null 2>&1; then break; fi
  sleep 1
done
mysql --no-defaults --protocol=SOCKET --socket="$scratch/db.sock" -u root -e 'CREATE DATABASE mrn_editorial'
export WP_CLI_CONFIG_PATH=/dev/null
export WP_CLI_CACHE_DIR="$scratch/wp-cache"
export MRN_EDITORIAL_PROVIDER="$provider"
export MRN_EDITORIAL_WORDPRESS_FIXTURE=1
wp_fixture() {
  /opt/homebrew/opt/php@8.2/bin/php /opt/homebrew/bin/wp --path="$scratch/wordpress" "$@"
}
wp_fixture config create --dbname=mrn_editorial --dbuser=root --dbhost="localhost:$scratch/db.sock" --skip-check --skip-salts
wp_fixture config set WP_ENVIRONMENT_TYPE local
wp_fixture config set WP_HTTP_BLOCK_EXTERNAL true --raw
wp_fixture config set DISABLE_WP_CRON true --raw
wp_fixture config set WP_AUTO_UPDATE_CORE false --raw
openssl rand -hex 32 | wp_fixture core install --url=https://child.fixture.example.invalid --title='Scratch editorial fixture' --admin_user=editorial-fixture --admin_email=fixture@example.invalid --skip-email --prompt=admin_password >/dev/null
wp_fixture rewrite structure '/%postname%/'
wp_fixture eval-file "$test_file"
```

Observed on WordPress 7.1.3, PHP 8.2.35 and MySQL 8.4.11: draft creation,
core edits, publish and return to draft passed for pages with matching
independent reads and unrelated metadata. For posts, draft creation and core
edits passed, and a contributor without `publish_posts` was denied. The first
administrator post publication failed with `mrn_content_partial`: the default
WordPress publish hook added `_pingme` and `_encloseme` inside the transaction.
The provider rolled back, and a fresh read matched the original draft exactly.
Do not turn off the hook or discard the unrelated metadata check to pass this
test. The minimum provider fix must model the exact allowed core publication
effects and refuse all other metadata differences. It can be deferred for a
page-only demonstration.

## What closes the page demonstration

- Extend the existing protected content runner for exactly one fixture page:
  prove draft absence before creation, exact draft after, and restoration of
  the disposable preimage; prove the exact record status, public rendering
  after publish, and anonymous absence after return to draft. Keep the
  separate ACF signing purpose and admission checks intact.
- Bind the matching provider and gateway profiles and the fixture's existing
  operation-bound database recovery route. Require an independently readable
  pre-write backup for every mutation, including drafts. Creation has no
  provider inverse; retain the verified database preimage. A status change
  back to draft is its own reviewed operation and does not undo creation.
- After separate native execution authorization, use the same disposable
  Dashboard and child to execute draft creation, title/body edit, explicit
  publish, one separately qualified ACF scalar change on the published page,
  and explicit return to draft. For each action use the current snapshot and
  request key, wait for signed QA, review and approve the exact digest,
  execute once, and independently re-read the record. Verify unrelated records
  and ACF values, public visibility, backup proof, and individual requester,
  approver and executor in the audit trail. An uncertain result retains its
  lock. No live site or new worker is needed for the adapter reproduction.
