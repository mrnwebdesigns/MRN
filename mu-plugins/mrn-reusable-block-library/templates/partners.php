<?php
/**
 * Partners reusable block template.
 *
 * Theme override path:
 * wp-content/themes/{active-theme}/mrn-blocks/partners.php
 *
 * @var array<string, mixed> $context
 */

if (!isset($context) || !is_array($context)) {
    return;
}

$fields        = isset($context['fields']) && is_array($context['fields']) ? $context['fields'] : array();
$block_post    = isset($context['post']) && $context['post'] instanceof WP_Post ? $context['post'] : null;
$block_post_id = isset($context['post_id']) ? (int) $context['post_id'] : 0;
$post_name     = isset($context['post_name']) ? (string) $context['post_name'] : ($block_post instanceof WP_Post ? (string) $block_post->post_name : '');
$label         = isset($fields['label']) ? trim((string) $fields['label']) : '';
$label_tag     = function_exists('mrn_rbl_normalize_text_tag') ? mrn_rbl_normalize_text_tag($fields['label_tag'] ?? '', 'p') : 'p';
$heading       = isset($fields['heading']) ? trim((string) $fields['heading']) : '';
$heading_tag   = isset($fields['heading_tag']) ? sanitize_key((string) $fields['heading_tag']) : 'h2';
$subheading    = isset($fields['subheading']) ? trim((string) $fields['subheading']) : '';
$subheading_tag = isset($fields['subheading_tag']) ? sanitize_key((string) $fields['subheading_tag']) : 'p';
$legacy_intro  = isset($fields['block_content']) ? trim((string) $fields['block_content']) : '';
$background_color = isset($fields['background_color']) ? sanitize_title((string) $fields['background_color']) : '';
$accent        = !empty($fields['bottom_accent']);
$accent_slug   = isset($fields['bottom_accent_style']) ? (string) $fields['bottom_accent_style'] : '';
$display_mode  = isset($fields['display_mode']) ? sanitize_key((string) $fields['display_mode']) : 'grid';
$logos_per_page = isset($fields['per_page']) ? max(3, min(6, (int) $fields['per_page'])) : 6;
$show_arrows   = !empty($fields['show_arrows']);
$show_pagination = !empty($fields['show_pagination']);
$pause_on_hover = !array_key_exists('pause_on_hover', $fields) || !empty($fields['pause_on_hover']);
$autoplay      = !empty($fields['autoplay']);
$delay_start   = isset($fields['delay_start']) ? max(0, (float) $fields['delay_start']) : 0;
$delay_time    = isset($fields['delay_time']) ? max(1, (float) $fields['delay_time']) : 5;
$time_on_slide = isset($fields['time_on_slide']) ? max(100, (int) $fields['time_on_slide']) : 600;
$raw_items     = isset($fields['logo_items']) && is_array($fields['logo_items']) ? $fields['logo_items'] : array();

if (function_exists('mrn_base_stack_normalize_builder_layout_display_mode')) {
    $display_mode = mrn_base_stack_normalize_builder_layout_display_mode($display_mode, 'logos');
}
if (!in_array($display_mode, array('grid', 'slider'), true)) {
    $display_mode = 'grid';
}

if ($raw_items === array() && isset($fields['add_logo']) && is_array($fields['add_logo'])) {
    $raw_items = $fields['add_logo'];
}

if ($heading === '' && $subheading === '' && $legacy_intro !== '') {
    $heading = $legacy_intro;

    if (empty($fields['heading_tag'])) {
        $heading_tag = 'p';
    }
}

$allowed_tags = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span');
if (!in_array($label_tag, $allowed_tags, true)) {
    $label_tag = 'p';
}
if (!in_array($heading_tag, $allowed_tags, true)) {
    $heading_tag = 'h2';
}
if (!in_array($subheading_tag, $allowed_tags, true)) {
    $subheading_tag = 'p';
}

$normalize_image = static function ($image): array {
    $image_id  = function_exists('mrn_rbl_get_image_attachment_id') ? mrn_rbl_get_image_attachment_id($image) : 0;
    $image_alt = '';

    if (is_array($image) && isset($image['alt'])) {
        $image_alt = (string) $image['alt'];
    } elseif ($image_id > 0) {
        $stored_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
        $image_alt  = is_scalar($stored_alt) ? (string) $stored_alt : '';
    }

    return array(
        'id'  => $image_id,
        'alt' => $image_alt,
    );
};

