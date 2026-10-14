<?php
defined( 'ABSPATH' ) || exit;

/**
 * Variable-product helpers shared by the admin picker, the storefront and the cart.
 *
 * A picked row is [ 'id' => parent product id, 'variation_id' => int ]:
 *   variation_id > 0                       => the admin pinned one variation
 *   variation_id = 0 on a variable product => the shopper chooses on the page
 *   variation_id = 0 on a simple product   => nothing to choose
 *
 * Browser <-> server tokens are "productId:variationId" ("12:0", "12:34"); a bare "12" is
 * read as "12:0" so data saved by older versions keeps working.
 */
class SMB_Variations {

	public static function max_variations() {
		return max( 1, (int) apply_filters( 'smb_max_variations', 50 ) );
	}

	/** Unique key for a picked row, e.g. "12:0" or "12:34". */
	public static function key( $row ) {
		$id  = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		$vid = isset( $row['variation_id'] ) ? absint( $row['variation_id'] ) : 0;
		return $id . ':' . $vid;
	}

	/** "Red, M" */
	public static function label( $variation ) {
		$label = wc_get_formatted_variation( $variation, true, false );
		return '' !== $label ? $label : '#' . $variation->get_id();
	}

	/**
	 * Name shown for a picked row in the admin (picker results and the picked-products table).
	 */
	public static function admin_label( $product_id, $variation_id = 0 ) {
		$p = wc_get_product( absint( $product_id ) );
		if ( ! $p ) {
			return '';
		}
		$variation_id = absint( $variation_id );
		if ( $variation_id ) {
			$v = wc_get_product( $variation_id );
			return $v && $v->is_type( 'variation' ) ? $p->get_name() . ' – ' . self::label( $v ) : '';
		}
		if ( $p->is_type( 'variable' ) ) {
			/* translators: %s: product name */
			return sprintf( __( '%s — shopper chooses the variation', 'smart-bundles' ), $p->get_name() );
		}
		return $p->get_name();
	}

	/**
	 * Variations a shopper can actually buy: published, purchasable, in stock and with every
	 * attribute fixed (a variation with an "Any ..." attribute cannot be offered from a dropdown).
	 *
	 * @param WC_Product_Variable $parent Parent product.
	 * @return array[] [ variation_id => [ 'id', 'label', 'attrs', 'product' ] ]
	 */
	public static function selectable( $parent ) {
		$out = array();
		foreach ( array_slice( $parent->get_children(), 0, self::max_variations() ) as $vid ) {
			$v = wc_get_product( $vid );
			if ( ! $v || ! $v->is_type( 'variation' ) || 'publish' !== $v->get_status() || ! $v->is_purchasable() || ! $v->is_in_stock() ) {
				continue;
			}
			$attrs = $v->get_variation_attributes();
			if ( in_array( '', $attrs, true ) ) {
				continue;
			}
			$out[ (int) $vid ] = array(
				'id'      => (int) $vid,
				'label'   => self::label( $v ),
				'attrs'   => $attrs,
				'product' => $v,
			);
		}
		return $out;
	}

	/**
	 * Turn a picked row into something that can be shown and added to the cart.
	 *
	 * @param int  $product_id Parent (or simple) product id.
	 * @param int  $pinned     Admin-pinned variation id (0 = none).
	 * @param int  $requested  Variation the shopper chose (0 = use the first available one).
	 * @param bool $need_stock Require simple products to be in stock (variations always must be).
	 * @return array|null product (parent), purchase (what is bought: the simple product or the
	 *                    variation), product_id, variation_id, attrs, pinned, options (the shopper's
	 *                    choices; [] when pinned or simple).
	 */
	public static function resolve( $product_id, $pinned = 0, $requested = 0, $need_stock = true ) {
		$product_id = absint( $product_id );
		$pinned     = absint( $pinned );
		$requested  = absint( $requested );
		$p          = $product_id ? wc_get_product( $product_id ) : false;

		if ( ! $p || 'publish' !== $p->get_status() ) {
			return null;
		}

		if ( $p->is_type( 'simple' ) ) {
			if ( $pinned || $requested || ! $p->is_purchasable() || ( $need_stock && ! $p->is_in_stock() ) ) {
				return null;
			}
			return array(
				'product'      => $p,
				'purchase'     => $p,
				'product_id'   => $product_id,
				'variation_id' => 0,
				'attrs'        => array(),
				'pinned'       => false,
				'options'      => array(),
			);
		}

		if ( ! $p->is_type( 'variable' ) ) {
			return null;
		}

		$options = self::selectable( $p );
		if ( empty( $options ) ) {
			return null;
		}

		if ( $pinned ) {
			if ( ! isset( $options[ $pinned ] ) ) {
				return null;
			}
			$chosen = $pinned;
		} elseif ( $requested && isset( $options[ $requested ] ) ) {
			$chosen = $requested;
		} else {
			// Nothing (valid) requested: the same variation the storefront dropdown shows first.
			$chosen = (int) key( $options );
		}

		return array(
			'product'      => $p,
			'purchase'     => $options[ $chosen ]['product'],
			'product_id'   => $product_id,
			'variation_id' => $chosen,
			'attrs'        => $options[ $chosen ]['attrs'],
			'pinned'       => (bool) $pinned,
			'options'      => $pinned ? array() : array_values( $options ),
		);
	}

	/**
	 * "12,34:56" => [ ['id' => 12, 'vid' => 0], ['id' => 34, 'vid' => 56] ]
	 */
	public static function parse_tokens( $raw ) {
		$out = array();
		foreach ( explode( ',', (string) $raw ) as $part ) {
			$bits = explode( ':', trim( $part ) );
			$id   = absint( $bits[0] );
			if ( ! $id ) {
				continue;
			}
			$out[] = array(
				'id'  => $id,
				'vid' => isset( $bits[1] ) ? absint( $bits[1] ) : 0,
			);
		}
		return $out;
	}

	/** Shopper's variation choice per product: [ product id => variation id ]. */
	public static function requested_map( $raw ) {
		$map = array();
		foreach ( self::parse_tokens( $raw ) as $t ) {
			if ( $t['vid'] && ! isset( $map[ $t['id'] ] ) ) {
				$map[ $t['id'] ] = $t['vid'];
			}
		}
		return $map;
	}

	/**
	 * Never trust the browser: keep only the requested tokens that match a row we would have
	 * offered ($rows = [ ['id','variation_id'], ... ]) and resolve them to cart-ready purchases.
	 *
	 * @return array[] resolve() results, unique per product + variation.
	 */
	public static function resolve_tokens( $raw, $rows ) {
		$out  = array();
		$seen = array();
		foreach ( self::parse_tokens( $raw ) as $t ) {
			foreach ( $rows as $row ) {
				$rid  = (int) $row['id'];
				$rvid = isset( $row['variation_id'] ) ? (int) $row['variation_id'] : 0;
				if ( $rid !== $t['id'] ) {
					continue;
				}
				if ( $rvid && $t['vid'] && $rvid !== $t['vid'] ) {
					continue; // a different pinned variation of the same product
				}
				$r = self::resolve( $rid, $rvid, $t['vid'] );
				if ( $r ) {
					$k = $r['product_id'] . ':' . $r['variation_id'];
					if ( ! isset( $seen[ $k ] ) ) {
						$seen[ $k ] = true;
						$out[]      = $r;
					}
					break;
				}
			}
		}
		return $out;
	}
}
