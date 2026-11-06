#!/usr/bin/env bash
# Disposable CI toolchain only; never installs an extension on the runner host.
set -euo pipefail
umask 077
[[ "${GITHUB_ACTIONS:-}" == true && "${MRN_EXISTING_ACCESS_PROFILE_ONLY:-}" == 1 && "$#" == 1 ]]
profile_output="$1"
[[ "$profile_output" == /* && ! -e "$profile_output" ]]
mkdir -m 700 "$profile_output"
profile_build="$(mktemp -d)"
profile_builder="mrn-access-profiler-build-${GITHUB_RUN_ID:?}-${GITHUB_RUN_ATTEMPT:?}"
profile_image='wordpress:6.9.4-php8.3-apache@sha256:5d2c212561c4b5442ebc4d98933a9cbadcf3dee8888ed3fd9ed44667c27cc905'
profile_commit='127bbcb980400752221cfaa54bdc1420e6ef3c12'
cleanup() {
  docker rm -f "$profile_builder" >/dev/null 2>&1 || true
  rm -rf "$profile_build"
}
trap cleanup EXIT
git clone --quiet --no-checkout https://github.com/xdebug/xdebug.git "$profile_build/source"
git -C "$profile_build/source" checkout --quiet "$profile_commit"
[[ "$(git -C "$profile_build/source" rev-parse HEAD)" == "$profile_commit" ]]
git -C "$profile_build/source" rev-parse HEAD^{tree} > "$profile_output/source-tree.txt"
docker pull "$profile_image" >/dev/null
# Build before the original authorization fixture starts. No make install,
# no application source mutation and no alternate PHP image/ABI.
if ! docker run --name "$profile_builder" --user 0:0 --entrypoint sh \
  -v "$profile_build/source:/source:ro" -v "$profile_output:/out" "$profile_image" -c '
    set -eu
    apt-get update -qq
    apt-get install -y --no-install-recommends $PHPIZE_DEPS
    cp -a /source /tmp/profile-build
    cd /tmp/profile-build
    phpize
    ./configure --enable-xdebug
    make -j2
    cp modules/xdebug.so /out/xdebug.so
    php -n -d zend_extension=/out/xdebug.so -d xdebug.mode=off -r '\''
      if (phpversion("xdebug") !== "3.5.3" || PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 3) exit(1);
      echo json_encode(["php"=>PHP_VERSION,"extension"=>phpversion("xdebug"),"php_api"=>PHP_ZTS ? "zts" : "nts"]), "\n";
    '\'' > /out/abi.json
    chown '"$(id -u):$(id -g)"' /out/xdebug.so /out/abi.json
    chmod 600 /out/xdebug.so /out/abi.json
  ' > "$profile_build/build.log" 2>&1; then
  printf '%s\n' 'Profiler toolchain build unavailable; private build output withheld.' >&2
  exit 1
fi
python3 - "$profile_output" "$profile_image" "$profile_commit" <<'PY'
import hashlib, json, pathlib, sys
p = pathlib.Path(sys.argv[1])
record = {"schema": 1, "image": sys.argv[2], "xdebug_commit": sys.argv[3],
          "xdebug_tree": (p / "source-tree.txt").read_text().strip(),
          "extension_sha256": hashlib.sha256((p / "xdebug.so").read_bytes()).hexdigest(),
          **json.loads((p / "abi.json").read_text())}
(p / "toolchain.json").write_text(json.dumps(record, sort_keys=True) + "\n")
(p / "toolchain.json").chmod(0o600)
PY
printf '%s\n' 'Pinned disposable profiler built; no host extension installed.'
