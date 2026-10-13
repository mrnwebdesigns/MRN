<?php
/**
 * Remove retired Stack UI records from AME data, without changing role grants.
 *
 * Shared by the canonical exports and the reviewed existing-site migration.
 * Matching uses identifiers and callback ownership, never visible SEO labels.
 */
final class MRN_AME_Stack_Cleanup {

	const RETIRED_PLUGINS = array(
		'wpmu-dev-seo', 'smartcrawl-seo', 'wpmudev-updates', 'wp-defender',
		'wp-defender-pro', 'wp-smush-pro', 'wp-smushit', 'hummingbird-performance',
		'beehive-analytics', 'fluent-smtp', 'searchwp',
		'schema-and-structured-data-for-wp', 'schema-app-structured-data-for-schemaorg',
		'mrn-seo-helper', 'mrn-acf-character-count', 'mrn-ai-assist',
		'mrn-cookie-consent', 'mrn-gtm-injector', 'mrn-editor-tools',
		'mrn-sendgrid-provisioning',
	);

	private const RETIRED_IDS = array(
		'acf-group_69a1c0f3a1b01', 'mrn-schema-bridge-controls',
		'mrn-config-helper-breadcrumbs', 'schema_meta_json_ld', 'fluent-mail',
		'mrn-gtm-settings', 'seo', 'readability', 'defender-two-fa', 'smushit',
	);

	/** Identifier prefixes belonging to explicitly retired providers. */
	private const PREFIXES = array(
		'wds-', 'wds_', 'smartcrawl-', 'wpmudev', 'wp-defender', 'wdf-',
		'wphb', 'beehive', 'saswp', 'searchwp', 'mrn-seo-helper',
		'mrn-acf-character-count', 'mrn-ai-assist', 'mrn-cookie-consent',
		'mrn-gtm-injector', 'mrn-editor-tools', 'mrn-sendgrid-provisioning',
	);

	/** @var array<string,int> Counts contain no saved values or secrets. */
	public $removed = array();

	private function count( $section ) {
		$this->removed[ $section ] = ( $this->removed[ $section ] ?? 0 ) + 1;
	}

	private function retired( $id, $record = array() ) {
		$id = (string) $id;
		if ( in_array( $id, self::RETIRED_IDS, true ) ) {
			return true;
		}
		foreach ( self::PREFIXES as $prefix ) {
			if ( $id === $prefix || 0 === strpos( $id, $prefix ) ) {
				return true;
			}
		}
		$callback = $record['callbackFileName'] ?? '';
		if ( is_string( $callback ) && preg_match( '~[/\\\\]wp-content[/\\\\]plugins[/\\\\]([^/\\\\]+)[/\\\\]~', $callback, $match ) ) {
			return in_array( $match[1], self::RETIRED_PLUGINS, true );
		}
		return false;
	}

	private function records( $records, $section ) {
		$result = array();
		foreach ( $records as $key => $record ) {
			$id = is_array( $record ) ? ( $record['id'] ?? $key ) : $key;
			if ( $this->retired( $id, is_array( $record ) ? $record : array() ) ) {
				$this->count( $section );
				continue;
			}
			$result[ $key ] = $record;
		}
		return array_is_list( $records ) ? array_values( $result ) : $result;
	}

	public function menu( $menu ) {
		if ( empty( $menu['tree'] ) || ! is_array( $menu['tree'] ) ) {
			return $menu;
		}
		$menu['tree'] = $this->menu_nodes( $menu['tree'] );
		return $menu;
	}

