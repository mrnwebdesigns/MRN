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
$columns         = isset($fields['columns']) ? (string) $fields['columns'] : '3';
$equal_height    = !empty($fields['equal_height']);
$accent          = !empty($fields['bottom_accent']);
$accent_slug     = isset($fields['bottom_accent_style']) ? (string) $fields['bottom_accent_style'] : '';
$item_link_style = isset($fields['link_style']) ? sanitize_key((string) $fields['link_style']) : 'link';
$enable_full_item_link = !empty($fields['enable_full_item_link']);
$hide_item_link = $enable_full_item_link && !empty($fields['hide_item_link']);
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

if (!in_array($columns, array('2', '3', '4'), true)) {
    $columns = '3';
}

$classes = array(
    'mrn-reusable-block',
    'mrn-reusable-block--content-grid',
    'mrn-reusable-block--grid-columns-' . $columns,
    'mrn-reusable-block--grid-link-' . $item_link_style,
);

if ($equal_height) {
    $classes[] = 'mrn-reusable-block--grid-equal-height';
}
if ($enable_full_item_link) {
    $classes[] = 'mrn-reusable-block--grid-full-link';
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
    $styles[] = '--mrn-content-grid-bg: var(--site-color-' . $bg_color . ')';
}

if ($link_color !== '') {
    $styles[] = '--mrn-content-grid-link-color: var(--site-color-' . $link_color . ')';
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
	    <div class="mrn-content-grid mrn-content-grid--collection-shell mrn-ui__body">
	        <?php if ($label !== '' || $heading !== '' || $subheading !== '') : ?>
	            <div class="mrn-content-grid__head mrn-ui__head">
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

        <?php if ($grid_items !== array()) : ?>
	            <div class="mrn-content-grid__items mrn-content-grid__items--collection-shell mrn-ui__items">
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
                    $item_background_color = isset($item['background_color']) ? sanitize_title((string) $item['background_color']) : '';
                    foreach (array('item_url', 'link_url', 'url') as $legacy_url_key) {
                        if (isset($item[$legacy_url_key]) && is_string($item[$legacy_url_key]) && trim($item[$legacy_url_key]) !== '') {
                            $item['url'] = trim($item[$legacy_url_key]);
                            break;
                        }
                    }
                    if (isset($item['link']) && is_string($item['link']) && trim($item['link']) !== '') {
                        $item['url'] = trim($item['link']);
                    }
                    $normalized_link = function_exists('mrn_base_stack_get_repeater_item_primary_link')
                        ? mrn_base_stack_get_repeater_item_primary_link(
                            $item,
                            array(
                                'fallback_link_style' => $item_link_style,
                            )
                        )
                        : (function_exists('mrn_rbl_normalize_content_link')
                            ? mrn_rbl_normalize_content_link(
                                $item,
                                array(
                                    'fallback_link_style' => $item_link_style,
                                )
                            )
                            : array());
                    $link_url = isset($normalized_link['url']) ? (string) $normalized_link['url'] : '';
                    $link_text = isset($normalized_link['text']) ? (string) $normalized_link['text'] : '';
                    $link_style = isset($normalized_link['link_style']) ? (string) $normalized_link['link_style'] : $item_link_style;
                    $link_attr_html = function_exists('mrn_rbl_get_content_link_html_attributes')
                        ? mrn_rbl_get_content_link_html_attributes($normalized_link)
                        : '';
                    $link_custom_classes = function_exists('mrn_rbl_get_content_link_custom_class_names')
                        ? mrn_rbl_get_content_link_custom_class_names($normalized_link)
                        : '';
                    $link_class_names = 'mrn-ui__link ' . ('button' === $link_style ? 'mrn-ui__link--button' : 'mrn-ui__link--text');
                    $link_icon_markup = function_exists('mrn_base_stack_get_button_link_icon_markup')
                        ? mrn_base_stack_get_button_link_icon_markup($normalized_link)
                        : '';
                    $link_icon_position = function_exists('mrn_base_stack_get_button_link_icon_position')
                        ? mrn_base_stack_get_button_link_icon_position($normalized_link)
                        : 'left';
                    $link_label = $link_text !== '' ? $link_text : $link_url;
                    $link_aria_label = $link_text !== ''
                        ? $link_text
                        : ($item_heading !== ''
                            ? wp_strip_all_tags($item_heading)
                            : __('View grid item', 'mrn-reusable-block-library'));
                    $item_classes = array(
                        'mrn-content-grid__item',
                        'mrn-content-grid__item--collection-shell',
                        'mrn-ui__item',
                    );
                    $item_styles = array();

                    if ($link_custom_classes !== '') {
                        $link_class_names .= ' ' . $link_custom_classes;
                    }

                    if (!in_array($item_heading_tag, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span'), true)) {
                        $item_heading_tag = 'h3';
                    }

                    if ($item_label === '' && $item_heading === '' && $item_copy === '' && $link_url === '') {
                        continue;
                    }

                    if ($enable_full_item_link && $link_url !== '') {
                        $item_classes[] = 'mrn-content-grid__item--full-link';
                    }

                    if ($item_background_color !== '') {
                        $item_styles[] = '--mrn-content-grid-item-bg: var(--site-color-' . $item_background_color . ')';
                    }
                    ?>
                    <article class="<?php echo esc_attr(implode(' ', $item_classes)); ?>"<?php echo $item_styles !== array() ? ' style="' . esc_attr(implode('; ', $item_styles)) . '"' : ''; ?>>
                        <div class="mrn-content-grid__item-body mrn-ui__body">
                            <?php if ($item_label !== '' || $item_heading !== '') : ?>
                                <div class="mrn-content-grid__item-head mrn-ui__head">
                                    <?php if ($item_label !== '') : ?>
                                        <<?php echo esc_html($item_label_tag); ?> class="mrn-ui__label"><?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($item_label) : esc_html($item_label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html($item_label_tag); ?>>
                                    <?php endif; ?>

                                    <?php if ($item_heading !== '') : ?>
                                        <<?php echo esc_html($item_heading_tag); ?> class="mrn-ui__heading"><?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($item_heading) : esc_html($item_heading); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html($item_heading_tag); ?>>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($item_copy !== '') : ?>
                                <div class="mrn-ui__text">
                                    <?php echo apply_filters('the_content', $item_copy); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($link_url !== '') : ?>
                                <?php if (!$enable_full_item_link) : ?>
                                    <div class="mrn-content-grid__item-link-wrap">
                                        <a
                                            class="<?php echo esc_attr(trim($link_class_names)); ?>"
                                            <?php echo '' !== $link_attr_html ? $link_attr_html : 'href="' . esc_url($link_url) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        >
                                            <?php
                                            echo function_exists('mrn_base_stack_get_compact_link_label_markup')
                                                ? mrn_base_stack_get_compact_link_label_markup($link_label, $link_icon_markup, $link_icon_position)
                                                : esc_html($link_label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes text and icon markup is escaped at source.
                                            ?>
                                        </a>
                                    </div>
                                <?php elseif (!$hide_item_link) : ?>
                                    <div class="mrn-content-grid__item-link-wrap">
                                        <span class="<?php echo esc_attr(trim($link_class_names)); ?>">
                                            <?php
                                            echo function_exists('mrn_base_stack_get_compact_link_label_markup')
                                                ? mrn_base_stack_get_compact_link_label_markup($link_label, $link_icon_markup, $link_icon_position)
                                                : esc_html($link_label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes text and icon markup is escaped at source.
                                            ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($enable_full_item_link && $link_url !== '') : ?>
                            <a
                                class="mrn-content-grid__item-overlay-link"
                                <?php echo '' !== $link_attr_html ? $link_attr_html : 'href="' . esc_url($link_url) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            >
                                <span class="screen-reader-text"><?php echo esc_html($link_aria_label); ?></span>
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
