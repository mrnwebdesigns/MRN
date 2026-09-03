<?php
/**
 * Plugin Name: MRN Dashboard Support
 * Description: Adds a fixed, non-collapsible, non-movable MRN Web Designs support widget pinned to the top-left of the WP dashboard and an admin-only Notifications Center for stack and WordPress updates.
 * Author: MRN Web Designs
 * Version: 1.3.0
 *
 * INSTALL (MU-Plugin):
 * 1) Save as: /wp-content/mu-plugins/mrn-dashboard-support.php
 * 2) Optional logo: /wp-content/mu-plugins/mrn-logo.png
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MRN_DASHBOARD_SUPPORT_VERSION' ) ) {
	define( 'MRN_DASHBOARD_SUPPORT_VERSION', '1.3.0' );
}

/**
 * Optional manual override for launch date.
 *
 * Set to YYYY-MM-DD (example: '2026-02-13') to force the displayed date from code.
 * Leave as '' to use the one-time saved dashboard value.
 */
if ( ! defined( 'MRN_LAUNCH_DATE_OVERRIDE' ) ) {
	define( 'MRN_LAUNCH_DATE_OVERRIDE', '' );
}

/**
 * Get the capability required to view the Notifications Center.
 *
 * @return string
 */
function mrn_dashboard_support_notifications_capability() {
	$default = function_exists( 'is_network_admin' ) && is_network_admin() ? 'manage_network_options' : 'manage_options';
	$cap     = apply_filters( 'mrn_dashboard_support_notifications_capability', $default );

	return is_string( $cap ) && '' !== $cap ? $cap : $default;
}

/**
 * Check whether the current user can see the Notifications Center.
 *
 * @return bool
 */
function mrn_dashboard_support_can_view_notifications() {
	return current_user_can( mrn_dashboard_support_notifications_capability() );
}

/**
 * Return the Notifications Center page slug.
 *
 * @return string
 */
function mrn_dashboard_support_notifications_page_slug() {
	return 'mrn-notifications-center';
}

/**
 * Return the Notifications Center page title.
 *
 * @return string
 */
function mrn_dashboard_support_notifications_page_title() {
	$title = apply_filters( 'mrn_dashboard_support_notifications_page_title', 'Notifications Center' );

	return is_string( $title ) && '' !== $title ? $title : 'Notifications Center';
}

/**
 * Return the Notifications Center menu title.
 *
 * @return string
 */
function mrn_dashboard_support_notifications_menu_title() {
	$title = apply_filters( 'mrn_dashboard_support_notifications_menu_title', 'Notifications' );

	return is_string( $title ) && '' !== $title ? $title : 'Notifications';
}

/**
 * Get the current administrator's notification state.
 *
 * @return array<string, array<string, bool>>
 */
function mrn_dashboard_support_get_notification_state() {
	if ( ! is_user_logged_in() ) {
		return array();
	}

	$state = get_user_meta( get_current_user_id(), 'mrn_dashboard_notification_state', true );

	return is_array( $state ) ? $state : array();
}

/**
 * Save one notification state value for the current administrator.
 *
 * @param string $notification_id Notification ID.
 * @param string $state_key        State key.
 * @param bool   $value            State value.
 */
function mrn_dashboard_support_update_notification_state( $notification_id, $state_key, $value = true ) {
	if ( ! is_user_logged_in() || '' === $notification_id || ! in_array( $state_key, array( 'read', 'dismissed' ), true ) ) {
		return;
	}

	$state = mrn_dashboard_support_get_notification_state();
	if ( ! isset( $state[ $notification_id ] ) || ! is_array( $state[ $notification_id ] ) ) {
		$state[ $notification_id ] = array();
	}

	$state[ $notification_id ][ $state_key ] = (bool) $value;
	update_user_meta( get_current_user_id(), 'mrn_dashboard_notification_state', $state );
}

/**
 * Store plugin/core admin notices for the Notifications Center.
 *
 * @param string $html Rendered admin notice HTML.
 */
function mrn_dashboard_support_capture_admin_notice( $html ) {
	if ( ! mrn_dashboard_support_can_view_notifications() || ! is_string( $html ) ) {
		return;
	}

	$message = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) );
	if ( '' === $message ) {
		return;
	}

	$type = 'info';
	if ( preg_match( '/notice-error|\berror\b/i', $html ) ) {
		$type = 'error';
	} elseif ( preg_match( '/notice-warning|\bwarning\b/i', $html ) ) {
		$type = 'warning';
	} elseif ( preg_match( '/notice-success|\bsuccess\b/i', $html ) ) {
		$type = 'success';
	}

	$notification = mrn_dashboard_support_normalize_notification(
		array(
			'id'       => 'admin-notice-' . md5( $message ),
			'group'    => 'wordpress',
			'type'     => $type,
			'title'    => 'Admin notice',
			'message'  => $message,
			'source'   => 'WordPress / plugin notice',
			'priority' => 500,
		)
	);

	if ( empty( $notification ) ) {
		return;
	}

	$captured = get_user_meta( get_current_user_id(), 'mrn_dashboard_captured_notifications', true );
	$captured = is_array( $captured ) ? $captured : array();
	$captured[ $notification['id'] ] = $notification;
	$captured = array_slice( $captured, -100, 100, true );
	update_user_meta( get_current_user_id(), 'mrn_dashboard_captured_notifications', $captured );
}

/**
 * Capture standard notice output so plugin/core warnings do not remain on the dashboard.
 *
 * The all_admin_notices channel is intentionally excluded. The shared Stack uses that
 * channel for Universal Sticky Bar markup and styles, so buffering it would consume
 * toolbar output before WordPress can render it.
 */
function mrn_dashboard_support_begin_admin_notice_capture() {
	if ( mrn_dashboard_support_can_view_notifications() ) {
		$GLOBALS['mrn_dashboard_support_notice_buffer_level'] = ob_get_level();
		ob_start();
	}
}

/**
 * Finish capturing notice output and store it for the current administrator.
 */
