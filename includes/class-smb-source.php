<?php
defined( 'ABSPATH' ) || exit;

/**
 * Per-product configuration and finding the "bought together" products.
 */
class SMB_Source {

	public static function init() {
		add_action( 'save_post_product', array( __CLASS__, 'bump_cache' ) );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'bump_cache' ) );
		add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'bump_cache' ) );
	}

	/* ---------------------------------------------------------------
	 * Per-product config
	 * ------------------------------------------------------------- */

	/**
	 * Picked products: [ ['id' => int, 'badge' => string], ... ] in the order the admin arranged them.
	 * Falls back to the old "_smb_manual" list from version 1.0.
	 */
	private static function stored_items( $product_id ) {
		if ( metadata_exists( 'post', $product_id, '_smb_items' ) ) {
			$raw = get_post_meta( $product_id, '_smb_items', true );
		} else {
			$raw = array();
			foreach ( (array) get_post_meta( $product_id, '_smb_manual', true ) as $id ) {
				$raw[] = array( 'id' => $id, 'badge' => '' );
			}
		}

		$items = array();
		$seen  = array();
		foreach ( (array) $raw as $r ) {
			$id = isset( $r['id'] ) ? absint( $r['id'] ) : 0;
			if ( ! $id || $id === $product_id || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$items[]     = array(
				'id'    => $id,
				'badge' => isset( $r['badge'] ) ? (string) $r['badge'] : '',
			);
		}
		return $items;
	}

	/**
	 * Pack offers are ON for every product out of the box: "1 pack" (qty 1, no discount, selected
	 * by default) and "2 pack" (qty 2, no discount). The admin can edit/replace these per product
	 * in the product editor (add a discount, more tiers, badges…) or switch pack offers off entirely.
	 */
	private static function default_packs() {
		return array(
			array( 'label' => '', 'qty' => 1, 'type' => 'percent', 'value' => 0, 'badge' => '', 'default' => true ),
			array( 'label' => '', 'qty' => 2, 'type' => 'percent', 'value' => 0, 'badge' => '', 'default' => false ),
		);
	}

	public static function config( $product_id ) {
		$product_id = absint( $product_id );
		$meta       = function ( $key, $default = '' ) use ( $product_id ) {
			$v = get_post_meta( $product_id, '_smb_' . $key, true );
			return ( '' === $v || null === $v ) ? $default : $v;
		};

		$packs = $meta( 'packs', array() );
		$packs = is_array( $packs ) ? $packs : array();
		if ( empty( $packs ) ) {
			$packs = self::default_packs();
		}

		// On by default (new, never-saved product); an explicit save (checkbox ticked or not)
		// always wins from then on.
		$packs_on_saved = metadata_exists( 'post', $product_id, '_smb_packs_on' )
			? ( 'yes' === $meta( 'packs_on', 'no' ) )
			: true;

		// Pack offers and the bundle offer are mutually exclusive per product: a bundle, once
		// switched on, always takes over from pack offers on that same product.
		$bundle_on = self::bundle_config( $product_id )['on'];

		$g         = SMB_Settings::all();
		$pick_from = $meta( 'pick_from', 'inherit' );
		$limit     = $meta( 'limit', '' );

		return array(
			'packs_on'    => $packs_on_saved && ! $bundle_on && ! empty( $packs ),
			'packs'       => $packs,
			'packs_title' => (string) $meta( 'packs_title', '' ), // '' = use the global heading
			'fbt_mode'    => $meta( 'fbt_mode', 'inherit' ),       // inherit|on|off
			'fbt_title'   => (string) $meta( 'fbt_title', '' ),    // '' = use the global heading
			'items'       => self::stored_items( $product_id ),
			'pick_from'   => 'inherit' === $pick_from ? $g['fbt_pick_from'] : $pick_from,
			'cats'        => array_filter( array_map( 'absint', (array) $meta( 'cats', array() ) ) ),
			'tags'        => array_filter( array_map( 'absint', (array) $meta( 'tags', array() ) ) ),
			'limit'       => '' === $limit ? (int) $g['fbt_limit'] : max( 1, min( 12, (int) $limit ) ),
		);
	}

	/* ---------------------------------------------------------------
	 * Bundle offer (one fixed group of products, sold as one deal)
	 * ------------------------------------------------------------- */

	public static function bundle_config( $product_id ) {
		$product_id = absint( $product_id );
		$meta       = function ( $key, $default = '' ) use ( $product_id ) {
			$v = get_post_meta( $product_id, '_smb_bundle_' . $key, true );
			return ( '' === $v || null === $v ) ? $default : $v;
		};

		$items = array();
		$seen  = array();
		foreach ( (array) get_post_meta( $product_id, '_smb_bundle_items', true ) as $r ) {
			$id = is_array( $r ) ? absint( $r['id'] ?? 0 ) : absint( $r );
			if ( ! $id || $id === $product_id || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$items[]     = $id;
		}

		$g = SMB_Settings::all();

		return array(
			'on'    => 'yes' === $meta( 'on', 'no' ) && ! empty( $items ),
			'items' => $items,
			'title' => (string) $meta( 'title', '' ), // '' = use the global heading
			'name'  => '' !== (string) $meta( 'name', '' ) ? (string) $meta( 'name', '' ) : (string) $g['bundle_name_default'],
			'badge' => (string) $meta( 'badge', '' ),
			'type'  => 'fixed' === $meta( 'type', 'percent' ) ? 'fixed' : 'percent',
			'value' => max( 0, (float) $meta( 'value', 0 ) ),
		);
	}

	/**
	 * Is the "bought together" box active for this product?
	 */
	public static function fbt_enabled( $product_id ) {
		$c = self::config( $product_id );
		if ( 'on' === $c['fbt_mode'] ) {
			return true;
		}
		if ( 'off' === $c['fbt_mode'] ) {
			return false;
		}
		return 'yes' === SMB_Settings::get( 'fbt_global' );
	}

	/* ---------------------------------------------------------------
	 * Finding add-on products
	 * ------------------------------------------------------------- */

	public static function bump_cache() {
		update_option( 'smb_cache_ver', time(), false );
	}

	/**
	 * @return array[] [ ['id' => int, 'badge' => string], ... ] simple, purchasable products only.
	 *                 Hand-picked products come first (in the admin's order, with their badges),
	 *                 then the automatic ones.
	 */
	public static function get_items( $main_id ) {
		$main_id = absint( $main_id );

		if ( ! self::fbt_enabled( $main_id ) ) {
			return array();
		}
		$c = self::config( $main_id );

		$ver = get_option( 'smb_cache_ver', 0 );
		$key = 'smb_items_' . $main_id . '_' . md5( $ver . wp_json_encode( $c ) . SMB_Settings::get( 'fbt_hide_oos' ) );
		$hit = get_transient( $key );
		if ( is_array( $hit ) ) {
			return apply_filters( 'smb_items', $hit, $main_id );
		}

		$candidates = array();

		// 1) hand-picked
		foreach ( $c['items'] as $it ) {
			$candidates[] = $it;
		}

		// 2) top up from the automatic source
		if ( 'manual_only' !== $c['pick_from'] && count( $candidates ) < $c['limit'] ) {
			foreach ( self::query_ids( $main_id, $c, $c['limit'] * 3 ) as $id ) {
				$candidates[] = array( 'id' => $id, 'badge' => '' );
			}
		}

		// 3) clean up: unique, not the main product, simple + purchasable (+ in stock)
		$hide_oos = 'yes' === SMB_Settings::get( 'fbt_hide_oos' );
		$clean    = array();
		foreach ( $candidates as $it ) {
			$id = absint( $it['id'] );
			if ( ! $id || $id === $main_id || isset( $clean[ $id ] ) ) {
				continue;
			}
			$p = wc_get_product( $id );
			if ( ! $p || 'publish' !== $p->get_status() || ! $p->is_type( 'simple' ) || ! $p->is_purchasable() ) {
				continue;
			}
			if ( $hide_oos && ! $p->is_in_stock() ) {
				continue;
			}
			$clean[ $id ] = array( 'id' => $id, 'badge' => (string) $it['badge'] );
			if ( count( $clean ) >= $c['limit'] ) {
				break;
			}
		}
		$items = array_values( $clean );

		set_transient( $key, $items, 6 * HOUR_IN_SECONDS );

		return apply_filters( 'smb_items', $items, $main_id );
	}

	/** Just the IDs. */
	public static function get_item_ids( $main_id ) {
		return array_map( 'absint', wp_list_pluck( self::get_items( $main_id ), 'id' ) );
	}

	/**
	 * Suggestions for the cart page: the bought-together products of everything in the cart,
	 * minus what is already in the cart.
	 *
	 * @return array[] [ ['id' => int, 'badge' => string], ... ]
	 */
	public static function cart_items() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array();
		}
		$in_cart = array();
		$mains   = array();
		foreach ( WC()->cart->get_cart() as $line ) {
			$in_cart[ (int) $line['product_id'] ] = true;
			if ( ! empty( $line['variation_id'] ) ) {
				$in_cart[ (int) $line['variation_id'] ] = true;
			}
			$mains[] = (int) $line['product_id'];
		}

		$limit = max( 1, (int) SMB_Settings::get( 'cart_limit' ) );
		$out   = array();
		foreach ( array_unique( $mains ) as $main ) {
			foreach ( self::get_items( $main ) as $it ) {
				if ( isset( $in_cart[ $it['id'] ] ) || isset( $out[ $it['id'] ] ) ) {
					continue;
				}
				$out[ $it['id'] ] = $it;
				if ( count( $out ) >= $limit ) {
					break 2;
				}
			}
		}
		return array_values( $out );
	}

	private static function query_ids( $main_id, $c, $fetch ) {
		// WooCommerce "related" has its own logic
		if ( 'related' === $c['pick_from'] ) {
			return wc_get_related_products( $main_id, $fetch );
		}

		$args = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => $fetch,
			'post__not_in'           => array( $main_id ),
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'tax_query'              => array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => array( 'simple' ),
				),
			),
		);

		switch ( $c['pick_from'] ) {
			case 'same_cat':
				$terms = wp_get_post_terms( $main_id, 'product_cat', array( 'fields' => 'ids' ) );
				if ( is_wp_error( $terms ) || empty( $terms ) ) {
					return array();
				}
				$args['tax_query'][] = array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $terms );
				break;
			case 'same_tag':
				$terms = wp_get_post_terms( $main_id, 'product_tag', array( 'fields' => 'ids' ) );
				if ( is_wp_error( $terms ) || empty( $terms ) ) {
					return array();
				}
				$args['tax_query'][] = array( 'taxonomy' => 'product_tag', 'field' => 'term_id', 'terms' => $terms );
				break;
			case 'cats':
				if ( empty( $c['cats'] ) ) {
					return array();
				}
				$args['tax_query'][] = array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => array_values( $c['cats'] ) );
				break;
			case 'tags':
				if ( empty( $c['tags'] ) ) {
					return array();
				}
				$args['tax_query'][] = array( 'taxonomy' => 'product_tag', 'field' => 'term_id', 'terms' => array_values( $c['tags'] ) );
				break;
			case 'all':
			default:
				break;
		}

		$q = new WP_Query( apply_filters( 'smb_query_args', $args, $main_id, $c ) );
		return $q->posts;
	}
}
