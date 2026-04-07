<?php
/**
 * FAQ reusable block template.
 *
 * Theme override path:
 * wp-content/themes/{active-theme}/mrn-blocks/faq.php
 *
 * @var array<string, mixed> $context
 */

if (!isset($context) || !is_array($context)) {
    return;
}

$post_id     = isset($context['post_id']) ? (int) $context['post_id'] : 0;
$post_name   = isset($context['post_name']) ? (string) $context['post_name'] : '';
$fields      = isset($context['fields']) && is_array($context['fields']) ? $context['fields'] : array();
$label       = isset($fields['label']) ? (string) $fields['label'] : '';
$label_tag   = function_exists('mrn_rbl_normalize_text_tag') ? mrn_rbl_normalize_text_tag($fields['label_tag'] ?? '', 'p') : 'p';
$heading     = isset($fields['heading']) ? (string) $fields['heading'] : '';
$heading_tag = isset($fields['heading_tag']) ? sanitize_key((string) $fields['heading_tag']) : 'h2';
$subheading  = isset($fields['subheading']) ? (string) $fields['subheading'] : '';
$subheading_tag = isset($fields['subheading_tag']) ? sanitize_key((string) $fields['subheading_tag']) : 'p';
$items       = isset($fields['faq_items']) && is_array($fields['faq_items']) ? $fields['faq_items'] : array();
$bg_color    = isset($fields['bg_color']) ? (string) $fields['bg_color'] : '';
$start_open  = !empty($fields['start_open']);
$bottom_accent = !empty($fields['bottom_accent']);
$bottom_accent_style = isset($fields['bottom_accent_style']) ? (string) $fields['bottom_accent_style'] : '';

if (!in_array($heading_tag, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span'), true)) {
    $heading_tag = 'h2';
}
if (!in_array($subheading_tag, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span'), true)) {
    $subheading_tag = 'p';
}

if ($items === array()) {
    return;
}

$classes = array(
    'mrn-reusable-block',
    'mrn-reusable-block--faq',
);

$inline_styles = array();

if ($bg_color !== '' && function_exists('mrn_site_colors_get_css_var')) {
    $inline_styles[] = '--mrn-faq-bg-color: var(' . mrn_site_colors_get_css_var($bg_color) . ')';
}

$accent_contract = function_exists('mrn_site_styles_get_bottom_accent_contract')
    ? mrn_site_styles_get_bottom_accent_contract($bottom_accent, $bottom_accent_style)
    : array(
        'classes'    => $bottom_accent ? array('has-bottom-accent') : array(),
        'attributes' => array(),
    );

if (!empty($accent_contract['classes']) && is_array($accent_contract['classes'])) {
    $classes = array_merge($classes, $accent_contract['classes']);
}

echo function_exists('mrn_rbl_get_anchor_markup') ? mrn_rbl_get_anchor_markup($context) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Anchor markup is escaped in the helper.
?>
<section
    class="<?php echo esc_attr(implode(' ', $classes)); ?>"
    data-block-id="<?php echo esc_attr((string) $post_id); ?>"
    data-block-slug="<?php echo esc_attr($post_name); ?>"
    <?php if (!empty($accent_contract['attributes']) && is_array($accent_contract['attributes'])) : ?>
        <?php foreach ($accent_contract['attributes'] as $attribute_name => $attribute_value) : ?>
            <?php if ($attribute_name !== '' && $attribute_value !== '') : ?>
                <?php echo ' ' . esc_attr($attribute_name) . '="' . esc_attr($attribute_value) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($inline_styles !== array()) : ?>
        style="<?php echo esc_attr(implode('; ', $inline_styles)); ?>"
    <?php endif; ?>
>
	    <div class="mrn-reusable-block__inner mrn-ui__body">
	        <div class="mrn-faq mrn-faq--editorial-shell">
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

	            <div class="mrn-faq__items mrn-ui__items">
                <?php foreach ($items as $index => $item) : ?>
                    <?php
                    if (!is_array($item)) {
                        continue;
                    }

                    $question = isset($item['question']) ? (string) $item['question'] : '';
                    $answer   = isset($item['answer']) ? (string) $item['answer'] : '';

                    if ($question === '' && $answer === '') {
                        continue;
                    }
                    ?>
	                    <details class="mrn-faq__item mrn-ui__item"<?php echo ($start_open && $index === 0) ? ' open' : ''; ?>>
	                        <?php if ($question !== '') : ?>
	                            <summary class="mrn-faq__question mrn-ui__heading"><?php echo function_exists('mrn_base_stack_format_heading_inline_html') ? mrn_base_stack_format_heading_inline_html($question) : esc_html($question); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></summary>
                        <?php endif; ?>

                        <?php if ($answer !== '') : ?>
	                            <div class="mrn-faq__answer mrn-ui__text">
                                <?php echo wp_kses_post(wpautop($answer)); ?>
                            </div>
                        <?php endif; ?>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
