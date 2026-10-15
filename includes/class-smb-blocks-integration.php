<?php
defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Tells the WooCommerce Cart and Checkout blocks about Smart Bundles, so the block page loads
 * our script. Only loaded once WooCommerce Blocks has defined IntegrationInterface
 * (see SMB_Blocks::loaded()).
 */
class SMB_Blocks_Integration implements IntegrationInterface {

	const HANDLE = 'smart-bundles-blocks';

	public function get_name() {
		return 'smart-bundles';
	}

	public function initialize() {
		wp_register_script(
			self::HANDLE,
			SMB_URL . 'assets/js/blocks-cart.js',
			array( 'wp-data' ), // the cart store ("wc/store/cart") is registered by the Cart block's own scripts
			SMB_VERSION,
			true
		);
	}

	/** Loaded on the front end wherever a Cart or Checkout block is on the page. */
	public function get_script_handles() {
		return array( self::HANDLE );
	}

	/** Nothing to add in the block editor. */
	public function get_editor_script_handles() {
		return array();
	}

	/** Reachable from JS as getSetting( 'smart-bundles_data' ). */
	public function get_script_data() {
		return array(
			'version' => SMB_VERSION,
		);
	}
}
