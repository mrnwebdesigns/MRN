<?php

namespace MRN_Mega_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Renderer {
	private static $assignments = null;
	private static $render_instances = 0;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'strip_native_mega_children' ), 20, 2 );
		add_filter( 'nav_menu_css_class', array( __CLASS__, 'menu_item_classes' ), 10, 4 );
		add_filter( 'nav_menu_item_title', array( __CLASS__, 'menu_item_title' ), 20, 4 );
		add_filter( 'walker_nav_menu_start_el', array( __CLASS__, 'append_panel' ), 10, 4 );
	}

	/**
	 * Avoid rendering a duplicate hidden submenu when a mega panel owns the children.
	 *
	 * The panel reads canonical items directly from the WordPress menu, so removing
	 * child objects here affects only this wp_nav_menu() output instance.
	 *
	 * @param array<int, \WP_Post> $items Sorted nav menu items.
	 * @param object               $args  wp_nav_menu() arguments.
	 * @return array<int, \WP_Post>
	 */
	public static function strip_native_mega_children( $items, $args ) {
		if ( empty( $items ) || ! is_array( $items ) ) {
			return $items;
		}
		if ( self::uses_native_mobile_fallback( $args ) ) {
			return $items;
		}

		$menu_id = 0;
		if ( isset( $args->menu ) && is_object( $args->menu ) && isset( $args->menu->term_id ) ) {
			$menu_id = absint( $args->menu->term_id );
		} elseif ( isset( $args->menu ) ) {
			$menu_id = absint( $args->menu );
		}
		if ( ! $menu_id ) {
			$first_item = reset( $items );
			$menu_ids   = $first_item ? wp_get_object_terms( absint( $first_item->ID ), 'nav_menu', array( 'fields' => 'ids' ) ) : array();
			$menu_id    = ! is_wp_error( $menu_ids ) && $menu_ids ? absint( reset( $menu_ids ) ) : 0;
		}
		if ( ! $menu_id || ! Plugin::is_menu_mega( $menu_id ) ) {
			return $items;
		}
		$layout_id      = Plugin::get_menu_layout_id( $menu_id );
		$visible_parent = $layout_id ? Plugin::get_layout_parent_ids( $layout_id, $menu_id ) : array();
		if ( $layout_id && $visible_parent ) {
			$parent_by_id = array();
			foreach ( $items as $item ) {
				$parent_by_id[ absint( $item->ID ) ] = absint( $item->menu_item_parent );
			}
			$items = array_values(
				array_filter(
					$items,
					static function ( $item ) use ( $visible_parent, $parent_by_id ) {
						$root = absint( $item->ID );
						$seen = array();
						while ( isset( $parent_by_id[ $root ] ) && $parent_by_id[ $root ] && ! isset( $seen[ $root ] ) ) {
							$seen[ $root ] = true;
							$root          = $parent_by_id[ $root ];
						}
						return in_array( $root, $visible_parent, true );
					}
				)
			);
		}
		$assigned_parents = array_intersect_key( self::assignments(), array_flip( array_map( 'absint', wp_list_pluck( $items, 'ID' ) ) ) );
		if ( empty( $assigned_parents ) ) {
			return $items;
		}
		$parent_by_id = array();
		foreach ( $items as $item ) {
			$parent_by_id[ absint( $item->ID ) ] = absint( $item->menu_item_parent );
		}

		return array_values(
			array_filter(
				$items,
				static function ( $item ) use ( $assigned_parents, $parent_by_id ) {
					$parent = absint( $item->menu_item_parent );
					$seen   = array();
					while ( $parent && ! isset( $seen[ $parent ] ) ) {
						if ( isset( $assigned_parents[ $parent ] ) ) {
							return false;
						}
						$seen[ $parent ] = true;
						$parent          = isset( $parent_by_id[ $parent ] ) ? $parent_by_id[ $parent ] : 0;
					}
					return true;
				}
			)
		);
	}

	/**
	 * Whether this menu keeps its native hierarchy for a responsive mobile fallback.
	 *
	 * The stack renders one primary menu for both desktop and mobile. Keeping the
	 * native children in that instance lets desktop use mega panels while the
	 * mobile drawer uses the standard nested menu controls.
	 *
	 * @param object|array<string, mixed> $args wp_nav_menu() arguments.
	 * @return bool
	 */
	private static function uses_native_mobile_fallback( $args ) {
		if ( is_object( $args ) ) {
			return ! empty( $args->mrn_mega_menu_mobile_fallback );
		}

		return is_array( $args ) && ! empty( $args['mrn_mega_menu_mobile_fallback'] );
	}

	public static function enqueue_assets() {
		if ( empty( self::assignments() ) ) {
			return;
		}
		wp_enqueue_style( 'mrn-mega-menu', MRN_MEGA_MENU_URL . 'assets/css/mega-menu.css', array(), MRN_MEGA_MENU_VERSION );
		wp_enqueue_style( 'dashicons' );
		if ( function_exists( 'mrn_shared_assets_enqueue_fontawesome' ) ) {
			mrn_shared_assets_enqueue_fontawesome( 'mrn-mega-menu-fontawesome', 'mega-menu' );
		}
		wp_enqueue_script( 'mrn-mega-menu', MRN_MEGA_MENU_URL . 'assets/js/mega-menu.js', array(), MRN_MEGA_MENU_VERSION, true );
	}

	public static function menu_item_classes( $classes, $item, $args, $depth ) {
		if ( 0 === (int) $depth && isset( self::assignments()[ $item->ID ] ) ) {
			$classes[] = 'mrn-has-mega-menu';
		}
		return $classes;
	}

	/**
	 * Apply a layout-specific label without changing the native menu item.
	 *
	 * @param string   $title Menu item title.
	 * @param \WP_Post $item  Menu item object.
	 * @param object   $args  Menu arguments.
	 * @param int      $depth Menu depth.
	 * @return string
	 */
	public static function menu_item_title( $title, $item, $args, $depth ) {
		unset( $args );
		$assignments = self::assignments();
		if ( 0 !== (int) $depth || ! isset( $assignments[ $item->ID ] ) ) {
			return $title;
		}
		$panel = self::find_panel( absint( $assignments[ $item->ID ] ), $item->ID );
		$label = is_array( $panel ) ? sanitize_text_field( $panel['label_override'] ?? '' ) : '';
		return $label ? Stack_Integration::resolve_text( $label ) : $title;
	}

	public static function append_panel( $item_output, $item, $depth, $args ) {
		$assignments = self::assignments();
		if ( 0 !== (int) $depth || ! isset( $assignments[ $item->ID ] ) ) {
			return $item_output;
		}
		$panel_id = absint( $assignments[ $item->ID ] );
		if ( $panel_id && 'publish' !== get_post_status( $panel_id ) ) {
			return $item_output;
		}
		$panel = self::find_panel( $panel_id, $item->ID );
		if ( ! is_array( $panel ) ) {
			return $item_output;
		}
		$item_icon  = Plugin::sanitize_icon( $panel['item_icon'] ?? array() );
		$arrow_icon = Plugin::sanitize_icon( $panel['arrow_icon'] ?? array() );
		if ( ! $item_icon['value'] ) {
			$item_icon = Plugin::get_menu_item_icon( $item->ID, 'item' );
		}
		if ( ! $arrow_icon['value'] ) {
			$arrow_icon = Plugin::get_menu_item_icon( $item->ID, 'arrow' );
		}

		self::$render_instances++;
		$instance   = absint( $item->ID ) . '-' . self::$render_instances;
		$control_id = 'mrn-mega-menu-trigger-' . $instance;
		$panel_dom  = 'mrn-mega-menu-panel-' . $instance;
		$label      = sanitize_text_field( $panel['label_override'] ?? '' );
		$label      = $label ? Stack_Integration::resolve_text( $label ) : wp_strip_all_tags( $item->title );
		$attributes = sprintf(
			'id="%1$s" data-mrn-mega-menu-trigger %3$s role="button" aria-expanded="false" aria-controls="%2$s" aria-haspopup="true"',
			esc_attr( $control_id ),
			esc_attr( $panel_dom ),
			$arrow_icon['value'] ? 'data-mrn-custom-arrow' : ''
		);
		$trigger_output = preg_replace( '/<a\s/', '<a ' . $attributes . ' ', $item_output, 1 );
		if ( ! is_string( $trigger_output ) ) {
			return $item_output;
		}

		$item_icon_html = self::render_icon_markup( $item_icon, 'mrn-mega-menu__trigger-icon' );
		$arrow_html     = self::render_icon_markup( $arrow_icon, 'mrn-mega-menu__trigger-arrow' );
		if ( $item_icon_html ) {
			$trigger_output = preg_replace_callback(
				'/(<a\b[^>]*>)/',
				static function ( $matches ) use ( $item_icon_html ) {
					return $matches[1] . $item_icon_html;
				},
				$trigger_output,
				1
			);
		}
		if ( $arrow_html ) {
			$trigger_output = preg_replace_callback(
				'/<\/a>/',
				static function () use ( $arrow_html ) {
					return $arrow_html . '</a>';
				},
				$trigger_output,
				1
			);
		}

		return $trigger_output . self::render_panel( $panel_id, $panel, $panel_dom, $control_id, $label, $item->ID );
	}

	private static function find_panel( $panel_id, $assigned_item_id ) {
		$layout = Plugin::get_layout( $panel_id );
		$panels = isset( $layout['panels'] ) && is_array( $layout['panels'] ) ? $layout['panels'] : array();
		$panel  = null;
		foreach ( $panels as $candidate ) {
			if ( absint( $candidate['menu_item_id'] ?? 0 ) === absint( $assigned_item_id ) ) {
				$panel = $candidate;
				break;
			}
		}
		if ( ! $panel_id && ! $panel && $panels ) {
			$panel = reset( $panels );
		}
		return is_array( $panel ) ? $panel : null;
	}

	private static function render_panel( $panel_id, $panel, $panel_dom, $control_id, $label, $assigned_item_id ) {
		if ( ! is_array( $panel ) ) {
			return '';
		}
		$layout  = Plugin::get_layout( $panel_id );
		$columns = isset( $panel['columns'] ) && is_array( $panel['columns'] ) ? $panel['columns'] : array();
		$child_arrow_icon = Plugin::sanitize_icon( $panel['child_arrow_icon'] ?? ( $panel['child_icon'] ?? array() ) );
		$blocks  = array();
		foreach ( $columns as $column ) {
			foreach ( isset( $column['blocks'] ) && is_array( $column['blocks'] ) ? $column['blocks'] : array() as $block ) {
				$blocks[] = $block;
			}
		}
		// Mega menus are column-based. Legacy grid coordinates are intentionally ignored.
		$is_grid = false;
		$width   = isset( $layout['width'] ) && 'full' === $layout['width'] ? 'full' : 'content';
		$classes = array( 'mrn-mega-menu', 'mrn-mega-menu--' . $width );
		if ( Stack_Integration::is_stack_available() ) {
			$classes[] = 'mrn-mega-menu--stack';
		}
		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" id="<?php echo esc_attr( $panel_dom ); ?>" aria-labelledby="<?php echo esc_attr( $control_id ); ?>" data-mrn-mega-menu data-runtime-mode="<?php echo Stack_Integration::is_stack_available() ? 'stack' : 'standalone'; ?>" hidden>
			<div class="mrn-mega-menu__surface">
				<div class="mrn-mega-menu__mobile-header">
					<strong><?php echo esc_html( $label ); ?></strong>
					<button type="button" class="mrn-mega-menu__close"><span aria-hidden="true">&times;</span><span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Close %s menu', 'mrn-mega-menu' ), $label ) ); ?></span></button>
				</div>
				<div class="mrn-mega-menu__columns<?php echo $is_grid ? ' mrn-mega-menu__columns--grid' : ''; ?>" data-columns="<?php echo esc_attr( max( 1, min( 12, count( $columns ) ) ) ); ?>" data-grid-layout="<?php echo $is_grid ? 'true' : 'false'; ?>" style="--mrn-mega-columns: <?php echo esc_attr( max( 1, min( 12, count( $columns ) ) ) ); ?>">
					<?php if ( $is_grid ) : ?>
						<?php foreach ( $blocks as $block ) : ?><?php self::render_block( $block, $assigned_item_id, true, $child_arrow_icon ); ?><?php endforeach; ?>
					<?php else : ?>
						<?php foreach ( $columns as $column ) : ?>
							<div class="mrn-mega-menu__column">
								<?php foreach ( isset( $column['blocks'] ) && is_array( $column['blocks'] ) ? $column['blocks'] : array() as $block ) : ?><?php self::render_block( $block, $assigned_item_id, false, $child_arrow_icon ); ?><?php endforeach; ?>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_block( $block, $assigned_item_id, $use_grid = false, $child_arrow_icon = array() ) {
		$type = isset( $block['type'] ) ? $block['type'] : '';
		if ( ! in_array( $type, array( 'menu', 'links', 'categories', 'products', 'promo', 'reusable' ), true ) ) {
			return;
		}
		$title         = isset( $block['title'] ) ? Stack_Integration::resolve_text( $block['title'] ) : '';
		$reusable_html = '';
		if ( 'reusable' === $type ) {
			$reusable_html = Stack_Integration::render_reusable_block( isset( $block['reusable_block_id'] ) ? $block['reusable_block_id'] : 0 );
			if ( '' === $reusable_html ) {
				return;
			}
		}
		?>
		<?php
		$style = '';
		if ( $use_grid ) {
			$column = max( 1, absint( $block['grid_column'] ) );
			$row    = max( 1, absint( $block['grid_row'] ) );
			$span   = max( 1, absint( isset( $block['column_span'] ) ? $block['column_span'] : 1 ) );
			$style  = sprintf( 'grid-column:%1$d / span %2$d;grid-row:%3$d', $column, $span, $row );
		}
		?>
		<section class="mrn-mega-menu__block mrn-mega-menu__block--<?php echo esc_attr( $type ); ?>"<?php echo $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>>
			<?php if ( $title ) : ?><h3 class="mrn-mega-menu__heading"><?php echo esc_html( $title ); ?></h3><?php endif; ?>
			<?php
			if ( 'menu' === $type ) {
				self::render_native_menu( $block, $assigned_item_id, $child_arrow_icon );
			} elseif ( 'links' === $type ) {
				self::render_links( $block );
			} elseif ( 'categories' === $type ) {
				self::render_categories( $block );
			} elseif ( 'products' === $type ) {
				self::render_products( $block );
			} elseif ( 'reusable' === $type ) {
				echo $reusable_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is rendered by the trusted Reusable Block Library template contract.
			} else {
				self::render_promo( $block );
			}
			?>
		</section>
		<?php
	}

	private static function render_native_menu( $block, $assigned_item_id, $child_arrow_icon = array() ) {
		$source       = isset( $block['source'] ) && 'selected' === $block['source'] ? 'selected' : 'assigned_children';
		$menu_id      = isset( $block['menu_id'] ) ? absint( $block['menu_id'] ) : 0;
		$root_item_id = isset( $block['root_item_id'] ) ? absint( $block['root_item_id'] ) : 0;
		$branch_mode  = isset( $block['branch_mode'] ) && in_array( $block['branch_mode'], array( 'parent_and_children', 'children_only', 'parent_only' ), true ) ? $block['branch_mode'] : 'parent_and_children';
		$column_index = isset( $block['distribution_index'] ) ? absint( $block['distribution_index'] ) : 0;
		$column_total = isset( $block['distribution_total'] ) ? max( 1, min( 12, absint( $block['distribution_total'] ) ) ) : 1;
		$parent       = 0;

		if ( 'assigned_children' === $source ) {
			$menu_ids = wp_get_object_terms( absint( $assigned_item_id ), 'nav_menu', array( 'fields' => 'ids' ) );
			if ( is_wp_error( $menu_ids ) || empty( $menu_ids ) ) {
				return;
			}
			$menu_id = absint( reset( $menu_ids ) );
			$parent  = absint( $assigned_item_id );
		}

		if ( ! $menu_id ) {
			return;
		}

		$items = wp_get_nav_menu_items( $menu_id, array( 'update_post_term_cache' => false ) );
		if ( empty( $items ) || is_wp_error( $items ) ) {
			return;
		}

		$children    = array();
		$items_by_id = array();
		foreach ( $items as $item ) {
			$item_parent                = absint( $item->menu_item_parent );
			$children[ $item_parent ][] = $item;
			$items_by_id[ $item->ID ]   = $item;
		}

		if ( 'selected' === $source && $root_item_id ) {
			if ( ! isset( $items_by_id[ $root_item_id ] ) ) {
				return;
			}

			if ( 'children_only' === $branch_mode ) {
				$parent = $root_item_id;
			} else {
				?>
				<ul class="mrn-mega-menu__native-menu">
					<?php self::render_native_menu_item( $items_by_id[ $root_item_id ], $children, 0, array(), 'parent_and_children' === $branch_mode, $child_arrow_icon, false ); ?>
				</ul>
				<?php
				return;
			}
		}

		if ( empty( $children[ $parent ] ) ) {
			return;
		}
		if ( 'assigned_children' === $source && $column_total > 1 ) {
			$item_count = count( $children[ $parent ] );
			$start      = (int) floor( $item_count * min( $column_index, $column_total - 1 ) / $column_total );
			$end        = (int) floor( $item_count * min( $column_index + 1, $column_total ) / $column_total );
			$children[ $parent ] = array_slice( $children[ $parent ], $start, max( 0, $end - $start ) );
			if ( empty( $children[ $parent ] ) ) {
				return;
			}
		}
		?>
		<ul class="mrn-mega-menu__native-menu mrn-mega-menu__native-menu--direct-children">
			<?php self::render_native_menu_level( $children, $parent, 0, array(), $child_arrow_icon ); ?>
		</ul>
		<?php
	}

	private static function render_native_menu_level( $children, $parent, $depth, $visited, $child_arrow_icon = array() ) {
		if ( $depth > 3 || empty( $children[ $parent ] ) ) {
			return;
		}

		foreach ( $children[ $parent ] as $item ) {
			self::render_native_menu_item( $item, $children, $depth, $visited, true, $child_arrow_icon );
		}
	}

	private static function render_native_menu_item( $item, $children, $depth, $visited, $include_children, $child_arrow_icon = array(), $use_child_default = true ) {
			$item_id = absint( $item->ID );
			if ( ! $item_id || isset( $visited[ $item_id ] ) ) {
				return;
			}
			$next_visited             = $visited;
			$next_visited[ $item_id ] = true;
			$has_children             = $include_children && ! empty( $children[ $item_id ] );
			$item_icon                = Plugin::get_menu_item_icon( $item_id, 'item' );
			$arrow_icon               = Plugin::get_menu_item_icon( $item_id, 'arrow' );
			if ( $use_child_default && ! $arrow_icon['value'] ) {
				$arrow_icon = Plugin::sanitize_icon( $child_arrow_icon );
			}
			$target                   = '_blank' === $item->target ? '_blank' : '';
			$rel                      = trim( sanitize_text_field( $item->xfn ) );
			if ( '_blank' === $target && false === strpos( $rel, 'noopener' ) ) {
				$rel = trim( $rel . ' noopener' );
			}
			?>
			<li class="<?php echo $has_children ? 'mrn-mega-menu__native-item--parent' : ''; ?>">
				<a href="<?php echo esc_url( $item->url ); ?>"<?php echo $target ? ' target="_blank"' : ''; ?><?php echo $rel ? ' rel="' . esc_attr( $rel ) . '"' : ''; ?>><?php echo self::render_icon_markup( $item_icon, 'mrn-mega-menu__item-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes every icon type. ?><?php echo esc_html( Stack_Integration::resolve_text( $item->title ) ); ?><?php if ( ! $has_children ) : ?><?php echo $arrow_icon['value'] ? self::render_icon_markup( $arrow_icon, 'mrn-mega-menu__link-arrow' ) : '<span aria-hidden="true">&rarr;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes every icon type; fallback is static HTML. ?><?php endif; ?></a>
				<?php if ( $has_children ) : ?>
					<ul><?php self::render_native_menu_level( $children, $item_id, $depth + 1, $next_visited, $child_arrow_icon ); ?></ul>
				<?php endif; ?>
			</li>
			<?php
	}

	private static function render_links( $block ) {
		$links = isset( $block['links'] ) && is_array( $block['links'] ) ? $block['links'] : array();
		if ( empty( $links ) ) {
			return;
		}
		?>
		<ul class="mrn-mega-menu__links">
			<?php foreach ( $links as $link ) : ?>
				<?php if ( empty( $link['label'] ) || empty( $link['url'] ) ) { continue; } ?>
				<?php $new_tab = isset( $link['target'] ) && '_blank' === $link['target']; ?>
				<li><a href="<?php echo esc_url( $link['url'] ); ?>"<?php echo $new_tab ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( Stack_Integration::resolve_text( $link['label'] ) ); ?><span aria-hidden="true">&rarr;</span></a></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	private static function render_categories( $block ) {
		$ids = isset( $block['category_ids'] ) && is_array( $block['category_ids'] ) ? $block['category_ids'] : array();
		if ( ! taxonomy_exists( 'product_cat' ) || empty( $ids ) ) {
			return;
		}
		?>
		<ul class="mrn-mega-menu__categories">
			<?php foreach ( $ids as $term_id ) : ?>
				<?php
				$term = get_term( absint( $term_id ), 'product_cat' );
				if ( ! $term || is_wp_error( $term ) ) { continue; }
				$url = get_term_link( $term );
				if ( is_wp_error( $url ) ) { continue; }
				?>
				<li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $term->name ); ?><span aria-hidden="true">&rarr;</span></a></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	private static function render_products( $block ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return;
		}
		$limit  = isset( $block['limit'] ) ? max( 1, min( 8, absint( $block['limit'] ) ) ) : 4;
		$source = isset( $block['source'] ) ? $block['source'] : 'featured';
		$args   = array( 'status' => 'publish', 'limit' => $limit );
		if ( 'sale' === $source && function_exists( 'wc_get_product_ids_on_sale' ) ) {
			$args['include'] = wc_get_product_ids_on_sale();
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		} elseif ( 'latest' === $source ) {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		} elseif ( 'manual' === $source ) {
			$args['include'] = isset( $block['product_ids'] ) ? array_map( 'absint', $block['product_ids'] ) : array();
			$args['orderby'] = 'include';
		} else {
			$args['featured'] = true;
		}
		if ( isset( $args['include'] ) && empty( $args['include'] ) ) {
			return;
		}
		$products = wc_get_products( $args );
		if ( empty( $products ) ) {
			return;
		}
		?>
		<div class="mrn-mega-menu__products">
			<?php foreach ( $products as $product ) : ?>
				<a class="mrn-mega-menu__product" href="<?php echo esc_url( $product->get_permalink() ); ?>">
					<span class="mrn-mega-menu__product-image"><?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ) ); ?></span>
					<span class="mrn-mega-menu__product-name"><?php echo esc_html( $product->get_name() ); ?></span>
					<span class="mrn-mega-menu__product-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function render_promo( $block ) {
		$image_id = isset( $block['image_id'] ) ? absint( $block['image_id'] ) : 0;
		$text     = isset( $block['text'] ) ? Stack_Integration::resolve_text( $block['text'] ) : '';
		$url      = isset( $block['url'] ) ? $block['url'] : '';
		$label    = isset( $block['link_label'] ) ? Stack_Integration::resolve_text( $block['link_label'] ) : '';
		if ( $image_id ) {
			?>
			<div class="mrn-mega-menu__promo-media">
				<?php echo wp_kses_post( wp_get_attachment_image( $image_id, 'large', false, array( 'class' => 'mrn-mega-menu__promo-image', 'loading' => 'lazy' ) ) ); ?>
			</div>
			<?php
		}
		if ( $text ) {
			?><p class="mrn-mega-menu__promo-text"><?php echo esc_html( $text ); ?></p><?php
		}
		if ( $url && $label ) {
			?><a class="mrn-mega-menu__promo-link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?><span aria-hidden="true">&rarr;</span></a><?php
		}
	}

	private static function render_icon_markup( $icon, $class_name ) {
		$icon = Plugin::sanitize_icon( $icon );
		if ( ! $icon['value'] ) {
			return '';
		}
		$class_name = sanitize_html_class( $class_name );
		if ( 'media' === $icon['type'] ) {
			return sprintf( '<img class="%1$s" src="%2$s" alt="" aria-hidden="true">', esc_attr( $class_name ), esc_url( $icon['value'] ) );
		}
		$classes = $icon['value'];
		if ( 'dashicons' === $icon['type'] && false === strpos( ' ' . $classes . ' ', ' dashicons ' ) ) {
			$classes = 'dashicons ' . $classes;
		}
		return sprintf( '<span class="%1$s %2$s" aria-hidden="true"></span>', esc_attr( $class_name ), esc_attr( $classes ) );
	}

	private static function assignments() {
		if ( null === self::$assignments ) {
			self::$assignments = Plugin::get_assignments();
		}
		return self::$assignments;
	}
}
