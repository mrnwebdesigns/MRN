<?php
/**
 * Plugin Name: Reusable Block Library (MU)
 * Description: Adds a reusable block library powered by typed custom post types for editor-managed content blocks.
 * Author: MRN Web Designs
 * Version: 0.1.14
 */

defined('ABSPATH') || exit;

/**
 * Shared parent menu slug for the library.
 */
function mrn_rbl_get_library_menu_slug(): string {
    return 'mrn-reusable-block-library';
}

/**
 * Get the base directory for plugin-managed block templates.
 */
function mrn_rbl_get_templates_dir(): string {
    return __DIR__ . '/templates';
}

/**
 * Centralized CPT definitions so the library can grow without changing registration logic.
 *
 * @return array<string, array<string, mixed>>
 */
function mrn_rbl_get_post_type_definitions(): array {
    $post_types = array(
        'mrn_reusable_cta' => array(
            'singular'       => 'CTA',
            'plural'         => 'CTAs',
            'list_label'     => 'CTAs',
            'add_new_label'  => 'Add New CTA',
            'description'    => 'Call-to-action blocks that can be selected and placed into pages later.',
            'menu_icon'      => 'dashicons-screenoptions',
            'supports'       => array('title', 'revisions'),
            'starter_slug'   => 'reusable-cta',
            'starter_title'  => 'CTA',
        ),
        'mrn_reusable_basic' => array(
            'singular'       => 'Basic Block',
            'plural'         => 'Basic Blocks',
            'list_label'     => 'Basic Blocks',
            'add_new_label'  => 'Add New Basic Block',
            'description'    => 'Basic content blocks that can be selected and placed into pages later.',
            'menu_icon'      => 'dashicons-screenoptions',
            'supports'       => array('title', 'revisions'),
            'starter_slug'   => 'reusable-basic-block',
            'starter_title'  => 'Basic Block',
        ),
        'mrn_reusable_faq' => array(
            'singular'       => 'FAQs/Accordion',
            'plural'         => 'FAQs/Accordion',
            'list_label'     => 'FAQs/Accordion',
            'add_new_label'  => 'Add New FAQs/Accordion',
            'description'    => 'Accordion-style question and answer blocks that can be selected and placed into pages later.',
            'menu_icon'      => 'dashicons-screenoptions',
            'supports'       => array('title', 'revisions'),
            'starter_slug'   => 'reusable-faq',
            'starter_title'  => 'FAQs/Accordion',
        ),
        'mrn_reusable_grid' => array(
            'singular'       => 'Content Grid',
            'plural'         => 'Content Grids',
            'list_label'     => 'Content Grids',
            'add_new_label'  => 'Add New Content Grid',
            'description'    => 'Structured headline-and-grid content sections for clear, scannable messaging.',
            'menu_icon'      => 'dashicons-grid-view',
            'supports'       => array('title', 'revisions'),
            'starter_slug'   => 'reusable-content-grid',
            'starter_title'  => 'Content Grid',
        ),
        'mrn_reusable_list' => array(
            'singular'       => 'Content List',
            'plural'         => 'Content Lists',
            'list_label'     => 'Content Lists',
            'add_new_label'  => 'Add New Content List',
            'description'    => 'Query-driven content listing sections that can be reused across pages.',
            'menu_icon'      => 'dashicons-list-view',
            'supports'       => array('title', 'revisions'),
            'starter_slug'   => 'reusable-content-lists',
            'starter_title'  => 'Content List',
        ),
        'mrn_reusable_search' => array(
            'singular'       => 'Search Form',
            'plural'         => 'Search Forms',
            'list_label'     => 'Search Forms',
            'add_new_label'  => 'Add New Search Form',
            'description'    => 'SearchWP-powered search form sections that can be reused across pages.',
            'menu_icon'      => 'dashicons-search',
            'supports'       => array('title', 'revisions'),
            'starter_slug'   => 'reusable-search-form',
            'starter_title'  => 'Search Form',
        ),
    );

    return apply_filters('mrn_rbl_post_type_definitions', $post_types);
}

/**
 * Get the reusable block post types hidden from the back-end UI.
 *
 * @return array<int, string>
 */
function mrn_rbl_get_hidden_post_types(): array {
    $post_types = function_exists('mrn_config_helper_get_hidden_admin_cpts') ? mrn_config_helper_get_hidden_admin_cpts() : array();

    if (!is_array($post_types)) {
        return array();
    }

    return array_values(
        array_unique(
            array_filter(
                array_map('sanitize_key', $post_types)
            )
        )
    );
}

/**
 * Determine whether a reusable block post type should appear in the back-end UI.
 *
 * @param string $post_type Post type slug.
 * @return bool
 */
function mrn_rbl_is_post_type_visible(string $post_type): bool {
    return !in_array(sanitize_key($post_type), mrn_rbl_get_hidden_post_types(), true);
}

/**
 * Build labels for a library CPT.
 *
 * @param array<string, mixed> $definition
 * @return array<string, string>
 */
function mrn_rbl_get_post_type_labels(array $definition): array {
    $singular = isset($definition['singular']) ? (string) $definition['singular'] : 'Block';
    $plural   = isset($definition['plural']) ? (string) $definition['plural'] : 'Blocks';

    return array(
        'name'                  => $plural,
        'singular_name'         => $singular,
        'menu_name'             => $plural,
        'name_admin_bar'        => $singular,
        'add_new'               => 'Add New',
        'add_new_item'          => 'Add New ' . $singular,
        'edit_item'             => 'Edit ' . $singular,
        'new_item'              => 'New ' . $singular,
        'view_item'             => 'View ' . $singular,
        'view_items'            => 'View ' . $plural,
        'search_items'          => 'Search ' . $plural,
        'not_found'             => 'No ' . strtolower($plural) . ' found.',
        'not_found_in_trash'    => 'No ' . strtolower($plural) . ' found in Trash.',
        'all_items'             => 'All ' . $plural,
        'archives'              => $singular . ' Archives',
        'attributes'            => $singular . ' Attributes',
        'insert_into_item'      => 'Insert into ' . strtolower($singular),
        'uploaded_to_this_item' => 'Uploaded to this ' . strtolower($singular),
        'filter_items_list'     => 'Filter ' . strtolower($plural) . ' list',
        'items_list_navigation' => $plural . ' list navigation',
        'items_list'            => $plural . ' list',
        'item_published'        => $singular . ' published.',
        'item_updated'          => $singular . ' updated.',
    );
}

/**
 * Register the library post types.
 */
function mrn_rbl_register_post_types(): void {
    foreach (mrn_rbl_get_post_type_definitions() as $post_type => $definition) {
        if (!is_string($post_type) || $post_type === '' || !is_array($definition)) {
            continue;
        }

        $supports = isset($definition['supports']) && is_array($definition['supports'])
            ? array_values(array_filter($definition['supports'], 'is_string'))
            : array('title', 'editor', 'revisions');

        $description = isset($definition['description']) ? (string) $definition['description'] : '';
        $menu_icon   = isset($definition['menu_icon']) ? (string) $definition['menu_icon'] : 'dashicons-screenoptions';
        $show_ui     = mrn_rbl_is_post_type_visible($post_type);

        register_post_type($post_type, array(
            'labels'              => mrn_rbl_get_post_type_labels($definition),
            'description'         => $description,
            'public'              => false,
            'show_ui'             => $show_ui,
            'show_in_menu'        => false,
            'show_in_admin_bar'   => $show_ui,
            'show_in_nav_menus'   => false,
            'show_in_rest'        => true,
            'rest_base'           => $post_type,
            'menu_icon'           => $menu_icon,
            'supports'            => $supports,
            'hierarchical'        => false,
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ));
    }
}
add_action('init', 'mrn_rbl_register_post_types');

/**
 * Determine whether a REST request targets one of the reusable block CPT routes.
 *
 * @param WP_REST_Request $request
 * @return bool
 */
function mrn_rbl_is_library_rest_request(WP_REST_Request $request): bool {
    $route = $request->get_route();
    if (!is_string($route) || $route === '') {
        return false;
    }

    foreach (mrn_rbl_get_post_types() as $post_type) {
        $rest_prefix = '/wp/v2/' . $post_type;
        if (0 === strpos($route, $rest_prefix)) {
            return true;
        }
    }

    return false;
}

/**
 * Restrict reusable block REST routes to authenticated editors.
 *
 * The CPTs keep `show_in_rest` enabled for editor/admin compatibility, but the
 * content should not be anonymously browsable.
 *
 * @param mixed           $result  Existing pre-dispatch result.
 * @param WP_REST_Server  $server  REST server instance.
 * @param WP_REST_Request $request Current request.
 * @return mixed
 */
function mrn_rbl_restrict_rest_access($result, WP_REST_Server $server, WP_REST_Request $request) {
    unset($server);

    if ($result instanceof WP_Error) {
        return $result;
    }

    if (!mrn_rbl_is_library_rest_request($request)) {
        return $result;
    }

    if (current_user_can('edit_posts')) {
        return $result;
    }

    return new WP_Error(
        'rest_forbidden',
        __('Sorry, you are not allowed to access reusable block library content.', 'mrn-rbl'),
        array('status' => rest_authorization_required_code())
    );
}
add_filter('rest_pre_dispatch', 'mrn_rbl_restrict_rest_access', 10, 3);

/**
 * Shared permission callback for reusable block REST routes.
 *
 * @return true|WP_Error
 */
function mrn_rbl_rest_permission_check() {
    if (current_user_can('edit_posts')) {
        return true;
    }

    return new WP_Error(
        'rest_forbidden',
        __('Sorry, you are not allowed to access reusable block library content.', 'mrn-rbl'),
        array('status' => rest_authorization_required_code())
    );
}

/**
 * Replace public read permissions on reusable block REST routes with editor-only access.
 *
 * @param array<string, array<int, array<string, mixed>>> $endpoints
 * @return array<string, array<int, array<string, mixed>>>
 */
function mrn_rbl_lock_rest_endpoints(array $endpoints): array {
    foreach ($endpoints as $route => $handlers) {
        if (!is_string($route) || $route === '' || !is_array($handlers)) {
            continue;
        }

        $matches_library_route = false;
        foreach (mrn_rbl_get_post_types() as $post_type) {
            $rest_prefix = '/wp/v2/' . $post_type;
            if (0 === strpos($route, $rest_prefix)) {
                $matches_library_route = true;
                break;
            }
        }

        if (!$matches_library_route) {
            continue;
        }

        foreach ($handlers as $index => $handler) {
            if (!is_array($handler)) {
                continue;
            }

            $endpoints[$route][$index]['permission_callback'] = 'mrn_rbl_rest_permission_check';
        }
    }

    return $endpoints;
}
add_filter('rest_endpoints', 'mrn_rbl_lock_rest_endpoints', 20);

/**
 * Map post types to render template slugs.
 *
 * @param string $post_type
 * @return string
 */
function mrn_rbl_get_template_slug_for_post_type(string $post_type): string {
    $map = array(
        'mrn_reusable_basic' => 'basic-block',
        'mrn_reusable_cta'   => 'cta',
        'mrn_reusable_list' => 'content-lists',
        'mrn_reusable_faq'   => 'faq',
        'mrn_reusable_grid'  => 'content-grid',
        'mrn_reusable_search' => 'search-form',
    );

    return isset($map[$post_type]) ? $map[$post_type] : 'generic-block';
}

/**
 * Locate a reusable block template, preferring theme overrides.
 *
 * @param string $template_slug
 * @return string
 */
function mrn_rbl_locate_template(string $template_slug): string {
    $template_slug = sanitize_file_name($template_slug);
    $relative_path = 'mrn-blocks/' . $template_slug . '.php';
    $candidates = array(
        trailingslashit(get_stylesheet_directory()) . $relative_path,
    );

    if (get_template_directory() !== get_stylesheet_directory()) {
        $candidates[] = trailingslashit(get_template_directory()) . $relative_path;
    }

    $candidates[] = trailingslashit(mrn_rbl_get_templates_dir()) . $template_slug . '.php';

    $allowed_roots = array(
        wp_normalize_path(trailingslashit(get_stylesheet_directory()) . 'mrn-blocks/'),
        wp_normalize_path(trailingslashit(mrn_rbl_get_templates_dir())),
    );

    if (get_template_directory() !== get_stylesheet_directory()) {
        $allowed_roots[] = wp_normalize_path(trailingslashit(get_template_directory()) . 'mrn-blocks/');
    }

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || $candidate === '') {
            continue;
        }

        $resolved_path = realpath($candidate);
        if (!is_string($resolved_path) || $resolved_path === '') {
            continue;
        }

        $resolved_path = wp_normalize_path($resolved_path);

        foreach ($allowed_roots as $allowed_root) {
            if ($allowed_root !== '' && 0 === strpos($resolved_path, $allowed_root)) {
                return $resolved_path;
            }
        }
    }

    return '';
}

/**
 * Determine whether the current request is allowed to render a reusable block post.
 *
 * Published reusable blocks can render anywhere. Unpublished blocks are restricted
 * to users who can edit the specific block.
 *
 * @param WP_Post $post
 * @return bool
 */
function mrn_rbl_can_render_post(WP_Post $post): bool {
    if (!in_array($post->post_type, mrn_rbl_get_post_types(), true)) {
        return false;
    }

    if ('publish' === $post->post_status) {
        return true;
    }

    return current_user_can('edit_post', $post->ID);
}

/**
 * Determine whether one-time maintenance routines are allowed to run.
 *
 * These routines mutate content and should not run on anonymous front-end traffic.
 *
 * @return bool
 */
function mrn_rbl_can_run_maintenance(): bool {
    if (defined('WP_CLI') && WP_CLI) {
        return true;
    }

    return is_admin() && current_user_can('activate_plugins');
}

/**
 * Build a stable signature for starter-block definitions.
 *
 * @return string
 */
function mrn_rbl_get_starter_seed_signature(): string {
    $signature_source = array();

    foreach (mrn_rbl_get_post_type_definitions() as $post_type => $definition) {
        if (!is_array($definition)) {
            continue;
        }

        $signature_source[$post_type] = array(
            'starter_slug'  => isset($definition['starter_slug']) ? (string) $definition['starter_slug'] : '',
            'starter_title' => isset($definition['starter_title']) ? (string) $definition['starter_title'] : '',
        );
    }

    return md5((string) wp_json_encode($signature_source));
}

/**
 * Resolve a reusable block post by ID or slug.
 *
 * @param int|string $identifier
 * @return WP_Post|null
 */
function mrn_rbl_get_block_post($identifier): ?WP_Post {
    if (is_numeric($identifier)) {
        $post = get_post((int) $identifier);
        return $post instanceof WP_Post && in_array($post->post_type, mrn_rbl_get_post_types(), true) ? $post : null;
    }

    $slug = sanitize_title((string) $identifier);
    if ($slug === '') {
        return null;
    }

    $posts = get_posts(array(
        'post_type'              => mrn_rbl_get_post_types(),
        'name'                   => $slug,
        'post_status'            => array('publish', 'draft', 'private'),
        'posts_per_page'         => 1,
        'suppress_filters'       => true,
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ));

    return isset($posts[0]) && $posts[0] instanceof WP_Post ? $posts[0] : null;
}

/**
 * Build render context for a reusable block post.
 *
 * @param WP_Post              $post
 * @param array<string, mixed> $extra_context
 * @return array<string, mixed>
 */
function mrn_rbl_get_render_context(WP_Post $post, array $extra_context = array()): array {
    $post_type = $post->post_type;
    $context = array(
        'post'          => $post,
        'post_id'       => (int) $post->ID,
        'post_type'     => $post_type,
        'post_name'     => (string) $post->post_name,
        'block_name'    => (string) $post->post_title,
        'template_slug' => mrn_rbl_get_template_slug_for_post_type($post_type),
    );

    if (function_exists('get_fields')) {
        $fields = get_fields($post->ID);
        $context['fields'] = is_array($fields) ? $fields : array();
    } else {
        $context['fields'] = array();
    }

    if ($extra_context !== array()) {
        $context = array_merge($context, $extra_context);
    }

    return $context;
}

/**
 * Render a resolved block context through the matching template.
 *
 * @param array<string, mixed> $context
 * @return string
 */
function mrn_rbl_render_context(array $context): string {
    $template_slug = isset($context['template_slug']) ? (string) $context['template_slug'] : '';
    if ($template_slug === '') {
        return '';
    }

    $template = mrn_rbl_locate_template($template_slug);
    if ($template === '') {
        return '';
    }

    ob_start();
    // nosemgrep: semgrep.php-dynamic-include -- Template path is sanitized and allow-listed by mrn_rbl_locate_template().
    include $template;
    return (string) ob_get_clean();
}

/**
 * Build reusable-block anchor markup for standalone rendering contexts.
 *
 * @param array<string, mixed> $context
 * @return string
 */
