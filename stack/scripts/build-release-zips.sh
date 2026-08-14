#!/bin/bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
STANDALONE_PLUGINS_ROOT="${MRN_STANDALONE_PLUGINS_ROOT:-$(cd "${ROOT_DIR}/.." && pwd)/MRN-plugins}"

usage() {
	cat <<'EOF'
Usage:
  stack/scripts/build-release-zips.sh plugins [slug ...]
  stack/scripts/build-release-zips.sh mu-plugins [slug ...]
  stack/scripts/build-release-zips.sh theme
  stack/scripts/build-release-zips.sh all

Builds fresh zip artifacts into /releases from local source directories.
Release zips are build artifacts only and are gitignored.

Named plugins resolve from MRN/plugins first, then from the sibling
MRN-plugins workspace. Override the latter with MRN_STANDALONE_PLUGINS_ROOT.
EOF
}

list_directories() {
	local source_root="$1"

	find "$source_root" -mindepth 1 -maxdepth 1 -type d ! -name '.*' -exec basename {} \; | sort
}

zip_directory() {
	local source_root="$1"
	local release_root="$2"
	local slug="$3"
	local source_dir="${source_root}/${slug}"
	local zip_path="${release_root}/${slug}.zip"

	if [[ ! -d "$source_dir" ]]; then
		echo "Missing source directory: $source_dir" >&2
		return 1
	fi

	mkdir -p "$release_root"
	rm -f "$zip_path"

	(
		cd "$source_root"
		zip -rq "$zip_path" "$slug" \
			-x "$slug/.git/*" \
			-x "$slug/.git/**/*" \
			-x "$slug/.DS_Store" \
			-x "$slug/**/.DS_Store" \
			-x "$slug/.tmp/*" \
			-x "$slug/.tmp/**/*" \
			-x "$slug/node_modules/*" \
			-x "$slug/node_modules/**/*" \
			-x "$slug/playwright-report/*" \
			-x "$slug/playwright-report/**/*" \
			-x "$slug/test-results/*" \
			-x "$slug/test-results/**/*" \
			-x "$slug/zip/*" \
			-x "$slug/zip/**/*"
	)

	echo "Built $zip_path"
}

resolve_plugin_source_root() {
	local slug="$1"

	if [[ -d "${ROOT_DIR}/plugins/${slug}" ]]; then
		printf '%s\n' "${ROOT_DIR}/plugins"
		return 0
	fi

	if [[ -d "${STANDALONE_PLUGINS_ROOT}/${slug}" ]]; then
		printf '%s\n' "${STANDALONE_PLUGINS_ROOT}"
		return 0
	fi

	echo "Missing plugin source: ${slug}. Checked ${ROOT_DIR}/plugins and ${STANDALONE_PLUGINS_ROOT}." >&2
	return 1
}

build_plugins() {
	local in_repo_source_root="${ROOT_DIR}/plugins"
	local source_root
	local release_root="${ROOT_DIR}/releases/plugins"
	local slugs=("$@")
	local slug

	if [[ ${#slugs[@]} -eq 0 ]]; then
		slugs=()
		while IFS= read -r slug; do
			slugs+=( "$slug" )
		done < <(list_directories "$in_repo_source_root")
	fi

	for slug in "${slugs[@]}"; do
		source_root="$(resolve_plugin_source_root "$slug")"
		zip_directory "$source_root" "$release_root" "$slug"
	done
}

build_mu_plugins() {
	local source_root="${ROOT_DIR}/mu-plugins"
	local release_root="${ROOT_DIR}/releases/mu-plugins"
	local slugs=("$@")
	local slug

	if [[ ${#slugs[@]} -eq 0 ]]; then
		slugs=()
		while IFS= read -r slug; do
			slugs+=( "$slug" )
		done < <(list_directories "$source_root")
	fi

	for slug in "${slugs[@]}"; do
		zip_directory "$source_root" "$release_root" "$slug"
	done
}

build_theme() {
	local source_root="${ROOT_DIR}/stack/themes"
	local release_root="${ROOT_DIR}/releases/stack"
	local legacy_zip_path="${ROOT_DIR}/stack/themes/mrn-base-stack.zip"
	local theme_slugs=(
		"mrn-base-stack"
		"mrn-base-stack-child"
	)

	for slug in "${theme_slugs[@]}"; do
		zip_directory "$source_root" "$release_root" "$slug"
	done

	cp "${release_root}/mrn-base-stack.zip" "${legacy_zip_path}"
	echo "Mirrored ${legacy_zip_path}"
}

main() {
	local target="${1:-}"

	case "$target" in
		plugins)
			shift
			build_plugins "$@"
			;;
		mu-plugins)
			shift
			build_mu_plugins "$@"
			;;
		theme)
			build_theme
			;;
		all)
			build_plugins
			build_mu_plugins
			build_theme
			;;
		-h|--help|help)
			usage
			;;
		*)
			usage >&2
			exit 1
			;;
	esac
}

main "$@"