function mrn_dashboard_support_finish_admin_notice_capture() {
	$buffer_level = isset( $GLOBALS['mrn_dashboard_support_notice_buffer_level'] )
		? (int) $GLOBALS['mrn_dashboard_support_notice_buffer_level']
		: -1;

	if ( ! mrn_dashboard_support_can_view_notifications() || -1 === $buffer_level || ob_get_level() <= $buffer_level ) {
		return;
	}

	$notice_html = ob_get_clean();
	unset( $GLOBALS['mrn_dashboard_support_notice_buffer_level'] );
	mrn_dashboard_support_capture_admin_notice( $notice_html );
}

/**
 * Return captured notices that have not been dismissed.
 *
 * @return array<int, array<string, mixed>>
 */
function mrn_dashboard_support_get_captured_notifications() {
	$captured = get_user_meta( get_current_user_id(), 'mrn_dashboard_captured_notifications', true );

	return is_array( $captured ) ? array_values( $captured ) : array();
}

/**
 * Apply per-user read and dismissed state to a notification list.
 *
 * @param array<int, array<string, mixed>> $notifications Notifications.
 * @return array<int, array<string, mixed>>
 */
function mrn_dashboard_support_apply_notification_state( $notifications ) {
	$state = mrn_dashboard_support_get_notification_state();
	$visible = array();

	foreach ( $notifications as $notification ) {
		$id = isset( $notification['id'] ) ? (string) $notification['id'] : '';
		if ( '' === $id || ! empty( $state[ $id ]['dismissed'] ) ) {
			continue;
		}

		$notification['read'] = ! empty( $state[ $id ]['read'] );
		$visible[] = $notification;
	}

	return $visible;
}

/**
 * Count unread notifications.
 *
 * @param array<int, array<string, mixed>> $notifications Notifications.
 * @return int
 */
function mrn_dashboard_support_count_unread_notifications( $notifications ) {
	$count = 0;

	foreach ( $notifications as $notification ) {
		if ( empty( $notification['read'] ) ) {
			$count++;
		}
	}

	return $count;
}

/**
 * Check whether unread warning/error notifications need attention.
 *
 * @param array<int, array<string, mixed>> $notifications Notifications.
 * @return bool
 */
