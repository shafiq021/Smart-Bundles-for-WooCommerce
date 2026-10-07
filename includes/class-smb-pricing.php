<?php
defined( 'ABSPATH' ) || exit;

/**
 * Pack-offer maths + cart behaviour.
 *
 * Rules
 *  - Pack offer: the cart line quantity picks the tier (highest tier qty <= line qty).
 *      percent  => unit price = current price x (1 - value/100)
 *      fixed    => unit price = value / tier qty      ("2 pack for Rs.2,565")
 *  - "Frequently bought together" add-ons are ordinary cart lines at their normal price. No discount.
 *  - Bundle offer: the main product + its picked items are sold as one deal. The combined discount
 *    (percent off, or a fixed total) is spread across every line so each still shows its own price,
 *    and the lines are linked so removing the main product removes the rest of the bundle too.
 */
class SMB_Pricing {

	private static $adding = false;
	private static $bundling = false;

	public static function init() {
		// Add-ons ticked on the product page travel with the normal Add to cart button
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'add_addons' ), 20, 6 );

		// Bundle offer: add the rest of the bundle when the toggle was on
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'add_bundle' ), 21, 6 );

		// Pack / bundle prices
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_prices' ), 9999 );

		// Keep the custom cart data across page loads
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'get_cart_item_from_session' ), 10, 2 );

		// Remove the rest of the bundle when the main line is removed
		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'cart_item_removed' ), 10, 2 );

		// Cart + order display
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 10, 3 );
	}

	/* ---------------------------------------------------------------
	 * Tier helpers
	 * ------------------------------------------------------------- */

	/**
	 * Clean tiers sorted by qty.
	 */
	public static function clean_tiers( $packs ) {
		$out = array();
		foreach ( (array) $packs as $row ) {
			$qty = isset( $row['qty'] ) ? absint( $row['qty'] ) : 0;
			if ( $qty < 1 ) {
				continue;
			}
			$type  = ( isset( $row['type'] ) && 'fixed' === $row['type'] ) ? 'fixed' : 'percent';
			$value = isset( $row['value'] ) ? max( 0, (float) $row['value'] ) : 0;
			if ( 'percent' === $type ) {
				$value = min( 100, $value );
			}
			$out[] = array(
				/* translators: %d: number of items in the pack */
				'label'   => isset( $row['label'] ) && '' !== $row['label'] ? (string) $row['label'] : sprintf( __( '%d pack', 'smart-bundles' ), $qty ),
				'qty'     => $qty,
				'type'    => $type,
				'value'   => $value,
				'badge'   => isset( $row['badge'] ) ? (string) $row['badge'] : '',
				'default' => ! empty( $row['default'] ),
			);
		}
		usort( $out, function ( $a, $b ) {
			return $a['qty'] <=> $b['qty'];
		} );
		return $out;
	}

	/**
	 * The tier that applies to a cart quantity, or null.
	 */
	public static function tier_for_qty( $tiers, $qty ) {
		$match = null;
		foreach ( $tiers as $t ) {
			if ( $t['qty'] <= $qty ) {
				$match = $t;
			}
		}
		return $match;
	}

	/**
	 * Unit price (storage basis, same as the product price meta) for a tier.
	 */
	public static function tier_unit_price( $tier, $current_price ) {
		if ( 'fixed' === $tier['type'] ) {
			return $tier['value'] / max( 1, $tier['qty'] );
		}
		return (float) $current_price * ( 100 - $tier['value'] ) / 100;
	}

	/**
	 * Spread a combined discount across several "current price" figures.
	 *
	 * @param string $type   'percent' or 'fixed'.
	 * @param float  $value  % off, or the fixed total for the whole group.
	 * @param array  $prices [key => current price], raw (storage-basis) prices.
	 * @return array{total: float, sum: float, units: array} units keyed the same as $prices.
	 */
	public static function bundle_totals( $type, $value, $prices ) {
		$sum = array_sum( $prices );
		if ( 'fixed' === $type ) {
			$total = max( 0, (float) $value );
		} else {
			$value = max( 0, min( 100, (float) $value ) );
			$total = $sum * ( 100 - $value ) / 100;
		}

		$count = count( $prices );
		$units = array();
		foreach ( $prices as $k => $cur ) {
			$share    = $sum > 0 ? ( (float) $cur / $sum ) : ( $count ? 1 / $count : 0 );
			$units[ $k ] = 'fixed' === $type ? $total * $share : (float) $cur * ( 100 - $value ) / 100;
		}

		return array( 'total' => $total, 'sum' => $sum, 'units' => $units );
	}

	/* ---------------------------------------------------------------
	 * Add-ons from the product page
	 * ------------------------------------------------------------- */

	public static function add_addons( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		if ( self::$adding || empty( $_REQUEST['smb_addons'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$requested = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_REQUEST['smb_addons'] ) ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( empty( $requested ) ) {
			return;
		}

		// Never trust the browser: only products we would have shown.
		$allowed = SMB_Source::get_item_ids( $product_id );
		$ids     = array_values( array_intersect( array_unique( $requested ), $allowed ) );
		if ( empty( $ids ) ) {
			return;
		}

		self::$adding = true;
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p || ! $p->is_purchasable() || ! $p->is_in_stock() ) {
				continue;
			}
			WC()->cart->add_to_cart( $id, 1 );
		}
		self::$adding = false;

		// Don't let a later add-to-cart in the same request re-add them.
		unset( $_REQUEST['smb_addons'] );
	}

	/* ---------------------------------------------------------------
	 * Bundle offer
	 * ------------------------------------------------------------- */

	public static function add_bundle( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		if ( self::$bundling || empty( $_REQUEST['smb_bundle'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$cfg = SMB_Source::bundle_config( $product_id );
		if ( ! $cfg['on'] || empty( $cfg['items'] ) ) {
			return;
		}

		$main_product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $main_product ) {
			return;
		}

		$prices = array( 'main' => (float) $main_product->get_price() );
		$items  = array();
		foreach ( $cfg['items'] as $iid ) {
			$p = wc_get_product( $iid );
			if ( ! $p || $p->is_type( 'variable' ) || ! $p->is_purchasable() || ! $p->is_in_stock() ) {
				continue;
			}
			$items[ $iid ]  = $p;
			$prices[ $iid ] = (float) $p->get_price();
		}
		if ( empty( $items ) ) {
			return;
		}

		$calc  = self::bundle_totals( $cfg['type'], $cfg['value'], $prices );
		$label = '' !== $cfg['badge'] ? $cfg['badge'] : $cfg['name'];

		self::$bundling = true;

		WC()->cart->cart_contents[ $cart_item_key ]['smb_bundle_price'] = $calc['units']['main'];
		WC()->cart->cart_contents[ $cart_item_key ]['smb_bundle_name']  = $label;

		$children = array();
		foreach ( $items as $iid => $p ) {
			$child_key = WC()->cart->add_to_cart( $iid, 1, 0, array(), array(
				'smb_bundle_price'      => $calc['units'][ $iid ],
				'smb_bundle_name'       => $label,
				'smb_bundle_parent_key' => $cart_item_key,
			) );
			if ( $child_key ) {
				$children[] = $child_key;
				WC()->cart->cart_contents[ $child_key ]['smb_bundle_price']      = $calc['units'][ $iid ];
				WC()->cart->cart_contents[ $child_key ]['smb_bundle_name']       = $label;
				WC()->cart->cart_contents[ $child_key ]['smb_bundle_parent_key'] = $cart_item_key;
			}
		}
		if ( ! empty( $children ) ) {
			WC()->cart->cart_contents[ $cart_item_key ]['smb_bundle_children'] = $children;
		}

		self::$bundling = false;
		unset( $_REQUEST['smb_bundle'] );
	}

	/**
	 * Keep the bundle link across a page load / session restore.
	 */
	public static function get_cart_item_from_session( $cart_item, $values ) {
		foreach ( array( 'smb_bundle_price', 'smb_bundle_name', 'smb_bundle_parent_key', 'smb_bundle_children' ) as $k ) {
			if ( isset( $values[ $k ] ) ) {
				$cart_item[ $k ] = $values[ $k ];
			}
		}
		return $cart_item;
	}

	/**
	 * Removing the main line of a bundle removes the rest of the bundle with it.
	 */
	public static function cart_item_removed( $cart_item_key, $cart ) {
		$removed = isset( $cart->removed_cart_contents[ $cart_item_key ] ) ? $cart->removed_cart_contents[ $cart_item_key ] : null;
		if ( ! $removed || empty( $removed['smb_bundle_children'] ) ) {
			return;
		}
		foreach ( (array) $removed['smb_bundle_children'] as $child_key ) {
			if ( isset( $cart->cart_contents[ $child_key ] ) ) {
				WC()->cart->remove_cart_item( $child_key );
			}
		}
	}

	/* ---------------------------------------------------------------
	 * Prices
	 * ------------------------------------------------------------- */

	public static function apply_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {
			// Bundle offer: the unit price was already computed when it was added to the cart.
			if ( isset( $item['smb_bundle_price'] ) ) {
				$item['data']->set_price( (float) $item['smb_bundle_price'] );
				continue;
			}

			$cfg = SMB_Source::config( $item['product_id'] );
			if ( ! $cfg['packs_on'] ) {
				continue;
			}
			$product = ! empty( $item['variation_id'] ) ? wc_get_product( $item['variation_id'] ) : wc_get_product( $item['product_id'] );
			if ( ! $product ) {
				continue;
			}
			$tier = self::tier_for_qty( self::clean_tiers( $cfg['packs'] ), (int) $item['quantity'] );
			if ( $tier ) {
				$current = (float) $product->get_price(); // fresh object => no compounding
				$item['data']->set_price( self::tier_unit_price( $tier, $current ) );
			}
		}
	}

	/* ---------------------------------------------------------------
	 * Display in cart / order
	 * ------------------------------------------------------------- */

	private static function describe( $item ) {
		$rows = array();

		if ( isset( $item['smb_bundle_price'] ) ) {
			$name   = ! empty( $item['smb_bundle_name'] ) ? $item['smb_bundle_name'] : __( 'Bundle', 'smart-bundles' );
			$rows[] = array(
				'name'  => ! empty( $item['smb_bundle_children'] ) ? __( 'Bundle offer', 'smart-bundles' ) : __( 'Part of bundle', 'smart-bundles' ),
				'value' => $name,
			);
			return $rows;
		}

		if ( ! empty( $item['product_id'] ) ) {
			$cfg = SMB_Source::config( $item['product_id'] );
			if ( $cfg['packs_on'] ) {
				$tier = self::tier_for_qty( self::clean_tiers( $cfg['packs'] ), (int) $item['quantity'] );
				if ( $tier ) {
					$rows[] = array(
						'name'  => __( 'Pack offer', 'smart-bundles' ),
						'value' => $tier['label'],
					);
				}
			}
		}
		return $rows;
	}

	public static function item_data( $data, $item ) {
		foreach ( self::describe( $item ) as $r ) {
			$data[] = array( 'key' => $r['name'], 'value' => $r['value'] );
		}
		return $data;
	}

	public static function order_item_meta( $order_item, $cart_item_key, $values ) {
		foreach ( self::describe( $values ) as $r ) {
			$order_item->add_meta_data( $r['name'], $r['value'] );
		}
	}
}
