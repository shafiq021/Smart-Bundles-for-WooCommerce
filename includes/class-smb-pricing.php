<?php
defined( 'ABSPATH' ) || exit;

/**
 * Pack-offer maths + cart behaviour.
 *
 * Rules
 *  - Pack offer: the cart line quantity picks the tier (highest tier qty <= line qty).
 *      percent  => unit price = current price x (1 - value/100)
 *      fixed    => unit price = value / tier qty      ("2 pack for Rs.2,565")
 *  - Quantity ranges (optional Pack mode): "1-2 = 5% off, 3-5 = 10% off, 6+ = 15% off". The range is
 *    picked by the TOTAL quantity of the product in the cart (all variations together), and every
 *    unit of it is charged at that range's percentage off.
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

	/* ---------------------------------------------------------------
	 * Quantity ranges
	 * ------------------------------------------------------------- */

	/**
	 * Clean ranges sorted by "from". A range is [ 'from' => int >= 1, 'to' => int (0 = "and up"),
	 * 'value' => % off (0-100), 'label' => string ]. Rows that cannot be read are dropped;
	 * validate_ranges() is what tells the admin about them.
	 */
	public static function clean_ranges( $rows ) {
		$out = array();
		foreach ( (array) $rows as $r ) {
			$from = isset( $r['from'] ) ? absint( $r['from'] ) : 0;
			$to   = isset( $r['to'] ) ? absint( $r['to'] ) : 0;
			if ( $from < 1 || ( $to && $to < $from ) ) {
				continue;
			}
			$out[] = array(
				'from'  => $from,
				'to'    => $to,
				'value' => isset( $r['value'] ) ? max( 0, min( 100, (float) $r['value'] ) ) : 0,
				'label' => isset( $r['label'] ) ? (string) $r['label'] : '',
			);
		}
		usort( $out, function ( $a, $b ) {
			return $a['from'] <=> $b['from'];
		} );
		return $out;
	}

	/**
	 * Check raw ranges (as typed in the editor) and say what is wrong, in plain words.
	 * Completely empty rows are ignored. An empty result means the ranges are fine.
	 *
	 * @param array $rows [ ['from' => '', 'to' => '', 'value' => '', 'label' => ''], ... ]
	 * @return string[]
	 */
	public static function validate_ranges( $rows ) {
		$errors = array();
		$ok     = array();
		$n      = 0;
		foreach ( (array) $rows as $r ) {
			$from  = isset( $r['from'] ) ? trim( (string) $r['from'] ) : '';
			$to    = isset( $r['to'] ) ? trim( (string) $r['to'] ) : '';
			$value = isset( $r['value'] ) ? trim( (string) $r['value'] ) : '';
			if ( '' === $from && '' === $to && '' === $value ) {
				continue;
			}
			$n++;
			$bad = false;
			if ( ! ctype_digit( $from ) || (int) $from < 1 ) {
				/* translators: %d: row number */
				$errors[] = sprintf( __( 'Range %d: "From" must be a whole number, 1 or more.', 'smart-bundles' ), $n );
				$bad      = true;
			}
			if ( '' !== $to && ( ! ctype_digit( $to ) || ( ctype_digit( $from ) && (int) $to < (int) $from ) ) ) {
				/* translators: %d: row number */
				$errors[] = sprintf( __( 'Range %d: "To" must be a whole number that is not lower than "From" (leave it empty for "and up").', 'smart-bundles' ), $n );
				$bad      = true;
			}
			if ( ! is_numeric( $value ) || (float) $value < 0 || (float) $value > 100 ) {
				/* translators: %d: row number */
				$errors[] = sprintf( __( 'Range %d: the discount must be a number from 0 to 100.', 'smart-bundles' ), $n );
				$bad      = true;
			}
			if ( ! $bad ) {
				$ok[] = array( 'from' => (int) $from, 'to' => '' === $to ? 0 : (int) $to );
			}
		}

		usort( $ok, function ( $a, $b ) {
			return $a['from'] <=> $b['from'];
		} );
		for ( $i = 1; $i < count( $ok ); $i++ ) {
			$prev = $ok[ $i - 1 ];
			$cur  = $ok[ $i ];
			if ( 0 === $prev['to'] || $cur['from'] <= $prev['to'] ) {
				$errors[] = sprintf(
					/* translators: 1: first range such as 1–3, 2: second range such as 3–5 */
					__( 'Ranges %1$s and %2$s overlap.', 'smart-bundles' ),
					self::range_text( $prev['from'], $prev['to'] ),
					self::range_text( $cur['from'], $cur['to'] )
				);
			}
		}
		return $errors;
	}

	/** "3–5", "6+", or "4" for a single quantity. */
	public static function range_text( $from, $to ) {
		if ( ! $to ) {
			return $from . '+';
		}
		return $from === $to ? (string) $from : $from . '–' . $to;
	}

	/** The range that covers a quantity, or null (a gap between ranges means no discount). */
	public static function range_for_qty( $ranges, $qty ) {
		foreach ( $ranges as $r ) {
			if ( $qty >= $r['from'] && ( ! $r['to'] || $qty <= $r['to'] ) ) {
				return $r;
			}
		}
		return null;
	}

	/** Unit price after a range's percentage off. */
	public static function range_unit_price( $range, $current_price ) {
		return (float) $current_price * ( 100 - $range['value'] ) / 100;
	}

	/**
	 * Total quantity of each product in the cart (all variations together). Bundle lines are left
	 * out: they are priced by the bundle, not by quantity.
	 *
	 * @return array [ product id => quantity ]
	 */
	public static function product_quantities( $cart ) {
		$totals = array();
		foreach ( $cart->get_cart() as $item ) {
			if ( isset( $item['smb_bundle_price'] ) || ! empty( $item['sb_pack_custom'] ) ) { // bundle and Custom pack lines are not priced by quantity
				continue;
			}
			$pid            = (int) $item['product_id'];
			$totals[ $pid ] = ( isset( $totals[ $pid ] ) ? $totals[ $pid ] : 0 ) + (int) $item['quantity'];
		}
		return $totals;
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

		$raw = sanitize_text_field( wp_unslash( $_REQUEST['smb_addons'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' === $raw ) {
			return;
		}

		// Never trust the browser: only products we would have shown. Tokens are "id:variation_id".
		$picks = SMB_Variations::resolve_tokens( $raw, SMB_Source::get_items( $product_id ) );
		if ( empty( $picks ) ) {
			return;
		}

		self::$adding = true;
		foreach ( $picks as $r ) {
			WC()->cart->add_to_cart( $r['product_id'], 1, $r['variation_id'], $r['attrs'] );
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

		// Variations the shopper picked on the page ("id:variation_id" tokens). Pinned variations
		// come from the bundle's own config and cannot be overridden from the browser.
		$chosen = SMB_Variations::requested_map( isset( $_REQUEST['smb_bundle_pick'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['smb_bundle_pick'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification

		$prices = array( 'main' => (float) $main_product->get_price() );
		$items  = array();
		foreach ( $cfg['rows'] as $row ) {
			$r = SMB_Variations::resolve( $row['id'], $row['variation_id'], isset( $chosen[ $row['id'] ] ) ? $chosen[ $row['id'] ] : 0 );
			if ( ! $r ) {
				continue;
			}
			$k            = $r['product_id'] . ':' . $r['variation_id'];
			$items[ $k ]  = $r;
			$prices[ $k ] = (float) $r['purchase']->get_price(); // the variation's own price, not the parent's
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
		foreach ( $items as $k => $r ) {
			$child_key = WC()->cart->add_to_cart( $r['product_id'], 1, $r['variation_id'], $r['attrs'], array(
				'smb_bundle_price'      => $calc['units'][ $k ],
				'smb_bundle_name'       => $label,
				'smb_bundle_parent_key' => $cart_item_key,
			) );
			if ( $child_key ) {
				$children[] = $child_key;
				WC()->cart->cart_contents[ $child_key ]['smb_bundle_price']      = $calc['units'][ $k ];
				WC()->cart->cart_contents[ $child_key ]['smb_bundle_name']       = $label;
				WC()->cart->cart_contents[ $child_key ]['smb_bundle_parent_key'] = $cart_item_key;
			}
		}
		if ( ! empty( $children ) ) {
			WC()->cart->cart_contents[ $cart_item_key ]['smb_bundle_children'] = $children;
		}

		self::$bundling = false;
		unset( $_REQUEST['smb_bundle'], $_REQUEST['smb_bundle_pick'] );
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

		$totals = self::product_quantities( $cart );

		foreach ( $cart->get_cart() as $item ) {
			// Bundle offer: the unit price was already computed when it was added to the cart.
			if ( isset( $item['smb_bundle_price'] ) ) {
				$item['data']->set_price( (float) $item['smb_bundle_price'] );
				continue;
			}

			// Custom pack line (pack offers, Custom card): priced by SMB_Custom_Pack, never by a 1 pack / 2 pack tier.
			if ( ! empty( $item['sb_pack_custom'] ) ) {
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

			// Quantity ranges: always set the price, so a quantity that drops out of a range goes back to
			// the normal price instead of keeping the last discount.
			if ( 'ranges' === $cfg['pack_mode'] ) {
				$current = (float) $product->get_price();
				$qty     = isset( $totals[ (int) $item['product_id'] ] ) ? $totals[ (int) $item['product_id'] ] : (int) $item['quantity'];
				$range   = self::range_for_qty( $cfg['ranges'], $qty );
				$item['data']->set_price( $range ? self::range_unit_price( $range, $current ) : $current );
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

		if ( ! empty( $item['sb_pack_custom'] ) ) {
			return $rows; // SMB_Custom_Pack prints the label of its own lines
		}

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
			if ( $cfg['packs_on'] && 'ranges' === $cfg['pack_mode'] ) {
				$totals = ( function_exists( 'WC' ) && WC()->cart ) ? self::product_quantities( WC()->cart ) : array();
				$qty    = isset( $totals[ (int) $item['product_id'] ] ) ? $totals[ (int) $item['product_id'] ] : (int) $item['quantity'];
				$range  = self::range_for_qty( $cfg['ranges'], $qty );
				if ( $range && $range['value'] > 0 ) {
					$rows[] = array(
						'name'  => __( 'Quantity discount', 'smart-bundles' ),
						/* translators: 1: quantity range such as 3–5, 2: percent off */
						'value' => sprintf( __( '%1$s (%2$s%% off)', 'smart-bundles' ), self::range_text( $range['from'], $range['to'] ), SMB_Frontend::pct_text( $range['value'] ) ),
					);
				}
			} elseif ( $cfg['packs_on'] ) {
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
