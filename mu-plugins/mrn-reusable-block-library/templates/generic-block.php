<?php
/**
 * Generic fallback template for reusable blocks.
 *
 * Theme override path:
 * wp-content/themes/{active-theme}/mrn-blocks/generic-block.php
 */

if (!isset($context) || !is_array($context)) {
    return;
}

$post = isset($context['post']) && $context['post'] instanceof WP_Post ? $context['post'] : null;
if (!$post instanceof WP_Post) {
    return;
}
?>
<section
    class="mrn-reusable-block mrn-reusable-block--generic"
    data-block-id="<?php echo esc_attr((string) $post->ID); ?>"
    data-block-slug="<?php echo esc_attr((string) $post->post_name); ?>"
>
    <div class="mrn-reusable-block__inner">
        <h2 class="mrn-reusable-block__heading"><?php echo esc_html($post->post_title); ?></h2>
    </div>
</section>
