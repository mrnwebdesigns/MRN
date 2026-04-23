<?php
/**
 * Runtime-discovered dummy content generator.
 *
 * @package mrn-dummy-content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MRN_Dummy_Content {
	const VERSION = '0.1.14';
	const NONCE_ACTION = 'mrn_dummy_content_generate';
	const CUSTOM_NONCE_ACTION = 'mrn_dummy_content_generate_custom';
	const DELETE_NONCE_ACTION = 'mrn_dummy_content_delete';
	const MENU_SLUG = 'mrn-dummy-content';
	const TAB_QUERY_ARG = 'mrn_dummy_content_tab';
	const NOTICE_QUERY_ARG = 'mrn_dummy_content_notice';
	const PLACEHOLDER_FLAG_META = '_mrn_dummy_content_placeholder';
	const GENERATED_BY_META = '_mrn_dummy_content_generated_by';
	const GENERATED_AT_META = '_mrn_dummy_content_generated_at';

	/**
	 * Per-request cache of discovered ACF fields by post type.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private static $acf_fields_cache = array();

	/**
	 * Per-request cache of related posts for relationship-style fields.
	 *
	 * @var array<string, WP_Post|null>
	 */
	private static $related_post_cache = array();

	/**
	 * Per-request cache of sample terms by taxonomy.
	 *
	 * @var array<string, WP_Term|null>
	 */
	private static $sample_term_cache = array();

	/**
	 * Per-request cache of the placeholder attachment ID.
	 *
	 * @var int|null
	 */
	private static $placeholder_attachment_id = null;

	/**
	 * Track whether the placeholder file was refreshed during this request.
	 *
	 * @var bool
	 */
	private static $placeholder_attachment_refreshed = false;

	/**
	 * Bootstrap hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_tools_page' ) );
		add_action( 'admin_post_mrn_dummy_content_generate', array( __CLASS__, 'handle_generate' ) );
		add_action( 'admin_post_mrn_dummy_content_generate_custom', array( __CLASS__, 'handle_generate_custom' ) );
		add_action( 'admin_post_mrn_dummy_content_delete', array( __CLASS__, 'handle_delete' ) );
		add_filter( 'wp_list_pages_excludes', array( __CLASS__, 'exclude_generated_pages_from_page_menu' ) );
	}

	/**
	 * Register the admin screen.
	 *
	 * @return void
	 */
	public static function register_tools_page() {
		add_management_page(
			__( 'Dummy Content', 'mrn-dummy-content' ),
			__( 'Dummy Content', 'mrn-dummy-content' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_tools_page' )
		);
	}

	/**
	 * Render the generator UI.
	 *
	 * @return void
	 */
	public static function render_tools_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::maybe_load_sticky_toolbar_helper();

		$notice                = self::get_notice_from_request();
		$active_tab            = self::get_active_tab_from_request();
		$custom_types          = self::get_target_post_types();
		$flex_fields           = self::get_page_flexible_fields();
		$layout_labels         = self::get_all_layout_page_labels();
		$custom_layout_options = self::get_custom_layout_options_by_post_type();
		$sidebar_variants      = self::get_sidebar_variants();
		$shell_size_variants   = self::get_shell_size_variants();
		$generated             = self::get_generated_posts();
		$placeholders          = self::get_placeholder_attachments();

		$default_custom_post_type = isset( $custom_types['page'] ) ? 'page' : (string) array_key_first( $custom_types );
		if ( '' === $default_custom_post_type ) {
			$default_custom_post_type = 'post';
		}
		?>
		<div class="wrap">
			<?php self::render_tabbed_toolbar( $active_tab ); ?>
			<?php self::render_progress_ui(); ?>
			<h1><?php esc_html_e( 'Dummy Content', 'mrn-dummy-content' ); ?></h1>
			<p><?php esc_html_e( 'This tool scans the active site at runtime, creates sample content for available custom post types, and builds an all-layouts page from any ACF flexible-content page builder fields it can find.', 'mrn-dummy-content' ); ?></p>

			<?php if ( ! empty( $notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<div id="mrn-dummy-panel-generate" class="mrn-dummy-content-panel<?php echo 'generate' === $active_tab ? ' is-active' : ''; ?>" data-mrn-dummy-panel="generate" role="tabpanel" aria-labelledby="mrn-dummy-tab-generate"<?php echo 'generate' === $active_tab ? '' : ' hidden'; ?>>
				<h2><?php esc_html_e( 'Detected Content Types', 'mrn-dummy-content' ); ?></h2>
				<?php if ( empty( $custom_types ) ) : ?>
					<p><?php esc_html_e( 'No public custom post types were detected. The generator can still create an all-layouts page if page-builder fields exist.', 'mrn-dummy-content' ); ?></p>
				<?php else : ?>
					<ul>
						<?php foreach ( $custom_types as $slug => $post_type ) : ?>
							<li><?php echo esc_html( $post_type->labels->singular_name . ' (' . $slug . ')' ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<h2><?php esc_html_e( 'Detected Page Layout Fields', 'mrn-dummy-content' ); ?></h2>
				<?php if ( empty( $flex_fields ) ) : ?>
					<p><?php esc_html_e( 'No ACF flexible-content page fields were detected for pages. A page will still be created, but it will not contain auto-built layouts.', 'mrn-dummy-content' ); ?></p>
				<?php else : ?>
					<ul>
						<?php foreach ( $flex_fields as $field ) : ?>
							<li><?php echo esc_html( $field['label'] . ' (' . $field['name'] . ')' ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<h2><?php esc_html_e( 'All Layouts Page Will Include', 'mrn-dummy-content' ); ?></h2>
				<?php if ( empty( $layout_labels ) ) : ?>
					<p><?php esc_html_e( 'No compatible layouts were detected for the current site.', 'mrn-dummy-content' ); ?></p>
				<?php else : ?>
					<ul>
						<?php foreach ( $layout_labels as $layout_label ) : ?>
							<li><?php echo esc_html( $layout_label ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<form id="mrn-dummy-generate-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mrn-dummy-content-action-form" data-progress-message="<?php echo esc_attr__( 'Generating dummy content. This can take a couple of minutes on larger sites.', 'mrn-dummy-content' ); ?>">
					<input type="hidden" name="action" value="mrn_dummy_content_generate" />
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				</form>

				<h2><?php esc_html_e( 'Delete Generated Content', 'mrn-dummy-content' ); ?></h2>
				<?php if ( empty( $generated ) && empty( $placeholders ) ) : ?>
					<p><?php esc_html_e( 'No plugin-generated content is currently stored on this site.', 'mrn-dummy-content' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'This deletes only posts, pages, and placeholder media created by Dummy Content.', 'mrn-dummy-content' ); ?></p>
					<ul>
						<?php foreach ( $generated as $post ) : ?>
							<li><?php echo esc_html( sprintf( '%s: %s (#%d)', $post->post_type, get_the_title( $post ), (int) $post->ID ) ); ?></li>
						<?php endforeach; ?>
						<?php foreach ( $placeholders as $attachment ) : ?>
							<li><?php echo esc_html( sprintf( 'attachment: %s (#%d)', get_the_title( $attachment ), (int) $attachment->ID ) ); ?></li>
						<?php endforeach; ?>
					</ul>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mrn-dummy-content-action-form mrn-dummy-content-action-form--delete" data-progress-message="<?php echo esc_attr__( 'Deleting plugin-generated content. Please keep this tab open until WordPress finishes.', 'mrn-dummy-content' ); ?>" onsubmit="return window.confirm('Delete only Dummy Content generated items?');">
						<input type="hidden" name="action" value="mrn_dummy_content_delete" />
						<?php wp_nonce_field( self::DELETE_NONCE_ACTION ); ?>
						<?php submit_button( __( 'Delete Generated Content', 'mrn-dummy-content' ), 'delete', 'submit', false, array( 'data-loading-label' => __( 'Deleting Generated Content...', 'mrn-dummy-content' ) ) ); ?>
					</form>
				<?php endif; ?>
			</div>

			<div id="mrn-dummy-panel-custom" class="mrn-dummy-content-panel<?php echo 'custom' === $active_tab ? ' is-active' : ''; ?>" data-mrn-dummy-panel="custom" role="tabpanel" aria-labelledby="mrn-dummy-tab-custom"<?php echo 'custom' === $active_tab ? '' : ' hidden'; ?>>
				<h2><?php esc_html_e( 'Create Custom Entry', 'mrn-dummy-content' ); ?></h2>
				<p><?php esc_html_e( 'Create one or more pages/posts/CPT entries and optionally choose exactly which compatible builder layouts to seed.', 'mrn-dummy-content' ); ?></p>

				<form id="mrn-dummy-custom-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mrn-dummy-content-action-form" data-progress-message="<?php echo esc_attr__( 'Creating custom dummy content. Please keep this tab open until WordPress finishes.', 'mrn-dummy-content' ); ?>">
					<input type="hidden" name="action" value="mrn_dummy_content_generate_custom" />
					<?php wp_nonce_field( self::CUSTOM_NONCE_ACTION ); ?>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="mrn-dummy-custom-post-type"><?php esc_html_e( 'Content Type', 'mrn-dummy-content' ); ?></label></th>
								<td>
									<select id="mrn-dummy-custom-post-type" name="custom_post_type" required>
										<?php foreach ( $custom_types as $slug => $post_type ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>"<?php selected( $slug, $default_custom_post_type ); ?>><?php echo esc_html( $post_type->labels->singular_name . ' (' . $slug . ')' ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="mrn-dummy-custom-title"><?php esc_html_e( 'Title', 'mrn-dummy-content' ); ?></label></th>
								<td>
									<input type="text" id="mrn-dummy-custom-title" name="custom_title" class="regular-text" required />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="mrn-dummy-custom-slug"><?php esc_html_e( 'Slug (Optional)', 'mrn-dummy-content' ); ?></label></th>
								<td>
									<input type="text" id="mrn-dummy-custom-slug" name="custom_slug" class="regular-text" />
									<p class="description"><?php esc_html_e( 'Leave blank to let WordPress create the slug automatically.', 'mrn-dummy-content' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="mrn-dummy-custom-count"><?php esc_html_e( 'Quantity', 'mrn-dummy-content' ); ?></label></th>
								<td>
									<input type="number" id="mrn-dummy-custom-count" name="custom_count" class="small-text" min="1" max="50" step="1" value="1" />
									<p class="description"><?php esc_html_e( 'Create this many entries in one run (1-50).', 'mrn-dummy-content' ); ?></p>
								</td>
							</tr>
							<tr data-mrn-dummy-page-option>
								<th scope="row"><label for="mrn-dummy-custom-sidebar-layout"><?php esc_html_e( 'Sidebar Layout', 'mrn-dummy-content' ); ?></label></th>
								<td>
									<select id="mrn-dummy-custom-sidebar-layout" name="custom_sidebar_layout">
										<?php foreach ( $sidebar_variants as $sidebar_layout => $sidebar_label ) : ?>
											<option value="<?php echo esc_attr( $sidebar_layout ); ?>"><?php echo esc_html( $sidebar_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr data-mrn-dummy-page-option>
								<th scope="row"><label for="mrn-dummy-custom-shell-width"><?php esc_html_e( 'Section Width', 'mrn-dummy-content' ); ?></label></th>
								<td>
									<select id="mrn-dummy-custom-shell-width" name="custom_shell_width">
										<?php foreach ( $shell_size_variants as $shell_width => $shell_label ) : ?>
											<option value="<?php echo esc_attr( $shell_width ); ?>"><?php echo esc_html( $shell_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Layouts', 'mrn-dummy-content' ); ?></th>
								<td>
									<p class="description"><?php esc_html_e( 'Choose the layout rows to include. Leave all unchecked to use the plugin defaults for that content type.', 'mrn-dummy-content' ); ?></p>
									<div class="mrn-dummy-layout-groups" data-mrn-dummy-layout-groups>
										<?php foreach ( $custom_layout_options as $post_type_slug => $layout_options ) : ?>
											<fieldset class="mrn-dummy-layout-group" data-mrn-layout-group="<?php echo esc_attr( $post_type_slug ); ?>"<?php echo $post_type_slug === $default_custom_post_type ? '' : ' hidden'; ?>>
												<?php if ( empty( $layout_options ) ) : ?>
													<p class="description"><?php esc_html_e( 'No compatible layouts were detected for this content type.', 'mrn-dummy-content' ); ?></p>
												<?php else : ?>
													<div class="mrn-dummy-layout-grid">
														<?php foreach ( $layout_options as $layout_option ) : ?>
															<label class="mrn-dummy-layout-choice">
																<input type="checkbox" name="custom_layouts[]" value="<?php echo esc_attr( $layout_option['name'] ); ?>"<?php echo $post_type_slug === $default_custom_post_type ? '' : ' disabled'; ?> />
																<span><?php echo esc_html( $layout_option['label'] ); ?></span>
															</label>
														<?php endforeach; ?>
													</div>
												<?php endif; ?>
											</fieldset>
										<?php endforeach; ?>
									</div>
								</td>
							</tr>
						</tbody>
					</table>

				</form>
			</div>
		</div>
		<?php self::render_tabbed_ui_script( $active_tab ); ?>
		<?php
	}

	/**
	 * Load the shared sticky toolbar helper when available.
	 *
	 * @return void
	 */
	private static function maybe_load_sticky_toolbar_helper() {
		$local_toolbar_helper = __DIR__ . '/mrn-sticky-settings-toolbar.php';
		if ( file_exists( $local_toolbar_helper ) ) {
			require_once $local_toolbar_helper;
		}
	}

	/**
	 * Resolve the active admin tab.
	 *
	 * @return string
	 */
	private static function get_active_tab_from_request() {
		$tab = filter_input( INPUT_GET, self::TAB_QUERY_ARG, FILTER_UNSAFE_RAW );
		$tab = sanitize_key( is_string( $tab ) ? wp_unslash( $tab ) : '' );

		return in_array( $tab, array( 'generate', 'custom' ), true ) ? $tab : 'generate';
	}

	/**
	 * Render the universal sticky tab toolbar.
	 *
	 * @param string $active_tab Active tab key.
	 * @return void
	 */
	private static function render_tabbed_toolbar( $active_tab ) {
		if ( function_exists( 'mrn_sticky_toolbar_render' ) ) {
			mrn_sticky_toolbar_render(
				array(
					'toolbar_id' => 'mrn-dummy-content-toolbar',
					'form_id'    => '',
					'title'      => 'Dummy Content Actions',
					'save_label' => 'Generate',
					'aria_label' => 'Dummy Content tabs',
					'tabs'       => array(
						array(
							'key'    => 'generate',
							'label'  => 'Generate All',
							'active' => 'generate' === $active_tab,
						),
						array(
							'key'    => 'custom',
							'label'  => 'Custom',
							'active' => 'custom' === $active_tab,
						),
					),
				)
			);

			return;
		}

		?>
		<div id="mrn-dummy-content-toolbar" class="mrn-sticky-save-bar" aria-label="<?php esc_attr_e( 'Dummy Content tabs', 'mrn-dummy-content' ); ?>">
			<div class="mrn-settings-toolbar__meta">
				<span class="mrn-settings-toolbar__title"><?php esc_html_e( 'Dummy Content Actions', 'mrn-dummy-content' ); ?></span>
			</div>
			<div class="mrn-settings-toolbar__actions">
				<nav class="mrn-settings-tabs" aria-label="<?php esc_attr_e( 'Dummy Content tabs', 'mrn-dummy-content' ); ?>">
					<button id="mrn-dummy-tab-generate" type="button" class="mrn-settings-tab<?php echo 'generate' === $active_tab ? ' is-active' : ''; ?>" data-mrn-tab="generate" aria-pressed="<?php echo 'generate' === $active_tab ? 'true' : 'false'; ?>"><?php esc_html_e( 'Generate All', 'mrn-dummy-content' ); ?></button>
					<button id="mrn-dummy-tab-custom" type="button" class="mrn-settings-tab<?php echo 'custom' === $active_tab ? ' is-active' : ''; ?>" data-mrn-tab="custom" aria-pressed="<?php echo 'custom' === $active_tab ? 'true' : 'false'; ?>"><?php esc_html_e( 'Custom', 'mrn-dummy-content' ); ?></button>
				</nav>
				<button type="submit" id="mrn-dummy-toolbar-generate" class="button button-primary mrn-settings-tab mrn-settings-tab--save" form="<?php echo 'custom' === $active_tab ? 'mrn-dummy-custom-form' : 'mrn-dummy-generate-form'; ?>" data-loading-label="<?php echo esc_attr__( 'Generating...', 'mrn-dummy-content' ); ?>">
					<?php esc_html_e( 'Generate', 'mrn-dummy-content' ); ?>
				</button>
			</div>
		</div>
		<div class="mrn-admin-toolbar-spacer" data-mrn-toolbar-spacer-for="mrn-dummy-content-toolbar" aria-hidden="true"></div>
		<?php
	}

	/**
	 * Render styles and scripts for tab and custom-layout interactions.
	 *
	 * @param string $active_tab Active tab key.
	 * @return void
	 */
	private static function render_tabbed_ui_script( $active_tab ) {
		?>
		<?php
		if ( function_exists( 'mrn_sticky_toolbar_universal_css' ) ) {
			?>
			<style>
			<?php echo mrn_sticky_toolbar_universal_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</style>
			<?php
		}

		if ( function_exists( 'mrn_sticky_toolbar_render_css' ) ) {
			mrn_sticky_toolbar_render_css(
				array(
					'toolbar_id'   => 'mrn-dummy-content-toolbar',
					'page_class'   => 'tools_page_mrn-dummy-content',
					'desktop_left' => 196,
					'desktop_right' => 0,
					'mobile_left' => 10,
					'mobile_right' => 10,
					'spacer_height' => 88,
					'spacer_height_mobile' => 120,
				)
			);
		}
		?>
		<style>
			<?php if ( ! function_exists( 'mrn_sticky_toolbar_render_css' ) ) : ?>
			.mrn-settings-tabs {
				display: flex;
				flex-wrap: wrap;
				gap: 8px;
				margin: 0;
				align-items: center;
				padding: 4px;
				border-radius: 8px;
				background: rgba(255, 255, 255, 0.06);
			}
			.mrn-settings-toolbar__meta {
				display: flex;
				align-items: center;
				gap: 10px;
				min-width: 0;
				flex: 0 0 auto;
			}
			.mrn-settings-toolbar__title {
				font-weight: 600;
				color: #f6f7f7;
				white-space: nowrap;
			}
			.mrn-settings-toolbar__actions {
				display: flex;
				align-items: center;
				gap: 12px;
				margin-left: 24px;
				min-width: 0;
				flex: 1 1 auto;
			}
			.mrn-settings-tab {
				appearance: none;
				border: 0;
				background: transparent;
				color: #f6f7f7;
				border-radius: 4px;
				padding: 7px 10px;
				font-weight: 600;
				line-height: 1.2;
				cursor: pointer;
				display: inline-flex;
				align-items: center;
				gap: 6px;
				margin: 0;
			}
			.mrn-settings-tab:hover,
			.mrn-settings-tab:focus {
				background: #2c2c2c;
				color: #ffffff;
				outline: none;
			}
			.mrn-settings-tab.is-active {
				background: #4a4a4a;
				color: #ffffff;
				border-radius: 4px;
			}
			.mrn-sticky-save-bar .mrn-settings-tab--save.button.button-primary {
				margin-left: auto;
				min-height: 36px;
				border-radius: 6px;
				background: #f6f7f7;
				border-color: #8c8f94;
				color: #1d2327;
				box-shadow: none;
			}
			.mrn-sticky-save-bar .mrn-settings-tab--save.button.button-primary:hover,
			.mrn-sticky-save-bar .mrn-settings-tab--save.button.button-primary:focus {
				background: #ffffff;
				border-color: #c3c4c7;
				color: #1d2327;
			}
			.mrn-admin-toolbar-spacer {
				height: 88px;
			}
			#mrn-dummy-content-toolbar {
				--mrn-toolbar-left: 196px;
				--mrn-toolbar-right: 0px;
				position: fixed;
				top: 32px;
				left: var(--mrn-toolbar-left);
				right: var(--mrn-toolbar-right);
				z-index: 99990;
				width: calc(100vw - var(--mrn-toolbar-left) - var(--mrn-toolbar-right));
				display: flex;
				align-items: center;
				justify-content: flex-start;
				gap: 12px;
				box-sizing: border-box;
				margin: 0;
				padding: 10px 18px 10px 16px;
				border: 1px solid #2c2c2c;
				border-radius: 0;
				background: #1d2327;
				box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
			}
			.folded.tools_page_mrn-dummy-content #mrn-dummy-content-toolbar {
				--mrn-toolbar-left: 56px;
			}
			@media (max-width: 1000px) {
				#mrn-dummy-content-toolbar {
					--mrn-toolbar-left: 10px;
					--mrn-toolbar-right: 10px;
					top: 46px;
					flex-wrap: wrap;
					width: calc(100vw - var(--mrn-toolbar-left) - var(--mrn-toolbar-right));
				}
				.mrn-settings-toolbar__actions {
					flex-wrap: wrap;
					width: 100%;
				}
				.mrn-admin-toolbar-spacer {
					height: 120px;
				}
			}
			<?php endif; ?>
			.mrn-dummy-content-panel[hidden] {
				display: none !important;
			}
			.mrn-dummy-layout-group {
				margin: 0;
				padding: 8px 0 0;
				border: 0;
			}
			.mrn-dummy-layout-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
				gap: 8px 14px;
				margin-top: 6px;
			}
			.mrn-dummy-layout-choice {
				display: inline-flex;
				align-items: flex-start;
				gap: 8px;
				line-height: 1.4;
			}
			.mrn-dummy-layout-choice input {
				margin-top: 2px;
			}
			#mrn-dummy-panel-custom textarea,
			#mrn-dummy-panel-generate textarea {
				height: 84px;
				min-height: 84px;
				resize: vertical;
			}
			#mrn-dummy-panel-custom .wp-editor-wrap textarea.wp-editor-area,
			#mrn-dummy-panel-generate .wp-editor-wrap textarea.wp-editor-area {
				height: 84px;
				min-height: 84px;
			}
		</style>
		<script>
			(function () {
				var toolbar = document.getElementById('mrn-dummy-content-toolbar');
				if (!toolbar || toolbar.dataset.mrnDummyTabsInit === '1') {
					return;
				}

				toolbar.dataset.mrnDummyTabsInit = '1';

				var tabButtons = Array.prototype.slice.call(toolbar.querySelectorAll('[data-mrn-tab]'));
				var panels = Array.prototype.slice.call(document.querySelectorAll('[data-mrn-dummy-panel]'));
				var generateButton = toolbar.querySelector('.mrn-settings-tab--save') || document.getElementById('mrn-dummy-toolbar-generate');
				var activeTab = <?php echo wp_json_encode( $active_tab ); ?>;
				var defaultTab = activeTab || 'generate';

				if (generateButton && !generateButton.getAttribute('data-loading-label')) {
					generateButton.setAttribute('data-loading-label', 'Generating...');
				}

				tabButtons.forEach(function (button) {
					var tabKey = button.getAttribute('data-mrn-tab') || '';
					if (tabKey === 'generate' && !button.id) {
						button.id = 'mrn-dummy-tab-generate';
					}
					if (tabKey === 'custom' && !button.id) {
						button.id = 'mrn-dummy-tab-custom';
					}
				});

				function activateTab(tabKey, updateHash) {
					if (!tabKey) {
						tabKey = defaultTab;
					}

					var hasMatch = false;

					panels.forEach(function (panel) {
						var isActive = panel.getAttribute('data-mrn-dummy-panel') === tabKey;
						panel.hidden = !isActive;
						if (isActive) {
							hasMatch = true;
						}
					});

					tabButtons.forEach(function (button) {
						var isActive = button.getAttribute('data-mrn-tab') === tabKey;
						button.classList.toggle('is-active', isActive);
						button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
					});

					if (hasMatch && generateButton) {
						generateButton.setAttribute('form', tabKey === 'custom' ? 'mrn-dummy-custom-form' : 'mrn-dummy-generate-form');
					}

					if (hasMatch && updateHash) {
						if (window.history && window.history.replaceState) {
							window.history.replaceState(null, '', '#tab-' + tabKey);
						} else {
							window.location.hash = 'tab-' + tabKey;
						}
					}
				}

				tabButtons.forEach(function (button) {
					button.addEventListener('click', function (event) {
						event.preventDefault();
						event.stopPropagation();
						activateTab(button.getAttribute('data-mrn-tab') || 'generate', true);
					});
				});

				window.addEventListener('hashchange', function () {
					var hashTab = window.location.hash ? window.location.hash.replace(/^#tab-/, '') : '';
					if (hashTab) {
						activateTab(hashTab, false);
					}
				});

				var initialHash = window.location.hash ? window.location.hash.replace(/^#tab-/, '') : '';
				activateTab(initialHash || defaultTab, false);

				var postTypeSelect = document.getElementById('mrn-dummy-custom-post-type');
				var pageOnlyRows = Array.prototype.slice.call(document.querySelectorAll('[data-mrn-dummy-page-option]'));
				var layoutGroups = Array.prototype.slice.call(document.querySelectorAll('[data-mrn-layout-group]'));

				function syncCustomControls() {
					if (!postTypeSelect) {
						return;
					}

					var postType = postTypeSelect.value || '';
					var isPage = postType === 'page';

					pageOnlyRows.forEach(function (row) {
						row.hidden = !isPage;
						Array.prototype.slice.call(row.querySelectorAll('select, input')).forEach(function (control) {
							control.disabled = !isPage;
						});
					});

					layoutGroups.forEach(function (group) {
						var isActive = group.getAttribute('data-mrn-layout-group') === postType;
						group.hidden = !isActive;
						Array.prototype.slice.call(group.querySelectorAll('input[type="checkbox"]')).forEach(function (checkbox) {
							checkbox.disabled = !isActive;
						});
					});
				}

				if (postTypeSelect) {
					postTypeSelect.addEventListener('change', syncCustomControls);
				}

				syncCustomControls();
			})();
		</script>
		<?php
	}

	/**
	 * Gather compatible layouts for each selectable post type.
	 *
	 * @return array<string, array<int, array{name:string,label:string}>>
	 */
	private static function get_custom_layout_options_by_post_type() {
		$options = array();

		foreach ( self::get_target_post_types() as $post_type => $object ) {
			unset( $object );
			$options[ $post_type ] = self::get_custom_layout_options_for_post_type( $post_type );
		}

		return $options;
	}

	/**
	 * Gather compatible flexible-content layouts for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<int, array{name:string,label:string}>
	 */
	private static function get_custom_layout_options_for_post_type( $post_type ) {
		$post_type = sanitize_key( (string) $post_type );
		if ( '' === $post_type ) {
			return array();
		}

		$fields = self::get_acf_fields_for_post_type( $post_type );
		if ( empty( $fields ) ) {
			return array();
		}

		$context = array(
			'post_id'            => 0,
			'post_type'          => $post_type,
			'prefer_all_layouts' => true,
		);

		$options = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || 'flexible_content' !== ( $field['type'] ?? '' ) ) {
				continue;
			}

			if ( 'page' === $post_type && ! self::is_demo_layout_field( $field ) ) {
				continue;
			}

			$layouts = isset( $field['layouts'] ) && is_array( $field['layouts'] ) ? $field['layouts'] : array();
			$layouts = self::filter_layouts_for_demo( $field, $layouts, $context );
			if ( empty( $layouts ) ) {
				continue;
			}

			foreach ( $layouts as $layout ) {
				$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
				if ( '' === $layout_name || isset( $options[ $layout_name ] ) ) {
					continue;
				}

				$options[ $layout_name ] = array(
					'name'  => $layout_name,
					'label' => wp_strip_all_tags( (string) ( $layout['label'] ?? $layout_name ) ),
				);
			}
		}

		if ( empty( $options ) ) {
			return array();
		}

		uasort(
			$options,
			static function ( $left, $right ) {
				$left_label  = isset( $left['label'] ) ? (string) $left['label'] : '';
				$right_label = isset( $right['label'] ) ? (string) $right['label'] : '';

				return strnatcasecmp( $left_label, $right_label );
			}
		);

		return array_values( $options );
	}

	/**
	 * Resolve a lookup table of allowed custom layout names for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<string, bool>
	 */
	private static function get_custom_layout_lookup_for_post_type( $post_type ) {
		$lookup  = array();
		$options = self::get_custom_layout_options_for_post_type( $post_type );

		foreach ( $options as $option ) {
			if ( ! is_array( $option ) || empty( $option['name'] ) ) {
				continue;
			}

			$lookup[ sanitize_key( (string) $option['name'] ) ] = true;
		}

		return $lookup;
	}

	/**
	 * Render a lightweight progress indicator for long-running admin actions.
	 *
	 * @return void
	 */
	private static function render_progress_ui() {
		?>
		<style>
			.mrn-dummy-content-progress {
				display: none;
				margin: 16px 0 20px;
				padding: 16px 18px;
				border: 1px solid #c3c4c7;
				border-left: 4px solid #2271b1;
				background: #fff;
				box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
				scroll-margin-top: 110px;
			}

			.mrn-dummy-content-progress.is-active {
				display: flex;
				align-items: flex-start;
				gap: 12px;
			}

			.mrn-dummy-content-progress__spinner {
				flex: 0 0 auto;
				width: 20px;
				height: 20px;
				border: 2px solid #c3c4c7;
				border-top-color: #2271b1;
				border-radius: 50%;
				animation: mrn-dummy-content-spin 0.8s linear infinite;
				margin-top: 2px;
			}

			.mrn-dummy-content-progress__message {
				margin: 0;
				font-weight: 600;
			}

			.mrn-dummy-content-progress__detail {
				margin: 4px 0 0;
				color: #50575e;
			}

			.mrn-dummy-content-action-form.is-busy {
				opacity: 0.72;
				pointer-events: none;
			}

			@keyframes mrn-dummy-content-spin {
				from { transform: rotate(0deg); }
				to { transform: rotate(360deg); }
			}
			@media (max-width: 782px) {
				.mrn-dummy-content-progress {
					scroll-margin-top: 130px;
				}
			}
		</style>
		<div class="mrn-dummy-content-progress" data-mrn-dummy-content-progress aria-live="polite" aria-hidden="true">
			<div class="mrn-dummy-content-progress__spinner" aria-hidden="true"></div>
			<div>
				<p class="mrn-dummy-content-progress__message"><?php esc_html_e( 'Working on your request...', 'mrn-dummy-content' ); ?></p>
				<p class="mrn-dummy-content-progress__detail"><?php esc_html_e( 'Dummy Content is processing this request. Please keep this tab open until the success notice appears.', 'mrn-dummy-content' ); ?></p>
			</div>
		</div>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				var progress = document.querySelector('[data-mrn-dummy-content-progress]');
				var forms = document.querySelectorAll('.mrn-dummy-content-action-form');

				if (!progress || !forms.length) {
					return;
				}

				var messageNode = progress.querySelector('.mrn-dummy-content-progress__message');

				forms.forEach(function (form) {
					form.addEventListener('submit', function (event) {
						var message = form.getAttribute('data-progress-message');
						var submit = event && event.submitter ? event.submitter : null;

						if (!submit && form.id) {
							submit = document.querySelector('button[type="submit"][form="' + form.id + '"], input[type="submit"][form="' + form.id + '"]');
						}

						if (!submit) {
							submit = form.querySelector('button[type="submit"], input[type="submit"]');
						}

						if (message && messageNode) {
							messageNode.textContent = message;
						}

						progress.classList.add('is-active');
						progress.setAttribute('aria-hidden', 'false');
						form.classList.add('is-busy');
						progress.scrollIntoView({ behavior: 'smooth', block: 'start' });

						if (submit) {
							if (submit.tagName === 'BUTTON') {
								submit.dataset.originalLabel = submit.textContent;
								submit.textContent = submit.getAttribute('data-loading-label') || submit.textContent;
							} else {
								submit.dataset.originalLabel = submit.value;
								submit.value = submit.getAttribute('data-loading-label') || submit.value;
							}

							submit.disabled = true;
						}
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * Generate content from the current site's runtime definitions.
	 *
	 * @return void
	 */
	public static function handle_generate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to generate dummy content.', 'mrn-dummy-content' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$results = array(
			'posts_created' => 0,
			'posts_updated' => 0,
			'page_ids'      => array(),
			'index_page_id' => 0,
			'post_types'    => array(),
		);

		$target_post_types = self::get_target_post_types();

		foreach ( $target_post_types as $post_type => $object ) {
			foreach ( self::get_generation_variants( $post_type ) as $variant ) {
				$post_result = self::ensure_sample_post_variant(
					$post_type,
					$object,
					$variant['sidebar_layout'],
					$variant['sidebar_label'],
					$variant['shell_width'],
					$variant['shell_label']
				);
				if ( ! empty( $post_result['post_id'] ) ) {
					$results['post_types'][] = $post_type;
					$results['posts_created'] += (int) $post_result['created'];
					$results['posts_updated'] += (int) $post_result['updated'];
				}
			}
		}

		foreach ( self::get_shell_size_variants() as $shell_width => $shell_label ) {
			$page_result = self::ensure_all_layouts_page_variant( $shell_width, $shell_label );
			if ( ! empty( $page_result['post_id'] ) ) {
				$results['page_ids'][] = (int) $page_result['post_id'];
				$results['posts_created'] += $page_result['created'];
				$results['posts_updated'] += $page_result['updated'];
			}
		}

		$index_result              = self::ensure_generated_pages_index();
		$results['index_page_id']  = $index_result['post_id'];
		$results['posts_created'] += $index_result['created'];
		$results['posts_updated'] += $index_result['updated'];

		$message = self::build_notice_message( $results );
		self::redirect_with_notice( 'success', $message, 'generate' );
	}

	/**
	 * Create one custom post/page/CPT entry with optional selected layouts.
	 *
	 * @return void
	 */
	public static function handle_generate_custom() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to generate dummy content.', 'mrn-dummy-content' ) );
		}

		check_admin_referer( self::CUSTOM_NONCE_ACTION );

		$target_post_types = self::get_target_post_types();
		$post_type_input   = filter_input( INPUT_POST, 'custom_post_type', FILTER_UNSAFE_RAW );
		$post_type         = sanitize_key( is_string( $post_type_input ) ? wp_unslash( $post_type_input ) : '' );

		if ( '' === $post_type || ! isset( $target_post_types[ $post_type ] ) ) {
			self::redirect_with_notice( 'error', __( 'Please choose a valid content type before creating custom content.', 'mrn-dummy-content' ), 'custom' );
		}

		$title_input = filter_input( INPUT_POST, 'custom_title', FILTER_UNSAFE_RAW );
		$title       = sanitize_text_field( is_string( $title_input ) ? wp_unslash( $title_input ) : '' );
		if ( '' === $title ) {
			$post_type_label = $target_post_types[ $post_type ]->labels->singular_name;
			$title           = sprintf( 'Sample %s Custom', $post_type_label );
		}

		$slug_input = filter_input( INPUT_POST, 'custom_slug', FILTER_UNSAFE_RAW );
		$slug       = sanitize_title( is_string( $slug_input ) ? wp_unslash( $slug_input ) : '' );

		$count_input = filter_input(
			INPUT_POST,
			'custom_count',
			FILTER_VALIDATE_INT,
			array(
				'options' => array(
					'default'   => 1,
					'min_range' => 1,
				),
			)
		);
		$count = is_int( $count_input ) ? $count_input : 1;
		$count = max( 1, min( 50, $count ) );

		$sidebar_layout_input = filter_input( INPUT_POST, 'custom_sidebar_layout', FILTER_UNSAFE_RAW );
		$sidebar_layout       = sanitize_key( is_string( $sidebar_layout_input ) ? wp_unslash( $sidebar_layout_input ) : '' );
		$sidebar_variants     = self::get_sidebar_variants();
		if ( ! isset( $sidebar_variants[ $sidebar_layout ] ) ) {
			$sidebar_layout = 'none';
		}

		$shell_width_input = filter_input( INPUT_POST, 'custom_shell_width', FILTER_UNSAFE_RAW );
		$shell_width       = sanitize_key( is_string( $shell_width_input ) ? wp_unslash( $shell_width_input ) : '' );
		$shell_variants    = self::get_shell_size_variants();
		if ( ! isset( $shell_variants[ $shell_width ] ) ) {
			$shell_width = 'content';
		}

		if ( 'page' !== $post_type ) {
			$sidebar_layout = 'none';
			$shell_width    = '';
		}

		$selected_layouts_input = filter_input( INPUT_POST, 'custom_layouts', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$selected_layouts_input = is_array( $selected_layouts_input ) ? $selected_layouts_input : array();
		$selected_layouts       = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $layout_name ) {
							return sanitize_key( (string) $layout_name );
						},
						$selected_layouts_input
					)
				)
			)
		);

		if ( ! empty( $selected_layouts ) ) {
			$allowed_layouts = self::get_custom_layout_lookup_for_post_type( $post_type );
			$selected_layouts = array_values(
				array_filter(
					$selected_layouts,
					static function ( $layout_name ) use ( $allowed_layouts ) {
						return isset( $allowed_layouts[ $layout_name ] );
					}
				)
			);
		}

		$post_type_label = $target_post_types[ $post_type ]->labels->singular_name;
		$created_ids     = array();
		$failed_count    = 0;

		for ( $index = 1; $index <= $count; $index++ ) {
			$entry_title = $title;
			$entry_slug  = $slug;

			if ( $count > 1 ) {
				$entry_title = sprintf( '%s %d', $title, $index );
				if ( '' !== $entry_slug ) {
					$entry_slug .= '-' . $index;
				}
			}

			$postarr = array(
				'post_type'    => $post_type,
				'post_status'  => 'publish',
				'post_title'   => $entry_title,
				'post_content' => self::get_sample_paragraphs( $post_type_label ),
				'post_excerpt' => sprintf( 'Sample %s content generated by Dummy Content.', strtolower( $post_type_label ) ),
			);

			if ( '' !== $entry_slug ) {
				$postarr['post_name'] = $entry_slug;
			}

			$post_id = wp_insert_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				++$failed_count;
				continue;
			}

			update_post_meta( $post_id, self::GENERATED_BY_META, self::VERSION );
			update_post_meta( $post_id, self::GENERATED_AT_META, current_time( 'mysql' ) );

			self::seed_taxonomies_for_post( $post_id, $post_type );
			self::seed_acf_fields_for_post( $post_id, $post_type, false, $shell_width, $selected_layouts );
			self::apply_generated_variant_fields( $post_id, $post_type, $sidebar_layout, $shell_width, $selected_layouts );
			self::maybe_set_placeholder_thumbnail( $post_id, $post_type );

			$created_ids[] = (int) $post_id;
		}

		if ( empty( $created_ids ) ) {
			self::redirect_with_notice( 'error', __( 'WordPress could not create the custom entry. Please review your selections and try again.', 'mrn-dummy-content' ), 'custom' );
		}

		if ( 1 === count( $created_ids ) ) {
			$single_id = $created_ids[0];
			$message   = sprintf(
				/* translators: 1: post type label, 2: generated post title, 3: post ID. */
				__( 'Created custom %1$s "%2$s" (#%3$d).', 'mrn-dummy-content' ),
				strtolower( (string) $post_type_label ),
				get_the_title( $single_id ),
				$single_id
			);
		} else {
			$message = sprintf(
				/* translators: 1: created entry count, 2: post type label. */
				__( 'Created %1$d custom %2$s entries.', 'mrn-dummy-content' ),
				count( $created_ids ),
				strtolower( (string) $post_type_label )
			);
		}

		if ( ! empty( $selected_layouts ) ) {
			$message .= ' ' . sprintf(
				/* translators: %d: selected layout count. */
				__( 'Applied %d selected layouts.', 'mrn-dummy-content' ),
				count( $selected_layouts )
			);
		}

		if ( $failed_count > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of entries that failed to create. */
				__( '%d entries could not be created.', 'mrn-dummy-content' ),
				$failed_count
			);
		}

		self::redirect_with_notice( 'success', $message, 'custom' );
	}

	/**
	 * Delete only content generated by this plugin.
	 *
	 * @return void
	 */
	public static function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete dummy content.', 'mrn-dummy-content' ) );
		}

		check_admin_referer( self::DELETE_NONCE_ACTION );

		$deleted_posts       = 0;
		$deleted_attachments = 0;
		$generated_posts     = self::get_generated_posts();
		$placeholders        = self::get_placeholder_attachments();

		foreach ( $generated_posts as $post ) {
			if ( $post instanceof WP_Post && wp_delete_post( (int) $post->ID, true ) ) {
				++$deleted_posts;
			}
		}

		foreach ( $placeholders as $attachment ) {
			if ( $attachment instanceof WP_Post && wp_delete_attachment( (int) $attachment->ID, true ) ) {
				++$deleted_attachments;
			}
		}

		$message = sprintf(
			/* translators: 1: deleted posts count, 2: deleted attachments count. */
			__( 'Deleted %1$d generated posts/pages and %2$d placeholder attachments.', 'mrn-dummy-content' ),
			$deleted_posts,
			$deleted_attachments
		);

		self::redirect_with_notice( 'success', $message, 'generate' );
	}

	/**
	 * Keep generated sample pages out of the fallback page menu.
	 *
	 * @param array<int, int> $exclude_array Excluded page IDs.
	 * @return array<int, int>
	 */
	public static function exclude_generated_pages_from_page_menu( $exclude_array ) {
		$exclude_array = is_array( $exclude_array ) ? $exclude_array : array();
		$generated_ids = self::get_generated_sample_page_ids_to_hide();

		return array_values(
			array_unique(
				array_map(
					'intval',
					array_merge( $exclude_array, $generated_ids )
				)
			)
		);
	}

	/**
	 * Build the post-generation notice text.
	 *
	 * @param array<string, mixed> $results Generation summary.
	 * @return string
	 */
	private static function build_notice_message( array $results ) {
		$parts = array();

		if ( ! empty( $results['post_types'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: comma-separated post type slugs. */
				__( 'Seeded sample entries for: %s.', 'mrn-dummy-content' ),
				implode( ', ', array_map( 'sanitize_key', $results['post_types'] ) )
			);
		}

		if ( ! empty( $results['page_ids'] ) ) {
			$parts[] = sprintf(
				/* translators: %d: generated page count. */
				__( 'Built or refreshed %d all-layouts pages.', 'mrn-dummy-content' ),
				count( $results['page_ids'] )
			);
		}

		if ( ! empty( $results['index_page_id'] ) ) {
			$parts[] = sprintf(
				/* translators: %d: generated page ID. */
				__( 'Built or refreshed the generated-pages index (#%d).', 'mrn-dummy-content' ),
				(int) $results['index_page_id']
			);
		}

		if ( empty( $parts ) ) {
			return __( 'No compatible content structures were detected, so nothing new was generated.', 'mrn-dummy-content' );
		}

		return implode( ' ', $parts );
	}

	/**
	 * Parse the admin notice payload.
	 *
	 * @return array<string, string>
	 */
	private static function get_notice_from_request() {
		$notice = filter_input( INPUT_GET, self::NOTICE_QUERY_ARG, FILTER_UNSAFE_RAW );
		$notice = is_string( $notice ) ? wp_unslash( $notice ) : '';
		if ( '' === $notice ) {
			return array(
				'type'    => '',
				'message' => '',
			);
		}

		$decoded = json_decode( rawurldecode( $notice ), true );
		if ( ! is_array( $decoded ) ) {
			return array(
				'type'    => '',
				'message' => '',
			);
		}

		return array(
			'type'    => isset( $decoded['type'] ) ? sanitize_key( (string) $decoded['type'] ) : 'info',
			'message' => isset( $decoded['message'] ) ? sanitize_text_field( (string) $decoded['message'] ) : '',
		);
	}

	/**
	 * Redirect back to the tools screen with a flash notice.
	 *
	 * @param string $type Notice type.
	 * @param string $message Notice text.
	 * @param string $tab Active tab key.
	 * @return void
	 */
	private static function redirect_with_notice( $type, $message, $tab = 'generate' ) {
		$tab = in_array( $tab, array( 'generate', 'custom' ), true ) ? $tab : 'generate';

		$redirect = add_query_arg(
			array(
				'page'                 => self::MENU_SLUG,
				self::TAB_QUERY_ARG    => $tab,
				self::NOTICE_QUERY_ARG => rawurlencode(
					wp_json_encode(
						array(
							'type'    => sanitize_key( (string) $type ),
							'message' => sanitize_text_field( (string) $message ),
						)
					)
				),
			),
			admin_url( 'tools.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Return public custom post types to seed.
	 *
	 * @return array<string, WP_Post_Type>
	 */
	private static function get_target_post_types() {
		$post_types = array(
			'post' => get_post_type_object( 'post' ),
			'page' => get_post_type_object( 'page' ),
		);

		$custom_post_types = get_post_types(
			array(
				'public'   => true,
				'_builtin' => false,
			),
			'objects'
		);

		if ( is_array( $custom_post_types ) ) {
			unset( $custom_post_types['acf-field-group'], $custom_post_types['acf-field'] );
			$post_types = array_merge( $post_types, $custom_post_types );
		}

		return array_filter(
			$post_types,
			static function( $post_type_object ) {
				return $post_type_object instanceof WP_Post_Type;
			}
		);
	}

	/**
	 * Get posts/pages generated by this plugin.
	 *
	 * @return array<int, WP_Post>
	 */
	private static function get_generated_posts() {
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'meta_key'       => self::GENERATED_BY_META,
				'orderby'        => 'post_type title',
				'order'          => 'ASC',
			)
		);

		return is_array( $posts ) ? $posts : array();
	}

	/**
	 * Get placeholder attachments created by this plugin.
	 *
	 * @return array<int, WP_Post>
	 */
	private static function get_placeholder_attachments() {
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'meta_key'       => self::PLACEHOLDER_FLAG_META,
				'meta_value'     => '1',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		return is_array( $attachments ) ? $attachments : array();
	}

	/**
	 * Get the sidebar variants to generate for each supported content type.
	 *
	 * @return array<string, string>
	 */
	private static function get_sidebar_variants() {
		return array(
			'none'  => 'No Sidebar',
			'left'  => 'Left Sidebar',
			'right' => 'Right Sidebar',
		);
	}

	/**
	 * Get generation variants for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<int, array<string, string>>
	 */
	private static function get_generation_variants( $post_type ) {
		$variants = array();

		foreach ( self::get_sidebar_variants() as $sidebar_layout => $sidebar_label ) {
			if ( 'page' === $post_type ) {
				foreach ( self::get_shell_size_variants() as $shell_width => $shell_label ) {
					$variants[] = array(
						'sidebar_layout' => $sidebar_layout,
						'sidebar_label'  => $sidebar_label,
						'shell_width'    => $shell_width,
						'shell_label'    => $shell_label,
					);
				}
				continue;
			}

			$variants[] = array(
				'sidebar_layout' => $sidebar_layout,
				'sidebar_label'  => $sidebar_label,
				'shell_width'    => '',
				'shell_label'    => '',
			);
		}

		return $variants;
	}

	/**
	 * Get shell-size variants for page demos.
	 *
	 * @return array<string, string>
	 */
	private static function get_shell_size_variants() {
		return array(
			'content'    => 'Content',
			'wide'       => 'Wide',
			'full-width' => 'Full Width',
		);
	}

	/**
	 * Create or refresh a sample post for a post type.
	 *
	 * @param string       $post_type Post type slug.
	 * @param WP_Post_Type $object Registered post type object.
	 * @return array<string, int>
	 */
	private static function ensure_sample_post_variant( $post_type, $object, $sidebar_layout, $sidebar_label, $shell_width = '', $shell_label = '' ) {
		$slug     = sanitize_key( (string) $post_type );
		$variant_slug = 'sample-' . $slug . '-' . sanitize_title( $sidebar_layout );
		$title_suffix = sprintf( '(%s)', $sidebar_label );

		if ( '' !== $shell_width && '' !== $shell_label ) {
			$variant_slug .= '-' . sanitize_title( $shell_width );
			$title_suffix  = sprintf( '(%s, %s)', $sidebar_label, $shell_label );
		}

		$existing = self::get_existing_generated_post( $slug, $variant_slug );
		$is_new   = empty( $existing );

		$postarr = array(
			'post_type'    => $slug,
			'post_status'  => 'publish',
			'post_title'   => sprintf( 'Sample %s %s', $object->labels->singular_name, $title_suffix ),
			'post_name'    => $variant_slug,
			'post_content' => self::get_sample_paragraphs( $object->labels->singular_name ),
			'post_excerpt' => sprintf( 'Sample %s content generated by Dummy Content.', strtolower( $object->labels->singular_name ) ),
		);

		if ( ! $is_new ) {
			$postarr['ID'] = (int) $existing->ID;
		}

		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return array(
				'post_id' => 0,
				'created' => 0,
				'updated' => 0,
			);
		}

		update_post_meta( $post_id, self::GENERATED_BY_META, self::VERSION );
		update_post_meta( $post_id, self::GENERATED_AT_META, current_time( 'mysql' ) );

		self::seed_taxonomies_for_post( $post_id, $slug );
		self::seed_acf_fields_for_post( $post_id, $slug, false, $shell_width );
		self::apply_generated_variant_fields( $post_id, $slug, $sidebar_layout, $shell_width );
		self::maybe_set_placeholder_thumbnail( $post_id, $slug );

		return array(
			'post_id' => (int) $post_id,
			'created' => $is_new ? 1 : 0,
			'updated' => $is_new ? 0 : 1,
		);
	}

	/**
	 * Create or refresh the all-layouts page.
	 *
	 * @return array<string, int>
	 */
	private static function ensure_all_layouts_page_variant( $shell_width, $shell_label ) {
		$slug     = 'all-layouts-' . sanitize_title( $shell_width );
		$title    = sprintf( 'All Layouts (%s)', $shell_label );
		$existing = self::get_existing_generated_post( 'page', $slug );
		$is_new   = empty( $existing );

		$postarr = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => '',
		);

		if ( ! $is_new ) {
			$postarr['ID'] = (int) $existing->ID;
		}

		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return array(
				'post_id' => 0,
				'created' => 0,
				'updated' => 0,
			);
		}

		update_post_meta( $post_id, self::GENERATED_BY_META, self::VERSION );
		update_post_meta( $post_id, self::GENERATED_AT_META, current_time( 'mysql' ) );

		self::seed_acf_fields_for_post( $post_id, 'page', true, $shell_width );

		return array(
			'post_id' => (int) $post_id,
			'created' => $is_new ? 1 : 0,
			'updated' => $is_new ? 0 : 1,
		);
	}

	/**
	 * Create or refresh an index page linking to generated pages.
	 *
	 * @return array<string, int>
	 */
	private static function ensure_generated_pages_index() {
		$existing = self::get_existing_generated_post( 'page', 'dummy-content-index' );
		$is_new   = empty( $existing );
		$postarr = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Dummy Content Index',
			'post_name'    => 'dummy-content-index',
			'post_content' => '',
		);

		if ( ! $is_new ) {
			$postarr['ID'] = (int) $existing->ID;
		}

		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return array(
				'post_id' => 0,
				'created' => 0,
				'updated' => 0,
			);
		}

		update_post_meta( $post_id, self::GENERATED_BY_META, self::VERSION );
		update_post_meta( $post_id, self::GENERATED_AT_META, current_time( 'mysql' ) );
		self::seed_generated_pages_index_builder( $post_id );

		return array(
			'post_id' => (int) $post_id,
			'created' => $is_new ? 1 : 0,
			'updated' => $is_new ? 0 : 1,
		);
	}

	/**
	 * Return flexible-content fields available on pages.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_page_flexible_fields() {
		$fields = self::get_acf_fields_for_post_type( 'page' );

		return array_values(
			array_filter(
				$fields,
				static function( $field ) {
					return is_array( $field )
						&& ( $field['type'] ?? '' ) === 'flexible_content'
						&& (
							self::is_primary_page_demo_field( $field )
							|| self::is_page_hero_field( $field )
							|| self::is_page_after_content_field( $field )
						);
				}
			)
		);
	}

	/**
	 * Get the layout labels that will be added to the All Layouts page.
	 *
	 * @return array<int, string>
	 */
	private static function get_all_layout_page_labels() {
		$labels = array();
		$fields = self::get_acf_fields_for_post_type( 'page' );
		$label_context = array(
			'post_id'            => 0,
			'post_type'          => 'page',
			'prefer_all_layouts' => true,
		);

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
				continue;
			}

			$filtered_layouts = self::filter_layouts_for_demo( $field, $field['layouts'], $label_context );
			if ( empty( $filtered_layouts ) ) {
				continue;
			}

			if ( self::is_page_hero_field( $field ) ) {
				$hero_layout = self::find_layout_by_preferred_names( $filtered_layouts, array( 'basic', 'hero' ) );
				if ( is_array( $hero_layout ) ) {
					$labels[] = wp_strip_all_tags( (string) ( $hero_layout['label'] ?? 'Basic' ) );
				}

				continue;
			}

			if ( ! self::is_primary_page_demo_field( $field ) ) {
				continue;
			}

			foreach ( $filtered_layouts as $layout ) {
				$labels[] = wp_strip_all_tags( (string) ( $layout['label'] ?? $layout['name'] ?? '' ) );
			}
		}

		return array_values( array_unique( array_filter( $labels ) ) );
	}

	/**
	 * Find the first matching layout definition from preferred layout names.
	 *
	 * @param array<int, array<string, mixed>> $layouts Layout definitions.
	 * @param array<int, string>               $preferred_names Preferred layout names in priority order.
	 * @return array<string, mixed>|null
	 */
	private static function find_layout_by_preferred_names( array $layouts, array $preferred_names ) {
		$preferred_names = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $name ) {
							return sanitize_key( (string) $name );
						},
						$preferred_names
					)
				)
			)
		);

		if ( empty( $preferred_names ) ) {
			return null;
		}

		foreach ( $preferred_names as $preferred_name ) {
			foreach ( $layouts as $layout ) {
				if ( ! is_array( $layout ) ) {
					continue;
				}

				$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
				if ( $preferred_name === $layout_name ) {
					return $layout;
				}
			}
		}

		return null;
	}

	/**
	 * Seed taxonomies associated with a post type.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post type slug.
	 * @return void
	 */
	private static function seed_taxonomies_for_post( $post_id, $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		if ( ! is_array( $taxonomies ) ) {
			return;
		}

		foreach ( $taxonomies as $taxonomy => $taxonomy_object ) {
			if ( ! $taxonomy_object instanceof WP_Taxonomy ) {
				continue;
			}

			$term = self::get_or_create_sample_term( $taxonomy_object );
			if ( $term instanceof WP_Term ) {
				wp_set_object_terms( $post_id, array( (int) $term->term_id ), $taxonomy, false );
			}
		}
	}

	/**
	 * Seed ACF fields for a given post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post type slug.
	 * @param bool                 $prefer_all_layouts Whether to expand all layouts for flexible-content page fields.
	 * @param string               $preferred_section_width Preferred section width choice.
	 * @param array<int, string>   $selected_layout_names Optional selected layout names.
	 * @return void
	 */
	private static function seed_acf_fields_for_post( $post_id, $post_type, $prefer_all_layouts = false, $preferred_section_width = '', array $selected_layout_names = array() ) {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) || ! function_exists( 'update_field' ) ) {
			return;
		}

		$fields = self::get_acf_fields_for_post_type( $post_type );
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			if ( 'page' === $post_type && ! self::should_seed_page_field( $field, $prefer_all_layouts ) ) {
				continue;
			}

			$context = array(
				'post_id'            => (int) $post_id,
				'post_type'          => sanitize_key( $post_type ),
				'prefer_all_layouts' => (bool) $prefer_all_layouts,
				'depth'              => 0,
				'preferred_section_width' => (string) $preferred_section_width,
				'selected_layout_names'   => $selected_layout_names,
			);

			$field_context = $context;
			if ( self::should_expand_sample_variant_layouts( $post_type ) && self::is_demo_layout_field( $field ) ) {
				$field_context['prefer_all_layouts'] = true;
			}

			if ( ! empty( $field_context['prefer_all_layouts'] ) && self::is_page_hero_field( $field ) ) {
				$value = self::generate_basic_hero_value( $field, $field_context );
			} else {
				$value = self::generate_field_value( $field, $field_context );
			}

			if ( null === $value ) {
				continue;
			}

			update_field( $field['key'], $value, $post_id );
		}
	}

	/**
	 * Apply explicit sidebar and after-content demo fields for generated variants.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post type slug.
	 * @param string $sidebar_layout Sidebar variant.
	 * @param array<int, string> $selected_layout_names Optional selected layout names.
	 * @return void
	 */
	private static function apply_generated_variant_fields( $post_id, $post_type, $sidebar_layout, $preferred_section_width = '', array $selected_layout_names = array() ) {
		if ( ! function_exists( 'update_field' ) ) {
			return;
		}

		$fields = self::get_acf_fields_for_post_type( $post_type );
		if ( empty( $fields ) ) {
			return;
		}

		$context = array(
			'post_id'            => (int) $post_id,
			'post_type'          => sanitize_key( $post_type ),
			'prefer_all_layouts' => self::should_expand_sample_variant_layouts( $post_type ),
			'depth'              => 0,
			'preferred_section_width' => (string) $preferred_section_width,
			'selected_layout_names'   => $selected_layout_names,
		);

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['key'] ) ) {
				continue;
			}

			$name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
			$type = isset( $field['type'] ) ? (string) $field['type'] : '';

			if ( 'sidebar_layout' === $name ) {
				update_field( $field['key'], $sidebar_layout, $post_id );
				continue;
			}

			if ( 'page_after_content_rows' === $name && 'flexible_content' === $type ) {
				update_field( $field['key'], self::generate_flexible_content_value( $field, $context ), $post_id );
				continue;
			}

			if ( 'page_sidebar_rows' === $name && 'flexible_content' === $type ) {
				$value = 'none' === $sidebar_layout ? array() : self::generate_flexible_content_value( $field, $context );
				update_field( $field['key'], $value, $post_id );
			}
		}
	}

	/**
	 * Get ACF fields attached to a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_acf_fields_for_post_type( $post_type ) {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return array();
		}

		$post_type = sanitize_key( (string) $post_type );
		if ( isset( self::$acf_fields_cache[ $post_type ] ) ) {
			return self::$acf_fields_cache[ $post_type ];
		}

		$field_groups = acf_get_field_groups(
			array(
				'post_type' => $post_type,
			)
		);

		if ( ! is_array( $field_groups ) ) {
			return array();
		}

		$fields = array();

		foreach ( $field_groups as $field_group ) {
			$group_key = isset( $field_group['key'] ) ? (string) $field_group['key'] : '';
			if ( '' === $group_key ) {
				continue;
			}

			$group_fields = acf_get_fields( $group_key );
			if ( ! is_array( $group_fields ) ) {
				continue;
			}

			foreach ( $group_fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$type = isset( $field['type'] ) ? (string) $field['type'] : '';
				if ( in_array( $type, array( 'tab', 'accordion', 'message' ), true ) ) {
					continue;
				}

				$fields[] = $field;
			}
		}

		self::$acf_fields_cache[ $post_type ] = $fields;

		return $fields;
	}

	/**
	 * Create sample data for a field based on its runtime ACF definition.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param array<string, mixed> $context Generation context.
	 * @return mixed
	 */
	private static function generate_field_value( array $field, array $context ) {
		$type  = isset( $field['type'] ) ? (string) $field['type'] : '';
		$name  = isset( $field['name'] ) ? (string) $field['name'] : '';
		$label = isset( $field['label'] ) ? (string) $field['label'] : ucfirst( str_replace( '_', ' ', $name ) );
		$depth = isset( $context['depth'] ) ? (int) $context['depth'] : 0;

		if ( $depth > 3 ) {
			return null;
		}

			switch ( $type ) {
				case 'text':
				case 'textarea':
					return self::sample_text_for_field( $name, $label, $type, $context );

			case 'wysiwyg':
				return self::get_sample_paragraphs( $label );

			case 'email':
				return 'dummy@example.com';

			case 'url':
			case 'page_link':
				return home_url( '/dummy-content/' );

			case 'number':
			case 'range':
				if ( false !== strpos( strtolower( $name ), 'posts_per_page' ) ) {
					return 3;
				}
				if ( false !== strpos( strtolower( $name ), 'columns' ) ) {
					return 3;
				}
				if ( isset( $field['default_value'] ) && '' !== (string) $field['default_value'] ) {
					return (string) $field['default_value'];
				}
				if ( isset( $field['min'] ) && '' !== (string) $field['min'] ) {
					return (int) $field['min'] + 1;
				}
				return 3;

			case 'true_false':
				return self::get_safe_true_false_value( $field ) ? 1 : 0;

				case 'button_group':
				case 'select':
				case 'radio':
					return self::get_safe_choice_value( $field, $context );

			case 'checkbox':
				$choice = self::get_first_choice_value( $field );
				return null !== $choice ? array( $choice ) : array();

			case 'link':
				return array(
					'url'    => home_url( '/dummy-content/' . sanitize_title( $label ) . '/' ),
					'title'  => sprintf( 'Explore %s', $label ),
					'target' => '',
				);

			case 'image':
				return self::get_placeholder_attachment_id( $label );

			case 'file':
				return self::get_safe_file_value( $field );

			case 'gallery':
				return array(
					self::get_placeholder_attachment_id( $label . ' 1' ),
					self::get_placeholder_attachment_id( $label . ' 2' ),
				);

			case 'oembed':
				return 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

			case 'color_picker':
				return '#1f5eff';

			case 'date_picker':
				return current_time( 'Ymd' );

			case 'date_time_picker':
				return current_time( 'Y-m-d H:i:s' );

			case 'taxonomy':
				return self::get_taxonomy_field_value( $field );

			case 'post_object':
			case 'relationship':
				return self::get_related_post_field_value( $field );

			case 'group':
				return self::generate_group_value( $field, $context );

			case 'repeater':
				return self::generate_repeater_value( $field, $context );

			case 'flexible_content':
				return self::generate_flexible_content_value( $field, $context );

			default:
				return null;
		}
	}

	/**
	 * Build sample text suited to a field name.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @param string $type Field type.
	 * @return string
	 */
	private static function sample_text_for_field( $name, $label, $type, array $context = array() ) {
		$key                     = strtolower( $name . ' ' . $label );
		$field_name              = sanitize_key( (string) $name );
		$depth                   = isset( $context['depth'] ) ? (int) $context['depth'] : 0;
		$layout_label            = isset( $context['layout_label'] ) ? trim( (string) $context['layout_label'] ) : '';
		$layout_name             = isset( $context['layout_name'] ) ? trim( (string) $context['layout_name'] ) : '';
		$layout_sub_field_names  = isset( $context['layout_sub_field_names'] ) && is_array( $context['layout_sub_field_names'] )
			? array_values( array_unique( array_filter( array_map( 'sanitize_key', $context['layout_sub_field_names'] ) ) ) )
			: array();
		$layout_sub_field_labels = isset( $context['layout_sub_field_labels'] ) && is_array( $context['layout_sub_field_labels'] )
			? array_values( array_unique( array_filter( array_map( 'wp_strip_all_tags', $context['layout_sub_field_labels'] ) ) ) )
			: array();

		$has_layout_name_context = ( '' !== $layout_label || '' !== $layout_name );
		$layout_meta_field_names = array( 'label', 'title', 'heading', 'subheading', 'layout_name', 'name' );
		$has_layout_meta_fields  = ! empty( array_intersect( $layout_sub_field_names, $layout_meta_field_names ) );
		$descriptor_target_fields = array( 'layout_name', 'name', 'row_name', 'label', 'title', 'heading', 'subheading' );
		$is_admin_row_name_field = false !== strpos( $key, 'admin use only' ) || false !== strpos( $key, 'row name' );

		if (
			$has_layout_name_context
			&& $has_layout_meta_fields
			&& (
				in_array( $field_name, $descriptor_target_fields, true )
				|| $is_admin_row_name_field
				|| false !== strpos( $key, 'layout name' )
			)
		) {
			return self::build_layout_descriptor_text( $layout_label, $layout_name, $layout_sub_field_labels );
		}

		if ( false !== strpos( $key, 'subheading' ) ) {
			return sprintf( '%s sample subheading to show the layout hierarchy.', $label );
		}

		if ( false !== strpos( $key, 'heading' ) ) {
			if ( '' !== $layout_label ) {
				return $layout_label;
			}

			if ( '' !== $layout_name ) {
				return ucwords( str_replace( array( '_', '-' ), ' ', $layout_name ) );
			}

			return sprintf( '%s Sample Heading', $label );
		}

		if ( false !== strpos( $key, 'label' ) ) {
			if ( '' !== $layout_label ) {
				return preg_replace( '/\s*-\s*.*/', '', $layout_label );
			}

			return 'Section Label';
		}

		if ( false !== strpos( $key, 'name' ) ) {
			return 'Jordan Example';
		}

		if ( false !== strpos( $key, 'company' ) ) {
			return 'Example Company';
		}

		if ( false !== strpos( $key, 'position' ) ) {
			return 'Marketing Director';
		}

		if ( false !== strpos( $key, 'stat' ) || false !== strpos( $key, 'value' ) ) {
			return '42';
		}

		if ( false !== strpos( $key, 'excerpt' ) ) {
			return 'Sample summary copy generated to preview this field.';
		}

		if ( 'textarea' === $type ) {
			return sprintf( 'Sample %s content generated by Dummy Content.', strtolower( $label ) );
		}

		return sprintf( 'Sample %s', $label );
	}

	/**
	 * Build a simple layout display name for layout identity fields.
	 *
	 * Example: "Grid - label|heading|subheading|repeater" => "Grid"
	 *
	 * @param string             $layout_label Layout label.
	 * @param string             $layout_name  Layout key/name.
	 * @param array<int, string> $sub_labels   Layout subfield labels.
	 * @return string
	 */
	private static function build_layout_descriptor_text( $layout_label, $layout_name, array $sub_labels = array() ) {
		unset( $sub_labels );

		$layout_label = trim( (string) $layout_label );
		$layout_name  = trim( (string) $layout_name );

		$display = '' !== $layout_label
			? $layout_label
			: ( '' !== $layout_name ? ucwords( str_replace( array( '_', '-' ), ' ', $layout_name ) ) : '' );

		if ( '' === $display ) {
			return 'Layout';
		}

		$display = wp_strip_all_tags( $display );
		$dash_position = strpos( $display, ' - ' );
		if ( false !== $dash_position ) {
			$display = substr( $display, 0, $dash_position );
		}

		$pipe_position = strpos( $display, '|' );
		if ( false !== $pipe_position ) {
			$display = substr( $display, 0, $pipe_position );
		}

		$display = trim( preg_replace( '/\s+/', ' ', (string) $display ) );

		return '' !== $display ? $display : 'Layout';
	}

	/**
	 * Generate a nested group value.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param array<string, mixed> $context Generation context.
	 * @return array<string, mixed>
	 */
	private static function generate_group_value( array $field, array $context ) {
		$value      = array();
		$sub_fields = isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ? $field['sub_fields'] : array();

		foreach ( $sub_fields as $sub_field ) {
			if ( ! is_array( $sub_field ) || empty( $sub_field['name'] ) ) {
				continue;
			}

			$sub_value = self::generate_field_value(
				$sub_field,
				array_merge(
					$context,
					array(
						'depth' => (int) $context['depth'] + 1,
					)
				)
			);

			if ( null !== $sub_value ) {
				$value[ $sub_field['name'] ] = $sub_value;
			}
		}

		return $value;
	}

	/**
	 * Generate repeater rows.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param array<string, mixed> $context Generation context.
	 * @return array<int, array<string, mixed>>
	 */
	private static function generate_repeater_value( array $field, array $context ) {
		$rows       = array();
		$sub_fields = isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ? $field['sub_fields'] : array();
		$row_count  = 2;

		for ( $i = 0; $i < $row_count; $i++ ) {
			$row = array();

			foreach ( $sub_fields as $sub_field ) {
				if ( ! is_array( $sub_field ) || empty( $sub_field['name'] ) ) {
					continue;
				}

					$value = self::generate_field_value(
						$sub_field,
						array_merge(
							$context,
							array(
								'depth' => (int) $context['depth'] + 1,
							)
						)
					);

				if ( null !== $value ) {
					$row[ $sub_field['name'] ] = $value;
				}
			}

			if ( ! empty( $row ) ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * Generate flexible-content rows.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param array<string, mixed> $context Generation context.
	 * @return array<int, array<string, mixed>>
	 */
	private static function generate_flexible_content_value( array $field, array $context ) {
		$layouts = isset( $field['layouts'] ) && is_array( $field['layouts'] ) ? $field['layouts'] : array();
		if ( empty( $layouts ) ) {
			return array();
		}

		$layouts = self::filter_layouts_for_demo( $field, $layouts, $context );
		if ( empty( $layouts ) ) {
			return array();
		}

		$selected_layout_names = self::get_selected_layout_names_from_context( $context );
		if ( ! empty( $selected_layout_names ) ) {
			$selected_lookup = array_fill_keys( $selected_layout_names, true );

			$layouts = array_values(
				array_filter(
					$layouts,
					static function ( $layout ) use ( $selected_lookup ) {
						if ( ! is_array( $layout ) || empty( $layout['name'] ) ) {
							return false;
						}

						$layout_name = sanitize_key( (string) $layout['name'] );
						return '' !== $layout_name && isset( $selected_lookup[ $layout_name ] );
					}
				)
			);

			if ( empty( $layouts ) ) {
				return array();
			}
		}

		$rows        = array();
		$all_layouts = ! empty( $context['prefer_all_layouts'] ) || ! empty( $selected_layout_names );
		$limit       = $all_layouts ? count( $layouts ) : min( 2, count( $layouts ) );
		$counter     = 0;
		$nav_items   = array();
		$is_all_layouts_page_content = self::is_all_layouts_page_content_context( $field, $context );

		foreach ( $layouts as $layout ) {
			if ( ! is_array( $layout ) || empty( $layout['name'] ) ) {
				continue;
			}

			if ( ! self::is_safe_layout_for_demo( $layout ) ) {
				continue;
			}

			if ( $counter >= $limit ) {
				break;
			}

			$row = array(
				'acf_fc_layout' => $layout['name'],
			);
				$row_anchor = '';

				$sub_fields = isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ? $layout['sub_fields'] : array();
				$layout_sub_field_names  = array();
				$layout_sub_field_labels = array();

				foreach ( $sub_fields as $layout_sub_field ) {
					if ( ! is_array( $layout_sub_field ) || empty( $layout_sub_field['name'] ) ) {
						continue;
					}

					$layout_sub_field_names[] = sanitize_key( (string) $layout_sub_field['name'] );
					$layout_sub_field_labels[] = wp_strip_all_tags(
						(string) ( $layout_sub_field['label'] ?? $layout_sub_field['name'] )
					);
				}

				$layout_sub_field_names  = array_values( array_unique( array_filter( $layout_sub_field_names ) ) );
				$layout_sub_field_labels = array_values( array_unique( array_filter( $layout_sub_field_labels ) ) );

				foreach ( $sub_fields as $sub_field ) {
					if ( ! is_array( $sub_field ) || empty( $sub_field['name'] ) ) {
						continue;
					}

				if ( $is_all_layouts_page_content && 'anchor' === (string) $sub_field['name'] ) {
					$row_anchor = self::generate_layout_anchor_id( $layout, $counter + 1 );
					$row[ $sub_field['name'] ] = $row_anchor;
					continue;
				}

				$value = self::generate_field_value(
					$sub_field,
						array_merge(
							$context,
							array(
								'depth'                   => (int) $context['depth'] + 1,
								'layout_name'             => isset( $layout['name'] ) ? (string) $layout['name'] : '',
								'layout_label'            => isset( $layout['label'] ) ? wp_strip_all_tags( (string) $layout['label'] ) : '',
								'layout_sub_field_names'  => $layout_sub_field_names,
								'layout_sub_field_labels' => $layout_sub_field_labels,
							)
						)
					);

				if ( null !== $value ) {
					$row[ $sub_field['name'] ] = $value;
				}
			}

			if ( $is_all_layouts_page_content ) {
				$nav_items[] = array(
					'label'  => isset( $layout['label'] ) ? wp_strip_all_tags( (string) $layout['label'] ) : ucwords( str_replace( array( '_', '-' ), ' ', (string) $layout['name'] ) ),
					'anchor' => $row_anchor,
				);
			}

			$rows[] = $row;
			++$counter;
		}

		if ( $is_all_layouts_page_content ) {
			$nav_row = self::build_all_layouts_navigation_row( $layouts, $nav_items, $context );
			if ( ! empty( $nav_row ) ) {
				array_unshift( $rows, $nav_row );
			}
		}

		return $rows;
	}

	/**
	 * Get selected layout names from generation context.
	 *
	 * @param array<string, mixed> $context Generation context.
	 * @return array<int, string>
	 */
	private static function get_selected_layout_names_from_context( array $context ) {
		$selected_layout_names = isset( $context['selected_layout_names'] ) && is_array( $context['selected_layout_names'] )
			? $context['selected_layout_names']
			: array();

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $layout_name ) {
							return sanitize_key( (string) $layout_name );
						},
						$selected_layout_names
					)
				)
			)
		);
	}

	/**
	 * Determine whether the current flexible-content generation is for the All Layouts page content field.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param array<string, mixed> $context Generation context.
	 * @return bool
	 */
	private static function is_all_layouts_page_content_context( array $field, array $context ) {
		return ! empty( $context['prefer_all_layouts'] )
			&& 'page' === ( $context['post_type'] ?? '' )
			&& self::is_primary_page_demo_field( $field );
	}

	/**
	 * Generate a stable anchor ID for a layout row.
	 *
	 * @param array<string, mixed> $layout Layout definition.
	 * @param int                  $position 1-based layout position.
	 * @return string
	 */
	private static function generate_layout_anchor_id( array $layout, $position ) {
		$base = isset( $layout['name'] ) ? sanitize_title( (string) $layout['name'] ) : '';
		if ( '' === $base ) {
			$base = 'layout-' . absint( $position );
		}

		return sprintf( 'layout-%s-%d', $base, absint( $position ) );
	}

	/**
	 * Build a nav row that links to each generated All Layouts section anchor.
	 *
	 * @param array<int, array<string, mixed>> $layouts Layout definitions.
	 * @param array<int, array<string, string>> $nav_items Nav items with labels and anchors.
	 * @param array<string, mixed>              $context Generation context.
	 * @return array<string, mixed>
	 */
	private static function build_all_layouts_navigation_row( array $layouts, array $nav_items, array $context ) {
		if ( empty( $nav_items ) ) {
			return array();
		}

		$body_layout = null;
		foreach ( $layouts as $layout ) {
			if ( is_array( $layout ) && ( $layout['name'] ?? '' ) === 'body_text' ) {
				$body_layout = $layout;
				break;
			}
		}

		if ( ! is_array( $body_layout ) ) {
			return array();
		}

		$links = array();
		foreach ( $nav_items as $nav_item ) {
			if ( empty( $nav_item['anchor'] ) || empty( $nav_item['label'] ) ) {
				continue;
			}

			$links[] = sprintf(
				'<li><a href="#%1$s">%2$s</a></li>',
				esc_attr( $nav_item['anchor'] ),
				esc_html( $nav_item['label'] )
			);
		}

		if ( empty( $links ) ) {
			return array();
		}

		$row = array(
			'acf_fc_layout' => 'body_text',
		);

		$sub_fields = isset( $body_layout['sub_fields'] ) && is_array( $body_layout['sub_fields'] ) ? $body_layout['sub_fields'] : array();
		foreach ( $sub_fields as $sub_field ) {
			if ( ! is_array( $sub_field ) || empty( $sub_field['name'] ) ) {
				continue;
			}

			$name = (string) $sub_field['name'];

			if ( 'heading' === $name ) {
				$row[ $name ] = 'Jump to a Layout';
				continue;
			}

			if ( 'subheading' === $name ) {
				$row[ $name ] = 'Quick anchor links for each generated layout section on this page.';
				continue;
			}

			if ( 'body_text' === $name ) {
				$row[ $name ] = '<ul>' . implode( '', $links ) . '</ul>';
				continue;
			}

			if ( 'anchor' === $name ) {
				$row[ $name ] = 'all-layouts-menu';
				continue;
			}

			$value = self::generate_field_value(
				$sub_field,
				array_merge(
					$context,
					array(
						'depth'        => (int) ( $context['depth'] ?? 0 ) + 1,
						'layout_name'  => 'body_text',
						'layout_label' => 'Jump to a Layout',
					)
				)
			);

			if ( null !== $value ) {
				$row[ $name ] = $value;
			}
		}

		return $row;
	}

	/**
	 * Get the first available choice value.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return string|null
	 */
	private static function get_first_choice_value( array $field ) {
		if ( isset( $field['default_value'] ) && '' !== (string) $field['default_value'] ) {
			return (string) $field['default_value'];
		}

		$choices = isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : array();
		if ( empty( $choices ) ) {
			return null;
		}

		$keys = array_keys( $choices );
		return isset( $keys[0] ) ? (string) $keys[0] : null;
	}

	/**
	 * Determine whether a page field should be seeded.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param bool                 $prefer_all_layouts Whether the page is the all-layouts demo.
	 * @return bool
	 */
	private static function should_seed_page_field( array $field, $prefer_all_layouts ) {
		$name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$type = isset( $field['type'] ) ? (string) $field['type'] : '';

		if ( '' === $name ) {
			return false;
		}

		if ( in_array( $name, array( 'page_sidebar_rows', 'sidebar_layout', 'page_after_content_rows' ), true ) ) {
			return false;
		}

		if ( self::is_page_hero_field( $field ) ) {
			return (bool) $prefer_all_layouts;
		}

		if ( $prefer_all_layouts && 'flexible_content' === $type ) {
			return self::is_primary_page_demo_field( $field );
		}

		if ( false !== strpos( $name, 'sidebar' ) || false !== strpos( $name, 'after_content' ) ) {
			return false;
		}

		if ( false !== strpos( $name, 'background_video' ) || false !== strpos( $name, 'video_upload' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Determine whether a flexible-content page field is the primary demo target.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return bool
	 */
	private static function is_primary_page_demo_field( array $field ) {
		$name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';

		if ( '' === $name ) {
			return false;
		}

		if ( in_array( $name, array( 'page_content_rows', 'content_rows', 'page_builder_rows', 'layout_rows', 'builder_rows' ), true ) ) {
			return true;
		}

		if ( false !== strpos( $name, 'sidebar' ) || false !== strpos( $name, 'after_content' ) || false !== strpos( $name, 'hero' ) ) {
			return false;
		}

		return false !== strpos( $name, 'content' ) || false !== strpos( $name, 'layout' ) || false !== strpos( $name, 'builder' );
	}

	/**
	 * Determine whether a field is the page hero flexible-content field.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return bool
	 */
	private static function is_page_hero_field( array $field ) {
		$name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$type = isset( $field['type'] ) ? (string) $field['type'] : '';

		if ( 'flexible_content' !== $type ) {
			return false;
		}

		return in_array( $name, array( 'page_hero_rows', 'hero_rows' ), true );
	}

	/**
	 * Determine whether a field is the page after-content flexible-content field.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return bool
	 */
	private static function is_page_after_content_field( array $field ) {
		$name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		$type = isset( $field['type'] ) ? (string) $field['type'] : '';

		if ( 'flexible_content' !== $type ) {
			return false;
		}

		return in_array( $name, array( 'page_after_content_rows', 'after_content_rows' ), true );
	}

	/**
	 * Determine whether a field is one of the demo layout buckets.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return bool
	 */
	private static function is_demo_layout_field( array $field ) {
		return self::is_primary_page_demo_field( $field )
			|| self::is_page_after_content_field( $field )
			|| self::is_page_hero_field( $field );
	}

	/**
	 * Determine whether generated sample variants should expand all compatible layouts.
	 *
	 * Pages keep lighter sample variants because dedicated all-layouts pages already exist.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	private static function should_expand_sample_variant_layouts( $post_type ) {
		return false;
	}

	/**
	 * Build the generated-pages index markup.
	 *
	 * @return string
	 */
	private static function get_generated_pages_index_markup() {
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => self::GENERATED_BY_META,
				'orderby'        => 'post_type title',
				'order'          => 'ASC',
			)
		);

		$groups = array();

		if ( is_array( $posts ) ) {
			foreach ( $posts as $post ) {
				if ( ! $post instanceof WP_Post ) {
					continue;
				}

				if ( 'dummy-content-index' === $post->post_name ) {
					continue;
				}

				$post_type_object = get_post_type_object( $post->post_type );
				$group_label      = $post_type_object instanceof WP_Post_Type
					? (string) $post_type_object->labels->name
					: ucwords( str_replace( array( '_', '-' ), ' ', $post->post_type ) );

				if ( ! isset( $groups[ $group_label ] ) ) {
					$groups[ $group_label ] = array();
				}

				$groups[ $group_label ][] = sprintf(
					'<li><a href="%1$s">%2$s</a></li>',
					esc_url( get_permalink( $post ) ),
					esc_html( get_the_title( $post ) )
				);
			}
		}

		if ( empty( $groups ) ) {
			return '<p>No generated content is available yet.</p>';
		}

		ksort( $groups, SORT_NATURAL | SORT_FLAG_CASE );

		$sections = array( '<p>Links to all generated demo content, organized by content type.</p>' );

		foreach ( $groups as $group_label => $items ) {
			$sections[] = sprintf(
				'<h3>%1$s</h3><ul>%2$s</ul>',
				esc_html( $group_label ),
				implode( '', $items )
			);
		}

		return implode( '', $sections );
	}

	/**
	 * Populate the generated index page using the page builder.
	 *
	 * @param int $post_id Page ID.
	 * @return void
	 */
	private static function seed_generated_pages_index_builder( $post_id ) {
		if ( ! function_exists( 'update_field' ) ) {
			return;
		}

		$fields = self::get_acf_fields_for_post_type( 'page' );
		$markup = self::get_generated_pages_index_markup();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['key'] ) ) {
				continue;
			}

			if ( ! self::is_primary_page_demo_field( $field ) ) {
				continue;
			}

			if ( 'flexible_content' !== ( $field['type'] ?? '' ) || empty( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
				continue;
			}

			foreach ( $field['layouts'] as $layout ) {
				if ( ! is_array( $layout ) || ( $layout['name'] ?? '' ) !== 'body_text' ) {
					continue;
				}

				$row = array(
					'acf_fc_layout' => 'body_text',
				);

				$sub_fields = isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ? $layout['sub_fields'] : array();
				foreach ( $sub_fields as $sub_field ) {
					if ( ! is_array( $sub_field ) || empty( $sub_field['name'] ) ) {
						continue;
					}

					$name = (string) $sub_field['name'];

					if ( 'heading' === $name ) {
						$row[ $name ] = 'Generated Pages';
						continue;
					}

					if ( 'subheading' === $name ) {
						$row[ $name ] = 'Quick links to all generated demo content grouped by content type.';
						continue;
					}

					if ( 'body_text' === $name ) {
						$row[ $name ] = $markup;
						continue;
					}

					$value = self::generate_field_value(
						$sub_field,
						array(
							'post_id'            => (int) $post_id,
							'post_type'          => 'page',
							'prefer_all_layouts' => false,
							'depth'              => 1,
							'layout_name'        => 'body_text',
							'layout_label'       => 'Generated Pages',
						)
					);

					if ( null !== $value ) {
						$row[ $name ] = $value;
					}
				}

				update_field( $field['key'], array( $row ), $post_id );
				return;
			}
		}
	}

	/**
	 * Get generated sample page IDs that should stay out of the main menu.
	 *
	 * @return array<int, int>
	 */
	private static function get_generated_sample_page_ids_to_hide() {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'meta_key'       => self::GENERATED_BY_META,
			)
		);

		$ids = array();

			if ( is_array( $pages ) ) {
				foreach ( $pages as $page ) {
					if ( ! $page instanceof WP_Post ) {
						continue;
					}

					$post_name = sanitize_title( (string) $page->post_name );
					if ( 'dummy-content-index' === $post_name ) {
						continue;
					}

					$is_sample_page = 0 === strpos( $post_name, 'sample-page-' );
					$is_all_layouts = 0 === strpos( $post_name, 'all-layouts-' );

					/*
					 * Keep intentionally generated stack demo pages out of fallback
					 * page menus, but do not hide custom-created pages.
					 */
					if ( ! $is_sample_page && ! $is_all_layouts ) {
						continue;
					}

					$ids[] = (int) $page->ID;
				}
			}

		return $ids;
	}

	/**
	 * Generate a single basic hero row when a hero field exists.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param array<string, mixed> $context Generation context.
	 * @return array<int, array<string, mixed>>
	 */
	private static function generate_basic_hero_value( array $field, array $context ) {
		$layouts = isset( $field['layouts'] ) && is_array( $field['layouts'] ) ? $field['layouts'] : array();
		if ( empty( $layouts ) ) {
			return array();
		}

		$layout = self::find_layout_by_preferred_names( $layouts, array( 'basic', 'hero' ) );
		if ( ! is_array( $layout ) ) {
			return array();
		}

		$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
		if ( '' === $layout_name ) {
			return array();
		}

		$layout_label = isset( $layout['label'] ) ? wp_strip_all_tags( (string) $layout['label'] ) : ucwords( str_replace( array( '_', '-' ), ' ', $layout_name ) );
		$row          = array(
			'acf_fc_layout' => $layout_name,
		);

		$sub_fields = isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ? $layout['sub_fields'] : array();
		foreach ( $sub_fields as $sub_field ) {
			if ( ! is_array( $sub_field ) || empty( $sub_field['name'] ) ) {
				continue;
			}

			$value = self::generate_field_value(
				$sub_field,
				array_merge(
					$context,
					array(
						'depth'        => (int) $context['depth'] + 1,
						'layout_name'  => $layout_name,
						'layout_label' => $layout_label,
					)
				)
			);

			if ( null !== $value ) {
				$row[ $sub_field['name'] ] = $value;
			}
		}

		return array( $row );
	}

	/**
	 * Determine a safer default for boolean config fields.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return bool
	 */
	private static function get_safe_true_false_value( array $field ) {
		$name = isset( $field['name'] ) ? strtolower( (string) $field['name'] ) : '';

		if ( isset( $field['default_value'] ) && '' !== (string) $field['default_value'] ) {
			return ! empty( $field['default_value'] );
		}

		foreach ( array( 'autoplay', 'background_video', 'video', 'bottom_accent', 'show_arrows', 'show_pagination', 'pause_on_hover', 'delay_start', 'sticky', 'overlay', 'featured' ) as $needle ) {
			if ( false !== strpos( $name, $needle ) ) {
				return false;
			}
		}

		return false !== strpos( $name, 'show_' );
	}

	/**
	 * Determine a safer default choice value.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return string|null
	 */
	private static function get_safe_choice_value( array $field, array $context = array() ) {
		$name    = isset( $field['name'] ) ? strtolower( (string) $field['name'] ) : '';
		$choices = isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : array();

		if ( empty( $choices ) ) {
			return self::get_first_choice_value( $field );
		}

		$is_sub_content_width = false !== strpos( $name, 'sub_content_width' );
		$is_section_width     = false !== strpos( $name, 'section_width' ) && ! $is_sub_content_width;

		if ( $is_section_width && ! empty( $context['preferred_section_width'] ) ) {
			$preferred = strtolower( (string) $context['preferred_section_width'] );
			foreach ( array_keys( $choices ) as $choice_key ) {
				if ( strtolower( (string) $choice_key ) === $preferred ) {
					return (string) $choice_key;
				}
			}
		}

		$preferred_map = array(
			'sub_content_width' => array( 'content', 'wide', 'full_width', 'full-width' ),
			'section_width'     => array( 'wide', 'content', 'full_width', 'full-width' ),
			'column_ratio'     => array( '50-50', '50_50' ),
			'image_position'   => array( 'right', 'left' ),
			'image_alignment'  => array( 'center', 'left', 'right' ),
			'image_size'       => array( 'cover', 'medium', 'large' ),
			'display_mode'     => array( 'static', 'grid', 'default', 'list' ),
			'list_style'       => array( 'list', 'grid', 'default' ),
			'orderby'          => array( 'menu_order', 'date', 'title' ),
			'order'            => array( 'ASC', 'DESC' ),
			'background_color' => array( '', 'white', 'light', 'none' ),
		);

		foreach ( $preferred_map as $needle => $preferred_values ) {
			if ( false === strpos( $name, $needle ) ) {
				continue;
			}

			foreach ( $preferred_values as $preferred_value ) {
				foreach ( array_keys( $choices ) as $choice_key ) {
					if ( strtolower( (string) $choice_key ) === strtolower( $preferred_value ) ) {
						return (string) $choice_key;
					}
				}
			}
		}

		return self::get_first_choice_value( $field );
	}

	/**
	 * Return safe file values for runtime demos.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return int|null
	 */
	private static function get_safe_file_value( array $field ) {
		$name = strtolower( (string) ( $field['name'] ?? '' ) );

		if ( false !== strpos( $name, 'video' ) ) {
			return null;
		}

		return self::get_placeholder_attachment_id( (string) ( $field['label'] ?? 'File' ) );
	}

	/**
	 * Limit layouts to ones that are safe to demo with generic content.
	 *
	 * @param array<string, mixed> $layout Layout definition.
	 * @return bool
	 */
	private static function is_safe_layout_for_demo( array $layout ) {
		$name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';

		if ( '' === $name ) {
			return false;
		}

		$blocked = array(
			'basic_block',
			'content_grid',
			'faq_block',
			'hero',
			'hero_two_column_split',
			'external_widget',
		);

		return ! in_array( $name, $blocked, true );
	}

	/**
	 * Filter layout definitions to safe and allowlisted rows for runtime demos.
	 *
	 * @param array<string, mixed>            $field   Flexible-content field definition.
	 * @param array<int, array<string, mixed>> $layouts Layout definitions.
	 * @param array<string, mixed>             $context Generation context.
	 * @return array<int, array<string, mixed>>
	 */
	private static function filter_layouts_for_demo( array $field, array $layouts, array $context = array() ) {
		$safe_layouts = array();

		foreach ( $layouts as $layout ) {
			if ( ! is_array( $layout ) || ! self::is_safe_layout_for_demo( $layout ) ) {
				continue;
			}

			$safe_layouts[] = $layout;
		}

		if ( empty( $safe_layouts ) ) {
			return array();
		}

		$allowed_names  = self::get_allowlisted_layout_names_for_demo_field( $field, $safe_layouts, $context );
		$allowed_lookup = ! empty( $allowed_names ) ? array_fill_keys( $allowed_names, true ) : array();

		if ( empty( $allowed_lookup ) ) {
			return array();
		}

		$filtered_layouts = array();

		foreach ( $safe_layouts as $layout ) {
			$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
			if ( '' === $layout_name || ! isset( $allowed_lookup[ $layout_name ] ) ) {
				continue;
			}

			$filtered_layouts[] = $layout;
		}

		return $filtered_layouts;
	}

	/**
	 * Resolve allowlisted layout names for a flexible-content field.
	 *
	 * Falls back to all safe layout names when allowlist helpers are unavailable.
	 *
	 * @param array<string, mixed>             $field   Flexible-content field definition.
	 * @param array<int, array<string, mixed>> $layouts Safe layout definitions.
	 * @param array<string, mixed>             $context Generation context.
	 * @return array<int, string>
	 */
	private static function get_allowlisted_layout_names_for_demo_field( array $field, array $layouts, array $context = array() ) {
		$layout_names = array();

		foreach ( $layouts as $layout ) {
			if ( ! is_array( $layout ) ) {
				continue;
			}

			$layout_name = isset( $layout['name'] ) ? sanitize_key( (string) $layout['name'] ) : '';
			if ( '' !== $layout_name ) {
				$layout_names[] = $layout_name;
			}
		}

		$layout_names = array_values( array_unique( $layout_names ) );
		if ( empty( $layout_names ) ) {
			return array();
		}

		$field_name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
		if ( '' === $field_name ) {
			return $layout_names;
		}

		/*
		 * Dummy Content "Generate All" and explicit custom layout picks should not
		 * inherit per-entry builder allowlists from editor UI state.
		 */
		if ( ! empty( $context['prefer_all_layouts'] ) || ! empty( self::get_selected_layout_names_from_context( $context ) ) ) {
			return $layout_names;
		}

		if (
			! function_exists( 'mrn_base_stack_get_builder_layout_allowlist_targets' ) ||
			! function_exists( 'mrn_base_stack_get_builder_layout_allowlist_catalog_from_field' ) ||
			! function_exists( 'mrn_base_stack_get_builder_layout_allowlist_effective_names' )
		) {
			return $layout_names;
		}

		$targets = mrn_base_stack_get_builder_layout_allowlist_targets();
		if ( ! is_array( $targets ) || ! isset( $targets[ $field_name ] ) ) {
			return $layout_names;
		}

		$catalog = mrn_base_stack_get_builder_layout_allowlist_catalog_from_field( $field );
		if ( ! is_array( $catalog ) || empty( $catalog ) ) {
			return $layout_names;
		}

		$post_id         = isset( $context['post_id'] ) ? absint( $context['post_id'] ) : 0;
		$effective_names = mrn_base_stack_get_builder_layout_allowlist_effective_names( $post_id, $field_name, $catalog );
		if ( ! is_array( $effective_names ) || empty( $effective_names ) ) {
			return array();
		}

		return array_values(
			array_unique(
				array_intersect(
					$layout_names,
					array_filter(
						array_map( 'sanitize_key', $effective_names )
					)
				)
			)
		);
	}

	/**
	 * Get a taxonomy field value.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return mixed
	 */
	private static function get_taxonomy_field_value( array $field ) {
		$taxonomy = isset( $field['taxonomy'] ) ? sanitize_key( (string) $field['taxonomy'] ) : '';
		if ( '' === $taxonomy ) {
			return null;
		}

		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_object instanceof WP_Taxonomy ) {
			return null;
		}

		$term = self::get_or_create_sample_term( $taxonomy_object );
		if ( ! $term instanceof WP_Term ) {
			return null;
		}

		$return_format = isset( $field['return_format'] ) ? (string) $field['return_format'] : 'id';
		$is_multiple   = ! empty( $field['multiple'] );

		if ( 'object' === $return_format ) {
			return $is_multiple ? array( $term ) : $term;
		}

		return $is_multiple ? array( (int) $term->term_id ) : (int) $term->term_id;
	}

	/**
	 * Get a post object or relationship value.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return mixed
	 */
	private static function get_related_post_field_value( array $field ) {
		$post_types = isset( $field['post_type'] ) && is_array( $field['post_type'] ) ? array_map( 'sanitize_key', $field['post_type'] ) : array( 'page' );

		foreach ( $post_types as $post_type ) {
			if ( ! array_key_exists( $post_type, self::$related_post_cache ) ) {
				$post = get_posts(
					array(
						'post_type'      => $post_type,
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'orderby'        => 'date',
						'order'          => 'DESC',
					)
				);

				self::$related_post_cache[ $post_type ] = ! empty( $post[0] ) && $post[0] instanceof WP_Post ? $post[0] : null;
			}

			if ( ! self::$related_post_cache[ $post_type ] instanceof WP_Post ) {
				continue;
			}

			$post = self::$related_post_cache[ $post_type ];

			$return_format = isset( $field['return_format'] ) ? (string) $field['return_format'] : 'id';
			$is_multiple   = 'relationship' === ( $field['type'] ?? '' );

			if ( 'object' === $return_format ) {
				return $is_multiple ? array( $post ) : $post;
			}

			return $is_multiple ? array( (int) $post->ID ) : (int) $post->ID;
		}

		return null;
	}

	/**
	 * Get or create a taxonomy term for sample content.
	 *
	 * @param WP_Taxonomy $taxonomy_object Taxonomy object.
	 * @return WP_Term|null
	 */
	private static function get_or_create_sample_term( WP_Taxonomy $taxonomy_object ) {
		if ( array_key_exists( $taxonomy_object->name, self::$sample_term_cache ) ) {
			return self::$sample_term_cache[ $taxonomy_object->name ];
		}

		$existing = get_terms(
			array(
				'taxonomy'   => $taxonomy_object->name,
				'hide_empty' => false,
				'number'     => 1,
			)
		);

		if ( is_array( $existing ) && ! empty( $existing[0] ) && $existing[0] instanceof WP_Term ) {
			self::$sample_term_cache[ $taxonomy_object->name ] = $existing[0];
			return $existing[0];
		}

		$term_name = sprintf( 'Sample %s', $taxonomy_object->labels->singular_name );
		$result    = wp_insert_term(
			$term_name,
			$taxonomy_object->name,
			array(
				'slug' => sanitize_title( $term_name ),
			)
		);

		if ( is_wp_error( $result ) || empty( $result['term_id'] ) ) {
			self::$sample_term_cache[ $taxonomy_object->name ] = null;
			return null;
		}

		$term = get_term( (int) $result['term_id'], $taxonomy_object->name );
		self::$sample_term_cache[ $taxonomy_object->name ] = $term instanceof WP_Term ? $term : null;

		return self::$sample_term_cache[ $taxonomy_object->name ];
	}

	/**
	 * Get or create a site-local placeholder image attachment.
	 *
	 * @param string $label Human-readable label.
	 * @return int
	 */
	private static function get_placeholder_attachment_id( $label ) {
		if ( null !== self::$placeholder_attachment_id ) {
			if ( ! self::$placeholder_attachment_refreshed && self::$placeholder_attachment_id > 0 ) {
				self::refresh_placeholder_attachment_file( self::$placeholder_attachment_id, $label );
				self::$placeholder_attachment_refreshed = true;
			}

			return (int) self::$placeholder_attachment_id;
		}

		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'meta_key'       => self::PLACEHOLDER_FLAG_META,
				'meta_value'     => '1',
			)
		);

		if ( ! empty( $attachments[0] ) ) {
			self::$placeholder_attachment_id = (int) $attachments[0]->ID;
			self::refresh_placeholder_attachment_file( self::$placeholder_attachment_id, $label );
			self::$placeholder_attachment_refreshed = true;
			return self::$placeholder_attachment_id;
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}

		$filename = 'mrn-dummy-content-placeholder.svg';
		$svg      = self::get_placeholder_svg( $label );
		$result   = wp_upload_bits( $filename, null, $svg );

		if ( ! empty( $result['error'] ) || empty( $result['file'] ) ) {
			self::$placeholder_attachment_id = 0;
			return 0;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_title'     => 'Dummy Content Placeholder',
				'post_mime_type' => 'image/svg+xml',
				'post_status'    => 'inherit',
			),
			$result['file']
		);

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			self::$placeholder_attachment_id = 0;
			return 0;
		}

		update_post_meta( $attachment_id, self::PLACEHOLDER_FLAG_META, '1' );
		self::$placeholder_attachment_id        = (int) $attachment_id;
		self::$placeholder_attachment_refreshed = true;

		return self::$placeholder_attachment_id;
	}

	/**
	 * Refresh the placeholder attachment file contents to the latest SVG template.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $label Human-readable label.
	 * @return void
	 */
	private static function refresh_placeholder_attachment_file( $attachment_id, $label ) {
		$file = get_attached_file( $attachment_id );
		if ( ! is_string( $file ) || '' === $file ) {
			return;
		}

		$directory = dirname( $file );
		if ( ! is_dir( $directory ) || ! wp_is_writable( $directory ) ) {
			return;
		}

		$svg = self::get_placeholder_svg( $label );
		if ( false === file_put_contents( $file, $svg ) ) {
			return;
		}

		clearstatcache( true, $file );
	}

	/**
	 * Build a simple placeholder SVG.
	 *
	 * @param string $label Display label.
	 * @return string
	 */
	private static function get_placeholder_svg( $label ) {
		$description = wp_strip_all_tags( $label );
		if ( '' === $description ) {
			$description = 'Placeholder image';
		}

		$description = esc_html( $description );

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900" viewBox="0 0 1600 900" role="img" aria-labelledby="title desc"><title id="title">Dummy Content Placeholder</title><desc id="desc">%1$s</desc><rect width="1600" height="900" fill="#f5f7fb"/><rect x="220" y="180" width="520" height="360" rx="20" fill="#dfe4ec" opacity="0.75" transform="rotate(-8 480 360)"/><rect x="420" y="250" width="560" height="390" rx="24" fill="#d4dae4"/><rect x="470" y="300" width="460" height="290" rx="18" fill="#eef2f7"/><circle cx="585" cy="390" r="42" fill="#d4dae4"/><path d="M530 530l110-110 80 74 95-128 115 164H530z" fill="#c4ccd8"/><rect x="420" y="250" width="560" height="390" rx="24" fill="none" stroke="#c2c9d4" stroke-width="18"/></svg>',
			$description
		);
	}

	/**
	 * Find an existing generated post by type and optional slug.
	 *
	 * @param string      $post_type Post type slug.
	 * @param string|null $slug Optional post slug.
	 * @return WP_Post|null
	 */
	private static function get_existing_generated_post( $post_type, $slug = null ) {
		$args = array(
			'post_type'      => $post_type,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 1,
			'meta_key'       => self::GENERATED_BY_META,
		);

		if ( null !== $slug ) {
			$args['name'] = sanitize_title( $slug );
		}

		$posts = get_posts( $args );
		return ! empty( $posts[0] ) ? $posts[0] : null;
	}

	/**
	 * Set a placeholder thumbnail when the post type supports it.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post type slug.
	 * @return void
	 */
	private static function maybe_set_placeholder_thumbnail( $post_id, $post_type ) {
		if ( ! post_type_supports( $post_type, 'thumbnail' ) ) {
			return;
		}

		$attachment_id = self::get_placeholder_attachment_id( get_the_title( $post_id ) );
		if ( $attachment_id > 0 ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	/**
	 * Sample body copy helper.
	 *
	 * @param string $subject Subject label.
	 * @return string
	 */
	private static function get_sample_paragraphs( $subject ) {
		$subject = wp_strip_all_tags( $subject );

		return sprintf(
			'<p>This sample %1$s content was generated from the site&apos;s runtime configuration so you can validate templates, layout behavior, and editor flows.</p><p>Update or remove it whenever you&apos;re ready to replace the placeholder copy with real content.</p>',
			esc_html( strtolower( $subject ) )
		);
	}
}
