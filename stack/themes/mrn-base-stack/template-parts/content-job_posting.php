<?php
/**
 * Template part for displaying job posting entries.
 *
 * @package mrn-base-stack
 */

$mrn_post_id       = get_the_ID();
$mrn_is_singular   = is_singular( 'job_posting' );
$mrn_job_posting   = function_exists( 'mrn_base_stack_get_job_posting_data' ) ? mrn_base_stack_get_job_posting_data( $mrn_post_id ) : array();
$mrn_label         = isset( $mrn_job_posting['label'] ) ? trim( (string) $mrn_job_posting['label'] ) : '';
$mrn_heading       = isset( $mrn_job_posting['heading'] ) ? trim( (string) $mrn_job_posting['heading'] ) : '';
$mrn_subheading    = isset( $mrn_job_posting['subheading'] ) ? trim( (string) $mrn_job_posting['subheading'] ) : '';
$mrn_summary       = isset( $mrn_job_posting['summary'] ) ? (string) $mrn_job_posting['summary'] : '';
$mrn_categories    = get_the_term_list( $mrn_post_id, 'category', '', esc_html__( ', ', 'mrn-base-stack' ) );
$mrn_tags          = get_the_term_list( $mrn_post_id, 'post_tag', '', esc_html_x( ', ', 'list item separator', 'mrn-base-stack' ) );
$mrn_detail_labels = array(
	'department'           => __( 'Department', 'mrn-base-stack' ),
	'employment_type'      => __( 'Employment Type', 'mrn-base-stack' ),
	'workplace_type'       => __( 'Workplace Type', 'mrn-base-stack' ),
	'location'             => __( 'Location', 'mrn-base-stack' ),
	'compensation_note'    => __( 'Compensation', 'mrn-base-stack' ),
	'application_deadline' => __( 'Application Deadline', 'mrn-base-stack' ),
);

if ( ! empty( $mrn_job_posting['location_record'] ) ) {
	$mrn_location_title = get_the_title( (int) $mrn_job_posting['location_record'] );
	if ( '' !== trim( (string) $mrn_location_title ) ) {
		$mrn_job_posting['location'] = $mrn_location_title;
	}
}

$mrn_application_url   = isset( $mrn_job_posting['application_url'] ) ? trim( (string) $mrn_job_posting['application_url'] ) : '';
$mrn_application_email = isset( $mrn_job_posting['application_email'] ) ? trim( (string) $mrn_job_posting['application_email'] ) : '';
$mrn_apply_href        = '' !== $mrn_application_url ? $mrn_application_url : '';

