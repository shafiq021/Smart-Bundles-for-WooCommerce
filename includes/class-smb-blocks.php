<?php
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Cart / Checkout block support.
 *
 * What already works for block carts because it hooks the cart itself, not a page template:
 *  - pack / range / bundle prices (woocommerce_before_calculate_totals runs for the Store API too)
 *  - the bundle links kept in the cart session (woocommerce_get_cart_item_from_session)
 *  - removing a bundle's main line removing the rest (woocommerce_cart_item_removed)
 *  - "Pack offer" / "Bundle offer" notes under the item name (woocommerce_get_item_data, which the
 *    Store API prints as the item's item_data)
 *  - the same notes on the order (woocommerce_checkout_create_order_line_item, which the Store
 *    checkout fires through WC_Checkout::create_order_line_items)
 *
 * What needs this class:
 *  - an integration registered with the Cart and Checkout blocks (loads our script there)
 *  - the "bought together" suggestions, which the classic cart prints through template hooks the
 *    Cart block never fires: they are added after the block and put items into the cart through
 *    the block cart's own data store, so the cart refreshes by itself
 *  - the bundle details on each cart line in the Store API response, for scripts and headless carts
 */
class SMB_Blocks {

	public static function init() {
		// WooCommerce Blocks may already have announced itself by the time this plugin boots.
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			self::loaded();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'loaded' ) );
		}

		add_filter( 'render_block_woocommerce/cart', array( __CLASS__, 'cart_block' ), 20, 1 );
	}

	public static function loaded() {
		if ( ! interface_exists( '\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
			return;
		}
		require_once SMB_DIR . 'includes/class-smb-blocks-integration.php';

		add_action( 'woocommerce_blocks_cart_block_registration', array( __CLASS__, 'register' ) );
		add_action( 'woocommerce_blocks_checkout_block_registration', array( __CLASS__, 'register' ) );

		self::extend_store_api();
	}

	/** @param \Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry $registry */
	public static function register( $registry ) {
		$registry->register( new SMB_Blocks_Integration() );
	}

	/**
	 * The "bought together" box for the cart page, after the Cart block. The classic cart gets it
	 * through the position chosen in the settings; the block has no such hooks, so it always goes
	 * after the block. Same on/off switch (Cart page setting) as the classic cart.
	 */
	public static function cart_block( $content ) {
		return $content . SMB_Frontend::cart_html();
	}

	/* ---------------------------------------------------------------
	 * Store API: what each cart line is, as far as Smart Bundles is concerned
	 * ------------------------------------------------------------- */

	private static function extend_store_api() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data( array(
			'endpoint'        => 'cart-item',
			'namespace'       => 'smart-bundles',
			'data_callback'   => array( __CLASS__, 'cart_item_data' ),
			'schema_callback' => array( __CLASS__, 'cart_item_schema' ),
			'schema_type'     => ARRAY_A,
		) );
	}

	/**
	 * @param array $cart_item A WooCommerce cart item.
	 * @return array bundle: the bundle's label ('' if the line is not part of one);
	 *               role: 'main', 'part' or ''.
	 */
	public static function cart_item_data( $cart_item ) {
		if ( ! isset( $cart_item['smb_bundle_price'] ) ) {
			return array( 'bundle' => '', 'role' => '' );
		}
		return array(
			'bundle' => isset( $cart_item['smb_bundle_name'] ) ? (string) $cart_item['smb_bundle_name'] : '',
			'role'   => ! empty( $cart_item['smb_bundle_children'] ) ? 'main' : 'part',
		);
	}

	public static function cart_item_schema() {
		return array(
			'bundle' => array(
				'description' => __( 'Label of the Smart Bundles bundle this line belongs to.', 'smart-bundles' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'role'   => array(
				'description' => __( 'main: the product the bundle was bought from. part: one of its other products. Empty: not part of a bundle.', 'smart-bundles' ),
				'type'        => 'string',
				'enum'        => array( 'main', 'part', '' ),
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		);
	}
}
