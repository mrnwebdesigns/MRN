<?php
/** ZIP compatibility and security regression; temporary local files only. */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/class-mrn-updraft-mainwp-maintenance.php';

$checks = array();

/** @param bool $condition Assertion. @param string $label Description. */
function check_package( $condition, $label ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( 'Package validation regression.' );
	}
	$checks[] = $label;
}

/** @param array $entries ZIP entries. @param string $kind Package type. @return string */
function fixture_package( $entries = array(), $kind = 'plugin' ) {
	$file = tempnam( sys_get_temp_dir(), 'mrn-zip-' );
	$zip  = new ZipArchive();
	$zip->open( $file, ZipArchive::OVERWRITE );
	$zip->addEmptyDir( 'fixture' );
	$zip->addEmptyDir( 'fixture/assets' );
	$header = 'plugin' === $kind ? "<?php\n/*\nPlugin Name: Fixture\nVersion: 1.1\n*/\n" : "/*\nTheme Name: Fixture\nVersion: 1.1\n*/\n";
	$zip->addFromString( 'plugin' === $kind ? 'fixture/fixture.php' : 'fixture/style.css', $header );
	foreach ( $entries as $entry ) {
		$zip->addFromString( $entry['name'], $entry['data'] ?? 'fixture' );
		if ( isset( $entry['mode'] ) ) {
			$zip->setExternalAttributesName( $entry['name'], ZipArchive::OPSYS_UNIX, $entry['mode'] << 16 );
		}
	}
	$zip->close();
	return $file;
}

/** @param string $file ZIP. @param string $kind Package type. @param string $version Expected version. @return array */
function inspect_package( $file, $kind = 'plugin', $version = '1.1' ) {
	$method = new ReflectionMethod( MRN_Updraft_MainWP_Maintenance::class, 'package' );
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}
	return $method->invoke( null, $file, array( 'slug' => 'plugin' === $kind ? 'fixture/fixture.php' : 'fixture', 'from' => '1.0', 'to' => $version ), $kind, array( 'themes' => array( 'fixture' => array( 'template' => '' ) ) ) );
}

foreach ( array( 'plugin', 'theme' ) as $kind ) {
	$file = fixture_package( array( array( 'name' => 'fixture/assets/example.txt' ) ), $kind );
	try {
		$manifest = inspect_package( $file, $kind );
		check_package( hash_file( 'sha256', $file ) === $manifest['sha256'] && filesize( $file ) === $manifest['bytes'], $kind . ' accepts explicit root/nested directory entries with exact checksum' );
	} finally {
		unlink( $file );
	}
}

$unsafe = array(
	'dot directory alias'   => array( 'name' => 'fixture/./alias.php' ),
	'dot directory entry'   => array( 'name' => 'fixture/./' ),
	'empty directory alias' => array( 'name' => 'fixture//alias.php' ),
	'double trailing slash' => array( 'name' => 'fixture/assets//' ),
	'parent traversal'      => array( 'name' => 'fixture/../outside.php' ),
	'absolute path'         => array( 'name' => '/fixture/outside.php' ),
	'wrong root'            => array( 'name' => 'other/outside.php' ),
	'backslash traversal'   => array( 'name' => 'fixture/..\\outside.php' ),
	'case collision'        => array( 'name' => 'fixture/FIXTURE.php' ),
	'directory collision'   => array( 'name' => 'fixture/ASSETS/' ),
	'file/directory alias'  => array( 'name' => 'fixture/assets' ),
	'symlink'               => array( 'name' => 'fixture/link', 'mode' => 0120777 ),
	'FIFO'                  => array( 'name' => 'fixture/pipe', 'mode' => 0010644 ),
);
foreach ( $unsafe as $label => $entry ) {
	$file = fixture_package( array( $entry ) );
	try {
		$refused = false;
		try {
			inspect_package( $file );
		} catch ( RuntimeException $error ) {
			$refused = true;
		}
		check_package( $refused, $label . ' refused' );
	} finally {
		unlink( $file );
	}
}
$file = fixture_package();
try {
	$refused = false;
	try {
		inspect_package( $file, 'plugin', '1.2' );
	} catch ( RuntimeException $error ) {
		$refused = true;
	}
	check_package( $refused, 'unexpected package version refused' );
} finally {
	unlink( $file );
}

echo json_encode( array( 'status' => 'pass', 'checks' => $checks, 'production_writes' => 0 ), JSON_PRETTY_PRINT ) . "\n";
