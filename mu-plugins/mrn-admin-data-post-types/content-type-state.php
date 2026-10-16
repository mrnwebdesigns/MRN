<?php
/**
 * Explicit disabled state, independent of menu visibility and reusable data.
 *
 * @package MRN_Admin_Data_Post_Types
 */

defined( 'ABSPATH' ) || exit;

const MRN_ADMIN_DATA_POST_TYPES_DISABLED_OPTION = 'mrn_disabled_post_types';

/**
 * Queue one soft rewrite rebuild after a deliberate state change.
 *
 * @param mixed $old Previous selection.
 * @param mixed $selection New selection.
 * @return void
 */
function mrn_admin_data_post_types_queue_rewrite_flush( $old, $selection ) {
	if ( mrn_admin_data_post_types_normalize_disabled( $old ) !== mrn_admin_data_post_types_normalize_disabled( $selection ) ) {
		update_option( 'mrn_disabled_post_types_rewrite_pending', true, false );
	}
}
add_action( 'update_option_mrn_disabled_post_types', 'mrn_admin_data_post_types_queue_rewrite_flush', 10, 2 );

/**
 * Queue the initial opt-in too: update_option uses add_option on first save.
 *
 * @param string $option Added option name.
 * @param mixed  $value  Initial selection.
 * @return void
 */
function mrn_admin_data_post_types_add_disabled_selection( $option, $value ) {
	mrn_admin_data_post_types_queue_rewrite_flush( array(), $value );
}
add_action( 'add_option_mrn_disabled_post_types', 'mrn_admin_data_post_types_add_disabled_selection', 10, 2 );

/**
 * Rebuild after all types register with the new state, never during the save.
 *
 * @return void
 */
function mrn_admin_data_post_types_flush_pending_rewrites() {
	if ( get_option( 'mrn_disabled_post_types_rewrite_pending' ) ) {
		flush_rewrite_rules( false );
		delete_option( 'mrn_disabled_post_types_rewrite_pending' );
	}
}
add_action( 'wp_loaded', 'mrn_admin_data_post_types_flush_pending_rewrites', 100 );

/**
 * Preserve preferences for controls Config Helper cannot discover while disabled.
 * Its sanitizer also synchronizes the separate Content Only option before save.
 *
 * @param mixed  $value New option value.
 * @param mixed  $old   Previous value.
 * @param string $name  Option name.
 * @return mixed
 */
function mrn_admin_data_post_types_preserve_disabled_preferences( $value, $old, $name ) {
	$disabled = mrn_admin_data_post_types_get_disabled_selection();
	if ( empty( $disabled ) || ! is_array( $value ) || ! is_array( $old ) ) {
		return $value;
	}
	if ( MRN_ADMIN_DATA_POST_TYPES_OPTION === $name ) {
		return array_values( array_unique( array_merge( array_diff( $value, $disabled ), array_intersect( $old, $disabled ) ) ) );
	}
	foreach ( array( 'disabled_admin_cpts', 'content_only_post_types' ) as $group ) {
		if ( isset( $old[ $group ] ) && is_array( $old[ $group ] ) ) {
			$incoming        = isset( $value[ $group ] ) && is_array( $value[ $group ] ) ? $value[ $group ] : array();
			$value[ $group ] = array_values( array_unique( array_merge( array_diff( $incoming, $disabled ), array_intersect( $old[ $group ], $disabled ) ) ) );
		}
	}
	foreach ( $disabled as $post_type ) {
		if ( isset( $old['content_only_search_destinations'][ $post_type ] ) ) {
			$value['content_only_search_destinations'][ $post_type ] = $old['content_only_search_destinations'][ $post_type ];
		}
	}
	return $value;
}
add_filter( 'pre_update_option_mrn_helper_settings', 'mrn_admin_data_post_types_preserve_disabled_preferences', 100, 3 );
add_filter( 'pre_update_option_mrn_admin_data_post_types_manual', 'mrn_admin_data_post_types_preserve_disabled_preferences', 100, 3 );

/**
 * Normalize custom post type keys without accepting built-in WordPress types.
 *
 * @param mixed $requested Requested keys.
 * @return string[]
 */
