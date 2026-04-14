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
$background_image = isset($fields['background_image']) && is_array($fields['background_image']) ? $fields['background_image'] : array();
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

$links = function_exists('mrn_rbl_get_content_links')
    ? mrn_rbl_get_content_links(
        $fields,
        array(
            'max' => 2,
        )
    )
    : array();

$primary_link   = isset($links[0]) && is_array($links[0]) ? $links[0] : array();
$secondary_link = isset($links[1]) && is_array($links[1]) ? $links[1] : array();

$primary_link_url           = isset($primary_link['url']) ? (string) $primary_link['url'] : '';
$primary_link_text          = isset($primary_link['text']) ? (string) $primary_link['text'] : '';
$primary_link_style         = isset($primary_link['link_style']) && in_array($primary_link['link_style'], array('link', 'button'), true) ? (string) $primary_link['link_style'] : 'button';
$primary_link_tag           = function_exists('mrn_rbl_get_content_link_tag_name') ? mrn_rbl_get_content_link_tag_name($primary_link) : 'a';
$primary_link_attr_html     = function_exists('mrn_rbl_get_content_link_html_attributes') ? mrn_rbl_get_content_link_html_attributes($primary_link) : '';
$primary_link_class_names   = 'mrn-ui__link ' . ( 'button' === $primary_link_style ? 'mrn-ui__link--button' : 'mrn-ui__link--text' );
$primary_link_icon_markup   = 'button' === $primary_link_style && function_exists('mrn_base_stack_get_button_link_icon_markup')
    ? mrn_base_stack_get_button_link_icon_markup($primary_link)
    : '';
$primary_link_icon_position = 'button' === $primary_link_style && function_exists('mrn_base_stack_get_button_link_icon_position')
    ? mrn_base_stack_get_button_link_icon_position($primary_link)
    : 'left';
$secondary_link_url           = isset($secondary_link['url']) ? (string) $secondary_link['url'] : '';
$secondary_link_text          = isset($secondary_link['text']) ? (string) $secondary_link['text'] : '';
$secondary_link_style         = isset($secondary_link['link_style']) && in_array($secondary_link['link_style'], array('link', 'button'), true) ? (string) $secondary_link['link_style'] : 'button';
$secondary_link_tag           = function_exists('mrn_rbl_get_content_link_tag_name') ? mrn_rbl_get_content_link_tag_name($secondary_link) : 'a';
$secondary_link_attr_html     = function_exists('mrn_rbl_get_content_link_html_attributes') ? mrn_rbl_get_content_link_html_attributes($secondary_link) : '';
$secondary_link_class_names   = 'mrn-ui__link mrn-ui__link--secondary ' . ( 'button' === $secondary_link_style ? 'mrn-ui__link--button' : 'mrn-ui__link--text' );
$secondary_link_icon_markup   = 'button' === $secondary_link_style && function_exists('mrn_base_stack_get_button_link_icon_markup')
    ? mrn_base_stack_get_button_link_icon_markup($secondary_link)
    : '';
$secondary_link_icon_position = 'button' === $secondary_link_style && function_exists('mrn_base_stack_get_button_link_icon_position')
    ? mrn_base_stack_get_button_link_icon_position($secondary_link)
    : 'left';

if (function_exists('mrn_rbl_get_content_link_custom_class_names')) {
    $primary_link_custom_classes = mrn_rbl_get_content_link_custom_class_names($primary_link);
    if ('' !== $primary_link_custom_classes) {
        $primary_link_class_names .= ' ' . $primary_link_custom_classes;
    }

    $secondary_link_custom_classes = mrn_rbl_get_content_link_custom_class_names($secondary_link);
    if ('' !== $secondary_link_custom_classes) {
        $secondary_link_class_names .= ' ' . $secondary_link_custom_classes;
    }
}

if ($label === '' && $heading === '' && $subheading === '' && $copy === '' && $primary_link_url === '' && $secondary_link_url === '') {
    return;
}

$classes = array(
    'mrn-reusable-block',
    'mrn-reusable-block--cta',
    'mrn-reusable-block--cta-link-' . ( '' !== $primary_link_url ? $primary_link_style : ( '' !== $secondary_link_url ? $secondary_link_style : 'button' ) ),
);

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
    $styles[] = '--mrn-cta-bg: var(--site-color-' . $bg_color . ')';
}

$background_image_style = function_exists('mrn_base_stack_get_background_image_style')
    ? mrn_base_stack_get_background_image_style($background_image, '--mrn-cta-bg-image')
    : '';

if ($background_image_style !== '') {
    $styles[] = $background_image_style;
    $classes[] = 'has-background-image';
}

if ($link_color !== '') {
    $styles[] = '--mrn-cta-link-color: var(--site-color-' . $link_color . ')';
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
	    <div class="mrn-reusable-block__inner mrn-reusable-block__inner--callout mrn-ui__body">
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

        <?php if ($primary_link_url !== '' || $secondary_link_url !== '') : ?>
	            <div class="mrn-reusable-block__actions mrn-reusable-block__actions--callout mrn-ui__actions">
                <?php if ($primary_link_url !== '') : ?>
                <<?php echo esc_html($primary_link_tag); ?>
		                    class="<?php echo esc_attr(trim($primary_link_class_names)); ?>"
	                    <?php echo '' !== $primary_link_attr_html ? $primary_link_attr_html : 'href="' . esc_url($primary_link_url) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                >
                    <?php if ('left' === $primary_link_icon_position) : ?>
                        <?php echo $primary_link_icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon markup is escaped in the helper. ?>
                    <?php endif; ?>
                    <?php echo esc_html($primary_link_text !== '' ? $primary_link_text : $primary_link_url); ?>
                    <?php if ('right' === $primary_link_icon_position) : ?>
                        <?php echo $primary_link_icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon markup is escaped in the helper. ?>
                    <?php endif; ?>
                </<?php echo esc_html($primary_link_tag); ?>>
                <?php endif; ?>

                <?php if ($secondary_link_url !== '') : ?>
                <<?php echo esc_html($secondary_link_tag); ?>
		                    class="<?php echo esc_attr(trim($secondary_link_class_names)); ?>"
	                    <?php echo '' !== $secondary_link_attr_html ? $secondary_link_attr_html : 'href="' . esc_url($secondary_link_url) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                >
                    <?php if ('left' === $secondary_link_icon_position) : ?>
                        <?php echo $secondary_link_icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon markup is escaped in the helper. ?>
                    <?php endif; ?>
                    <?php echo esc_html($secondary_link_text !== '' ? $secondary_link_text : $secondary_link_url); ?>
                    <?php if ('right' === $secondary_link_icon_position) : ?>
                        <?php echo $secondary_link_icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon markup is escaped in the helper. ?>
                    <?php endif; ?>
                </<?php echo esc_html($secondary_link_tag); ?>>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
