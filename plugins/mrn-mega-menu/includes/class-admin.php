<?php

namespace MRN_Mega_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	private const NONCE_ACTION = 'mrn_mega_menu_save';
	private const NONCE_NAME = 'mrn_mega_menu_nonce';

	public static function init() {
		add_action( 'add_meta_boxes_' . Plugin::POST_TYPE, array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Plugin::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'configure_universal_sticky_bar' ), 100 );
		add_action( 'before_delete_post', array( __CLASS__, 'remove_assignment' ) );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_filter( 'post_updated_messages', array( __CLASS__, 'updated_messages' ) );
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'mrn-mega-menu-builder',
			__( 'Menu Builder', 'mrn-mega-menu' ),
			array( __CLASS__, 'render_builder' ),
			Plugin::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'mrn-mega-menu-assignment',
			__( 'Menu usage', 'mrn-mega-menu' ),
			array( __CLASS__, 'render_assignment' ),
			Plugin::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Tell the Universal Sticky Bar that mega menus are reusable admin records.
	 *
	 * This replaces public permalink and preview actions with an admin URL and a
	 * purpose-specific Save Mega Menu action.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function configure_universal_sticky_bar( $hook_suffix ) {
		$screen = get_current_screen();
		if ( ! $screen || Plugin::POST_TYPE !== $screen->post_type || ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$script = sprintf(
			'window.mrnUniversalStickyBarConfig = window.mrnUniversalStickyBarConfig || {}; window.mrnUniversalStickyBarConfig.reusableBlockLabels = window.mrnUniversalStickyBarConfig.reusableBlockLabels || {}; window.mrnUniversalStickyBarConfig.reusableBlockLabels[%1$s] = %2$s;',
			wp_json_encode( Plugin::POST_TYPE ),
			wp_json_encode( __( 'Mega Layout', 'mrn-mega-menu' ) )
		);

		wp_add_inline_script( 'jquery', $script, 'before' );
	}

	public static function enqueue_assets( $hook_suffix ) {
		$screen = get_current_screen();
		if ( ! $screen || Plugin::POST_TYPE !== $screen->post_type || ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$style_dependencies  = array();
		$script_dependencies = array( 'jquery', 'jquery-ui-sortable', 'wplink' );
		if ( function_exists( 'mrn_shared_assets_enqueue_admin_layout_builder' ) ) {
			mrn_shared_assets_enqueue_admin_layout_builder();
			$style_dependencies[]  = 'mrn-shared-admin-layout-builder';
			$script_dependencies[] = 'mrn-shared-admin-layout-builder';
		}
		if ( function_exists( 'mrn_shared_assets_enqueue_admin_icon_chooser' ) ) {
			mrn_shared_assets_enqueue_admin_icon_chooser();
			$style_dependencies[]  = 'mrn-shared-icon-chooser';
			$script_dependencies[] = 'mrn-shared-icon-chooser';
		}

		wp_enqueue_media();
		wp_enqueue_editor();
		if ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
			wp_enqueue_script( 'wc-enhanced-select' );
		}
		if ( wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}
		wp_enqueue_style( 'mrn-mega-menu-admin', Plugin::asset_url( 'assets/css/admin.css' ), $style_dependencies, Plugin::VERSION );
		wp_enqueue_script( 'mrn-mega-menu-icon-controls', Plugin::asset_url( 'assets/js/icon-controls.js' ), array_values( array_unique( array_merge( array( 'jquery' ), $script_dependencies ) ) ), Plugin::VERSION, true );
		$script_dependencies[] = 'mrn-mega-menu-icon-controls';
		wp_enqueue_script( 'mrn-mega-menu-admin', Plugin::asset_url( 'assets/js/admin.js' ), $script_dependencies, Plugin::VERSION, true );
		wp_localize_script(
			'mrn-mega-menu-admin',
			'MRNMegaMenuAdmin',
			array(
				'confirmRemove' => __( 'Remove this block?', 'mrn-mega-menu' ),
				'confirmRemoveColumn' => __( 'Remove this layout column and all content inside it?', 'mrn-mega-menu' ),
				'confirmRemovePanel' => __( 'Remove this mega menu and all content inside it?', 'mrn-mega-menu' ),
				'lastColumn'    => __( 'A mega menu must keep at least one layout column.', 'mrn-mega-menu' ),
				'lastPanel'     => __( 'A layout must keep at least one mega menu.', 'mrn-mega-menu' ),
				'columnRemoved' => __( 'Column removed.', 'mrn-mega-menu' ),
				'columnCopied'  => __( 'Column copied. Paste it into any column in this layout.', 'mrn-mega-menu' ),
				'columnPasted'  => __( 'Copied column pasted. Save or update the layout to publish this change.', 'mrn-mega-menu' ),
				'confirmPasteColumn' => __( 'Replace this column and all of its content with the copied column?', 'mrn-mega-menu' ),
				'categoryResult' => __( '1 category found.', 'mrn-mega-menu' ),
				'categoryResults' => __( '%d categories found.', 'mrn-mega-menu' ),
				'noCategoryResults' => __( 'No categories found.', 'mrn-mega-menu' ),
				'blockMoved'   => __( 'Block moved.', 'mrn-mega-menu' ),
				'linkMoved'    => __( 'Link moved.', 'mrn-mega-menu' ),
				'mediaTitle'    => __( 'Choose a promotion image', 'mrn-mega-menu' ),
				'mediaButton'   => __( 'Use this image', 'mrn-mega-menu' ),
				'entireMenu'    => __( 'Entire menu', 'mrn-mega-menu' ),
				'selectParent'  => __( 'Select a parent item', 'mrn-mega-menu' ),
				'confirmBuild'  => __( 'Replace the current mega menus from the assigned WordPress menu? Existing blocks in this layout will be removed.', 'mrn-mega-menu' ),
				'columnsBuilt'  => __( 'Mega menus built from the assigned menu. Save or update the layout to publish this change.', 'mrn-mega-menu' ),
				'panelAdded'    => __( 'Mega menu added. Save or update the layout to publish this change.', 'mrn-mega-menu' ),
				'panelExists'   => __( 'That top-level navigation item already has a mega menu in this layout.', 'mrn-mega-menu' ),
				'menuItems'     => self::get_menu_picker_data(),
			)
		);
	}

	public static function default_layout() {
		return array(
			'width'  => 'content',
			'panels' => array(
				array(
					'menu_item_id'  => 0,
					'label_override' => '',
					'display_mode'  => 'mega',
					'parent_click'  => 'inherit',
					'item_icon'     => array( 'type' => '', 'value' => '' ),
					'arrow_icon'    => array( 'type' => '', 'value' => '' ),
					'child_arrow_icon' => array( 'type' => '', 'value' => '' ),
					'columns'       => array( array( 'blocks' => array() ) ),
				),
			),
		);
	}

	public static function render_builder( $post ) {
		$layout           = Plugin::get_layout( $post->ID );
		$panels           = isset( $layout['panels'] ) && is_array( $layout['panels'] ) ? $layout['panels'] : self::default_layout()['panels'];
		$width            = isset( $layout['width'] ) && 'full' === $layout['width'] ? 'full' : 'content';
		$status           = Stack_Integration::get_status();
		$assigned_menu_id = self::assigned_menu_id( $post->ID );
		$menu_picker_data = self::get_menu_picker_data();
		$top_level_items  = $assigned_menu_id && isset( $menu_picker_data[ $assigned_menu_id ] ) ? $menu_picker_data[ $assigned_menu_id ] : array();
		$manage_menu_url  = $assigned_menu_id ? add_query_arg( array( 'action' => 'edit', 'menu' => $assigned_menu_id ), admin_url( 'nav-menus.php' ) ) : '';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div class="mrn-mm-builder mrn-mm-builder--panels mrn-admin-layout-builder" data-layout-mode="panels" data-mrn-admin-ui-contract="1.1.0" data-next-block="<?php echo esc_attr( self::next_block_index( $panels ) ); ?>" data-runtime-mode="<?php echo esc_attr( $status['mode'] ); ?>" data-assigned-menu-id="<?php echo esc_attr( $assigned_menu_id ); ?>">
			<div class="mrn-mm-builder__intro">
				<div>
					<span class="mrn-mm-runtime-badge mrn-mm-runtime-badge--<?php echo esc_attr( $status['mode'] ); ?>">
						<span class="dashicons <?php echo $status['connected'] ? 'dashicons-yes-alt' : 'dashicons-admin-plugins'; ?>" aria-hidden="true"></span>
						<?php echo $status['connected'] ? esc_html__( 'MRN Stack connected', 'mrn-mega-menu' ) : esc_html__( 'Standalone mode', 'mrn-mega-menu' ); ?>
					</span>
					<h2><?php esc_html_e( 'Build the panel people see when they open this menu item.', 'mrn-mega-menu' ); ?></h2>
					<p><?php esc_html_e( 'Choose a layout, then add only the content shoppers need. Columns stack automatically on smaller screens.', 'mrn-mega-menu' ); ?></p>
					<?php if ( ! empty( $status['capabilities']['tokens'] ) ) : ?>
						<p class="mrn-mm-stack-help"><?php echo wp_kses_post( __( 'Stack tokens are available in headings, link labels, promotion headlines, messages, and button labels. Example: <code>{token:phone}</code>.', 'mrn-mega-menu' ) ); ?></p>
					<?php endif; ?>
				</div>
				<div class="mrn-mm-layout-settings" aria-label="<?php esc_attr_e( 'Mega menu layout settings', 'mrn-mega-menu' ); ?>">
					<label class="mrn-mm-toggle">
						<input type="checkbox" name="mrn_mega_menu_width" value="full" <?php checked( 'full', $width ); ?>>
						<span><?php esc_html_e( 'Full width panel', 'mrn-mega-menu' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'When enabled, the opened panel expands to the site full-width shell. When off, it stays within the site content shell.', 'mrn-mega-menu' ); ?></p>
				</div>
			</div>

			<div class="mrn-mm-panels-toolbar">
				<div><strong><?php esc_html_e( 'Mega menus', 'mrn-mega-menu' ); ?></strong><p class="description"><?php esc_html_e( 'Each tab is the complete dropdown opened by one top-level WordPress menu item.', 'mrn-mega-menu' ); ?></p></div>
				<div class="mrn-mm-panels-toolbar__actions">
					<label><span><?php esc_html_e( 'Top-level navigation item', 'mrn-mega-menu' ); ?></span><select class="mrn-mm-add-panel-item"<?php disabled( ! $assigned_menu_id ); ?>><option value="0"><?php esc_html_e( 'Choose an item', 'mrn-mega-menu' ); ?></option><?php foreach ( $top_level_items as $item ) : ?><option value="<?php echo esc_attr( $item['id'] ); ?>"><?php echo esc_html( $item['title'] ); ?></option><?php endforeach; ?></select></label>
					<button type="button" class="button button-secondary mrn-mm-add-panel"<?php disabled( ! $assigned_menu_id ); ?>><?php esc_html_e( 'Add mega menu', 'mrn-mega-menu' ); ?></button>
					<button type="button" class="button button-secondary mrn-mm-build-assigned"<?php disabled( ! $assigned_menu_id ); ?>><?php esc_html_e( 'Rebuild all from assigned menu', 'mrn-mega-menu' ); ?></button>
					<?php if ( $manage_menu_url ) : ?><a class="button" href="<?php echo esc_url( $manage_menu_url ); ?>"><?php esc_html_e( 'Manage navigation items', 'mrn-mega-menu' ); ?></a><?php endif; ?>
				</div>
			</div>
			<p class="description mrn-mm-build-help"><?php echo $assigned_menu_id ? esc_html__( 'Add a single existing top-level item, or rebuild every parent branch at once. Use Manage navigation items to create, reorder, nest, or add default icons in WordPress.', 'mrn-mega-menu' ) : esc_html__( 'Assign this published layout to a mega-enabled WordPress menu before building its mega menus.', 'mrn-mega-menu' ); ?></p>
			<div class="mrn-mm-panel-tabs mrn-admin-layout-builder__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Mega menus in this layout', 'mrn-mega-menu' ); ?>">
				<?php foreach ( $panels as $panel_index => $panel ) : ?>
					<?php $panel_label = self::panel_label( $panel, $panel_index ); ?>
					<button type="button" role="tab" id="mrn-mm-panel-tab-<?php echo esc_attr( $panel_index ); ?>" aria-controls="mrn-mm-panel-<?php echo esc_attr( $panel_index ); ?>" aria-selected="<?php echo 0 === $panel_index ? 'true' : 'false'; ?>" tabindex="<?php echo 0 === $panel_index ? '0' : '-1'; ?>" data-panel-tab="<?php echo esc_attr( $panel_index ); ?>"><?php echo esc_html( $panel_label ); ?></button>
				<?php endforeach; ?>
			</div>
			<div class="mrn-mm-panels">
				<?php foreach ( $panels as $panel_index => $panel ) : ?><?php self::render_panel_editor( $panel, $panel_index, 0 === $panel_index ); ?><?php endforeach; ?>
			</div>
			<input type="hidden" name="mrn_mega_menu_layout" class="mrn-mm-layout-data" value="">
			<label class="screen-reader-text" for="mrn-mm-link-picker-target"><?php esc_html_e( 'Selected WordPress link', 'mrn-mega-menu' ); ?></label>
			<textarea id="mrn-mm-link-picker-target" class="mrn-mm-link-picker-target" tabindex="-1"></textarea>
			<?php self::render_templates(); ?>
		</div>
		<?php
	}

	private static function render_add_menu() {
		?>
		<div class="mrn-mm-add__menu" hidden>
			<button type="button" data-add-block="menu"><span class="dashicons dashicons-menu-alt3"></span><span><strong><?php esc_html_e( 'WordPress menu', 'mrn-mega-menu' ); ?></strong><small><?php esc_html_e( 'Use native menu items and ordering', 'mrn-mega-menu' ); ?></small></span></button>
			<button type="button" data-add-block="links"><span class="dashicons dashicons-editor-ul"></span><span><strong><?php esc_html_e( 'Custom link group', 'mrn-mega-menu' ); ?></strong><small><?php esc_html_e( 'Build one-off links here', 'mrn-mega-menu' ); ?></small></span></button>
			<button type="button" data-add-block="categories"><span class="dashicons dashicons-category"></span><span><strong><?php esc_html_e( 'Product categories', 'mrn-mega-menu' ); ?></strong><small><?php esc_html_e( 'Selected WooCommerce categories', 'mrn-mega-menu' ); ?></small></span></button>
			<button type="button" data-add-block="products"><span class="dashicons dashicons-cart"></span><span><strong><?php esc_html_e( 'Products', 'mrn-mega-menu' ); ?></strong><small><?php esc_html_e( 'Featured, sale, latest, or selected', 'mrn-mega-menu' ); ?></small></span></button>
			<button type="button" data-add-block="promo"><span class="dashicons dashicons-format-image"></span><span><strong><?php esc_html_e( 'Promotion', 'mrn-mega-menu' ); ?></strong><small><?php esc_html_e( 'Image, message, and call to action', 'mrn-mega-menu' ); ?></small></span></button>
			<button type="button" data-add-block="reusable"><span class="dashicons dashicons-screenoptions"></span><span><strong><?php esc_html_e( 'Reusable block', 'mrn-mega-menu' ); ?></strong><small><?php esc_html_e( 'Published content from the block library', 'mrn-mega-menu' ); ?></small></span></button>
		</div>
		<?php
	}

	private static function render_panel_editor( $panel, $panel_index, $active ) {
		$columns      = isset( $panel['columns'] ) && is_array( $panel['columns'] ) ? $panel['columns'] : array( array( 'blocks' => array() ) );
		$menu_item_id = absint( $panel['menu_item_id'] ?? 0 );
		$panel_label  = self::panel_label( $panel, $panel_index );
		$display_mode = Plugin::sanitize_display_mode( $panel['display_mode'] ?? 'mega' );
		?>
		<section class="mrn-mm-panel" id="mrn-mm-panel-<?php echo esc_attr( $panel_index ); ?>" role="tabpanel" aria-labelledby="mrn-mm-panel-tab-<?php echo esc_attr( $panel_index ); ?>" data-panel-index="<?php echo esc_attr( $panel_index ); ?>" data-menu-item-id="<?php echo esc_attr( $menu_item_id ); ?>" data-display-mode="<?php echo esc_attr( $display_mode ); ?>"<?php echo $active ? '' : ' hidden'; ?>>
			<div class="mrn-mm-panel__header">
				<div><h3><?php echo esc_html( sprintf( __( 'Mega menu: %s', 'mrn-mega-menu' ), $panel_label ) ); ?></h3><p class="description"><?php esc_html_e( 'Choose the visual columns inside this dropdown, then drag content between them.', 'mrn-mega-menu' ); ?></p></div>
				<button type="button" class="button-link-delete mrn-admin-card-remove mrn-mm-remove-panel" aria-label="<?php esc_attr_e( 'Remove mega menu', 'mrn-mega-menu' ); ?>" title="<?php esc_attr_e( 'Remove mega menu', 'mrn-mega-menu' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
			</div>
			<div class="mrn-mm-panel-icons">
				<label class="mrn-mm-panel-display-mode"><span><?php esc_html_e( 'Menu style', 'mrn-mega-menu' ); ?></span><select data-panel-field="display_mode"><option value="mega" <?php selected( $display_mode, 'mega' ); ?>><?php esc_html_e( 'Full mega menu', 'mrn-mega-menu' ); ?></option><option value="dropdown" <?php selected( $display_mode, 'dropdown' ); ?>><?php esc_html_e( 'Simple dropdown (single narrow column)', 'mrn-mega-menu' ); ?></option></select><small><?php esc_html_e( 'A simple dropdown opens as a narrow list anchored under this item instead of the full-width mega panel. Choose which layout columns it uses below.', 'mrn-mega-menu' ); ?></small></label>
				<label class="mrn-mm-panel-label-override"><span><?php esc_html_e( 'Parent navigation label', 'mrn-mega-menu' ); ?></span><input type="text" data-panel-field="label_override" value="<?php echo esc_attr( $panel['label_override'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $panel_label ); ?>"><small><?php esc_html_e( 'Overrides the visible top-level label for this layout without renaming the WordPress menu item.', 'mrn-mega-menu' ); ?></small></label>
				<label class="mrn-mm-panel-parent-click"><span><?php esc_html_e( 'Parent item click behavior', 'mrn-mega-menu' ); ?></span><select data-panel-field="parent_click"><option value="inherit" <?php selected( $panel['parent_click'] ?? 'inherit', 'inherit' ); ?>><?php esc_html_e( 'Use global setting', 'mrn-mega-menu' ); ?></option><option value="toggle" <?php selected( $panel['parent_click'] ?? 'inherit', 'toggle' ); ?>><?php esc_html_e( 'Open mega menu only', 'mrn-mega-menu' ); ?></option><option value="link" <?php selected( $panel['parent_click'] ?? 'inherit', 'link' ); ?>><?php esc_html_e( 'Allow parent link', 'mrn-mega-menu' ); ?></option></select><small><?php esc_html_e( 'Applies only to this assigned WordPress menu-item ID. Linked parents navigate on desktop and Enter; touch users tap once to open and again to follow. Space always toggles the panel.', 'mrn-mega-menu' ); ?></small></label>
				<?php self::render_icon_control( 'item_icon', __( 'Navigation item icon', 'mrn-mega-menu' ), $panel['item_icon'] ?? array(), __( 'Overrides the icon configured on the native WordPress menu item for this layout.', 'mrn-mega-menu' ) ); ?>
				<?php self::render_icon_control( 'arrow_icon', __( 'Navigation arrow icon', 'mrn-mega-menu' ), $panel['arrow_icon'] ?? array(), __( 'Overrides the arrow used to indicate this item opens a mega menu.', 'mrn-mega-menu' ) ); ?>
				<?php self::render_icon_control( 'child_arrow_icon', __( 'Child navigation arrow', 'mrn-mega-menu' ), $panel['child_arrow_icon'] ?? array(), __( 'Replaces the default trailing arrow on child links. A child item’s own navigation arrow takes precedence.', 'mrn-mega-menu' ) ); ?>
			</div>
			<div class="mrn-mm-columns-toolbar mrn-admin-layout-builder__toolbar">
				<div><strong><?php esc_html_e( 'Layout columns', 'mrn-mega-menu' ); ?></strong><p class="description"><?php esc_html_e( 'These columns appear together inside this one mega menu and stack on smaller screens. Use Copy and Paste in a column header to duplicate its content between top-level menu items.', 'mrn-mega-menu' ); ?></p></div>
				<label><?php esc_html_e( 'Columns', 'mrn-mega-menu' ); ?><input type="number" class="mrn-mm-column-count" min="1" max="6" value="<?php echo esc_attr( max( 1, min( 6, count( $columns ) ) ) ); ?>"></label>
			</div>
			<div class="mrn-mm-columns-scroll mrn-admin-layout-builder__scroll" tabindex="0" aria-label="<?php echo esc_attr( sprintf( __( 'Layout columns for %s. Scroll horizontally to see additional columns.', 'mrn-mega-menu' ), $panel_label ) ); ?>">
				<div class="mrn-mm-columns mrn-admin-layout-builder__lanes" data-columns="<?php echo esc_attr( count( $columns ) ); ?>" data-sort-group="panel-<?php echo esc_attr( $panel_index ); ?>">
					<?php foreach ( $columns as $column_index => $column ) : ?><?php self::render_column( $column, $column_index ); ?><?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
	}

	private static function render_column( $column, $column_index ) {
		$blocks = isset( $column['blocks'] ) && is_array( $column['blocks'] ) ? $column['blocks'] : array();
		$visible_in_dropdown = ! isset( $column['visible_in_dropdown'] ) || ! empty( $column['visible_in_dropdown'] );
		?>
		<section class="mrn-mm-column mrn-admin-layout-builder__lane" data-column-index="<?php echo esc_attr( $column_index ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Layout column %d', 'mrn-mega-menu' ), $column_index + 1 ) ); ?>">
			<div class="mrn-mm-column__header">
				<strong><?php echo esc_html( sprintf( __( 'Layout column %d', 'mrn-mega-menu' ), $column_index + 1 ) ); ?></strong>
				<div class="mrn-mm-column__actions"><span class="mrn-mm-block-count"><?php echo esc_html( sprintf( _n( '%d block', '%d blocks', count( $blocks ), 'mrn-mega-menu' ), count( $blocks ) ) ); ?></span><button type="button" class="button-link mrn-mm-copy-column" aria-label="<?php esc_attr_e( 'Copy layout column', 'mrn-mega-menu' ); ?>" title="<?php esc_attr_e( 'Copy layout column', 'mrn-mega-menu' ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button><button type="button" class="button-link mrn-mm-paste-column" aria-label="<?php esc_attr_e( 'Paste copied layout column', 'mrn-mega-menu' ); ?>" title="<?php esc_attr_e( 'Paste copied layout column', 'mrn-mega-menu' ); ?>" disabled><span class="dashicons dashicons-clipboard" aria-hidden="true"></span></button><button type="button" class="button-link-delete mrn-admin-card-remove mrn-mm-remove-column" aria-label="<?php esc_attr_e( 'Remove layout column', 'mrn-mega-menu' ); ?>" title="<?php esc_attr_e( 'Remove layout column', 'mrn-mega-menu' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></div>
			</div>
			<label class="mrn-mm-column__dropdown-toggle"><input type="checkbox" data-column-field="visible_in_dropdown" <?php checked( $visible_in_dropdown ); ?>> <?php esc_html_e( 'Show in dropdown', 'mrn-mega-menu' ); ?></label>
			<div class="mrn-mm-blocks">
				<?php foreach ( $blocks as $block_index => $block ) : ?>
					<?php self::render_block( $block, $block_index ); ?>
				<?php endforeach; ?>
			</div>
			<div class="mrn-mm-add">
				<button type="button" class="button mrn-mm-add__toggle" aria-expanded="false"><?php esc_html_e( '+ Add content', 'mrn-mega-menu' ); ?></button>
				<div class="mrn-mm-add__menu" hidden>
					<button type="button" data-add-block="menu"><span class="dashicons dashicons-menu-alt3"></span><span><strong><?php esc_html_e( 'WordPress menu', 'mrn-mega-menu' ); ?></strong><small><?php esc_html_e( 'Use native menu items and ordering', 'mrn-mega-menu' ); ?></small></span></button>
					<button type="button" data-add-block="links"><span class="dashicons dashicons-editor-ul"></span><span><strong><?php esc_html_e( 'Custom link group', 'mrn-mega-menu' ); ?></strong><small><?php esc_html_e( 'Build one-off links here', 'mrn-mega-menu' ); ?></small></span></button>
					<button type="button" data-add-block="categories"><span class="dashicons dashicons-category"></span><span><strong><?php esc_html_e( 'Product categories', 'mrn-mega-menu' ); ?></strong><small><?php esc_html_e( 'Selected WooCommerce categories', 'mrn-mega-menu' ); ?></small></span></button>
					<button type="button" data-add-block="products"><span class="dashicons dashicons-cart"></span><span><strong><?php esc_html_e( 'Products', 'mrn-mega-menu' ); ?></strong><small><?php esc_html_e( 'Featured, sale, latest, or selected', 'mrn-mega-menu' ); ?></small></span></button>
					<button type="button" data-add-block="promo"><span class="dashicons dashicons-format-image"></span><span><strong><?php esc_html_e( 'Promotion', 'mrn-mega-menu' ); ?></strong><small><?php esc_html_e( 'Image, message, and call to action', 'mrn-mega-menu' ); ?></small></span></button>
					<button type="button" data-add-block="reusable"><span class="dashicons dashicons-screenoptions"></span><span><strong><?php esc_html_e( 'Reusable block', 'mrn-mega-menu' ); ?></strong><small><?php esc_html_e( 'Published content from the block library', 'mrn-mega-menu' ); ?></small></span></button>
				</div>
			</div>
		</section>
		<?php
	}

	private static function render_block( $block, $block_index ) {
		$type  = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : 'links';
		$title = isset( $block['title'] ) ? $block['title'] : '';
		if ( 'reusable' === $type && ! empty( $block['reusable_block_id'] ) ) {
			$title = Stack_Integration::get_reusable_block_label( get_post( absint( $block['reusable_block_id'] ) ) );
		}
		$names = array(
			'menu'       => __( 'WordPress menu', 'mrn-mega-menu' ),
			'links'      => __( 'Custom link group', 'mrn-mega-menu' ),
			'categories' => __( 'Product categories', 'mrn-mega-menu' ),
			'products'   => __( 'Products', 'mrn-mega-menu' ),
			'promo'      => __( 'Promotion', 'mrn-mega-menu' ),
			'reusable'   => __( 'Reusable block', 'mrn-mega-menu' ),
		);
		if ( ! isset( $names[ $type ] ) ) {
			$type = 'links';
		}
		?>
		<article class="mrn-mm-block mrn-admin-layout-builder__item" data-block-type="<?php echo esc_attr( $type ); ?>" data-block-index="<?php echo esc_attr( $block_index ); ?>">
			<header class="mrn-mm-block__header">
				<button type="button" class="mrn-mm-block__toggle" aria-expanded="true" title="<?php esc_attr_e( 'Drag to move this block. Click to collapse its options.', 'mrn-mega-menu' ); ?>">
					<span class="dashicons <?php echo esc_attr( self::block_icon( $type ) ); ?>"></span>
					<span><strong><?php echo esc_html( $names[ $type ] ); ?></strong><small><?php echo esc_html( $title ? $title : __( 'Untitled', 'mrn-mega-menu' ) ); ?></small></span>
					<span class="dashicons dashicons-arrow-up-alt2 mrn-mm-block__chevron"></span>
				</button>
				<div class="mrn-mm-block__actions">
					<button type="button" class="button-link-delete mrn-admin-card-remove mrn-mm-remove" aria-label="<?php esc_attr_e( 'Remove block', 'mrn-mega-menu' ); ?>" title="<?php esc_attr_e( 'Remove block', 'mrn-mega-menu' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
				</div>
			</header>
			<div class="mrn-mm-block__body">
				<input type="hidden" data-field="type" value="<?php echo esc_attr( $type ); ?>">
				<?php self::render_block_fields( $type, $block ); ?>
			</div>
		</article>
		<?php
	}

	private static function render_block_fields( $type, $block ) {
		$title = isset( $block['title'] ) ? $block['title'] : '';
		if ( 'reusable' === $type ) {
			self::render_reusable_block_fields( $block );
			return;
		}
		?>
		<label class="mrn-mm-field">
			<span><?php echo 'promo' === $type ? esc_html__( 'Headline', 'mrn-mega-menu' ) : esc_html__( 'Heading', 'mrn-mega-menu' ); ?></span>
			<input type="text" data-field="title" value="<?php echo esc_attr( $title ); ?>" maxlength="120">
			<?php if ( 'promo' !== $type ) : ?><small><?php esc_html_e( 'Optional section heading inside the opened panel. It does not create or rename a menu trigger.', 'mrn-mega-menu' ); ?></small><?php endif; ?>
		</label>
		<?php
		if ( 'menu' === $type ) {
			self::render_menu_fields( $block );
		} elseif ( 'links' === $type ) {
			$links = isset( $block['links'] ) && is_array( $block['links'] ) ? $block['links'] : array();
			?>
			<div class="mrn-mm-field">
				<span><?php esc_html_e( 'Links', 'mrn-mega-menu' ); ?></span>
				<div class="mrn-mm-link-rows">
					<?php foreach ( $links as $link ) : ?>
						<?php self::render_link_row( $link ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button-link mrn-mm-add-link"><?php esc_html_e( '+ Add link', 'mrn-mega-menu' ); ?></button>
			</div>
			<?php
		} elseif ( 'categories' === $type ) {
			self::render_category_fields( $block );
		} elseif ( 'products' === $type ) {
			self::render_product_fields( $block );
		} elseif ( 'promo' === $type ) {
			self::render_promo_fields( $block );
		}
	}

	private static function render_reusable_block_fields( $block ) {
		$selected_id = isset( $block['reusable_block_id'] ) ? absint( $block['reusable_block_id'] ) : 0;
		$blocks      = Stack_Integration::get_reusable_blocks();
		?>
		<label class="mrn-mm-field">
			<span><?php esc_html_e( 'Reusable block', 'mrn-mega-menu' ); ?></span>
			<select data-field="reusable_block_id" class="mrn-mm-reusable-block-select">
				<option value="0"><?php esc_html_e( 'Select a published reusable block', 'mrn-mega-menu' ); ?></option>
				<?php foreach ( $blocks as $reusable_block ) : ?>
					<option value="<?php echo esc_attr( $reusable_block->ID ); ?>" <?php selected( $selected_id, $reusable_block->ID ); ?>><?php echo esc_html( Stack_Integration::get_reusable_block_label( $reusable_block ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<small><?php esc_html_e( 'The menu always displays the latest published content from the Reusable Block Library.', 'mrn-mega-menu' ); ?></small>
		</label>
		<?php if ( empty( $blocks ) ) : ?>
			<p class="description"><?php esc_html_e( 'No published reusable blocks are available, or the Reusable Block Library is inactive.', 'mrn-mega-menu' ); ?></p>
		<?php endif; ?>
		<?php
	}

	private static function render_menu_fields( $block ) {
		$menu_id      = isset( $block['menu_id'] ) ? absint( $block['menu_id'] ) : 0;
		$root_item_id = isset( $block['root_item_id'] ) ? absint( $block['root_item_id'] ) : 0;
		$branch_mode  = isset( $block['branch_mode'] ) && in_array( $block['branch_mode'], array( 'parent_and_children', 'children_only', 'parent_only' ), true ) ? $block['branch_mode'] : 'parent_and_children';
		$distribution_index = isset( $block['distribution_index'] ) ? absint( $block['distribution_index'] ) : 0;
		$distribution_total = isset( $block['distribution_total'] ) ? max( 1, min( 12, absint( $block['distribution_total'] ) ) ) : 1;
		$menus        = wp_get_nav_menus();
		$picker_data  = self::get_menu_picker_data();
		?>
		<input type="hidden" data-field="source" value="selected">
		<input type="hidden" data-field="distribution_index" value="<?php echo esc_attr( $distribution_index ); ?>">
		<input type="hidden" data-field="distribution_total" value="<?php echo esc_attr( $distribution_total ); ?>">
		<label class="mrn-mm-field mrn-mm-selected-menu">
			<span><?php esc_html_e( 'Menu', 'mrn-mega-menu' ); ?></span>
			<select data-field="menu_id" class="mrn-mm-menu-select">
				<option value="0"><?php esc_html_e( 'Select a menu', 'mrn-mega-menu' ); ?></option>
				<?php foreach ( $menus as $menu ) : ?>
					<option value="<?php echo esc_attr( $menu->term_id ); ?>" <?php selected( $menu_id, $menu->term_id ); ?>><?php echo esc_html( $menu->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<small><?php esc_html_e( 'The WordPress menu supplying this column. Build from assigned menu chooses it automatically.', 'mrn-mega-menu' ); ?></small>
		</label>
		<label class="mrn-mm-field mrn-mm-menu-root-field">
			<span><?php esc_html_e( 'Parent item', 'mrn-mega-menu' ); ?></span>
			<select data-field="root_item_id" class="mrn-mm-menu-root">
				<option value="0"><?php esc_html_e( 'Select a parent item', 'mrn-mega-menu' ); ?></option>
				<?php foreach ( isset( $picker_data[ $menu_id ] ) ? $picker_data[ $menu_id ] : array() as $item ) : ?>
					<option value="<?php echo esc_attr( $item['id'] ); ?>" <?php selected( $root_item_id, $item['id'] ); ?>><?php echo esc_html( $item['title'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="mrn-mm-field mrn-mm-branch-mode" <?php echo ! $root_item_id ? 'hidden' : ''; ?>>
			<span><?php esc_html_e( 'Show in dropdown', 'mrn-mega-menu' ); ?></span>
			<select data-field="branch_mode">
				<option value="parent_and_children" <?php selected( 'parent_and_children', $branch_mode ); ?>><?php esc_html_e( 'Parent link and children', 'mrn-mega-menu' ); ?></option>
				<option value="children_only" <?php selected( 'children_only', $branch_mode ); ?>><?php esc_html_e( 'Children only', 'mrn-mega-menu' ); ?></option>
				<option value="parent_only" <?php selected( 'parent_only', $branch_mode ); ?>><?php esc_html_e( 'Parent link only', 'mrn-mega-menu' ); ?></option>
			</select>
			<small><?php esc_html_e( 'Choose whether the opened dropdown repeats the parent link above its child links.', 'mrn-mega-menu' ); ?></small>
		</label>
		<p class="description mrn-mm-menu-description">
			<?php esc_html_e( 'Native menu labels, URLs, hierarchy, and ordering remain managed in Appearance → Menus.', 'mrn-mega-menu' ); ?>
		</p>
		<?php
	}

	private static function get_menu_picker_data() {
		$data = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
			if ( empty( $items ) || is_wp_error( $items ) ) {
				continue;
			}
			foreach ( $items as $item ) {
				if ( 0 !== Plugin::get_menu_item_parent_id( $item ) ) {
					continue;
				}
				$data[ $menu->term_id ][] = array(
					'id'    => absint( $item->ID ),
					'title' => sanitize_text_field( $item->title ),
				);
			}
		}
		return $data;
	}

	private static function render_link_row( $link = array() ) {
		?>
		<div class="mrn-mm-link-row">
			<button type="button" class="button-link mrn-mm-link-drag" aria-label="<?php esc_attr_e( 'Drag to reorder link. Use up and down arrow keys to move without dragging.', 'mrn-mega-menu' ); ?>" title="<?php esc_attr_e( 'Drag to reorder link', 'mrn-mega-menu' ); ?>"><span class="dashicons dashicons-move" aria-hidden="true"></span></button>
			<label><span class="screen-reader-text"><?php esc_html_e( 'Link label', 'mrn-mega-menu' ); ?></span><input type="text" data-link-field="label" value="<?php echo esc_attr( isset( $link['label'] ) ? $link['label'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Label', 'mrn-mega-menu' ); ?>" maxlength="100"></label>
			<label><span class="screen-reader-text"><?php esc_html_e( 'Link URL', 'mrn-mega-menu' ); ?></span><input type="url" data-link-field="url" value="<?php echo esc_attr( isset( $link['url'] ) ? $link['url'] : '' ); ?>" placeholder="https://"></label>
			<input type="hidden" data-link-field="target" value="<?php echo isset( $link['target'] ) && '_blank' === $link['target'] ? '_blank' : ''; ?>">
			<button type="button" class="button mrn-mm-pick-link"><?php esc_html_e( 'Choose link', 'mrn-mega-menu' ); ?></button>
			<button type="button" class="button-link-delete mrn-mm-remove-link"><?php esc_html_e( 'Remove', 'mrn-mega-menu' ); ?></button>
		</div>
		<?php
	}

	private static function render_category_fields( $block ) {
		$selected          = isset( $block['category_ids'] ) && is_array( $block['category_ids'] ) ? array_values( array_filter( array_map( 'absint', $block['category_ids'] ) ) ) : array();
		$selected_positions = array_flip( $selected );
		$category_display  = isset( $block['category_display'] ) && in_array( $block['category_display'], array( 'links', 'links_descriptions', 'descriptions' ), true )
			? $block['category_display']
			: ( ! empty( $block['show_category_descriptions'] ) ? 'links_descriptions' : 'links' );
		$name_display      = isset( $block['category_name_display'] ) && in_array( $block['category_name_display'], array( 'link', 'text', 'hidden' ), true )
			? $block['category_name_display']
			: ( 'descriptions' === $category_display ? 'hidden' : 'link' );
		$description_source = isset( $block['category_description_source'] ) && in_array( $block['category_description_source'], array( 'none', 'description', 'short_description' ), true )
			? $block['category_description_source']
			: ( 'links' === $category_display ? 'none' : 'description' );
		$name_tag          = self::sanitize_category_name_tag( $block['category_name_tag'] ?? 'span' );
		$terms             = taxonomy_exists( 'product_cat' ) ? get_terms(
			array(
				'taxonomy'     => 'product_cat',
				'hide_empty'   => false,
				'hierarchical' => true,
				'orderby'      => 'name',
				'order'        => 'ASC',
				'number'       => 0,
			)
		) : array();
		$label_id          = wp_unique_id( 'mrn-mm-categories-' );
		?>
		<div class="mrn-mm-field">
			<span id="<?php echo esc_attr( $label_id ); ?>"><?php esc_html_e( 'Categories', 'mrn-mega-menu' ); ?></span>
			<div class="mrn-mm-category-picker">
				<label class="mrn-mm-category-search">
					<span><?php esc_html_e( 'Search categories', 'mrn-mega-menu' ); ?></span>
					<span class="mrn-mm-category-search__controls">
						<input type="search" class="mrn-mm-category-search__input" placeholder="<?php esc_attr_e( 'Search by category name', 'mrn-mega-menu' ); ?>" autocomplete="off">
						<button type="button" class="button mrn-mm-category-search__clear" hidden><?php esc_html_e( 'Clear', 'mrn-mega-menu' ); ?></button>
					</span>
				</label>
				<p class="mrn-mm-category-search__status screen-reader-text" aria-live="polite"></p>
				<div class="mrn-mm-check-list" role="group" aria-labelledby="<?php echo esc_attr( $label_id ); ?>">
				<?php if ( is_wp_error( $terms ) || empty( $terms ) ) : ?>
					<p><?php esc_html_e( 'No product categories are available yet.', 'mrn-mega-menu' ); ?></p>
				<?php else : ?>
					<?php self::render_category_tree( $terms, $selected_positions ); ?>
					<p class="mrn-mm-category-search__empty" hidden><?php esc_html_e( 'No categories found.', 'mrn-mega-menu' ); ?></p>
				<?php endif; ?>
				</div>
			</div>
		</div>
		<label class="mrn-mm-field">
			<span><?php esc_html_e( 'Category name', 'mrn-mega-menu' ); ?></span>
			<select data-field="category_name_display">
				<option value="link" <?php selected( $name_display, 'link' ); ?>><?php esc_html_e( 'Linked category name', 'mrn-mega-menu' ); ?></option>
				<option value="text" <?php selected( $name_display, 'text' ); ?>><?php esc_html_e( 'Plain category name', 'mrn-mega-menu' ); ?></option>
				<option value="hidden" <?php selected( $name_display, 'hidden' ); ?>><?php esc_html_e( 'Hide category name', 'mrn-mega-menu' ); ?></option>
			</select>
			<small><?php esc_html_e( 'Choose whether the category name links to its archive, renders as plain text, or is omitted.', 'mrn-mega-menu' ); ?></small>
		</label>
		<label class="mrn-mm-field">
			<span><?php esc_html_e( 'Category name HTML tag', 'mrn-mega-menu' ); ?></span>
			<select data-field="category_name_tag">
				<?php foreach ( array( 'span', 'p', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $tag ) : ?>
					<option value="<?php echo esc_attr( $tag ); ?>" <?php selected( $name_tag, $tag ); ?>>&lt;<?php echo esc_html( $tag ); ?>&gt;</option>
				<?php endforeach; ?>
			</select>
			<small><?php esc_html_e( 'Use a heading tag only when it fits the surrounding page hierarchy. Existing blocks default to a span.', 'mrn-mega-menu' ); ?></small>
		</label>
		<label class="mrn-mm-field">
			<span><?php esc_html_e( 'Description', 'mrn-mega-menu' ); ?></span>
			<select data-field="category_description_source">
				<option value="none" <?php selected( $description_source, 'none' ); ?>><?php esc_html_e( 'No description', 'mrn-mega-menu' ); ?></option>
				<option value="description" <?php selected( $description_source, 'description' ); ?>><?php esc_html_e( 'Category description', 'mrn-mega-menu' ); ?></option>
				<option value="short_description" <?php selected( $description_source, 'short_description' ); ?>><?php esc_html_e( 'Short Description', 'mrn-mega-menu' ); ?></option>
			</select>
			<small><?php esc_html_e( 'Short Description reads the product-category term field with the short_description key. Empty selected descriptions are omitted.', 'mrn-mega-menu' ); ?></small>
		</label>
		<?php
	}

	/**
	 * Render WooCommerce product categories using their stored taxonomy hierarchy.
	 *
	 * @param \WP_Term[] $terms              Product category terms.
	 * @param int[]     $selected_positions Selected term IDs mapped to their saved order.
	 * @return void
	 */
	private static function render_category_tree( $terms, $selected_positions ) {
		$terms_by_parent = array();
		$known_term_ids  = array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) );
		$tree_position   = 0;

		foreach ( $terms as $term ) {
			$parent_id = in_array( (int) $term->parent, $known_term_ids, true ) ? (int) $term->parent : 0;

			$terms_by_parent[ $parent_id ][] = $term;
		}

		echo '<ul class="mrn-mm-category-tree">';
		self::render_category_branch( 0, $terms_by_parent, $selected_positions, $tree_position );
		echo '</ul>';
	}

	/**
	 * Render one branch of the product category checklist.
	 *
	 * @param int        $parent_id          Parent term ID.
	 * @param \WP_Term[][] $terms_by_parent   Terms grouped by parent term ID.
	 * @param int[]      $selected_positions Selected term IDs mapped to their saved order.
	 * @param int        $tree_position      Current hierarchy display position.
	 * @return void
	 */
	private static function render_category_branch( $parent_id, $terms_by_parent, $selected_positions, &$tree_position ) {
		if ( empty( $terms_by_parent[ $parent_id ] ) ) {
			return;
		}

		foreach ( $terms_by_parent[ $parent_id ] as $term ) {
			$term_id        = (int) $term->term_id;
			$is_selected    = array_key_exists( $term_id, $selected_positions );
			$selection_order = $is_selected ? (int) $selected_positions[ $term_id ] : count( $selected_positions ) + $tree_position;
			++$tree_position;
			?>
			<li class="mrn-mm-category-tree__item">
				<label>
					<input type="checkbox" data-category-id="<?php echo esc_attr( $term_id ); ?>" data-category-order="<?php echo esc_attr( $selection_order ); ?>" <?php checked( $is_selected ); ?>>
					<span><?php echo esc_html( $term->name ); ?></span>
				</label>
				<?php if ( ! empty( $terms_by_parent[ $term_id ] ) ) : ?>
					<ul>
						<?php self::render_category_branch( $term_id, $terms_by_parent, $selected_positions, $tree_position ); ?>
					</ul>
				<?php endif; ?>
			</li>
			<?php
		}
	}

	private static function render_product_fields( $block ) {
		$source   = isset( $block['source'] ) ? $block['source'] : 'featured';
		$limit    = isset( $block['limit'] ) ? absint( $block['limit'] ) : 4;
		$selected = isset( $block['product_ids'] ) && is_array( $block['product_ids'] ) ? array_map( 'absint', $block['product_ids'] ) : array();
		?>
		<div class="mrn-mm-field-row">
			<label class="mrn-mm-field">
				<span><?php esc_html_e( 'Products to show', 'mrn-mega-menu' ); ?></span>
				<select data-field="source">
					<option value="featured" <?php selected( 'featured', $source ); ?>><?php esc_html_e( 'Featured products', 'mrn-mega-menu' ); ?></option>
					<option value="sale" <?php selected( 'sale', $source ); ?>><?php esc_html_e( 'On-sale products', 'mrn-mega-menu' ); ?></option>
					<option value="latest" <?php selected( 'latest', $source ); ?>><?php esc_html_e( 'Latest products', 'mrn-mega-menu' ); ?></option>
					<option value="manual" <?php selected( 'manual', $source ); ?>><?php esc_html_e( 'Products I choose', 'mrn-mega-menu' ); ?></option>
				</select>
			</label>
			<label class="mrn-mm-field">
				<span><?php esc_html_e( 'Maximum', 'mrn-mega-menu' ); ?></span>
				<input type="number" data-field="limit" min="1" max="8" value="<?php echo esc_attr( max( 1, min( 8, $limit ) ) ); ?>">
			</label>
		</div>
		<div class="mrn-mm-field mrn-mm-manual-products" <?php echo 'manual' !== $source ? 'hidden' : ''; ?>>
			<span><?php esc_html_e( 'Choose products', 'mrn-mega-menu' ); ?></span>
			<?php if ( function_exists( 'wc_get_product' ) ) : ?>
				<select class="wc-product-search" multiple="multiple" data-field="product_ids" data-placeholder="<?php esc_attr_e( 'Search for products…', 'mrn-mega-menu' ); ?>" data-action="woocommerce_json_search_products_and_variations" style="width: 100%;">
					<?php foreach ( $selected as $product_id ) : ?>
						<?php $product = wc_get_product( $product_id ); ?>
						<?php if ( $product ) : ?><option value="<?php echo esc_attr( $product_id ); ?>" selected><?php echo esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ); ?></option><?php endif; ?>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<input type="text" data-field="product_ids" value="<?php echo esc_attr( implode( ', ', $selected ) ); ?>" placeholder="12, 34, 56" inputmode="numeric">
				<small><?php esc_html_e( 'WooCommerce will add product search when it is active.', 'mrn-mega-menu' ); ?></small>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_promo_fields( $block ) {
		$image_id = isset( $block['image_id'] ) ? absint( $block['image_id'] ) : 0;
		?>
		<div class="mrn-mm-promo-image">
			<div class="mrn-mm-promo-image__preview">
				<?php echo $image_id ? wp_kses_post( wp_get_attachment_image( $image_id, 'medium' ) ) : '<span class="dashicons dashicons-format-image"></span>'; ?>
			</div>
			<input type="hidden" data-field="image_id" value="<?php echo esc_attr( $image_id ); ?>">
			<div><button type="button" class="button mrn-mm-choose-image"><?php echo $image_id ? esc_html__( 'Replace image', 'mrn-mega-menu' ) : esc_html__( 'Choose image', 'mrn-mega-menu' ); ?></button> <button type="button" class="button-link-delete mrn-mm-remove-image" <?php echo ! $image_id ? 'hidden' : ''; ?>><?php esc_html_e( 'Remove', 'mrn-mega-menu' ); ?></button></div>
		</div>
		<label class="mrn-mm-field"><span><?php esc_html_e( 'Message', 'mrn-mega-menu' ); ?></span><textarea data-field="text" rows="3" maxlength="240"><?php echo esc_textarea( isset( $block['text'] ) ? $block['text'] : '' ); ?></textarea></label>
		<div class="mrn-mm-field-row">
			<label class="mrn-mm-field"><span><?php esc_html_e( 'Button label', 'mrn-mega-menu' ); ?></span><input type="text" data-field="link_label" value="<?php echo esc_attr( isset( $block['link_label'] ) ? $block['link_label'] : '' ); ?>" maxlength="80"></label>
			<label class="mrn-mm-field"><span><?php esc_html_e( 'Button URL', 'mrn-mega-menu' ); ?></span><input type="url" data-field="url" value="<?php echo esc_attr( isset( $block['url'] ) ? $block['url'] : '' ); ?>" placeholder="https://"></label>
		</div>
		<?php
	}

	private static function render_templates() {
		foreach ( array( 'menu', 'links', 'categories', 'products', 'promo', 'reusable' ) as $type ) {
			ob_start();
			self::render_block( array( 'type' => $type ), 0 );
			$template = ob_get_clean();
			?>
			<template data-block-template="<?php echo esc_attr( $type ); ?>"><?php echo $template; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every dynamic value is escaped by render_block(). ?></template>
			<?php
		}
		ob_start();
		self::render_link_row();
		$link_template = ob_get_clean();
		?>
		<template data-link-template><?php echo $link_template; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every dynamic value is escaped by render_link_row(). ?></template>
		<?php
	}

	public static function render_assignment( $post ) {
		$uses = array();
		foreach ( Plugin::get_enabled_menus() as $menu ) {
			if ( (int) $post->ID === Plugin::get_menu_layout_id( $menu->term_id ) ) {
				$uses[] = $menu;
			}
		}
		?>
		<p><?php esc_html_e( 'Layouts are optional presentation recipes used by native WordPress menus.', 'mrn-mega-menu' ); ?></p>
		<?php if ( $uses ) : ?>
			<ul>
				<?php foreach ( $uses as $menu ) : ?>
					<li><strong><?php echo esc_html( $menu->name ); ?></strong> — <a href="<?php echo esc_url( admin_url( 'nav-menus.php?action=edit&menu=' . absint( $menu->term_id ) ) ); ?>"><?php esc_html_e( 'Edit items', 'mrn-mega-menu' ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'No WordPress menu currently uses this layout.', 'mrn-mega-menu' ); ?></p>
		<?php endif; ?>
		<hr>
		<p><a href="<?php echo esc_url( admin_url( 'themes.php?page=mrn-mega-menus' ) ); ?>"><?php esc_html_e( 'Manage Mega Menu behavior', 'mrn-mega-menu' ); ?></a></p>
		<?php
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( Plugin::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded and sanitized field-by-field before persistence.
		$raw    = isset( $_POST['mrn_mega_menu_layout'] ) && is_string( $_POST['mrn_mega_menu_layout'] ) ? wp_unslash( $_POST['mrn_mega_menu_layout'] ) : '';
		$layout = json_decode( $raw, true );
		if ( is_array( $layout ) ) {
			update_post_meta( $post_id, Plugin::META_LAYOUT, self::sanitize_layout( $layout ) );
		}

	}

	private static function sanitize_layout( $layout ) {
		$layout = Plugin::normalize_layout( $layout );
		$clean = array(
			'width'  => isset( $layout['width'] ) && 'full' === $layout['width'] ? 'full' : 'content',
			'panels' => array(),
		);
		$panels = isset( $layout['panels'] ) && is_array( $layout['panels'] ) ? array_slice( $layout['panels'], 0, 12 ) : array();
		foreach ( $panels as $panel ) {
			$clean_panel = array(
				'menu_item_id'  => absint( $panel['menu_item_id'] ?? 0 ),
				'label_override' => sanitize_text_field( $panel['label_override'] ?? '' ),
				'display_mode'  => Plugin::sanitize_display_mode( $panel['display_mode'] ?? 'mega' ),
				'parent_click'  => Plugin::sanitize_parent_click_override( $panel['parent_click'] ?? 'inherit' ),
				'item_icon'     => Plugin::sanitize_icon( $panel['item_icon'] ?? array() ),
				'arrow_icon'    => Plugin::sanitize_icon( $panel['arrow_icon'] ?? array() ),
				'child_arrow_icon' => Plugin::sanitize_icon( $panel['child_arrow_icon'] ?? ( $panel['child_icon'] ?? array() ) ),
				'columns'       => array(),
			);
			$columns = isset( $panel['columns'] ) && is_array( $panel['columns'] ) ? array_slice( $panel['columns'], 0, 6 ) : array();
			foreach ( $columns as $column ) {
				$clean_column = array(
					'blocks'              => array(),
					'visible_in_dropdown' => ! isset( $column['visible_in_dropdown'] ) || ! empty( $column['visible_in_dropdown'] ),
				);
				$blocks       = isset( $column['blocks'] ) && is_array( $column['blocks'] ) ? array_slice( $column['blocks'], 0, 20 ) : array();
				foreach ( $blocks as $block ) {
					$clean_block = self::sanitize_block( $block );
					if ( $clean_block ) {
						$clean_column['blocks'][] = $clean_block;
					}
				}
				$clean_panel['columns'][] = $clean_column;
			}
			if ( empty( $clean_panel['columns'] ) ) {
				$clean_panel['columns'][] = array( 'blocks' => array(), 'visible_in_dropdown' => true );
			}
			$clean['panels'][] = $clean_panel;
		}
		if ( empty( $clean['panels'] ) ) {
			$clean['panels'][] = array(
				'menu_item_id'  => 0,
				'label_override' => '',
				'display_mode'  => 'mega',
				'parent_click'  => 'inherit',
				'item_icon'     => array( 'type' => '', 'value' => '' ),
				'arrow_icon'    => array( 'type' => '', 'value' => '' ),
				'child_arrow_icon' => array( 'type' => '', 'value' => '' ),
				'columns'       => array( array( 'blocks' => array(), 'visible_in_dropdown' => true ) ),
			);
		}
		return $clean;
	}

	private static function sanitize_block( $block ) {
		$type = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
		if ( ! in_array( $type, array( 'menu', 'links', 'categories', 'products', 'promo', 'reusable' ), true ) ) {
			return null;
		}
		$clean = array(
			'type'  => $type,
			'title' => isset( $block['title'] ) ? self::short_text( $block['title'], 120 ) : '',
		);
		if ( 'menu' === $type ) {
			$branch_modes          = array( 'parent_and_children', 'children_only', 'parent_only' );
			$clean['source']       = isset( $block['source'] ) && 'selected' === $block['source'] ? 'selected' : 'assigned_children';
			$clean['menu_id']      = isset( $block['menu_id'] ) ? absint( $block['menu_id'] ) : 0;
			$clean['root_item_id'] = isset( $block['root_item_id'] ) ? absint( $block['root_item_id'] ) : 0;
			$clean['branch_mode']  = isset( $block['branch_mode'] ) && in_array( $block['branch_mode'], $branch_modes, true ) ? $block['branch_mode'] : 'parent_and_children';
			$clean['distribution_index'] = isset( $block['distribution_index'] ) ? min( 11, absint( $block['distribution_index'] ) ) : 0;
			$clean['distribution_total'] = isset( $block['distribution_total'] ) ? max( 1, min( 12, absint( $block['distribution_total'] ) ) ) : 1;
		} elseif ( 'links' === $type ) {
			$clean['links'] = array();
			$links          = isset( $block['links'] ) && is_array( $block['links'] ) ? array_slice( $block['links'], 0, 20 ) : array();
			foreach ( $links as $link ) {
				$label = isset( $link['label'] ) ? self::short_text( $link['label'], 100 ) : '';
				$url   = isset( $link['url'] ) ? esc_url_raw( $link['url'] ) : '';
				if ( $label && $url ) {
					$clean['links'][] = array(
						'label'  => $label,
						'url'    => $url,
						'target' => isset( $link['target'] ) && '_blank' === $link['target'] ? '_blank' : '',
					);
				}
			}
		} elseif ( 'categories' === $type ) {
			$clean['category_ids'] = isset( $block['category_ids'] ) && is_array( $block['category_ids'] ) ? array_slice( array_values( array_unique( array_filter( array_map( 'absint', $block['category_ids'] ) ) ) ), 0, 20 ) : array();
			$category_displays         = array( 'links', 'links_descriptions', 'descriptions' );
			$legacy_display            = isset( $block['category_display'] ) && in_array( $block['category_display'], $category_displays, true )
				? $block['category_display']
				: ( ! empty( $block['show_category_descriptions'] ) ? 'links_descriptions' : 'links' );
			$name_displays             = array( 'link', 'text', 'hidden' );
			$description_sources       = array( 'none', 'description', 'short_description' );
			$clean['category_name_display'] = isset( $block['category_name_display'] ) && in_array( $block['category_name_display'], $name_displays, true )
				? $block['category_name_display']
				: ( 'descriptions' === $legacy_display ? 'hidden' : 'link' );
			$clean['category_name_tag'] = self::sanitize_category_name_tag( $block['category_name_tag'] ?? 'span' );
			$clean['category_description_source'] = isset( $block['category_description_source'] ) && in_array( $block['category_description_source'], $description_sources, true )
				? $block['category_description_source']
				: ( 'links' === $legacy_display ? 'none' : 'description' );
		} elseif ( 'products' === $type ) {
			$sources              = array( 'featured', 'sale', 'latest', 'manual' );
			$clean['source']      = isset( $block['source'] ) && in_array( $block['source'], $sources, true ) ? $block['source'] : 'featured';
			$clean['limit']       = isset( $block['limit'] ) ? max( 1, min( 8, absint( $block['limit'] ) ) ) : 4;
			$clean['product_ids'] = isset( $block['product_ids'] ) && is_array( $block['product_ids'] ) ? array_slice( array_values( array_unique( array_filter( array_map( 'absint', $block['product_ids'] ) ) ) ), 0, 20 ) : array();
		} elseif ( 'reusable' === $type ) {
			$clean['reusable_block_id'] = isset( $block['reusable_block_id'] ) ? absint( $block['reusable_block_id'] ) : 0;
		} else {
			$clean['image_id']   = isset( $block['image_id'] ) ? absint( $block['image_id'] ) : 0;
			$clean['text']       = isset( $block['text'] ) ? self::short_text( $block['text'], 240 ) : '';
			$clean['link_label'] = isset( $block['link_label'] ) ? self::short_text( $block['link_label'], 80 ) : '';
			$clean['url']        = isset( $block['url'] ) ? esc_url_raw( $block['url'] ) : '';
		}
		return $clean;
	}

	private static function short_text( $value, $length ) {
		$value = sanitize_text_field( $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	private static function sanitize_category_name_tag( $value ) {
		$value = is_scalar( $value ) ? strtolower( (string) $value ) : '';
		return in_array( $value, array( 'span', 'p', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $value : 'span';
	}

	public static function remove_assignment( $post_id ) {
		if ( Plugin::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		$assignments = get_option( Plugin::OPTION_ASSIGNMENTS, array() );
		$assignments = is_array( $assignments ) ? $assignments : array();
		foreach ( $assignments as $item_id => $panel_id ) {
			if ( (int) $panel_id === (int) $post_id ) {
				unset( $assignments[ $item_id ] );
			}
		}
		update_option( Plugin::OPTION_ASSIGNMENTS, $assignments, false );

		foreach ( Plugin::get_enabled_menus() as $menu ) {
			if ( (int) $post_id === Plugin::get_menu_layout_id( $menu->term_id ) ) {
				update_term_meta( $menu->term_id, Plugin::MENU_META_LAYOUT_ID, 0 );
			}
		}
	}

	public static function title_placeholder( $title, $post ) {
		return Plugin::POST_TYPE === $post->post_type ? __( 'Name this mega layout (for example, Product navigation)', 'mrn-mega-menu' ) : $title;
	}

	public static function updated_messages( $messages ) {
		if ( isset( $messages[ Plugin::POST_TYPE ] ) ) {
			$messages[ Plugin::POST_TYPE ][1] = __( 'Mega layout saved.', 'mrn-mega-menu' );
			$messages[ Plugin::POST_TYPE ][6] = __( 'Mega layout published.', 'mrn-mega-menu' );
		}
		return $messages;
	}

	private static function block_icon( $type ) {
		$icons = array(
			'menu'       => 'dashicons-admin-links',
			'links'      => 'dashicons-editor-ul',
			'categories' => 'dashicons-category',
			'products'   => 'dashicons-cart',
			'promo'      => 'dashicons-format-image',
			'reusable'   => 'dashicons-screenoptions',
		);
		return isset( $icons[ $type ] ) ? $icons[ $type ] : 'dashicons-admin-links';
	}

	private static function render_icon_control( $field, $label, $icon, $description = '' ) {
		$icon = Plugin::sanitize_icon( $icon );
		?>
		<div class="mrn-mm-icon-control" data-mrn-icon-control>
			<span class="mrn-mm-icon-control__label"><?php echo esc_html( $label ); ?></span>
			<input type="hidden" data-icon-value data-panel-field="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( wp_json_encode( $icon ) ); ?>">
			<span class="mrn-mm-icon-preview" data-icon-preview aria-hidden="true"></span>
			<button type="button" class="button" data-choose-icon><?php esc_html_e( 'Choose icon', 'mrn-mega-menu' ); ?></button>
			<button type="button" class="button-link" data-clear-icon<?php echo $icon['value'] ? '' : ' hidden'; ?>><?php esc_html_e( 'Clear', 'mrn-mega-menu' ); ?></button>
			<?php if ( $description ) : ?><small><?php echo esc_html( $description ); ?></small><?php endif; ?>
		</div>
		<?php
	}

	private static function next_block_index( $panels ) {
		$count = 0;
		foreach ( $panels as $panel ) {
			foreach ( isset( $panel['columns'] ) && is_array( $panel['columns'] ) ? $panel['columns'] : array() as $column ) {
				$count += isset( $column['blocks'] ) && is_array( $column['blocks'] ) ? count( $column['blocks'] ) : 0;
			}
		}
		return $count + 1;
	}

	private static function panel_label( $panel, $panel_index ) {
		$menu_item_id = absint( $panel['menu_item_id'] ?? 0 );
		if ( $menu_item_id ) {
			$item = get_post( $menu_item_id );
			if ( $item && 'nav_menu_item' === $item->post_type ) {
				$menu_item = wp_setup_nav_menu_item( $item );
				if ( $menu_item && ! empty( $menu_item->title ) ) {
					return wp_strip_all_tags( $menu_item->title );
				}
			}
		}
		return sprintf( __( 'Unassigned %d', 'mrn-mega-menu' ), $panel_index + 1 );
	}

	private static function assigned_menu_id( $layout_id ) {
		foreach ( Plugin::get_enabled_menus() as $menu ) {
			if ( absint( $layout_id ) === Plugin::get_menu_layout_id( $menu->term_id ) ) {
				return absint( $menu->term_id );
			}
		}
		return 0;
	}
}
