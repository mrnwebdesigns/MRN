<?php
/**
 * Plugin Name: Style Guide (MU)
 * Description: Adds a logged-in-only front-end style guide panel and full reference page for reviewing live brand styles.
 * Author: MRN Web Designs
 * Version: 0.1.3
 */

defined('ABSPATH') || exit;

/**
 * Determine whether the style guide should be available on the current request.
 */
function mrn_active_style_guide_is_available(): bool {
    return !is_admin() && is_user_logged_in();
}

/**
 * Get the public path slug for the style guide page.
 */
function mrn_active_style_guide_slug(): string {
    return 'style-guide';
}

/**
 * Get the front-end style guide page URL.
 */
function mrn_active_style_guide_get_page_url(): string {
    return add_query_arg('mrn-style-guide-page', '1', home_url('/'));
}

/**
 * Get the current page URL with the panel-open flag applied.
 */
function mrn_active_style_guide_get_panel_url(): string {
    global $wp;

    $request_path = '';
    if (isset($wp->request) && is_string($wp->request)) {
        $request_path = $wp->request;
    }

    $current_url = home_url('/' . ltrim($request_path, '/'));

    if (is_singular()) {
        $permalink = get_permalink();
        if (is_string($permalink) && $permalink !== '') {
            $current_url = $permalink;
        }
    } elseif (is_home() || is_front_page()) {
        $current_url = home_url('/');
    }

    $current_url = remove_query_arg(array('mrn-style-guide', 'mrn-style-guide-page'), $current_url);

    return add_query_arg('mrn-style-guide', 'open', $current_url);
}

/**
 * Determine whether the current request targets the style guide page.
 */
function mrn_active_style_guide_is_page_request(): bool {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query-flag detection.
    $style_guide_page = isset($_GET['mrn-style-guide-page']) ? sanitize_text_field(wp_unslash((string) $_GET['mrn-style-guide-page'])) : '';
    if ($style_guide_page === '1') {
        return true;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request-path detection.
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI'])) : '';
    $request_path = wp_parse_url($request_uri, PHP_URL_PATH);
    $home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);

    $request_path = trim((string) $request_path, '/');
    $home_path = trim((string) $home_path, '/');

    if ($home_path !== '' && str_starts_with($request_path, $home_path . '/')) {
        $request_path = substr($request_path, strlen($home_path) + 1);
    } elseif ($request_path === $home_path) {
        $request_path = '';
    }

    return $request_path === trim(mrn_active_style_guide_slug(), '/');
}

/**
 * Get configured site colors when available.
 *
 * @return array<int, array<string, string>>
 */
function mrn_active_style_guide_get_colors(): array {
    if (function_exists('mrn_site_colors_get_all')) {
        return mrn_site_colors_get_all();
    }

    return array();
}

/**
 * Build typography rows for live inspection.
 *
 * @return array<int, array<string, string>>
 */
function mrn_active_style_guide_get_typography_rows(): array {
    return array(
        array(
            'tag'   => 'h1',
            'label' => 'Heading 1 (H1)',
            'text'  => 'Style Guide Heading One',
        ),
        array(
            'tag'   => 'h2',
            'label' => 'Heading 2 (H2)',
            'text'  => 'Style Guide Heading Two',
        ),
        array(
            'tag'   => 'h3',
            'label' => 'Heading 3 (H3)',
            'text'  => 'Style Guide Heading Three',
        ),
        array(
            'tag'   => 'h4',
            'label' => 'Heading 4 (H4)',
            'text'  => 'Style Guide Heading Four',
        ),
        array(
            'tag'   => 'p',
            'label' => 'Body Copy (P)',
            'text'  => 'This style guide uses your live theme CSS so the samples reflect the current site, not a disconnected mockup.',
        ),
    );
}

/**
 * Build button rows for live inspection.
 *
 * @return array<int, array<string, string>>
 */
function mrn_active_style_guide_get_button_rows(): array {
    return array(
        array(
            'label' => 'Primary',
            'class' => 'mrn-active-style-guide-button is-primary',
            'text'  => 'Primary Action',
        ),
        array(
            'label' => 'Secondary',
            'class' => 'mrn-active-style-guide-button',
            'text'  => 'Secondary Action',
        ),
    );
}

/**
 * Build form sample rows.
 *
 * @return array<int, array<string, string>>
 */
function mrn_active_style_guide_get_form_rows(): array {
    return array(
        array(
            'label'       => 'Text Field',
            'placeholder' => 'Example text input',
            'type'        => 'text',
        ),
        array(
            'label'       => 'Email Field',
            'placeholder' => 'name@example.com',
            'type'        => 'email',
        ),
    );
}

/**
 * Add a quick-open link to the admin bar on the front end.
 *
 * @param WP_Admin_Bar $admin_bar Admin bar instance.
 */
function mrn_active_style_guide_add_admin_bar_node(WP_Admin_Bar $admin_bar): void {
    if (!mrn_active_style_guide_is_available()) {
        return;
    }

    $admin_bar->add_node(array(
        'id'    => 'mrn-active-style-guide',
        'title' => 'Style Guide',
        'href'  => mrn_active_style_guide_get_panel_url(),
        'meta'  => array(
            'class' => 'mrn-active-style-guide-admin-bar',
        ),
    ));
}
add_action('admin_bar_menu', 'mrn_active_style_guide_add_admin_bar_node', 90);

/**
 * Add a wp-admin sidebar entry that opens the full front-end guide.
 */
function mrn_active_style_guide_register_admin_menu(): void {
    $hook_suffix = add_theme_page(
        'Style Guide',
        'Style Guide',
        'edit_theme_options',
        'mrn-active-style-guide',
        'mrn_active_style_guide_render_admin_menu_page'
    );

    if (is_string($hook_suffix) && $hook_suffix !== '') {
        add_action('load-' . $hook_suffix, 'mrn_active_style_guide_handle_admin_menu_redirect');
    }
}
add_action('admin_menu', 'mrn_active_style_guide_register_admin_menu');

/**
 * Redirect the wp-admin menu entry to the front-end guide.
 */
function mrn_active_style_guide_handle_admin_menu_redirect(): void {
    if (!current_user_can('edit_theme_options')) {
        wp_die(esc_html__('You do not have permission to view the style guide.', 'mrn-active-style-guide'));
    }

    wp_safe_redirect(mrn_active_style_guide_get_page_url());
    exit;
}

/**
 * Fallback callback for the admin menu page.
 */
function mrn_active_style_guide_render_admin_menu_page(): void {
    if (!current_user_can('edit_theme_options')) {
        wp_die(esc_html__('You do not have permission to view the style guide.', 'mrn-active-style-guide'));
    }

    echo '<div class="wrap"><h1>' . esc_html__('Style Guide', 'mrn-active-style-guide') . '</h1><p>' . esc_html__('Redirecting to the front-end style guide.', 'mrn-active-style-guide') . '</p></div>';
}

/**
 * Add a wp-admin sidebar entry for front-end developer references.
 */
