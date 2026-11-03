<?php
/** Standalone native People contract checks; no WordPress state or writes. */
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/D', $value ) ? $value : null; }
function mrn_rbl_get_site_color_choices() { return array( 'ink' => 'Ink' ); }
function mrn_site_colors_get_all() { return array( array( 'slug' => 'ink', 'value' => '#112233' ) ); }
function mrn_site_colors_get_css_var( $slug ) { return '--site-color-' . $slug; }
$base = dirname( __DIR__, 2 ) . '/inc/builder/';
foreach ( array( 'hero-frame.php', 'card-frame.php', 'content-frame.php', 'faq-aside.php', 'presentation.php', 'content-list-people.php' ) as $file ) { require_once $base . $file; }
$checks = array();
$expect = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( esc_html( $label ) ); } $checks[] = $label; };
$empty = array( 'classes' => array(), 'attributes' => array() );
foreach ( array( array(), array( 'record_presentation' => 'people' ), array( 'record_presentation' => 'standard', 'people_frame' => 'aside' ), array( 'record_presentation' => 'comparisons', 'people_frame' => 'aside' ), array( 'record_presentation' => 'people', 'people_frame' => 'invalid' ) ) as $index => $row ) { $expect( $empty === mrn_base_stack_get_people_frame_contract( $row ), 'Unselected or unrelated presentation stays byte-empty: ' . $index ); }
$row = array( 'record_presentation' => 'people', 'people_frame' => 'aside', 'people_grid_breakpoints' => '640-420', 'people_aside_breakpoint' => '991', 'people_content_width' => '1200px', 'people_heading_width' => '286px', 'people_inner_gutter' => '30px', 'people_media_ratio' => '261/306', 'people_media_background_color' => '#DCDCDC', 'people_heading_font' => 'heading', 'people_name_weight' => '400', 'people_designation_size' => 'var(--site-text-body-4-size)', 'people_designation_line_height' => '1', 'people_name_color' => 'ink', 'people_image_size' => 'medium_large' );
$c = mrn_base_stack_get_people_frame_contract( $row ); $style = $c['attributes']['style'];
$expect( array( 'has-people-frame', 'has-people-aside', 'people-aside-at-991', 'people-grid-640-420' ) === $c['classes'], 'Explicit frame, heading and grid breakpoints are independent' );
foreach ( array( 'content-width:1200px', 'heading-width:286px', 'inner-gutter:30px', 'media-ratio:261/306', 'media-background-color:#DCDCDC', 'heading-font:var(--site-font-heading)', 'name-weight:400', 'designation-size:var(--site-text-body-4-size)', 'designation-line-height:1', 'name-color:var(--site-color-ink)' ) as $part ) { $expect( str_contains( $style, '--mrn-people-' . $part ), 'Editable safe value appears: ' . $part ); }
$fields = mrn_base_stack_get_people_frame_fields( 'field_one' ); $other = mrn_base_stack_get_people_frame_fields( 'field_two' );
$expect( 41 === count( $fields ) && 41 === count( array_unique( array_column( $fields, 'key' ) ) ), 'All forty-one controls have unique native keys' );
$expect( array() === array_intersect( array_column( $fields, 'key' ), array_column( $other, 'key' ) ), 'Cloned factories receive independent field keys' );
foreach ( $fields as $field ) { $expect( 'field_one_record_presentation' === $field['conditional_logic'][0][0]['field'] && 'people' === $field['conditional_logic'][0][0]['value'] && ( 'people_frame' === $field['name'] || 'field_one_people_frame' === $field['conditional_logic'][0][1]['field'] ), 'Editor condition stays on its exact frame/profile: ' . $field['name'] ); }
foreach ( array( 'people_heading_width' => '-2px', 'people_column_gap' => 'url(https://example.test)', 'people_heading_font' => 'evil', 'people_name_weight' => '450', 'people_name_color' => 'unknown', 'people_media_background_color' => 'rgba(0,0,0,.5)', 'people_designation_size' => 'var(--untrusted-size)' ) as $key => $value ) { $bad = array( 'record_presentation' => 'people', 'people_frame' => 'stacked', $key => $value ); $expect( ! isset( mrn_base_stack_get_people_frame_contract( $bad )['attributes']['style'] ), 'Unsafe or unresolved field cannot emit a variable: ' . $key ); }
foreach ( array( '0/0', '1/0', '-1/2', '1/1;color:red', '10000/2', '1e3/2', array( 1, 2 ) ) as $index => $ratio ) { $bad = array( 'record_presentation' => 'people', 'people_frame' => 'aside', 'people_media_ratio' => $ratio ); $expect( ! isset( mrn_base_stack_get_people_frame_contract( $bad )['attributes']['style'] ), 'Invalid media ratio is ignored: ' . $index ); }
$expect( '' === mrn_base_stack_get_people_frame_dimension( 'var(--site-text-body-4-line)', 'designation_size' ) && '' === mrn_base_stack_get_people_frame_dimension( 'var(--site-text-body-4-size)', 'designation_line_height' ), 'Site Text size and line tokens cannot cross property types' );
$expect( 'mrn-content-media' === mrn_base_stack_get_people_image_size( array( 'people_image_size' => 'full' ) ) && 'medium_large' === mrn_base_stack_get_people_image_size( $row ), 'Only an explicitly selected People frame changes the image size' );
$bad = $row; $bad['people_image_size'] = '../../private'; $expect( 'mrn-content-media' === mrn_base_stack_get_people_image_size( $bad ), 'An unregistered image size cannot reach the renderer' );
$bad = $row; $bad['people_frame'] = 'stacked'; $bad['people_aside_breakpoint'] = '767'; $bad['people_grid_breakpoints'] = '999-599';
$expect( array( 'has-people-frame', 'people-aside-at-767', 'people-grid-999-599' ) === mrn_base_stack_get_people_frame_contract( $bad )['classes'], 'Stacked heading and alternate breakpoints remain reusable' );
echo json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'checks' => $checks ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
