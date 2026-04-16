<?php
/**
 * Custom template tags for this theme
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package mrn-base-stack
 */

if ( ! function_exists( 'mrn_base_stack_posted_on' ) ) :
	/**
	 * Prints HTML with meta information for the current post-date/time.
	 */
	function mrn_base_stack_posted_on() {
		$time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
		if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) {
			$time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="updated" datetime="%3$s">%4$s</time>';
		}

		$time_string = sprintf(
			$time_string,
			esc_attr( get_the_date( DATE_W3C ) ),
			esc_html( get_the_date() ),
			esc_attr( get_the_modified_date( DATE_W3C ) ),
			esc_html( get_the_modified_date() )
		);

		$posted_on = sprintf(
			/* translators: %s: post date. */
			esc_html_x( 'Posted on %s', 'post date', 'mrn-base-stack' ),
			'<a href="' . esc_url( get_permalink() ) . '" rel="bookmark">' . $time_string . '</a>'
		);

		echo '<span class="posted-on">' . $posted_on . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
endif;

if ( ! function_exists( 'mrn_base_stack_posted_by' ) ) :
	/**
	 * Prints HTML with meta information for the current author.
	 */
	function mrn_base_stack_posted_by() {
		$byline = sprintf(
			/* translators: %s: post author. */
			esc_html_x( 'by %s', 'post author', 'mrn-base-stack' ),
			'<span class="author vcard"><a class="url fn n" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( get_the_author() ) . '</a></span>'
		);

		echo '<span class="byline"> ' . $byline . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
endif;

if ( ! function_exists( 'mrn_base_stack_entry_footer' ) ) :
	/**
	 * Prints HTML with meta information for the categories, tags and comments.
	 */
	function mrn_base_stack_entry_footer() {
		// Hide category and tag text for pages.
		if ( in_array( get_post_type(), array( 'post', 'post_with_sidebars', 'blog' ), true ) ) {
			/* translators: used between list items, there is a space after the comma */
			$categories_list = get_the_category_list( esc_html__( ', ', 'mrn-base-stack' ) );
			if ( $categories_list ) {
				/* translators: 1: list of categories. */
				printf( '<span class="cat-links">' . esc_html__( 'Posted in %1$s', 'mrn-base-stack' ) . '</span>', $categories_list ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			/* translators: used between list items, there is a space after the comma */
			$tags_list = get_the_tag_list( '', esc_html_x( ', ', 'list item separator', 'mrn-base-stack' ) );
			if ( $tags_list ) {
				/* translators: 1: list of tags. */
				printf( '<span class="tags-links">' . esc_html__( 'Tagged %1$s', 'mrn-base-stack' ) . '</span>', $tags_list ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		if ( ! is_single() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
			echo '<span class="comments-link">';
			comments_popup_link(
				sprintf(
					wp_kses(
						/* translators: %s: post title */
						__( 'Leave a Comment<span class="screen-reader-text"> on %s</span>', 'mrn-base-stack' ),
						array(
							'span' => array(
								'class' => array(),
							),
						)
					),
					wp_kses_post( get_the_title() )
				)
			);
			echo '</span>';
		}

		edit_post_link(
			sprintf(
				wp_kses(
					/* translators: %s: Name of current post. Only visible to screen readers */
					__( 'Edit <span class="screen-reader-text">%s</span>', 'mrn-base-stack' ),
					array(
						'span' => array(
							'class' => array(),
						),
					)
				),
				wp_kses_post( get_the_title() )
			),
			'<span class="edit-link">',
			'</span>'
		);
	}
endif;

if ( ! function_exists( 'mrn_base_stack_post_thumbnail' ) ) :
	/**
	 * Displays an optional post thumbnail.
	 *
	 * Wraps the post thumbnail in an anchor element on index views, or a div
	 * element when on single views.
	 */
	function mrn_base_stack_post_thumbnail() {
		if ( post_password_required() || is_attachment() || ! has_post_thumbnail() ) {
			return;
		}

		if ( is_singular() ) :
			?>

			<div class="post-thumbnail">
				<?php the_post_thumbnail(); ?>
			</div><!-- .post-thumbnail -->

		<?php else : ?>

			<a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
				<?php
					the_post_thumbnail(
						'post-thumbnail',
						array(
							'alt' => the_title_attribute(
								array(
									'echo' => false,
								)
							),
						)
					);
				?>
			</a>

			<?php
		endif; // End is_singular().
	}
endif;

if ( ! function_exists( 'wp_body_open' ) ) :
	/**
	 * Shim for sites older than 5.2.
	 *
	 * @link https://core.trac.wordpress.org/ticket/12563
	 */
	function wp_body_open() {
		do_action( 'wp_body_open' );
	}
endif;

if ( ! function_exists( 'mrn_base_stack_has_action' ) ) :
	/**
	 * Determine whether a hook has callable listeners.
	 *
	 * @param string $hook_name Hook name.
	 * @return bool
	 */
	function mrn_base_stack_has_action( $hook_name ) {
		return (bool) has_action( $hook_name );
	}
endif;

if ( ! function_exists( 'mrn_base_stack_render_header_search' ) ) :
	/**
	 * Render the header search area using the stack search hook.
	 */
	function mrn_base_stack_render_header_search() {
		if ( ! mrn_base_stack_has_action( 'mrn_base_stack_header_search' ) ) {
			return;
		}

		$header_options = function_exists( 'mrn_base_stack_get_theme_header_footer_options' ) ? mrn_base_stack_get_theme_header_footer_options() : array();
		$classes        = array( 'mrn-site-header__search' );

		if ( isset( $header_options['header_search_style'] ) && 'icon_only' === $header_options['header_search_style'] ) {
			$classes[] = 'mrn-site-header__search--icon-only';
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		do_action( 'mrn_base_stack_header_search' );
		echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
endif;

if ( ! function_exists( 'mrn_base_stack_get_header_search_icon_markup' ) ) :
	/**
	 * Return the configured icon markup for icon-only search mode.
	 *
	 * @param array<string, mixed> $header_options Header option payload.
	 * @return string
	 */
	function mrn_base_stack_get_header_search_icon_markup( $header_options ) {
		$icon_source   = isset( $header_options['header_search_icon_source'] ) ? (string) $header_options['header_search_icon_source'] : 'dashicons';
		$standard_icon = isset( $header_options['header_search_standard_icon'] ) ? (string) $header_options['header_search_standard_icon'] : 'dashicons-search';
		$fa_class      = isset( $header_options['header_search_fa_class'] ) ? trim( (string) $header_options['header_search_fa_class'] ) : '';
		$media_icon    = isset( $header_options['header_search_media_icon'] ) && is_array( $header_options['header_search_media_icon'] ) ? $header_options['header_search_media_icon'] : array();

		if ( 'fontawesome' === $icon_source && '' !== $fa_class ) {
			return '<span class="mrn-site-search__icon mrn-site-search__icon--fontawesome" aria-hidden="true"><i class="' . esc_attr( $fa_class ) . '"></i></span>';
		}

		if ( 'media' === $icon_source ) {
			$attachment_id = isset( $media_icon['ID'] ) ? absint( $media_icon['ID'] ) : 0;
			$icon_url      = isset( $media_icon['url'] ) ? (string) $media_icon['url'] : '';

			if ( $attachment_id > 0 ) {
				$image = wp_get_attachment_image(
					$attachment_id,
					'thumbnail',
					false,
					array(
						'class' => 'mrn-site-search__icon-image',
						'alt'   => '',
					)
				);

				if ( is_string( $image ) && '' !== $image ) {
					return '<span class="mrn-site-search__icon mrn-site-search__icon--media" aria-hidden="true">' . $image . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}

			if ( '' !== $icon_url ) {
				return '<span class="mrn-site-search__icon mrn-site-search__icon--media" aria-hidden="true"><img class="mrn-site-search__icon-image" src="' . esc_url( $icon_url ) . '" alt="" /></span>';
			}
		}

		return '<span class="mrn-site-search__icon mrn-site-search__icon--dashicons dashicons ' . esc_attr( $standard_icon ) . '" aria-hidden="true"></span>';
	}
endif;

if ( ! function_exists( 'mrn_base_stack_has_searchwp_form_support' ) ) :
	/**
	 * Determine whether SearchWP form integrations are available on the current site.
	 *
	 * @return bool
	 */
	function mrn_base_stack_has_searchwp_form_support() {
		return shortcode_exists( 'searchwp_form' ) || class_exists( 'SearchWP_Live_Search_Storage' ) || function_exists( 'searchwp_live_search' );
	}
endif;

if ( ! function_exists( 'mrn_base_stack_get_searchwp_forms' ) ) :
	/**
	 * Return available SearchWP form settings keyed by form ID.
	 *
	 * SearchWP stores form definitions in the `searchwp_forms` option as a JSON
	 * payload, not as a post type, so the builder/reusable-block pickers need to
	 * resolve those settings directly.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function mrn_base_stack_get_searchwp_forms() {
		if ( ! mrn_base_stack_has_searchwp_form_support() ) {
			return array();
		}

		$stored_forms = get_option( 'searchwp_forms', '' );

		if ( is_array( $stored_forms ) ) {
			$decoded = $stored_forms;
		} elseif ( is_string( $stored_forms ) && '' !== $stored_forms ) {
			$decoded = json_decode( $stored_forms, true );
		} else {
			$decoded = array();
		}

		if ( ! is_array( $decoded ) || empty( $decoded['forms'] ) || ! is_array( $decoded['forms'] ) ) {
			return array();
		}

		$forms = array();

		foreach ( $decoded['forms'] as $form_key => $form ) {
			if ( ! is_array( $form ) ) {
				continue;
			}

			$form_id = absint( $form['id'] ?? $form_key );
			if ( $form_id < 1 ) {
				continue;
			}

				$form_title = isset( $form['title'] ) ? trim( (string) $form['title'] ) : '';
			if ( '' === $form_title ) {
				/* translators: %d: SearchWP form ID. */
				$form_title = sprintf( __( 'Search Form %d', 'mrn-base-stack' ), $form_id );
			}

			$forms[ $form_id ] = array(
				'id'       => $form_id,
				'title'    => $form_title,
				'settings' => $form,
			);
		}

		ksort( $forms );

		/**
		 * Filter the normalized SearchWP form settings list.
		 *
		 * @param array<int, array<string, mixed>> $forms Available SearchWP forms.
		 */
		return apply_filters( 'mrn_base_stack_searchwp_forms', $forms );
	}
endif;

if ( ! function_exists( 'mrn_base_stack_get_searchwp_form_choices' ) ) :
	/**
	 * Build ACF-ready SearchWP form choices.
	 *
	 * @return array<string, string>
	 */
	function mrn_base_stack_get_searchwp_form_choices() {
		$forms       = mrn_base_stack_get_searchwp_forms();
			$choices = array();

		foreach ( $forms as $form_id => $form ) {
			/* translators: %d: SearchWP form ID. */
			$choices[ (string) $form_id ] = isset( $form['title'] ) ? (string) $form['title'] : sprintf( __( 'Search Form %d', 'mrn-base-stack' ), $form_id );
		}

		/**
		 * Filter the SearchWP form picker choices.
		 *
		 * @param array<string, string>             $choices ACF-ready choices keyed by form ID.
		 * @param array<int, array<string, mixed>>  $forms   Normalized SearchWP form settings.
		 */
		return apply_filters( 'mrn_base_stack_searchwp_form_choices', $choices, $forms );
	}
endif;

if ( ! function_exists( 'mrn_base_stack_get_searchwp_form_title' ) ) :
	/**
	 * Resolve a SearchWP form title for UI labels and builder row titles.
	 *
	 * @param int|string $form_id SearchWP form ID.
	 * @return string
	 */
	function mrn_base_stack_get_searchwp_form_title( $form_id ) {
		$form_id = absint( $form_id );
		if ( $form_id < 1 ) {
			return '';
		}

		$forms = mrn_base_stack_get_searchwp_forms();

		return isset( $forms[ $form_id ]['title'] ) ? (string) $forms[ $form_id ]['title'] : '';
	}
endif;

if ( ! function_exists( 'mrn_base_stack_get_searchwp_form_markup' ) ) :
	/**
	 * Return rendered SearchWP form markup, with a theme search fallback.
	 *
	 * @param int|string $form_id SearchWP form ID.
	 * @return string
	 */
	function mrn_base_stack_get_searchwp_form_markup( $form_id = 0 ) {
		$form_id = absint( $form_id );

		if ( $form_id > 0 && shortcode_exists( 'searchwp_form' ) ) {
			$form_title = mrn_base_stack_get_searchwp_form_title( $form_id );

			if ( '' !== $form_title ) {
				return do_shortcode( sprintf( '[searchwp_form id="%d"]', $form_id ) );
			}
		}

		ob_start();

		if ( function_exists( 'mrn_base_stack_render_search_form_markup' ) ) {
			mrn_base_stack_render_search_form_markup(
				array(
					'search_style' => 'full',
				)
			);
		} else {
			get_search_form();
		}

		return trim( (string) ob_get_clean() );
	}
endif;

if ( ! function_exists( 'mrn_base_stack_render_search_form_markup' ) ) :
	/**
	 * Render the stack header search form.
	 *
	 * @param array<string, mixed> $args Optional rendering overrides.
	 */
	function mrn_base_stack_render_search_form_markup( $args = array() ) {
		$args           = is_array( $args ) ? $args : array();
		$search_query   = get_search_query();
		$header_options = function_exists( 'mrn_base_stack_get_theme_header_footer_options' ) ? mrn_base_stack_get_theme_header_footer_options() : array();
		$search_style   = isset( $args['search_style'] ) ? (string) $args['search_style'] : ( isset( $header_options['header_search_style'] ) ? (string) $header_options['header_search_style'] : 'full' );

		if ( 'icon_only' === $search_style ) {
			$is_expanded = '' !== $search_query;
			$form_class  = 'mrn-site-search searchwp-form mrn-site-search--icon-only';

			if ( $is_expanded ) {
				$form_class .= ' is-expanded';
			}
			?>
			<form role="search" method="get" class="<?php echo esc_attr( $form_class ); ?>" action="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Search site content', 'mrn-base-stack' ); ?>" data-mrn-search-toggle>
				<label class="screen-reader-text" for="mrn-header-search-input"><?php esc_html_e( 'Search for:', 'mrn-base-stack' ); ?></label>
				<button type="button" class="mrn-site-search__toggle" aria-expanded="<?php echo $is_expanded ? 'true' : 'false'; ?>" aria-controls="mrn-header-search-input-wrap">
					<?php echo mrn_base_stack_get_header_search_icon_markup( $header_options ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Open search', 'mrn-base-stack' ); ?></span>
				</button>
				<div class="mrn-site-search__input-wrap" id="mrn-header-search-input-wrap">
					<div class="mrn-site-search__field">
						<span class="mrn-site-search__prompt" aria-hidden="true" data-mrn-search-prompt><?php esc_html_e( 'Search', 'mrn-base-stack' ); ?></span>
						<input
							type="search"
							id="mrn-header-search-input"
							class="mrn-site-search__input"
							placeholder=""
							value="<?php echo esc_attr( $search_query ); ?>"
							name="s"
							autocomplete="off"
						/>
						<button type="button" class="mrn-site-search__clear" aria-label="<?php esc_attr_e( 'Clear search', 'mrn-base-stack' ); ?>" <?php echo '' === $search_query ? 'hidden' : ''; ?>>
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
				</div>
			</form>
			<?php

			return;
		}

		?>
		<form role="search" method="get" class="mrn-site-search searchwp-form" action="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Search site content', 'mrn-base-stack' ); ?>">
			<label class="screen-reader-text" for="mrn-header-search-input"><?php esc_html_e( 'Search for:', 'mrn-base-stack' ); ?></label>
			<div class="mrn-site-search__input-wrap">
				<input
					type="search"
					id="mrn-header-search-input"
					class="mrn-site-search__input"
					placeholder="<?php esc_attr_e( 'Search…', 'mrn-base-stack' ); ?>"
					value="<?php echo esc_attr( $search_query ); ?>"
					name="s"
					autocomplete="off"
				/>
				<button type="submit" class="mrn-site-search__button"><?php esc_html_e( 'Search', 'mrn-base-stack' ); ?></button>
			</div>
		</form>
		<?php
	}
endif;

if ( ! function_exists( 'mrn_base_stack_default_header_search' ) ) :
	/**
	 * Default header search implementation.
	 */
	function mrn_base_stack_default_header_search() {
		mrn_base_stack_render_search_form_markup();
	}
endif;
add_action( 'mrn_base_stack_header_search', 'mrn_base_stack_default_header_search' );

if ( ! function_exists( 'mrn_base_stack_get_business_address_lines' ) ) :
	/**
	 * Return formatted business address lines.
	 *
	 * @return array<int, string>
	 */
	function mrn_base_stack_get_business_address_lines() {
		$business_information = function_exists( 'mrn_base_stack_get_business_information' ) ? mrn_base_stack_get_business_information() : array();
		$address              = isset( $business_information['address'] ) && is_array( $business_information['address'] ) ? $business_information['address'] : array();

		$lines = array_filter(
			array(
				isset( $address['line_1'] ) ? (string) $address['line_1'] : '',
				isset( $address['line_2'] ) ? (string) $address['line_2'] : '',
				trim(
					implode(
						', ',
						array_filter(
							array(
								isset( $address['city'] ) ? (string) $address['city'] : '',
								isset( $address['state'] ) ? (string) $address['state'] : '',
								isset( $address['postal_code'] ) ? (string) $address['postal_code'] : '',
							)
						)
					)
				),
				isset( $address['country'] ) ? (string) $address['country'] : '',
			)
		);

		return array_values( $lines );
	}
endif;

if ( ! function_exists( 'mrn_base_stack_get_business_hours_display_rows' ) ) :
	/**
	 * Return formatted weekday business hours rows.
	 *
	 * @return array<int, array<string, string>>
	 */
	function mrn_base_stack_get_business_hours_display_rows() {
		$business_information = function_exists( 'mrn_base_stack_get_business_information' ) ? mrn_base_stack_get_business_information() : array();
		$business_hours       = isset( $business_information['business_hours'] ) && is_array( $business_information['business_hours'] ) ? $business_information['business_hours'] : array();
		$labels               = array(
			'monday'    => __( 'Monday', 'mrn-base-stack' ),
			'tuesday'   => __( 'Tuesday', 'mrn-base-stack' ),
			'wednesday' => __( 'Wednesday', 'mrn-base-stack' ),
			'thursday'  => __( 'Thursday', 'mrn-base-stack' ),
			'friday'    => __( 'Friday', 'mrn-base-stack' ),
		);
		$rows                 = array();

		foreach ( $labels as $day => $label ) {
			$hours = isset( $business_hours[ $day ] ) && is_array( $business_hours[ $day ] ) ? $business_hours[ $day ] : array();
			$open  = isset( $hours['open'] ) ? trim( (string) $hours['open'] ) : '';
			$close = isset( $hours['close'] ) ? trim( (string) $hours['close'] ) : '';

			if ( '' === $open || '' === $close ) {
				continue;
			}

			$rows[] = array(
				'label' => $label,
				'hours' => $open . ' - ' . $close,
			);
		}

		return $rows;
	}
endif;

if ( ! function_exists( 'mrn_base_stack_get_footer_copyright_text' ) ) :
	/**
	 * Return the footer copyright line.
	 *
	 * @return string
	 */
	function mrn_base_stack_get_footer_copyright_text() {
		$options = function_exists( 'mrn_base_stack_get_theme_header_footer_options' ) ? mrn_base_stack_get_theme_header_footer_options() : array();

		if ( ! empty( $options['footer_copyright_text'] ) ) {
			return (string) $options['footer_copyright_text'];
		}

		return sprintf(
			/* translators: 1: year, 2: site name. */
			__( 'Copyright %1$s %2$s. All rights reserved.', 'mrn-base-stack' ),
			wp_date( 'Y' ),
			get_bloginfo( 'name' )
		);
	}
endif;

if ( ! function_exists( 'mrn_base_stack_render_social_links' ) ) :
	/**
	 * Render configured social links.
	 */
	function mrn_base_stack_render_social_links() {
		if ( ! function_exists( 'mrn_config_helper_get_social_links' ) ) {
			return;
		}

		$social_links = mrn_config_helper_get_social_links();

		if ( ! is_array( $social_links ) || empty( $social_links ) ) {
			return;
		}

		echo '<ul class="mrn-social-links">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		foreach ( $social_links as $row ) {
			if ( ! is_array( $row ) || empty( $row['url'] ) ) {
				continue;
			}

			$url         = esc_url( (string) $row['url'] );
			$icon_type   = isset( $row['icon_type'] ) ? (string) $row['icon_type'] : '';
			$icon_id     = isset( $row['icon_id'] ) ? (int) $row['icon_id'] : 0;
			$name        = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
			$alt_text    = isset( $row['alt_text'] ) ? trim( (string) $row['alt_text'] ) : '';
			$icon_markup = '';
			$label       = '' !== $name ? $name : ( isset( $row['fa_name'] ) && '' !== $row['fa_name'] ? (string) $row['fa_name'] : __( 'Social link', 'mrn-base-stack' ) );

			if ( 'dashicons' === $icon_type && ! empty( $row['dashicon'] ) ) {
				$label = '' !== $name ? $name : (string) $row['dashicon'];
			}

			if ( 'media' === $icon_type && $icon_id > 0 ) {
				$attached_file = get_attached_file( $icon_id );

				if ( is_string( $attached_file ) && '' !== $attached_file && file_exists( $attached_file ) ) {
					$icon_markup = wp_get_attachment_image(
						$icon_id,
						'thumbnail',
						false,
						array(
							'class' => 'mrn-social-links__image',
							'alt'   => $alt_text,
						)
					);
				}
			}

			$accessible_label = '' !== $alt_text ? $alt_text : ucwords( str_replace( '-', ' ', $label ) );

			echo '<li class="mrn-social-links__item">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<a class="mrn-social-links__link" href="' . $url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $accessible_label ) . '" title="' . esc_attr( $accessible_label ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			if ( 'fontawesome' === $icon_type && ! empty( $row['fa_class'] ) ) {
				echo '<span class="mrn-social-links__icon" aria-hidden="true"><i class="' . esc_attr( (string) $row['fa_class'] ) . '"></i></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( 'dashicons' === $icon_type && ! empty( $row['dashicon'] ) ) {
				echo '<span class="mrn-social-links__icon" aria-hidden="true"><span class="dashicons ' . esc_attr( (string) $row['dashicon'] ) . '"></span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( '' !== $icon_markup ) {
				echo $icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<span class="mrn-social-links__text">' . esc_html( ucwords( str_replace( '-', ' ', $label ) ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			echo '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
endif;
