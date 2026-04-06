<?php
/**
 * Plugin Name: Style Guide (MU)
 * Description: Adds a logged-in-only front-end style guide panel and full reference page for reviewing live brand styles.
 * Author: MRN Web Designs
 * Version: 0.1.2
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
    if (isset($_GET['mrn-style-guide-page']) && wp_unslash($_GET['mrn-style-guide-page']) === '1') {
        return true;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
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
    $is_open = isset($_GET['mrn-style-guide']) && wp_unslash($_GET['mrn-style-guide']) === 'open';
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
