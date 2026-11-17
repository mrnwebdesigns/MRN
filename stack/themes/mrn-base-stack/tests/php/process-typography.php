<?php
// phpcs:ignoreFile -- Isolated native field/renderer proof, using the existing WordPress boundary stubs.
if ( 'cli' !== PHP_SAPI ) { exit; }
$type_argv = $argv; $argv = array_values( array_diff( $argv, array( '--render' ) ) );
ob_start(); require __DIR__ . '/process-frame.php'; $type_baseline = json_decode( ob_get_clean(), true, 512, JSON_THROW_ON_ERROR ); $argv = $type_argv;
$expect( 'passed' === $type_baseline['result'], 'Existing Process/frame/escaping/refusal assertions remain passing' );
// Process typography, geometry and colors come from the theme's CSS; they are not row fields.
$retired = array( 'process_intro_size', 'process_intro_line_height', 'process_answer_size', 'process_answer_size_mobile', 'process_list_margin_inline_start', 'process_paragraph_max_width', 'process_paragraph_space_before', 'process_list_padding_before', 'process_step_padding_inline', 'process_step_padding_inline_mobile', 'process_step_muted_border_width', 'process_step_number_bleed_block', 'process_step_number_font', 'process_detail_heading_font', 'process_list_weight', 'process_detail_heading_weight', 'process_step_mobile_breakpoint', 'process_list_columns_mobile', 'process_list_line_height' );
$all = array_column( mrn_base_stack_get_process_card_fields( 'field_type' ), null, 'name' );
$expect( ! array_intersect( $retired, array_keys( $all ) ), 'Process typography, geometry and colors are not editable row fields' );
$type_row = array_merge( $frame, array_fill_keys( array( 'process_intro_size', 'process_paragraph_max_width', 'process_list_line_height' ), '20px' ) );
$type_contract = mrn_base_stack_get_process_card_contract( $type_row );
$expect( ! str_contains( $type_contract['attributes']['style'] ?? '', '--mrn-process-intro-size' ) && ! str_contains( $type_contract['attributes']['style'] ?? '', '--mrn-process-paragraph-max-width' ), 'Stray typography values add no per-row styling' );
foreach ( array( 'standard', 'pricing', 'timeline' ) as $presentation ) { $probe = $type_row; $probe['card_presentation'] = $presentation; $expect( $empty === mrn_base_stack_get_process_card_contract( $probe ), 'Process controls remain dormant for ' . $presentation ); }
$before_type = $type_row; $html = $render( $type_row ); $expect( $type_row === $before_type && 6 === substr_count( $html, 'class="mrn-process__step-label"' ) && 5 === substr_count( $html, '<details ' ), 'All six overview labels, five details and original candidate bytes survive native rendering' );
foreach ( array_slice( $type_row['card_items'], 0, 5 ) as $item ) { $expect( str_contains( $html, $item['process_body'] ), 'Exact rich body and links remain unchanged: ' . $item['card_label'] ); }
if ( in_array( '--render', $argv, true ) ) { echo $html; $second = $row; $second['heading'] = 'Unchanged independent process'; echo $render( $second ); }
else { echo json_encode( array( 'result' => 'passed', 'count' => count( $checks ), 'baseline_count' => $type_baseline['count'], 'checks' => $checks, 'runtime_writes' => 0, 'scope' => 'Isolated native field factory, contract and actual template; installed source/editor/full-page acceptance remains separate.' ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL; }
