<?php
// phpcs:ignoreFile -- Standalone WordPress/ACF stub harness for editor-lockdown ACF layout discovery.
/**
 * Focused runtime coverage for ACF field-group discovery and layout merging.
 *
 * Run with:
 * php mu-plugins/mrn-editor-lockdown/tests/php/acf-layout-discovery.php
 *
 * @package mrn-editor-lockdown
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['mrn_editor_lockdown_test_hooks']      = array();
$GLOBALS['mrn_editor_lockdown_test_field_groups'] = array(
	'team_member' => array(
		array(
			'key'         => 'group_mrn_team_member_settings',
			'title'       => 'Team Member Settings',
			'position'    => 'side',
			'menu_order'  => 10,
			'fields'      => array(
				array(
					'key'  => 'field_mrn_team_member_public_profile',
					'name' => 'team_member_public_profile',
				),
				array(
					'key'  => 'field_trilliant_team_profile_eyebrow',
					'name' => 'team_profile_eyebrow',
				),
				array(
					'key'  => 'field_trilliant_team_profile_back_label',
					'name' => 'team_profile_back_label',
				),
			),
		),
		array(
			'key'        => 'group_trilliant_team_member_banner',
			'title'      => 'Team Member Banner',
			'position'   => 'side',
			'menu_order' => 20,
			'fields'     => array(
				array(
					'key'  => 'field_trilliant_team_banner_note',
					'name' => 'team_banner_note',
				),
			),
		),
	),
	'post' => array(
		array(
			'key'        => 'group_trilliant_post_detail',
			'title'      => 'Post Detail',
			'position'   => 'normal',
			'menu_order' => 15,
			'fields'     => array(
				array(
					'key'  => 'field_trilliant_post_detail_note',
					'name' => 'post_detail_note',
				),
			),
		),
	),
	'page' => array(
		array(
			'key'        => 'group_trilliant_page_banner',
			'title'      => 'Page Banner',
			'position'   => 'acf_after_title',
			'menu_order' => 5,
			'fields'     => array(
				array(
					'key'  => 'field_trilliant_page_banner_text',
					'name' => 'page_banner_text',
				),
			),
		),
	),
	'content_only_cpt' => array(
		array(
			'key'        => 'group_trilliant_content_only',
			'title'      => 'Content Only',
			'position'   => 'normal',
			'menu_order' => 1,
			'fields'     => array(
				array(
					'key'  => 'field_trilliant_content_only_note',
					'name' => 'content_only_note',
				),
			),
		),
	),
	'excluded_cpt' => array(
		array(
			'key'        => 'group_trilliant_excluded',
			'title'      => 'Excluded Group',
			'position'   => 'side',
			'menu_order' => 1,
			'fields'     => array(),
		),
		array(
			'key'        => 'group_trilliant_included',
			'title'      => 'Included Group',
			'position'   => 'side',
			'menu_order' => 2,
			'fields'     => array(),
		),
	),
);

function sanitize_key( $value ) {
	$value = strtolower( (string) $value );
	$value = preg_replace( '/[^a-z0-9_\-]/', '', $value );

	return is_string( $value ) ? $value : '';
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	add_filter( $hook_name, $callback, $priority, $accepted_args );
}

function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['mrn_editor_lockdown_test_hooks'][ $hook_name ][] = array(
		'callback'      => $callback,
		'priority'      => (int) $priority,
		'accepted_args' => (int) $accepted_args,
	);
}

function apply_filters( $hook_name, $value, ...$args ) {
	if ( empty( $GLOBALS['mrn_editor_lockdown_test_hooks'][ $hook_name ] ) ) {
		return $value;
	}

	$callbacks = $GLOBALS['mrn_editor_lockdown_test_hooks'][ $hook_name ];

	usort(
		$callbacks,
		static function ( $left, $right ) {
			return $left['priority'] <=> $right['priority'];
		}
	);

	foreach ( $callbacks as $hook ) {
		$call_args = array_slice( array_merge( array( $value ), $args ), 0, $hook['accepted_args'] );
		$value     = call_user_func_array( $hook['callback'], $call_args );
	}

	return $value;
}

function get_post_types( $args = array(), $output = 'names' ) {
	unset( $args, $output );

	return array_keys( $GLOBALS['mrn_editor_lockdown_test_field_groups'] );
}

function acf_get_field_groups( $filter = array() ) {
	$post_type = isset( $filter['post_type'] ) ? sanitize_key( (string) $filter['post_type'] ) : '';

	if ( '' === $post_type || empty( $GLOBALS['mrn_editor_lockdown_test_field_groups'][ $post_type ] ) ) {
		return array();
	}

	return $GLOBALS['mrn_editor_lockdown_test_field_groups'][ $post_type ];
}

function acf_get_fields( $field_group ) {
	return isset( $field_group['fields'] ) && is_array( $field_group['fields'] ) ? $field_group['fields'] : array();
}

require_once dirname( __DIR__, 2 ) . '/mrn-editor-lockdown.php';

add_filter(
	'mrn_editor_lockdown_acf_field_groups',
	static function ( $metaboxes, $post_type ) {
		if ( 'team_member' === $post_type ) {
			foreach ( $metaboxes as &$metabox ) {
				if ( 'acf-group_trilliant_team_member_banner' === $metabox['id'] ) {
					$metabox['position']   = 'normal';
					$metabox['menu_order'] = -5;
				}
			}
			unset( $metabox );
		}

		if ( 'excluded_cpt' === $post_type ) {
			$metaboxes = array_values(
				array_filter(
					$metaboxes,
					static function ( $metabox ) {
						return 'acf-group_trilliant_excluded' !== ( $metabox['id'] ?? '' );
					}
				)
			);
		}

		return $metaboxes;
	},
	10,
	2
);

function mrn_editor_lockdown_test_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, 'FAIL: ' . $message . "\n" );
	exit( 1 );
}

function mrn_editor_lockdown_test_order_items( $order ) {
	return array_values( array_filter( array_map( 'trim', explode( ',', (string) $order ) ) ) );
}

function mrn_editor_lockdown_test_order_contains( $order, $metabox_id ) {
	return in_array( $metabox_id, mrn_editor_lockdown_test_order_items( $order ), true );
}

$team_member_layout = mrn_editor_lockdown_get_layout_for_post_type( 'team_member' );

mrn_editor_lockdown_test_assert(
	mrn_editor_lockdown_test_order_contains( $team_member_layout['meta_box_order']['side'], 'acf-group_mrn_team_member_settings' ),
	'Team Member keeps the Stack-owned parent group in the sidebar.'
);
mrn_editor_lockdown_test_assert(
	mrn_editor_lockdown_test_order_contains( $team_member_layout['meta_box_order']['normal'], 'acf-group_trilliant_team_member_banner' ),
	'Team Member child-registered group can be repositioned into the normal column.'
);
mrn_editor_lockdown_test_assert(
	3 === count( acf_get_fields( $GLOBALS['mrn_editor_lockdown_test_field_groups']['team_member'][0] ) ),
	'Child-added fields attached to the Stack-owned parent group remain in the ACF field tree.'
);

$post_layout = mrn_editor_lockdown_get_layout_for_post_type( 'post' );
mrn_editor_lockdown_test_assert(
	mrn_editor_lockdown_test_order_contains( $post_layout['meta_box_order']['normal'], 'acf-group_trilliant_post_detail' ),
	'Non-hierarchical posts keep normal ACF groups in the main column.'
);

$page_layout = mrn_editor_lockdown_get_layout_for_post_type( 'page' );
mrn_editor_lockdown_test_assert(
	! empty( $page_layout['acf_after_title'] ) && in_array( 'acf-group_trilliant_page_banner', $page_layout['acf_after_title'], true ),
	'Hierarchical pages preserve acf_after_title groups at their dedicated position.'
);

$content_only_layout = mrn_editor_lockdown_get_layout_for_post_type( 'content_only_cpt' );
mrn_editor_lockdown_test_assert(
	mrn_editor_lockdown_test_order_contains( $content_only_layout['meta_box_order']['normal'], 'acf-group_trilliant_content_only' ),
	'Content Only style CPTs still receive editable ACF fields.'
);

$excluded_layout = mrn_editor_lockdown_get_layout_for_post_type( 'excluded_cpt' );
mrn_editor_lockdown_test_assert(
	! mrn_editor_lockdown_test_order_contains( $excluded_layout['meta_box_order']['side'], 'acf-group_trilliant_excluded' ),
	'An explicit filter can exclude a matching ACF group from the layout.'
);
mrn_editor_lockdown_test_assert(
	mrn_editor_lockdown_test_order_contains( $excluded_layout['meta_box_order']['side'], 'acf-group_trilliant_included' ),
	'Matching groups that are not excluded remain visible.'
);

$hidden = mrn_editor_lockdown_get_visible_hidden_metaboxes(
	array(
		'acf-group_69a1c0f3a1b01',
		'acf-group_trilliant_excluded',
		'acf-group_trilliant_included',
	),
	mrn_editor_lockdown_get_acf_field_group_metabox_ids( 'excluded_cpt' )
);

mrn_editor_lockdown_test_assert(
	in_array( 'acf-group_trilliant_excluded', $hidden, true ),
	'Excluded groups stay hidden until a project opts them back in.'
);
mrn_editor_lockdown_test_assert(
	! in_array( 'acf-group_trilliant_included', $hidden, true ),
	'Discovered matching ACF groups are removed from hidden-screen state.'
);
mrn_editor_lockdown_test_assert(
	! in_array( 'acf-group_69a1c0f3a1b01', $hidden, true ),
	'The SEO Helper metabox remains forced visible.'
);

echo "PASS: ACF layout discovery and visibility regression.\n";
