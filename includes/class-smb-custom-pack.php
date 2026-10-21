<?php
defined( 'ABSPATH' ) || exit;

/**
 * Pack Offers: the "Custom" card (shopper picks a quantity and, for variable products, a variation).
 *
 * One PHP function, SMB_Custom_Pack::pricing(), is the single source of truth for the price of one unit.
 * The card prints its result per variation (already tax-aware display prices) and the JavaScript only
 * multiplies by the quantity, so the page and the cart can never disagree. The cart always recomputes
 * the price on the server; a price sent by the browser is never read.
 *
 * Pricing cases (per unit, per selected variation):
 *   A  a Custom card discount is set  -> regular price minus the discount (any sale price is ignored)
 *   B  no discount, product is on sale -> sale price, regular price struck through (WooCommerce's own price)
 *   C  no discount, not on sale        -> normal price only
 *
 * Settings live in their own option (smb_custom_pack) but are printed inside the Pack offers section of the
 * Smart Bundles settings page (action smb_pack_settings_fields) and saved with the same form.
 */
class SMB_Custom_Pack {

	const OPTION = 'smb_custom_pack';
	const FLAG   = 'sb_pack_custom'; // cart item key that marks a line as a Custom pack line

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 11 );

		// Cart
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 10, 4 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'from_session' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_prices' ), 10000 ); // after SMB_Pricing (9999)
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 10, 3 );

		// Admin
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'smb_pack_settings_fields', array( __CLASS__, 'render_settings' ) );
	}

	/* ---------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------- */

	public static function defaults() {
		return array(
			'on'         => 'no',
			'title'      => 'Custom Pack',
			'badge_on'   => 'yes',
			'badge'      => 'Best Value',
			'disc_type'  => 'none', // none | percent | fixed (amount per item)
			'disc_value' => 0,
		);
	}

	public static function settings() {
		static $cache = null;
		if ( null === $cache ) {
			$cache = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		}
		return $cache;
	}

	public static function enabled() {
		return 'yes' === self::settings()['on'];
	}

	public static function heading() {
		$t = trim( (string) self::settings()['title'] );
		return '' !== $t ? $t : __( 'Custom Pack', 'smart-bundles' );
	}

	public static function badge() {
		$s = self::settings();
		return 'yes' === $s['badge_on'] ? trim( (string) $s['badge'] ) : '';
	}

	/**
	 * The active Custom card discount, or null (type None, value 0, or the card is switched off).
	 *
	 * @return array|null [ 'type' => percent|fixed, 'value' => float ]
	 */
	public static function discount() {
		$s = self::settings();
		if ( ! self::enabled() ) {
			return null;
		}
		$type  = $s['disc_type'];
		$value = (float) $s['disc_value'];
		if ( ! in_array( $type, array( 'percent', 'fixed' ), true ) || $value <= 0 ) {
			return null;
		}
		return array( 'type' => $type, 'value' => 'percent' === $type ? min( 100, $value ) : $value );
	}

	public static function register_setting() {
		register_setting( 'smb_group', self::OPTION, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			'default'           => self::defaults(),
		) );
	}

	/** A percentage must be 0-100, a fixed amount 0 or more. A bad value is refused and the previous one kept. */
	public static function sanitize( $in ) {
		$in  = (array) $in;
		$d   = self::defaults();
		$out = array();

		$out['on']       = ( isset( $in['on'] ) && 'yes' === $in['on'] ) ? 'yes' : 'no';
		$out['title']    = isset( $in['title'] ) ? sanitize_text_field( $in['title'] ) : $d['title'];
		$out['badge_on'] = ( isset( $in['badge_on'] ) && 'yes' === $in['badge_on'] ) ? 'yes' : 'no';
		$out['badge']    = isset( $in['badge'] ) ? sanitize_text_field( $in['badge'] ) : $d['badge'];

		$type             = ( isset( $in['disc_type'] ) && in_array( $in['disc_type'], array( 'none', 'percent', 'fixed' ), true ) ) ? $in['disc_type'] : 'none';
		$out['disc_type'] = $type;

		$raw   = isset( $in['disc_value'] ) && ! is_array( $in['disc_value'] ) ? trim( (string) $in['disc_value'] ) : '';
		$valid = function ( $v, $t ) {
			return is_numeric( $v ) && (float) $v >= 0 && ( 'percent' !== $t || (float) $v <= 100 );
		};

		if ( '' === $raw ) {
			$raw = '0';
		}
		if ( 'none' === $type ) {
			$out['disc_value'] = is_numeric( $raw ) && (float) $raw >= 0 ? (float) $raw : 0;
		} elseif ( $valid( $raw, $type ) ) {
			$out['disc_value'] = (float) $raw;
		} else {
			$old  = (array) get_option( self::OPTION, array() );
			$prev = isset( $old['disc_value'] ) ? $old['disc_value'] : 0;
			$out['disc_value'] = $valid( $prev, $type ) ? (float) $prev : 0;

			$seen = false;
			foreach ( get_settings_errors( 'smb_group' ) as $e ) {
				if ( 'smb_custom_disc' === $e['code'] ) {
					$seen = true;
				}
			}
			if ( ! $seen ) {
				add_settings_error(
					'smb_group',
					'smb_custom_disc',
					'percent' === $type
						? __( 'Custom card discount not saved: a percentage must be a number from 0 to 100.', 'smart-bundles' )
						: __( 'Custom card discount not saved: a fixed amount must be a number of 0 or more.', 'smart-bundles' ),
					'error'
				);
			}
		}
		return $out;
	}

	/** Controls inside the "Pack offers" section of the Smart Bundles settings page. */
	public static function render_settings() {
		$s = self::settings();
		$n = self::OPTION;

		$row = function ( $label, $field, $help = '', $class = '', $hidden = false ) {
			echo '<div class="smb-ctl ' . esc_attr( $class ) . '"' . ( $hidden ? ' style="display:none"' : '' ) . '><label>' . esc_html( $label ) . '</label><div class="smb-ctl-field smb-ctl-field--wide">';
			echo $field; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped where built
			echo '</div>' . ( $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) . '</div>';
		};

		$row(
			__( 'Custom card', 'smart-bundles' ),
			'<label class="smb-check-label"><input type="checkbox" name="' . esc_attr( $n ) . '[on]" value="yes"' . checked( $s['on'], 'yes', false ) . '/> ' . esc_html__( 'Enable Custom Card (last card of the pack offers: the shopper picks a quantity and, for variable products, a variation)', 'smart-bundles' ) . '</label>'
		);
		$row(
			__( 'Custom card heading', 'smart-bundles' ),
			'<input type="text" name="' . esc_attr( $n ) . '[title]" value="' . esc_attr( $s['title'] ) . '"/>'
		);
		$row(
			__( 'Custom card badge', 'smart-bundles' ),
			'<label class="smb-check-label"><input type="checkbox" name="' . esc_attr( $n ) . '[badge_on]" value="yes"' . checked( $s['badge_on'], 'yes', false ) . '/> ' . esc_html__( 'Show a badge on the Custom card', 'smart-bundles' ) . '</label>'
		);
		$row(
			__( 'Custom card badge text', 'smart-bundles' ),
			'<input type="text" name="' . esc_attr( $n ) . '[badge]" value="' . esc_attr( $s['badge'] ) . '"/>',
			__( 'Uses the same look as the pack badges (Style > Pack offers > Badge).', 'smart-bundles' )
		);

		$opts  = array(
			'none'    => __( 'None', 'smart-bundles' ),
			'percent' => __( 'Percentage', 'smart-bundles' ),
			'fixed'   => __( 'Fixed amount per item', 'smart-bundles' ),
		);
		$field = '<select name="' . esc_attr( $n ) . '[disc_type]" class="smb-custom-disc-type">';
		foreach ( $opts as $v => $l ) {
			$field .= '<option value="' . esc_attr( $v ) . '"' . selected( $s['disc_type'], $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		$field .= '</select>';
		$row(
			__( 'Custom card discount type', 'smart-bundles' ),
			$field,
			__( 'When a discount is set it overrides sale prices: it is always taken off the regular price.', 'smart-bundles' )
		);
		$row(
			__( 'Custom card discount value', 'smart-bundles' ),
			'<input type="number" step="any" inputmode="decimal" class="small-text smb-custom-disc-val" name="' . esc_attr( $n ) . '[disc_value]" value="' . esc_attr( (float) $s['disc_value'] ) . '"/><span class="smb-custom-disc-warn" style="color:#b32d2e;display:none"></span>',
			__( 'Percentage: 0 to 100. Fixed amount: 0 or more, taken off each item.', 'smart-bundles' ),
			'smb-custom-disc-row',
			'none' === $s['disc_type']
		);
	}

	public static function admin_assets( $hook ) {
		if ( 'woocommerce_page_smart-bundles' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'smb-pack-custom-admin', SMB_URL . 'assets/js/pack-custom-admin.js', array( 'jquery' ), SMB_VERSION, true );
		wp_localize_script( 'smb-pack-custom-admin', 'smbCustomAdmin', array(
			'percent' => __( 'A percentage must be between 0 and 100.', 'smart-bundles' ),
			'fixed'   => __( 'A fixed amount must be 0 or more.', 'smart-bundles' ),
		) );
	}

	/* ---------------------------------------------------------------
	 * Assets (only enqueued when a Pack offer with a Custom card is printed)
	 * ------------------------------------------------------------- */

	public static function register_assets() {
		wp_register_script( 'smart-bundles-pack-custom', SMB_URL . 'assets/js/pack-custom.js', array( 'jquery', 'smart-bundles' ), SMB_VERSION, true );
	}

	private static function enqueue() {
		// Safe before registration too (block themes print content before wp_head): WordPress prints it once registered.
		wp_enqueue_script( 'smart-bundles-pack-custom' );
	}

	/* ---------------------------------------------------------------
	 * Pricing (source of truth)
	 * ------------------------------------------------------------- */

	/**
	 * Price of ONE unit of a simple product or one variation, in storage basis (same basis as the
	 * product price meta; tax display is applied separately).
	 *
	 * @param WC_Product $product Simple product or variation (a fresh object, never a cart-modified one).
	 * @return array [ 'case' => 'A'|'B'|'C', 'regular' => float, 'unit' => float ]
	 */
	public static function pricing( $product ) {
		$active  = (float) $product->get_price();
		$regular = (float) $product->get_regular_price();
		if ( $regular <= 0 ) {
			$regular = $active;
		}

		$d = self::discount();
		if ( $d ) {
			$unit = 'percent' === $d['type'] ? $regular * ( 100 - $d['value'] ) / 100 : max( 0, $regular - $d['value'] );
			return array( 'case' => 'A', 'regular' => $regular, 'unit' => $unit );
		}
		if ( $active < $regular ) {
			return array( 'case' => 'B', 'regular' => $regular, 'unit' => $active );
		}
		return array( 'case' => 'C', 'regular' => $active, 'unit' => $active );
	}

	/** Display prices (tax aware, like the other pack cards) + stock cap for one purchasable product. */
	private static function display( $purchase ) {
		$p   = self::pricing( $purchase );
		$max = $purchase->get_max_purchase_quantity(); // -1 = no limit (not managed, or backorders allowed)
		return array(
			'regular' => (float) wc_get_price_to_display( $purchase, array( 'price' => $p['regular'] ) ),
			'unit'    => (float) wc_get_price_to_display( $purchase, array( 'price' => $p['unit'] ) ),
			'max'     => $max > 0 ? (int) $max : 0, // 0 = no limit
		);
	}

	/**
	 * Every combination the shopper can pick. Out-of-stock ones are included (disabled). A variation that
	 * has an "Any ..." attribute cannot be listed (same rule as SMB_Variations::selectable()).
	 */
	private static function variation_options( $parent ) {
		$out = array();
		foreach ( array_slice( $parent->get_children(), 0, SMB_Variations::max_variations() ) as $vid ) {
			$v = wc_get_product( $vid );
			if ( ! $v || ! $v->is_type( 'variation' ) || 'publish' !== $v->get_status() || ! $v->is_purchasable() ) {
				continue;
			}
			$attrs = $v->get_variation_attributes();
			if ( in_array( '', $attrs, true ) ) {
				continue;
			}
			$off   = ! $v->is_in_stock();
			$label = SMB_Variations::label( $v );
			$out[] = array_merge(
				array(
					'id'       => (int) $vid,
					/* translators: %s: variation name such as "Red, M" */
					'label'    => $off ? sprintf( __( '%s (out of stock)', 'smart-bundles' ), $label ) : $label,
					'attrs'    => $attrs,
					'disabled' => $off,
				),
				self::display( $v )
			);
		}
		return $out;
	}

	/**
	 * Data for the card template, or null when the card must not appear (switched off, product type not
	 * supported, nothing to buy). Called by SMB_Frontend::packs_html() only, so it follows every rule that
	 * decides whether pack offers show at all (pack cards style, no active bundle, ...).
	 */
	public static function card_data( $product ) {
		if ( ! self::enabled() || ! $product ) {
			return null;
		}

		$out = array(
			'title'    => self::heading(),
			'badge'    => self::badge(),
			'variable' => false,
			'options'  => array(),
			'selected' => 0,
			'start'    => array(),
			'demo'     => false,
		);

		if ( $product->is_type( 'variable' ) ) {
			$options = self::variation_options( $product );
			$first   = null;
			foreach ( $options as $o ) {
				if ( empty( $o['disabled'] ) ) {
					$first = $o;
					break;
				}
			}
			if ( ! $first ) {
				return null;
			}
			$out['variable'] = true;
			$out['options']  = $options;
			$out['selected'] = $first['id'];
			$out['start']    = $first;
		} elseif ( $product->is_type( 'simple' ) ) {
			if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				return null;
			}
			$out['start'] = self::display( $product );
		} else {
			return null;
		}

		self::enqueue();
		return apply_filters( 'smb_custom_card', $out, $product );
	}

	/** Static sample for the live preview on the settings page (only while the card is switched on). */
	public static function demo_card() {
		if ( ! self::enabled() ) {
			return null;
		}
		$reg  = 1599.0;
		$d    = self::discount();
		$unit = $d ? ( 'percent' === $d['type'] ? $reg * ( 100 - $d['value'] ) / 100 : max( 0, $reg - $d['value'] ) ) : $reg;

		$options = array(
			array( 'id' => 1, 'label' => 'Rose / 50ml', 'attrs' => array(), 'disabled' => false, 'regular' => $reg, 'unit' => $unit, 'max' => 0 ),
			array( 'id' => 2, 'label' => 'Rose / 100ml', 'attrs' => array(), 'disabled' => false, 'regular' => $reg, 'unit' => $unit, 'max' => 0 ),
		);
		return array(
			'title'    => self::heading(),
			'badge'    => self::badge(),
			'variable' => true,
			'options'  => $options,
			'selected' => 1,
			'start'    => $options[0],
			'demo'     => true,
		);
	}

	/* ---------------------------------------------------------------
	 * Add to cart
	 * ------------------------------------------------------------- */

	/** The product the add-to-cart request is for (normal form post, or WooCommerce AJAX). */
	private static function requested_product_id() {
		if ( isset( $_REQUEST['add-to-cart'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return absint( wp_unslash( $_REQUEST['add-to-cart'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		if ( isset( $_REQUEST['product_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return absint( wp_unslash( $_REQUEST['product_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		return 0;
	}

	/**
	 * Is this add-to-cart call the Custom card's? Only the product the request is FOR counts, so the
	 * bought-together add-ons added in the same request are never flagged. No nonce is used, like
	 * WooCommerce's own add-to-cart, because a nonce printed in a cached page goes stale; every value is
	 * validated on the server instead (validate()) and the price is never read from the browser.
	 */
	private static function wants_custom( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id || empty( $_REQUEST['smb_pack_custom'] ) || ! self::enabled() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}
		if ( $product_id !== self::requested_product_id() ) {
			return false;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || ! ( $product->is_type( 'simple' ) || $product->is_type( 'variable' ) ) ) {
			return false;
		}
		$cfg = SMB_Source::config( $product_id ); // false when packs are off or a bundle took over
		return $cfg['packs_on'] && 'cards' === $cfg['pack_mode'];
	}

	public static function validate( $passed, $product_id, $quantity, $variation_id = 0 ) {
		if ( ! $passed || ! self::wants_custom( $product_id ) ) {
			return $passed;
		}
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		$quantity     = absint( $quantity );

		$parent = wc_get_product( $product_id );
		if ( ! $parent ) {
			return $passed;
		}

		$purchase = $parent;
		if ( $parent->is_type( 'variable' ) ) {
			$v = $variation_id ? wc_get_product( $variation_id ) : false;
			if ( ! $v || ! $v->is_type( 'variation' ) || (int) $v->get_parent_id() !== $product_id ) {
				wc_add_notice( __( 'Please choose a valid option for the Custom pack.', 'smart-bundles' ), 'error' );
				return false;
			}
			$purchase = $v;
		} elseif ( $variation_id ) {
			wc_add_notice( __( 'Please choose a valid option for the Custom pack.', 'smart-bundles' ), 'error' );
			return false;
		}

		if ( $quantity < 1 || ! $purchase->is_purchasable() || ! $purchase->is_in_stock() ) {
			wc_add_notice( __( 'Sorry, that option is not available.', 'smart-bundles' ), 'error' );
			return false;
		}
		$max = $purchase->get_max_purchase_quantity();
		if ( $max > 0 && $quantity > $max ) {
			/* translators: %d: maximum quantity */
			wc_add_notice( sprintf( __( 'Sorry, only %d can be added.', 'smart-bundles' ), $max ), 'error' );
			return false;
		}
		return $passed;
	}

	/** The flag makes the cart keep Custom pack lines apart from normal pack lines of the same product. */
	public static function add_cart_item_data( $data, $product_id ) {
		if ( self::wants_custom( $product_id ) ) {
			$data[ self::FLAG ] = 1;
		}
		return $data;
	}

	public static function from_session( $item, $values ) {
		if ( ! empty( $values[ self::FLAG ] ) ) {
			$item[ self::FLAG ] = 1;
		}
		return $item;
	}

	/**
	 * Case A only: regular price minus the discount. Cases B and C leave the price alone: WooCommerce
	 * already charges the sale or the regular price. The price is rebuilt from a fresh product object on
	 * every calculation, so it never compounds. SMB_Pricing skips Custom lines (no 1 pack / 2 pack tier on them).
	 */
	public static function apply_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item[ self::FLAG ] ) ) {
				continue;
			}
			$product = wc_get_product( ! empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'] );
			if ( ! $product ) {
				continue;
			}
			$p = self::pricing( $product );
			if ( 'A' === $p['case'] ) {
				$item['data']->set_price( $p['unit'] );
			}
		}
	}

	/* ---------------------------------------------------------------
	 * Cart / order display
	 * ------------------------------------------------------------- */

	public static function item_data( $data, $item ) {
		if ( ! empty( $item[ self::FLAG ] ) ) {
			$data[] = array( 'key' => __( 'Pack offer', 'smart-bundles' ), 'value' => self::heading() );
		}
		return $data;
	}

	public static function order_item_meta( $order_item, $cart_item_key, $values ) {
		if ( ! empty( $values[ self::FLAG ] ) ) {
			$order_item->add_meta_data( __( 'Pack offer', 'smart-bundles' ), self::heading() );
		}
	}
}