function mrn_rbl_get_anchor_markup(array $context): string {
    if (!empty($context['suppress_anchor'])) {
        return '';
    }

    $fields = isset($context['fields']) && is_array($context['fields']) ? $context['fields'] : array();
    $anchor = mrn_rbl_normalize_anchor_id($fields['anchor'] ?? '');

    if ($anchor === '') {
        return '';
    }

    // Keep reusable-block anchors aligned with theme-level anchor de-duplication.
    if (function_exists('mrn_base_stack_get_unique_builder_anchor_id')) {
        $anchor = (string) mrn_base_stack_get_unique_builder_anchor_id($anchor);
        if ($anchor === '') {
            return '';
        }
    }

    return sprintf(
        '<div id="%1$s" class="mrn-reusable-block__anchor" aria-hidden="true"></div>',
        esc_attr($anchor)
    );
}

/**
 * Determine whether a render context is a true reusable-block post instance.
 */
function mrn_rbl_should_apply_motion_contract(array $context): bool {
    return !empty($context['apply_motion_contract']) || (isset($context['post']) && $context['post'] instanceof WP_Post);
}

/**
 * Build a motion contract for reusable-block post renders.
 *
 * @param array<string, mixed> $fields
 * @param array<string, mixed> $context
 * @return array{classes:array<int,string>,attributes:array<string,string>}
 */
function mrn_rbl_get_motion_contract(array $fields, array $context = array()): array {
    if (!mrn_rbl_should_apply_motion_contract($context) || !function_exists('mrn_base_stack_get_motion_contract_for_settings')) {
        return array(
            'classes'    => array(),
            'attributes' => array(),
        );
    }

    return mrn_base_stack_get_motion_contract_for_settings($fields['motion_settings'] ?? array());
}

/**
 * Merge reusable-block HTML attributes while preserving helper behavior when available.
 *
 * @param array<string, string> $base
 * @param array<string, string> $extra
 * @return array<string, string>
 */
function mrn_rbl_merge_attributes(array $base, array $extra): array {
    if (function_exists('mrn_base_stack_merge_builder_attributes')) {
        return mrn_base_stack_merge_builder_attributes($base, $extra);
    }

    return array_merge($base, $extra);
}

/**
 * Convert reusable-block attributes into escaped HTML.
 *
 * @param array<string, string> $attributes
 * @return string
 */
function mrn_rbl_get_html_attributes(array $attributes): string {
    if (function_exists('mrn_base_stack_get_html_attributes')) {
        return mrn_base_stack_get_html_attributes($attributes);
    }

    $chunks = array();

    foreach ($attributes as $name => $value) {
        if (!is_string($name) || '' === $name || !is_scalar($value)) {
            continue;
        }

        $chunks[] = sprintf('%1$s="%2$s"', esc_attr($name), esc_attr((string) $value));
    }

    return implode(' ', $chunks);
}

/**
 * Render arbitrary fields using the template contract for a reusable block type.
 *
 * @param string               $post_type
 * @param array<string, mixed> $fields
 * @param array<string, mixed> $args
 * @return string
 */
function mrn_rbl_render_fields_as_block(string $post_type, array $fields, array $args = array()): string {
    $context = array(
        'post'          => isset($args['post']) && $args['post'] instanceof WP_Post ? $args['post'] : null,
        'post_id'       => isset($args['post_id']) ? (int) $args['post_id'] : 0,
        'post_type'     => $post_type,
        'post_name'     => isset($args['post_name']) ? sanitize_title((string) $args['post_name']) : '',
        'block_name'    => isset($args['block_name']) ? (string) $args['block_name'] : '',
        'template_slug' => mrn_rbl_get_template_slug_for_post_type($post_type),
        'fields'        => $fields,
    );

    return mrn_rbl_render_context($context);
}

/**
 * Render a reusable block post and return HTML.
 *
 * @param int|string|WP_Post $block
 * @return string
 */
function mrn_rbl_render_block($block): string {
    $post = $block instanceof WP_Post ? $block : mrn_rbl_get_block_post($block);
    if (!$post instanceof WP_Post) {
        return '';
    }

    if (!mrn_rbl_can_render_post($post)) {
        return '';
    }

    return mrn_rbl_render_context(mrn_rbl_get_render_context($post));
}

/**
 * Render a reusable block post with additional host context.
 *
 * @param int|string|WP_Post   $block
 * @param array<string, mixed> $extra_context
 * @return string
 */
function mrn_rbl_render_block_with_context($block, array $extra_context = array()): string {
    $post = $block instanceof WP_Post ? $block : mrn_rbl_get_block_post($block);
    if (!$post instanceof WP_Post) {
        return '';
    }

    if (!mrn_rbl_can_render_post($post)) {
        return '';
    }

    return mrn_rbl_render_context(mrn_rbl_get_render_context($post, $extra_context));
}

/**
 * Shortcode helper for rendering reusable blocks before picker UI exists.
 *
 * Usage:
 * [mrn_block slug="reusable-basic-block"]
 * [mrn_block id="123"]
 *
 * @param array<string, string> $atts
 * @return string
 */
function mrn_rbl_shortcode(array $atts): string {
    $atts = shortcode_atts(
        array(
            'id'   => '',
            'slug' => '',
        ),
        $atts,
        'mrn_block'
    );

    if ($atts['id'] !== '') {
        return mrn_rbl_render_block($atts['id']);
    }

    if ($atts['slug'] !== '') {
        return mrn_rbl_render_block($atts['slug']);
    }

    return '';
}
add_shortcode('mrn_block', 'mrn_rbl_shortcode');

/**
 * Get reusable block post type slugs.
 *
 * @return array<int, string>
 */
function mrn_rbl_get_post_types(): array {
    return array_values(array_filter(array_keys(mrn_rbl_get_post_type_definitions()), 'is_string'));
}

/**
 * Remove core and SEO metaboxes that are not useful for reusable blocks.
 */
function mrn_rbl_remove_unneeded_metaboxes(): void {
    $post_types = mrn_rbl_get_post_types();
    $metabox_ids = array(
        'postexcerpt',
        'trackbacksdiv',
        'commentstatusdiv',
        'commentsdiv',
        'slugdiv',
        'authordiv',
        'revisionsdiv',
        'postimagediv',
        'rank_math_metabox',
        'wpseo_meta',
        'aioseo-settings',
        'seopress_content_analysis',
        'seopress_titles',
        'tsf-inpost-box',
    );

    foreach ($post_types as $post_type) {
        foreach ($metabox_ids as $metabox_id) {
            remove_meta_box($metabox_id, $post_type, 'normal');
            remove_meta_box($metabox_id, $post_type, 'side');
            remove_meta_box($metabox_id, $post_type, 'advanced');
        }
    }
}
add_action('add_meta_boxes', 'mrn_rbl_remove_unneeded_metaboxes', 100);

/**
 * Register the shared admin menu for the library.
 */
function mrn_rbl_register_admin_menu(): void {
    $parent_slug = mrn_rbl_get_library_menu_slug();
    $visible_post_types = array();

    foreach (mrn_rbl_get_post_type_definitions() as $post_type => $definition) {
        if (!is_string($post_type) || $post_type === '' || !is_array($definition)) {
            continue;
        }

        if (!mrn_rbl_is_post_type_visible($post_type)) {
            continue;
        }

        $visible_post_types[$post_type] = $definition;
    }

    if ($visible_post_types === array()) {
        return;
    }

    add_menu_page(
        'Reusable Block Library',
        'Reusable Block Library',
        'edit_posts',
        $parent_slug,
        'mrn_rbl_render_library_overview',
        'dashicons-screenoptions',
        26
    );

    foreach ($visible_post_types as $post_type => $definition) {
        $plural        = isset($definition['plural']) ? (string) $definition['plural'] : 'Blocks';
        $list_label    = isset($definition['list_label']) ? (string) $definition['list_label'] : $plural;
        $add_new_label = isset($definition['add_new_label']) ? (string) $definition['add_new_label'] : 'Add New';

        add_submenu_page(
            $parent_slug,
            $plural,
            $list_label,
            'edit_posts',
            'edit.php?post_type=' . $post_type
        );

        add_submenu_page(
            $parent_slug,
            $add_new_label,
            $add_new_label,
            'edit_posts',
            'post-new.php?post_type=' . $post_type
        );
    }
}
add_action('admin_menu', 'mrn_rbl_register_admin_menu', 9);

/**
 * Render the top-level library overview page.
 */
function mrn_rbl_render_library_overview(): void {
    $definitions = mrn_rbl_get_post_type_definitions();
    ?>
    <div class="wrap">
        <h1>Reusable Block Library</h1>
        <p>Manage your reusable block types from one place.</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;max-width:900px;margin-top:20px;">
            <?php foreach ($definitions as $post_type => $definition) : ?>
                <?php
                if (!is_string($post_type) || $post_type === '' || !is_array($definition)) {
                    continue;
                }

                $singular    = isset($definition['singular']) ? (string) $definition['singular'] : 'Block';
                $plural      = isset($definition['plural']) ? (string) $definition['plural'] : 'Blocks';
                $count       = wp_count_posts($post_type);
                $draft_count = isset($count->draft) ? (int) $count->draft : 0;
                $all_count   = 0;

                if ($count instanceof stdClass) {
                    $counted_statuses = array('publish', 'draft', 'pending', 'private', 'future');

                    foreach ($counted_statuses as $status) {
                        if (isset($count->{$status})) {
                            $all_count += (int) $count->{$status};
                        }
                    }
                }

                $list_url = admin_url('edit.php?post_type=' . $post_type);
                $new_url  = admin_url('post-new.php?post_type=' . $post_type);
                ?>
                <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;display:flex;flex-direction:column;gap:12px;">
                    <h2 style="margin-top:0;"><?php echo esc_html($plural); ?></h2>
                    <p style="margin-bottom:8px;">Total items: <strong><?php echo esc_html((string) $all_count); ?></strong></p>
                    <p style="margin-top:0;">Drafts: <strong><?php echo esc_html((string) $draft_count); ?></strong></p>
                    <div style="display:flex;flex-wrap:nowrap;gap:8px;align-items:center;">
                        <a class="button button-primary" style="white-space:nowrap;" href="<?php echo esc_url($list_url); ?>">View <?php echo esc_html($plural); ?></a>
                        <a class="button" style="white-space:nowrap;" href="<?php echo esc_url($new_url); ?>">Add <?php echo esc_html($singular); ?></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/**
 * Keep the shared menu highlighted while editing library CPTs.
 *
 * @param mixed $parent_file
 * @return string
 */
function mrn_rbl_filter_parent_file($parent_file): string {
    $parent_file = is_string($parent_file) ? $parent_file : '';

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen instanceof WP_Screen) {
        return $parent_file;
    }

    if (!array_key_exists($screen->post_type, mrn_rbl_get_post_type_definitions())) {
        return $parent_file;
    }

    return mrn_rbl_get_library_menu_slug();
}
add_filter('parent_file', 'mrn_rbl_filter_parent_file');

/**
 * Keep the correct submenu highlighted while editing library CPTs.
 *
 * @param mixed $submenu_file
 * @return string
 */
function mrn_rbl_filter_submenu_file($submenu_file): string {
    $submenu_file = is_string($submenu_file) ? $submenu_file : '';

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen instanceof WP_Screen) {
        return $submenu_file;
    }

    $post_type = $screen->post_type;
    if (!array_key_exists($post_type, mrn_rbl_get_post_type_definitions())) {
        return $submenu_file;
    }

    $current_action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin UI state for submenu highlighting.

    if ($screen->base === 'post' && 'add' === $current_action) {
        return 'post-new.php?post_type=' . $post_type;
    }

    if ($screen->base === 'post-new') {
        return 'post-new.php?post_type=' . $post_type;
    }

    return 'edit.php?post_type=' . $post_type;
}
add_filter('submenu_file', 'mrn_rbl_filter_submenu_file');

/**
 * Ensure reusable block edit screens load the core editor runtime for ACF
 * WYSIWYG fields.
 *
 * @param string $hook_suffix Current admin hook suffix.
 * @return void
 */
function mrn_rbl_enqueue_editor_assets(string $hook_suffix): void {
    if (!in_array($hook_suffix, array('post.php', 'post-new.php'), true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen instanceof WP_Screen || !mrn_rbl_is_reusable_post_type((string) $screen->post_type)) {
        return;
    }

    if (function_exists('wp_enqueue_editor')) {
        wp_enqueue_editor();
    }
}
add_action('admin_enqueue_scripts', 'mrn_rbl_enqueue_editor_assets');

/**
 * Build reusable choices from configured Site Colors.
 *
 * @return array<string, string>
 */
function mrn_rbl_get_site_color_choices(): array {
    $choices = array(
        '' => 'Select a Site Color',
    );

    if (!function_exists('mrn_site_colors_get_all')) {
        return $choices;
    }

    foreach (mrn_site_colors_get_all() as $row) {
        $slug  = isset($row['slug']) ? (string) $row['slug'] : '';
        $name  = isset($row['name']) ? (string) $row['name'] : '';
        $value = isset($row['value']) ? (string) $row['value'] : '';

        if ($slug === '' || $name === '') {
            continue;
        }

        $choices[$slug] = $value !== '' ? $name . ' (' . $value . ')' : $name;
    }

    return $choices;
}

/**
 * Shared heading tag choices for blocks that output a main heading.
 *
 * @return array<string, string>
 */
function mrn_rbl_get_heading_tag_choices(): array {
    return array(
        'h1'   => 'H1',
        'h2'   => 'H2',
        'h3'   => 'H3',
        'h4'   => 'H4',
        'h5'   => 'H5',
        'h6'   => 'H6',
        'p'    => 'Paragraph',
        'div'  => 'Div',
        'span' => 'Span',
    );
}

/**
 * Shared label tag choices for reusable blocks with a label field.
 *
 * @return array<string, string>
 */
function mrn_rbl_get_label_tag_choices(): array {
    return mrn_rbl_get_heading_tag_choices();
}

/**
 * Normalize a requested text tag to the supported tag set.
 */
function mrn_rbl_normalize_text_tag($value, string $default = 'p'): string {
    $tag          = sanitize_key((string) $value);
    $default_tag  = sanitize_key($default);
    $allowed_tags = array_keys(mrn_rbl_get_heading_tag_choices());

    if (!in_array($default_tag, $allowed_tags, true)) {
        $default_tag = 'p';
    }

    if (!in_array($tag, $allowed_tags, true)) {
        $tag = $default_tag;
    }

    return $tag;
}

/**
 * Build a standard inline-HTML-enabled text field definition.
 *
 * @return array<string, mixed>
 */
function mrn_rbl_get_inline_text_field(string $key, string $label, string $name, string $instructions = 'Limited inline HTML allowed: span, strong, em, br.', string $width = '75'): array {
    return array(
        'key'          => $key,
        'label'        => $label,
        'name'         => $name,
        'type'         => 'text',
        'instructions' => $instructions,
        'wrapper'      => array(
            'width' => $width,
        ),
    );
}

/**
 * Build a standard anchor field definition.
 *
 * @return array<string, mixed>
 */
function mrn_rbl_get_anchor_field(string $key, string $name = 'anchor', string $label = 'Anchor ID'): array {
    return array(
        'key'          => $key,
        'label'        => $label,
        'name'         => $name,
        'type'         => 'text',
        'instructions' => 'Optional anchor slug for one-page links. Enter the value without #.',
        'wrapper'      => array(
            'width' => '50',
        ),
    );
}

/**
 * Shared section-width choices for reusable block contracts.
 *
 * @return array<string, string>
 */
function mrn_rbl_get_section_width_choices(): array {
    if (function_exists('mrn_base_stack_get_section_width_choices')) {
        return mrn_base_stack_get_section_width_choices();
    }

    return array(
        'content'    => 'Content',
        'wide'       => 'Wide',
        'full-width' => 'Full Width',
    );
}

/**
 * Build a reusable-block section-width field definition.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $default_width Default width choice.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_rbl_get_section_width_field(string $key, string $name = 'section_width', string $default_width = 'wide', string $label = 'Section Width (Content)'): array {
    return array(
        'key'           => $key,
        'label'         => $label,
        'name'          => $name,
        'type'          => 'select',
        'choices'       => mrn_rbl_get_section_width_choices(),
        'default_value' => $default_width,
        'ui'            => 1,
        'wrapper'       => array(
            'width' => '50',
        ),
    );
}

/**
 * Build a reusable-block sub-content width field definition.
 *
 * @param string $key Unique ACF field key.
 * @param string $name Field name.
 * @param string $default_width Default width choice.
 * @param string $label Field label.
 * @return array<string, mixed>
 */
function mrn_rbl_get_sub_content_width_field(string $key, string $name = 'sub_content_width', string $default_width = 'content', string $label = 'Section Width (Sub-content)'): array {
    return array(
        'key'           => $key,
        'label'         => $label,
        'name'          => $name,
        'type'          => 'select',
        'choices'       => mrn_rbl_get_section_width_choices(),
        'default_value' => $default_width,
        'ui'            => 1,
        'wrapper'       => array(
            'width' => '50',
        ),
    );
}

/**
 * Build the reusable-block Effects tab field definition.
 *
 * @return array<string, mixed>
 */
function mrn_rbl_get_effects_tab_field(string $key, string $label = 'Effects'): array {
    if (function_exists('mrn_base_stack_get_effects_tab_field')) {
        return mrn_base_stack_get_effects_tab_field($key, $label);
    }

    return array(
        'key'       => $key,
        'label'     => $label,
        'name'      => '',
        'type'      => 'tab',
        'placement' => 'top',
        'endpoint'  => 0,
    );
}

/**
 * Build the reusable-block motion group field definition.
 *
 * @return array<string, mixed>
 */
function mrn_rbl_get_motion_group_field(string $key, string $name = 'motion_settings', string $label = 'Motion Effects'): array {
    if (function_exists('mrn_base_stack_get_motion_group_field')) {
        return mrn_base_stack_get_motion_group_field($key, $name, $label);
    }

    return array(
        'key'        => $key,
        'label'      => $label,
        'name'       => $name,
        'type'       => 'group',
        'layout'     => 'block',
        'sub_fields' => array(),
    );
}

/**
 * Determine whether a field list already includes reusable-block motion settings.
 *
 * @param array<int, mixed> $fields
 * @return bool
 */
function mrn_rbl_fields_have_motion_group(array $fields): bool {
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        if (isset($field['name']) && 'motion_settings' === (string) $field['name']) {
            return true;
        }
    }

    return false;
}

