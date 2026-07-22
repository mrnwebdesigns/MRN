<?php

namespace MRN_Mega_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes standard WordPress nav menus the user-facing mega menu objects.
 */
final class Menu_Admin {
	private const PAGE_SLUG = 'mrn-mega-menus';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_layout_list' ) );
		add_action( 'admin_post_mrn_mega_menu_save_menu', array( __CLASS__, 'save_menu' ) );
		add_action( 'admin_post_mrn_mega_menu_save_all', array( __CLASS__, 'save_all_menus' ) );
		add_action( 'admin_post_mrn_mega_menu_delete_layout', array( __CLASS__, 'delete_layout' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_navigation_manager_assets' ) );
		add_action( 'wp_nav_menu_item_custom_fields', array( __CLASS__, 'render_menu_item_icon_fields' ), 10, 2 );
		add_action( 'wp_update_nav_menu_item', array( __CLASS__, 'save_menu_item_icon_fields' ), 10, 2 );
		add_filter( 'parent_file', array( __CLASS__, 'highlight_workspace_menu' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'highlight_workspace_submenu' ) );
	}

	/**
	 * Load the shared icon chooser on the native WordPress menu manager.
	 *
	 * @param string $hook_suffix Current admin hook.
	 */
	public static function enqueue_navigation_manager_assets( $hook_suffix ) {
		if ( 'nav-menus.php' !== $hook_suffix ) {
			return;
		}

		$dependencies = array( 'jquery' );
		if ( function_exists( 'mrn_shared_assets_enqueue_admin_icon_chooser' ) ) {
			mrn_shared_assets_enqueue_admin_icon_chooser();
			$dependencies[] = 'mrn-shared-icon-chooser';
		}
		wp_enqueue_media();
		wp_enqueue_style( 'mrn-mega-menu-admin', MRN_MEGA_MENU_URL . 'assets/css/admin.css', array(), MRN_MEGA_MENU_VERSION );
		wp_enqueue_script( 'mrn-mega-menu-icon-controls', MRN_MEGA_MENU_URL . 'assets/js/icon-controls.js', $dependencies, MRN_MEGA_MENU_VERSION, true );
	}

	/**
	 * Add icon controls to WordPress' native menu-item cards.
	 *
	 * @param int      $item_id Menu item ID.
	 * @param \WP_Post $item    Menu item post.
	 */
	public static function render_menu_item_icon_fields( $item_id, $item ) {
		unset( $item );
		self::render_native_icon_control( $item_id, 'item', __( 'Menu item icon', 'mrn-mega-menu' ), Plugin::get_menu_item_icon( $item_id, 'item' ) );
		self::render_native_icon_control( $item_id, 'arrow', __( 'Navigation arrow icon', 'mrn-mega-menu' ), Plugin::get_menu_item_icon( $item_id, 'arrow' ) );
	}

	private static function render_native_icon_control( $item_id, $role, $label, $icon ) {
		?>
		<div class="field-mrn-mega-icon description description-wide mrn-mm-native-icon-field">
			<div class="mrn-mm-icon-control" data-mrn-icon-control>
				<span class="mrn-mm-icon-control__label"><?php echo esc_html( $label ); ?></span>
				<input type="hidden" data-icon-value name="menu-item-mrn-<?php echo esc_attr( $role ); ?>-icon[<?php echo esc_attr( $item_id ); ?>]" value="<?php echo esc_attr( wp_json_encode( $icon ) ); ?>">
				<span class="mrn-mm-icon-preview" data-icon-preview aria-hidden="true"></span>
				<button type="button" class="button" data-choose-icon><?php esc_html_e( 'Choose icon', 'mrn-mega-menu' ); ?></button>
				<button type="button" class="button-link" data-clear-icon<?php echo $icon['value'] ? '' : ' hidden'; ?>><?php esc_html_e( 'Clear', 'mrn-mega-menu' ); ?></button>
			</div>
			<small><?php echo 'arrow' === $role ? esc_html__( 'Used after the label to indicate navigation or an expandable mega menu.', 'mrn-mega-menu' ) : esc_html__( 'Displayed before this item’s label wherever the mega-menu renderer controls output.', 'mrn-mega-menu' ); ?></small>
		</div>
		<?php
	}

	/**
	 * Save native menu-item icon metadata during WordPress' normal menu save.
	 *
	 * @param int $menu_id      Menu term ID.
	 * @param int $menu_item_id Menu item post ID.
	 */
	public static function save_menu_item_icon_fields( $menu_id, $menu_item_id ) {
		unset( $menu_id );
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		$fields = array(
			'item'  => Plugin::ITEM_META_ICON,
			'arrow' => Plugin::ITEM_META_ARROW_ICON,
		);
		foreach ( $fields as $role => $meta_key ) {
			$request_key = 'menu-item-mrn-' . $role . '-icon';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Core verifies the menu nonce; Plugin::sanitize_icon validates the selected item's JSON value below.
			$values = isset( $_POST[ $request_key ] ) && is_array( $_POST[ $request_key ] ) ? wp_unslash( $_POST[ $request_key ] ) : array();
			$icon   = Plugin::sanitize_icon( $values[ $menu_item_id ] ?? array() );
			if ( $icon['value'] ) {
				update_post_meta( $menu_item_id, $meta_key, $icon );
			} else {
				delete_post_meta( $menu_item_id, $meta_key );
			}
		}
	}

	public static function register_page() {
		add_theme_page(
			__( 'Mega Menus', 'mrn-mega-menu' ),
			__( 'Mega Menus', 'mrn-mega-menu' ),
			'edit_theme_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Keep old Mega Layout list bookmarks pointed at the unified workspace.
	 */
	public static function redirect_legacy_layout_list() {
		global $pagenow;
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing.
		$post_status = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing.
		if ( 'edit.php' !== $pagenow || Plugin::POST_TYPE !== $post_type || 'trash' === $post_status || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'view' => 'layouts' ), admin_url( 'themes.php' ) ) );
		exit;
	}

	public static function render_page() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage menus.', 'mrn-mega-menu' ) );
		}