function mrn_admin_data_post_types_normalize_disabled( $requested ) {
	$keys = array();
	foreach ( is_array( $requested ) ? $requested : array() as $value ) {
		if ( ! is_string( $value ) ) {
			continue;
		}
		$key  = sanitize_key( $value );
		$type = get_post_type_object( $key );
		if ( $type && $type->_builtin ) {
			continue;
		}
		if ( '' !== $key && strlen( $key ) <= 20 && ! in_array( $key, array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'any' ), true ) ) {
			$keys[] = $key;
		}
	}
	return array_values( array_unique( $keys ) );
}

/**
 * Read explicit disable intent. Never infer it from UI flags or content counts.
 *
 * @return string[]
 */
function mrn_admin_data_post_types_get_disabled_selection() {
	return mrn_admin_data_post_types_normalize_disabled( get_option( MRN_ADMIN_DATA_POST_TYPES_DISABLED_OPTION, array() ) );
}

/**
 * Resolve canonical state. Disable wins; menu hiding can coexist with data mode.
 *
 * @param string $post_type Post type key.
 * @return string
 */
function mrn_admin_data_post_types_get_state( $post_type ) {
	if ( in_array( $post_type, mrn_admin_data_post_types_get_disabled_selection(), true ) ) {
		return 'disabled';
	}
	if ( null !== mrn_admin_data_post_types_get_post_type_config( $post_type ) ) {
		return 'content_only';
	}
	$hidden = function_exists( 'mrn_config_helper_get_hidden_admin_cpts' ) ? mrn_config_helper_get_hidden_admin_cpts() : array();
	return is_array( $hidden ) && in_array( $post_type, $hidden, true ) ? 'menu_hidden' : 'enabled';
}

/**
 * Types without public SEO destinations, retaining the existing data contract.
 *
 * @return string[]
 */
function mrn_admin_data_post_types_get_excluded_post_types() {
	return array_values( array_unique( array_merge( array_keys( mrn_admin_data_post_types_get_config() ), mrn_admin_data_post_types_get_disabled_selection() ) ) );
}

/**
 * Exclude only taxonomies touching a disabled type with no enabled public owner.
 *
 * Resolve associations on every call: registration order and later associations
 * must not freeze an early decision. Unrelated/orphan taxonomies are untouched.
 *
 * @return string[]
 */
function mrn_admin_data_post_types_get_excluded_taxonomies() {
	$disabled = mrn_admin_data_post_types_get_disabled_selection();
	if ( empty( $disabled ) ) {
		return array();
	}
	$excluded = mrn_admin_data_post_types_get_excluded_post_types();
	$result   = array();
	foreach ( get_taxonomies( array(), 'objects' ) as $object ) {
		$owners = (array) $object->object_type;
		if ( ! array_intersect( $owners, $disabled ) ) {
			continue;
		}
		foreach ( $owners as $owner ) {
			$type = get_post_type_object( $owner );
			if ( ! in_array( $owner, $excluded, true ) && $type && $type->public && is_post_type_viewable( $type ) ) {
				continue 2;
			}
		}
		$result[] = $object->name;
	}
	return $result;
}

/**
 * Filter taxonomy registries (SEOPress and core sitemaps share keyed objects).
 *
 * @param mixed $taxonomies Taxonomy collection.
 * @return mixed
 */
function mrn_admin_data_post_types_filter_taxonomies( $taxonomies ) {
	if ( ! is_array( $taxonomies ) ) {
		return $taxonomies;
	}
	$excluded = mrn_admin_data_post_types_get_excluded_taxonomies();
	foreach ( $taxonomies as $key => $value ) {
		$taxonomy = mrn_admin_data_post_types_resolve_collection_post_type( $key, $value );
		if ( in_array( $taxonomy, $excluded, true ) ) {
			unset( $taxonomies[ $key ] );
		}
	}
	return $taxonomies;
}
add_filter( 'wp_sitemaps_taxonomies', 'mrn_admin_data_post_types_filter_taxonomies', 100 );

/**
 * Block disabled public destinations even while old rewrite rules are cached.
 * Programmatic queries are deliberately retained for recovery and data access.
 *
 * @return void
 */
function mrn_admin_data_post_types_guard_disabled_routes() {
	if ( is_admin() ) {
		return;
	}
	$disabled = mrn_admin_data_post_types_get_disabled_selection();
	$blocked  = ! empty( $disabled ) && ( is_singular( $disabled ) || is_post_type_archive( $disabled ) );
	if ( ! $blocked && is_tax( mrn_admin_data_post_types_get_excluded_taxonomies() ) ) {
		// An empty is_tax() list means every taxonomy, so test the actual name.
		$object  = get_queried_object();
		$blocked = isset( $object->taxonomy ) && in_array( $object->taxonomy, mrn_admin_data_post_types_get_excluded_taxonomies(), true );
	}
	if ( $blocked ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		remove_action( 'template_redirect', 'redirect_canonical' );
	}
}
add_action( 'template_redirect', 'mrn_admin_data_post_types_guard_disabled_routes', 0 );