/**
 * Determine whether a field list already includes top-level tabs.
 *
 * @param array<int, mixed> $fields
 * @return bool
 */
function mrn_rbl_fields_have_tabs(array $fields): bool {
    foreach ($fields as $field) {
        if (is_array($field) && isset($field['type']) && 'tab' === (string) $field['type']) {
            return true;
        }
    }

    return false;
}

/**
 * Build an editor-only internal name field for reusable layouts/blocks.
 *
 * @param string $key Unique ACF field key.
 * @return array<string, mixed>
 */
function mrn_rbl_get_internal_layout_name_field(string $key): array {
    return array(
        'key'          => $key,
        'label'        => 'Name (admin use only)',
        'name'         => 'internal_name',
        'type'         => 'text',
        'instructions' => 'Optional editor-only row name used in the layout list. This is not rendered on the front end.',
        'wrapper'      => array(
            'width' => '50',
        ),
    );
}

/**
 * Normalize one field label to the shared primary-layout contract.
 *
 * @param array<string, mixed> $field Field definition.
 * @return array<string, mixed>
 */
function mrn_rbl_normalize_primary_layout_field(array $field): array {
    $field_type = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
    $field_name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
    $is_tag_chooser_field = ('select' === $field_type) && (1 === preg_match('/(^|_)(label|heading|subheading|text_field)_tag$/', $field_name));

    if ('internal_name' === $field_name) {
        $field['label'] = 'Name (admin use only)';
    }

    if ('label' === $field_name && 'text' === $field_type) {
        $field['label'] = 'Label';
        if (!isset($field['wrapper']) || !is_array($field['wrapper'])) {
            $field['wrapper'] = array();
        }
        $field['wrapper']['width'] = '75';
    }

    if ($is_tag_chooser_field) {
        $field['label'] = 'Tag';
        if (!isset($field['wrapper']) || !is_array($field['wrapper'])) {
            $field['wrapper'] = array();
        }
        $field['wrapper']['width'] = '25';
    }

    if ('heading' === $field_name && 'text' === $field_type) {
        $field['label'] = 'Heading';
        if (!isset($field['wrapper']) || !is_array($field['wrapper'])) {
            $field['wrapper'] = array();
        }
        $field['wrapper']['width'] = '75';
    }

    if ('subheading' === $field_name && 'text' === $field_type) {
        $field['label'] = 'Subheading';
        if (!isset($field['wrapper']) || !is_array($field['wrapper'])) {
            $field['wrapper'] = array();
        }
        $field['wrapper']['width'] = '75';
    }

    if ('wysiwyg' === $field_type && in_array($field_name, array('content', 'body_text', 'intro'), true)) {
        $field['label'] = 'Text';
    }

    if ('repeater' === $field_type && 'links' !== $field_name) {
        $field['layout'] = 'block';
    }

    if ('links' === $field_name && 'repeater' === $field_type) {
        $field['label'] = 'Link repeater';
    }

    if ('background_color' === $field_name && 'select' === $field_type) {
        $field['label'] = 'Background Color';
    }

    if ('anchor' === $field_name) {
        $field['label'] = 'Anchor ID';
    }

    if ('section_width' === $field_name && 'select' === $field_type) {
        $field['label'] = 'Section Width (Content)';
    }

    if ('sub_content_width' === $field_name && 'select' === $field_type) {
        $field['label'] = 'Section Width (Sub-content)';
    }

    if ('bottom_accent' === $field_name && 'true_false' === $field_type) {
        $field['label'] = 'Accent';
    }

    if ('bottom_accent_style' === $field_name && 'select' === $field_type) {
        $field['label'] = 'Bottom accent style';
    }

    return $field;
}

/**
 * Keep Label/Heading/Subheading text fields at 75% when paired with *_tag fields.
 *
 * This supports nested repeater naming patterns like `item_label` + `item_label_tag`.
 *
 * @param array<int, mixed> $fields
 * @return array<int, mixed>
 */
function mrn_rbl_apply_tag_field_column_layout(array $fields): array {
    $text_field_indexes_by_name = array();

    foreach ($fields as $index => $field) {
        if (!is_array($field)) {
            continue;
        }

        $field_type = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
        $field_name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';

        if ('text' !== $field_type || '' === $field_name) {
            continue;
        }

        if (!isset($text_field_indexes_by_name[$field_name])) {
            $text_field_indexes_by_name[$field_name] = array();
        }

        $text_field_indexes_by_name[$field_name][] = $index;
    }

    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $field_type = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
        $field_name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';

        if ('select' !== $field_type || '' === $field_name) {
            continue;
        }

        if (1 !== preg_match('/^(.*?)(label|heading|subheading)_tag$/', $field_name, $matches)) {
            continue;
        }

        $companion_name = sanitize_key($matches[1] . $matches[2]);
        if ('' === $companion_name || !isset($text_field_indexes_by_name[$companion_name])) {
            continue;
        }

        foreach ($text_field_indexes_by_name[$companion_name] as $text_index) {
            if (!isset($fields[$text_index]) || !is_array($fields[$text_index])) {
                continue;
            }

            if (!isset($fields[$text_index]['wrapper']) || !is_array($fields[$text_index]['wrapper'])) {
                $fields[$text_index]['wrapper'] = array();
            }

            $fields[$text_index]['wrapper']['width'] = '75';
        }
    }

    return $fields;
}

/**
 * Ensure non-link repeater sub-fields include a Subheading + Tag pair.
 *
 * When a repeater row already follows the heading/tag pattern but is missing
 * subheading fields, inject them in-place without adding internal-name fields.
 *
 * @param array<int, mixed> $fields Repeater sub-fields.
 * @param string            $repeater_key Parent repeater field key.
 * @return array<int, mixed>
 */
function mrn_rbl_ensure_repeater_subheading_contract(array $fields, string $repeater_key = ''): array {
    foreach ($fields as $index => $field) {
        if (!is_array($field)) {
            continue;
        }

        $field_name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
        if ('' === $field_name) {
            continue;
        }

        $is_subsubheading_field = false;
        if (strlen($field_name) >= 13 && 'subsubheading' === substr($field_name, -13)) {
            $is_subsubheading_field = true;
        }
        if (strlen($field_name) >= 17 && 'subsubheading_tag' === substr($field_name, -17)) {
            $is_subsubheading_field = true;
        }

        if ($is_subsubheading_field) {
            unset($fields[$index]);
        }
    }
    $fields = array_values($fields);

    $heading_index        = null;
    $heading_tag_index    = null;
    $subheading_index     = null;
    $subheading_tag_index = null;
    $prefix               = null;
    $heading_key          = '';

    foreach ($fields as $index => $field) {
        if (!is_array($field)) {
            continue;
        }

        $field_type = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
        $field_name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
        $field_key  = isset($field['key']) ? trim((string) $field['key']) : '';

        if ('' === $field_name) {
            continue;
        }

        $is_subheading_seed = strlen($field_name) >= 10 && 'subheading' === substr($field_name, -10);
        if (null === $heading_index && 'text' === $field_type && !$is_subheading_seed && 1 === preg_match('/^(.*)heading$/', $field_name, $heading_match)) {
            $heading_index = $index;
            $prefix        = $heading_match[1];
            $heading_key   = $field_key;
            continue;
        }

        if (null === $prefix) {
            continue;
        }

        if (null === $heading_tag_index && 'select' === $field_type && in_array($field_name, array($prefix . 'heading_tag', $prefix . 'text_field_tag'), true)) {
            $heading_tag_index = $index;
            continue;
        }

        if (null === $subheading_index && 'text' === $field_type && $field_name === $prefix . 'subheading') {
            $subheading_index = $index;
            continue;
        }

        if (null === $subheading_tag_index && 'select' === $field_type && $field_name === $prefix . 'subheading_tag') {
            $subheading_tag_index = $index;
        }
    }

    if (null === $heading_index || null === $prefix) {
        $fallback_anchor_index = null;
        $fallback_anchor_name  = '';
        $fallback_anchor_key   = '';
        $fallback_prefix       = '';

        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            $field_type = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
            $field_name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
            $field_key  = isset($field['key']) ? trim((string) $field['key']) : '';

            if ('' === $field_name) {
                continue;
            }

            if (null === $fallback_anchor_index && 'tab' !== $field_type && 'accordion' !== $field_type && 'links' !== $field_name) {
                $fallback_anchor_index = $index;
                $fallback_anchor_name  = $field_name;
                $fallback_anchor_key   = $field_key;
            }

            if ('' === $fallback_prefix && 1 === preg_match('/^(.*?)(heading|label|text|content)$/', $field_name, $fallback_match)) {
                $fallback_prefix = $fallback_match[1];
            }
        }

        if (null === $fallback_anchor_index) {
            return $fields;
        }

        $heading_index = $fallback_anchor_index;
        $heading_key   = $fallback_anchor_key;
        $prefix        = $fallback_prefix;

        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            $field_type = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
            $field_name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';

            if ('' === $field_name) {
                continue;
            }

            if (null === $heading_tag_index && 'select' === $field_type && in_array($field_name, array($prefix . 'heading_tag', $prefix . 'text_field_tag'), true)) {
                $heading_tag_index = $index;
                continue;
            }

            if (null === $subheading_index && 'text' === $field_type && $field_name === $prefix . 'subheading') {
                $subheading_index = $index;
                continue;
            }

            if (null === $subheading_tag_index && 'select' === $field_type && $field_name === $prefix . 'subheading_tag') {
                $subheading_tag_index = $index;
            }
        }
    }

    $needs_subheading     = null === $subheading_index;
    $needs_subheading_tag = null === $subheading_tag_index;

    if (!$needs_subheading && !$needs_subheading_tag) {
        return $fields;
    }

    $subheading_name = $prefix . 'subheading';
    $tag_name        = $prefix . 'subheading_tag';
    $key_seed        = '' !== $heading_key ? sanitize_key($heading_key) : sanitize_key($repeater_key);
    if ('' === $key_seed) {
        $key_seed = 'field_mrn_rbl_subfield_heading';
    }

    $subheading_key     = $key_seed . '_subheading';
    $subheading_tag_key = $key_seed . '_subheading_tag';
    $new_fields         = array();

    if ($needs_subheading) {
        $new_fields[] = mrn_rbl_get_inline_text_field($subheading_key, 'Subheading', $subheading_name);
    }

    if ($needs_subheading_tag) {
        $new_fields[] = mrn_rbl_get_text_tag_field($subheading_tag_key, 'Tag', $tag_name, 'p');
    }

    if (empty($new_fields)) {
        return $fields;
    }

    if ($needs_subheading && !$needs_subheading_tag) {
        $insert_at = null !== $subheading_tag_index ? $subheading_tag_index : (null !== $heading_tag_index ? $heading_tag_index + 1 : $heading_index + 1);
        array_splice($fields, $insert_at, 0, array($new_fields[0]));
        return $fields;
    }

    if (!$needs_subheading && $needs_subheading_tag) {
        $insert_at = null !== $subheading_index ? $subheading_index + 1 : (null !== $heading_tag_index ? $heading_tag_index + 1 : $heading_index + 1);
        array_splice($fields, $insert_at, 0, array($new_fields[0]));
        return $fields;
    }

    $insert_at = null !== $heading_tag_index ? $heading_tag_index + 1 : $heading_index + 1;
    array_splice($fields, $insert_at, 0, $new_fields);

    return $fields;
}

/**
 * Check whether a repeater should receive the shared item-level contract tabs.
 *
 * @param string $repeater_name Repeater field name.
 * @return bool
 */
function mrn_rbl_repeater_uses_primary_item_contract(string $repeater_name): bool {
    $repeater_name = sanitize_key($repeater_name);

    return in_array(
        $repeater_name,
        array(
            'grid_items',
            'card_items',
            'showcase_items',
            'slider_items',
            'tabs',
            'logo_items',
        ),
        true
    );
}

/**
 * Resolve the functionality group for a repeater config field.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return string
 */
function mrn_rbl_get_repeater_config_field_group_key(array $field): string {
    $field_name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';

    if ('' === $field_name) {
        return '';
    }

    if (0 === strpos($field_name, 'link_icon_')) {
        return 'icons';
    }

    if (in_array($field_name, array('is_button', 'target', 'download'), true)) {
        return 'behavior';
    }

    if (in_array($field_name, array('rel', 'title_attribute', 'hreflang', 'media'), true)) {
        return 'attributes';
    }

    if (in_array($field_name, array('css_classes', 'background_color'), true)) {
        return 'appearance';
    }

    return 'advanced';
}

/**
 * Group repeater config controls by functionality within the Configs tab.
 *
 * @param array<int, mixed> $fields Repeater sub-fields.
 * @param string            $key_seed Repeater key seed.
 * @return array<int, mixed>
 */
function mrn_rbl_group_repeater_config_fields_by_functionality(array $fields, string $key_seed): array {
    $config_tab_index = null;
    $next_tab_index   = null;
    $total_fields     = count($fields);

    foreach ($fields as $index => $field) {
        if (!is_array($field)) {
            continue;
        }

        $field_type  = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
        $field_label = isset($field['label']) ? sanitize_title((string) $field['label']) : '';

        if ('tab' !== $field_type) {
            continue;
        }

        if (null === $config_tab_index && 'configs' === $field_label) {
            $config_tab_index = $index;
            continue;
        }

        if (null !== $config_tab_index && $index > $config_tab_index) {
            $next_tab_index = $index;
            break;
        }
    }

    if (null === $config_tab_index) {
        return $fields;
    }

    $segment_start = $config_tab_index + 1;
    $segment_end   = null !== $next_tab_index ? $next_tab_index : $total_fields;
    $segment_len   = max(0, $segment_end - $segment_start);

    if ($segment_len < 1) {
        return $fields;
    }

    $config_fields = array_slice($fields, $segment_start, $segment_len);
    $group_prefix  = sanitize_key($key_seed) . '_cfg_group_';
    $sanitized     = array();

    foreach ($config_fields as $field) {
        if (!is_array($field)) {
            $sanitized[] = $field;
            continue;
        }

        $field_key  = isset($field['key']) ? sanitize_key((string) $field['key']) : '';
        $field_type = isset($field['type']) ? sanitize_key((string) $field['type']) : '';

        if ('accordion' === $field_type && '' !== $field_key && 0 === strpos($field_key, $group_prefix)) {
            continue;
        }

        $sanitized[] = $field;
    }

    $group_order = array(
        'behavior'   => 'Link behavior',
        'attributes' => 'Link attributes',
        'icons'      => 'Icon settings',
        'appearance' => 'Appearance',
        'advanced'   => 'Additional settings',
    );
    $grouped     = array();

    foreach (array_keys($group_order) as $group_key) {
        $grouped[$group_key] = array();
    }

    foreach ($sanitized as $field) {
        if (!is_array($field)) {
            $grouped['advanced'][] = $field;
            continue;
        }

        $field_type = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
        if ('tab' === $field_type) {
            continue;
        }

        $group_key = mrn_rbl_get_repeater_config_field_group_key($field);
        if ('' === $group_key || !isset($grouped[$group_key])) {
            $group_key = 'advanced';
        }

        $grouped[$group_key][] = $field;
    }

    $has_group_content = false;
    foreach ($grouped as $group_fields) {
        if (!empty($group_fields)) {
            $has_group_content = true;
            break;
        }
    }

    if (!$has_group_content) {
        return $fields;
    }

    $grouped_segment = array();
    $is_first_group  = true;
    foreach ($group_order as $group_key => $group_label) {
        $group_fields = $grouped[$group_key];
        if (empty($group_fields)) {
            continue;
        }

        $grouped_segment[] = array(
            'key'          => $group_prefix . $group_key,
            'label'        => $group_label,
            'name'         => '',
            'type'         => 'accordion',
            'open'         => $is_first_group ? 1 : 0,
            'multi_expand' => 1,
            'endpoint'     => 0,
        );

        foreach ($group_fields as $group_field) {
            $grouped_segment[] = $group_field;
        }

        $is_first_group = false;
    }

    $grouped_segment[] = array(
        'key'          => $group_prefix . 'end',
        'label'        => '',
        'name'         => '',
        'type'         => 'accordion',
        'endpoint'     => 1,
        'multi_expand' => 1,
    );

    array_splice($fields, $segment_start, $segment_len, $grouped_segment);

    return $fields;
}