function mrn_active_style_guide_register_developer_reference_menu(): void {
    add_theme_page(
        'Developer Reference',
        'Developer Reference',
        'edit_theme_options',
        'mrn-active-style-guide-developer-reference',
        'mrn_active_style_guide_render_developer_reference_page'
    );
}
add_action('admin_menu', 'mrn_active_style_guide_register_developer_reference_menu');

/**
 * Convert an absolute file path into a stylesheet-relative path.
 *
 * @param string $absolute_path Absolute path.
 * @return string
 */
function mrn_active_style_guide_get_stylesheet_relative_path(string $absolute_path): string {
    $theme_root = wp_normalize_path(get_stylesheet_directory());
    $absolute_path = wp_normalize_path($absolute_path);

    if ($theme_root !== '' && str_starts_with($absolute_path, $theme_root . '/')) {
        return ltrim(substr($absolute_path, strlen($theme_root)), '/');
    }

    return ltrim($absolute_path, '/');
}

/**
 * Determine whether a root theme PHP file should be treated as a template entry.
 *
 * @param string $filename File basename.
 * @return bool
 */
function mrn_active_style_guide_is_root_template_filename(string $filename): bool {
    $filename = trim($filename);
    if ($filename === '') {
        return false;
    }

    $direct_matches = array(
        'index.php',
        'front-page.php',
        'home.php',
        'page.php',
        'single.php',
        'archive.php',
        'search.php',
        '404.php',
        'header.php',
        'footer.php',
        'sidebar.php',
    );

    if (in_array($filename, $direct_matches, true)) {
        return true;
    }

    $prefixes = array(
        'single-',
        'page-',
        'archive-',
        'category-',
        'tag-',
        'taxonomy-',
        'author-',
        'date-',
        'template-',
    );

    foreach ($prefixes as $prefix) {
        if (str_starts_with($filename, $prefix)) {
            return true;
        }
    }

    return false;
}

/**
 * Get root template files from the active stylesheet directory.
 *
 * @return array<int, string>
 */
function mrn_active_style_guide_get_root_template_files(): array {
    $theme_root = get_stylesheet_directory();
    if (!is_string($theme_root) || $theme_root === '') {
        return array();
    }

    $matches = glob(trailingslashit($theme_root) . '*.php');
    if (!is_array($matches)) {
        return array();
    }

    $templates = array();

    foreach ($matches as $match) {
        if (!is_string($match) || $match === '') {
            continue;
        }

        $filename = wp_basename($match);
        if (!mrn_active_style_guide_is_root_template_filename($filename)) {
            continue;
        }

        $templates[] = $filename;
    }

    sort($templates, SORT_NATURAL | SORT_FLAG_CASE);

    return array_values(array_unique($templates));
}

/**
 * Get template-part files from the active stylesheet directory.
 *
 * @return array<int, string>
 */
function mrn_active_style_guide_get_template_part_files(): array {
    $template_parts_dir = trailingslashit(get_stylesheet_directory()) . 'template-parts';
    if (!is_dir($template_parts_dir)) {
        return array();
    }

    $files = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($template_parts_dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file_info) {
        if (!$file_info instanceof SplFileInfo || !$file_info->isFile()) {
            continue;
        }

        if (strtolower($file_info->getExtension()) !== 'php') {
            continue;
        }

        $files[] = mrn_active_style_guide_get_stylesheet_relative_path($file_info->getPathname());
    }

    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    return array_values(array_unique($files));
}

/**
 * Get builder layout-to-template references used by the stack shell.
 *
 * @return array<string, string>
 */
function mrn_active_style_guide_get_builder_layout_template_map(): array {
    return array(
        'body_text'        => 'template-parts/builder/body-text.php',
        'content_lists'    => 'template-parts/builder/content-lists.php',
        'basic'            => 'template-parts/builder/basic.php',
        'cta'              => 'Reusable block renderer (`mrn_reusable_cta`)',
        'grid'             => 'Reusable block renderer (`mrn_reusable_grid`)',
        'faq'              => 'Reusable block renderer (`mrn_reusable_faq`)',
        'image_content'    => 'template-parts/builder/image-content.php',
        'video'            => 'template-parts/builder/video.php',
        'slider'           => 'template-parts/builder/slider.php',
        'tabbed_layout'    => 'template-parts/builder/tabbed-layout.php',
        'logos'            => 'template-parts/builder/logos.php',
        'stats'            => 'template-parts/builder/stats.php',
        'showcase'         => 'template-parts/builder/showcase.php',
        'external_widget'  => 'template-parts/builder/external-widget.php',
        'wpforms'          => 'template-parts/builder/wpforms.php',
        'searchwp_form'    => 'template-parts/builder/searchwp-form.php',
        'card'             => 'template-parts/builder/card.php',
        'two_column_split' => 'template-parts/builder/two-column-split.php',
        'reusable_block'   => 'template-parts/builder/reusable-block.php',
        'basic_block'      => 'Reusable block renderer (`mrn_reusable_basic`)',
    );
}

/**
 * Get CSS custom properties defined in the active theme stylesheet.
 *
 * @return array<int, string>
 */
function mrn_active_style_guide_get_css_custom_properties(): array {
    $style_css = trailingslashit(get_stylesheet_directory()) . 'style.css';
    if (!is_readable($style_css)) {
        return array();
    }

    $contents = file_get_contents($style_css);
    if (!is_string($contents) || $contents === '') {
        return array();
    }

    if (!preg_match_all('/(--[A-Za-z0-9_-]+)\s*:/', $contents, $matches) || empty($matches[1])) {
        return array();
    }

    $variables = array_values(
        array_unique(
            array_filter(
                array_map(
                    static function ($item): string {
                        return is_string($item) ? trim($item) : '';
                    },
                    $matches[1]
                )
            )
        )
    );

    sort($variables, SORT_NATURAL | SORT_FLAG_CASE);

    return $variables;
}

/**
 * Get site-color CSS variable names from configured Site Colors.
 *
 * @return array<int, string>
 */
function mrn_active_style_guide_get_site_color_variables(): array {
    if (!function_exists('mrn_site_colors_get_all')) {
        return array();
    }

    $variables = array();

    foreach (mrn_site_colors_get_all() as $row) {
        if (!is_array($row)) {
            continue;
        }

        $slug = isset($row['slug']) ? sanitize_title((string) $row['slug']) : '';
        if ($slug === '') {
            continue;
        }

        $variables[] = '--site-color-' . $slug;
    }

    $variables = array_values(array_unique($variables));
    sort($variables, SORT_NATURAL | SORT_FLAG_CASE);

    return $variables;
}

/**
 * Get stable front-end classes and wrappers for stack theming.
 *
 * @return array<int, string>
 */
function mrn_active_style_guide_get_frontend_class_reference(): array {
    return array(
        '.mrn-shell-container',
        '.mrn-shell-container--content',
        '.mrn-shell-container--wide',
        '.mrn-layout-section',
        '.mrn-layout-container',
        '.mrn-layout-grid',
        '.mrn-content-builder',
        '.mrn-content-builder__row',
        '.mrn-singular-shell',
        '.mrn-singular-shell--has-sidebar',
        '.mrn-singular-shell__main',
        '.mrn-singular-shell__sidebar',
        '.mrn-ui__head',
        '.mrn-ui__body',
        '.mrn-ui__link',
        '.mrn-ui__link--button',
        '.mrn-ui__link--text',
    );
}

