<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gutenberg blocks: smart-bundles/pack, smart-bundles/bundle, smart-bundles/fbt.
 *
 * Each block has one attribute, productId (0 = the product of the page it sits on) and is rendered
 * on the server with the same functions as the shortcodes, so the block, the shortcode and the
 * automatic positions always print identical markup. In the editor the block fetches its product
 * list from the REST API (see SMB_REST) and its preview through ServerSideRender.
 *
 * The JavaScript is built with @wordpress/scripts from /src into /build (the build is committed).
 */
class SMB_Gutenberg {

	private static $types = array(
		'pack'   => array( 'rest' => 'packs', 'render' => 'packs_html' ),
		'bundle' => array( 'rest' => 'bundles', 'render' => 'bundle_html' ),
		'fbt'    => array( 'rest' => 'fbt', 'render' => 'fbt_html' ),
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_assets', array( __CLASS__, 'editor_styles' ) );
	}

	public static function register() {
		// The build uses the automatic JSX runtime, which WordPress ships from 6.6. Older sites keep
		// the shortcodes and the automatic positions; the blocks just do not appear.
		if ( version_compare( get_bloginfo( 'version' ), '6.6', '<' ) ) {
			return;
		}
		foreach ( array_keys( self::$types ) as $slug ) {
			$dir = SMB_DIR . 'build/' . $slug;
			if ( ! file_exists( $dir . '/block.json' ) ) {
				continue; // build missing: the shortcodes still work
			}
			register_block_type( $dir, array(
				'render_callback' => array( __CLASS__, 'render_' . $slug ),
			) );
			wp_set_script_translations( 'smart-bundles-' . $slug . '-editor-script', 'smart-bundles' );
		}
	}

	/** The offers are styled by the same CSS as on the shop, plus the style settings page. */
	public static function editor_styles() {
		if ( ! is_admin() ) {
			return;
		}
		wp_enqueue_style( 'smart-bundles-editor', SMB_URL . 'assets/css/frontend.css', array(), SMB_VERSION );
		$css = SMB_Style::css( SMB_Settings::all() );
		wp_add_inline_style( 'smart-bundles-editor', $css . "\n.wp-block-smart-bundles-pack .smb-wrap,.wp-block-smart-bundles-bundle .smb-wrap,.wp-block-smart-bundles-fbt .smb-wrap{pointer-events:none}" );
	}

	public static function render_pack( $atts, $content = '', $block = null ) {
		return self::render( 'pack', $atts, $block );
	}

	public static function render_bundle( $atts, $content = '', $block = null ) {
		return self::render( 'bundle', $atts, $block );
	}

	public static function render_fbt( $atts, $content = '', $block = null ) {
		return self::render( 'fbt', $atts, $block );
	}

	private static function render( $slug, $atts, $block ) {
		$id = isset( $atts['productId'] ) ? absint( $atts['productId'] ) : 0;
		if ( ! $id ) {
			$id = ( $block && ! empty( $block->context['postId'] ) ) ? absint( $block->context['postId'] ) : absint( get_the_ID() );
		}
		$product = $id ? wc_get_product( $id ) : false;
		if ( ! $product ) {
			return '';
		}
		$html = call_user_func( array( 'SMB_Frontend', self::$types[ $slug ]['render'] ), $product, true );

		if ( '' === $html && self::is_editor_request() ) {
			return '<p class="smb-block-empty">' . esc_html__( 'No offer of this kind is active for this product. Set it up in the product editor under Smart Bundles.', 'smart-bundles' ) . '</p>';
		}
		return $html;
	}

	private static function is_editor_request() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}
}