function mrn_dashboard_support_has_unread_alerts( $notifications ) {
	foreach ( $notifications as $notification ) {
		if ( empty( $notification['read'] ) && in_array( $notification['type'], array( 'error', 'warning' ), true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Convert a pending count into the standard WordPress badge markup.
 *
 * @param int $count Pending count.
 * @return string
 */
function mrn_dashboard_support_notification_badge_html( $count ) {
	$count = absint( $count );
	if ( $count < 1 ) {
		return '';
	}

	return sprintf(
		' <span class="update-plugins count-%1$d"><span class="update-count">%1$d</span></span>',
		$count
	);
}

/**
 * Human-friendly label for a notification group.
 *
 * @param string $group Group key.
 * @return string
 */
function mrn_dashboard_support_notification_group_label( $group ) {
	switch ( $group ) {
		case 'wordpress':
			return 'WordPress';
		case 'stack':
		default:
			return 'Stack';
	}
}

/**
 * Sort key for notification groups.
 *
 * @param string $group Group key.
 * @return int
 */
function mrn_dashboard_support_notification_group_order( $group ) {
	switch ( $group ) {
		case 'stack':
			return 0;
		case 'wordpress':
			return 1;
		default:
			return 10;
	}
}

/**
 * Human-friendly label for a notification type.
 *
 * @param string $type Type key.
 * @return string
 */
function mrn_dashboard_support_notification_type_label( $type ) {
	switch ( $type ) {
		case 'error':
			return 'Error';
		case 'warning':
			return 'Warning';
		case 'success':
			return 'Success';
		case 'info':
		default:
			return 'Info';
	}
}

/**
 * Normalize a notification array for stable rendering and de-duplication.
 *
 * @param array<string, mixed> $notification Notification payload.
 * @return array<string, mixed>
 */
function mrn_dashboard_support_normalize_notification( $notification ) {
	if ( ! is_array( $notification ) ) {
		return array();
	}

	$notification = array_merge(
		array(
			'id'           => '',
			'group'        => 'stack',
			'type'         => 'info',
			'title'        => '',
			'message'      => '',
			'details'      => array(),
			'action_label' => '',
			'action_url'   => '',
			'source'       => '',
			'priority'     => 100,
		),
		$notification
	);

	$notification['id'] = sanitize_key( (string) $notification['id'] );
	if ( '' === $notification['id'] ) {
		return array();
	}

	$notification['group'] = sanitize_key( (string) $notification['group'] );
	if ( '' === $notification['group'] ) {
		$notification['group'] = 'stack';
	}

	$type = sanitize_key( (string) $notification['type'] );
	if ( ! in_array( $type, array( 'error', 'warning', 'success', 'info' ), true ) ) {
		$type = 'info';
	}
	$notification['type'] = $type;

	$notification['title']   = trim( wp_strip_all_tags( (string) $notification['title'] ) );
	$notification['message'] = trim( wp_strip_all_tags( (string) $notification['message'] ) );
	$notification['source']  = trim( wp_strip_all_tags( (string) $notification['source'] ) );
	$notification['priority'] = absint( $notification['priority'] );

	$details = $notification['details'];
	if ( is_string( $details ) ) {
		$details = '' !== trim( $details ) ? array( $details ) : array();
	}

	if ( ! is_array( $details ) ) {
		$details = array();
	}

	$clean_details = array();
	foreach ( $details as $detail ) {
		$detail = trim( wp_strip_all_tags( (string) $detail ) );
		if ( '' !== $detail ) {
			$clean_details[] = $detail;
		}
	}

	$notification['details'] = array_values( array_unique( $clean_details ) );
	$notification['action_label'] = trim( wp_strip_all_tags( (string) $notification['action_label'] ) );
	$notification['action_url']   = esc_url_raw( (string) $notification['action_url'] );

	return $notification;
}

/**
 * Compare two normalized notifications.
 *
 * @param array<string, mixed> $left  Left item.
 * @param array<string, mixed> $right Right item.
 * @return int
 */
function mrn_dashboard_support_compare_notifications( $left, $right ) {
	$left_priority  = isset( $left['priority'] ) ? absint( $left['priority'] ) : 100;
	$right_priority = isset( $right['priority'] ) ? absint( $right['priority'] ) : 100;

	if ( $left_priority !== $right_priority ) {
		return $left_priority <=> $right_priority;
	}

	$left_group_order  = mrn_dashboard_support_notification_group_order( isset( $left['group'] ) ? (string) $left['group'] : 'stack' );
	$right_group_order = mrn_dashboard_support_notification_group_order( isset( $right['group'] ) ? (string) $right['group'] : 'stack' );

	if ( $left_group_order !== $right_group_order ) {
		return $left_group_order <=> $right_group_order;
	}

	$left_title  = isset( $left['title'] ) ? (string) $left['title'] : '';
	$right_title = isset( $right['title'] ) ? (string) $right['title'] : '';

	return strcasecmp( $left_title, $right_title );
}

/**
 * Join a list of update names into a readable sentence fragment.
 *
 * @param array<int, string> $names  Item names.
 * @param int                $limit  Maximum number of names to include before truncation.
 * @return string
 */
function mrn_dashboard_support_join_names( $names, $limit = 3 ) {
	$names = array_values( array_filter( array_map( 'strval', (array) $names ) ) );
	$count = count( $names );

	if ( 0 === $count ) {
		return '';
	}

	if ( $count <= $limit ) {
		if ( 1 === $count ) {
			return $names[0];
		}

		$last = array_pop( $names );

		return implode( ', ', $names ) . ' and ' . $last;
	}

	$subset = array_slice( $names, 0, $limit );

	return implode( ', ', $subset ) . sprintf( ' and %d more', $count - $limit );
}

/**
 * Build the stack and WordPress notification list.
 *
 * @return array<int, array<string, mixed>>
 */
function mrn_dashboard_support_collect_notifications() {
	if ( ! mrn_dashboard_support_can_view_notifications() ) {
		return array();
	}

	$notifications = array();

	$stack_notifications = apply_filters( 'mrn_dashboard_support_notifications', array() );
	if ( is_array( $stack_notifications ) ) {
		$notifications = array_merge( $notifications, $stack_notifications );
	}

	$wordpress_notifications = mrn_dashboard_support_collect_wordpress_notifications();
	if ( ! empty( $wordpress_notifications ) ) {
		$notifications = array_merge( $notifications, $wordpress_notifications );
	}

	$captured_notifications = mrn_dashboard_support_get_captured_notifications();
	if ( ! empty( $captured_notifications ) ) {
		$notifications = array_merge( $notifications, $captured_notifications );
	}

	$normalized = array();
	foreach ( $notifications as $notification ) {
		$item = mrn_dashboard_support_normalize_notification( $notification );
		if ( empty( $item ) ) {
			continue;
		}

		$normalized[ $item['id'] ] = $item;
	}

	$normalized = array_values( $normalized );
	usort( $normalized, 'mrn_dashboard_support_compare_notifications' );

	return mrn_dashboard_support_apply_notification_state( $normalized );
}

/**
 * Collect basic WordPress update notifications.
 *
 * @return array<int, array<string, mixed>>
 */
function mrn_dashboard_support_collect_wordpress_notifications() {
	if ( ! mrn_dashboard_support_can_view_notifications() ) {
		return array();
	}

	$notifications = array();

	if ( function_exists( 'get_core_updates' ) ) {
		$core_updates = get_core_updates();
		if ( is_array( $core_updates ) ) {
			$available_updates = array();

			foreach ( $core_updates as $core_update ) {
				if ( is_object( $core_update ) && isset( $core_update->response ) && 'upgrade' === $core_update->response ) {
					$available_updates[] = $core_update;
				}
			}

			if ( ! empty( $available_updates ) ) {
				$latest          = reset( $available_updates );
				$current_version = is_object( $latest ) && isset( $latest->current ) ? trim( (string) $latest->current ) : '';
				$target_version  = is_object( $latest ) && isset( $latest->version ) ? trim( (string) $latest->version ) : '';

				$message = '' !== $target_version
					? sprintf( 'WordPress %s is available.', $target_version )
					: 'A WordPress core update is available.';

				if ( '' !== $current_version && '' !== $target_version ) {
					$message .= sprintf( ' Current version: %s.', $current_version );
				}

				$notifications[] = array(
					'id'           => 'wordpress-core-update',
					'group'        => 'wordpress',
					'type'         => 'warning',
					'title'        => 'WordPress core update available',
					'message'      => $message,
					'details'      => array( 'Open the Updates screen to review release notes and run the core update when ready.' ),
					'action_label' => 'Open Updates',
					'action_url'   => admin_url( 'update-core.php' ),
					'source'       => 'WordPress',
					'priority'     => 300,
				);
			}
		}
	}

	if ( function_exists( 'get_plugin_updates' ) ) {
		$plugin_updates = get_plugin_updates();
		if ( is_array( $plugin_updates ) && ! empty( $plugin_updates ) ) {
			$plugin_names = array();
			foreach ( $plugin_updates as $plugin_file => $plugin_data ) {
				if ( is_object( $plugin_data ) && isset( $plugin_data->Name ) ) {
					$plugin_names[] = trim( (string) $plugin_data->Name );
					continue;
				}

				if ( is_array( $plugin_data ) && isset( $plugin_data['Name'] ) ) {
					$plugin_names[] = trim( (string) $plugin_data['Name'] );
					continue;
				}

				$plugin_names[] = trim( (string) $plugin_file );
			}

			$plugin_names = array_values( array_filter( array_map( 'sanitize_text_field', $plugin_names ) ) );
			$plugin_count = count( $plugin_names );

			if ( $plugin_count > 0 ) {
				$message = 1 === $plugin_count
					? sprintf( 'An update is available for %s.', $plugin_names[0] )
					: sprintf( '%d plugin updates are available.', $plugin_count );

				$details = array();
				if ( $plugin_count > 1 ) {
					$details[] = 'Affected plugins: ' . mrn_dashboard_support_join_names( $plugin_names ) . '.';
				}

				$notifications[] = array(
					'id'           => 'wordpress-plugin-updates',
					'group'        => 'wordpress',
					'type'         => 'warning',
					'title'        => 'Plugin updates available',
					'message'      => $message,
					'details'      => $details,
					'action_label' => 'Open Plugins',
					'action_url'   => admin_url( 'plugins.php?plugin_status=upgrade' ),
					'source'       => 'WordPress',
					'priority'     => 310,
				);
			}
		}
	}

	if ( function_exists( 'get_theme_updates' ) ) {
		$theme_updates = get_theme_updates();
		if ( is_array( $theme_updates ) && ! empty( $theme_updates ) ) {
			$theme_names = array();
			foreach ( $theme_updates as $theme_slug => $theme_data ) {
				if ( is_object( $theme_data ) && isset( $theme_data->Name ) ) {
					$theme_names[] = trim( (string) $theme_data->Name );
					continue;
				}

				if ( is_array( $theme_data ) && isset( $theme_data['Name'] ) ) {
					$theme_names[] = trim( (string) $theme_data['Name'] );
					continue;
				}

				$theme_names[] = trim( (string) $theme_slug );
			}

			$theme_names = array_values( array_filter( array_map( 'sanitize_text_field', $theme_names ) ) );
			$theme_count  = count( $theme_names );

			if ( $theme_count > 0 ) {
				$message = 1 === $theme_count
					? sprintf( 'An update is available for %s.', $theme_names[0] )
					: sprintf( '%d theme updates are available.', $theme_count );

				$details = array();
				if ( $theme_count > 1 ) {
					$details[] = 'Affected themes: ' . mrn_dashboard_support_join_names( $theme_names ) . '.';
				}

				$notifications[] = array(
					'id'           => 'wordpress-theme-updates',
					'group'        => 'wordpress',
					'type'         => 'warning',
					'title'        => 'Theme updates available',
					'message'      => $message,
					'details'      => $details,
					'action_label' => 'Open Themes',
					'action_url'   => admin_url( 'themes.php' ),
					'source'       => 'WordPress',
					'priority'     => 320,
				);
			}
		}
	}

	return $notifications;
}

/**
 * Register the Notifications Center top-level menu entry.
 */
function mrn_dashboard_support_register_notifications_page() {
	$menu_title = mrn_dashboard_support_notifications_menu_title();
	$notifications = mrn_dashboard_support_collect_notifications();
	$menu_title .= mrn_dashboard_support_notification_badge_html( mrn_dashboard_support_count_unread_notifications( $notifications ) );

	$hook = add_menu_page(
		mrn_dashboard_support_notifications_page_title(),
		$menu_title,
		mrn_dashboard_support_notifications_capability(),
		mrn_dashboard_support_notifications_page_slug(),
		'mrn_dashboard_support_render_notifications_page',
		'dashicons-bell',
		2.2
	);

	if ( $hook ) {
		$GLOBALS['mrn_dashboard_support_notification_page_hooks'][] = $hook;
	}
}
add_action( 'admin_menu', 'mrn_dashboard_support_register_notifications_page' );

/**
 * Keep the standard WordPress notice channels from duplicating the center.
 */
function mrn_dashboard_support_register_admin_notice_capture() {
	if ( ! is_admin() ) {
		return;
	}

	add_action( 'admin_notices', 'mrn_dashboard_support_begin_admin_notice_capture', -10000 );
	add_action( 'admin_notices', 'mrn_dashboard_support_finish_admin_notice_capture', PHP_INT_MAX );
}
add_action( 'plugins_loaded', 'mrn_dashboard_support_register_admin_notice_capture', 9999 );

/**
 * Style the Notifications Center menu icon when unread alerts need attention.
 */
function mrn_dashboard_support_render_notification_menu_styles() {
	if ( ! mrn_dashboard_support_can_view_notifications() ) {
		return;
	}

	$notifications = mrn_dashboard_support_collect_notifications();
	if ( ! mrn_dashboard_support_has_unread_alerts( $notifications ) ) {
		return;
	}
	?>
	<style>
		#adminmenu #toplevel_page_mrn-notifications-center .wp-menu-image:before {
			color: #d63638 !important;
			animation: mrn-notification-bell-pulse 1.8s ease-in-out infinite;
		}

		#adminmenu #toplevel_page_mrn-notifications-center .update-plugins {
			background: #d63638;
			color: #fff;
		}

		@keyframes mrn-notification-bell-pulse {
			0%, 100% { transform: rotate(0deg); }
			10%, 30%, 50% { transform: rotate(-9deg); }
			20%, 40%, 60% { transform: rotate(9deg); }
			70% { transform: rotate(0deg); }
		}

		@media (prefers-reduced-motion: reduce) {
			#adminmenu #toplevel_page_mrn-notifications-center .wp-menu-image:before {
				animation: none;
			}
		}
	</style>
	<?php
}
add_action( 'admin_head', 'mrn_dashboard_support_render_notification_menu_styles' );

if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	add_action( 'network_admin_menu', 'mrn_dashboard_support_register_notifications_page' );
}