/**
 * Get key helper functions used in stack template rendering.
 *
 * @return array<int, string>
 */
function mrn_active_style_guide_get_frontend_helper_functions(): array {
    return array(
        'mrn_base_stack_get_section_width_layers()',
        'mrn_base_stack_get_builder_anchor_markup()',
        'mrn_base_stack_get_builder_motion_contract()',
        'mrn_base_stack_get_builder_sub_content_width_contract()',
        'mrn_base_stack_get_builder_flex_contract()',
        'mrn_base_stack_get_button_link_icon_markup()',
        'mrn_base_stack_get_button_link_icon_position()',
        'mrn_site_colors_get_css_var()',
        'mrn_rbl_render_block()',
    );
}

/**
 * Get theme asset handles and the primary files they load.
 *
 * @return array<string, string>
 */
function mrn_active_style_guide_get_theme_asset_handle_reference(): array {
    return array(
        'mrn-base-stack-style'                   => 'Front-end stylesheet (`style.css`)',
        'mrn-base-stack-navigation'              => 'Front-end script (`js/navigation.js`)',
        'mrn-base-stack-header-search'           => 'Front-end script (`js/header-search.js`, conditional)',
        'mrn-base-stack-motion'                  => 'Front-end script (`js/vendor/motion.js`, conditional)',
        'mrn-base-stack-front-end-effects'       => 'Front-end script (`js/front-end-effects.js`, conditional)',
        'mrn-base-stack-splide'                  => 'Slider assets (`css/js/vendor/splide.min.*`, conditional)',
        'mrn-base-stack-front-end-slider'        => 'Front-end slider script (`js/front-end-slider.js`, conditional)',
        'mrn-base-stack-front-end-tabs'          => 'Front-end tabs script (`js/front-end-tabs.js`, conditional)',
        'mrn-base-stack-glightbox'               => 'Gallery lightbox assets (`css/js/vendor/glightbox.min.*`, conditional)',
        'mrn-base-stack-front-end-gallery'       => 'Front-end gallery script (`js/front-end-gallery.js`, conditional)',
        'mrn-base-stack-admin-repeater-controls' => 'Admin repeater assets (`css/js/admin-repeater-controls.*`)',
        'mrn-base-stack-admin-icon-choosers'     => 'Admin icon-chooser assets (`css/js/admin-icon-choosers.*`)',
        'mrn-base-stack-gallery-admin'           => 'Admin gallery script (`js/admin-gallery.js`, gallery CPT only)',
        'mrn-base-stack-content-builder-admin'   => 'Admin builder script (`js/content-builder-admin.js`, builder only)',
        'mrn-base-stack-row-flex-layout-admin'   => 'Admin row flex script (`js/admin-row-flex-layout.js`, builder only)',
        'mrn-base-stack-fontawesome'             => 'Shared Font Awesome enqueue wrapper (conditional)',
        'dashicons'                              => 'WordPress core icon font (conditional)',
    );
}

/**
 * Get key stack hook and filter references useful for front-end implementation.
 *
 * @return array<string, string>
 */
function mrn_active_style_guide_get_theme_hook_map_reference(): array {
    return array(
        'wp_enqueue_scripts'                    => 'mrn_base_stack_scripts()',
        'acf/input/admin_enqueue_scripts'       => 'mrn_base_stack_enqueue_shared_repeater_admin_assets()',
        'admin_enqueue_scripts'                 => 'mrn_base_stack_enqueue_gallery_admin_assets()',
        'body_class'                            => 'mrn_base_stack_body_classes()',
        'wp_head'                               => 'mrn_base_stack_pingback_header()',
        'wp_head (priority 40)'                 => 'mrn_base_stack_print_business_schema()',
        'mrn_universal_sticky_bar_post_types'   => 'mrn_base_stack_add_editorial_cpts_to_universal_sticky_bar()',
        'mrn_base_stack_sidebar_supported_post_types' => 'Filter sidebar-enabled singular post types (default: none)',
        'mrn_base_stack_singular_shell_post_types'    => 'Filter singular shell post types',
    );
}

/**
 * Get sidebar shell contracts and classes for stack theming.
 *
 * @return array<string, string>
 */
function mrn_active_style_guide_get_sidebar_contract_reference(): array {
    return array(
        'Sidebar-enabled post types (default)' => 'None (enable via mrn_base_stack_sidebar_supported_post_types)',
        'Sidebar layout field'                 => 'sidebar_layout (left|right)',
        'Sidebar content field (builder off)'  => 'sidebar_content (WYSIWYG)',
        'Sidebar rows field (builder on)'      => 'page_sidebar_rows (flexible content)',
        'Main shell wrapper'                   => '.mrn-singular-shell',
        'Has-sidebar shell modifier'           => '.mrn-singular-shell--has-sidebar',
        'Left sidebar shell modifier'          => '.mrn-singular-shell--sidebar-left',
        'Right sidebar shell modifier'         => '.mrn-singular-shell--sidebar-right',
        'Main content column wrapper'          => '.mrn-singular-shell__main',
        'Sidebar column wrapper'               => '.mrn-singular-shell__sidebar',
        'Sidebar markup wrapper'               => '.mrn-singular-sidebar',
    );
}

/**
 * Get reusable block CPT to shortcode references.
 *
 * @return array<string, string>
 */
function mrn_active_style_guide_get_reusable_block_shortcode_rows(): array {
    $rows = array(
        'Generic shortcode (slug)' => '[mrn_block slug="your-block-slug"]',
        'Generic shortcode (ID)'   => '[mrn_block id="123"]',
    );

    if (!function_exists('mrn_rbl_get_post_type_definitions')) {
        return $rows;
    }

    foreach (mrn_rbl_get_post_type_definitions() as $post_type => $definition) {
        if (!is_string($post_type) || $post_type === '' || !is_array($definition)) {
            continue;
        }

        $singular = isset($definition['singular']) ? trim((string) $definition['singular']) : 'Reusable Block';
        $starter_slug = isset($definition['starter_slug']) ? sanitize_title((string) $definition['starter_slug']) : '';
        $shortcode = $starter_slug !== ''
            ? sprintf('[mrn_block slug="%s"]', $starter_slug)
            : '[mrn_block slug="your-block-slug"]';

        $rows[$post_type . ' (' . $singular . ')'] = $shortcode;
    }

    return $rows;
}

/**
 * Get starter CSS snippets for common theme adjustments.
 *
 * @return array<string, string>
 */
