<?php
/** Per-user appearance for the bounded Local Hub candidate. */
defined( 'ABSPATH' ) || exit;

/** Reuse the existing emergency switch and local-environment boundary. */
function mrn_admin_appearance_enabled() {
	return is_admin() && function_exists( 'mrn_editor_experience_enabled' ) && mrn_editor_experience_enabled();
}

/** Invalid or unset stored preferences follow the operating system. */
function mrn_admin_appearance_preference() {
	$value = get_user_meta( get_current_user_id(), 'mrn_admin_appearance', true );
	return in_array( $value, array( 'light', 'dark', 'system' ), true ) ? $value : 'system';
}

// The preference is in the opening HTML tag, before any styles or visible DOM.
// System is resolved by CSS media queries, including with JavaScript disabled.
add_action( 'admin_xml_ns', function () {
	if ( mrn_admin_appearance_enabled() ) {
		echo ' data-mrn-appearance="' . esc_attr( mrn_admin_appearance_preference() ) . '"';
	}
} );

add_filter( 'admin_body_class', function ( $classes ) {
	if ( ! mrn_admin_appearance_enabled() ) {
		return $classes;
	}
	$workspace = function_exists( 'mrn_editor_experience_screen' ) && mrn_editor_experience_screen();
	return $classes . ' mrn-appearance' . ( $workspace || mrn_admin_ui_modern_screen() ? ' mrn-themed-content' : ' mrn-native-app' );
} );

add_action( 'admin_enqueue_scripts', function () {
	if ( ! mrn_admin_appearance_enabled() ) {
		return;
	}
	foreach ( array( 'mrn-admin-tokens', 'mrn-admin-shell' ) as $name ) {
		wp_enqueue_style( $name, content_url( 'mu-plugins/mrn-admin-ui-css/' . $name . '.css' ), array( 'common' ), (string) filemtime( __DIR__ . '/' . $name . '.css' ) );
	}
	wp_enqueue_script( 'mrn-admin-appearance', content_url( 'mu-plugins/mrn-admin-ui-css/appearance.js' ), array(), (string) filemtime( __DIR__ . '/appearance.js' ), true );
	wp_localize_script( 'mrn-admin-appearance', 'mrnAdminAppearance', array(
		'url' => admin_url( 'admin-ajax.php' ),
		'saving' => __( 'Saving appearance…', 'mrn-admin-ui-css' ),
		'saved' => __( 'Appearance saved for your account.', 'mrn-admin-ui-css' ),
		'failed' => __( 'Appearance could not be saved. Your previous preference has been restored. Try Save again.', 'mrn-admin-ui-css' ),
		'visit' => __( 'Visit %s', 'mrn-admin-ui-css' ),
	) );
}, 30 );

add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! mrn_admin_appearance_enabled() || ! current_user_can( 'read' ) ) {
		return;
	}
	$bar->add_node( array(
		'id' => 'mrn-appearance',
		'title' => '<span class="ab-icon dashicons dashicons-admin-appearance" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Appearance', 'mrn-admin-ui-css' ) . '</span>',
		'href' => '#mrn-appearance-preference',
		'meta' => array( 'title' => __( 'Your admin appearance', 'mrn-admin-ui-css' ) ),
	) );
	ob_start();
	?>
	<form class="mrn-appearance-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="mrn_admin_appearance">
		<?php wp_nonce_field( 'mrn_admin_appearance' ); ?>
		<label for="mrn-appearance-preference"><?php esc_html_e( 'Your appearance', 'mrn-admin-ui-css' ); ?></label>
		<select id="mrn-appearance-preference" name="appearance" aria-describedby="mrn-appearance-help">
			<?php foreach ( array( 'light' => __( 'Light', 'mrn-admin-ui-css' ), 'dark' => __( 'Dark', 'mrn-admin-ui-css' ), 'system' => __( 'System', 'mrn-admin-ui-css' ) ) as $value => $label ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( mrn_admin_appearance_preference(), $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p id="mrn-appearance-help"><?php esc_html_e( 'Saved to your account. System follows your device. Plugin applications and media dialogs keep their native appearance.', 'mrn-admin-ui-css' ); ?></p>
		<button type="submit"><?php esc_html_e( 'Save appearance', 'mrn-admin-ui-css' ); ?></button>
		<p class="mrn-appearance-status" role="status" aria-live="polite"></p>
	</form>
	<?php
	$bar->add_node( array( 'id' => 'mrn-appearance-controls', 'parent' => 'mrn-appearance', 'title' => ob_get_clean() ) );
}, 90 );

/** Accept only a self-owned preference; never accept a user ID from the request. */
function mrn_admin_appearance_save() {
	if ( ! mrn_admin_appearance_enabled() || ! current_user_can( 'read' ) ) {
		wp_die( esc_html__( 'Appearance is unavailable.', 'mrn-admin-ui-css' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'mrn_admin_appearance' );
	$value = isset( $_POST['appearance'] ) && is_string( $_POST['appearance'] ) ? sanitize_key( wp_unslash( $_POST['appearance'] ) ) : '';
	if ( ! in_array( $value, array( 'light', 'dark', 'system' ), true ) ) {
		wp_die( esc_html__( 'Choose Light, Dark or System.', 'mrn-admin-ui-css' ), '', array( 'response' => 400 ) );
	}
	update_user_meta( get_current_user_id(), 'mrn_admin_appearance', $value );
	if ( mrn_admin_appearance_preference() !== $value ) {
		wp_die( esc_html__( 'Appearance could not be saved.', 'mrn-admin-ui-css' ), '', array( 'response' => 500 ) );
	}
	if ( wp_doing_ajax() ) {
		wp_send_json_success( array( 'appearance' => $value ) );
	}
	wp_safe_redirect( wp_get_referer() ?: admin_url() );
	exit;
}
add_action( 'wp_ajax_mrn_admin_appearance', 'mrn_admin_appearance_save' );
add_action( 'admin_post_mrn_admin_appearance', 'mrn_admin_appearance_save' );
