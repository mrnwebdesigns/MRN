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
		if ( 'post' === get_post_type() ) {
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
		$media_icon    = $header_options['header_search_media_icon'] ?? null;

		if ( 'fontawesome' === $icon_source && '' !== $fa_class ) {
			return '<span class="mrn-site-search__icon mrn-site-search__icon--fontawesome" aria-hidden="true"><i class="' . esc_attr( $fa_class ) . '"></i></span>';
		}

		if ( 'media' === $icon_source ) {
			$attachment_id = function_exists( 'mrn_base_stack_get_image_attachment_id' ) ? mrn_base_stack_get_image_attachment_id( $media_icon ) : 0;

			if ( $attachment_id > 0 ) {
				$image = function_exists( 'mrn_base_stack_get_attachment_image' ) ? mrn_base_stack_get_attachment_image(
					$attachment_id,
					'mrn-icon',
					array(
						'class' => 'mrn-site-search__icon-image',
						'alt'   => '',
					)
				) : '';

				if ( is_string( $image ) && '' !== $image ) {
					return '<span class="mrn-site-search__icon mrn-site-search__icon--media" aria-hidden="true">' . $image . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
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

if ( ! function_exists( 'mrn_base_stack_get_default_searchwp_form_id' ) ) :
	/**
	 * Resolve the stack-owned SearchWP form ID.
	 *
	 * @return int
	 */
	function mrn_base_stack_get_default_searchwp_form_id() {
		$forms          = mrn_base_stack_get_searchwp_forms();
		$stored_form_id = absint( get_option( 'mrn_base_stack_searchwp_form_id', 0 ) );

		if ( $stored_form_id > 0 && isset( $forms[ $stored_form_id ] ) ) {
			return $stored_form_id;
		}

		if ( empty( $forms ) ) {
			return 0;
		}

		$form_ids = array_keys( $forms );

		return absint( reset( $form_ids ) );
	}
endif;

if ( ! function_exists( 'mrn_base_stack_seed_searchwp_form' ) ) :
	/**
	 * Ensure the stack has one canonical SearchWP form to use in header/search layouts.
	 *
	 * @return void
	 */
	function mrn_base_stack_seed_searchwp_form() {
		if ( ! shortcode_exists( 'searchwp_form' ) && ! class_exists( 'SearchWP\\Settings' ) ) {
			return;
		}

		$form_id = mrn_base_stack_get_default_searchwp_form_id();

		if ( $form_id < 1 ) {
			$stored_forms = get_option( 'searchwp_forms', '' );
			$decoded      = is_string( $stored_forms ) && '' !== $stored_forms ? json_decode( $stored_forms, true ) : array();
			$decoded      = is_array( $decoded ) ? $decoded : array();
			$decoded      = wp_parse_args(
				$decoded,
				array(
					'forms'   => array(),
					'next_id' => 1,
				)
			);

			if ( ! isset( $decoded['forms'] ) || ! is_array( $decoded['forms'] ) ) {
				$decoded['forms'] = array();
			}

			$form_id = absint( $decoded['next_id'] ?? 1 );
			$form_id = $form_id > 0 ? $form_id : 1;

			while ( isset( $decoded['forms'][ $form_id ] ) ) {
				++$form_id;
			}

			$decoded['forms'][ $form_id ] = array(
				'id'                           => $form_id,
				'title'                        => __( 'Site Search', 'mrn-base-stack' ),
				'engine'                       => 'default',
				'target_url'                   => '/',
				'input_name'                   => 's',
				'template-include-search-form' => true,
				'swp-layout-theme'             => 'basic',
				'category-search'              => false,
				'quick-search'                 => false,
				'advanced-search'              => false,
				'voice-search'                 => false,
				'voice-search-auto-submit'     => false,
				'post-type'                    => array(),
				'category'                     => array(),
				'field-label'                  => '',
				'search-button'                => true,
				'quick-search-items'           => array(),
				'advanced-search-filters'      => array( 'authors', 'post_types', 'tags' ),
				'swp-sfinput-shape'            => '',
				'swp-sfbutton-filled'          => '',
				'search-form-color'            => '',
				'search-form-font-size'        => '',
				'button-label'                 => __( 'Search', 'mrn-base-stack' ),
				'button-background-color'      => '',
				'button-font-color'            => '',
				'button-font-size'             => '',
			);
			$decoded['next_id']           = $form_id + 1;
			$encoded                      = wp_json_encode( $decoded );
			$updated                      = class_exists( 'SearchWP\\Settings' ) ? \SearchWP\Settings::update( 'forms', $encoded ) : null;

			if ( null === $updated ) {
				update_option( 'searchwp_forms', $encoded, false );
			}
		}

		$form_id = absint( $form_id );
		if ( $form_id < 1 ) {
			return;
		}

		update_option( 'mrn_base_stack_searchwp_form_id', $form_id, false );

		$forms = mrn_base_stack_get_searchwp_forms();
		if ( ! isset( $forms[ $form_id ] ) ) {
			return;
		}

		$selected_header_form_id = function_exists( 'get_field' ) ? absint( get_field( 'header_searchwp_form_id', 'option' ) ) : absint( get_option( 'options_header_searchwp_form_id', 0 ) );
		if ( $selected_header_form_id > 0 && isset( $forms[ $selected_header_form_id ] ) ) {
			return;
		}

		update_option( 'options_header_searchwp_form_id', (string) $form_id, false );
		update_option( '_options_header_searchwp_form_id', 'field_mrn_theme_header_searchwp_form_id', false );
	}
endif;
add_action( 'acf/init', 'mrn_base_stack_seed_searchwp_form', 10 );
add_action( 'init', 'mrn_base_stack_seed_searchwp_form', 30 );

if ( ! function_exists( 'mrn_base_stack_get_searchwp_form_choices' ) ) :
	/**
	 * Build ACF-ready SearchWP form choices.
	 *
	 * @return array<string, string>
	 */
	function mrn_base_stack_get_searchwp_form_choices() {
		$forms   = mrn_base_stack_get_searchwp_forms();
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
	 * Return rendered SearchWP form markup.
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

		return '';
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
		$header_options = function_exists( 'mrn_base_stack_get_theme_header_footer_options' ) ? mrn_base_stack_get_theme_header_footer_options() : array();
		$form_id        = absint( $header_options['header_searchwp_form_id'] ?? 0 );
		$form_markup    = mrn_base_stack_get_searchwp_form_markup( $form_id );
		$search_style   = isset( $header_options['header_search_style'] ) ? (string) $header_options['header_search_style'] : 'full';

		if ( '' === trim( $form_markup ) ) {
			return;
		}

		if ( 'icon_only' === $search_style ) {
			$search_query = get_search_query();
			$is_expanded  = '' !== $search_query;
			$classes      = array(
				'mrn-site-search',
				'mrn-site-search--searchwp',
				'mrn-site-search--icon-only',
			);

			if ( $is_expanded ) {
				$classes[] = 'is-expanded';
			}
			?>
			<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-mrn-search-toggle>
				<button type="button" class="mrn-site-search__toggle" aria-expanded="<?php echo $is_expanded ? 'true' : 'false'; ?>" aria-controls="mrn-header-searchwp-input-wrap">
					<?php echo mrn_base_stack_get_header_search_icon_markup( $header_options ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Open search', 'mrn-base-stack' ); ?></span>
				</button>
				<div class="mrn-site-search__input-wrap" id="mrn-header-searchwp-input-wrap">
					<div class="mrn-site-search__field mrn-site-search__field--searchwp">
						<span class="mrn-site-search__prompt" aria-hidden="true" data-mrn-search-prompt><?php esc_html_e( 'Search', 'mrn-base-stack' ); ?></span>
						<?php echo $form_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SearchWP shortcode output is plugin-rendered markup. ?>
						<button type="button" class="mrn-site-search__clear" aria-label="<?php esc_attr_e( 'Clear search', 'mrn-base-stack' ); ?>" <?php echo '' === $search_query ? 'hidden' : ''; ?>>
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
				</div>
			</div>
			<?php
			return;
		}

		echo '<div class="mrn-site-search mrn-site-search--searchwp">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SearchWP shortcode output is plugin-rendered markup.
		echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

if ( ! function_exists( 'mrn_base_stack_render_singular_breadcrumbs' ) ) :
	/**
	 * Render the stack breadcrumb slot between the hero and content shell.
	 *
	 * @param int $post_id Current post ID.
	 */
	function mrn_base_stack_render_singular_breadcrumbs( $post_id = 0 ) {
		if ( ! function_exists( 'mrn_render_breadcrumbs' ) || ! is_singular() ) {
			return;
		}

		$post_id = $post_id ? (int) $post_id : get_the_ID();
		$markup  = mrn_render_breadcrumbs(
			array(
				'echo'       => false,
				'post_id'    => $post_id,
				'placement'  => 'singular_header',
				'wrap_class' => 'mrn-breadcrumbs-wrap--singular',
			)
		);

		if ( '' === trim( (string) $markup ) ) {
			return;
		}

		echo '<div class="mrn-singular-breadcrumbs-slot mrn-layout-container mrn-layout-container--wide" data-mrn-layout-slot="breadcrumbs">' . $markup . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
endif;

if ( ! function_exists( 'mrn_base_stack_render_singular_accessible_title' ) ) :
	/**
	 * Render a non-visual H1 when a singular entry has no hero H1.
	 *
	 * @param int $post_id Current post ID.
	 */
	function mrn_base_stack_render_singular_accessible_title( $post_id = 0 ) {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = $post_id ? (int) $post_id : get_the_ID();
		$title   = $post_id ? trim( (string) get_the_title( $post_id ) ) : '';

		if ( '' === $title ) {
			return;
		}

		printf(
			'<h1 class="screen-reader-text mrn-singular-title-fallback">%s</h1>',
			esc_html( $title )
		);
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

if ( ! function_exists( 'mrn_base_stack_get_header_utility_message_options' ) ) :
	/**
	 * Return normalized Header Utility Message options.
	 *
	 * @return array{enabled:bool,text:string,link:array<string,string>,link_style:string,link_icon:array<string,mixed>}
	 */
	function mrn_base_stack_get_header_utility_message_options() {
		$options = array(
			'enabled'    => false,
			'text'       => '',
			'link'       => array(
				'url'    => '',
				'title'  => '',
				'target' => '',
			),
			'link_style' => 'link',
			'link_icon'  => array(
				'enabled'    => false,
				'source'     => 'dashicons',
				'dashicon'   => 'dashicons-arrow-right-alt2',
				'fa_class'   => 'fa-solid fa-arrow-right',
				'media_icon' => null,
				'gap'        => 8,
			),
		);

		if ( ! function_exists( 'get_field' ) ) {
			return $options;
		}

		$link                = get_field( 'header_utility_message_link', 'option' );
		$link_style          = sanitize_key( (string) get_field( 'header_utility_message_link_style', 'option' ) );
		$link_icon_source    = sanitize_key( (string) get_field( 'header_utility_message_link_icon_source', 'option' ) );
		$link_icon_dashicon  = (string) get_field( 'header_utility_message_link_icon_dashicon', 'option' );
		$link_icon_fa_class  = trim( (string) get_field( 'header_utility_message_link_icon_fa_class', 'option' ) );
		$link_icon_media     = get_field( 'header_utility_message_link_icon_media', 'option' );
		$link_icon_gap       = get_field( 'header_utility_message_link_icon_gap', 'option' );
		$dashicon_choices    = function_exists( 'mrn_base_stack_get_header_search_standard_icon_choices' ) ? array_keys( mrn_base_stack_get_header_search_standard_icon_choices() ) : array();
		$fontawesome_choices = function_exists( 'mrn_base_stack_get_header_search_fontawesome_choices' ) ? array_keys( mrn_base_stack_get_header_search_fontawesome_choices() ) : array();

		if ( ! in_array( $link_style, array( 'link', 'button' ), true ) ) {
			$link_style = 'link';
		}

		if ( ! in_array( $link_icon_source, array( 'dashicons', 'fontawesome', 'media' ), true ) ) {
			$link_icon_source = 'dashicons';
		}

		if ( ! in_array( $link_icon_dashicon, $dashicon_choices, true ) ) {
			$link_icon_dashicon = 'dashicons-arrow-right-alt2';
		}

		if ( ! in_array( $link_icon_fa_class, $fontawesome_choices, true ) ) {
			$link_icon_fa_class = 'fa-solid fa-arrow-right';
		}

		if ( ! function_exists( 'mrn_base_stack_image_has_content' ) || ! mrn_base_stack_image_has_content( $link_icon_media ) ) {
			$link_icon_media = null;
		}

		$link_icon_gap = is_numeric( $link_icon_gap ) ? max( 0, (float) $link_icon_gap ) : 8;

		$options['enabled']    = (bool) get_field( 'header_utility_message_enabled', 'option' );
		$options['text']       = function_exists( 'mrn_base_stack_sanitize_limited_inline_html' )
			? mrn_base_stack_sanitize_limited_inline_html( get_field( 'header_utility_message_text', 'option' ) )
			: wp_kses(
				(string) get_field( 'header_utility_message_text', 'option' ),
				array(
					'span'   => array(
						'class' => true,
					),
					'strong' => array(),
					'em'     => array(),
					'br'     => array(),
				)
			);
		$options['link_style'] = $link_style;
		$options['link_icon']  = array(
			'enabled'    => (bool) get_field( 'header_utility_message_link_icon_enabled', 'option' ),
			'source'     => $link_icon_source,
			'dashicon'   => $link_icon_dashicon,
			'fa_class'   => $link_icon_fa_class,
			'media_icon' => $link_icon_media,
			'gap'        => $link_icon_gap,
		);

		if ( is_array( $link ) && ! empty( $link['url'] ) && ! empty( $link['title'] ) ) {
			$options['link'] = array(
				'url'    => esc_url( (string) $link['url'] ),
				'title'  => sanitize_text_field( (string) $link['title'] ),
				'target' => ! empty( $link['target'] ) ? sanitize_key( (string) $link['target'] ) : '',
			);
		}

		return $options;
	}
endif;

if ( ! function_exists( 'mrn_base_stack_format_header_utility_message_icon_gap' ) ) :
	/**
	 * Format the utility message link icon gap as a CSS length.
	 *
	 * @param mixed $gap Raw gap value.
	 * @return string
	 */
	function mrn_base_stack_format_header_utility_message_icon_gap( $gap ) {
		$gap = is_numeric( $gap ) ? max( 0, (float) $gap ) : 8;

		if ( 0.0 === fmod( $gap, 1.0 ) ) {
			return (string) (int) $gap . 'px';
		}

		return rtrim( rtrim( sprintf( '%.2f', $gap ), '0' ), '.' ) . 'px';
	}
endif;

if ( ! function_exists( 'mrn_base_stack_get_header_utility_message_link_icon_markup' ) ) :
	/**
	 * Return the utility message trailing link icon markup.
	 *
	 * @param array<string, mixed> $options Header Utility Message options.
	 * @return string
	 */
	function mrn_base_stack_get_header_utility_message_link_icon_markup( array $options ) {
		$link_icon = isset( $options['link_icon'] ) && is_array( $options['link_icon'] ) ? $options['link_icon'] : array();

		if ( empty( $link_icon['enabled'] ) ) {
			return '';
		}

		$source     = isset( $link_icon['source'] ) ? sanitize_key( (string) $link_icon['source'] ) : 'dashicons';
		$gap        = mrn_base_stack_format_header_utility_message_icon_gap( $link_icon['gap'] ?? 8 );
		$style_attr = ' style="--mrn-link-icon-gap:' . esc_attr( $gap ) . ';"';
		$classes    = 'mrn-ui__link-icon mrn-ui__link-icon--right mrn-site-header__utility-message-link-icon';

		if ( 'fontawesome' === $source ) {
			$fa_class = isset( $link_icon['fa_class'] ) ? trim( (string) $link_icon['fa_class'] ) : '';

			if ( '' === $fa_class || ( function_exists( 'mrn_fapm_icon_is_allowed' ) && ! mrn_fapm_icon_is_allowed( $fa_class ) ) ) {
				return '';
			}

			return '<span class="' . esc_attr( $classes . ' mrn-ui__link-icon--fontawesome' ) . '" aria-hidden="true"' . $style_attr . '><i class="' . esc_attr( $fa_class ) . '"></i></span>';
		}

		if ( 'media' === $source ) {
			$attachment_id = function_exists( 'mrn_base_stack_get_image_attachment_id' ) ? mrn_base_stack_get_image_attachment_id( $link_icon['media_icon'] ?? null ) : 0;

			if ( $attachment_id > 0 ) {
				$image = function_exists( 'mrn_base_stack_get_attachment_image' ) ? mrn_base_stack_get_attachment_image(
					$attachment_id,
					'mrn-icon',
					array(
						'class'       => 'mrn-ui__link-icon-image',
						'alt'         => '',
						'aria-hidden' => 'true',
					)
				) : '';

				if ( is_string( $image ) && '' !== $image ) {
					return '<span class="' . esc_attr( $classes . ' mrn-ui__link-icon--media' ) . '" aria-hidden="true"' . $style_attr . '>' . $image . '</span>';
				}
			}

			return '';
		}

		$dashicon         = isset( $link_icon['dashicon'] ) ? sanitize_html_class( (string) $link_icon['dashicon'] ) : 'dashicons-arrow-right-alt2';
		$dashicon_choices = function_exists( 'mrn_base_stack_get_header_search_standard_icon_choices' ) ? array_keys( mrn_base_stack_get_header_search_standard_icon_choices() ) : array();

		if ( ! in_array( $dashicon, $dashicon_choices, true ) ) {
			$dashicon = 'dashicons-arrow-right-alt2';
		}

		return '<span class="' . esc_attr( $classes . ' mrn-ui__link-icon--dashicons' ) . '" aria-hidden="true"' . $style_attr . '><span class="dashicons ' . esc_attr( $dashicon ) . '"></span></span>';
	}
endif;

if ( ! function_exists( 'mrn_base_stack_has_header_utility_message' ) ) :
	/**
	 * Determine whether the Header Utility Message should render.
	 *
	 * @return bool
	 */
	function mrn_base_stack_has_header_utility_message() {
		$options = mrn_base_stack_get_header_utility_message_options();

		return ! empty( $options['enabled'] ) && ( '' !== $options['text'] || '' !== $options['link']['url'] );
	}
endif;

if ( ! function_exists( 'mrn_base_stack_get_header_utility_message_markup' ) ) :
	/**
	 * Return Header Utility Message markup.
	 *
	 * @return string
	 */
	function mrn_base_stack_get_header_utility_message_markup() {
		if ( ! mrn_base_stack_has_header_utility_message() ) {
			return '';
		}

		$options      = mrn_base_stack_get_header_utility_message_options();
		$link_classes = array(
			'mrn-site-header__utility-message-link',
			'mrn-site-header__utility-message-link--' . sanitize_html_class( $options['link_style'] ),
		);

		if ( 'button' === $options['link_style'] ) {
			$link_classes[] = 'mrn-ui__link';
			$link_classes[] = 'mrn-ui__link--button';
		}

		ob_start();
		?>
		<div class="mrn-site-header__utility-message">
			<?php if ( '' !== $options['text'] ) : ?>
				<span class="mrn-site-header__utility-message-text"><?php echo $options['text']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized by mrn_base_stack_get_header_utility_message_options(). ?></span>
			<?php endif; ?>
			<?php if ( '' !== $options['link']['url'] ) : ?>
				<a class="<?php echo esc_attr( implode( ' ', array_unique( $link_classes ) ) ); ?>" href="<?php echo esc_url( $options['link']['url'] ); ?>"<?php echo '_blank' === $options['link']['target'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
					<span class="mrn-site-header__utility-message-link-label"><?php echo esc_html( $options['link']['title'] ); ?></span>
					<?php echo mrn_base_stack_get_header_utility_message_link_icon_markup( $options ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon helper escapes classes, styles, and media attributes. ?>
				</a>
			<?php endif; ?>
		</div>
		<?php

		return trim( (string) ob_get_clean() );
	}
endif;

if ( ! function_exists( 'mrn_base_stack_get_configured_social_link_rows' ) ) :
	/**
	 * Return configured social rows that can render in a public social slot.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function mrn_base_stack_get_configured_social_link_rows() {
		if ( ! function_exists( 'mrn_config_helper_get_social_links' ) ) {
			return array();
		}

		$social_links = mrn_config_helper_get_social_links();

		if ( ! is_array( $social_links ) || empty( $social_links ) ) {
			return array();
		}

		$renderable_links = array();
		foreach ( $social_links as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$url       = isset( $row['url'] ) ? esc_url( (string) $row['url'] ) : '';
			$name      = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
			$alt_text  = isset( $row['alt_text'] ) ? trim( (string) $row['alt_text'] ) : '';
			$icon_type = isset( $row['icon_type'] ) ? sanitize_key( (string) $row['icon_type'] ) : '';
			$has_icon  = ( 'fontawesome' === $icon_type && ! empty( $row['fa_class'] ) )
				|| ( 'dashicons' === $icon_type && ! empty( $row['dashicon'] ) )
				|| ( 'media' === $icon_type && ! empty( $row['icon_id'] ) );

			if ( '' === $url && '' === $name && '' === $alt_text && ! $has_icon ) {
				continue;
			}

			$row['url']         = $url;
			$renderable_links[] = $row;
		}

		return $renderable_links;
	}
endif;

if ( ! function_exists( 'mrn_base_stack_has_configured_social_links' ) ) :
	/**
	 * Determine whether configured social links can render.
	 *
	 * @return bool
	 */
	function mrn_base_stack_has_configured_social_links() {
		return ! empty( mrn_base_stack_get_configured_social_link_rows() );
	}
endif;

if ( ! function_exists( 'mrn_base_stack_render_social_links' ) ) :
	/**
	 * Render configured social links.
	 *
	 * @param array<string, mixed> $args Optional render args.
	 */
	function mrn_base_stack_render_social_links( array $args = array() ) {
		$social_links = mrn_base_stack_get_configured_social_link_rows();

		if ( ! is_array( $social_links ) || empty( $social_links ) ) {
			return;
		}

		$classes = array( 'mrn-social-links' );
		if ( ! empty( $args['class'] ) && is_scalar( $args['class'] ) ) {
			$extra_classes = preg_split( '/\s+/', trim( (string) $args['class'] ) );
			foreach ( is_array( $extra_classes ) ? $extra_classes : array() as $extra_class ) {
				$extra_class = sanitize_html_class( $extra_class );
				if ( '' !== $extra_class ) {
					$classes[] = $extra_class;
				}
			}
		}

		echo '<ul class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		foreach ( $social_links as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$url         = isset( $row['url'] ) ? esc_url( (string) $row['url'] ) : '';
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
			if ( '' !== $url ) {
				echo '<a class="mrn-social-links__link" href="' . $url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $accessible_label ) . '" title="' . esc_attr( $accessible_label ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<span class="mrn-social-links__link mrn-social-links__link--static" role="img" aria-label="' . esc_attr( $accessible_label ) . '" title="' . esc_attr( $accessible_label ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			if ( 'fontawesome' === $icon_type && ! empty( $row['fa_class'] ) ) {
				echo '<span class="mrn-social-links__icon" aria-hidden="true"><i class="' . esc_attr( (string) $row['fa_class'] ) . '"></i></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( 'dashicons' === $icon_type && ! empty( $row['dashicon'] ) ) {
				echo '<span class="mrn-social-links__icon" aria-hidden="true"><span class="dashicons ' . esc_attr( (string) $row['dashicon'] ) . '"></span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( '' !== $icon_markup ) {
				echo $icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<span class="mrn-social-links__text">' . esc_html( ucwords( str_replace( '-', ' ', $label ) ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			echo '' !== $url ? '</a>' : '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
endif;
