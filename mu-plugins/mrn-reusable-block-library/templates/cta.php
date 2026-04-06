<?php
/**
 * CTA reusable block template.
 *
 * @var array<string, mixed> $context
 */

$fields      = isset($context['fields']) && is_array($context['fields']) ? $context['fields'] : array();
$label       = isset($fields['label']) ? trim((string) $fields['label']) : '';
$label_tag   = function_exists('mrn_rbl_normalize_text_tag') ? mrn_rbl_normalize_text_tag($fields['label_tag'] ?? '', 'p') : 'p';
$heading     = isset($fields['text_field']) ? (string) $fields['text_field'] : '';
$heading_tag = isset($fields['text_field_tag']) ? sanitize_key((string) $fields['text_field_tag']) : 'h2';
$copy        = isset($fields['content']) ? (string) $fields['content'] : '';
$primary_link   = isset($fields['primary_link']) && is_array($fields['primary_link']) ? $fields['primary_link'] : array();
$secondary_link = isset($fields['secondary_link']) && is_array($fields['secondary_link']) ? $fields['secondary_link'] : array();
$background_image = isset($fields['background_image']) && is_array($fields['background_image']) ? $fields['background_image'] : array();
$primary_link_url    = isset($primary_link['url']) ? (string) $primary_link['url'] : '';
$primary_link_title  = isset($primary_link['title']) ? (string) $primary_link['title'] : '';
$primary_link_target = isset($primary_link['target']) ? (string) $primary_link['target'] : '';
$secondary_link_url    = isset($secondary_link['url']) ? (string) $secondary_link['url'] : '';
$secondary_link_title  = isset($secondary_link['title']) ? (string) $secondary_link['title'] : '';
$secondary_link_target = isset($secondary_link['target']) ? (string) $secondary_link['target'] : '';
$link_style  = isset($fields['link_style']) ? sanitize_key((string) $fields['link_style']) : 'button';
$bg_color    = isset($fields['bg_color']) ? sanitize_title((string) $fields['bg_color']) : '';
$link_color  = isset($fields['link_color']) ? sanitize_title((string) $fields['link_color']) : '';
$accent      = !empty($fields['bottom_accent']);
$accent_slug = isset($fields['bottom_accent_style']) ? (string) $fields['bottom_accent_style'] : '';
$post_id     = isset($context['post_id']) ? (int) $context['post_id'] : 0;
$post_name   = isset($context['post_name']) ? (string) $context['post_name'] : '';

if (!in_array($heading_tag, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span'), true)) {
    $heading_tag = 'h2';
}

if (!in_array($link_style, array('link', 'button'), true)) {
    $link_style = 'button';
}

if ($label === '' && $heading === '' && $copy === '' && $primary_link_url === '' && $secondary_link_url === '') {
    return;
}

$classes = array(
    'mrn-reusable-block',
    'mrn-reusable-block--cta',
    'mrn-reusable-block--cta-link-' . $link_style,
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
?>
<section
    class="<?php echo esc_attr(implode(' ', $classes)); ?>"
    data-block-id="<?php echo esc_attr((string) $post_id); ?>"
    data-block-slug="<?php echo esc_attr($post_name); ?>"
    <?php if (isset($accent_contract['attributes']) && is_array($accent_contract['attributes'])) : ?>
        <?php foreach ($accent_contract['attributes'] as $attribute_name => $attribute_value) : ?>
            <?php if ($attribute_name !== '' && $attribute_value !== '') : ?>
                <?php echo ' ' . esc_attr($attribute_name) . '="' . esc_attr($attribute_value) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($styles !== array()) : ?>
        style="<?php echo esc_attr(implode('; ', $styles)); ?>"
    <?php endif; ?>
>
    <div class="mrn-reusable-block__inner mrn-reusable-block__inner--callout">
        <?php if ($label !== '') : ?>
            <<?php echo esc_html($label_tag); ?> class="mrn-reusable-block__label">
                <?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($label) : esc_html($label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </<?php echo esc_html($label_tag); ?>>
        <?php endif; ?>

        <?php if ($heading !== '') : ?>
            <<?php echo esc_html($heading_tag); ?> class="mrn-reusable-block__heading">
                <?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($heading) : esc_html($heading); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </<?php echo esc_html($heading_tag); ?>>
        <?php endif; ?>

        <?php if ($copy !== '') : ?>
            <div class="mrn-reusable-block__text">
                <?php echo apply_filters('the_content', $copy); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>

        <?php if ($primary_link_url !== '' || $secondary_link_url !== '') : ?>
            <div class="mrn-reusable-block__actions mrn-reusable-block__actions--callout">
                <?php if ($primary_link_url !== '') : ?>
                <a
                    class="mrn-reusable-block__link <?php echo 'button' === $link_style ? 'mrn-reusable-block__link--button' : 'mrn-reusable-block__link--text'; ?>"
                    href="<?php echo esc_url($primary_link_url); ?>"
                    <?php if ($primary_link_target !== '') : ?>
                        target="<?php echo esc_attr($primary_link_target); ?>"
                    <?php endif; ?>
                    <?php if ('_blank' === $primary_link_target) : ?>
                        rel="noopener noreferrer"
                    <?php endif; ?>
                >
                    <?php echo esc_html($primary_link_title !== '' ? $primary_link_title : $primary_link_url); ?>
                </a>
                <?php endif; ?>

                <?php if ($secondary_link_url !== '') : ?>
                <a
                    class="mrn-reusable-block__link mrn-reusable-block__link--secondary <?php echo 'button' === $link_style ? 'mrn-reusable-block__link--button' : 'mrn-reusable-block__link--text'; ?>"
                    href="<?php echo esc_url($secondary_link_url); ?>"
                    <?php if ($secondary_link_target !== '') : ?>
                        target="<?php echo esc_attr($secondary_link_target); ?>"
                    <?php endif; ?>
                    <?php if ('_blank' === $secondary_link_target) : ?>
                        rel="noopener noreferrer"
                    <?php endif; ?>
                >
                    <?php echo esc_html($secondary_link_title !== '' ? $secondary_link_title : $secondary_link_url); ?>
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