$normalize_link = static function (array $item): array {
    $link = function_exists('mrn_base_stack_get_repeater_item_primary_link')
        ? mrn_base_stack_get_repeater_item_primary_link($item)
        : array();

    if (!is_array($link) || empty($link['url'])) {
        $link = isset($item['link']) && is_array($item['link']) ? $item['link'] : array();
    }

    return array(
        'url'    => isset($link['url']) ? (string) $link['url'] : '',
        'title'  => isset($link['title']) ? (string) $link['title'] : (isset($link['text']) ? (string) $link['text'] : ''),
        'target' => isset($link['target']) ? (string) $link['target'] : '',
    );
};

$items = array();
foreach ($raw_items as $raw_item) {
    if (!is_array($raw_item)) {
        continue;
    }

    $image = $normalize_image($raw_item['image'] ?? null);
    if ($image['id'] < 1) {
        continue;
    }

    $items[] = array(
        'image' => $image,
        'link'  => $normalize_link($raw_item),
    );
}

if ($label === '' && $heading === '' && $subheading === '' && $items === array()) {
    return;
}

$classes = array(
    'mrn-reusable-block',
    'mrn-reusable-block--partners',
    'mrn-partners-block',
    'client-logos',
    'mrn-reusable-block--partners-' . $display_mode,
);

$display_contract = function_exists('mrn_base_stack_get_builder_display_contract')
    ? mrn_base_stack_get_builder_display_contract(array_merge($fields, array('display_mode' => $display_mode)), 'logos')
    : array(
        'classes'    => array(),
        'attributes' => array(),
    );