	private function menu_nodes( $nodes ) {
		$result = array();
		foreach ( $nodes as $key => $node ) {
			if ( ! is_array( $node ) ) {
				$result[ $key ] = $node;
				continue;
			}
			$defaults = is_array( $node['defaults'] ?? null ) ? $node['defaults'] : array();
			$file     = $defaults['file'] ?? $node['file'] ?? $key;
			$page     = $file;
			$query    = parse_url( (string) $file, PHP_URL_QUERY );
			if ( is_string( $query ) ) {
				parse_str( $query, $parameters );
				$page = $parameters['page'] ?? $file;
			}
			if ( $this->retired( $page ) ) {
				$this->count( 'admin-menu' );
				continue;
			}
			if ( isset( $node['items'] ) && is_array( $node['items'] ) ) {
				$node['items'] = $this->menu_nodes( $node['items'] );
			}
			// Replace only the historical Stack Advanced placeholder. Preserve its
			// custom title, visibility and explicit actor access decisions.
			if ( '#ame-unclickable-menu-item-1' === $key && '>special:none' === ( $node['template_id'] ?? '' ) ) {
				if ( isset( $nodes['mrn-public-security-hardening'] ) ) {
					$this->count( 'advanced-route' );
					continue;
				}
				$key                 = 'mrn-public-security-hardening';
				$node['template_id']  = '>' . $key;
				$node['defaults']     = array_merge( $defaults, array(
					'page_title' => 'Public Security', 'menu_title' => 'Advanced',
					'file' => $key, 'access_level' => 'manage_options',
					'is_plugin_page' => true, 'url' => 'admin.php?page=' . $key,
				) );
				$node['required_capability_read_only'] = 'manage_options';
				$node['items'] = $node['items'] ?? array();
				array_unshift( $node['items'], array(
					'template_id' => $key . '>' . $key,
					'defaults' => array( 'menu_title' => 'Public Security', 'page_title' => 'Public Security',
						'file' => $key, 'access_level' => 'manage_options', 'is_plugin_page' => true,
						'url' => 'admin.php?page=' . $key ),
					'f' => 'ip',
				) );
				$this->count( 'advanced-route' );
			}
			$result[ $key ] = $node;
		}
		return array_is_list( $nodes ) ? array_values( $result ) : $result;
	}

	public function metaboxes( $settings ) {
		foreach ( $settings['screens'] ?? array() as $id => $screen ) {
			if ( 'saswp' === $id ) {
				unset( $settings['screens'][ $id ] );
				$this->count( 'metabox-screens' );
				continue;
			}
			foreach ( array( 'metaBoxes:', 'metaBoxes' ) as $field ) {
				if ( isset( $screen[ $field ] ) && is_array( $screen[ $field ] ) ) {
					$settings['screens'][ $id ][ $field ] = $this->records( $screen[ $field ], 'metaboxes' );
				}
			}
		}
		return $settings;
	}

	public function columns( $settings ) {
		foreach ( $settings['screens'] ?? array() as $id => $screen ) {
			if ( 'edit-saswp' === $id ) {
				unset( $settings['screens'][ $id ] );
				$this->count( 'table-screens' );
				continue;
			}
			if ( isset( $screen['columns'] ) && is_array( $screen['columns'] ) ) {
				$settings['screens'][ $id ]['columns'] = $this->records( $screen['columns'], 'table-columns' );
			}
			foreach ( array( 'defaultOrder', 'customOrder' ) as $field ) {
				if ( isset( $screen[ $field ] ) && is_array( $screen[ $field ] ) ) {
					$settings['screens'][ $id ][ $field ] = array_values( array_filter(
						$screen[ $field ], function ( $column ) { return ! $this->retired( $column ); }
					) );
				}
			}
		}
		return $settings;
	}

	public function widgets( $settings ) {
		if ( isset( $settings['widgets'] ) && is_array( $settings['widgets'] ) ) {
			$settings['widgets'] = $this->records( $settings['widgets'], 'dashboard-widgets' );
		}
		return $settings;
	}

	public function toolbar( $settings ) {
		if ( isset( $settings['nodes'] ) && is_array( $settings['nodes'] ) ) {
			$original = $settings['nodes'];
			$settings['nodes'] = $this->records( $original, 'toolbar' );
			// A site-specific child can have an unrelated ID but a retired parent.
			$deleted = array_fill_keys( array_diff( array_keys( $original ), array_keys( $settings['nodes'] ) ), true );
			do {
				$changed = false;
				foreach ( $settings['nodes'] as $id => $node ) {
					$parent = $node['parent'] ?? $node['defaults']['parent'] ?? null;
					if ( is_string( $parent ) && isset( $deleted[ $parent ] ) ) {
						unset( $settings['nodes'][ $id ] );
						$deleted[ $id ] = true;
						$changed = true;
						$this->count( 'toolbar' );
					}
				}
			} while ( $changed );
		}
		return $settings;
	}

	public function container( $container ) {
		foreach ( array( 'admin-menu' => 'menu', 'metaboxes' => 'metaboxes',
			'table-columns' => 'columns', 'dashboard-widget-editor' => 'widgets' ) as $id => $method ) {
			if ( isset( $container['settings'][ $id ] ) && is_array( $container['settings'][ $id ] ) ) {
				$container['settings'][ $id ] = $this->$method( $container['settings'][ $id ] );
			}
		}
		return $container;
	}
}