function mrn_active_style_guide_get_starter_css_snippets(): array {
    return array(
        'Sidebar Width Tuning' => ".mrn-singular-shell--has-sidebar {\n    grid-template-columns: minmax(0, 1fr) minmax(260px, 320px);\n}\n\n@media (max-width: 980px) {\n    .mrn-singular-shell--has-sidebar {\n        grid-template-columns: 1fr;\n    }\n}",
        'Global Container Width Override' => ":root {\n    --mrn-shell-wide-width: 1360px;\n    --mrn-shell-max-width: 1120px;\n    --mrn-shell-content-width: 760px;\n}",
        'Primary Button Tone Override' => ".mrn-ui__link--button,\n.mrn-active-style-guide-button.is-primary {\n    background: var(--site-color-brand-primary, #0b6ea8);\n    border-color: var(--site-color-brand-primary, #0b6ea8);\n    color: #fff;\n}",
        'Content Row Vertical Rhythm' => ".entry-content--builder {\n    gap: clamp(1.25rem, 2.2vw, 2rem);\n}\n\n.mrn-content-builder__row {\n    margin-block: 0;\n}",
        'Header Search Icon Size' => ".mrn-site-search__icon {\n    font-size: 1.125rem;\n}\n\n.mrn-site-search__toggle {\n    min-width: 2.5rem;\n    min-height: 2.5rem;\n}",
    );
}

/**
 * Render one developer-reference section card with copy controls.
 *
 * @param string               $section_id Unique DOM section ID.
 * @param string               $title Section title.
 * @param string               $description Section intro text.
 * @param array<string, string> $rows Label => value map.
 * @param string               $label_heading Label column heading.
 * @param string               $value_heading Value column heading.
 * @return void
 */
function mrn_active_style_guide_render_developer_reference_section(
    string $section_id,
    string $title,
    string $description,
    array $rows,
    string $label_heading = 'Name',
    string $value_heading = 'Reference'
): void {
    echo '<section id="' . esc_attr($section_id) . '" class="mrn-devref-card">';
    echo '<div class="mrn-devref-card__head">';
    echo '<h2>' . esc_html($title) . '</h2>';
    echo '<button type="button" class="button button-secondary mrn-devref-copy-all">' . esc_html__('Copy All', 'mrn-active-style-guide') . '</button>';
    echo '</div>';
    echo '<p class="description">' . esc_html($description) . '</p>';

    if ($rows === array()) {
        echo '<p>' . esc_html__('No entries found in this environment.', 'mrn-active-style-guide') . '</p>';
        echo '</section>';
        return;
    }

    echo '<div class="mrn-devref-table-wrap"><table class="widefat striped mrn-devref-table">';
    echo '<thead><tr><th>' . esc_html($label_heading) . '</th><th>' . esc_html($value_heading) . '</th><th>' . esc_html__('Copy', 'mrn-active-style-guide') . '</th></tr></thead>';
    echo '<tbody>';

    foreach ($rows as $label => $value) {
        $label = is_string($label) ? trim($label) : '';
        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            continue;
        }

        if ($label === '') {
            $label = $value;
        }

        echo '<tr>';
        echo '<th scope="row">' . esc_html($label) . '</th>';
        echo '<td><code class="mrn-devref-copy-value">' . esc_html($value) . '</code></td>';
        echo '<td><button type="button" class="button button-small mrn-devref-copy" data-copy-value="' . esc_attr($value) . '">' . esc_html__('Copy', 'mrn-active-style-guide') . '</button></td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
    echo '</section>';
}

/**
 * Render one developer-reference snippets section with copy controls.
 *
 * @param string                $section_id Unique DOM section ID.
 * @param string                $title Section title.
 * @param string                $description Section intro text.
 * @param array<string, string> $snippets Snippet label => snippet code.
 * @return void
 */
function mrn_active_style_guide_render_developer_reference_snippet_section(
    string $section_id,
    string $title,
    string $description,
    array $snippets
): void {
    echo '<section id="' . esc_attr($section_id) . '" class="mrn-devref-card">';
    echo '<div class="mrn-devref-card__head">';
    echo '<h2>' . esc_html($title) . '</h2>';
    echo '<button type="button" class="button button-secondary mrn-devref-copy-all">' . esc_html__('Copy All', 'mrn-active-style-guide') . '</button>';
    echo '</div>';
    echo '<p class="description">' . esc_html($description) . '</p>';

    if ($snippets === array()) {
        echo '<p>' . esc_html__('No snippets available.', 'mrn-active-style-guide') . '</p>';
        echo '</section>';
        return;
    }

    echo '<div class="mrn-devref-snippets">';

    foreach ($snippets as $label => $snippet) {
        $label = is_string($label) ? trim($label) : '';
        $snippet = is_string($snippet) ? trim($snippet) : '';

        if ($snippet === '') {
            continue;
        }

        if ($label === '') {
            $label = 'Snippet';
        }

        $snippet_id = sanitize_html_class($section_id . '-' . sanitize_title($label));
        if ($snippet_id === '') {
            $snippet_id = sanitize_html_class($section_id . '-' . wp_unique_id('snippet-'));
        }

        echo '<article class="mrn-devref-snippet">';
        echo '<div class="mrn-devref-snippet__head">';
        echo '<h3>' . esc_html($label) . '</h3>';
        echo '<button type="button" class="button button-small mrn-devref-copy-target" data-copy-target="' . esc_attr($snippet_id) . '">' . esc_html__('Copy', 'mrn-active-style-guide') . '</button>';
        echo '</div>';
        echo '<pre><code id="' . esc_attr($snippet_id) . '" class="mrn-devref-copy-value mrn-devref-copy-value--multiline">' . esc_html($snippet) . '</code></pre>';
        echo '</article>';
    }

    echo '</div>';
    echo '</section>';
}

/**
 * Render the Developer Reference admin page.
 *
 * @return void
 */