/**
 * Process read and remove actions from the Notifications Center.
 */
function mrn_dashboard_support_process_notification_action() {
	if ( ! mrn_dashboard_support_can_view_notifications() || empty( $_POST['mrn_notification_action'] ) ) {
		return;
	}

	$action = sanitize_key( wp_unslash( $_POST['mrn_notification_action'] ) );
	if ( ! in_array( $action, array( 'read', 'unread', 'dismiss', 'mark_all_read', 'clear_all' ), true ) ) {
		return;
	}

	$nonce = isset( $_POST['mrn_notification_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['mrn_notification_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'mrn_notification_action' ) ) {
		return;
	}

	if ( in_array( $action, array( 'read', 'unread', 'dismiss' ), true ) ) {
		$notification_id = isset( $_POST['mrn_notification_id'] ) ? sanitize_key( wp_unslash( $_POST['mrn_notification_id'] ) ) : '';
		if ( '' !== $notification_id ) {
			if ( 'dismiss' === $action ) {
				mrn_dashboard_support_update_notification_state( $notification_id, 'dismissed' );
			} else {
				mrn_dashboard_support_update_notification_state( $notification_id, 'read', 'read' === $action );
			}
		}
	} else {
		foreach ( mrn_dashboard_support_collect_notifications() as $notification ) {
			if ( empty( $notification['id'] ) ) {
				continue;
			}

			if ( 'clear_all' === $action ) {
				mrn_dashboard_support_update_notification_state( (string) $notification['id'], 'dismissed' );
			} else {
				mrn_dashboard_support_update_notification_state( (string) $notification['id'], 'read' );
			}
		}
	}

	$redirect = wp_get_referer();
	if ( ! is_string( $redirect ) || '' === $redirect ) {
		$redirect = admin_url( 'admin.php?page=' . mrn_dashboard_support_notifications_page_slug() );
	}

	if ( 'read' === $action ) {
		$redirect = add_query_arg( 'mrn_notification_view', 'unread', $redirect );
	} elseif ( 'unread' === $action ) {
		$redirect = add_query_arg( 'mrn_notification_view', 'read', $redirect );
	}

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_init', 'mrn_dashboard_support_process_notification_action' );

/**
 * Render the Notifications Center page.
 */
function mrn_dashboard_support_render_notifications_page() {
	if ( ! mrn_dashboard_support_can_view_notifications() ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'mrn-dashboard-support' ) );
	}

	$all_notifications = mrn_dashboard_support_collect_notifications();
	$unread_notifications = array_values( array_filter( $all_notifications, static function ( $notification ) { return empty( $notification['read'] ); } ) );
	$read_notifications = array_values( array_filter( $all_notifications, static function ( $notification ) { return ! empty( $notification['read'] ); } ) );
	$view = isset( $_GET['mrn_notification_view'] ) ? sanitize_key( wp_unslash( $_GET['mrn_notification_view'] ) ) : 'unread';
	if ( ! in_array( $view, array( 'all', 'unread', 'read' ), true ) ) {
		$view = 'unread';
	}
	$notifications = 'read' === $view ? $read_notifications : ( 'all' === $view ? $all_notifications : $unread_notifications );
	$type_counts   = array(
		'error'   => 0,
		'warning' => 0,
		'success' => 0,
		'info'    => 0,
	);
	$grouped = array();

	foreach ( $notifications as $notification ) {
		$type = isset( $notification['type'] ) ? (string) $notification['type'] : 'info';
		if ( ! isset( $type_counts[ $type ] ) ) {
			$type = 'info';
		}

		$type_counts[ $type ]++;

		$group = isset( $notification['group'] ) ? (string) $notification['group'] : 'stack';
		if ( '' === $group ) {
			$group = 'stack';
		}

		if ( ! isset( $grouped[ $group ] ) ) {
			$grouped[ $group ] = array();
		}

		$grouped[ $group ][] = $notification;
	}

	if ( ! empty( $grouped ) ) {
		uksort(
			$grouped,
			static function ( $left, $right ) {
				$left_order  = mrn_dashboard_support_notification_group_order( (string) $left );
				$right_order = mrn_dashboard_support_notification_group_order( (string) $right );

				if ( $left_order !== $right_order ) {
					return $left_order <=> $right_order;
				}

				return strcasecmp(
					mrn_dashboard_support_notification_group_label( (string) $left ),
					mrn_dashboard_support_notification_group_label( (string) $right )
				);
			}
		);
	}
	?>
	<div class="wrap mrn-notifications-center" data-mrn-admin-ui-contract="1.1">
		<style>
			.mrn-notifications-center__controls {
				margin: 18px 0 0;
			}

			.mrn-notifications-center__item--read {
				opacity: 0.72;
			}

			.mrn-notifications-center__item--read .mrn-notifications-center__badge {
				filter: grayscale(0.35);
			}

			.mrn-notifications-center__item-actions {
				margin: 12px 0 0;
			}

			.mrn-notifications-center__tabs {
				margin: 20px 0 0;
			}

			.mrn-notifications-center__item--fading-out {
				animation: mrn-notification-fade-out 240ms ease forwards;
				pointer-events: none;
			}

			@keyframes mrn-notification-fade-out {
				from { opacity: 1; transform: translateY(0); }
				to { opacity: 0; transform: translateY(-8px); }
			}

			@media (prefers-reduced-motion: reduce) {
				.mrn-notifications-center__item--fading-out {
					animation-duration: 1ms;
				}
			}

			.mrn-notifications-center__intro {
				max-width: 760px;
				color: #50575e;
			}

			.mrn-notifications-center__summary {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
				gap: 12px;
				margin: 20px 0 0;
			}

			.mrn-notifications-center__summary-item {
				background: #fff;
				border: 1px solid #dcdcde;
				border-left: 4px solid #2271b1;
				border-radius: 4px;
				padding: 12px 14px;
			}

			.mrn-notifications-center__summary-item--error { border-left-color: #d63638; }
			.mrn-notifications-center__summary-item--warning { border-left-color: #dba617; }
			.mrn-notifications-center__summary-item--success { border-left-color: #00a32a; }
			.mrn-notifications-center__summary-item--info { border-left-color: #2271b1; }

			.mrn-notifications-center__summary-label {
				display: block;
				margin-bottom: 4px;
				font-size: 12px;
				font-weight: 600;
				letter-spacing: 0.02em;
				text-transform: uppercase;
				color: #646970;
			}

			.mrn-notifications-center__summary-value {
				font-size: 26px;
				font-weight: 700;
				line-height: 1.1;
			}

			.mrn-notifications-center__group {
				background: #fff;
				border: 1px solid #dcdcde;
				border-radius: 4px;
				margin-top: 18px;
				padding: 16px 18px;
			}

			.mrn-notifications-center__group-header {
				align-items: center;
				display: flex;
				flex-wrap: wrap;
				gap: 10px;
				justify-content: space-between;
				margin-bottom: 12px;
			}

			.mrn-notifications-center__group-header h2 {
				margin: 0;
			}

			.mrn-notifications-center__group-count {
				background: #f0f0f1;
				border-radius: 999px;
				color: #50575e;
				font-size: 12px;
				font-weight: 700;
				line-height: 1;
				padding: 4px 9px;
			}

			.mrn-notifications-center__item {
				border-top: 1px solid #dcdcde;
				padding: 16px 0;
			}

			.mrn-notifications-center__item:first-child {
				border-top: 0;
				padding-top: 0;
			}

			.mrn-notifications-center__item:last-child {
				padding-bottom: 0;
			}

			.mrn-notifications-center__item--error {
				border-left: 4px solid #d63638;
				padding-left: 12px;
			}

			.mrn-notifications-center__item--warning {
				border-left: 4px solid #dba617;
				padding-left: 12px;
			}

			.mrn-notifications-center__item--success {
				border-left: 4px solid #00a32a;
				padding-left: 12px;
			}

			.mrn-notifications-center__item--info {
				border-left: 4px solid #2271b1;
				padding-left: 12px;
			}

			.mrn-notifications-center__meta {
				align-items: center;
				display: flex;
				flex-wrap: wrap;
				gap: 8px;
				margin-bottom: 8px;
			}

			.mrn-notifications-center__badge {
				border-radius: 999px;
				display: inline-flex;
				font-size: 12px;
				font-weight: 700;
				line-height: 1;
				padding: 4px 8px;
			}

			.mrn-notifications-center__badge--error { background: #fcf0f1; color: #8a1f11; }
			.mrn-notifications-center__badge--warning { background: #fcf9e8; color: #755100; }
			.mrn-notifications-center__badge--success { background: #edfaef; color: #0a6b22; }
			.mrn-notifications-center__badge--info { background: #eef5fb; color: #0a4b78; }

			.mrn-notifications-center__source {
				color: #646970;
				font-size: 12px;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: 0.02em;
			}

			.mrn-notifications-center__item h3 {
				margin: 0 0 8px;
			}

			.mrn-notifications-center__item p {
				margin: 0 0 8px;
			}

			.mrn-notifications-center__details {
				margin: 0 0 12px 20px;
			}

			.mrn-notifications-center__details li {
				margin-bottom: 4px;
			}
		</style>
		<h1><?php echo esc_html( mrn_dashboard_support_notifications_page_title() ); ?></h1>
		<p class="mrn-notifications-center__intro">
			Stack, WordPress, and plugin notices are collected here for admins. Read notices stay available for reference; removed notices are hidden for your account.
		</p>
		<nav class="mrn-notifications-center__tabs nav-tab-wrapper" aria-label="Notification views">
			<a class="nav-tab<?php echo 'unread' === $view ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'mrn_notification_view', 'unread', admin_url( 'admin.php?page=' . mrn_dashboard_support_notifications_page_slug() ) ) ); ?>">Unread <span class="count">(<?php echo esc_html( (string) count( $unread_notifications ) ); ?>)</span></a>
			<a class="nav-tab<?php echo 'read' === $view ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'mrn_notification_view', 'read', admin_url( 'admin.php?page=' . mrn_dashboard_support_notifications_page_slug() ) ) ); ?>">Read <span class="count">(<?php echo esc_html( (string) count( $read_notifications ) ); ?>)</span></a>
			<a class="nav-tab<?php echo 'all' === $view ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'mrn_notification_view', 'all', admin_url( 'admin.php?page=' . mrn_dashboard_support_notifications_page_slug() ) ) ); ?>">All <span class="count">(<?php echo esc_html( (string) count( $all_notifications ) ); ?>)</span></a>
		</nav>
		<div class="mrn-notifications-center__controls mrn-admin-action-group">
			<form class="mrn-admin-inline-action" method="post">
				<?php wp_nonce_field( 'mrn_notification_action', 'mrn_notification_nonce' ); ?>
				<input type="hidden" name="mrn_notification_action" value="mark_all_read">
				<button type="submit" class="button">Mark all as read</button>
			</form>
			<form class="mrn-admin-inline-action" method="post">
				<?php wp_nonce_field( 'mrn_notification_action', 'mrn_notification_nonce' ); ?>
				<input type="hidden" name="mrn_notification_action" value="clear_all">
				<button type="submit" class="button">Clear all</button>
			</form>
		</div>

		<div class="mrn-notifications-center__summary" aria-label="Notification summary">
			<div class="mrn-notifications-center__summary-item mrn-notifications-center__summary-item--error">
				<span class="mrn-notifications-center__summary-label">Errors</span>
				<span class="mrn-notifications-center__summary-value"><?php echo esc_html( (string) $type_counts['error'] ); ?></span>
			</div>
			<div class="mrn-notifications-center__summary-item mrn-notifications-center__summary-item--warning">
				<span class="mrn-notifications-center__summary-label">Warnings</span>
				<span class="mrn-notifications-center__summary-value"><?php echo esc_html( (string) $type_counts['warning'] ); ?></span>
			</div>
			<div class="mrn-notifications-center__summary-item mrn-notifications-center__summary-item--success">
				<span class="mrn-notifications-center__summary-label">Success</span>
				<span class="mrn-notifications-center__summary-value"><?php echo esc_html( (string) $type_counts['success'] ); ?></span>
			</div>
			<div class="mrn-notifications-center__summary-item mrn-notifications-center__summary-item--info">
				<span class="mrn-notifications-center__summary-label">Info</span>
				<span class="mrn-notifications-center__summary-value"><?php echo esc_html( (string) $type_counts['info'] ); ?></span>
			</div>
		</div>

		<?php if ( empty( $notifications ) ) : ?>
			<div class="notice notice-success inline" style="margin-top:18px;">
				<p>No pending notifications.</p>
			</div>
		<?php else : ?>
			<?php foreach ( $grouped as $group_key => $items ) : ?>
				<section class="mrn-notifications-center__group" aria-labelledby="mrn-notifications-center-group-<?php echo esc_attr( $group_key ); ?>">
					<div class="mrn-notifications-center__group-header">
						<h2 id="mrn-notifications-center-group-<?php echo esc_attr( $group_key ); ?>"><?php echo esc_html( mrn_dashboard_support_notification_group_label( $group_key ) ); ?></h2>
						<span class="mrn-notifications-center__group-count"><?php echo esc_html( (string) count( $items ) ); ?></span>
					</div>

					<?php foreach ( $items as $notification ) : ?>
						<?php $read_class = ! empty( $notification['read'] ) ? ' mrn-notifications-center__item--read' : ''; ?>
						<article class="mrn-notifications-center__item mrn-notifications-center__item--<?php echo esc_attr( (string) $notification['type'] ); ?><?php echo esc_attr( $read_class ); ?>">
							<div class="mrn-notifications-center__meta">
								<span class="mrn-notifications-center__badge mrn-notifications-center__badge--<?php echo esc_attr( (string) $notification['type'] ); ?>">
									<?php echo esc_html( mrn_dashboard_support_notification_type_label( (string) $notification['type'] ) ); ?>
								</span>
								<?php if ( '' !== (string) $notification['source'] ) : ?>
									<span class="mrn-notifications-center__source"><?php echo esc_html( (string) $notification['source'] ); ?></span>
								<?php endif; ?>
							</div>

							<h3><?php echo esc_html( (string) $notification['title'] ); ?></h3>

							<?php if ( '' !== (string) $notification['message'] ) : ?>
								<p><?php echo esc_html( (string) $notification['message'] ); ?></p>
							<?php endif; ?>

							<?php if ( ! empty( $notification['details'] ) && is_array( $notification['details'] ) ) : ?>
								<ul class="mrn-notifications-center__details">
									<?php foreach ( $notification['details'] as $detail ) : ?>
										<li><?php echo esc_html( (string) $detail ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

							<?php if ( '' !== (string) $notification['action_url'] && '' !== (string) $notification['action_label'] ) : ?>
								<p class="mrn-notifications-center__actions">
									<a class="button button-primary" href="<?php echo esc_url( (string) $notification['action_url'] ); ?>">
										<?php echo esc_html( (string) $notification['action_label'] ); ?>
									</a>
								</p>
							<?php endif; ?>

							<div class="mrn-notifications-center__item-actions mrn-admin-action-group">
								<form class="mrn-admin-inline-action mrn-notification-state-form" method="post">
									<?php wp_nonce_field( 'mrn_notification_action', 'mrn_notification_nonce' ); ?>
									<input type="hidden" name="mrn_notification_action" value="<?php echo ! empty( $notification['read'] ) ? 'unread' : 'read'; ?>">
									<input type="hidden" name="mrn_notification_id" value="<?php echo esc_attr( (string) $notification['id'] ); ?>">
									<button type="submit" class="button"><?php echo ! empty( $notification['read'] ) ? 'Mark as unread' : 'Mark as read'; ?></button>
								</form>
								<form class="mrn-admin-inline-action mrn-notification-state-form" method="post">
									<?php wp_nonce_field( 'mrn_notification_action', 'mrn_notification_nonce' ); ?>
									<input type="hidden" name="mrn_notification_action" value="dismiss">
									<input type="hidden" name="mrn_notification_id" value="<?php echo esc_attr( (string) $notification['id'] ); ?>">
									<button type="submit" class="button-link button-link-delete">Remove</button>
								</form>
							</div>
						</article>
					<?php endforeach; ?>
				</section>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<script>
		document.querySelectorAll('.mrn-notification-state-form').forEach(function (form) {
			form.addEventListener('submit', function (event) {
				var action = form.querySelector('[name="mrn_notification_action"]');
				var item = form.closest('.mrn-notifications-center__item');

				if (!action || !item || !['read', 'dismiss'].includes(action.value)) {
					return;
				}

				event.preventDefault();
				item.classList.add('mrn-notifications-center__item--fading-out');
				window.setTimeout(function () {
					form.submit();
				}, 240);
			});
		});
	</script>
	<?php
}

/**
 * Register the dashboard widget.
 */
add_action(
	'wp_dashboard_setup',
	function () {
		wp_add_dashboard_widget(
			'mrn_support_widget',
			'MRN Web Designs - Website Support',
			'mrn_render_support_widget'
		);
	}
);

/**
 * Lock down the widget UI completely (CSS).
 *
 * - Removes collapse arrow
 * - Removes hamburger menu
 * - Removes move arrows
 * - Prevents dragging visuals
 */
add_action(
	'admin_head',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'dashboard' !== $screen->id ) {
			return;
		}
		?>
		<style>
			/* --------------------------------------------------
			 * MRN Support Widget - HARD UI LOCKDOWN
			 * -------------------------------------------------- */

			/* Remove collapse toggle */
			#mrn_support_widget .handlediv {
				display: none !important;
			}

			/* Remove hamburger (three-dot) menu */
			#mrn_support_widget .postbox-header .handle-actions {
				display: none !important;
			}

			/* Disable header interaction entirely */
			#mrn_support_widget .hndle {
				cursor: default !important;
				pointer-events: none !important;
			}

			/* Prevent closed state */
			#mrn_support_widget.postbox.closed {
				display: block !important;
			}

			/* Kill sortable visuals */
			#mrn_support_widget.ui-sortable-handle {
				cursor: default !important;
			}
		</style>
		<?php
	}
);

/**
 * Disable dashboard sorting behavior for this widget (JS).
 *
 * This prevents WordPress from re-enabling move arrows
 * via the handle-actions menu.
 */
add_action(
	'admin_footer',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'dashboard' !== $screen->id ) {
			return;
		}
		?>
		<script>
			(function () {
				if (typeof jQuery === 'undefined') return;

				jQuery(function ($) {
					var $widget = $('#mrn_support_widget');
					if (!$widget.length) return;

					// Remove sortable behavior entirely for this widget
					$widget.removeClass('ui-sortable-handle');

					// Disable dashboard sortable instance (safe no-op if missing)
					try {
						$('#dashboard-widgets').sortable('disable');
					} catch (e) {}

					// Move widget to top-left column once
					var $leftColumn = $('#dashboard-widgets .postbox-container').first();
					if ($leftColumn.length && !$widget.is(':first-child')) {
						$leftColumn.prepend($widget);
					}
				});
			})();
		</script>
		<?php
	}
);

