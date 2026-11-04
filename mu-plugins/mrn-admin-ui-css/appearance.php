<?php
/** Per-user appearance for the bounded Local Hub candidate. */
defined( 'ABSPATH' ) || exit;

/** Reuse the existing emergency switch and local-environment boundary. */
function mrn_admin_appearance_enabled() {
	return is_admin() && function_exists( 'mrn_editor_experience_enabled' ) && mrn_editor_experience_enabled();
}

/** Only the native Media Library and upload screen use the focused media shell. */
function mrn_admin_media_workspace_screen() {
	if ( ! mrn_admin_appearance_enabled() || ! function_exists( 'get_current_screen' ) ) {
		return false;
	}
	$screen = get_current_screen();
	return $screen && in_array( $screen->id, array( 'upload', 'media' ), true ) && in_array( $screen->base, array( 'upload', 'media' ), true );
}

/** Invalid or unset stored preferences follow the operating system. */
function mrn_admin_appearance_preference() {
	$value = get_user_meta( get_current_user_id(), 'mrn_admin_appearance', true );
	return in_array( $value, array( 'light', 'dark', 'system' ), true ) ? $value : 'system';
}

// Keep native form geometry available to ACF during startup, but do not paint
// the intermediate native layout. This is editor-only and always fails open.
add_action( 'admin_head', function () {
	if ( ! function_exists( 'mrn_editor_experience_screen' ) || ! mrn_editor_experience_screen() ) {
		return;
	}
	wp_print_inline_script_tag( "document.documentElement.classList.add('mrn-editor-starting');window.setTimeout(function(){document.documentElement.classList.remove('mrn-editor-starting');},8000);" );
}, 1 );

add_action( 'in_admin_header', function () {
	if ( function_exists( 'mrn_editor_experience_screen' ) && mrn_editor_experience_screen() ) {
		echo '<div id="mrn-editor-opening" role="status" aria-live="polite">' . esc_html__( 'Opening editor…', 'mrn-admin-ui-css' ) . '</div>';
	}
} );

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
	return $classes . ' mrn-appearance' . ( $workspace || mrn_admin_ui_modern_screen() ? ' mrn-themed-content' : ' mrn-native-app' ) . ( mrn_admin_media_workspace_screen() ? ' mrn-media-workspace' : '' );
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
		'failed' => __( 'Appearance could not be saved. Your previous preference has been restored. Try again.', 'mrn-admin-ui-css' ),
		'visit' => __( 'Visit %s', 'mrn-admin-ui-css' ),
		'closeScreen' => __( 'Close', 'mrn-admin-ui-css' ),
		'closeScreenLabel' => __( 'Close view options or help', 'mrn-admin-ui-css' ),
		'viewOptions' => __( 'View options', 'mrn-admin-ui-css' ),
		'settingsPanels' => __( 'Page Settings panels', 'mrn-admin-ui-css' ),
		'settingsPanelsHint' => __( 'Choose which optional panels appear in Page Settings. Content sections are always available.', 'mrn-admin-ui-css' ),
		'openSettings' => __( 'Open Page Settings', 'mrn-admin-ui-css' ),
	) );
}, 30 );

// Render workspace navigation before the media/folder layout, without a late
// toolbar insertion or rebuilding AME's menu. The anchor is the no-script fallback.
add_action( 'in_admin_header', function () {
	if ( mrn_admin_media_workspace_screen() && current_user_can( 'upload_files' ) ) {
		$library = 'upload' === get_current_screen()->base;
		?>
		<nav id="mrn-media-workspace-header" class="mrn-workspace-header hide-if-no-js" aria-label="<?php esc_attr_e( 'Media workspace', 'mrn-admin-ui-css' ); ?>">
			<a id="mrn-media-menu" class="mrn-workspace-button mrn-site-menu" href="#adminmenumain" aria-controls="adminmenumain"><?php esc_html_e( 'Site menu', 'mrn-admin-ui-css' ); ?></a>
			<a class="mrn-workspace-back" href="<?php echo esc_url( admin_url( $library ? 'index.php' : 'upload.php' ) ); ?>">&larr; <?php echo esc_html( $library ? __( 'Dashboard', 'mrn-admin-ui-css' ) : __( 'Back to library', 'mrn-admin-ui-css' ) ); ?></a>
		</nav>
		<?php
	}
} );

// Register the native Dashboard node before the site menu, so it precedes Visit
// Site. Toolbar Editor runs afterwards and retains configuration/role ownership.
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( mrn_admin_appearance_enabled() && current_user_can( 'read' ) && ! $bar->get_node( 'dashboard' ) ) {
		$bar->add_node( array( 'id' => 'dashboard', 'parent' => 'site-name', 'title' => __( 'Dashboard', 'mrn-admin-ui-css' ), 'href' => admin_url() ) );
	}
}, 29 );

add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! mrn_admin_appearance_enabled() || ! current_user_can( 'read' ) ) {
		return;
	}
	ob_start();
	?>
	<form class="mrn-appearance-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="mrn_admin_appearance">
		<?php wp_nonce_field( 'mrn_admin_appearance' ); ?>
		<span id="mrn-appearance-label"><?php esc_html_e( 'Appearance', 'mrn-admin-ui-css' ); ?></span>
		<div class="mrn-appearance-choices" role="group" aria-labelledby="mrn-appearance-label">
			<?php
			$choices = array(
				'light' => array( __( 'Light', 'mrn-admin-ui-css' ), 'M12 3v2m0 14v2M3 12h2m14 0h2M5.6 5.6 7 7m10 10 1.4 1.4M5.6 18.4 7 17m10-10 1.4-1.4M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0' ),
				'dark' => array( __( 'Dark', 'mrn-admin-ui-css' ), 'M20.5 14A8.8 8.8 0 0 1 10 3.5 9 9 0 1 0 20.5 14Z' ),
				'system' => array( __( 'System', 'mrn-admin-ui-css' ), 'M4 3h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1ZM8 21h8m-4-4v4' ),
			);
			foreach ( $choices as $value => $choice ) : ?>
			<button type="submit" name="appearance" value="<?php echo esc_attr( $value ); ?>" aria-pressed="<?php echo mrn_admin_appearance_preference() === $value ? 'true' : 'false'; ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="<?php echo esc_attr( $choice[1] ); ?>" /></svg><?php echo esc_html( $choice[0] ); ?></button>
			<?php endforeach; ?>
		</div>
		<p class="mrn-appearance-status" role="status" aria-live="polite"></p>
	</form>
	<?php
	// The former standalone node has a persisted parent in Toolbar Editor.
	// A distinct account-node ID avoids reviving that orphaned placement.
	$bar->add_node( array( 'id' => 'mrn-account-appearance', 'parent' => 'user-actions', 'title' => ob_get_clean() ) );
	$workspace = function_exists( 'mrn_editor_experience_screen' ) && mrn_editor_experience_screen();
	if ( $workspace || mrn_admin_ui_modern_screen() ) {
		$bar->add_node( array(
			'id' => 'mrn-screen',
			'parent' => 'top-secondary',
			'title' => '<span class="ab-icon dashicons dashicons-admin-generic" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'View', 'mrn-admin-ui-css' ) . '</span>',
			'href' => '#screen-meta',
			'meta' => array( 'class' => 'hide-if-no-js', 'title' => __( 'View options and help', 'mrn-admin-ui-css' ) ),
		) );
		$bar->add_node( array( 'id' => 'mrn-screen-controls', 'parent' => 'mrn-screen', 'title' => '' ) );
	}
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