function mrn_active_style_guide_render_developer_reference_page(): void {
    if (!current_user_can('edit_theme_options')) {
        wp_die(esc_html__('You do not have permission to view developer references.', 'mrn-active-style-guide'));
    }

    $root_templates = mrn_active_style_guide_get_root_template_files();
    $template_parts = mrn_active_style_guide_get_template_part_files();
    $builder_map = mrn_active_style_guide_get_builder_layout_template_map();
    $css_variables = mrn_active_style_guide_get_css_custom_properties();
    $site_color_variables = mrn_active_style_guide_get_site_color_variables();
    $class_reference = mrn_active_style_guide_get_frontend_class_reference();
    $helper_reference = mrn_active_style_guide_get_frontend_helper_functions();
    $asset_reference = mrn_active_style_guide_get_theme_asset_handle_reference();
    $hook_reference = mrn_active_style_guide_get_theme_hook_map_reference();
    $sidebar_contract_reference = mrn_active_style_guide_get_sidebar_contract_reference();
    $reusable_block_shortcodes = mrn_active_style_guide_get_reusable_block_shortcode_rows();
    $starter_css_snippets = mrn_active_style_guide_get_starter_css_snippets();

    $root_template_rows = array();
    foreach ($root_templates as $template) {
        $root_template_rows[$template] = $template;
    }

    $template_part_rows = array();
    foreach ($template_parts as $template_part) {
        $template_part_rows[$template_part] = $template_part;
    }

    $css_variable_rows = array();
    foreach ($css_variables as $variable) {
        $css_variable_rows[$variable] = $variable;
    }

    $site_color_variable_rows = array();
    foreach ($site_color_variables as $variable) {
        $site_color_variable_rows[$variable] = $variable;
    }

    $class_rows = array();
    foreach ($class_reference as $class_name) {
        $class_rows[$class_name] = $class_name;
    }

    $helper_rows = array();
    foreach ($helper_reference as $helper_name) {
        $helper_rows[$helper_name] = $helper_name;
    }

    $asset_rows = array();
    foreach ($asset_reference as $asset_handle => $asset_source) {
        $asset_rows[$asset_handle] = $asset_source;
    }

    $hook_rows = array();
    foreach ($hook_reference as $hook_name => $hook_target) {
        $hook_rows[$hook_name] = $hook_target;
    }

    $sidebar_contract_rows = array();
    foreach ($sidebar_contract_reference as $contract_name => $contract_value) {
        $sidebar_contract_rows[$contract_name] = $contract_value;
    }

    $reusable_shortcode_rows = array();
    foreach ($reusable_block_shortcodes as $label => $shortcode) {
        $reusable_shortcode_rows[$label] = $shortcode;
    }

    $tabs = array(
        'templates' => 'Templates',
        'variables' => 'Variables',
        'assets-hooks' => 'Assets & Hooks',
        'shell-contracts' => 'Shell Contracts',
        'reusable-blocks' => 'Reusable Blocks',
        'starter-snippets' => 'Starter Snippets',
    );
    ?>
    <div class="wrap mrn-devref-page">
        <h1><?php echo esc_html__('Developer Reference', 'mrn-active-style-guide'); ?></h1>
        <p>
            <?php echo esc_html__('Quick-copy references for front-end implementation in the active stack theme: templates, variables, hooks, assets, shell contracts, reusable block shortcuts, and starter snippets.', 'mrn-active-style-guide'); ?>
        </p>

        <p class="description">
            <?php
            echo esc_html(
                sprintf(
                    'Active stylesheet: %s',
                    (string) get_stylesheet()
                )
            );
            ?>
        </p>

        <div class="mrn-devref-tabs" role="tablist" aria-label="<?php echo esc_attr__('Developer reference sections', 'mrn-active-style-guide'); ?>">
            <?php
            $tab_index = 0;
            foreach ($tabs as $tab_key => $tab_label) :
                $tab_id = 'mrn-devref-tab-' . sanitize_html_class($tab_key);
                $panel_id = 'mrn-devref-panel-' . sanitize_html_class($tab_key);
                $is_active = $tab_index === 0;
                ?>
                <button
                    type="button"
                    id="<?php echo esc_attr($tab_id); ?>"
                    class="mrn-devref-tab<?php echo $is_active ? ' is-active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                    aria-controls="<?php echo esc_attr($panel_id); ?>"
                    tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
                >
                    <?php echo esc_html($tab_label); ?>
                </button>
                <?php
                $tab_index++;
            endforeach;
            ?>
        </div>

        <section id="mrn-devref-panel-templates" class="mrn-devref-panel is-active" role="tabpanel" aria-labelledby="mrn-devref-tab-templates">
            <div class="mrn-devref-grid">
                <?php
                mrn_active_style_guide_render_developer_reference_section(
                    'mrn-devref-root-templates',
                    'Root Templates',
                    'Primary template files in the active theme root.',
                    $root_template_rows,
                    'Template',
                    'Path'
                );

                mrn_active_style_guide_render_developer_reference_section(
                    'mrn-devref-template-parts',
                    'Template Parts',
                    'Shared template partials used throughout content and builder rendering.',
                    $template_part_rows,
                    'Template Part',
                    'Path'
                );

                mrn_active_style_guide_render_developer_reference_section(
                    'mrn-devref-builder-map',
                    'Builder Layout Map',
                    'Layout slug to rendering target mapping for content-builder rows.',
                    $builder_map,
                    'Layout Slug',
                    'Renderer'
                );
                ?>
            </div>
        </section>

        <section id="mrn-devref-panel-variables" class="mrn-devref-panel" role="tabpanel" aria-labelledby="mrn-devref-tab-variables" hidden>
            <div class="mrn-devref-grid">
                <?php
                mrn_active_style_guide_render_developer_reference_section(
                    'mrn-devref-css-vars',
                    'Theme CSS Variables',
                    'Custom properties found in the active theme `style.css`.',
                    $css_variable_rows,
                    'Variable',
                    'Token'
                );

                mrn_active_style_guide_render_developer_reference_section(
                    'mrn-devref-site-color-vars',
                    'Site Color Variables',
                    'Dynamic variables generated from Site Colors configuration.',
                    $site_color_variable_rows,
                    'Variable',
                    'Token'
                );
                ?>
            </div>
        </section>

        <section id="mrn-devref-panel-assets-hooks" class="mrn-devref-panel" role="tabpanel" aria-labelledby="mrn-devref-tab-assets-hooks" hidden>
            <div class="mrn-devref-grid">
                <?php
                mrn_active_style_guide_render_developer_reference_section(
                    'mrn-devref-assets',
                    'Theme Asset Handles',
                    'Registered/enqueued handles and their primary stack files.',
                    $asset_rows,
                    'Handle',
                    'Source'
                );

                mrn_active_style_guide_render_developer_reference_section(
                    'mrn-devref-hooks',
                    'Theme Hooks & Filters',
                    'Common hook and filter entry points relevant to front-end behavior.',
                    $hook_rows,
                    'Hook',
                    'Callback / Purpose'
                );

                mrn_active_style_guide_render_developer_reference_section(
                    'mrn-devref-helpers',
                    'Theme Helper Functions',
                    'Frequently used helper functions that influence rendering contracts.',
                    $helper_rows,
                    'Function',
                    'Function'
                );
                ?>
            </div>
        </section>

        <section id="mrn-devref-panel-shell-contracts" class="mrn-devref-panel" role="tabpanel" aria-labelledby="mrn-devref-tab-shell-contracts" hidden>
            <div class="mrn-devref-grid">
                <?php
                mrn_active_style_guide_render_developer_reference_section(
                    'mrn-devref-classes',
                    'Stable Front-End Classes',
                    'Core wrappers and utility selectors used as stack theming anchors.',
                    $class_rows,
                    'Selector',
                    'Selector'
                );

                mrn_active_style_guide_render_developer_reference_section(
                    'mrn-devref-sidebar-contracts',
                    'Sidebar Contracts',
                    'Sidebar field names, class modifiers, and shell wrappers used in with-sidebar templates.',
                    $sidebar_contract_rows,
                    'Contract',
                    'Reference'
                );
                ?>
            </div>
        </section>

        <section id="mrn-devref-panel-reusable-blocks" class="mrn-devref-panel" role="tabpanel" aria-labelledby="mrn-devref-tab-reusable-blocks" hidden>
            <div class="mrn-devref-grid">
                <?php
                mrn_active_style_guide_render_developer_reference_section(
                    'mrn-devref-reusable-block-shortcodes',
                    'Reusable Block Shortcodes',
                    'CPT-aware shortcode quick reference for reusable block usage in Classic editor fields and shortcodes.',
                    $reusable_shortcode_rows,
                    'CPT / Reference',
                    'Shortcode'
                );
                ?>
            </div>
        </section>

        <section id="mrn-devref-panel-starter-snippets" class="mrn-devref-panel" role="tabpanel" aria-labelledby="mrn-devref-tab-starter-snippets" hidden>
            <div class="mrn-devref-grid mrn-devref-grid--single">
                <?php
                mrn_active_style_guide_render_developer_reference_snippet_section(
                    'mrn-devref-starter-css-snippets',
                    'Starter CSS Snippets',
                    'Copy-ready starter snippets for common stack adjustments. Paste into your stylesheet or child-theme layer and tune as needed.',
                    $starter_css_snippets
                );
                ?>
            </div>
        </section>

        <p id="mrn-devref-copy-status" class="screen-reader-text" aria-live="polite"></p>
    </div>

    <style>
        .mrn-devref-page .mrn-devref-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
            margin-bottom: 16px;
        }

        .mrn-devref-page .mrn-devref-tab {
            border: 1px solid #8c8f94;
            background: #fff;
            color: #1d2327;
            border-radius: 6px;
            padding: 8px 12px;
            line-height: 1.3;
            cursor: pointer;
            font-size: 13px;
        }

        .mrn-devref-page .mrn-devref-tab.is-active {
            background: #2271b1;
            border-color: #2271b1;
            color: #fff;
        }

        .mrn-devref-page .mrn-devref-tab:focus-visible {
            outline: 2px solid #2271b1;
            outline-offset: 1px;
        }

        .mrn-devref-page .mrn-devref-panel[hidden] {
            display: none;
        }

        .mrn-devref-page .mrn-devref-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(460px, 1fr));
            gap: 16px;
        }

        .mrn-devref-page .mrn-devref-grid--single {
            grid-template-columns: minmax(0, 1fr);
        }

        .mrn-devref-page .mrn-devref-card {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 8px;
            padding: 16px;
            box-sizing: border-box;
        }

        .mrn-devref-page .mrn-devref-card__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }

        .mrn-devref-page .mrn-devref-card__head h2 {
            margin: 0;
            font-size: 18px;
            line-height: 1.3;
        }

        .mrn-devref-page .mrn-devref-table-wrap {
            overflow-x: auto;
        }

        .mrn-devref-page .mrn-devref-table code.mrn-devref-copy-value {
            display: inline-block;
            white-space: nowrap;
            max-width: 100%;
            overflow-x: auto;
            padding: 2px 4px;
        }

        .mrn-devref-page .mrn-devref-copy-value--multiline {
            white-space: pre;
            overflow-x: auto;
            max-width: 100%;
            display: block;
            padding: 10px;
            line-height: 1.45;
        }

        .mrn-devref-page .mrn-devref-snippets {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 12px;
        }

        .mrn-devref-page .mrn-devref-snippet {
            border: 1px solid #dcdcde;
            border-radius: 6px;
            overflow: hidden;
            background: #fff;
        }

        .mrn-devref-page .mrn-devref-snippet__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 10px 12px;
            border-bottom: 1px solid #dcdcde;
            background: #f6f7f7;
        }

        .mrn-devref-page .mrn-devref-snippet__head h3 {
            margin: 0;
            font-size: 14px;
            line-height: 1.3;
        }

        .mrn-devref-page .mrn-devref-snippet pre {
            margin: 0;
            background: #fff;
        }
    </style>

    <script>
        (function () {
            var statusNode = document.getElementById('mrn-devref-copy-status');
            var tabButtons = Array.prototype.slice.call(document.querySelectorAll('.mrn-devref-tab'));

            function setStatus(message) {
                if (!statusNode) {
                    return;
                }

                statusNode.textContent = message;
            }

            function fallbackCopy(text) {
                return new Promise(function (resolve, reject) {
                    var area = document.createElement('textarea');
                    area.value = text;
                    area.setAttribute('readonly', 'readonly');
                    area.style.position = 'absolute';
                    area.style.left = '-9999px';
                    document.body.appendChild(area);
                    area.select();

                    try {
                        var copied = document.execCommand('copy');
                        document.body.removeChild(area);

                        if (copied) {
                            resolve();
                            return;
                        }

                        reject(new Error('Copy command failed.'));
                    } catch (error) {
                        document.body.removeChild(area);
                        reject(error);
                    }
                });
            }

            function copyText(text) {
                if (!text) {
                    return Promise.reject(new Error('Nothing to copy.'));
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    return navigator.clipboard.writeText(text);
                }

                return fallbackCopy(text);
            }

            function activateTab(nextButton, shouldFocus) {
                if (!nextButton) {
                    return;
                }

                tabButtons.forEach(function (button) {
                    var panelId = button.getAttribute('aria-controls') || '';
                    var panel = panelId ? document.getElementById(panelId) : null;
                    var isActive = button === nextButton;

                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    button.setAttribute('tabindex', isActive ? '0' : '-1');

                    if (panel) {
                        if (isActive) {
                            panel.removeAttribute('hidden');
                            panel.classList.add('is-active');
                        } else {
                            panel.setAttribute('hidden', 'hidden');
                            panel.classList.remove('is-active');
                        }
                    }
                });

                if (shouldFocus) {
                    nextButton.focus();
                }
            }

            if (tabButtons.length > 0) {
                tabButtons.forEach(function (button, index) {
                    button.addEventListener('click', function () {
                        activateTab(button, false);
                    });

                    button.addEventListener('keydown', function (event) {
                        var key = event.key;
                        var nextIndex = index;

                        if (key === 'ArrowRight') {
                            nextIndex = (index + 1) % tabButtons.length;
                        } else if (key === 'ArrowLeft') {
                            nextIndex = (index - 1 + tabButtons.length) % tabButtons.length;
                        } else if (key === 'Home') {
                            nextIndex = 0;
                        } else if (key === 'End') {
                            nextIndex = tabButtons.length - 1;
                        } else {
                            return;
                        }

                        event.preventDefault();
                        activateTab(tabButtons[nextIndex], true);
                    });
                });
            }

            document.addEventListener('click', function (event) {
                var copyTargetButton = event.target.closest('.mrn-devref-copy-target');
                if (copyTargetButton) {
                    event.preventDefault();

                    var copyTarget = copyTargetButton.getAttribute('data-copy-target') || '';
                    var codeNode = copyTarget ? document.getElementById(copyTarget) : null;
                    var value = codeNode && codeNode.textContent ? codeNode.textContent : '';
                    var originalTargetLabel = copyTargetButton.textContent;

                    copyText(value).then(function () {
                        copyTargetButton.textContent = 'Copied';
                        setStatus('Copied to clipboard.');
                        window.setTimeout(function () {
                            copyTargetButton.textContent = originalTargetLabel;
                        }, 1200);
                    }).catch(function () {
                        copyTargetButton.textContent = 'Failed';
                        setStatus('Copy failed.');
                        window.setTimeout(function () {
                            copyTargetButton.textContent = originalTargetLabel;
                        }, 1500);
                    });

                    return;
                }

                var copyButton = event.target.closest('.mrn-devref-copy');
                if (copyButton) {
                    event.preventDefault();

                    var value = copyButton.getAttribute('data-copy-value') || '';
                    var original = copyButton.textContent;

                    copyText(value).then(function () {
                        copyButton.textContent = 'Copied';
                        setStatus('Copied to clipboard.');
                        window.setTimeout(function () {
                            copyButton.textContent = original;
                        }, 1200);
                    }).catch(function () {
                        copyButton.textContent = 'Failed';
                        setStatus('Copy failed.');
                        window.setTimeout(function () {
                            copyButton.textContent = original;
                        }, 1500);
                    });

                    return;
                }

                var copyAllButton = event.target.closest('.mrn-devref-copy-all');
                if (!copyAllButton) {
                    return;
                }

                event.preventDefault();

                var section = copyAllButton.closest('.mrn-devref-card');
                if (!section) {
                    return;
                }

                var values = Array.prototype.map.call(
                    section.querySelectorAll('.mrn-devref-copy-value'),
                    function (node) {
                        return node.textContent ? node.textContent.trim() : '';
                    }
                ).filter(function (item) {
                    return item !== '';
                });

                if (!values.length) {
                    setStatus('No values found to copy.');
                    return;
                }

                var originalText = copyAllButton.textContent;
                copyText(values.join('\n')).then(function () {
                    copyAllButton.textContent = 'Copied';
                    setStatus('Section copied to clipboard.');
                    window.setTimeout(function () {
                        copyAllButton.textContent = originalText;
                    }, 1200);
                }).catch(function () {
                    copyAllButton.textContent = 'Failed';
                    setStatus('Copy failed.');
                    window.setTimeout(function () {
                        copyAllButton.textContent = originalText;
                    }, 1500);
                });
            });
        })();
    </script>
    <?php
}

