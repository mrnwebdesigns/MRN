<?php
/** Native timeline frame registration and bounded dimension contract. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || home_url() !== getenv( 'MRN_STACK_TEST_HOME' ) || ! str_ends_with( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.localhost' ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Explicit local administrator target required.' );
}
$checks = array();
$expect = static function ( $ok, $label ) use ( &$checks ) {
	if ( ! $ok ) { throw new RuntimeException( esc_html( $label ) ); }
	$checks[] = $label;
};
$layouts = array_column( acf_get_field( 'field_mrn_page_content_rows' )['layouts'], null, 'name' );
$fields = array_column( $layouts['card']['sub_fields'], null, 'name' );
foreach ( mrn_base_stack_get_timeline_frame_fields( 'fixture' ) as $field ) {
	$expect( isset( $fields[ $field['name'] ] ), 'Editable timeline control: ' . $field['name'] );
}
$empty = array( 'active' => false, 'media_skip' => false, 'numbered_steps' => false, 'arrow_actions' => false, 'styles' => array() );
$expect( $empty === mrn_base_stack_get_timeline_frame_contract( array() ), 'Default timeline frame stays dormant' );
$expect( $empty === mrn_base_stack_get_timeline_frame_contract( array( 'card_presentation' => 'standard', 'timeline_frame' => 'contained' ) ), 'Other Card presentations ignore timeline frame' );
$row = array( 'card_presentation' => 'timeline', 'timeline_frame' => 'contained', 'timeline_image' => 10054, 'timeline_skip_position' => 'media', 'timeline_content_width' => '1200px', 'timeline_column_gap' => '120px' );
$contract = mrn_base_stack_get_timeline_frame_contract( $row );
$expect( $contract['active'] && $contract['media_skip'] && ! $contract['numbered_steps'] && ! $contract['arrow_actions'] && array( '--mrn-timeline-content-width:1200px', '--mrn-timeline-column-gap:120px' ) === $contract['styles'], 'Selected timeline frame emits only validated native dimensions' );
foreach ( array( '-1px', '1px;position:fixed', 'url(https://invalid.test)', array( '1px' ) ) as $bad ) {
	$row['timeline_content_width'] = $bad;
	$expect( array( '--mrn-timeline-column-gap:120px' ) === mrn_base_stack_get_timeline_frame_contract( $row )['styles'], 'Invalid timeline dimension cannot inject CSS' );
}
unset( $row['timeline_image'] );
$expect( ! mrn_base_stack_get_timeline_frame_contract( $row )['media_skip'], 'Missing image keeps the skip link before the timeline' );
$row['timeline_step_style'] = 'numbered';
$row['timeline_step_gap'] = '30px';
$expect( mrn_base_stack_get_timeline_frame_contract( $row )['numbered_steps'] && in_array( '--mrn-timeline-step-gap:30px', mrn_base_stack_get_timeline_frame_contract( $row )['styles'], true ), 'Selected numbered steps retain editable native spacing' );
$row['timeline_step_gap'] = '30px;position:fixed';
$expect( ! in_array( '--mrn-timeline-step-gap:30px;position:fixed', mrn_base_stack_get_timeline_frame_contract( $row )['styles'], true ), 'Invalid step gap cannot inject CSS' );
$row['timeline_step_action_icon'] = 'arrow';
$row['timeline_step_icon_gap'] = '4px';
$expect( mrn_base_stack_get_timeline_frame_contract( $row )['arrow_actions'] && in_array( '--mrn-timeline-step-icon-gap:4px', mrn_base_stack_get_timeline_frame_contract( $row )['styles'], true ), 'Selected decorative arrow keeps its editable gap' );
$row['timeline_step_action_icon'] = '';
$expect( ! mrn_base_stack_get_timeline_frame_contract( $row )['arrow_actions'] && ! in_array( '--mrn-timeline-step-icon-gap:4px', mrn_base_stack_get_timeline_frame_contract( $row )['styles'], true ), 'Dormant decorative arrow does not emit icon spacing' );
WP_CLI::line( wp_json_encode( array( 'result' => 'passed', 'assertions' => count( $checks ) ), JSON_PRETTY_PRINT ) );