/**
 * Save launch date once; ignore future updates.
 */
add_action(
	'admin_init',
	function () {
		if ( ! is_admin() ) {
			return;
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== strtoupper( $request_method ) ) {
			return;
		}

		if ( ! isset( $_POST['mrn_launch_date_nonce'] ) || ! isset( $_POST['mrn_launch_date'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mrn_launch_date_nonce'] ) ), 'mrn_save_launch_date' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$launch_date_override = trim( (string) MRN_LAUNCH_DATE_OVERRIDE );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $launch_date_override ) ) {
			return;
		}

		// Once set, the launch date is immutable.
		if ( get_option( 'mrn_launch_date' ) ) {
			return;
		}

		$launch_date = sanitize_text_field( wp_unslash( $_POST['mrn_launch_date'] ) );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $launch_date ) ) {
			return;
		}

		update_option( 'mrn_launch_date', $launch_date );
	}
);

/**
 * Render the dashboard widget content.
 */
function mrn_render_support_widget() {
	$company_name  = 'MRN Web Designs';
	$support_email = 'maintenance@mrnwebdesigns.com';

	$site_url   = site_url();
	$wp_version = get_bloginfo( 'version' );

	$launch_date_override = trim( (string) MRN_LAUNCH_DATE_OVERRIDE );
	$has_valid_override   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $launch_date_override ) === 1;
	$launch_date_saved    = get_option( 'mrn_launch_date', '' );
	$launch_date          = $has_valid_override ? $launch_date_override : $launch_date_saved;

	$site_age_text = '';
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $launch_date ) === 1 ) {
		$launch_timestamp = strtotime( $launch_date . ' 00:00:00' );
		if ( false !== $launch_timestamp ) {
			$now_timestamp = current_time( 'timestamp' );
			$age_days      = max( 0, (int) floor( ( $now_timestamp - $launch_timestamp ) / DAY_IN_SECONDS ) );
			$age_weeks     = (int) floor( $age_days / 7 );
			$age_years     = (int) floor( $age_days / 365.2425 );

			$site_age_text = sprintf(
				'This site is now %1$d day%2$s old (%3$d week%4$s, %5$d year%6$s).',
				$age_days,
				1 === $age_days ? '' : 's',
				$age_weeks,
				1 === $age_weeks ? '' : 's',
				$age_years,
				1 === $age_years ? '' : 's'
			);
		}
	}

	$logo_file = __DIR__ . '/mrn-logo.png';
	$logo_url  = content_url( 'mu-plugins/mrn-dashboard-support/mrn-logo.png' );

	// Fallback for non-standard setups where content_url does not map as expected.
	if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
		$host = preg_replace( '/:\d+$/', '', sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) );
		if ( is_string( $host ) && '' !== $host ) {
			$fallback_url = ( is_ssl() ? 'https://' : 'http://' ) . $host . '/wp-content/mu-plugins/mrn-dashboard-support/mrn-logo.png';
			$logo_url     = is_string( $logo_url ) && '' !== $logo_url ? $logo_url : $fallback_url;
		}
	}

	// Email construction.
	$email_subject = rawurlencode( 'WordPress Support Request - ' . $site_url );
	$email_body    = rawurlencode(
		"\n\n\n" .
		"----------------------------------\n" .
		"Site Details (auto-generated)\n" .
		"----------------------------------\n" .
		"Site URL:\n{$site_url}\n\n" .
		"WordPress Version:\n{$wp_version}\n"
	);

	$mailto_link = "mailto:{$support_email}?subject={$email_subject}&body={$email_body}";

	echo '<div style="text-align:left;">';

	if ( file_exists( $logo_file ) ) {
		echo '<p style="margin:0 0 12px 0;">';
		echo '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $company_name ) . ' Logo" style="max-width:220px;height:auto;">';
		echo '</p>';
	}

	echo '<p style="margin:0 0 8px 0;"><strong>' . esc_html( $company_name ) . '</strong></p>';

	echo '<p style="margin:0;font-size:13px;"><strong>Site URL:</strong><br>' . esc_html( $site_url ) . '</p>';
	echo '<p style="margin:6px 0 12px 0;font-size:13px;"><strong>WordPress Version:</strong><br>' . esc_html( $wp_version ) . '</p>';

	echo '<form method="post" style="margin:0 0 12px 0;">';
	wp_nonce_field( 'mrn_save_launch_date', 'mrn_launch_date_nonce' );

	echo '<label for="mrn_launch_date" style="display:block;margin:0 0 4px 0;font-size:13px;"><strong>Launch Date:</strong></label>';

	if ( ! empty( $launch_date ) ) {
		echo '<input id="mrn_launch_date" type="date" value="' . esc_attr( $launch_date ) . '" disabled style="margin:0 0 4px 0;max-width:220px;width:100%;">';
		if ( $has_valid_override ) {
			echo '<p style="margin:0;font-size:12px;color:#555;">Date is set from MRN_LAUNCH_DATE_OVERRIDE in this file.</p>';
		}
		if ( ! empty( $site_age_text ) ) {
			echo '<p style="margin:4px 0 0 0;font-size:12px;color:#555;">' . esc_html( $site_age_text ) . '</p>';
		}
	} else {
		echo '<input id="mrn_launch_date" name="mrn_launch_date" type="date" required style="margin:0 0 8px 0;max-width:220px;width:100%">';
		submit_button( 'Save Date', 'secondary small', 'submit', false );
	}

	echo '</form>';

	echo '<p style="margin:0;">';
	echo '<a href="' . esc_url( $mailto_link ) . '" style="display:inline-block;padding:8px 14px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;font-size:13px;">Contact MRN Support</a>';
	echo '</p>';

	echo '<p style="margin-top:10px;font-size:12px;color:#555;">';
	echo 'Click the button to open your email client with our support address (<a href="' . esc_url( 'mailto:' . $support_email ) . '">' . esc_html( $support_email ) . '</a>) and site details already filled in, so you can start typing your message right away.';
	echo '</p>';

	echo '</div>';
}
