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
$raw_items     = isset($fields['logo_items']) && is_array($fields['logo_items']) ? $fields['logo_items'] : array();

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
    $image_id  = 0;
    $image_url = '';
    $image_alt = '';

    if (is_array($image)) {
        if (!empty($image['ID']) && is_numeric($image['ID'])) {
            $image_id = (int) $image['ID'];
        } elseif (!empty($image['id']) && is_numeric($image['id'])) {
            $image_id = (int) $image['id'];
        }

        $image_url = isset($image['url']) ? (string) $image['url'] : '';
        $image_alt = isset($image['alt']) ? (string) $image['alt'] : '';
    } elseif (is_numeric($image)) {
        $image_id = (int) $image;
    } elseif (is_string($image)) {
        $image_url = $image;
    }

    if ($image_id > 0 && $image_url === '') {
        $resolved_url = wp_get_attachment_image_url($image_id, 'full');
        $image_url    = is_string($resolved_url) ? $resolved_url : '';
    }

    return array(
        'id'  => $image_id,
        'url' => $image_url,
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
    if ($image['id'] < 1 && $image['url'] === '') {
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
if ($background_color !== '' && function_exists('mrn_site_colors_get_css_var')) {
    $styles[] = '--mrn-partners-block-bg: var(' . mrn_site_colors_get_css_var($background_color) . ')';
}

$section_attrs = isset($accent_contract['attributes']) && is_array($accent_contract['attributes']) ? $accent_contract['attributes'] : array();
$section_attrs = function_exists('mrn_rbl_merge_attributes') ? mrn_rbl_merge_attributes($section_attrs, isset($motion_contract['attributes']) && is_array($motion_contract['attributes']) ? $motion_contract['attributes'] : array()) : array_merge($section_attrs, isset($motion_contract['attributes']) && is_array($motion_contract['attributes']) ? $motion_contract['attributes'] : array());
$section_attr_html = function_exists('mrn_rbl_get_html_attributes') ? mrn_rbl_get_html_attributes($section_attrs) : '';

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
    <div class="container mrn-reusable-block__inner mrn-partners-block__inner">
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
            <div class="logo-grid mrn-partners-block__grid">
                <?php foreach ($items as $item) : ?>
                    <?php
                    $image       = $item['image'];
                    $link        = $item['link'];
                    $link_url    = isset($link['url']) ? (string) $link['url'] : '';
                    $link_title  = isset($link['title']) ? (string) $link['title'] : '';
                    $link_target = isset($link['target']) ? (string) $link['target'] : '';
                    $aria_label  = $link_title !== '' ? $link_title : ($image['alt'] !== '' ? $image['alt'] : __('View partner', 'mrn-reusable-block-library'));
                    ?>
                    <div class="logo-item mrn-partners-block__item">
                        <?php if ($link_url !== '') : ?>
                            <a
                                class="mrn-partners-block__link"
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

                        <?php if ($image['id'] > 0) : ?>
                            <?php echo wp_get_attachment_image((int) $image['id'], 'full'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php else : ?>
                            <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy" decoding="async">
                        <?php endif; ?>

                        <?php if ($link_url !== '') : ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
