<?php
/**
 * Seed token aliases for the first-pass sync pipeline.
 *
 * These are optional helpers. The mapper also falls back to live Site Styles
 * slugs, token names, and exact hex matches when possible.
 */

return array(
	'site_colors' => array(
		'brand-primary'   => 'primary',
		'brand-secondary' => 'secondary',
		'surface-base'    => 'white',
		'surface-alt'     => 'light-gray',
		'text-primary'    => 'black',
	),
	'graphic_elements' => array(
		'accent-wave'      => 'wave',
		'accent-slash'     => 'slash',
		'accent-chevron'   => 'chevron',
	),
	'section_widths' => array(
		'content'      => 'content',
		'contained'    => 'content',
		'wide'         => 'wide',
		'full'         => 'full-width',
		'full-width'   => 'full-width',
	),
);