if ( '' === $mrn_apply_href && is_email( $mrn_application_email ) ) {
	$mrn_apply_href = 'mailto:' . $mrn_application_email;
}
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'mrn-job-posting' ); ?>>
	<?php if ( $mrn_is_singular ) : ?>
		<?php
		$mrn_has_hero         = function_exists( 'mrn_base_stack_render_hero_builder' ) ? mrn_base_stack_render_hero_builder( $mrn_post_id ) : false;
		$mrn_sidebar_settings = function_exists( 'mrn_base_stack_get_singular_sidebar_settings' ) ? mrn_base_stack_get_singular_sidebar_settings( $mrn_post_id ) : array( 'layout' => 'none' );
		$mrn_sidebar_markup   = function_exists( 'mrn_base_stack_get_singular_sidebar_markup' ) ? mrn_base_stack_get_singular_sidebar_markup( $mrn_post_id ) : '';
		$mrn_has_sidebar      = 'none' !== ( $mrn_sidebar_settings['layout'] ?? 'none' ) && '' !== $mrn_sidebar_markup;
		$mrn_shell_classes    = array(
			'mrn-singular-shell',
			'mrn-singular-shell--job-posting',
		);

		if ( $mrn_has_sidebar ) {
			$mrn_shell_classes[] = 'mrn-singular-shell--has-sidebar';
			$mrn_shell_classes[] = 'mrn-singular-shell--sidebar-' . sanitize_html_class( $mrn_sidebar_settings['layout'] );
		}

		if ( function_exists( 'mrn_base_stack_render_singular_breadcrumbs' ) ) {
			mrn_base_stack_render_singular_breadcrumbs( $mrn_post_id );
		}
		?>

		<div class="<?php echo esc_attr( implode( ' ', $mrn_shell_classes ) ); ?>" data-mrn-layout-slot="content-shell">
			<div class="mrn-singular-shell__main">
				<?php if ( ! $mrn_has_hero ) : ?>
					<header class="entry-header">
						<?php if ( '' !== $mrn_label ) : ?>
							<p class="mrn-entry-label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $mrn_label ) : esc_html( $mrn_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<?php endif; ?>

						<?php if ( '' !== $mrn_heading ) : ?>
							<h1 class="entry-title"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $mrn_heading ) : esc_html( $mrn_heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h1>
						<?php else : ?>
							<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
						<?php endif; ?>

						<?php if ( '' !== $mrn_subheading ) : ?>
							<p class="entry-summary"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $mrn_subheading ) : esc_html( $mrn_subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<?php endif; ?>
					</header>
				<?php endif; ?>

				<?php if ( $mrn_categories || $mrn_tags ) : ?>
					<div class="entry-meta">
						<?php if ( $mrn_categories ) : ?>
							<span class="cat-links">
								<?php
								printf(
									/* translators: 1: list of categories. */
									esc_html__( 'Posted in %1$s', 'mrn-base-stack' ),
									$mrn_categories // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								);
								?>
							</span>
						<?php endif; ?>

						<?php if ( $mrn_tags ) : ?>
							<span class="tags-links">
								<?php
								printf(
									/* translators: 1: list of tags. */
									esc_html__( 'Tagged %1$s', 'mrn-base-stack' ),
									$mrn_tags // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								);
								?>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( has_post_thumbnail() ) : ?>
					<div class="post-thumbnail">
						<?php the_post_thumbnail( 'large' ); ?>
					</div>
				<?php endif; ?>

				<div class="entry-content entry-content--job-posting">
					<?php if ( '' !== trim( wp_strip_all_tags( $mrn_summary ) ) ) : ?>
						<section class="mrn-job-posting-section mrn-job-posting-section--summary">
							<h2><?php esc_html_e( 'Job Summary', 'mrn-base-stack' ); ?></h2>
							<?php echo wp_kses_post( $mrn_summary ); ?>
						</section>
					<?php endif; ?>

					<?php if ( ! empty( array_filter( array_intersect_key( $mrn_job_posting, $mrn_detail_labels ) ) ) ) : ?>
						<section class="mrn-job-posting-section mrn-job-posting-section--details" aria-labelledby="job-posting-details-<?php echo esc_attr( (string) $mrn_post_id ); ?>">
							<h2 id="job-posting-details-<?php echo esc_attr( (string) $mrn_post_id ); ?>">
								<?php esc_html_e( 'Job Details', 'mrn-base-stack' ); ?>
							</h2>
							<dl class="mrn-job-posting-details">
								<?php foreach ( $mrn_detail_labels as $mrn_detail_key => $mrn_detail_label ) : ?>
									<?php
									$mrn_detail_value = isset( $mrn_job_posting[ $mrn_detail_key ] ) ? trim( (string) $mrn_job_posting[ $mrn_detail_key ] ) : '';

									if ( 'application_deadline' === $mrn_detail_key && '' !== $mrn_detail_value ) {
										$mrn_deadline_timestamp = strtotime( $mrn_detail_value );
										if ( false !== $mrn_deadline_timestamp ) {
											$mrn_detail_value = date_i18n( get_option( 'date_format' ), $mrn_deadline_timestamp );
										}
									}

									if ( '' === $mrn_detail_value ) {
										continue;
									}
									?>
									<div class="mrn-job-posting-details__item">
										<dt><?php echo esc_html( $mrn_detail_label ); ?></dt>
										<dd><?php echo esc_html( $mrn_detail_value ); ?></dd>
									</div>
								<?php endforeach; ?>
							</dl>
						</section>
					<?php endif; ?>

					<?php
					$mrn_job_posting_sections = array(
						'responsibilities' => __( 'Responsibilities', 'mrn-base-stack' ),
						'qualifications'   => __( 'Qualifications', 'mrn-base-stack' ),
						'benefits'         => __( 'Benefits', 'mrn-base-stack' ),
					);
					?>

					<?php foreach ( $mrn_job_posting_sections as $mrn_section_key => $mrn_section_heading ) : ?>
						<?php $mrn_section_content = isset( $mrn_job_posting[ $mrn_section_key ] ) ? (string) $mrn_job_posting[ $mrn_section_key ] : ''; ?>
						<?php if ( '' !== trim( wp_strip_all_tags( $mrn_section_content ) ) ) : ?>
							<section class="mrn-job-posting-section mrn-job-posting-section--<?php echo esc_attr( sanitize_html_class( $mrn_section_key ) ); ?>">
								<h2><?php echo esc_html( $mrn_section_heading ); ?></h2>
								<?php echo wp_kses_post( $mrn_section_content ); ?>
							</section>
						<?php endif; ?>
					<?php endforeach; ?>

					<?php if ( '' !== $mrn_apply_href ) : ?>
						<p class="mrn-job-posting-apply">
							<a class="mrn-ui__button" href="<?php echo esc_url( $mrn_apply_href ); ?>">
								<?php esc_html_e( 'Apply for this Job', 'mrn-base-stack' ); ?>
							</a>
						</p>
					<?php endif; ?>

					<?php
					if ( function_exists( 'mrn_base_stack_render_content_builder' ) ) {
						mrn_base_stack_render_content_builder( $mrn_post_id );
					}

					if ( function_exists( 'mrn_base_stack_render_after_content_builder' ) ) {
						mrn_base_stack_render_after_content_builder( $mrn_post_id );
					}
					?>
				</div>
			</div>

			<?php if ( $mrn_has_sidebar ) : ?>
				<div class="mrn-singular-shell__sidebar">
					<?php echo $mrn_sidebar_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="mrn-shell-container mrn-shell-container--content">
			<header class="entry-header">
				<?php if ( '' !== $mrn_label ) : ?>
					<p class="mrn-entry-label"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $mrn_label ) : esc_html( $mrn_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php endif; ?>

				<?php if ( '' !== $mrn_heading ) : ?>
					<h2 class="entry-title">
						<a href="<?php echo esc_url( get_permalink() ); ?>" rel="bookmark">
							<?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $mrn_heading ) : esc_html( $mrn_heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</a>
					</h2>
				<?php else : ?>
					<?php the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' ); ?>
				<?php endif; ?>

				<?php if ( '' !== $mrn_subheading ) : ?>
					<p class="entry-summary"><?php echo function_exists( 'mrn_base_stack_format_heading_inline_html' ) ? mrn_base_stack_format_heading_inline_html( $mrn_subheading ) : esc_html( $mrn_subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php endif; ?>
			</header>

			<?php
			$mrn_archive_details = array_filter(
				array(
					isset( $mrn_job_posting['employment_type'] ) ? trim( (string) $mrn_job_posting['employment_type'] ) : '',
					isset( $mrn_job_posting['workplace_type'] ) ? trim( (string) $mrn_job_posting['workplace_type'] ) : '',
					isset( $mrn_job_posting['location'] ) ? trim( (string) $mrn_job_posting['location'] ) : '',
				)
			);
			?>

			<?php if ( ! empty( $mrn_archive_details ) ) : ?>
				<p class="entry-meta"><?php echo esc_html( implode( ' | ', $mrn_archive_details ) ); ?></p>
			<?php endif; ?>

			<?php if ( has_post_thumbnail() ) : ?>
				<a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
					<?php the_post_thumbnail( 'large' ); ?>
				</a>
			<?php endif; ?>

			<?php
			$mrn_archive_text = function_exists( 'mrn_base_stack_get_job_posting_excerpt' ) ? mrn_base_stack_get_job_posting_excerpt( $mrn_post_id ) : '';
			if ( '' !== $mrn_archive_text ) :
				?>
				<div class="entry-summary">
					<p><?php echo esc_html( $mrn_archive_text ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</article>
