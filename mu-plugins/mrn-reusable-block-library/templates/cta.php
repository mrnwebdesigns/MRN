<?php
/**
 * CTA reusable block template.
 *
 * @var array<string, mixed> $context
 */

$fields      = isset($context['fields']) && is_array($context['fields']) ? $context['fields'] : array();
$label       = isset($fields['label']) ? trim((string) $fields['label']) : '';
$label_tag   = function_exists('mrn_rbl_normalize_text_tag') ? mrn_rbl_normalize_text_tag($fields['label_tag'] ?? '', 'p') : 'p';
$heading     = isset($fields['heading']) ? (string) $fields['heading'] : '';
$heading_tag = isset($fields['heading_tag']) ? sanitize_key((string) $fields['heading_tag']) : 'h2';
$subheading     = isset($fields['subheading']) ? (string) $fields['subheading'] : '';
$subheading_tag = isset($fields['subheading_tag']) ? sanitize_key((string) $fields['subheading_tag']) : 'p';
$copy        = isset($fields['content']) ? (string) $fields['content'] : '';
$image       = $fields['image'] ?? null;
$has_image   = function_exists('mrn_rbl_image_has_content') ? mrn_rbl_image_has_content($image) : false;
$image_place = isset($fields['image_placement']) ? sanitize_key((string) $fields['image_placement']) : 'left';
$background_image = $fields['background_image'] ?? null;
$background_video = isset($fields['background_video']) ? (string) $fields['background_video'] : '';
$background_video_upload = $fields['background_video_upload'] ?? null;
$bg_color    = isset($fields['bg_color']) ? sanitize_title((string) $fields['bg_color']) : '';
$link_color  = isset($fields['link_color']) ? sanitize_title((string) $fields['link_color']) : '';
$accent      = !empty($fields['bottom_accent']);
$accent_slug = isset($fields['bottom_accent_style']) ? (string) $fields['bottom_accent_style'] : '';
$post_id     = isset($context['post_id']) ? (int) $context['post_id'] : 0;
$post_name   = isset($context['post_name']) ? (string) $context['post_name'] : '';

