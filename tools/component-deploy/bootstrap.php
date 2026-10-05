<?php
/**
 * Build-owned early MU bootstrap. Adoption supplies the verified private root.
 * Never installed by a source build or a normal Stack package overwrite.
 *
 * @package MRNComponentRelease
 */

defined( 'ABSPATH' ) || exit;

$mrn_component_root   = realpath( ABSPATH . '__MRN_COMPONENT_STATE_RELATIVE__' );
$mrn_component_public = realpath( ABSPATH );
if ( false === $mrn_component_root || false === $mrn_component_public
	|| ! is_dir( $mrn_component_root ) || ( fileperms( $mrn_component_root ) & 0077 )
	|| $mrn_component_root === $mrn_component_public
	|| 0 === strpos( $mrn_component_root, $mrn_component_public . DIRECTORY_SEPARATOR ) ) {
	throw new RuntimeException( 'MRN component release storage is unavailable.' );
}
$mrn_component_control = $mrn_component_root . '/control/runtime.php';
if ( realpath( $mrn_component_control ) !== $mrn_component_control || ! is_file( $mrn_component_control )
	|| ! hash_equals( '__MRN_COMPONENT_RUNTIME_SHA256__', hash_file( 'sha256', $mrn_component_control ) ) ) {
	throw new RuntimeException( 'MRN component runtime differs from the qualified control artifact.' );
}
// The installer fixes this path; physical-root and exact checksum checks above
// reject aliases or altered control code. No request value reaches the include.
require_once $mrn_component_control; // nosemgrep: php-dynamic-include
MRN_Component_Release_Runtime::boot( $mrn_component_root );
unset( $mrn_component_root, $mrn_component_public, $mrn_component_control );
