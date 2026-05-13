<?php
/**
 * Template part for displaying posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package mrn-base-stack
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php
		$mrn_post_id         = get_the_ID();
		$mrn_is_singular     = is_singular();
		$mrn_has_hero        = $mrn_is_singular ? mrn_base_stack_render_hero_builder( $mrn_post_id ) : false;
		$mrn_is_spacing_test = $mrn_is_singular && 'spacing-test' === get_post_field( 'post_name', $mrn_post_id );
		$mrn_shell_classes   = array(
			'mrn-singular-shell',
			'mrn-singular-shell--post',
		);
		?>

	<div class="<?php echo esc_attr( implode( ' ', $mrn_shell_classes ) ); ?>">
		<div class="mrn-singular-shell__main">
				<?php if ( ! $mrn_has_hero && ! $mrn_is_spacing_test ) : ?>
				<header class="entry-header">
					<?php
					if ( $mrn_is_singular ) :
						the_title( '<h1 class="entry-title">', '</h1>' );
					else :
						the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' );
					endif;
					?>
				</header><!-- .entry-header -->
			<?php endif; ?>

				<?php
				if ( ! $mrn_is_spacing_test ) {
					mrn_base_stack_post_thumbnail(); }
				?>

				<div class="entry-content entry-content--builder">
					<?php
					if ( $mrn_is_singular ) {
						if ( $mrn_is_spacing_test && function_exists( 'get_field' ) ) {
							$mrn_spacing_rows = get_field( 'text', $mrn_post_id );

							$mrn_spacing_property_keys = array(
								'margin-top',
								'margin-right',
								'margin-bottom',
								'margin-left',
								'padding-top',
								'padding-right',
								'padding-bottom',
								'padding-left',
							);

							$mrn_normalize_scope = static function ( $scope ) {
								$scope = is_scalar( $scope ) ? strtolower( trim( (string) $scope ) ) : '';
								if ( in_array( $scope, array( 'margin', 'padding' ), true ) ) {
									return $scope;
								}

								if ( preg_match( '/^(margin|padding)\-(top|right|bottom|left)$/', $scope ) ) {
									return $scope;
								}

								return '';
							};

							$mrn_expand_property = static function ( $property ) {
								$property = is_scalar( $property ) ? strtolower( trim( (string) $property ) ) : '';
								if ( 'margin' === $property ) {
									return array( 'margin-top', 'margin-right', 'margin-bottom', 'margin-left' );
								}

								if ( 'padding' === $property ) {
									return array( 'padding-top', 'padding-right', 'padding-bottom', 'padding-left' );
								}

								if ( preg_match( '/^(margin|padding)\-(top|right|bottom|left)$/', $property ) ) {
									return array( $property );
								}

								return array();
							};

							$mrn_sanitize_dimension = static function ( $value ) {
								if ( function_exists( 'mrn_site_styles_sanitize_spacing_dimension' ) ) {
									return mrn_site_styles_sanitize_spacing_dimension( (string) $value );
								}

								$sanitized = preg_replace( '/[^a-zA-Z0-9.%(),\-+*\/\s]/', '', (string) $value );
								$sanitized = is_string( $sanitized ) ? trim( preg_replace( '/\s+/', ' ', $sanitized ) ) : '';

								return '' !== $sanitized ? substr( $sanitized, 0, 64 ) : '';
							};

							$mrn_property_matches_scope = static function ( $property, $scope ) use ( $mrn_expand_property, $mrn_normalize_scope ) {
								$scope = $mrn_normalize_scope( $scope );
								if ( '' === $scope ) {
									return true;
								}

								$target_properties = $mrn_expand_property( $property );
								if ( empty( $target_properties ) ) {
									return false;
								}

								if ( in_array( $scope, array( 'margin', 'padding' ), true ) ) {
									foreach ( $target_properties as $target_property ) {
										if ( 0 === strpos( $target_property, $scope . '-' ) ) {
											return true;
										}
									}

									return false;
								}

								return in_array( $scope, $target_properties, true );
							};

							$mrn_normalize_preset_name = static function ( $value ) {
								$name = is_scalar( $value ) ? trim( (string) $value ) : '';
								if ( '' === $name ) {
									return '';
								}

								$name = preg_replace( '/\s+/', ' ', $name );
								$name = is_string( $name ) ? trim( $name ) : '';

								return strtolower( $name );
							};

							$mrn_get_spacing_defaults = static function () use ( $mrn_expand_property, $mrn_sanitize_dimension, $mrn_spacing_property_keys ) {
								$defaults = array(
									'desktop' => array_fill_keys( $mrn_spacing_property_keys, '' ),
									'mobile'  => array_fill_keys( $mrn_spacing_property_keys, '' ),
								);

								if ( ! function_exists( 'mrn_site_styles_get_row_spacing_defaults_resolved' ) ) {
									return $defaults;
								}

								$configured_defaults = mrn_site_styles_get_row_spacing_defaults_resolved();
								if ( ! is_array( $configured_defaults ) ) {
									return $defaults;
								}

								foreach ( $configured_defaults as $property_key => $values ) {
									if ( ! is_array( $values ) ) {
										continue;
									}

									$target_properties = $mrn_expand_property( $property_key );
									if ( empty( $target_properties ) ) {
										continue;
									}

									$desktop = $mrn_sanitize_dimension( $values['desktop'] ?? '' );
									$mobile  = $mrn_sanitize_dimension( $values['mobile'] ?? '' );

									foreach ( $target_properties as $target_property ) {
										if ( '' !== $desktop ) {
											$defaults['desktop'][ $target_property ] = $desktop;
										}

										if ( '' !== $mobile ) {
											$defaults['mobile'][ $target_property ] = $mobile;
										}
									}
								}

								return $defaults;
							};

								$mrn_get_spacing_overrides_for_preset = static function ( $preset_name, $scope = '' ) use ( $mrn_expand_property, $mrn_normalize_preset_name, $mrn_normalize_scope, $mrn_property_matches_scope, $mrn_sanitize_dimension ) {
									$scope           = $mrn_normalize_scope( $scope );
									$normalized_name = $mrn_normalize_preset_name( $preset_name );
									$scope_is_side   = (bool) preg_match( '/^(margin|padding)\-(top|right|bottom|left)$/', $scope );
									$overrides       = array(
										'desktop' => array(),
										'mobile'  => array(),
									);

									if ( '' === $normalized_name || ! function_exists( 'mrn_site_styles_get_row_spacing_presets_resolved' ) ) {
										return $overrides;
									}

									$preset_rows = mrn_site_styles_get_row_spacing_presets_resolved();
									if ( ! is_array( $preset_rows ) ) {
										return $overrides;
									}

									foreach ( $preset_rows as $preset_row ) {
										if ( ! is_array( $preset_row ) ) {
											continue;
										}

										$row_name = $mrn_normalize_preset_name( $preset_row['name'] ?? '' );
										if ( '' === $row_name || $normalized_name !== $row_name ) {
											continue;
										}

										if ( ! $mrn_property_matches_scope( $preset_row['property'] ?? '', $scope ) ) {
											continue;
										}

										$target_properties = $mrn_expand_property( $preset_row['property'] ?? '' );
										if ( $scope_is_side ) {
											$target_properties = in_array( $scope, $target_properties, true ) ? array( $scope ) : array();
										}
										if ( empty( $target_properties ) ) {
											continue;
										}

										$desktop = $mrn_sanitize_dimension( $preset_row['desktop'] ?? '' );
										$mobile  = $mrn_sanitize_dimension( $preset_row['mobile'] ?? '' );

										foreach ( $target_properties as $target_property ) {
											if ( '' !== $desktop ) {
												$overrides['desktop'][ $target_property ] = $desktop;
											}

											if ( '' !== $mobile ) {
												$overrides['mobile'][ $target_property ] = $mobile;
											}
										}
									}

									return $overrides;
								};

							$mrn_get_spacing_values_for_row = static function ( array $row ) use ( $mrn_get_spacing_defaults, $mrn_get_spacing_overrides_for_preset, $mrn_sanitize_dimension ) {
								$values = $mrn_get_spacing_defaults();

								$preset_name         = isset( $row['row_spacing_preset'] ) && is_scalar( $row['row_spacing_preset'] ) ? trim( (string) $row['row_spacing_preset'] ) : '';
								$margin_preset_name  = isset( $row['row_spacing_margin_preset'] ) && is_scalar( $row['row_spacing_margin_preset'] ) ? trim( (string) $row['row_spacing_margin_preset'] ) : '';
								$padding_preset_name = isset( $row['row_spacing_padding_preset'] ) && is_scalar( $row['row_spacing_padding_preset'] ) ? trim( (string) $row['row_spacing_padding_preset'] ) : '';
								$side_presets        = array(
									'margin-top'     => isset( $row['row_spacing_margin_top_preset'] ) && is_scalar( $row['row_spacing_margin_top_preset'] ) ? trim( (string) $row['row_spacing_margin_top_preset'] ) : '',
									'margin-right'   => isset( $row['row_spacing_margin_right_preset'] ) && is_scalar( $row['row_spacing_margin_right_preset'] ) ? trim( (string) $row['row_spacing_margin_right_preset'] ) : '',
									'margin-bottom'  => isset( $row['row_spacing_margin_bottom_preset'] ) && is_scalar( $row['row_spacing_margin_bottom_preset'] ) ? trim( (string) $row['row_spacing_margin_bottom_preset'] ) : '',
									'margin-left'    => isset( $row['row_spacing_margin_left_preset'] ) && is_scalar( $row['row_spacing_margin_left_preset'] ) ? trim( (string) $row['row_spacing_margin_left_preset'] ) : '',
									'padding-top'    => isset( $row['row_spacing_padding_top_preset'] ) && is_scalar( $row['row_spacing_padding_top_preset'] ) ? trim( (string) $row['row_spacing_padding_top_preset'] ) : '',
									'padding-right'  => isset( $row['row_spacing_padding_right_preset'] ) && is_scalar( $row['row_spacing_padding_right_preset'] ) ? trim( (string) $row['row_spacing_padding_right_preset'] ) : '',
									'padding-bottom' => isset( $row['row_spacing_padding_bottom_preset'] ) && is_scalar( $row['row_spacing_padding_bottom_preset'] ) ? trim( (string) $row['row_spacing_padding_bottom_preset'] ) : '',
									'padding-left'   => isset( $row['row_spacing_padding_left_preset'] ) && is_scalar( $row['row_spacing_padding_left_preset'] ) ? trim( (string) $row['row_spacing_padding_left_preset'] ) : '',
								);

								$apply_overrides = static function ( array $overrides, array $target_values ) use ( $mrn_sanitize_dimension ) {
									foreach ( array( 'desktop', 'mobile' ) as $device_key ) {
										if ( ! isset( $overrides[ $device_key ] ) || ! is_array( $overrides[ $device_key ] ) ) {
											continue;
										}

										foreach ( $overrides[ $device_key ] as $property => $value ) {
											$property = sanitize_key( (string) $property );
											$value    = $mrn_sanitize_dimension( $value );
											if ( '' === $property || '' === $value ) {
												continue;
											}

											$target_values[ $device_key ][ $property ] = $value;
										}
									}

									return $target_values;
								};

								if ( '' !== $preset_name ) {
									$values = $apply_overrides( $mrn_get_spacing_overrides_for_preset( $preset_name, '' ), $values );
								}

								if ( '' !== $margin_preset_name ) {
									$values = $apply_overrides( $mrn_get_spacing_overrides_for_preset( $margin_preset_name, 'margin' ), $values );
								}

								if ( '' !== $padding_preset_name ) {
									$values = $apply_overrides( $mrn_get_spacing_overrides_for_preset( $padding_preset_name, 'padding' ), $values );
								}

								foreach ( $side_presets as $scope => $side_preset ) {
									if ( '' === $side_preset ) {
										continue;
									}

									$values = $apply_overrides( $mrn_get_spacing_overrides_for_preset( $side_preset, $scope ), $values );
								}

								return $values;
							};

								$mrn_row_spacing_selector_keys = array(
									'row_spacing_preset',
									'row_spacing_margin_preset',
									'row_spacing_padding_preset',
									'row_spacing_margin_top_preset',
									'row_spacing_margin_right_preset',
									'row_spacing_margin_bottom_preset',
									'row_spacing_margin_left_preset',
									'row_spacing_padding_top_preset',
									'row_spacing_padding_right_preset',
									'row_spacing_padding_bottom_preset',
									'row_spacing_padding_left_preset',
								);

								if ( is_array( $mrn_spacing_rows ) && ! empty( $mrn_spacing_rows ) ) {
									echo '<section class="mrn-spacing-test-output" data-mrn-spacing-test-output="1">';

									foreach ( $mrn_spacing_rows as $mrn_row_index => $mrn_spacing_row ) {
										if ( ! is_array( $mrn_spacing_row ) ) {
											continue;
										}

										$mrn_row_index = absint( $mrn_row_index );

										// Frontend ACF formatting can omit dynamically injected selector fields.
										// Pull selector values from raw row meta so spacing overrides still apply.
										foreach ( $mrn_row_spacing_selector_keys as $mrn_selector_key ) {
											if ( isset( $mrn_spacing_row[ $mrn_selector_key ] ) && '' !== trim( (string) $mrn_spacing_row[ $mrn_selector_key ] ) ) {
												continue;
											}

											$mrn_selector_meta_key = 'text_' . $mrn_row_index . '_' . $mrn_selector_key;
											$mrn_selector_value    = get_post_meta( $mrn_post_id, $mrn_selector_meta_key, true );
											if ( is_scalar( $mrn_selector_value ) ) {
												$mrn_selector_value = trim( (string) $mrn_selector_value );
												if ( '' !== $mrn_selector_value ) {
													$mrn_spacing_row[ $mrn_selector_key ] = $mrn_selector_value;
												}
											}
										}

										$mrn_row_text_values = array();
										foreach ( $mrn_spacing_row as $mrn_row_key => $mrn_row_value ) {
											$mrn_row_key = sanitize_key( (string) $mrn_row_key );
											if ( in_array(
												$mrn_row_key,
												array_merge(
													array( 'acf_fc_layout' ),
													$mrn_row_spacing_selector_keys
												),
												true
											) ) {
												continue;
											}

											if ( ! is_scalar( $mrn_row_value ) ) {
												continue;
											}

											$mrn_row_text_value = trim( (string) $mrn_row_value );
											if ( '' === $mrn_row_text_value ) {
												continue;
											}

											$mrn_row_text_values[] = $mrn_row_text_value;
										}

										if ( empty( $mrn_row_text_values ) ) {
											continue;
										}

										$mrn_resolved_spacing = $mrn_get_spacing_values_for_row( $mrn_spacing_row );
										$mrn_style_parts      = array(
											'padding-top: var(--mrn-row-padding-top)',
											'padding-right: var(--mrn-row-padding-right)',
											'padding-bottom: var(--mrn-row-padding-bottom)',
											'padding-left: var(--mrn-row-padding-left)',
										);

										foreach ( $mrn_spacing_property_keys as $mrn_spacing_property_key ) {
											$mrn_desktop_value = isset( $mrn_resolved_spacing['desktop'][ $mrn_spacing_property_key ] ) ? trim( (string) $mrn_resolved_spacing['desktop'][ $mrn_spacing_property_key ] ) : '';
											$mrn_mobile_value  = isset( $mrn_resolved_spacing['mobile'][ $mrn_spacing_property_key ] ) ? trim( (string) $mrn_resolved_spacing['mobile'][ $mrn_spacing_property_key ] ) : '';

											if ( '' !== $mrn_desktop_value ) {
												$mrn_style_parts[] = '--mrn-row-' . $mrn_spacing_property_key . '-desktop: ' . $mrn_desktop_value;
											}

											if ( '' !== $mrn_mobile_value ) {
												$mrn_style_parts[] = '--mrn-row-' . $mrn_spacing_property_key . '-mobile: ' . $mrn_mobile_value;
											}
										}

										$mrn_style_attribute = implode( '; ', array_filter( $mrn_style_parts ) );

										echo '<div class="mrn-spacing-test-row" data-mrn-row-spacing="defaults" style="' . esc_attr( $mrn_style_attribute ) . '">';
										foreach ( $mrn_row_text_values as $mrn_row_text_value ) {
											echo '<p>' . esc_html( $mrn_row_text_value ) . '</p>';
										}
										echo '</div>';
									}

									echo '</section>';
								}
						} else {
							mrn_base_stack_render_content_builder( $mrn_post_id );
							mrn_base_stack_render_after_content_builder( $mrn_post_id );
						}
					} else {
						the_excerpt();
					}

					wp_link_pages(
						array(
							'before' => '<div class="mrn-shell-container mrn-shell-container--content"><div class="page-links">' . esc_html__( 'Pages:', 'mrn-base-stack' ),
							'after'  => '</div></div>',
						)
					);
					?>
			</div><!-- .entry-content -->
		</div><!-- .mrn-singular-shell__main -->
	</div><!-- .mrn-singular-shell -->
</article><!-- #post-<?php the_ID(); ?> -->