if (!in_array($heading_tag, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span'), true)) {
    $heading_tag = 'h2';
}
if (!in_array($subheading_tag, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span'), true)) {
    $subheading_tag = 'p';
}
if (!in_array($image_place, array('left', 'right'), true)) {
    $image_place = 'left';
}

$links = function_exists('mrn_rbl_get_content_links')
    ? mrn_rbl_get_content_links(
        $fields,
        array(
            'max' => 4,
        )
    )
    : array();

$cta_links      = array();
$cta_link_slots = array('primary', 'secondary', 'tertiary', 'quaternary');

foreach ($links as $index => $link) {
    if (!is_array($link)) {
        continue;
    }

    $link_url           = isset($link['url']) ? (string) $link['url'] : '';
    $link_text          = isset($link['text']) ? (string) $link['text'] : '';
    $link_style         = isset($link['link_style']) && in_array($link['link_style'], array('link', 'button'), true) ? (string) $link['link_style'] : 'button';
    $link_tag           = function_exists('mrn_rbl_get_content_link_tag_name') ? mrn_rbl_get_content_link_tag_name($link) : 'a';
    $link_attr_html     = function_exists('mrn_rbl_get_content_link_html_attributes') ? mrn_rbl_get_content_link_html_attributes($link) : '';
    $link_class_names   = 'mrn-ui__link mrn-ui__link--' . sanitize_html_class($cta_link_slots[$index] ?? 'secondary') . ' ' . ( 'button' === $link_style ? 'mrn-ui__link--button' : 'mrn-ui__link--text' );
    $link_icon_markup   = function_exists('mrn_base_stack_get_button_link_icon_markup')
        ? mrn_base_stack_get_button_link_icon_markup($link)
        : '';
    $link_icon_position = function_exists('mrn_base_stack_get_button_link_icon_position')
        ? mrn_base_stack_get_button_link_icon_position($link)
        : 'left';

    if (function_exists('mrn_rbl_get_content_link_custom_class_names')) {
        $link_custom_classes = mrn_rbl_get_content_link_custom_class_names($link);
        if ('' !== $link_custom_classes) {
            $link_class_names .= ' ' . $link_custom_classes;
        }
    }

    $cta_links[] = array(
        'url'           => $link_url,
        'text'          => $link_text,
        'style'         => $link_style,
        'tag'           => $link_tag,
        'attr_html'     => $link_attr_html,
        'class_names'   => $link_class_names,
        'icon_markup'   => $link_icon_markup,
        'icon_position' => $link_icon_position,
    );
}

$primary_link_style = isset($cta_links[0]['style']) ? (string) $cta_links[0]['style'] : 'button';

$classes = array(
    'mrn-reusable-block',
    'mrn-reusable-block--cta',
    'mrn-reusable-block--cta-link-' . $primary_link_style,
    'mrn-reusable-block--cta-image-' . sanitize_html_class($image_place),
);

if ($has_image) {
    $classes[] = 'has-content-image';
}

$accent_contract = function_exists('mrn_site_styles_get_bottom_accent_contract')
    ? mrn_site_styles_get_bottom_accent_contract($accent, $accent_slug)
    : array(
        'classes'    => $accent ? array('has-bottom-accent') : array(),
        'attributes' => array(),
    );

if (isset($accent_contract['classes']) && is_array($accent_contract['classes'])) {
    $classes = array_merge($classes, $accent_contract['classes']);
}

$motion_contract = function_exists('mrn_rbl_get_motion_contract') ? mrn_rbl_get_motion_contract($fields, $context) : array(
    'classes'    => array(),
    'attributes' => array(),
);

if (isset($motion_contract['classes']) && is_array($motion_contract['classes'])) {
    $classes = array_merge($classes, $motion_contract['classes']);
}

$styles = array();
if ($bg_color !== '') {
    $styles[] = function_exists('mrn_site_colors_get_css_var')
        ? '--mrn-cta-bg: var(' . mrn_site_colors_get_css_var($bg_color) . ')'
        : '--mrn-cta-bg: var(--site-color-' . $bg_color . ')';
}

$background_gradient_style = function_exists('mrn_base_stack_get_background_gradient_style_declaration')
    ? mrn_base_stack_get_background_gradient_style_declaration($fields, '--mrn-cta-bg-gradient')
    : '';

$background_image_markup = function_exists('mrn_base_stack_get_background_image_markup')
    ? mrn_base_stack_get_background_image_markup($background_image)
    : '';
$background_video_markup = function_exists('mrn_base_stack_get_background_video_markup')
    ? mrn_base_stack_get_background_video_markup(
        $background_video,
        $background_video_upload,
        array(
            'poster_image' => $background_image,
        )
    )
    : '';

$has_gradient_data = !empty($fields['background_gradient_enabled'])
    || !empty($fields['background_gradient_start_color'])
    || !empty($fields['background_gradient_end_color']);

$has_cta_data = $label !== ''
    || trim(wp_strip_all_tags($heading)) !== ''
    || trim(wp_strip_all_tags($subheading)) !== ''
    || trim($copy) !== ''
    || $has_image
    || !empty($image)
    || !empty($cta_links)
    || $bg_color !== ''
    || $link_color !== ''
    || $accent
    || $accent_slug !== ''
    || $has_gradient_data
    || !empty($background_image)
    || $background_image_markup !== ''
    || trim($background_video) !== ''
    || !empty($background_video_upload);

if (!$has_cta_data) {
    return;
}

if ($background_gradient_style !== '') {
    $styles[] = $background_gradient_style;
    $classes[] = 'has-background-gradient';
}

if ($background_image_markup !== '') {
    $classes[] = 'has-background-image';
    $classes[] = 'has-row-background-media';
}

if ($background_video_markup !== '') {
    $classes[] = 'has-background-video';
    $classes[] = 'has-row-background-video';
}

if ($link_color !== '') {
    $styles[] = function_exists('mrn_site_colors_get_css_var')
        ? '--mrn-cta-link-color: var(' . mrn_site_colors_get_css_var($link_color) . ')'
        : '--mrn-cta-link-color: var(--site-color-' . $link_color . ')';
}

$section_attrs = isset($accent_contract['attributes']) && is_array($accent_contract['attributes']) ? $accent_contract['attributes'] : array();
$section_attrs = function_exists('mrn_rbl_merge_attributes') ? mrn_rbl_merge_attributes($section_attrs, isset($motion_contract['attributes']) && is_array($motion_contract['attributes']) ? $motion_contract['attributes'] : array()) : array_merge($section_attrs, isset($motion_contract['attributes']) && is_array($motion_contract['attributes']) ? $motion_contract['attributes'] : array());
$section_attr_html = function_exists('mrn_rbl_get_html_attributes') ? mrn_rbl_get_html_attributes($section_attrs) : '';

echo function_exists('mrn_rbl_get_anchor_markup') ? mrn_rbl_get_anchor_markup($context) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
?>
<section
    class="<?php echo esc_attr(implode(' ', $classes)); ?>"
    data-block-id="<?php echo esc_attr((string) $post_id); ?>"
    data-block-slug="<?php echo esc_attr($post_name); ?>"
    <?php echo '' !== $section_attr_html ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php if ($styles !== array()) : ?>
        style="<?php echo esc_attr(implode('; ', $styles)); ?>"
    <?php endif; ?>
>
	    <?php echo $background_image_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Decorative image markup is escaped in the helper. ?>
	    <?php echo $background_video_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Decorative video markup is escaped in the helper. ?>
	    <div class="mrn-reusable-block__inner mrn-reusable-block__inner--callout mrn-reusable-block__cta-inner mrn-layout-grid--media-stack">
	        <?php if ($has_image) : ?>
	            <div class="mrn-reusable-block__media mrn-reusable-block__media--cta mrn-layout-content--media-stack-media mrn-ui__media">
	                <?php echo function_exists('mrn_rbl_get_attachment_image') ? mrn_rbl_get_attachment_image($image, 'mrn-content-media') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	            </div>
	        <?php endif; ?>

	        <div class="mrn-reusable-block__content mrn-reusable-block__content--cta mrn-layout-content--media-stack-text mrn-ui__body">
	        <?php if ($label !== '' || $heading !== '' || $subheading !== '') : ?>
	            <div class="mrn-ui__head">
                <?php if ($label !== '') : ?>
	                    <<?php echo esc_html($label_tag); ?> class="mrn-ui__label">
                        <?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($label) : esc_html($label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </<?php echo esc_html($label_tag); ?>>
                <?php endif; ?>

                <?php if ($heading !== '') : ?>
	                    <<?php echo esc_html($heading_tag); ?> class="mrn-ui__heading">
                        <?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($heading) : esc_html($heading); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </<?php echo esc_html($heading_tag); ?>>
                <?php endif; ?>

                <?php if ($subheading !== '') : ?>
	                    <<?php echo esc_html($subheading_tag); ?> class="mrn-ui__sub">
                        <?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($subheading) : esc_html($subheading); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </<?php echo esc_html($subheading_tag); ?>>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($copy !== '') : ?>
	            <div class="mrn-ui__text">
                <?php echo apply_filters('the_content', $copy); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($cta_links)) : ?>
	            <div class="mrn-reusable-block__actions mrn-reusable-block__actions--callout mrn-ui__actions">
                <?php foreach ($cta_links as $cta_link) : ?>
                <<?php echo esc_html($cta_link['tag']); ?>
		                    class="<?php echo esc_attr(trim((string) $cta_link['class_names'])); ?>"
	                    <?php echo '' !== $cta_link['attr_html'] ? $cta_link['attr_html'] : 'href="' . esc_url((string) $cta_link['url']) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                >
                    <?php
                    $cta_link_label = '' !== $cta_link['text'] ? (string) $cta_link['text'] : (string) $cta_link['url'];
                    echo function_exists('mrn_base_stack_get_compact_link_label_markup')
                        ? mrn_base_stack_get_compact_link_label_markup($cta_link_label, (string) $cta_link['icon_markup'], (string) $cta_link['icon_position'])
                        : esc_html($cta_link_label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes text and icon markup is escaped at source.
                    ?>
                </<?php echo esc_html($cta_link['tag']); ?>>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>
</section>
