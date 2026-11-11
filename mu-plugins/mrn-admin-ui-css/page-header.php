<?php
/** Shared, first-paint page heading for the appearance pilot. */
defined( 'ABSPATH' ) || exit;

/** Reuse existing editor/settings action bars; fill the remaining admin screens. */
function mrn_admin_page_header_mode() {
	if ( ! mrn_admin_appearance_enabled() ) {
		return '';
	}
	$screen = get_current_screen();
	if ( ! $screen || 'post' === $screen->base || 'site-editor' === $screen->base
		|| ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) ) {
		return '';
	}
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	// Fullscreen application editors have their own persistent action header.
	if ( 'wpforms-builder' === $page ) {
		return '';
	}
	$owners = array(
		'mrn-config-helper', 'mrn-business-information', 'mrn-theme-header-footer',
		'mrn-header-utility-message', 'mrn-tokens', 'mrn-site-styles',
		'mrn-announcements-settings', 'mrn-editor-tools', 'mrn-cookie-consent',
		'mrn-recaptcha-enterprise-manager', 'mrn-acf-character-count',
		'mrn-gtm-settings', 'mrn-ai-assist', 'mrn-dummy-content',
	);
	/** Declare pages whose existing settings toolbar already supplies the heading. */
	$owned = (bool) apply_filters( 'mrn_admin_page_header_owned', in_array( $page, $owners, true ), $screen, $page );
	return $owned ? 'owned' : 'automatic';
}

add_filter( 'admin_body_class', function ( $classes ) {
	$mode = mrn_admin_page_header_mode();
	return $mode ? $classes . ' mrn-admin-page-heading mrn-admin-page-heading-' . $mode : $classes;
} );

add_action( 'in_admin_header', function () {
	if ( 'automatic' !== mrn_admin_page_header_mode() ) {
		return;
	}
	$title = trim( wp_strip_all_tags( get_admin_page_title() ) );
	if ( '' === $title ) {
		return;
	}
	// Static variant of the shared settings toolbar. No form, spacer, cloned
	// controls or dirty-state/geometry observer is needed for a page heading.
	?>
	<div id="mrn-admin-page-toolbar" class="mrn-sticky-save-bar">
		<div class="mrn-settings-toolbar__meta"><span class="mrn-settings-toolbar__title"><?php echo esc_html( $title ); ?></span></div>
	</div>
	<?php
}, 5 );

// WPForms exposes its branding header separately from entry controls/content.
add_filter( 'wpforms_admin_header', function ( $visible ) {
	return 'wpforms-entries' === mrn_admin_ui_modern_screen() ? false : $visible;
} );

add_action( 'admin_enqueue_scripts', function () {
	if ( 'wpforms-entries' !== mrn_admin_ui_modern_screen() ) {
		return;
	}
	wp_enqueue_style( 'mrn-wpforms-entries', content_url( 'mu-plugins/mrn-admin-ui-css/wpforms-entries.css' ), array( 'mrn-admin-shell' ), (string) filemtime( __DIR__ . '/wpforms-entries.css' ) );
	if ( wp_script_is( 'wpforms-chart', 'enqueued' ) ) {
		wp_enqueue_script( 'mrn-wpforms-entries', content_url( 'mu-plugins/mrn-admin-ui-css/wpforms-entries.js' ), array( 'wpforms-chart' ), (string) filemtime( __DIR__ . '/wpforms-entries.js' ), true );
		wp_localize_script( 'mrn-wpforms-entries', 'mrnEntriesAppearance', array( 'chartSettings' => __( 'Chart settings', 'mrn-admin-ui-css' ) ) );
	}
}, 40 );
