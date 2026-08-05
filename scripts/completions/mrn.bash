_mrn() {
	local cur prev cmd
	local map_file list_script site_hosts
	cur="${COMP_WORDS[COMP_CWORD]}"
	prev="${COMP_WORDS[COMP_CWORD-1]}"
	cmd="${COMP_WORDS[1]}"
	map_file="${MRN_LOCAL_SITE_MAP_FILE:-/Users/khofmeyer/Development/MRN/local/configs/local-site-map.mrndev.io.txt}"
	list_script="${MRN_MRNDEV_HOSTS_LIST_SCRIPT:-/Users/khofmeyer/Development/MRN/local/scripts/list-mrndev-hostnames.sh}"
	site_hosts=""

	if [[ -f "${map_file}" ]]; then
		site_hosts="$(
			awk -F'|' '
				/^[[:space:]]*#/ { next }
				NF < 2 { next }
				{
					host=$1
					gsub(/^[[:space:]]+|[[:space:]]+$/, "", host)
					if (host != "") print host
				}
			' "${map_file}" 2>/dev/null | sort -u | tr '\n' ' '
		)"
	fi

	if [[ -x "${list_script}" ]]; then
		site_hosts="$(
			{
				printf '%s\n' "${site_hosts}" | tr ' ' '\n'
				"${list_script}" 2>/dev/null
			} | sed '/^[[:space:]]*$/d' | sort -u | tr '\n' ' '
		)"
	fi

	if [[ ${COMP_CWORD} -eq 1 ]]; then
		COMPREPLY=( $(compgen -W "pull-site deploy-site nightly-pull local-hub completion install-completion help" -- "${cur}") )
		return 0
	fi

	case "${cmd}" in
		pull-site)
			if [[ ${COMP_CWORD} -eq 2 ]]; then
				if [[ "${cur}" == -* ]]; then
					COMPREPLY=( $(compgen -W "--site-hostname --local-site-path --sync-runtime --no-sync-runtime --local-api-auto-create --no-local-api-auto-create --snapshot-if-missing --snapshot-root --local-home-url --local-sites-root --map-file --discovery-ssh-host --skip-db --skip-uploads --dry-run" -- "${cur}") )
				elif [[ -n "${site_hosts}" ]]; then
					COMPREPLY=( $(compgen -W "${site_hosts}" -- "${cur}") )
				else
					COMPREPLY=()
				fi
				return 0
			fi

			if [[ ${COMP_CWORD} -eq 3 && "${COMP_WORDS[2]}" != -* ]]; then
				COMPREPLY=( $(compgen -d -- "${cur}") )
				return 0
			fi

			case "${prev}" in
				--site-hostname)
					if [[ -n "${site_hosts}" ]]; then
						COMPREPLY=( $(compgen -W "${site_hosts}" -- "${cur}") )
					else
						COMPREPLY=()
					fi
					return 0
					;;
				--local-site-path)
					COMPREPLY=( $(compgen -d -- "${cur}") )
					return 0
					;;
				--snapshot-root|--local-sites-root|--map-file)
					COMPREPLY=( $(compgen -d -- "${cur}") )
					return 0
					;;
			esac
			COMPREPLY=( $(compgen -W "--site-hostname --local-site-path --sync-runtime --no-sync-runtime --local-api-auto-create --no-local-api-auto-create --snapshot-if-missing --snapshot-root --local-home-url --local-sites-root --map-file --discovery-ssh-host --skip-db --skip-uploads --dry-run" -- "${cur}") )
			;;
		deploy-site)
			if [[ ${COMP_CWORD} -eq 2 ]]; then
				if [[ "${cur}" == -* ]]; then
					COMPREPLY=( $(compgen -W "--site-hostname --local-site-path --deploy-scope --local-home-url --local-sites-root --map-file --discovery-ssh-host --skip-backup --skip-db --skip-uploads --delete-uploads --yes --dry-run" -- "${cur}") )
				elif [[ -n "${site_hosts}" ]]; then
					COMPREPLY=( $(compgen -W "${site_hosts}" -- "${cur}") )
				else
					COMPREPLY=()
				fi
				return 0
			fi

			if [[ ${COMP_CWORD} -eq 3 && "${COMP_WORDS[2]}" != -* ]]; then
				COMPREPLY=( $(compgen -d -- "${cur}") )
				return 0
			fi

			case "${prev}" in
				--site-hostname)
					if [[ -n "${site_hosts}" ]]; then
						COMPREPLY=( $(compgen -W "${site_hosts}" -- "${cur}") )
					else
						COMPREPLY=()
					fi
					return 0
					;;
				--local-site-path)
					COMPREPLY=( $(compgen -d -- "${cur}") )
					return 0
					;;
				--deploy-scope)
					COMPREPLY=( $(compgen -W "site stack" -- "${cur}") )
					return 0
					;;
				--local-sites-root|--map-file)
					COMPREPLY=( $(compgen -d -- "${cur}") )
					return 0
					;;
			esac
			COMPREPLY=( $(compgen -W "--site-hostname --local-site-path --deploy-scope --local-home-url --local-sites-root --map-file --discovery-ssh-host --skip-backup --skip-db --skip-uploads --delete-uploads --yes --dry-run" -- "${cur}") )
			;;
		nightly-pull)
			case "${prev}" in
				--snapshot-root|--local-sites-root|--map-file)
					COMPREPLY=( $(compgen -d -- "${cur}") )
					return 0
					;;
			esac
			COMPREPLY=( $(compgen -W "--discovery-ssh-host --local-sites-root --map-file --snapshot-root --skip-db --with-uploads --dry-run" -- "${cur}") )
			;;
		completion|install-completion)
			COMPREPLY=( $(compgen -W "zsh bash" -- "${cur}") )
			;;
		local-hub)
			COMPREPLY=( $(compgen -W "--doctor" -- "${cur}") )
			;;
		*)
			COMPREPLY=()
			;;
	esac
}

complete -F _mrn mrn