/**
 * Resolve the functionality group for a main-row config field.
 *
 * @param array<string, mixed> $field ACF field definition.
 * @return string
 */
function mrn_rbl_get_main_config_field_group_key(array $field): string {
    $field_name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
    $field_type = isset($field['type']) ? sanitize_key((string) $field['type']) : '';

    if ('' === $field_name) {
        return '';
    }

    if (in_array($field_name, array('section_width', 'sub_content_width'), true)) {
        return 'appearance';
    }

    if (in_array($field_name, array('anchor', 'anchor_id'), true)) {
        return 'layout';
    }

    if (in_array($field_name, array('background_color', 'bg_color'), true) || 0 === strpos($field_name, 'background_')) {
        return 'appearance';
    }

    if (in_array($field_name, array('accent', 'bottom_accent', 'bottom_accent_style'), true) || 0 === strpos($field_name, 'accent_')) {
        return 'accent';
    }

    if (0 === strpos($field_name, 'link_') || in_array($field_name, array('is_button', 'css_classes', 'target', 'rel', 'title_attribute', 'download', 'hreflang', 'media'), true)) {
        return 'links';
    }

    if (
        false !== strpos($field_name, 'column')
        || false !== strpos($field_name, 'ratio')
        || false !== strpos($field_name, 'orientation')
        || false !== strpos($field_name, 'autoplay')
        || false !== strpos($field_name, 'delay')
        || false !== strpos($field_name, 'time_on_slide')
        || false !== strpos($field_name, 'hover')
        || false !== strpos($field_name, 'stagger')
        || false !== strpos($field_name, 'display_mode')
        || false !== strpos($field_name, 'equal')
        || false !== strpos($field_name, 'full')
        || false !== strpos($field_name, 'position')
        || false !== strpos($field_name, 'size')
        || false !== strpos($field_name, 'alignment')
        || false !== strpos($field_name, 'per_page')
        || 0 === strpos($field_name, 'show_')
    ) {
        return 'layout';
    }

    if (in_array($field_type, array('true_false', 'select', 'number', 'range', 'radio', 'button_group'), true)) {
        return 'layout';
    }

    return 'advanced';
}

/**
 * Group main-row Configs controls by functionality with collapsed accordions.
 *
 * @param array<int, mixed> $fields Layout/main field definitions.
 * @param string            $key_seed Optional key seed for generated accordion keys.
 * @return array<int, mixed>
 */
function mrn_rbl_group_main_config_fields_by_functionality(array $fields, string $key_seed = ''): array {
    $config_tab_index = null;
    $next_tab_index   = null;
    $total_fields     = count($fields);
    $seed             = sanitize_key($key_seed);

    foreach ($fields as $index => $field) {
        if (!is_array($field)) {
            continue;
        }

        $field_type  = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
        $field_label = isset($field['label']) ? sanitize_title((string) $field['label']) : '';
        $field_key   = isset($field['key']) ? sanitize_key((string) $field['key']) : '';

        if ('' === $seed && '' !== $field_key) {
            $seed = $field_key;
        }

        if ('tab' !== $field_type) {
            continue;
        }

        if (null === $config_tab_index && 'configs' === $field_label) {
            $config_tab_index = $index;

            if ('' !== $field_key) {
                $seed = $field_key;
            }
            continue;
        }

        if (null !== $config_tab_index && $index > $config_tab_index) {
            $next_tab_index = $index;
            break;
        }
    }

    if (null === $config_tab_index) {
        return $fields;
    }

    if ('' === $seed) {
        $seed = 'field_mrn_rbl_layout_config';
    }

    $segment_start = $config_tab_index + 1;
    $segment_end   = null !== $next_tab_index ? $next_tab_index : $total_fields;
    $segment_len   = max(0, $segment_end - $segment_start);

    if ($segment_len < 1) {
        return $fields;
    }

    $config_fields = array_slice($fields, $segment_start, $segment_len);
    $group_prefix  = $seed . '_cfg_main_group_';
    $sanitized     = array();

    foreach ($config_fields as $field) {
        if (!is_array($field)) {
            $sanitized[] = $field;
            continue;
        }

        $field_key  = isset($field['key']) ? sanitize_key((string) $field['key']) : '';
        $field_type = isset($field['type']) ? sanitize_key((string) $field['type']) : '';

        if ('accordion' === $field_type && '' !== $field_key && 0 === strpos($field_key, $group_prefix)) {
            continue;
        }

        $sanitized[] = $field;
    }

    $group_order = array(
        'layout'     => 'Basic Setting',
        'appearance' => 'Appearance',
        'accent'     => 'Accent settings',
        'links'      => 'Link settings',
        'advanced'   => 'Additional settings',
    );
    $grouped     = array();

    foreach (array_keys($group_order) as $group_key) {
        $grouped[$group_key] = array();
    }

    foreach ($sanitized as $field) {
        if (!is_array($field)) {
            $grouped['advanced'][] = $field;
            continue;
        }

        $field_type = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
        if (in_array($field_type, array('tab', 'accordion'), true)) {
            continue;
        }

        $group_key = mrn_rbl_get_main_config_field_group_key($field);
        if ('' === $group_key || !isset($grouped[$group_key])) {
            $group_key = 'advanced';
        }

        $grouped[$group_key][] = $field;
    }

    $has_group_content = false;
    foreach ($grouped as $group_fields) {
        if (!empty($group_fields)) {
            $has_group_content = true;
            break;
        }
    }

    if (!$has_group_content) {
        return $fields;
    }

    $grouped_segment = array();
    foreach ($group_order as $group_key => $group_label) {
        $group_fields = $grouped[$group_key];
        if (empty($group_fields)) {
            continue;
        }

        $grouped_segment[] = array(
            'key'          => $group_prefix . $group_key,
            'label'        => $group_label,
            'name'         => '',
            'type'         => 'accordion',
            'open'         => 0,
            'multi_expand' => 1,
            'endpoint'     => 0,
        );

        foreach ($group_fields as $group_field) {
            $grouped_segment[] = $group_field;
        }
    }

    $grouped_segment[] = array(
        'key'          => $group_prefix . 'end',
        'label'        => '',
        'name'         => '',
        'type'         => 'accordion',
        'endpoint'     => 1,
        'multi_expand' => 1,
    );

    array_splice($fields, $segment_start, $segment_len, $grouped_segment);

    return $fields;
}

/**
 * Ensure target repeater items use shared Content|Configs tabs and config controls.
 *
 * @param array<int, mixed> $fields Repeater sub-fields.
 * @param string            $repeater_name Repeater field name.
 * @param string            $repeater_key Repeater field key.
 * @return array<int, mixed>
 */
function mrn_rbl_apply_repeater_item_tabs_and_config_contract(array $fields, string $repeater_name, string $repeater_key = ''): array {
    if (!mrn_rbl_repeater_uses_primary_item_contract($repeater_name)) {
        return $fields;
    }

    $content_tab_index     = null;
    $link_tab_index        = null;
    $config_tab_index      = null;
    $has_background_color  = false;

    foreach ($fields as $index => $field) {
        if (!is_array($field)) {
            continue;
        }

        $field_type  = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
        $field_name  = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
        $field_label = isset($field['label']) ? sanitize_title((string) $field['label']) : '';

        if ('tab' === $field_type) {
            if (null === $content_tab_index && 'content' === $field_label) {
                $content_tab_index = $index;
            }

            if (null === $link_tab_index && 'link' === $field_label) {
                $link_tab_index = $index;
            }

            if (null === $config_tab_index && 'configs' === $field_label) {
                $config_tab_index = $index;
            }

            continue;
        }

        if ('background_color' === $field_name && 'select' === $field_type) {
            $has_background_color = true;
            continue;
        }

    }

    $key_seed = sanitize_key($repeater_key);
    if ('' === $key_seed) {
        $key_seed = 'field_mrn_rbl_repeater_item';
    }

    if (null === $content_tab_index) {
        array_unshift($fields, array(
            'key'       => $key_seed . '_content_tab',
            'label'     => 'Content',
            'name'      => '',
            'type'      => 'tab',
            'placement' => 'top',
            'endpoint'  => 0,
        ));
    }

    /*
     * Repeater-item contracts use top-level Content|Configs tabs.
     * Keep link controls in the shared flow without exposing Link as a peer tab.
     */
    if (null !== $link_tab_index) {
        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            $field_type  = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
            $field_label = isset($field['label']) ? sanitize_title((string) $field['label']) : '';

            if ('tab' === $field_type && 'link' === $field_label) {
                array_splice($fields, $index, 1);
                break;
            }
        }
    }

    $config_tab_index = null;
    foreach ($fields as $index => $field) {
        if (!is_array($field)) {
            continue;
        }

        $field_type  = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
        $field_label = isset($field['label']) ? sanitize_title((string) $field['label']) : '';

        if ('tab' === $field_type && 'configs' === $field_label) {
            $config_tab_index = $index;
            break;
        }
    }

    if (null === $config_tab_index) {
        $fields[] = array(
            'key'       => $key_seed . '_config_tab',
            'label'     => 'Configs',
            'name'      => '',
            'type'      => 'tab',
            'placement' => 'top',
            'endpoint'  => 0,
        );
    }

    if (!$has_background_color) {
        $fields[] = array(
            'key'          => $key_seed . '_background_color',
            'label'        => 'Background Color',
            'name'         => 'background_color',
            'type'         => 'select',
            'choices'      => mrn_rbl_get_site_color_choices(),
            'ui'           => 1,
            'allow_null'   => 1,
            'instructions' => 'Select from Site Colors when available.',
            'wrapper'      => array(
                'width' => '50',
            ),
        );
    }

    /*
     * Repeater-item contracts keep row effects in a dedicated Effects tab.
     */
    $effects_tab   = null;
    $effect_fields = array();
    foreach ($fields as $index => $field) {
        if (!is_array($field)) {
            continue;
        }

        $field_type  = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
        $field_name  = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
        $field_label = isset($field['label']) ? sanitize_title((string) $field['label']) : '';

        if (null === $effects_tab && 'tab' === $field_type && 'effects' === $field_label) {
            $effects_tab = $field;
            unset($fields[$index]);
            continue;
        }

        if ('enable_row_effects' === $field_name) {
            if (empty($effect_fields)) {
                $effect_fields[] = $field;
            }
            unset($fields[$index]);
        }
    }
    $fields = array_values($fields);

    if (null === $effects_tab) {
        $effects_tab = array(
            'key'       => $key_seed . '_effects_tab',
            'label'     => 'Effects',
            'name'      => '',
            'type'      => 'tab',
            'placement' => 'top',
            'endpoint'  => 0,
        );
    }

    if (empty($effect_fields)) {
        $effect_fields[] = array(
            'key'           => $key_seed . '_enable_row_effects',
            'label'         => 'Enable Row Effects',
            'name'          => 'enable_row_effects',
            'type'          => 'true_false',
            'ui'            => 1,
            'default_value' => 0,
            'ui_on_text'    => 'On',
            'ui_off_text'   => 'Off',
            'wrapper'       => array(
                'width' => '50',
            ),
        );
    }

    $fields[] = $effects_tab;
    array_splice($fields, count($fields), 0, $effect_fields);

    $fields = mrn_rbl_group_repeater_config_fields_by_functionality($fields, $key_seed);

    return $fields;
}

/**
 * Recursively apply the shared primary-layout field contract.
 *
 * @param array<int, mixed> $fields Field definitions.
 * @param bool              $inject_internal_name Whether to inject the editor-only internal name field.
 * @return array<int, mixed>
 */
function mrn_rbl_apply_primary_layout_field_contract(array $fields, bool $inject_internal_name = true): array {
    $normalized_fields = array();

    foreach ($fields as $field) {
        if (!is_array($field)) {
            $normalized_fields[] = $field;
            continue;
        }

        if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
            $field['sub_fields'] = mrn_rbl_apply_primary_layout_field_contract($field['sub_fields'], false);

            $field_type = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
            $field_name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
            $field_key  = isset($field['key']) ? trim((string) $field['key']) : '';

            if ('repeater' === $field_type && 'links' !== $field_name) {
                $field['sub_fields'] = mrn_rbl_ensure_repeater_subheading_contract($field['sub_fields'], $field_key);
                $field['sub_fields'] = mrn_rbl_apply_repeater_item_tabs_and_config_contract($field['sub_fields'], $field_name, $field_key);
            }
        }

        if (isset($field['fields']) && is_array($field['fields'])) {
            $field['fields'] = mrn_rbl_apply_primary_layout_field_contract($field['fields'], false);
        }

        if (isset($field['layouts']) && is_array($field['layouts'])) {
            foreach ($field['layouts'] as $layout_key => $layout) {
                if (!is_array($layout)) {
                    continue;
                }

                if (isset($layout['sub_fields']) && is_array($layout['sub_fields'])) {
                    $layout['sub_fields'] = mrn_rbl_apply_primary_layout_field_contract($layout['sub_fields'], true);
                }

                $field['layouts'][$layout_key] = $layout;
            }
        }

        $normalized_fields[] = mrn_rbl_normalize_primary_layout_field($field);
    }

    $normalized_fields = mrn_rbl_apply_tag_field_column_layout($normalized_fields);
    if ($inject_internal_name) {
        $normalized_fields = mrn_rbl_group_main_config_fields_by_functionality($normalized_fields);
    }

    if (!$inject_internal_name) {
        return $normalized_fields;
    }

    $content_tab_index = null;
    $first_field_index = null;
    $internal_name_key = 'field_mrn_rbl_internal_name';
    $has_internal_name = false;

    foreach ($normalized_fields as $index => $field) {
        if (!is_array($field)) {
            continue;
        }

        $field_name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
        $field_type = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
        $field_key  = isset($field['key']) ? trim((string) $field['key']) : '';

        if (null === $first_field_index) {
            $first_field_index = $index;

            if ('' !== $field_key) {
                $internal_name_key = sanitize_key($field_key) . '_internal_name';
            }
        }

        if ('internal_name' === $field_name) {
            $has_internal_name = true;
        }

        if (null !== $content_tab_index || 'tab' !== $field_type) {
            continue;
        }

        $field_label = isset($field['label']) ? sanitize_title((string) $field['label']) : '';
        if ('content' !== $field_label) {
            continue;
        }

        $content_tab_index = $index;

        if ('' !== $field_key) {
            $internal_name_key = sanitize_key($field_key) . '_internal_name';
        }
    }

    if (!$has_internal_name) {
        $insert_index = null !== $content_tab_index ? $content_tab_index + 1 : (null !== $first_field_index ? $first_field_index : 0);
        array_splice($normalized_fields, $insert_index, 0, array(mrn_rbl_get_internal_layout_name_field($internal_name_key)));
    }

    return $normalized_fields;
}

/**
 * Determine whether a field group targets a reusable-block post type.
 *
 * @param array<string, mixed> $field_group
 * @return bool
 */