/**
 * Render disabled controls through Config Helper's established extension point.
 *
 * @return void
 */
function mrn_admin_data_post_types_render_disabled_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$disabled = mrn_admin_data_post_types_get_disabled_selection();
	$types    = get_post_types( array( '_builtin' => false ), 'objects' );
	?>
	<div class="mrn-config-helper-card">
		<h2><?php esc_html_e( 'Disabled Content Types', 'mrn-admin-data-post-types' ); ?></h2>
		<p id="mrn-disabled-types-help"><?php esc_html_e( 'Disable removes the admin UI, public single pages, archives, REST endpoints, search results and SEO discovery. Exclusive taxonomy archives are also removed. Existing content, terms and SEO settings are kept. Review existing links and plan redirects before disabling a public type.', 'mrn-admin-data-post-types' ); ?></p>
		<p><?php esc_html_e( 'Hide above only hides the admin menu and keeps public URLs. Content Only keeps items available for use inside other pages. Disable takes priority over both settings; turning it off restores their previous behavior. Uploaded files and programmatic access are retained.', 'mrn-admin-data-post-types' ); ?></p>
		<?php wp_nonce_field( 'mrn_disabled_post_types_save', 'mrn_disabled_post_types_nonce' ); ?>
		<input type="hidden" name="mrn_disabled_post_types_present" value="1" />
		<div class="mrn-config-helper-checklist mrn-config-helper-checklist--toggles">
		<?php foreach ( $types as $post_type => $object ) : ?>
			<?php
			if ( ! $object->public && ! in_array( $post_type, $disabled, true ) && null === mrn_admin_data_post_types_get_post_type_config( $post_type ) ) {
				continue; }
			?>
			<label class="mrn-config-helper-toggle">
				<span class="mrn-config-helper-toggle__control">
					<input type="checkbox" class="mrn-config-helper-toggle__native" name="mrn_disabled_post_types[]" value="<?php echo esc_attr( $post_type ); ?>" aria-describedby="mrn-disabled-types-help" <?php checked( in_array( $post_type, $disabled, true ) ); ?> />
					<span class="mrn-config-helper-toggle__slider" aria-hidden="true"></span>
				</span>
				<?php /* translators: %s: content type label. */ ?>
				<span><?php echo esc_html( sprintf( __( 'Disable %s', 'mrn-admin-data-post-types' ), $object->labels->name ) ); ?></span>
			</label>
		<?php endforeach; ?>
		</div>
	</div>
	<?php
}
add_action( 'mrn_config_helper_render_admin_content_type_settings', 'mrn_admin_data_post_types_render_disabled_settings' );

/**
 * Save only an explicitly submitted, capability- and nonce-checked selection.
 *
 * @return void
 */
function mrn_admin_data_post_types_save_disabled_settings() {
	if ( ! current_user_can( 'manage_options' ) || empty( $_POST['mrn_disabled_post_types_present'] ) || ! isset( $_POST['mrn_disabled_post_types_nonce'] ) || ! is_string( $_POST['mrn_disabled_post_types_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mrn_disabled_post_types_nonce'] ) ), 'mrn_disabled_post_types_save' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each scalar is normalized below; nested values are rejected.
	$input    = isset( $_POST['mrn_disabled_post_types'] ) && is_array( $_POST['mrn_disabled_post_types'] ) ? wp_unslash( $_POST['mrn_disabled_post_types'] ) : array();
	$selected = array_values( array_intersect( mrn_admin_data_post_types_normalize_disabled( $input ), get_post_types( array( '_builtin' => false ) ) ) );
	// Preserve intent for a temporarily absent plugin/type; no invisible reset.
	$absent = array_diff( mrn_admin_data_post_types_get_disabled_selection(), get_post_types( array( '_builtin' => false ) ) );
	update_option( MRN_ADMIN_DATA_POST_TYPES_DISABLED_OPTION, array_values( array_unique( array_merge( $selected, $absent ) ) ), false );
}
add_action( 'mrn_config_helper_handle_save', 'mrn_admin_data_post_types_save_disabled_settings' );