if (isset($display_contract['classes']) && is_array($display_contract['classes'])) {
    $classes = array_merge($classes, $display_contract['classes']);
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
if ($background_color !== '' && function_exists('mrn_site_colors_get_css_var')) {
    $styles[] = '--mrn-partners-block-bg: var(' . mrn_site_colors_get_css_var($background_color) . ')';
}

$section_attrs = isset($display_contract['attributes']) && is_array($display_contract['attributes']) ? $display_contract['attributes'] : array();
$section_attrs = function_exists('mrn_rbl_merge_attributes') ? mrn_rbl_merge_attributes($section_attrs, isset($accent_contract['attributes']) && is_array($accent_contract['attributes']) ? $accent_contract['attributes'] : array()) : array_merge($section_attrs, isset($accent_contract['attributes']) && is_array($accent_contract['attributes']) ? $accent_contract['attributes'] : array());
$section_attrs = function_exists('mrn_rbl_merge_attributes') ? mrn_rbl_merge_attributes($section_attrs, isset($motion_contract['attributes']) && is_array($motion_contract['attributes']) ? $motion_contract['attributes'] : array()) : array_merge($section_attrs, isset($motion_contract['attributes']) && is_array($motion_contract['attributes']) ? $motion_contract['attributes'] : array());
$section_attr_html = function_exists('mrn_rbl_get_html_attributes') ? mrn_rbl_get_html_attributes($section_attrs) : '';
$slider_id = 'mrn-partners-' . ($block_post_id > 0 ? $block_post_id : abs(crc32($post_name))) . '-' . wp_generate_password(6, false, false);
$slider_label = $heading !== '' ? wp_strip_all_tags($heading) : __('Partner logos', 'mrn-reusable-block-library');

echo function_exists('mrn_rbl_get_anchor_markup') ? mrn_rbl_get_anchor_markup($context) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
?>
<section
    class="<?php echo esc_attr(implode(' ', array_unique(array_filter($classes)))); ?>"
    data-block-id="<?php echo esc_attr((string) $block_post_id); ?>"
    data-block-slug="<?php echo esc_attr($post_name); ?>"
    <?php echo $section_attr_html !== '' ? ' ' . $section_attr_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php if ($styles !== array()) : ?>
        style="<?php echo esc_attr(implode('; ', $styles)); ?>"
    <?php endif; ?>
>
    <div class="mrn-reusable-block__inner mrn-partners-block__inner mrn-ui__body">
        <?php if ($label !== '' || $heading !== '' || $subheading !== '') : ?>
            <header class="mrn-ui__head mrn-partners-block__header">
                <?php if ($label !== '') : ?>
                    <<?php echo esc_html($label_tag); ?> class="mrn-ui__label mrn-partners-block__label">
                        <?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($label) : esc_html($label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </<?php echo esc_html($label_tag); ?>>
                <?php endif; ?>

                <?php if ($heading !== '') : ?>
                    <<?php echo esc_html($heading_tag); ?> class="heading mrn-ui__heading mrn-partners-block__heading">
                        <?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($heading) : esc_html($heading); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </<?php echo esc_html($heading_tag); ?>>
                <?php endif; ?>

                <?php if ($subheading !== '') : ?>
                    <<?php echo esc_html($subheading_tag); ?> class="mrn-ui__sub mrn-partners-block__subheading">
                        <?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($subheading) : esc_html($subheading); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </<?php echo esc_html($subheading_tag); ?>>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <?php if ($items !== array()) : ?>
            <?php if ('slider' === $display_mode) : ?>
                <div
                    id="<?php echo esc_attr($slider_id); ?>"
                    class="splide mrn-splide mrn-logos-row__splide mrn-partners-block__splide"
                    aria-label="<?php echo esc_attr($slider_label); ?>"
                    data-per-page="<?php echo esc_attr((string) $logos_per_page); ?>"
                    data-arrows="<?php echo esc_attr($show_arrows ? 'true' : 'false'); ?>"
                    data-pagination="<?php echo esc_attr($show_pagination ? 'true' : 'false'); ?>"
                    data-pause-on-hover="<?php echo esc_attr($pause_on_hover ? 'true' : 'false'); ?>"
                    data-autoplay="<?php echo esc_attr($autoplay ? 'true' : 'false'); ?>"
                    data-delay-start="<?php echo esc_attr((string) $delay_start); ?>"
                    data-delay-time="<?php echo esc_attr((string) $delay_time); ?>"
                    data-time-on-slide="<?php echo esc_attr((string) $time_on_slide); ?>"
                >
                    <div class="splide__track">
                        <ul class="splide__list mrn-ui__items">
                            <?php foreach ($items as $item) : ?>
                                <?php
                                $image       = $item['image'];
                                $link        = $item['link'];
                                $link_url    = isset($link['url']) ? (string) $link['url'] : '';
                                $link_title  = isset($link['title']) ? (string) $link['title'] : '';
                                $link_target = isset($link['target']) ? (string) $link['target'] : '';
                                $aria_label  = $link_title !== '' ? $link_title : ($image['alt'] !== '' ? $image['alt'] : __('View partner', 'mrn-reusable-block-library'));
                                ?>
                                <li class="splide__slide">
                                    <div class="logo-item mrn-partners-block__item mrn-logos-row__item mrn-ui__item">
                                        <?php if ($link_url !== '') : ?>
                                            <a
                                                class="mrn-partners-block__link mrn-ui__link"
                                                href="<?php echo esc_url($link_url); ?>"
                                                aria-label="<?php echo esc_attr($aria_label); ?>"
                                                <?php if ($link_target !== '') : ?>
                                                    target="<?php echo esc_attr($link_target); ?>"
                                                <?php endif; ?>
                                                <?php if ($link_target === '_blank') : ?>
                                                    rel="noopener noreferrer"
                                                <?php endif; ?>
                                            >
                                        <?php endif; ?>

                                        <?php echo function_exists('mrn_rbl_get_attachment_image') ? mrn_rbl_get_attachment_image((int) $image['id'], 'mrn-logo') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                                        <?php if ($link_url !== '') : ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php else : ?>
            <div class="logo-grid mrn-partners-block__grid mrn-logos-row__grid mrn-logos-row__grid--logo-wall mrn-logos-row__grid--columns-<?php echo esc_attr((string) $logos_per_page); ?> mrn-ui__items">
                <?php foreach ($items as $item) : ?>
                    <?php
                    $image       = $item['image'];
                    $link        = $item['link'];
                    $link_url    = isset($link['url']) ? (string) $link['url'] : '';
                    $link_title  = isset($link['title']) ? (string) $link['title'] : '';
                    $link_target = isset($link['target']) ? (string) $link['target'] : '';
                    $aria_label  = $link_title !== '' ? $link_title : ($image['alt'] !== '' ? $image['alt'] : __('View partner', 'mrn-reusable-block-library'));
                    ?>
                    <div class="logo-item mrn-partners-block__item mrn-logos-row__item mrn-logos-row__item--logo-wall mrn-ui__item">
                        <?php if ($link_url !== '') : ?>
                            <a
                                class="mrn-partners-block__link mrn-ui__link"
                                href="<?php echo esc_url($link_url); ?>"
                                aria-label="<?php echo esc_attr($aria_label); ?>"
                                <?php if ($link_target !== '') : ?>
                                    target="<?php echo esc_attr($link_target); ?>"
                                <?php endif; ?>
                                <?php if ($link_target === '_blank') : ?>
                                    rel="noopener noreferrer"
                                <?php endif; ?>
                            >
                        <?php endif; ?>

                        <?php echo function_exists('mrn_rbl_get_attachment_image') ? mrn_rbl_get_attachment_image((int) $image['id'], 'mrn-logo') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                        <?php if ($link_url !== '') : ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