function mrn_rbl_should_auto_enhance_field_group(array $field_group): bool {
    $locations = isset($field_group['location']) && is_array($field_group['location']) ? $field_group['location'] : array();

    foreach ($locations as $location_group) {
        if (!is_array($location_group)) {
            continue;
        }

        foreach ($location_group as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $param = isset($rule['param']) ? (string) $rule['param'] : '';
            $value = isset($rule['value']) ? sanitize_key((string) $rule['value']) : '';

            if ('post_type' === $param && 0 === strpos($value, 'mrn_reusable_')) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Ensure reusable-block field groups always include shared effects controls.
 *
 * @param array<string, mixed> $field_group
 * @return array<string, mixed>
 */
function mrn_rbl_with_effects_fields(array $field_group): array {
    if (!mrn_rbl_should_auto_enhance_field_group($field_group)) {
        return $field_group;
    }

    $fields = isset($field_group['fields']) && is_array($field_group['fields']) ? $field_group['fields'] : array();
    $fields = mrn_rbl_apply_primary_layout_field_contract($fields);

    if (mrn_rbl_fields_have_motion_group($fields)) {
        $field_group['fields'] = $fields;
        return $field_group;
    }

    $group_key = isset($field_group['key']) ? sanitize_key((string) $field_group['key']) : 'mrn_reusable_group';
    if (mrn_rbl_fields_have_tabs($fields)) {
        $fields[] = mrn_rbl_get_effects_tab_field('field_' . $group_key . '_effects_tab_auto');
    }
    $fields[] = mrn_rbl_get_motion_group_field('field_' . $group_key . '_motion_settings_auto');

    $field_group['fields'] = $fields;

    return $field_group;
}

/**
 * Reapply shared effect transforms to reusable-block field groups after registration.
 *
 * @return void
 */
function mrn_rbl_auto_enhance_local_field_groups(): void {
    if (!function_exists('acf_get_local_field_groups') || !function_exists('acf_get_fields') || !function_exists('acf_add_local_field_group')) {
        return;
    }

    $field_groups = acf_get_local_field_groups();
    if (!is_array($field_groups)) {
        return;
    }

    foreach ($field_groups as $field_group) {
        if (!is_array($field_group)) {
            continue;
        }

        $group_key = isset($field_group['key']) ? (string) $field_group['key'] : '';
        if ('' === $group_key) {
            continue;
        }

        $fields = acf_get_fields($group_key);
        if (!is_array($fields)) {
            continue;
        }

        $field_group['fields'] = $fields;

        if (!mrn_rbl_should_auto_enhance_field_group($field_group)) {
            continue;
        }

        acf_add_local_field_group(mrn_rbl_with_effects_fields($field_group));
    }
}

/**
 * Normalize a reusable block anchor ID for safe front-end output.
 */
function mrn_rbl_normalize_anchor_id($value): string {
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = ltrim($value, "# \t\n\r\0\x0B");

    return sanitize_title($value);
}

/**
 * Build a standard label-tag ACF field definition.
 *
 * @return array<string, mixed>
 */
function mrn_rbl_get_label_tag_field(string $key, string $name = 'label_tag', string $default = 'p'): array {
    return array(
        'key'           => $key,
        'label'         => 'Tag',
        'name'          => $name,
        'type'          => 'select',
        'default_value' => mrn_rbl_normalize_text_tag($default, 'p'),
        'choices'       => mrn_rbl_get_label_tag_choices(),
        'multiple'      => 0,
        'return_format' => 'value',
        'ui'            => 1,
        'wrapper'       => array(
            'width' => '25',
        ),
    );
}

/**
 * Build a standard heading/subheading tag ACF field definition.
 *
 * @return array<string, mixed>
 */
function mrn_rbl_get_text_tag_field(string $key, string $label = 'Tag', string $name = 'text_field_tag', string $default = 'h2'): array {
    unset($label);

    return array(
        'key'           => $key,
        'label'         => 'Tag',
        'name'          => $name,
        'type'          => 'select',
        'default_value' => mrn_rbl_normalize_text_tag($default, 'h2'),
        'choices'       => mrn_rbl_get_heading_tag_choices(),
        'multiple'      => 0,
        'return_format' => 'value',
        'ui'            => 1,
        'wrapper'       => array(
            'width' => '25',
        ),
    );
}

/**
 * Shared link style choices for reusable blocks that can render links like buttons.
 *
 * @return array<string, string>
 */
function mrn_rbl_get_link_style_choices(): array {
    return array(
        'link'   => 'Link',
        'button' => 'Button',
    );
}

/**
 * Shared target choices for content links.
 *
 * @return array<string, string>
 */
function mrn_rbl_get_content_link_target_choices(): array {
    return array(
        ''        => 'Same Tab / Window',
        '_blank'  => 'New Tab / Window',
        '_self'   => 'Same Frame',
        '_parent' => 'Parent Frame',
        '_top'    => 'Top Frame',
    );
}

/**
 * Shared Dashicon choices for button-style links.
 *
 * @return array<string, string>
 */
function mrn_rbl_get_button_link_dashicon_choices(): array {
    if (function_exists('mrn_base_stack_get_header_search_standard_icon_choices')) {
        return mrn_base_stack_get_header_search_standard_icon_choices();
    }

    $choices = array();
    $icons   = function_exists('mrn_shared_assets_get_dashicons') ? mrn_shared_assets_get_dashicons() : array();

    foreach ($icons as $icon) {
        $key           = 'dashicons-' . $icon;
        $choices[$key] = ucwords(str_replace('-', ' ', $icon));
    }

    return $choices;
}

/**
 * Shared Font Awesome choices for button-style links.
 *
 * @return array<string, string>
 */
function mrn_rbl_get_button_link_fontawesome_choices(): array {
    if (function_exists('mrn_base_stack_get_builder_link_fontawesome_choices')) {
        return mrn_base_stack_get_builder_link_fontawesome_choices();
    }

    if (function_exists('mrn_base_stack_get_header_search_fontawesome_choices')) {
        return mrn_base_stack_get_header_search_fontawesome_choices();
    }

    $choices = array();
    $icons   = function_exists('mrn_shared_assets_get_fontawesome_icons') ? mrn_shared_assets_get_fontawesome_icons() : array();
    $styles  = array(
        'solid'   => 'fa-solid',
        'regular' => 'fa-regular',
        'brands'  => 'fa-brands',
    );

    foreach ($styles as $style => $class_prefix) {
        if (empty($icons[$style]) || !is_array($icons[$style])) {
            continue;
        }

        foreach ($icons[$style] as $icon) {
            if (!is_array($icon) || empty($icon['name'])) {
                continue;
            }

            $name  = sanitize_key((string) $icon['name']);
            $label = !empty($icon['label']) ? (string) $icon['label'] : ucwords(str_replace('-', ' ', $name));

            if ('' === $name) {
                continue;
            }

            $choices[$class_prefix . ' fa-' . $name] = $label . ' (' . ucfirst($style) . ')';
        }
    }

    if (!function_exists('mrn_fapm_get_icon_allowlist')) {
        return $choices;
    }

    $allowlist = mrn_fapm_get_icon_allowlist();
    if (!is_array($allowlist) || empty($allowlist)) {
        return $choices;
    }

    $filtered = array();
    foreach ($allowlist as $icon_class) {
        $icon_class = trim((string) $icon_class);
        if ('' === $icon_class) {
            continue;
        }

        $filtered[$icon_class] = isset($choices[$icon_class]) ? (string) $choices[$icon_class] : $icon_class;
    }

    return !empty($filtered) ? $filtered : $choices;
}

/**
 * Build shared manual icon fields for content links.
 *
 * @param string $key_prefix Unique ACF key prefix for this icon field set.
 * @param string $button_field_key Unused legacy arg kept for call-site compatibility.
 * @return array<int, array<string, mixed>>
 */
function mrn_rbl_get_button_link_icon_fields(string $key_prefix, string $button_field_key): array {
    unset($button_field_key);

    return array(
        array(
            'key'           => $key_prefix . '_source',
            'label'         => 'Icon Source',
            'name'          => 'link_icon_source',
            'type'          => 'button_group',
            'choices'       => array(
                'dashicons'   => 'Dashicons',
                'fontawesome' => 'Font Awesome',
                'media'       => 'Media',
            ),
            'default_value' => '',
            'layout'        => 'horizontal',
            'return_format' => 'value',
            'wrapper'       => array(
                'width' => '100',
                'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--source mrn-icon-chooser-field--allow-empty',
            ),
        ),
        array(
            'key'         => $key_prefix . '_dashicons',
            'label'       => 'Dashicon',
            'name'        => 'link_icon_dashicon',
            'type'        => 'text',
            'placeholder' => 'dashicons-arrow-right-alt2',
            'wrapper'     => array(
                'width' => '50',
                'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--dashicons',
            ),
        ),
        array(
            'key'         => $key_prefix . '_fontawesome',
            'label'       => 'Font Awesome',
            'name'        => 'link_icon_fa_class',
            'type'        => 'text',
            'placeholder' => 'fa-solid fa-arrow-right',
            'wrapper'     => array(
                'width' => '50',
                'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--fontawesome',
            ),
        ),
        array(
            'key'           => $key_prefix . '_media',
            'label'         => 'Media',
            'name'          => 'link_icon_media_icon',
            'type'          => 'image',
            'return_format' => 'array',
            'preview_size'  => 'thumbnail',
            'library'       => 'all',
            'mime_types'    => 'jpg,jpeg,png,gif,webp,svg',
            'wrapper'       => array(
                'width' => '50',
                'class' => 'mrn-icon-chooser-field mrn-icon-chooser-field--media',
            ),
        ),
        array(
            'key'           => $key_prefix . '_position',
            'label'         => 'Icon Position',
            'name'          => 'link_icon_position',
            'type'          => 'select',
            'choices'       => array(
                'left'  => 'Left',
                'right' => 'Right',
            ),
            'default_value' => 'left',
            'return_format' => 'value',
            'ui'            => 1,
            'wrapper'       => array(
                'width' => '50',
            ),
        ),
        array(
            'key'           => $key_prefix . '_gap',
            'label'         => 'Icon Gap',
            'name'          => 'link_icon_gap',
            'type'          => 'number',
            'default_value' => 10,
            'min'           => 0,
            'step'          => 1,
            'append'        => 'px',
            'wrapper'       => array(
                'width' => '50',
            ),
        ),
    );
}

/**
 * Build the shared link contract sub-fields used inside repeater rows/layout items.
 *
 * @param string               $key_prefix Field key prefix.
 * @param array<string, mixed> $args Optional config.
 * @return array<int, array<string, mixed>>
 */
function mrn_rbl_get_content_link_contract_sub_fields(string $key_prefix, array $args = array()): array {
    $link_field_key = $key_prefix . '_link';
    $button_key     = $key_prefix . '_button';
    $link_tab_key   = $key_prefix . '_link_tab';
    $config_tab_key = $key_prefix . '_config_tab';
    $link_label     = 'Select Link';
    $include_tabs   = true;

    if (isset($args['link_field_key']) && is_string($args['link_field_key']) && '' !== trim($args['link_field_key'])) {
        $link_field_key = trim($args['link_field_key']);
    }

    if (isset($args['button_key']) && is_string($args['button_key']) && '' !== trim($args['button_key'])) {
        $button_key = trim($args['button_key']);
    }

    if (isset($args['link_tab_key']) && is_string($args['link_tab_key']) && '' !== trim($args['link_tab_key'])) {
        $link_tab_key = trim($args['link_tab_key']);
    }

    if (isset($args['config_tab_key']) && is_string($args['config_tab_key']) && '' !== trim($args['config_tab_key'])) {
        $config_tab_key = trim($args['config_tab_key']);
    }

    if (isset($args['link_label']) && is_string($args['link_label']) && '' !== trim($args['link_label'])) {
        $link_label = trim($args['link_label']);
    }

    if (isset($args['include_tabs'])) {
        $include_tabs = (bool) $args['include_tabs'];
    }

    $sub_fields = array();

    if ($include_tabs) {
        $sub_fields[] = array(
            'key'       => $link_tab_key,
            'label'     => 'Link',
            'name'      => '',
            'type'      => 'tab',
            'placement' => 'top',
            'endpoint'  => 0,
        );
    }

    $sub_fields[] = array(
        'key'           => $link_field_key,
        'label'         => $link_label,
        'name'          => 'link',
        'type'          => 'link',
        'return_format' => 'array',
        'wrapper'       => array(
            'width' => '100',
        ),
    );

    if ($include_tabs) {
        $sub_fields[] = array(
            'key'       => $config_tab_key,
            'label'     => 'Configs',
            'name'      => '',
            'type'      => 'tab',
            'placement' => 'top',
            'endpoint'  => 0,
        );
    }

    return array_merge(
        $sub_fields,
        array(
            array(
                'key'           => $button_key,
                'label'         => 'Button',
                'name'          => 'is_button',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'wrapper'       => array(
                    'width' => '25',
                ),
            ),
            array(
                'key'     => $key_prefix . '_css_classes',
                'label'   => 'CSS Classes',
                'name'    => 'css_classes',
                'type'    => 'text',
                'wrapper' => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => $key_prefix . '_target',
                'label'         => 'Target',
                'name'          => 'target',
                'type'          => 'select',
                'choices'       => mrn_rbl_get_content_link_target_choices(),
                'default_value' => '',
                'ui'            => 1,
                'wrapper'       => array(
                    'width' => '25',
                ),
            ),
            array(
                'key'     => $key_prefix . '_rel',
                'label'   => 'Rel',
                'name'    => 'rel',
                'type'    => 'text',
                'wrapper' => array(
                    'width' => '33',
                ),
            ),
            array(
                'key'     => $key_prefix . '_title_attribute',
                'label'   => 'Title Attributes',
                'name'    => 'title_attribute',
                'type'    => 'text',
                'wrapper' => array(
                    'width' => '34',
                ),
            ),
            array(
                'key'           => $key_prefix . '_download',
                'label'         => 'Download',
                'name'          => 'download',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'wrapper'       => array(
                    'width' => '33',
                ),
            ),
            array(
                'key'     => $key_prefix . '_hreflang',
                'label'   => 'Hreflang',
                'name'    => 'hreflang',
                'type'    => 'text',
                'wrapper' => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'     => $key_prefix . '_media',
                'label'   => 'Media',
                'name'    => 'media',
                'type'    => 'text',
                'wrapper' => array(
                    'width' => '50',
                ),
            ),
            ...mrn_rbl_get_button_link_icon_fields($key_prefix . '_icon', $button_key),
        )
    );
}

/**
 * Build the shared content-link repeater field.
 *
 * @param string      $key Repeater field key.
 * @param string      $label Field label.
 * @param string      $name Field name.
 * @param int         $max Maximum rows allowed. Use 0 for unlimited.
 * @param string|null $instructions Optional instructions override.
 * @return array<string, mixed>
 */
function mrn_rbl_get_content_link_repeater_field(string $key, string $label = 'Link repeater', string $name = 'links', int $max = 0, ?string $instructions = null): array {
    unset($instructions);

    $link_key  = $key . '_link';
    $field_max = $max > 0 ? $max : 0;

    return array(
        'key'          => $key,
        'label'        => $label,
        'name'         => $name,
        'type'         => 'repeater',
        'layout'       => 'block',
        'button_label' => 'Add Link',
        'collapsed'    => $link_key,
        'min'          => 0,
        'max'          => $field_max,
        'sub_fields'   => mrn_rbl_get_content_link_contract_sub_fields(
            $key,
            array(
                'button_key' => $key . '_is_button',
            )
        ),
    );
}

/**
 * Build content-link fields for builder groups.
 *
 * @param string      $key Unique ACF key prefix.
 * @param string      $label Field label.
 * @param string      $name Field name.
 * @param int         $max Maximum rows allowed in the repeater.
 * @param string|null $instructions Optional instructions override.
 * @return array<int, array<string, mixed>>
 */
function mrn_rbl_get_content_link_fields(string $key, string $label = 'Links', string $name = 'links', int $max = 0, ?string $instructions = null): array {
    $tip_message = '<div style="margin:0 0 8px 0;padding:10px 12px;border-left:4px solid #2271b1;background:#f0f6fc;border-radius:2px;display:flex;align-items:flex-start;gap:8px;"><span class="dashicons dashicons-lightbulb" aria-hidden="true" style="margin-top:1px;color:#2271b1;"></span><span><strong>Pro Tip:</strong> Link Tab: use <em>Select Link</em>. Configs Tab: use <em>Button</em>, <em>CSS Classes</em>, <em>Target</em>, <em>Rel</em>, <em>Title Attributes</em>, <em>Download</em>, <em>Hreflang</em>, <em>Media</em>, <em>Font Awesome</em>, <em>Icon Position</em>, and <em>Icon Gap</em>.</span></div>';

    return array(
        array(
            'key'       => $key . '_tip',
            'label'     => '',
            'name'      => '',
            'type'      => 'message',
            'message'   => $tip_message,
            'esc_html'  => 0,
            'new_lines' => 'wpautop',
        ),
        mrn_rbl_get_content_link_repeater_field($key, $label, $name, $max, $instructions),
    );
}

/**
 * Normalize rel tokens for safe link output.
 *
 * @param string $rel Raw rel string.
 * @param string $target Normalized target value.
 * @return string
 */
function mrn_rbl_normalize_content_link_rel(string $rel, string $target): string {
    $tokens = preg_split('/\s+/', trim(strtolower($rel))) ?: array();
    $tokens = array_filter(array_map('sanitize_key', $tokens));

    if ('_blank' === $target) {
        $tokens[] = 'noopener';
        $tokens[] = 'noreferrer';
    }

    return implode(' ', array_values(array_unique($tokens)));
}

/**
 * Normalize a whitespace-delimited class string for safe HTML output.
 *
 * @param string $classes Raw class string.
 * @return array<int, string>
 */
function mrn_rbl_normalize_css_class_tokens(string $classes): array {
    $tokens = preg_split('/\s+/', trim($classes)) ?: array();
    $tokens = array_map('sanitize_html_class', $tokens);
    $tokens = array_filter($tokens);

    return array_values(array_unique($tokens));
}

/**
 * Normalize a content-link row for template use.
 *
 * @param array<string, mixed> $link Raw link row data.
 * @param array<string, mixed> $args Optional fallback config.
 * @return array<string, mixed>
 */
function mrn_rbl_normalize_content_link(array $link, array $args = array()): array {
    $fallback_style       = isset($args['fallback_link_style']) ? sanitize_key((string) $args['fallback_link_style']) : '';
    $fallback_icon_fields = isset($args['fallback_icon_fields']) && is_array($args['fallback_icon_fields']) ? $args['fallback_icon_fields'] : array();
    $allowed_styles       = array('link', 'button');
    $allowed_targets      = array('', '_blank', '_self', '_parent', '_top');

    if (!in_array($fallback_style, $allowed_styles, true)) {
        $fallback_style = 'link';
    }

    $acf_link = isset($link['link']) && is_array($link['link']) ? $link['link'] : array();
    $text     = trim((string) ($acf_link['title'] ?? ''));
    $url      = isset($acf_link['url']) ? esc_url_raw((string) $acf_link['url']) : '';
    $target   = isset($link['target']) ? sanitize_key((string) $link['target']) : '';

    if ('' === $target) {
        $target = isset($acf_link['target']) ? sanitize_key((string) $acf_link['target']) : '';
    }

    if ('' === $url) {
        $url = isset($link['url']) ? esc_url_raw((string) $link['url']) : '';
    }

    if ('' === $text) {
        $text = trim((string) ($link['text'] ?? ($link['label'] ?? ($link['title'] ?? ''))));
    }

    if (!in_array($target, $allowed_targets, true)) {
        $target = '';
    }

    $is_button = array_key_exists('is_button', $link)
        ? !empty($link['is_button'])
        : ('button' === sanitize_key((string) ($link['link_style'] ?? $fallback_style)));

    $normalized = array(
        'text'                 => $text,
        'url'                  => $url,
        'target'               => $target,
        'rel'                  => mrn_rbl_normalize_content_link_rel((string) ($link['rel'] ?? ''), $target),
        'title_attribute'      => sanitize_text_field((string) ($link['title_attribute'] ?? '')),
        'download'             => !empty($link['download']),
        'hreflang'             => sanitize_text_field((string) ($link['hreflang'] ?? '')),
        'media'                => sanitize_text_field((string) ($link['media'] ?? '')),
        'is_button'            => $is_button,
        'link_style'           => $is_button ? 'button' : 'link',
        'css_classes'          => mrn_rbl_normalize_css_class_tokens((string) ($link['css_classes'] ?? ($link['css_class'] ?? ''))),
        'link_icon_source'     => isset($link['link_icon_source']) ? sanitize_key((string) $link['link_icon_source']) : '',
        'link_icon_dashicon'   => isset($link['link_icon_dashicon']) ? trim((string) $link['link_icon_dashicon']) : '',
        'link_icon_fa_class'   => isset($link['link_icon_fa_class']) ? trim((string) $link['link_icon_fa_class']) : '',
        'link_icon_media_icon' => isset($link['link_icon_media_icon']) && is_array($link['link_icon_media_icon']) ? $link['link_icon_media_icon'] : array(),
        'link_icon_position'   => isset($link['link_icon_position']) ? sanitize_key((string) $link['link_icon_position']) : '',
        'link_icon_gap'        => $link['link_icon_gap'] ?? '',
    );

    foreach (array('link_icon_source', 'link_icon_dashicon', 'link_icon_fa_class', 'link_icon_position', 'link_icon_gap') as $icon_key) {
        if ('' === (string) $normalized[$icon_key] && isset($fallback_icon_fields[$icon_key])) {
            $normalized[$icon_key] = $fallback_icon_fields[$icon_key];
        }
    }

    if (empty($normalized['link_icon_media_icon']) && !empty($fallback_icon_fields['link_icon_media_icon']) && is_array($fallback_icon_fields['link_icon_media_icon'])) {
        $normalized['link_icon_media_icon'] = $fallback_icon_fields['link_icon_media_icon'];
    }

    if (
        '' !== (string) $normalized['link_icon_fa_class']
        && function_exists('mrn_fapm_icon_is_allowed')
        && !mrn_fapm_icon_is_allowed((string) $normalized['link_icon_fa_class'])
    ) {
        $normalized['link_icon_fa_class'] = '';
        if ('fontawesome' === (string) $normalized['link_icon_source']) {
            $normalized['link_icon_source'] = '';
        }
    }

    return $normalized;
}

/**
 * Normalize a link collection from named fields.
 *
 * @param array<string, mixed> $fields Field array with `primary_link`..`quaternary_link`.
 * @param array<string, mixed> $args Optional fallback config.
 * @return array<int, array<string, mixed>>
 */
function mrn_rbl_get_content_links(array $fields, array $args = array()): array {
    $links          = array();
    $named_link_map = array('primary_link', 'secondary_link', 'tertiary_link', 'quaternary_link');
    $max_links      = isset($args['max']) ? max(0, (int) $args['max']) : 0;

    foreach ($named_link_map as $link_key) {
        if (!isset($fields[$link_key]) || !is_array($fields[$link_key])) {
            continue;
        }

        $links[] = $fields[$link_key];
    }

    /**
     * Backward compatibility: keep rendering legacy repeater-based links so
     * existing builder rows and reusable blocks do not lose CTA output until
     * they are re-saved with the named link fields.
     */
    if (empty($links) && isset($fields['links']) && is_array($fields['links'])) {
        $legacy_links = $fields['links'];

        if (isset($legacy_links['link']) || isset($legacy_links['url'])) {
            $legacy_links = array($legacy_links);
        }

        foreach ($legacy_links as $legacy_link) {
            if (!is_array($legacy_link)) {
                continue;
            }

            $links[] = $legacy_link;
        }
    }

    $normalized = array();
    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        $link = mrn_rbl_normalize_content_link($link, $args);

        if ('' === $link['url']) {
            continue;
        }

        $normalized[] = $link;

        if ($max_links > 0 && count($normalized) >= $max_links) {
            break;
        }
    }

    return $normalized;
}

/**
 * Resolve the HTML tag used to render a normalized content link.
 *
 * @param array<string, mixed> $link Normalized link data.
 * @return string
 */
function mrn_rbl_get_content_link_tag_name(array $link): string {
    return 'a';
}

/**
 * Get any sanitized custom classes configured for a normalized content link.
 *
 * @param array<string, mixed> $link Normalized link data.
 * @return string
 */
function mrn_rbl_get_content_link_custom_class_names(array $link): string {
    $classes = isset($link['css_classes']) && is_array($link['css_classes']) ? $link['css_classes'] : array();
    $classes = array_filter(array_map('sanitize_html_class', $classes));

    return implode(' ', array_values(array_unique($classes)));
}

/**
 * Build a safe HTML attribute string for a normalized content link.
 *
 * @param array<string, mixed> $link Normalized link data.
 * @return string
 */
function mrn_rbl_get_content_link_html_attributes(array $link): string {
    $url = isset($link['url']) ? (string) $link['url'] : '';
    if ('' === $url) {
        return '';
    }

    $title_attribute = isset($link['title_attribute']) ? (string) $link['title_attribute'] : '';

    $attributes = array(
        'href="' . esc_url($url) . '"',
    );

    $target = isset($link['target']) ? (string) $link['target'] : '';
    if ('' !== $target) {
        $attributes[] = 'target="' . esc_attr($target) . '"';
    }

    $rel = isset($link['rel']) ? (string) $link['rel'] : '';
    if ('' !== $rel) {
        $attributes[] = 'rel="' . esc_attr($rel) . '"';
    }

    if ('' !== $title_attribute) {
        $attributes[] = 'title="' . esc_attr($title_attribute) . '"';
    }

    if (!empty($link['download'])) {
        $attributes[] = 'download';
    }

    $hreflang = isset($link['hreflang']) ? (string) $link['hreflang'] : '';
    if ('' !== $hreflang) {
        $attributes[] = 'hreflang="' . esc_attr($hreflang) . '"';
    }

    $media = isset($link['media']) ? (string) $link['media'] : '';
    if ('' !== $media) {
        $attributes[] = 'media="' . esc_attr($media) . '"';
    }

    return implode(' ', $attributes);
}

/**
 * Shared post-type choices for content-list reusable blocks.
 *
 * @return array<string, string>
 */
function mrn_rbl_get_content_list_post_type_choices(): array {
    if (function_exists('mrn_base_stack_get_content_list_post_type_choices')) {
        return mrn_base_stack_get_content_list_post_type_choices();
    }

    return array(
        'post' => 'Posts',
    );
}

/**
 * Shared list-style choices for content-list reusable blocks.
 *
 * @return array<string, string>
 */
function mrn_rbl_get_content_list_style_choices(): array {
    if (function_exists('mrn_base_stack_get_content_list_style_choices')) {
        return mrn_base_stack_get_content_list_style_choices();
    }

    return array(
        'unordered' => 'Unordered List',
        'ordered'   => 'Ordered List',
    );
}

/**
 * Shared display-mode choices for content-list reusable blocks.
 *
 * @return array<string, string>
 */
function mrn_rbl_get_content_list_display_mode_choices(): array {
    if (function_exists('mrn_base_stack_get_content_list_display_mode_choices')) {
        return mrn_base_stack_get_content_list_display_mode_choices();
    }

    return array(
        'standard'   => 'Standard',
        'title_only' => 'Title Only',
        'compact'    => 'Compact',
        'feature'    => 'Feature',
    );
}

/**
 * Shared order-by choices for content-list reusable blocks.
 *
 * @return array<string, string>
 */
function mrn_rbl_get_content_list_orderby_choices(): array {
    if (function_exists('mrn_base_stack_get_content_list_orderby_choices')) {
        return mrn_base_stack_get_content_list_orderby_choices();
    }

    return array(
        'date'          => 'Publish Date',
        'modified'      => 'Modified Date',
        'title'         => 'Title',
        'menu_order'    => 'Menu Order',
        'comment_count' => 'Comment Count',
        'rand'          => 'Random',
    );
}

/**
 * Shared taxonomy choices for content-list reusable blocks.
 *
 * @return array<string, string>
 */
function mrn_rbl_get_content_list_taxonomy_choices(): array {
    if (function_exists('mrn_base_stack_get_content_list_taxonomy_choices')) {
        return mrn_base_stack_get_content_list_taxonomy_choices();
    }

    return array(
        'category' => 'Categories',
    );
}

/**
 * Shared filter-source choices for content-list reusable blocks.
 *
 * @return array<string, string>
 */
function mrn_rbl_get_content_list_filter_source_choices(): array {
    if (function_exists('mrn_base_stack_get_content_list_filter_source_choices')) {
        return mrn_base_stack_get_content_list_filter_source_choices();
    }

    return array(
        'none'               => 'No Filter',
        'current_post_terms' => 'Use Current Page/Post Terms',
        'manual_terms'       => 'Use Specific Terms',
    );
}

/**
 * Shared term-matching choices for content-list reusable blocks.
 *
 * @return array<string, string>
 */
function mrn_rbl_get_content_list_filter_match_choices(): array {
    if (function_exists('mrn_base_stack_get_content_list_filter_match_choices')) {
        return mrn_base_stack_get_content_list_filter_match_choices();
    }

    return array(
        'any' => 'Match Any Selected Term',
        'all' => 'Match All Selected Terms',
    );
}

/**
 * Register ACF field groups for reusable block types.
 */
function mrn_rbl_register_acf_field_groups(): void {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group(mrn_rbl_with_effects_fields(array(
        'key'    => 'group_mrn_reusable_cta',
        'title'  => 'CTA Fields',
        'fields' => array(
            array(
                'key'       => 'field_mrn_cta_content_tab',
                'label'     => 'Content',
                'type'      => 'tab',
                'placement' => 'top',
            ),
            mrn_rbl_get_inline_text_field('field_mrn_cta_label', 'Label', 'label'),
            mrn_rbl_get_label_tag_field('field_mrn_cta_label_tag'),
            mrn_rbl_get_inline_text_field('field_mrn_cta_heading', 'Heading', 'heading'),
            mrn_rbl_get_text_tag_field('field_mrn_cta_heading_tag', 'Heading Tag', 'heading_tag', 'h2'),
            mrn_rbl_get_inline_text_field('field_mrn_cta_subheading', 'Subheading', 'subheading'),
            mrn_rbl_get_text_tag_field('field_mrn_cta_subheading_tag', 'Subheading Tag', 'subheading_tag', 'p'),
            array(
                'key'          => 'field_mrn_cta_copy',
                'label'        => 'Text area with editor',
                'name'         => 'content',
                'type'         => 'wysiwyg',
                'tabs'         => 'all',
                'toolbar'      => 'full',
                'media_upload' => 1,
                'delay'        => 0,
            ),
            ...mrn_rbl_get_content_link_fields('field_mrn_cta_links', 'Links', 'links', 2),
            array(
                'key'       => 'field_mrn_cta_config_tab',
                'label'     => 'Configs',
                'type'      => 'tab',
                'placement' => 'top',
                'endpoint'  => 0,
            ),
            mrn_rbl_get_anchor_field('field_mrn_cta_anchor'),
            mrn_rbl_get_section_width_field('field_mrn_cta_section_width', 'section_width', 'wide', 'Section Width (Content)'),
            array(
                'key'           => 'field_mrn_cta_bg_color',
                'label'         => 'Background color',
                'name'          => 'bg_color',
                'type'          => 'select',
                'choices'       => mrn_rbl_get_site_color_choices(),
                'ui'            => 1,
                'allow_null'    => 1,
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_cta_background_image',
                'label'         => 'Background image',
                'name'          => 'background_image',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'library'       => 'all',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_cta_link_color',
                'label'         => 'Link color',
                'name'          => 'link_color',
                'type'          => 'select',
                'choices'       => mrn_rbl_get_site_color_choices(),
                'ui'            => 1,
                'allow_null'    => 1,
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_cta_bottom_accent',
                'label'         => 'Accent',
                'name'          => 'bottom_accent',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_cta_bottom_accent_style',
                'label'         => 'Accent Style',
                'name'          => 'bottom_accent_style',
                'type'          => 'select',
                'default_value' => '',
                'choices'       => function_exists('mrn_site_styles_get_graphic_element_choices')
                    ? mrn_site_styles_get_graphic_element_choices()
                    : array(
                        '' => 'Select a Graphic Element',
                    ),
                'ui'            => 1,
                'instructions'  => 'Choose a saved graphic element from Site Styles.',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            mrn_rbl_get_effects_tab_field('field_mrn_cta_effects_tab'),
            mrn_rbl_get_motion_group_field('field_mrn_cta_motion_settings'),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'mrn_reusable_cta',
                ),
            ),
        ),
        'position'              => 'acf_after_title',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
    )));

    acf_add_local_field_group(mrn_rbl_with_effects_fields(array(
        'key'    => 'group_mrn_reusable_basic_block',
        'title'  => 'Basic Block Fields',
        'fields' => array(
            array(
                'key'   => 'field_mrn_basic_block_content_tab',
                'label' => 'Content',
                'type'  => 'tab',
                'placement' => 'top',
            ),
            mrn_rbl_get_inline_text_field('field_mrn_basic_block_label', 'Label', 'label'),
            mrn_rbl_get_label_tag_field('field_mrn_basic_block_label_tag'),
            mrn_rbl_get_inline_text_field('field_mrn_basic_block_title', 'Heading', 'heading'),
            mrn_rbl_get_text_tag_field('field_mrn_basic_block_title_tag', 'Heading Tag', 'heading_tag', 'h2'),
            mrn_rbl_get_inline_text_field('field_mrn_basic_block_subheading', 'Subheading', 'subheading'),
            mrn_rbl_get_text_tag_field('field_mrn_basic_block_subheading_tag', 'Subheading Tag', 'subheading_tag', 'p'),
            array(
                'key'          => 'field_mrn_basic_block_text',
                'label'        => 'Text area with editor',
                'name'         => 'content',
                'type'         => 'wysiwyg',
                'tabs'         => 'all',
                'toolbar'      => 'full',
                'media_upload' => 1,
                'delay'        => 0,
            ),
            array(
                'key'           => 'field_mrn_basic_block_image',
                'label'         => 'Image',
                'name'          => 'image',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'library'       => 'all',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            ...mrn_rbl_get_content_link_fields('field_mrn_basic_block_links', 'Links', 'links', 1),
            array(
                'key'   => 'field_mrn_basic_block_config_tab',
                'label' => 'Configs',
                'type'  => 'tab',
                'placement' => 'top',
                'endpoint'  => 0,
            ),
            mrn_rbl_get_anchor_field('field_mrn_basic_block_anchor'),
            mrn_rbl_get_section_width_field('field_mrn_basic_block_section_width', 'section_width', 'wide', 'Section Width (Content)'),
            array(
                'key'           => 'field_mrn_basic_block_bg_color',
                'label'         => 'Background color',
                'name'          => 'bg_color',
                'type'          => 'select',
                'choices'       => mrn_rbl_get_site_color_choices(),
                'ui'            => 1,
                'allow_null'    => 1,
                'instructions'  => 'Select from Site Colors so this block stays aligned with shared site variables.',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_basic_block_link_color',
                'label'         => 'Link color',
                'name'          => 'link_color',
                'type'          => 'select',
                'choices'       => mrn_rbl_get_site_color_choices(),
                'ui'            => 1,
                'allow_null'    => 1,
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_basic_block_image_placement',
                'label'         => 'Image placement',
                'name'          => 'image_placement',
                'type'          => 'select',
                'default_value' => 'left',
                'choices'       => array(
                    'left'  => 'Left',
                    'right' => 'Right',
                ),
                'ui'            => 1,
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_basic_block_bottom_accent',
                'label'         => 'Accent',
                'name'          => 'bottom_accent',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_basic_block_bottom_accent_style',
                'label'         => 'Accent Style',
                'name'          => 'bottom_accent_style',
                'type'          => 'select',
                'default_value' => '',
                'choices'       => function_exists('mrn_site_styles_get_graphic_element_choices')
                    ? mrn_site_styles_get_graphic_element_choices()
                    : array(
                        '' => 'Select a Graphic Element',
                    ),
                'ui'            => 1,
                'instructions'  => 'Choose a saved graphic element from Site Styles.',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            mrn_rbl_get_effects_tab_field('field_mrn_basic_block_effects_tab'),
            mrn_rbl_get_motion_group_field('field_mrn_basic_block_motion_settings'),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'mrn_reusable_basic',
                ),
            ),
        ),
        'position'              => 'acf_after_title',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'show_in_rest'          => 1,
    )));

    acf_add_local_field_group(mrn_rbl_with_effects_fields(array(
        'key'    => 'group_mrn_reusable_content_grid',
        'title'  => 'Grid Fields',
        'fields' => array(
            array(
                'key'       => 'field_mrn_content_grid_content_tab',
                'label'     => 'Content',
                'type'      => 'tab',
                'placement' => 'top',
            ),
            mrn_rbl_get_inline_text_field('field_mrn_content_grid_label', 'Label', 'label'),
            mrn_rbl_get_label_tag_field('field_mrn_content_grid_label_tag'),
            mrn_rbl_get_inline_text_field('field_mrn_content_grid_heading', 'Heading', 'heading'),
            mrn_rbl_get_text_tag_field('field_mrn_content_grid_heading_tag', 'Heading Tag', 'heading_tag', 'h2'),
            mrn_rbl_get_inline_text_field('field_mrn_content_grid_subheading', 'Subheading', 'subheading'),
            mrn_rbl_get_text_tag_field('field_mrn_content_grid_subheading_tag', 'Subheading Tag', 'subheading_tag', 'p'),
            array(
                'key'          => 'field_mrn_content_grid_items',
                'label'        => 'Grids',
                'name'         => 'grid_items',
                'type'         => 'repeater',
                'layout'       => 'block',
                'collapsed'    => 'field_mrn_content_grid_item_title',
                'min'          => 1,
                'button_label' => 'Add Grid Item',
                'sub_fields'   => array(
                    mrn_rbl_get_inline_text_field('field_mrn_content_grid_item_label', 'Label', 'label'),
                    mrn_rbl_get_label_tag_field('field_mrn_content_grid_item_label_tag'),
                    mrn_rbl_get_inline_text_field('field_mrn_content_grid_item_title', 'Heading', 'heading'),
                    mrn_rbl_get_text_tag_field('field_mrn_content_grid_item_title_tag', 'Heading Tag', 'heading_tag', 'h3'),
                    array(
                        'key'          => 'field_mrn_content_grid_item_copy',
                        'label'        => 'Text area with editor',
                        'name'         => 'content',
                        'type'         => 'wysiwyg',
                        'tabs'         => 'all',
                        'toolbar'      => 'full',
                        'media_upload' => 1,
                        'delay'        => 0,
                    ),
                    ...mrn_rbl_get_content_link_contract_sub_fields(
                        'field_mrn_content_grid_item_link',
                        array(
                            'link_label'     => 'Link',
                            'config_tab_key' => 'field_mrn_content_grid_item_config_tab',
                        )
                    ),
                ),
            ),
            array(
                'key'       => 'field_mrn_content_grid_config_tab',
                'label'     => 'Configs',
                'type'      => 'tab',
                'placement' => 'top',
            ),
            mrn_rbl_get_anchor_field('field_mrn_content_grid_anchor'),
            mrn_rbl_get_section_width_field('field_mrn_content_grid_section_width', 'section_width', 'wide', 'Section Width (Content)'),
            mrn_rbl_get_sub_content_width_field('field_mrn_content_grid_sub_content_width', 'sub_content_width', 'content', 'Section Width (Sub-content)'),
            array(
                'key'          => 'field_mrn_content_grid_bg_color',
                'label'        => 'Background color',
                'name'         => 'bg_color',
                'type'         => 'select',
                'choices'      => mrn_rbl_get_site_color_choices(),
                'ui'           => 1,
                'allow_null'   => 1,
                'wrapper'      => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_content_grid_columns',
                'label'         => 'Columns',
                'name'          => 'columns',
                'type'          => 'select',
                'choices'       => array(
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                ),
                'default_value' => '3',
                'ui'            => 1,
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_content_grid_link_style',
                'label'         => 'Link style',
                'name'          => 'link_style',
                'type'          => 'select',
                'default_value' => 'link',
                'choices'       => mrn_rbl_get_link_style_choices(),
                'ui'            => 1,
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_content_grid_enable_full_item_link',
                'label'         => 'Make Entire Grid Item Clickable',
                'name'          => 'enable_full_item_link',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_content_grid_hide_item_link',
                'label'         => 'Hide Link Label',
                'name'          => 'hide_item_link',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'conditional_logic' => array(
                    array(
                        array(
                            'field'    => 'field_mrn_content_grid_enable_full_item_link',
                            'operator' => '==',
                            'value'    => '1',
                        ),
                    ),
                ),
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_content_grid_equal_height',
                'label'         => 'Equal height',
                'name'          => 'equal_height',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_content_grid_link_color',
                'label'         => 'Link color',
                'name'          => 'link_color',
                'type'          => 'select',
                'choices'       => mrn_rbl_get_site_color_choices(),
                'ui'            => 1,
                'allow_null'    => 1,
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_content_grid_bottom_accent',
                'label'         => 'Accent',
                'name'          => 'bottom_accent',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_content_grid_bottom_accent_style',
                'label'         => 'Accent Style',
                'name'          => 'bottom_accent_style',
                'type'          => 'select',
                'default_value' => '',
                'choices'       => function_exists('mrn_site_styles_get_graphic_element_choices')
                    ? mrn_site_styles_get_graphic_element_choices()
                    : array(
                        '' => 'Select a Graphic Element',
                    ),
                'ui'            => 1,
                'instructions'  => 'Choose a saved graphic element from Site Styles.',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            mrn_rbl_get_effects_tab_field('field_mrn_content_grid_effects_tab'),
            mrn_rbl_get_motion_group_field('field_mrn_content_grid_motion_settings'),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'mrn_reusable_grid',
                ),
            ),
        ),
        'position'              => 'acf_after_title',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'show_in_rest'          => 1,
    )));

    acf_add_local_field_group(mrn_rbl_with_effects_fields(array(
        'key'    => 'group_mrn_reusable_content_lists',
        'title'  => 'Content Lists Fields',
        'fields' => array(
            array(
                'key'       => 'field_mrn_reusable_content_lists_content_tab',
                'label'     => 'Content',
                'type'      => 'tab',
                'placement' => 'top',
            ),
            mrn_rbl_get_inline_text_field('field_mrn_reusable_content_lists_label', 'Label', 'label'),
            mrn_rbl_get_label_tag_field('field_mrn_reusable_content_lists_label_tag'),
            mrn_rbl_get_inline_text_field('field_mrn_reusable_content_lists_heading', 'Heading', 'heading'),
            mrn_rbl_get_text_tag_field('field_mrn_reusable_content_lists_heading_tag', 'Heading Tag', 'heading_tag', 'h2'),
            mrn_rbl_get_inline_text_field('field_mrn_reusable_content_lists_subheading', 'Subheading', 'subheading'),
            mrn_rbl_get_text_tag_field('field_mrn_reusable_content_lists_subheading_tag', 'Subheading Tag', 'subheading_tag', 'p'),
            array(
                'key'          => 'field_mrn_reusable_content_lists_intro',
                'label'        => 'Intro text with editor',
                'name'         => 'content',
                'type'         => 'wysiwyg',
                'tabs'         => 'all',
                'toolbar'      => 'full',
                'media_upload' => 1,
                'delay'        => 0,
            ),
            array(
                'key'       => 'field_mrn_reusable_content_lists_config_tab',
                'label'     => 'Configs',
                'type'      => 'tab',
                'placement' => 'top',
            ),
            mrn_rbl_get_anchor_field('field_mrn_reusable_content_lists_anchor'),
            array(
                'key'           => 'field_mrn_reusable_content_lists_post_type',
                'label'         => 'Content Type',
                'name'          => 'list_post_type',
                'type'          => 'select',
                'choices'       => mrn_rbl_get_content_list_post_type_choices(),
                'default_value' => 'post',
                'ui'            => 1,
                'wrapper'       => array(
                    'width' => '33',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_style',
                'label'         => 'List Style',
                'name'          => 'list_style',
                'type'          => 'select',
                'choices'       => mrn_rbl_get_content_list_style_choices(),
                'default_value' => 'unordered',
                'ui'            => 1,
                'wrapper'       => array(
                    'width' => '33',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_display_mode',
                'label'         => 'Display Mode',
                'name'          => 'display_mode',
                'type'          => 'select',
                'choices'       => mrn_rbl_get_content_list_display_mode_choices(),
                'default_value' => 'standard',
                'ui'            => 1,
                'wrapper'       => array(
                    'width' => '34',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_posts_per_page',
                'label'         => 'How Many Items',
                'name'          => 'posts_per_page',
                'type'          => 'number',
                'default_value' => 10,
                'min'           => 1,
                'step'          => 1,
                'wrapper'       => array(
                    'width' => '33',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_orderby',
                'label'         => 'Order By',
                'name'          => 'orderby',
                'type'          => 'select',
                'choices'       => mrn_rbl_get_content_list_orderby_choices(),
                'default_value' => 'date',
                'ui'            => 1,
                'wrapper'       => array(
                    'width' => '33',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_order',
                'label'         => 'Sort Order',
                'name'          => 'order',
                'type'          => 'select',
                'choices'       => array(
                    'DESC' => 'Newest / Highest First',
                    'ASC'  => 'Oldest / Lowest First',
                ),
                'default_value' => 'DESC',
                'ui'            => 1,
                'wrapper'       => array(
                    'width' => '33',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_offset',
                'label'         => 'Offset',
                'name'          => 'offset',
                'type'          => 'number',
                'default_value' => 0,
                'min'           => 0,
                'step'          => 1,
                'instructions'  => 'Skips this many items before listing. Ignored when pagination is on.',
                'wrapper'       => array(
                    'width' => '34',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_filter_source',
                'label'         => 'Filter Source',
                'name'          => 'filter_source',
                'type'          => 'select',
                'choices'       => mrn_rbl_get_content_list_filter_source_choices(),
                'default_value' => 'none',
                'ui'            => 1,
                'wrapper'       => array(
                    'width' => '33',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_filter_taxonomy',
                'label'         => 'Filter Taxonomy',
                'name'          => 'filter_taxonomy',
                'type'          => 'select',
                'choices'       => mrn_rbl_get_content_list_taxonomy_choices(),
                'default_value' => 'category',
                'ui'            => 1,
                'wrapper'       => array(
                    'width' => '33',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_filter_match',
                'label'         => 'Term Matching',
                'name'          => 'filter_match',
                'type'          => 'select',
                'choices'       => mrn_rbl_get_content_list_filter_match_choices(),
                'default_value' => 'any',
                'ui'            => 1,
                'wrapper'       => array(
                    'width' => '34',
                ),
            ),
            array(
                'key'               => 'field_mrn_reusable_content_lists_filter_term_slugs',
                'label'             => 'Specific Terms',
                'name'              => 'filter_term_slugs',
                'type'              => 'text',
                'instructions'      => 'Enter term slugs separated by commas, like news, featured, company-updates.',
                'conditional_logic' => array(
                    array(
                        array(
                            'field'    => 'field_mrn_reusable_content_lists_filter_source',
                            'operator' => '==',
                            'value'    => 'manual_terms',
                        ),
                    ),
                ),
                'wrapper'           => array(
                    'width' => '100',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_pagination',
                'label'         => 'Enable Pagination',
                'name'          => 'enable_pagination',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'wrapper'       => array(
                    'width' => '25',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_show_image',
                'label'         => 'Show Featured Image',
                'name'          => 'show_featured_image',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 1,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'wrapper'       => array(
                    'width' => '25',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_show_date',
                'label'         => 'Show Publish Date',
                'name'          => 'show_publish_date',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 1,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'wrapper'       => array(
                    'width' => '25',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_show_excerpt',
                'label'         => 'Show Excerpt',
                'name'          => 'show_excerpt',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 1,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'wrapper'       => array(
                    'width' => '25',
                ),
            ),
            array(
                'key'               => 'field_mrn_reusable_content_lists_excerpt_length',
                'label'             => 'Excerpt Length',
                'name'              => 'excerpt_length',
                'type'              => 'number',
                'default_value'     => 24,
                'min'               => 5,
                'step'              => 1,
                'conditional_logic' => array(
                    array(
                        array(
                            'field'    => 'field_mrn_reusable_content_lists_show_excerpt',
                            'operator' => '==',
                            'value'    => '1',
                        ),
                    ),
                ),
                'wrapper'           => array(
                    'width' => '25',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_show_read_more',
                'label'         => 'Show Read More Link',
                'name'          => 'show_read_more',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 1,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'wrapper'       => array(
                    'width' => '25',
                ),
            ),
            array(
                'key'               => 'field_mrn_reusable_content_lists_read_more_label',
                'label'             => 'Read More Label',
                'name'              => 'read_more_label',
                'type'              => 'text',
                'default_value'     => 'Read More',
                'conditional_logic' => array(
                    array(
                        array(
                            'field'    => 'field_mrn_reusable_content_lists_show_read_more',
                            'operator' => '==',
                            'value'    => '1',
                        ),
                    ),
                ),
                'wrapper'           => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_empty_message',
                'label'         => 'Empty State Message',
                'name'          => 'empty_message',
                'type'          => 'text',
                'default_value' => 'No content found.',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_hide_when_empty',
                'label'         => 'Hide Entire Row When Empty',
                'name'          => 'hide_when_empty',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'ui_on_text'    => 'Hide Row',
                'ui_off_text'   => 'Show Empty State',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'          => 'field_mrn_reusable_content_lists_background_color',
                'label'        => 'Background color',
                'name'         => 'background_color',
                'type'         => 'select',
                'choices'      => mrn_rbl_get_site_color_choices(),
                'ui'           => 1,
                'allow_null'   => 1,
                'instructions' => 'Select from Site Colors when available.',
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_bottom_accent',
                'label'         => 'Bottom Accent',
                'name'          => 'bottom_accent',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_content_lists_bottom_accent_style',
                'label'         => 'Bottom Accent Style',
                'name'          => 'bottom_accent_style',
                'type'          => 'select',
                'choices'       => function_exists('mrn_site_styles_get_graphic_element_choices')
                    ? mrn_site_styles_get_graphic_element_choices()
                    : array(
                        '' => 'Select a Graphic Element',
                    ),
                'default_value' => '',
                'ui'            => 1,
                'allow_null'    => 1,
                'instructions'  => 'Choose a saved graphic element from Site Styles.',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            mrn_rbl_get_effects_tab_field('field_mrn_reusable_content_lists_effects_tab'),
            mrn_rbl_get_motion_group_field('field_mrn_reusable_content_lists_motion_settings'),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'mrn_reusable_list',
                ),
            ),
        ),
        'position'              => 'acf_after_title',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'show_in_rest'          => 1,
    )));

    acf_add_local_field_group(mrn_rbl_with_effects_fields(array(
        'key'    => 'group_mrn_reusable_search_form',
        'title'  => 'Search Form Fields',
        'fields' => array(
            array(
                'key'       => 'field_mrn_reusable_search_form_content_tab',
                'label'     => 'Content',
                'type'      => 'tab',
                'placement' => 'top',
            ),
            mrn_rbl_get_inline_text_field('field_mrn_reusable_search_form_label', 'Label', 'label'),
            mrn_rbl_get_label_tag_field('field_mrn_reusable_search_form_label_tag'),
            mrn_rbl_get_inline_text_field('field_mrn_reusable_search_form_heading', 'Heading', 'heading'),
            mrn_rbl_get_text_tag_field('field_mrn_reusable_search_form_heading_tag', 'Heading Tag', 'heading_tag', 'h2'),
            mrn_rbl_get_inline_text_field('field_mrn_reusable_search_form_subheading', 'Subheading', 'subheading'),
            mrn_rbl_get_text_tag_field('field_mrn_reusable_search_form_subheading_tag', 'Subheading Tag', 'subheading_tag', 'p'),
            array(
                'key'          => 'field_mrn_reusable_search_form_intro',
                'label'        => 'Text area with editor',
                'name'         => 'intro',
                'type'         => 'wysiwyg',
                'tabs'         => 'all',
                'toolbar'      => 'full',
                'media_upload' => 1,
                'delay'        => 0,
            ),
            array(
                'key'           => 'field_mrn_reusable_search_form_form_id',
                'label'         => 'Search Form',
                'name'          => 'searchwp_form_id',
                'type'          => 'select',
                'choices'       => function_exists('mrn_base_stack_get_searchwp_form_choices') ? mrn_base_stack_get_searchwp_form_choices() : array(),
                'ui'            => 1,
                'allow_null'    => 1,
                'default_value' => '',
                'placeholder'   => 'Default site search form',
                'instructions'  => 'Choose from the SearchWP forms available on this site. Leave blank to use the default site search form.',
            ),
            array(
                'key'       => 'field_mrn_reusable_search_form_config_tab',
                'label'     => 'Configs',
                'type'      => 'tab',
                'placement' => 'top',
            ),
            mrn_rbl_get_anchor_field('field_mrn_reusable_search_form_anchor'),
            array(
                'key'           => 'field_mrn_reusable_search_form_background_color',
                'label'         => 'Background color',
                'name'          => 'background_color',
                'type'          => 'select',
                'choices'       => mrn_rbl_get_site_color_choices(),
                'ui'            => 1,
                'allow_null'    => 1,
                'instructions'  => 'Select from Site Colors when available.',
            ),
            array(
                'key'           => 'field_mrn_reusable_search_form_bottom_accent',
                'label'         => 'Bottom Accent',
                'name'          => 'bottom_accent',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            array(
                'key'           => 'field_mrn_reusable_search_form_bottom_accent_style',
                'label'         => 'Bottom Accent Style',
                'name'          => 'bottom_accent_style',
                'type'          => 'select',
                'choices'       => function_exists('mrn_site_styles_get_graphic_element_choices')
                    ? mrn_site_styles_get_graphic_element_choices()
                    : array(
                        '' => 'Select a Graphic Element',
                    ),
                'default_value' => '',
                'ui'            => 1,
                'allow_null'    => 1,
                'instructions'  => 'Choose a saved graphic element from Site Styles.',
                'wrapper'       => array(
                    'width' => '50',
                ),
            ),
            mrn_rbl_get_effects_tab_field('field_mrn_reusable_search_form_effects_tab'),
            mrn_rbl_get_motion_group_field('field_mrn_reusable_search_form_motion_settings'),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'mrn_reusable_search',
                ),
            ),
        ),
        'position'              => 'acf_after_title',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'show_in_rest'          => 1,
    )));

    acf_add_local_field_group(mrn_rbl_with_effects_fields(array(
        'key'    => 'group_mrn_reusable_faq',
        'title'  => 'FAQs/Accordion Fields',
        'fields' => array(
            array(
                'key'       => 'field_mrn_faq_content_tab',
                'label'     => 'Content',
                'type'      => 'tab',
                'placement' => 'top',
            ),
            mrn_rbl_get_inline_text_field('field_mrn_faq_label', 'Label', 'label'),
            mrn_rbl_get_label_tag_field('field_mrn_faq_label_tag'),
            mrn_rbl_get_inline_text_field('field_mrn_faq_heading', 'Heading', 'heading'),
            mrn_rbl_get_text_tag_field('field_mrn_faq_heading_tag', 'Heading Tag', 'heading_tag', 'h2'),
            mrn_rbl_get_inline_text_field('field_mrn_faq_subheading', 'Subheading', 'subheading'),
            mrn_rbl_get_text_tag_field('field_mrn_faq_subheading_tag', 'Subheading Tag', 'subheading_tag', 'p'),
            array(
                'key'          => 'field_mrn_faq_items',
                'label'        => 'Items',
                'name'         => 'faq_items',
                'type'         => 'repeater',
                'layout'       => 'block',
                'collapsed'    => 'field_mrn_faq_item_question',
                'min'          => 1,
                'button_label' => 'Add Item',
                'instructions' => 'Add accordion items for this section.',
                'sub_fields'   => array(
                    array(
                        'key'     => 'field_mrn_faq_item_question',
                        'label'   => 'Question / Heading',
                        'name'    => 'question',
                        'type'    => 'text',
                        'instructions' => 'Limited inline HTML allowed: span, strong, em, br.',
                        'wrapper' => array(
                            'width' => '40',
                        ),
                    ),
                    array(
                        'key'          => 'field_mrn_faq_item_answer',
                        'label'        => 'Answer / Text',
                        'name'         => 'answer',
                        'type'         => 'wysiwyg',
                        'tabs'         => 'all',
                        'toolbar'      => 'basic',
                        'media_upload' => 1,
                        'delay'        => 0,
                        'wrapper'      => array(
                            'width' => '60',
                        ),
                    ),
                ),
            ),
            array(
                'key'       => 'field_mrn_faq_config_tab',
                'label'     => 'Configs',
                'type'      => 'tab',
                'placement' => 'top',
            ),
            mrn_rbl_get_anchor_field('field_mrn_faq_anchor'),
            mrn_rbl_get_section_width_field('field_mrn_faq_section_width', 'section_width', 'wide', 'Section Width (Content)'),
            mrn_rbl_get_sub_content_width_field('field_mrn_faq_sub_content_width', 'sub_content_width', 'content', 'Section Width (Sub-content)'),
            array(
                'key'          => 'field_mrn_faq_bg_color',
                'label'        => 'Background color',
                'name'         => 'bg_color',
                'type'         => 'select',
                'choices'      => mrn_rbl_get_site_color_choices(),
                'ui'           => 1,
                'allow_null'   => 1,
                'wrapper'      => array(
                    'width' => '34',
                ),
            ),
            array(
                'key'           => 'field_mrn_faq_start_open',
                'label'         => 'First Item Open',
                'name'          => 'start_open',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'ui_on_text'    => 'Open',
                'ui_off_text'   => 'Closed',
                'wrapper'       => array(
                    'width' => '33',
                ),
            ),
            array(
                'key'           => 'field_mrn_faq_bottom_accent',
                'label'         => 'Accent',
                'name'          => 'bottom_accent',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'ui_on_text'    => 'On',
                'ui_off_text'   => 'Off',
                'wrapper'       => array(
                    'width' => '33',
                ),
            ),
            array(
                'key'           => 'field_mrn_faq_bottom_accent_style',
                'label'         => 'Accent Style',
                'name'          => 'bottom_accent_style',
                'type'          => 'select',
                'choices'       => function_exists('mrn_site_styles_get_graphic_element_choices') ? mrn_site_styles_get_graphic_element_choices() : array('' => 'Select a Graphic Element'),
                'default_value' => '',
                'ui'            => 1,
                'allow_null'    => 1,
                'instructions'  => 'Choose a saved graphic element from Site Styles.',
                'wrapper'       => array(
                    'width' => '34',
                ),
            ),
            mrn_rbl_get_effects_tab_field('field_mrn_faq_effects_tab'),
            mrn_rbl_get_motion_group_field('field_mrn_faq_motion_settings'),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'mrn_reusable_faq',
                ),
            ),
        ),
        'position'              => 'acf_after_title',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'show_in_rest'          => 1,
    )));

}
add_action('acf/init', 'mrn_rbl_register_acf_field_groups');
add_action('acf/init', 'mrn_rbl_auto_enhance_local_field_groups', 100);

/**
 * Check whether a post type belongs to the reusable block library.
 *
 * @param string $post_type
 * @return bool
 */
function mrn_rbl_is_reusable_post_type(string $post_type): bool {
    return in_array($post_type, mrn_rbl_get_post_types(), true);
}

/**
 * Clarify that the native post title is the internal block name.
 *
 * @param string $title
 * @param WP_Post $post
 * @return string
 */
function mrn_rbl_filter_enter_title_here(string $title, WP_Post $post): string {
    if (!mrn_rbl_is_reusable_post_type($post->post_type)) {
        return $title;
    }

    return 'Admin Label for this block';
}
add_filter('enter_title_here', 'mrn_rbl_filter_enter_title_here', 10, 2);

/**
 * Show a short note under the title field so editors understand the distinction.
 */
function mrn_rbl_render_title_guidance(): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen instanceof WP_Screen) {
        return;
    }

    if (!mrn_rbl_is_reusable_post_type($screen->post_type)) {
        return;
    }
    ?>
    <div class="notice notice-info inline" style="margin:12px 0 16px; border-left-width:4px;">
        <p style="margin-bottom:6px;"><strong>Admin Label Only</strong></p>
        <p style="margin-bottom:6px;">Use this title to identify the block in admin lists, pickers, and search results. Use the ACF <strong>Heading</strong> field for the title that appears inside the block on the site.</p>
        <p style="margin-top:0;"><strong>Required:</strong> every reusable block needs an admin label before it can be saved.</p>
    </div>
    <?php
}
add_action('edit_form_after_title', 'mrn_rbl_render_title_guidance');

/**
 * Make the reusable block title behave like a required field in the classic editor.
 */
function mrn_rbl_print_title_required_js(): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen instanceof WP_Screen || !$screen->post_type || !mrn_rbl_is_reusable_post_type($screen->post_type)) {
        return;
    }
    ?>
    <script id="mrn-rbl-title-required">
        jQuery(function($) {
            var $title = $('#title');
            var $form = $('#post');

            if (!$title.length || !$form.length) {
                return;
            }

            function showTitleError() {
                var $notice = $('#mrn-rbl-title-required-notice');

                if (!$notice.length) {
                    $notice = $('<div />', {
                        id: 'mrn-rbl-title-required-notice',
                        class: 'notice notice-error inline'
                    }).append(
                        $('<p />').html('<strong>Admin Label required.</strong> Add a block name before saving this reusable block.')
                    );

                    $('#titlediv').after($notice);
                }

                $title.attr('aria-invalid', 'true').trigger('focus');
            }

            function clearTitleError() {
                $('#mrn-rbl-title-required-notice').remove();
                $title.removeAttr('aria-invalid');
            }

            function titleIsValid() {
                return $.trim($title.val()) !== '';
            }

            $title.attr('required', 'required');

            $title.on('input change', function() {
                if (titleIsValid()) {
                    clearTitleError();
                }
            });

            $form.on('submit', function(event) {
                if (titleIsValid()) {
                    clearTitleError();
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                showTitleError();
            });
        });
    </script>
    <?php
}
add_action('admin_print_footer_scripts', 'mrn_rbl_print_title_required_js');

/**
 * Prevent empty reusable block titles from being saved if the request bypasses the editor UI.
 *
 * @param bool                $maybe_empty
 * @param array<string,mixed> $postarr
 * @return bool
 */
function mrn_rbl_require_title_on_save(bool $maybe_empty, array $postarr): bool {
    $post_type = isset($postarr['post_type']) ? (string) $postarr['post_type'] : '';
    if (!mrn_rbl_is_reusable_post_type($post_type)) {
        return $maybe_empty;
    }

    $title = isset($postarr['post_title']) ? trim((string) $postarr['post_title']) : '';
    if ($title !== '') {
        return $maybe_empty;
    }

    return true;
}
add_filter('wp_insert_post_empty_content', 'mrn_rbl_require_title_on_save', 10, 2);

/**
 * Add a lightweight admin column so editors can see the block slug they can reference elsewhere.
 *
 * @param array<string, string> $columns
 * @return array<string, string>
 */
function mrn_rbl_add_slug_column(array $columns): array {
    $offset_columns = array();

    foreach ($columns as $key => $label) {
        $offset_columns[$key] = $label;

        if ($key === 'title') {
            $offset_columns['mrn_block_slug'] = 'Block Slug';
        }
    }

    if (!isset($offset_columns['mrn_block_slug'])) {
        $offset_columns['mrn_block_slug'] = 'Block Slug';
    }

    return $offset_columns;
}

/**
 * Render the block slug column.
 *
 * @param string $column_name
 * @param int    $post_id
 */
function mrn_rbl_render_slug_column(string $column_name, int $post_id): void {
    if ($column_name !== 'mrn_block_slug') {
        return;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        echo '—';
        return;
    }

    echo esc_html($post->post_name !== '' ? $post->post_name : '(draft)');
}

/**
 * Register admin columns across all library post types.
 */
function mrn_rbl_register_admin_columns(): void {
    foreach (array_keys(mrn_rbl_get_post_type_definitions()) as $post_type) {
        if (!is_string($post_type) || $post_type === '') {
            continue;
        }

        add_filter("manage_{$post_type}_posts_columns", 'mrn_rbl_add_slug_column');
        add_action("manage_{$post_type}_posts_custom_column", 'mrn_rbl_render_slug_column', 10, 2);
    }
}
add_action('init', 'mrn_rbl_register_admin_columns', 20);

/**
 * Tell Post Types Order not to expose reorder interfaces for library CPTs.
 *
 * @param array<string, mixed> $options
 * @return array<string, mixed>
 */
function mrn_rbl_disable_post_types_order_interfaces(array $options): array {
    if (!isset($options['show_reorder_interfaces']) || !is_array($options['show_reorder_interfaces'])) {
        $options['show_reorder_interfaces'] = array();
    }

    foreach (array_keys(mrn_rbl_get_post_type_definitions()) as $post_type) {
        $options['show_reorder_interfaces'][$post_type] = 'hide';
    }

    return $options;
}
add_filter('pto/get_options', 'mrn_rbl_disable_post_types_order_interfaces', PHP_INT_MAX);

/**
 * Remove stray reorder submenu items for the block library as a fallback.
 */
function mrn_rbl_remove_ordering_submenus(): void {
    foreach (array_keys(mrn_rbl_get_post_type_definitions()) as $post_type) {
        $parent_slug = 'edit.php?post_type=' . $post_type;

        remove_submenu_page($parent_slug, 'order-post-types-' . $post_type);
        remove_submenu_page($parent_slug, 'order-post-types-' . str_replace('_', '-', $post_type));

        global $submenu;
        if (!isset($submenu[$parent_slug]) || !is_array($submenu[$parent_slug])) {
            continue;
        }

        foreach ($submenu[$parent_slug] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = isset($item[0]) ? strtolower(wp_strip_all_tags((string) $item[0])) : '';
            $slug  = isset($item[2]) ? strtolower((string) $item[2]) : '';

            // This fallback intentionally matches common ordering labels/slugs from
            // Post Types Order. If that plugin changes its menu naming, this may
            // silently stop removing the extra submenu.
            $is_ordering_item = strpos($title, 'order') !== false
                || strpos($slug, 'order-post-types') !== false
                || strpos($slug, 'post-types-order') !== false
                || strpos($slug, 're-order') !== false
                || strpos($slug, 'reorder') !== false;

            if ($is_ordering_item) {
                unset($submenu[$parent_slug][$index]);
            }
        }
    }
}
add_action('admin_menu', 'mrn_rbl_remove_ordering_submenus', PHP_INT_MAX);

/**
 * Remove legacy generic reusable-block entries once.
 */
function mrn_rbl_cleanup_legacy_generic_posts(): void {
    if (!mrn_rbl_can_run_maintenance()) {
        return;
    }

    if (get_option('mrn_rbl_legacy_cleanup_complete') === '1') {
        return;
    }

    $legacy_posts = get_posts(array(
        'post_type'              => 'mrn_reusable_block',
        'post_status'            => array('publish', 'draft', 'pending', 'private', 'future', 'trash'),
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'suppress_filters'       => true,
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ));

    foreach ($legacy_posts as $legacy_post_id) {
        wp_delete_post((int) $legacy_post_id, true);
    }

    update_option('mrn_rbl_legacy_cleanup_complete', '1', false);
}
add_action('init', 'mrn_rbl_cleanup_legacy_generic_posts', 25);

/**
 * Ensure starter blocks exist for each typed reusable block CPT.
 */
function mrn_rbl_ensure_starter_blocks(): void {
    if (!mrn_rbl_can_run_maintenance()) {
        return;
    }

    $seed_signature = mrn_rbl_get_starter_seed_signature();
    if ($seed_signature !== '' && get_option('mrn_rbl_starter_seed_signature') === $seed_signature) {
        return;
    }

    foreach (mrn_rbl_get_post_type_definitions() as $post_type => $definition) {
        if (!post_type_exists($post_type) || !is_array($definition)) {
            continue;
        }

        $starter_slug  = isset($definition['starter_slug']) ? (string) $definition['starter_slug'] : '';
        $starter_title = isset($definition['starter_title']) ? (string) $definition['starter_title'] : '';

        if ($starter_slug === '' || $starter_title === '') {
            continue;
        }

        $existing_posts = get_posts(array(
            'post_type'              => $post_type,
            'post_status'            => array('publish', 'draft', 'pending', 'private', 'future'),
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'orderby'                => 'date',
            'order'                  => 'ASC',
            'suppress_filters'       => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        $posts_by_slug  = array();
        $posts_by_title = array();

        foreach ($existing_posts as $existing_post_id) {
            $existing_slug  = get_post_field('post_name', $existing_post_id);
            $existing_title = get_the_title($existing_post_id);

            if (is_string($existing_slug) && $existing_slug !== '' && !isset($posts_by_slug[$existing_slug])) {
                $posts_by_slug[$existing_slug] = (int) $existing_post_id;
            }

            if (is_string($existing_title) && $existing_title !== '' && !isset($posts_by_title[$existing_title])) {
                $posts_by_title[$existing_title] = (int) $existing_post_id;
            }
        }

        if (isset($posts_by_slug[$starter_slug])) {
            $existing_post_id = $posts_by_slug[$starter_slug];
            $existing_title   = get_the_title($existing_post_id);

            if ($existing_title !== $starter_title) {
                wp_update_post(array(
                    'ID'         => $existing_post_id,
                    'post_title' => $starter_title,
                ));
            }

            continue;
        }

        if (isset($posts_by_title[$starter_title])) {
            wp_update_post(array(
                'ID'        => $posts_by_title[$starter_title],
                'post_name' => $starter_slug,
            ));

            continue;
        }

        $post_id = wp_insert_post(array(
            'post_type'    => $post_type,
            'post_status'  => 'draft',
            'post_title'   => $starter_title,
            'post_name'    => $starter_slug,
            'post_content' => '',
        ), true);

        if (is_wp_error($post_id)) {
            return;
        }
    }

    if ($seed_signature !== '') {
        update_option('mrn_rbl_starter_seed_signature', $seed_signature, false);
    }
}
add_action('init', 'mrn_rbl_ensure_starter_blocks', 30);
