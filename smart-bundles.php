<?php
/**
 * Plugin Name: Smart Bundles for WooCommerce
 * Description: Pack offers (buy 1 / buy 2 ...), "frequently bought together" add-ons on the product page and cart page, and product badges. Fully styleable with a live preview.
 * Version: 1.2.0
 * Author: Shafiqur Rehman
 * Text Domain: smart-bundles
 * Requires Plugins: woocommerce
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 */

defined( 'ABSPATH' ) || exit;

define( 'SMB_VERSION', '1.2.0' );
define( 'SMB_FILE', __FILE__ );
define( 'SMB_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMB_URL', plugin_dir_url( __FILE__ ) );

/**
 * Boot after WooCommerce is loaded.
 */
add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>Smart Bundles</strong> needs WooCommerce to be active.</p></div>';
		} );
		return;
	}

	require_once SMB_DIR . 'includes/class-smb-style.php';
	require_once SMB_DIR . 'includes/class-smb-settings.php';
	require_once SMB_DIR . 'includes/class-smb-source.php';
	require_once SMB_DIR . 'includes/class-smb-pricing.php';
	require_once SMB_DIR . 'includes/class-smb-frontend.php';
	require_once SMB_DIR . 'includes/class-smb-admin.php';

	SMB_Settings::init();
	SMB_Source::init();
	SMB_Pricing::init();
	SMB_Frontend::init();
	if ( is_admin() ) {
		SMB_Admin::init();
	}
}, 20 );

// Declare HPOS compatibility (the plugin never touches order tables directly).
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SMB_FILE, true );
	}
} );

/**
 * Load a template. A theme can override any file by copying it to:
 * yourtheme/smart-bundles/<file>.php
 */
function smb_get_template( $file, $args = array() ) {
	$located = locate_template( 'smart-bundles/' . $file );
	if ( ! $located ) {
		$located = SMB_DIR . 'templates/' . $file;
	}
	$located = apply_filters( 'smb_template_path', $located, $file, $args );
	if ( file_exists( $located ) ) {
		extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
		include $located;
	}
}