/**
 * Force logged-in-only access for the dedicated style guide page.
 */
function mrn_active_style_guide_protect_page(): void {
    if (!mrn_active_style_guide_is_page_request()) {
        return;
    }

    if (is_user_logged_in()) {
        return;
    }

    global $wp_query;

    if ($wp_query instanceof WP_Query) {
        $wp_query->set_404();
    }

    status_header(404);
    nocache_headers();
}
add_action('template_redirect', 'mrn_active_style_guide_protect_page', 1);

/**
 * Route the dedicated style guide URL to the bundled template.
 *
 * @param string $template Current template path.
 * @return string
 */
function mrn_active_style_guide_template_include(string $template): string {
    if (!mrn_active_style_guide_is_page_request() || !is_user_logged_in()) {
        return $template;
    }

    $style_guide_template = __DIR__ . '/templates/style-guide-page.php';

    if (file_exists($style_guide_template)) {
        return $style_guide_template;
    }

    return $template;
}
add_filter('template_include', 'mrn_active_style_guide_template_include', 99);

/**
 * Render the shared color sample grid.
 */
function mrn_active_style_guide_render_color_grid(): void {
    $colors = mrn_active_style_guide_get_colors();

    if (empty($colors)) {
        echo '<p class="mrn-active-style-guide-note">No site colors are configured yet. Once Site Colors has values, they will appear here automatically.</p>';
        return;
    }

    echo '<div class="mrn-active-style-guide-grid">';
    foreach ($colors as $color) {
        $name = isset($color['name']) ? (string) $color['name'] : 'Color';
        $slug = isset($color['slug']) ? (string) $color['slug'] : '';
        $value = isset($color['value']) ? (string) $color['value'] : '';
        $css_var = $slug !== '' ? '--site-color-' . sanitize_title($slug) : '';

        echo '<div class="mrn-active-style-guide-swatch" style="--mrn-guide-color:' . esc_attr($value) . ';">';
        echo '<div class="mrn-active-style-guide-chip"></div>';
        echo '<div class="mrn-active-style-guide-swatch-meta">';
        echo '<p class="mrn-active-style-guide-swatch-name">' . esc_html($name) . '</p>';
        echo '<p class="mrn-active-style-guide-swatch-code">' . esc_html($value) . '</p>';
        if ($css_var !== '') {
            echo '<p class="mrn-active-style-guide-swatch-code">' . esc_html($css_var) . '</p>';
        }
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
}

/**
 * Render the shared typography samples.
 */
function mrn_active_style_guide_render_typography_samples(): void {
    foreach (mrn_active_style_guide_get_typography_rows() as $row) {
        $tag = isset($row['tag']) ? strtolower((string) $row['tag']) : 'p';
        $label = isset($row['label']) ? (string) $row['label'] : 'Sample';
        $text = isset($row['text']) ? (string) $row['text'] : '';

        if (!in_array($tag, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'), true)) {
            $tag = 'p';
        }

        echo '<div class="mrn-active-style-guide-type-row">';
        echo '<p class="mrn-active-style-guide-label">' . esc_html($label) . '</p>';
        echo '<' . esc_html($tag) . '>' . esc_html($text) . '</' . esc_html($tag) . '>';
        echo '</div>';
    }
}

/**
 * Render the shared button samples.
 */
function mrn_active_style_guide_render_button_samples(): void {
    echo '<div class="mrn-active-style-guide-buttons">';
    foreach (mrn_active_style_guide_get_button_rows() as $row) {
        $class = isset($row['class']) ? (string) $row['class'] : 'mrn-active-style-guide-button';
        $text = isset($row['text']) ? (string) $row['text'] : 'Action';
        echo '<a href="#" class="' . esc_attr($class) . '">' . esc_html($text) . '</a>';
    }
    echo '</div>';
}

/**
 * Render the shared form samples.
 */
function mrn_active_style_guide_render_form_samples(): void {
    echo '<div class="mrn-active-style-guide-form-grid">';
    foreach (mrn_active_style_guide_get_form_rows() as $row) {
        $label = isset($row['label']) ? (string) $row['label'] : 'Field';
        $placeholder = isset($row['placeholder']) ? (string) $row['placeholder'] : '';
        $type = isset($row['type']) ? (string) $row['type'] : 'text';

        echo '<label class="mrn-active-style-guide-field">';
        echo '<span class="mrn-active-style-guide-label">' . esc_html($label) . '</span>';
        echo '<input type="' . esc_attr($type) . '" placeholder="' . esc_attr($placeholder) . '" />';
        echo '</label>';
    }

    echo '<label class="mrn-active-style-guide-field">';
    echo '<span class="mrn-active-style-guide-label">Textarea</span>';
    echo '<textarea rows="4" placeholder="Example multi-line content"></textarea>';
    echo '</label>';
    echo '</div>';
}

/**
 * Print shared component styles for both the panel and the full page.
 */
function mrn_active_style_guide_print_shared_styles(): void {
    ?>
    <style id="mrn-active-style-guide-shared-styles">
        .mrn-active-style-guide-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(96px, 1fr));
        }

        .mrn-active-style-guide-swatch {
            border: 1px solid #dcdcde;
            background: #fff;
        }

        .mrn-active-style-guide-chip {
            height: 40px;
            border-bottom: 1px solid #dcdcde;
            background: var(--mrn-guide-color, #f6f7f7);
        }

        .mrn-active-style-guide-swatch-meta {
            padding: 8px;
        }

        .mrn-active-style-guide-swatch-name {
            margin: 0 0 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .mrn-active-style-guide-swatch-code {
            margin: 0;
            color: #50575e;
            font-size: 11px;
            line-height: 1.35;
            word-break: break-word;
        }

        .mrn-active-style-guide-type-row + .mrn-active-style-guide-type-row {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #f0f0f1;
        }

        .mrn-active-style-guide-label {
            display: block;
            margin: 0 0 10px;
            color: #50575e;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .mrn-active-style-guide-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .mrn-active-style-guide-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 16px;
            border: 1px solid #1d2327;
            background: #fff;
            color: #1d2327;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color .18s ease, color .18s ease, border-color .18s ease, box-shadow .18s ease;
        }

        .mrn-active-style-guide-button.is-primary {
            background: #1d2327;
            color: #fff;
        }

        .mrn-active-style-guide-button:hover,
        .mrn-active-style-guide-button:focus {
            border-color: #111517;
            box-shadow: 0 0 0 3px rgba(29, 35, 39, 0.12);
            outline: none;
        }

        .mrn-active-style-guide-button.is-primary:hover,
        .mrn-active-style-guide-button.is-primary:focus {
            background: #111517;
            color: #fff;
        }

        .mrn-active-style-guide-inline-links {
            display: grid;
            gap: 14px;
        }

        .mrn-active-style-guide-inline-links a {
            color: #0057b8;
            text-decoration-thickness: 1px;
            text-underline-offset: 0.15em;
            transition: color .18s ease, text-decoration-thickness .18s ease;
        }

        .mrn-active-style-guide-inline-links a:hover,
        .mrn-active-style-guide-inline-links a:focus {
            color: #003e85;
            text-decoration-thickness: 2px;
            outline: none;
        }

        .mrn-active-style-guide-surface {
            padding: 20px;
            border: 1px solid #dcdcde;
            background: #fff;
        }

        .mrn-active-style-guide-surface--dark {
            border-color: #1d2327;
            background: #1d2327;
            color: #fff;
        }

        .mrn-active-style-guide-surface--dark .mrn-active-style-guide-note,
        .mrn-active-style-guide-surface--dark .mrn-active-style-guide-label {
            color: rgba(255, 255, 255, 0.74);
        }

        .mrn-active-style-guide-surface--dark a {
            color: #fff;
        }

        .mrn-active-style-guide-mini-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .mrn-active-style-guide-card {
            padding: 18px;
            border: 1px solid #dcdcde;
            background: #fff;
        }

        .mrn-active-style-guide-card h3,
        .mrn-active-style-guide-card h4 {
            margin-top: 0;
        }

        .mrn-active-style-guide-form-grid {
            display: grid;
            gap: 16px;
        }

        .mrn-active-style-guide-field input,
        .mrn-active-style-guide-field textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #c3c4c7;
            background: #fff;
            color: #1d2327;
            font: inherit;
            box-sizing: border-box;
        }

        .mrn-active-style-guide-note {
            margin: 0;
            color: #50575e;
            font-size: 13px;
            line-height: 1.5;
        }
    </style>
    <?php
}

/**
 * Print the logged-in style guide UI on the front end.
 */
function mrn_active_style_guide_render_panel(): void {
    if (!mrn_active_style_guide_is_available() || mrn_active_style_guide_is_page_request()) {
        return;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query-flag detection.
    $panel_state = isset($_GET['mrn-style-guide']) ? sanitize_text_field(wp_unslash((string) $_GET['mrn-style-guide'])) : '';
    $is_open = $panel_state === 'open';
    ?>
    <?php mrn_active_style_guide_print_shared_styles(); ?>
    <style id="mrn-active-style-guide-panel-styles">
        .mrn-active-style-guide-panel {
            position: fixed;
            top: 72px;
            right: 24px;
            bottom: 24px;
            z-index: 999;
            width: min(420px, calc(100vw - 32px));
            display: none;
            flex-direction: column;
            border: 1px solid #dcdcde;
            background: #fff;
            color: #1d2327;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.18);
            overflow: hidden;
        }

        .mrn-active-style-guide-panel.is-open {
            display: flex;
        }

        .mrn-active-style-guide-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 18px;
            border-bottom: 1px solid #dcdcde;
            background: #f6f7f7;
        }

        .mrn-active-style-guide-header h2 {
            margin: 0;
            font-size: 18px;
            line-height: 1.2;
        }

        .mrn-active-style-guide-subtitle {
            margin: 4px 0 0;
            color: #50575e;
            font-size: 12px;
        }

        .mrn-active-style-guide-close,
        .mrn-active-style-guide-view-page {
            border: 1px solid #dcdcde;
            background: #fff;
            color: #1d2327;
            cursor: pointer;
            text-decoration: none;
            padding: 8px 10px;
            font-size: 12px;
            font-weight: 600;
        }

        .mrn-active-style-guide-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .mrn-active-style-guide-body {
            padding: 18px;
            overflow: auto;
        }

        .mrn-active-style-guide-section + .mrn-active-style-guide-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #f0f0f1;
        }

        .mrn-active-style-guide-section h3 {
            margin: 0 0 12px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        @media (max-width: 782px) {
            .mrn-active-style-guide-panel {
                top: 60px;
                right: 16px;
                left: 16px;
                bottom: 16px;
                width: auto;
            }
        }
    </style>
    <div id="mrn-active-style-guide" class="mrn-active-style-guide-panel<?php echo $is_open ? ' is-open' : ''; ?>" aria-hidden="<?php echo $is_open ? 'false' : 'true'; ?>">
        <div class="mrn-active-style-guide-header">
            <div>
                <h2>Style Guide</h2>
                <p class="mrn-active-style-guide-subtitle">Quick panel for live page review while you browse.</p>
            </div>
            <div class="mrn-active-style-guide-actions">
                <a class="mrn-active-style-guide-view-page" href="<?php echo esc_url(mrn_active_style_guide_get_page_url()); ?>">Open Full Guide</a>
                <button type="button" class="mrn-active-style-guide-close" aria-label="Close style guide">Close</button>
            </div>
        </div>
        <div class="mrn-active-style-guide-body">
            <section class="mrn-active-style-guide-section">
                <h3>Color System</h3>
                <?php mrn_active_style_guide_render_color_grid(); ?>
            </section>

            <section class="mrn-active-style-guide-section">
                <h3>Typography</h3>
                <?php mrn_active_style_guide_render_typography_samples(); ?>
            </section>
        </div>
    </div>
    <script id="mrn-active-style-guide-script">
        (function () {
            var panel = document.getElementById('mrn-active-style-guide');
            var closeButton = document.querySelector('.mrn-active-style-guide-close');

            if (!panel || !closeButton) {
                return;
            }

            function setOpenState(isOpen) {
                panel.classList.toggle('is-open', isOpen);
                panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            }

            closeButton.addEventListener('click', function () {
                setOpenState(false);
            });
        }());
    </script>
    <?php
}
add_action('wp_footer', 'mrn_active_style_guide_render_panel', 100);
