<?php
/**
 * Content Grid reusable block template.
 *
 * @var array<string, mixed> $context
 */

$fields          = isset($context['fields']) && is_array($context['fields']) ? $context['fields'] : array();
$label           = isset($fields['label']) ? trim((string) $fields['label']) : '';
$label_tag       = function_exists('mrn_rbl_normalize_text_tag') ? mrn_rbl_normalize_text_tag($fields['label_tag'] ?? '', 'p') : 'p';
$heading         = isset($fields['heading']) ? (string) $fields['heading'] : '';
$heading_tag     = isset($fields['heading_tag']) ? sanitize_key((string) $fields['heading_tag']) : 'h2';
$subheading      = isset($fields['subheading']) ? (string) $fields['subheading'] : '';
$subheading_tag  = isset($fields['subheading_tag']) ? sanitize_key((string) $fields['subheading_tag']) : 'p';
$grid_items      = isset($fields['grid_items']) && is_array($fields['grid_items']) ? $fields['grid_items'] : array();
$bg_color        = isset($fields['bg_color']) ? sanitize_title((string) $fields['bg_color']) : '';
$accent          = !empty($fields['bottom_accent']);
$accent_slug     = isset($fields['bottom_accent_style']) ? (string) $fields['bottom_accent_style'] : '';
$item_link_style = isset($fields['link_style']) ? sanitize_key((string) $fields['link_style']) : 'link';
$link_color      = isset($fields['link_color']) ? sanitize_title((string) $fields['link_color']) : '';
$post_id        = isset($context['post_id']) ? (int) $context['post_id'] : 0;
$post_name      = isset($context['post_name']) ? (string) $context['post_name'] : '';

if (!in_array($heading_tag, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span'), true)) {
    $heading_tag = 'h2';
}
if (!in_array($subheading_tag, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span'), true)) {
    $subheading_tag = 'p';
}

if (!in_array($item_link_style, array('link', 'button'), true)) {
    $item_link_style = 'link';
}

$classes = array(
    'mrn-reusable-block',
    'mrn-reusable-block--content-grid',
    'mrn-reusable-block--grid-link-' . $item_link_style,
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
    $styles[] = '--mrn-content-grid-bg: var(--site-color-' . $bg_color . ')';
}

if ($link_color !== '') {
    $styles[] = '--mrn-content-grid-link-color: var(--site-color-' . $link_color . ')';
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
    <div class="mrn-content-grid mrn-content-grid--collection-shell">
        <?php if ($label !== '') : ?>
            <<?php echo esc_html($label_tag); ?> class="mrn-content-grid__label">
                <?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($label) : esc_html($label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </<?php echo esc_html($label_tag); ?>>
        <?php endif; ?>

        <?php if ($heading !== '') : ?>
            <<?php echo esc_html($heading_tag); ?> class="mrn-content-grid__heading">
                <?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($heading) : esc_html($heading); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </<?php echo esc_html($heading_tag); ?>>
        <?php endif; ?>

        <?php if ($subheading !== '') : ?>
            <<?php echo esc_html($subheading_tag); ?> class="mrn-shell-section__subheading">
                <?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($subheading) : esc_html($subheading); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </<?php echo esc_html($subheading_tag); ?>>
        <?php endif; ?>

        <?php if ($grid_items !== array()) : ?>
            <div class="mrn-content-grid__items mrn-content-grid__items--collection-shell">
                <?php foreach ($grid_items as $item) : ?>
                    <?php
                    if (!is_array($item)) {
                        continue;
                    }

                    $item_label  = isset($item['label']) ? (string) $item['label'] : '';
                    $item_label_tag = function_exists('mrn_rbl_normalize_text_tag') ? mrn_rbl_normalize_text_tag($item['label_tag'] ?? '', 'p') : 'p';
                    $item_heading  = isset($item['heading']) ? (string) $item['heading'] : '';
                    $item_heading_tag = isset($item['heading_tag']) ? sanitize_key((string) $item['heading_tag']) : 'h3';
                    $item_copy   = isset($item['content']) ? (string) $item['content'] : '';
                    $item_link   = isset($item['link']) && is_array($item['link']) ? $item['link'] : array();
                    $link_url    = isset($item_link['url']) ? (string) $item_link['url'] : '';
                    $link_title  = isset($item_link['title']) ? (string) $item_link['title'] : '';
                    $link_target = isset($item_link['target']) ? (string) $item_link['target'] : '';

                    if (!in_array($item_heading_tag, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span'), true)) {
                        $item_heading_tag = 'h3';
                    }

                    if ($item_label === '' && $item_heading === '' && $item_copy === '' && $link_url === '') {
                        continue;
                    }
                    ?>
                    <article class="mrn-content-grid__item mrn-content-grid__item--collection-shell">
                        <?php if ($item_label !== '') : ?>
                            <<?php echo esc_html($item_label_tag); ?> class="mrn-content-grid__item-label"><?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($item_label) : esc_html($item_label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html($item_label_tag); ?>>
                        <?php endif; ?>

                        <?php if ($item_heading !== '') : ?>
                            <<?php echo esc_html($item_heading_tag); ?> class="mrn-content-grid__item-title"><?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($item_heading) : esc_html($item_heading); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html($item_heading_tag); ?>>
                        <?php endif; ?>

                        <?php if ($item_copy !== '') : ?>
                            <div class="mrn-content-grid__item-copy">
                                <?php echo apply_filters('the_content', $item_copy); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($link_url !== '') : ?>
                            <div class="mrn-content-grid__item-link-wrap">
                                <a
                                    class="mrn-content-grid__item-link <?php echo 'button' === $item_link_style ? 'mrn-content-grid__item-link--button' : 'mrn-content-grid__item-link--text'; ?>"
                                    href="<?php echo esc_url($link_url); ?>"
                                    <?php if ($link_target !== '') : ?>
                                        target="<?php echo esc_attr($link_target); ?>"
                                    <?php endif; ?>
                                    <?php if ('_blank' === $link_target) : ?>
                                        rel="noopener noreferrer"
                                    <?php endif; ?>
                                >
                                    <?php echo esc_html($link_title !== '' ? $link_title : $link_url); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