		$view       = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'menus'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Controls display only.
		$view       = in_array( $view, array( 'menus', 'layouts' ), true ) ? $view : 'menus';
		$menus      = wp_get_nav_menus();
		$layouts    = get_posts(
			array(
				'post_type'      => Plugin::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$published_layouts = array_values(
			array_filter(
				$layouts,
				static function ( $layout ) {
					return 'publish' === $layout->post_status;
				}
			)
		);
		$locations  = get_registered_nav_menus();
		$assigned   = get_nav_menu_locations();
		$header_id  = absint( get_option( 'options_header_primary_menu_id', 0 ) );
		$menus_url   = add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'themes.php' ) );
		$layouts_url = add_query_arg( array( 'page' => self::PAGE_SLUG, 'view' => 'layouts' ), admin_url( 'themes.php' ) );
		$has_toolbar = self::load_sticky_toolbar();

		if ( $has_toolbar ) {
			self::render_sticky_toolbar( $view, $menus_url, $layouts_url );
		}
		?>
		<div class="wrap mrn-mega-menu-workspace" data-mrn-admin-ui-contract="1.1.0">
			<form id="mrn-mega-menu-save-all" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" hidden>
				<input type="hidden" name="action" value="mrn_mega_menu_save_all">
				<input type="hidden" name="menu_settings" value="">
				<?php wp_nonce_field( 'mrn_mega_menu_save_all' ); ?>
			</form>
			<h1><?php esc_html_e( 'Mega Menus', 'mrn-mega-menu' ); ?></h1>
			<?php if ( ! $has_toolbar ) : ?>
				<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Mega Menu workspace', 'mrn-mega-menu' ); ?>">
					<a class="nav-tab<?php echo 'menus' === $view ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( $menus_url ); ?>"><?php esc_html_e( 'Menus', 'mrn-mega-menu' ); ?></a>
					<a class="nav-tab<?php echo 'layouts' === $view ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( $layouts_url ); ?>"><?php esc_html_e( 'Reusable Layouts', 'mrn-mega-menu' ); ?></a>
				</nav>
			<?php endif; ?>
			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only status flag. ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Menu settings saved.', 'mrn-mega-menu' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['layout_trashed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only status flag. ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Layout moved to Trash. Any menu that used it now falls back to automatic parent and child content.', 'mrn-mega-menu' ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'menus' === $view ) : ?>
			<p class="description"><?php esc_html_e( 'Every entry below is a native WordPress menu. Enable mega behavior when its top-level parent items should open their children in responsive panels.', 'mrn-mega-menu' ); ?></p>
			<div class="notice notice-info inline">
				<p><strong><?php esc_html_e( 'Navigation management:', 'mrn-mega-menu' ); ?></strong> <?php esc_html_e( 'Use the native WordPress menu editor to add, reorder, or nest top-level items and to assign each item’s leading icon and navigation arrow.', 'mrn-mega-menu' ); ?></p>
				<p><strong><?php esc_html_e( 'Automatic:', 'mrn-mega-menu' ); ?></strong> <?php esc_html_e( 'each top-level parent with children becomes a trigger, and its own children fill the panel. No reusable layout is used.', 'mrn-mega-menu' ); ?></p>
				<p><strong><?php esc_html_e( 'Reusable layout:', 'mrn-mega-menu' ); ?></strong> <?php esc_html_e( 'applies the same column and content recipe to every eligible parent. A “Children of the assigned menu item” block changes automatically for the parent being opened.', 'mrn-mega-menu' ); ?></p>
				<?php if ( empty( $published_layouts ) ) : ?>
					<p><strong><?php esc_html_e( 'Why only Automatic?', 'mrn-mega-menu' ); ?></strong> <?php esc_html_e( 'There are no published reusable layouts. Draft and trashed layouts do not appear in the selector.', 'mrn-mega-menu' ); ?> <a href="<?php echo esc_url( $layouts_url ); ?>"><?php esc_html_e( 'Manage reusable layouts', 'mrn-mega-menu' ); ?></a></p>
				<?php endif; ?>
			</div>
			<table class="widefat striped" style="margin-top: 20px;">
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'WordPress menu', 'mrn-mega-menu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Used in', 'mrn-mega-menu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Behavior', 'mrn-mega-menu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Template use', 'mrn-mega-menu' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $menus as $menu ) : ?>
					<?php
					$menu_id       = absint( $menu->term_id );
					$enabled       = Plugin::is_menu_mega( $menu_id );
					$layout_id     = Plugin::get_menu_layout_id( $menu_id );
					$usage         = self::menu_usage( $menu_id, $header_id, $locations, $assigned );
					$trigger_count = self::parent_trigger_count( $menu_id );
					$sources       = self::content_sources( $layout_id );
					$edit_layout_url = $layout_id ? get_edit_post_link( $layout_id, 'raw' ) : '';
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $menu->name ); ?></strong>
							<?php if ( $enabled ) : ?><span class="tag" style="margin-left:6px;"><?php esc_html_e( 'Mega', 'mrn-mega-menu' ); ?></span><?php endif; ?>
							<p><a href="<?php echo esc_url( admin_url( 'nav-menus.php?action=edit&menu=' . $menu_id ) ); ?>"><?php esc_html_e( 'Manage navigation items, hierarchy, and icons', 'mrn-mega-menu' ); ?></a></p>
						</td>
						<td><?php echo esc_html( $usage ? implode( ', ', $usage ) : __( 'Not currently placed', 'mrn-mega-menu' ) ); ?></td>
						<td>
							<div class="mrn-mega-menu-setting" data-menu-id="<?php echo esc_attr( $menu_id ); ?>">
								<label><input type="checkbox" name="mega_enabled" value="1" <?php checked( $enabled ); ?>> <?php esc_html_e( 'Enable mega behavior', 'mrn-mega-menu' ); ?></label>
								<p class="description"><?php echo esc_html( sprintf( _n( '%d parent with children', '%d parents with children', $trigger_count, 'mrn-mega-menu' ), $trigger_count ) ); ?></p>
								<label for="mrn-mega-layout-<?php echo esc_attr( $menu_id ); ?>"><?php esc_html_e( 'Layout', 'mrn-mega-menu' ); ?></label><br>
								<select id="mrn-mega-layout-<?php echo esc_attr( $menu_id ); ?>" name="layout_id">
									<option value="0"><?php esc_html_e( 'Automatic — parent’s children', 'mrn-mega-menu' ); ?></option>
									<?php foreach ( $published_layouts as $layout ) : ?>
									<option value="<?php echo esc_attr( $layout->ID ); ?>" data-edit-url="<?php echo esc_url( get_edit_post_link( $layout->ID, 'raw' ) ); ?>" <?php selected( $layout_id, $layout->ID ); ?>><?php echo esc_html( $layout->post_title ? $layout->post_title : sprintf( __( 'Untitled layout #%d', 'mrn-mega-menu' ), $layout->ID ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<a class="button-link mrn-mega-edit-selected-layout" href="<?php echo esc_url( $edit_layout_url ); ?>"<?php echo $edit_layout_url ? '' : ' hidden'; ?>><?php esc_html_e( 'Edit layout', 'mrn-mega-menu' ); ?></a>
								<p class="description"><?php esc_html_e( 'Choose Automatic for the native parent → children behavior. Choose a published layout only when every parent should use that reusable panel recipe.', 'mrn-mega-menu' ); ?></p>
								<p class="description"><strong><?php esc_html_e( 'Content:', 'mrn-mega-menu' ); ?></strong> <?php echo esc_html( implode( '; ', $sources ) ); ?></p>
							</div>
						</td>
						<td><code>wp_nav_menu( array( 'menu' =&gt; <?php echo esc_html( $menu_id ); ?> ) );</code></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $menus ) ) : ?>
					<tr><td colspan="4"><a href="<?php echo esc_url( admin_url( 'nav-menus.php?action=edit&menu=0' ) ); ?>"><?php esc_html_e( 'Create your first WordPress menu', 'mrn-mega-menu' ); ?></a></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
			<p>
				<?php if ( ! $has_toolbar ) : ?><button type="submit" class="button button-primary" form="mrn-mega-menu-save-all"><?php esc_html_e( 'Save settings', 'mrn-mega-menu' ); ?></button><?php endif; ?>
				<a class="button" href="<?php echo esc_url( $layouts_url ); ?>"><?php esc_html_e( 'Manage reusable layouts', 'mrn-mega-menu' ); ?></a>
			</p>
			<?php else : ?>
				<?php self::render_layouts_view( $layouts, $menus ); ?>
			<?php endif; ?>
		</div>
		<script>
		(function () {
			var bulkForm = document.getElementById('mrn-mega-menu-save-all');
			if (!bulkForm) {
				return;
			}
			function syncMenuSettings(notify) {
				var settings = {};
				document.querySelectorAll('.mrn-mega-menu-setting[data-menu-id]').forEach(function (row) {
					settings[row.dataset.menuId] = {
						enabled: row.querySelector('input[name="mega_enabled"]').checked ? 1 : 0,
						layout_id: row.querySelector('select[name="layout_id"]').value
					};
				});
				var settingsInput = bulkForm.querySelector('input[name="menu_settings"]');
				settingsInput.value = JSON.stringify(settings);
				if (notify) {
					settingsInput.dispatchEvent(new Event('input', { bubbles: true }));
				}
			}
			bulkForm.addEventListener('submit', function () {
				syncMenuSettings(false);
			});
			document.addEventListener('change', function (event) {
				if (event.target.closest('.mrn-mega-menu-setting')) {
					syncMenuSettings(true);
				}
			});
			syncMenuSettings(false);
		}());
		document.addEventListener('change', function (event) {
			if (!event.target.matches('select[name="layout_id"]')) {
				return;
			}
			var link = event.target.parentElement.querySelector('.mrn-mega-edit-selected-layout');
			var option = event.target.selectedOptions[0];
			var editUrl = option ? option.dataset.editUrl || '' : '';
			if (link) {
				link.href = editUrl;
				link.hidden = !editUrl;
			}
		});
		</script>
		<?php
	}

	/**
	 * Load the shared toolbar contract when it exists, while remaining portable.
	 */
	private static function load_sticky_toolbar() {
		$available = function_exists( 'mrn_sticky_toolbar_render' ) && function_exists( 'mrn_sticky_toolbar_render_css' );
		if ( ! $available ) {
			if ( file_exists( MRN_MEGA_MENU_PATH . 'includes/mrn-sticky-settings-toolbar.php' ) ) {
				require_once MRN_MEGA_MENU_PATH . 'includes/mrn-sticky-settings-toolbar.php';
			}
			$available = function_exists( 'mrn_sticky_toolbar_render' ) && function_exists( 'mrn_sticky_toolbar_render_css' );
		}

		return (bool) apply_filters( 'mrn_mega_menu_use_sticky_toolbar', $available );
	}

	private static function render_sticky_toolbar( $view, $menus_url, $layouts_url ) {
		if ( function_exists( 'mrn_sticky_toolbar_universal_css' ) ) {
			echo '<style>' . mrn_sticky_toolbar_universal_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted shared stack CSS.
		}

		mrn_sticky_toolbar_render_css(
			array(
				'toolbar_id'          => 'mrn-mega-menu-workspace-toolbar',
				'page_class'          => 'appearance_page_' . self::PAGE_SLUG,
				'desktop_left'        => 160,
				'desktop_right'       => 0,
				'mobile_left'         => 10,
				'mobile_right'        => 10,
				'spacer_height'       => 88,
				'spacer_height_mobile' => 120,
			)
		);
		mrn_sticky_toolbar_render(
			array(
				'toolbar_id' => 'mrn-mega-menu-workspace-toolbar',
				'form_id'    => 'menus' === $view ? 'mrn-mega-menu-save-all' : '',
				'title'      => __( 'Mega Menu Workspace', 'mrn-mega-menu' ),
				'save_label' => __( 'Save settings', 'mrn-mega-menu' ),
				'show_save'  => 'menus' === $view,
				'aria_label' => __( 'Mega Menu workspace', 'mrn-mega-menu' ),
				'tabs'       => array(
					array(
						'key'    => 'menus',
						'label'  => __( 'Menus', 'mrn-mega-menu' ),
						'icon'   => 'dashicons-menu-alt3',
						'active' => 'menus' === $view,
					),
					array(
						'key'    => 'layouts',
						'label'  => __( 'Reusable Layouts', 'mrn-mega-menu' ),
						'icon'   => 'dashicons-screenoptions',
						'active' => 'layouts' === $view,
					),
				),
			)
		);
		?>
		<script>
		(function () {
			var destinations = <?php echo wp_json_encode( array( 'menus' => $menus_url, 'layouts' => $layouts_url ) ); ?>;
			document.addEventListener('click', function (event) {
				var tab = event.target.closest('#mrn-mega-menu-workspace-toolbar [data-mrn-tab]');
				if (!tab || !destinations[tab.dataset.mrnTab]) {
					return;
				}
				window.location.assign(destinations[tab.dataset.mrnTab]);
			}, true);
		})();
		</script>
		<?php
	}

	private static function render_layouts_view( $layouts, $menus ) {
		$new_url     = admin_url( 'post-new.php?post_type=' . Plugin::POST_TYPE );
		$trash_url   = add_query_arg( array( 'post_type' => Plugin::POST_TYPE, 'post_status' => 'trash' ), admin_url( 'edit.php' ) );
		$post_counts = wp_count_posts( Plugin::POST_TYPE );
		$trash_count = $post_counts && isset( $post_counts->trash ) ? absint( $post_counts->trash ) : 0;
		?>
		<div class="mrn-mega-layouts-heading">
			<div>
				<h2><?php esc_html_e( 'Reusable Layouts', 'mrn-mega-menu' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Build a shared panel recipe, then select it from a mega-enabled WordPress menu. Only published layouts appear in menu Layout selectors.', 'mrn-mega-menu' ); ?></p>
			</div>
			<div class="mrn-mega-layouts-actions mrn-admin-action-group">
				<?php if ( $trash_count ) : ?>
					<a class="button" href="<?php echo esc_url( $trash_url ); ?>"><?php echo esc_html( sprintf( _n( 'Trash (%d)', 'Trash (%d)', $trash_count, 'mrn-mega-menu' ), $trash_count ) ); ?></a>
				<?php endif; ?>
				<a class="button button-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add reusable layout', 'mrn-mega-menu' ); ?></a>
			</div>
		</div>
		<table class="widefat striped" style="margin-top: 20px;">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Layout', 'mrn-mega-menu' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'mrn-mega-menu' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Used by', 'mrn-mega-menu' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Content', 'mrn-mega-menu' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $layouts as $layout ) : ?>
				<?php $usage = self::layout_usage( $layout->ID, $menus ); ?>
				<tr>
					<td>
						<strong><a href="<?php echo esc_url( get_edit_post_link( $layout->ID ) ); ?>"><?php echo esc_html( $layout->post_title ? $layout->post_title : sprintf( __( 'Untitled layout #%d', 'mrn-mega-menu' ), $layout->ID ) ); ?></a></strong>
						<div class="row-actions">
							<span class="edit"><a href="<?php echo esc_url( get_edit_post_link( $layout->ID ) ); ?>"><?php esc_html_e( 'Edit', 'mrn-mega-menu' ); ?></a> | </span>
							<form class="mrn-mega-layout-delete-form mrn-admin-inline-action" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="<?php esc_attr_e( 'Move this reusable layout to Trash? Menus using it will switch to automatic content.', 'mrn-mega-menu' ); ?>">
								<input type="hidden" name="action" value="mrn_mega_menu_delete_layout">
								<input type="hidden" name="layout_id" value="<?php echo esc_attr( $layout->ID ); ?>">
								<?php wp_nonce_field( 'mrn_mega_menu_delete_layout_' . $layout->ID ); ?>
								<button class="button-link-delete" type="submit"><?php esc_html_e( 'Move to Trash', 'mrn-mega-menu' ); ?></button>
							</form>
						</div>
					</td>
					<td><?php echo esc_html( get_post_status_object( $layout->post_status )->label ); ?></td>
					<td><?php echo esc_html( $usage ? implode( ', ', $usage ) : __( 'Not currently assigned', 'mrn-mega-menu' ) ); ?></td>
					<td><?php echo esc_html( implode( '; ', self::content_sources( $layout->ID ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( empty( $layouts ) ) : ?>
				<tr><td colspan="4"><a href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Build your first reusable layout', 'mrn-mega-menu' ); ?></a></td></tr>
			<?php endif; ?>
			</tbody>
		</table>
		<style>
		.mrn-mega-layouts-heading { align-items: center; display: flex; gap: 24px; justify-content: space-between; margin-top: 18px; }
		.mrn-mega-layouts-heading h2 { margin-bottom: 4px; }
		.mrn-mega-layouts-actions { display: flex; flex-wrap: wrap; gap: 8px; }
		.mrn-mega-layout-delete-form { display: inline; }
		@media (max-width: 782px) { .mrn-mega-layouts-heading { align-items: flex-start; flex-direction: column; gap: 12px; } }
		</style>
		<script>
		document.addEventListener('submit', function (event) {
			var form = event.target.closest('.mrn-mega-layout-delete-form');
			if (form && !window.confirm(form.dataset.confirm)) {
				event.preventDefault();
			}
		});
		</script>
		<?php
	}

	private static function layout_usage( $layout_id, $menus ) {
		$usage = array();
		foreach ( $menus as $menu ) {
			if ( Plugin::is_menu_mega( $menu->term_id ) && $layout_id === Plugin::get_menu_layout_id( $menu->term_id ) ) {
				$usage[] = $menu->name;
			}
		}
		return $usage;
	}

	public static function highlight_workspace_menu( $parent_file ) {
		$screen = get_current_screen();
		return $screen && Plugin::POST_TYPE === $screen->post_type ? 'themes.php' : $parent_file;
	}

	public static function highlight_workspace_submenu( $submenu_file ) {
		$screen = get_current_screen();
		return $screen && Plugin::POST_TYPE === $screen->post_type ? 'themes.php?page=' . self::PAGE_SLUG : $submenu_file;
	}

	public static function save_menu() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage menus.', 'mrn-mega-menu' ) );
		}

		$menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;
		check_admin_referer( 'mrn_mega_menu_save_menu_' . $menu_id );
		if ( ! $menu_id || ! wp_get_nav_menu_object( $menu_id ) ) {
			wp_die( esc_html__( 'The selected WordPress menu does not exist.', 'mrn-mega-menu' ) );
		}

		$enabled   = ! empty( $_POST['mega_enabled'] );
		$layout_id = isset( $_POST['layout_id'] ) ? absint( $_POST['layout_id'] ) : 0;
		if ( $layout_id && ( Plugin::POST_TYPE !== get_post_type( $layout_id ) || 'publish' !== get_post_status( $layout_id ) ) ) {
			$layout_id = 0;
		}

		if ( $enabled ) {
			update_term_meta( $menu_id, Plugin::MENU_META_ENABLED, '1' );
			update_term_meta( $menu_id, Plugin::MENU_META_LAYOUT_ID, $layout_id );
		} else {
			delete_term_meta( $menu_id, Plugin::MENU_META_ENABLED );
			delete_term_meta( $menu_id, Plugin::MENU_META_LAYOUT_ID );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'updated' => '1' ), admin_url( 'themes.php' ) ) );
		exit;
	}

	public static function save_all_menus() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage menus.', 'mrn-mega-menu' ) );
		}
		check_admin_referer( 'mrn_mega_menu_save_all' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded and each value is validated below.
		$raw      = isset( $_POST['menu_settings'] ) && is_string( $_POST['menu_settings'] ) ? wp_unslash( $_POST['menu_settings'] ) : '';
		$settings = json_decode( $raw, true );
		if ( ! is_array( $settings ) ) {
			wp_die( esc_html__( 'The menu settings were not valid.', 'mrn-mega-menu' ) );
		}
		foreach ( $settings as $menu_id => $setting ) {
			$menu_id = absint( $menu_id );
			if ( ! $menu_id || ! is_array( $setting ) || ! wp_get_nav_menu_object( $menu_id ) ) {
				continue;
			}
			$enabled   = ! empty( $setting['enabled'] );
			$layout_id = isset( $setting['layout_id'] ) ? absint( $setting['layout_id'] ) : 0;
			if ( $layout_id && ( Plugin::POST_TYPE !== get_post_type( $layout_id ) || 'publish' !== get_post_status( $layout_id ) ) ) {
				$layout_id = 0;
			}
			if ( $enabled ) {
				update_term_meta( $menu_id, Plugin::MENU_META_ENABLED, '1' );
				update_term_meta( $menu_id, Plugin::MENU_META_LAYOUT_ID, $layout_id );
			} else {
				delete_term_meta( $menu_id, Plugin::MENU_META_ENABLED );
				delete_term_meta( $menu_id, Plugin::MENU_META_LAYOUT_ID );
			}
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'updated' => '1' ), admin_url( 'themes.php' ) ) );
		exit;
	}

	public static function delete_layout() {
		$layout_id = isset( $_POST['layout_id'] ) ? absint( $_POST['layout_id'] ) : 0;
		$layout    = $layout_id ? get_post( $layout_id ) : null;
		if ( ! $layout || Plugin::POST_TYPE !== $layout->post_type ) {
			wp_die( esc_html__( 'The selected reusable layout does not exist.', 'mrn-mega-menu' ) );
		}
		if ( ! current_user_can( 'delete_post', $layout_id ) ) {
			wp_die( esc_html__( 'You are not allowed to delete this reusable layout.', 'mrn-mega-menu' ) );
		}

		check_admin_referer( 'mrn_mega_menu_delete_layout_' . $layout_id );
		Admin::remove_assignment( $layout_id );
		if ( ! wp_trash_post( $layout_id ) ) {
			wp_die( esc_html__( 'The reusable layout could not be moved to Trash.', 'mrn-mega-menu' ) );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'view' => 'layouts', 'layout_trashed' => '1' ), admin_url( 'themes.php' ) ) );
		exit;
	}

	private static function parent_trigger_count( $menu_id ) {
		$items   = wp_get_nav_menu_items( $menu_id, array( 'update_post_term_cache' => false ) );
		$parents = array();
		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( absint( $item->menu_item_parent ) ) {
					$parents[ absint( $item->menu_item_parent ) ] = true;
				}
			}
		}
		return count( $parents );
	}

	/**
	 * Describe where a menu's mega content comes from.
	 *
	 * @param int $layout_id Optional Mega Layout post ID.
	 * @return array<int, string>
	 */
	private static function content_sources( $layout_id ) {
		if ( ! $layout_id ) {
			return array( __( 'Each parent’s children in this WordPress menu', 'mrn-mega-menu' ) );
		}

		$sources = array();
		$layout  = Plugin::get_layout( $layout_id );
		foreach ( isset( $layout['panels'] ) && is_array( $layout['panels'] ) ? $layout['panels'] : array() as $panel ) {
			foreach ( isset( $panel['columns'] ) && is_array( $panel['columns'] ) ? $panel['columns'] : array() as $column ) {
				foreach ( isset( $column['blocks'] ) && is_array( $column['blocks'] ) ? $column['blocks'] : array() as $block ) {
				$type = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
				if ( 'menu' === $type && isset( $block['source'] ) && 'selected' === $block['source'] ) {
					$source_menu = wp_get_nav_menu_object( isset( $block['menu_id'] ) ? absint( $block['menu_id'] ) : 0 );
					$root_item   = isset( $block['root_item_id'] ) ? get_post( absint( $block['root_item_id'] ) ) : null;
					if ( $source_menu ) {
						$sources[] = $root_item ? sprintf( __( '%1$s → %2$s branch', 'mrn-mega-menu' ), $source_menu->name, $root_item->post_title ) : sprintf( __( 'Entire %s menu', 'mrn-mega-menu' ), $source_menu->name );
					}
				} elseif ( 'menu' === $type ) {
					$sources[] = __( 'Each assigned parent’s children', 'mrn-mega-menu' );
				} elseif ( 'links' === $type ) {
					$sources[] = __( 'Custom links', 'mrn-mega-menu' );
				} elseif ( 'categories' === $type ) {
					$sources[] = __( 'WooCommerce categories', 'mrn-mega-menu' );
				} elseif ( 'products' === $type ) {
					$sources[] = __( 'WooCommerce products', 'mrn-mega-menu' );
				} elseif ( 'promo' === $type ) {
					$sources[] = __( 'Promotion content', 'mrn-mega-menu' );
				}
			}
		}
		}

		return $sources ? array_values( array_unique( $sources ) ) : array( __( 'Empty layout', 'mrn-mega-menu' ) );
	}

	private static function menu_usage( $menu_id, $header_id, $locations, $assigned ) {
		$usage = array();
		if ( $menu_id === $header_id ) {
			$usage[] = __( 'Stack Primary Header', 'mrn-mega-menu' );
		}
		foreach ( $assigned as $location => $assigned_id ) {
			if ( $menu_id === absint( $assigned_id ) && isset( $locations[ $location ] ) ) {
				$usage[] = $locations[ $location ];
			}
		}
		return array_values( array_unique( $usage ) );
	}
}
