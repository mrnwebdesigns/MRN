<?php
/** Native image annotation contracts and existing-action compatibility. */
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $v ) { return esc_attr( $v ); }
function esc_url( $v ) { return is_string( $v ) && preg_match( '#^https?://#', $v ) ? esc_attr( $v ) : ''; }
function sanitize_hex_color( $v ) { return is_string( $v ) && preg_match( '/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/iD', $v ) ? $v : ''; }
function mrn_base_stack_get_background_color_field( $key ) { return array( 'key' => $key, 'name' => 'background_color', 'type' => 'select' ); }
function mrn_site_colors_get_all() { return array( array( 'slug' => 'ink', 'value' => '#112233' ) ); }
function mrn_site_colors_get_css_var( $slug ) { return '--site-color-' . $slug; }
function mrn_base_stack_image_has_content( $media ) { return is_int( $media ) && $media > 0; }
function mrn_base_stack_get_image_alt( $media ) { return 2 === $media ? 'Named point' : ''; }
function mrn_base_stack_get_attachment_image( $media, $size, array $attrs ) { return '<img class="' . esc_attr( $attrs['class'] ) . '" alt="' . esc_attr( $attrs['alt'] ) . '" src="/owned-marker.svg">'; }
$root = dirname( __DIR__, 2 ) . '/inc/builder/';
foreach ( array( 'hero-frame.php', 'card-frame.php', 'content-frame.php', 'faq-aside.php', 'presentation.php', 'image-action.php', 'image-overlays.php' ) as $file ) { require_once $root . $file; }
$checks = array(); $expect = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $label ) ); } $checks[] = $label; };
$empty = array( 'classes' => array(), 'attributes' => array() );
foreach ( array( array(), array( 'image_caption_presentation' => 'attribution', 'image_caption_style' => 'under' ), array( 'image_caption_presentation' => 'invalid', 'image_caption_style' => 'inside' ) ) as $i => $row ) { $expect( $empty === mrn_base_stack_get_image_attribution_contract( $row ), 'Ordinary captions stay unchanged: ' . $i ); }
foreach ( array( array(), array( 'image_markers' => 'bad' ), array( 'image_markers' => array_fill( 0, 31, array() ) ) ) as $i => $row ) { $expect( '' === mrn_base_stack_get_image_markers_markup( $row ), 'Empty/malformed/over-limit markers do not render: ' . $i ); }
$marker = array( 'marker_media' => 1, 'position_x' => 52, 'position_y' => 62, 'marker_size' => '48px', 'marker_anchor' => 'tip', 'decorative' => 1, 'marker_hover_scale' => 1.15, 'marker_shadow' => 1 );
$html = mrn_base_stack_get_image_markers_markup( array( 'image_markers' => array( $marker ) ) );
foreach ( array( '--mrn-image-marker-x:52%', '--mrn-image-marker-y:62%', '--mrn-image-marker-size:48px', '--mrn-image-marker-hover-scale:1.15', 'mrn-image-marker--tip', 'has-soft-shadow', 'aria-hidden="true"', 'alt=""' ) as $part ) { $expect( str_contains( $html, $part ), 'Explicit marker field renders: ' . $part ); }
$marker['decorative'] = '0'; $expect( '' === mrn_base_stack_get_image_markers_markup( array( 'image_markers' => array( $marker ) ) ), 'An informative marker without alt is not invented' );
$marker['marker_media'] = 2; $html = mrn_base_stack_get_image_markers_markup( array( 'image_markers' => array( $marker ) ) ); $expect( str_contains( $html, 'alt="Named point"' ) && ! str_contains( $html, 'aria-hidden' ), 'Attachment alt remains informative' );
$marker['marker_alt'] = 'Point "two"'; $html = mrn_base_stack_get_image_markers_markup( array( 'image_markers' => array( $marker ) ) ); $expect( str_contains( $html, 'alt="Point &quot;two&quot;"' ), 'Per-use informative alt is escaped' );
foreach ( array( -1, 101, '52%;color:red', array( 52 ), true ) as $i => $bad ) { $marker['position_x'] = $bad; $html = mrn_base_stack_get_image_markers_markup( array( 'image_markers' => array( $marker ) ) ); $expect( str_contains( $html, '--mrn-image-marker-x:50%' ), 'Unsafe coordinates safely default: ' . $i ); }
$base = array( 'image_action' => array( 'url' => 'https://example.test/directions', 'title' => 'Get Directions', 'target' => '_blank' ), 'image_action_marker' => 1 );
$ordinary = mrn_base_stack_wrap_image_action( '<img alt="Map">', $base, 1 );
$expect( str_starts_with( $ordinary, '<a class="mrn-image-action" href="https://example.test/directions" aria-label="Get Directions" target="_blank" rel="noopener noreferrer"><img alt="Map"><span class="mrn-image-action__marker" aria-hidden="true">Get Directions ' ) && str_ends_with( $ordinary, '</span></a>' ), 'Existing whole-image action keeps its exact wrapper and marker semantics' );
$base['image_action_scope'] = 'marker-only'; $base['image_action_alignment'] = 'left'; $base['image_action_vertical_alignment'] = 'top'; $base['image_action_icon_style'] = 'solid'; $html = mrn_base_stack_wrap_image_action( '<img alt="Map">', $base, 1 );
$expect( str_starts_with( $html, '<div class="mrn-image-action mrn-image-action--marker-only image-action-left image-action-top"><img alt="Map"><a class="mrn-image-action__marker"' ) && ! str_contains( $html, '<a class="mrn-image-action"' ), 'Visible-only action does not link its surrounding image' );
$expect( str_contains( $html, 'rel="noopener noreferrer"' ) && str_contains( $html, 'fill="currentColor"' ), 'Independent external action retains safe target and selected native icon' );
$base['image_action_marker'] = 0; $expect( '<img alt="Map">' === mrn_base_stack_wrap_image_action( '<img alt="Map">', $base, 1 ), 'Invisible action-only selections do not create hidden links' );
$base['image_action']['url'] = 'javascript:alert(1)'; $expect( '<img alt="Map">' === mrn_base_stack_wrap_image_action( '<img alt="Map">', $base, 1 ), 'Unsafe action URL is not rendered' );
$credit = array( 'image_caption_presentation' => 'attribution', 'image_caption_style' => 'inside', 'image_caption_offset_inline' => '10px', 'image_caption_offset_block' => '6px', 'image_caption_size' => '11px', 'image_caption_line_height' => '1', 'image_caption_font' => 'map', 'image_caption_color' => '#3c4043' );
$c = mrn_base_stack_get_image_attribution_contract( $credit ); foreach ( array( 'offset-inline:10px', 'offset-block:6px', 'size:11px', 'line-height:1', 'font:Roboto,Arial,sans-serif', 'color:#3c4043' ) as $part ) { $expect( str_contains( $c['attributes']['style'], '--mrn-image-caption-' . $part ), 'Editable attribution value: ' . $part ); }
foreach ( array( 'image_caption_offset_inline' => '-1px', 'image_caption_color' => 'rgba(0,0,0,.5)', 'image_caption_size' => 'url(evil)', 'image_caption_font' => 'evil;font-size:100px' ) as $key => $value ) { $bad = array( 'image_caption_presentation' => 'attribution', 'image_caption_style' => 'inside', $key => $value ); $expect( empty( mrn_base_stack_get_image_attribution_contract( $bad )['attributes'] ), 'Unsafe attribution style cannot escape: ' . $key ); }
$fields = mrn_base_stack_get_image_overlay_fields( 'field_one' ); $other = mrn_base_stack_get_image_overlay_fields( 'field_two' );
$expect( count( $fields ) === count( array_unique( array_column( $fields, 'name' ) ) ) && ! array_intersect( array_column( $fields, 'key' ), array_column( $other, 'key' ) ), 'Top-level names and cloned native field keys remain distinct' );
$repeater = array_column( $fields, null, 'name' )['image_markers']; $expect( 30 === $repeater['max'] && 9 === count( array_unique( array_column( $repeater['sub_fields'], 'key' ) ) ), 'Annotation capacity and child keys are explicit' );
echo json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
