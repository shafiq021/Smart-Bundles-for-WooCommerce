<?php
defined( 'ABSPATH' ) || exit;

/**
 * Storefront output: pack cards, bought-together list (product + cart page), badges, assets.
 */
class SMB_Frontend {

	/** @var array rendered markers so one block is never printed twice */
	private static $printed = array();

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );

		add_shortcode( 'smb_packs', array( __CLASS__, 'shortcode_packs' ) );
		add_shortcode( 'smb_bundle', array( __CLASS__, 'shortcode_bundle' ) );
		add_shortcode( 'smb_offer', array( __CLASS__, 'shortcode_offer' ) );
		add_shortcode( 'smb_cart_bundle', array( __CLASS__, 'shortcode_cart' ) );

		// Auto placement
		add_action( 'wp', array( __CLASS__, 'register_positions' ) );

		// Add suggested products from the cart page (returns just the refreshed cart region, no page reload)
		add_action( 'wc_ajax_smb_cart_add', array( __CLASS__, 'ajax_cart_add' ) );
	}

	/* ---------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------- */

	public static function assets() {
		$s = SMB_Settings::all();

		wp_register_style( 'smart-bundles', SMB_URL . 'assets/css/frontend.css', array(), SMB_VERSION );
		wp_register_script( 'smart-bundles', SMB_URL . 'assets/js/frontend.js', array( 'jquery' ), SMB_VERSION, true );

		$css = SMB_Style::css( $s );
		if ( ! empty( $s['custom_css'] ) ) {
			$css .= "\n" . $s['custom_css'];
		}
		wp_add_inline_style( 'smart-bundles', $css );

		$font = SMB_Style::font_url( $s );
		if ( $font ) {
			wp_register_style( 'smart-bundles-fonts', $font, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
		}

		wp_localize_script( 'smart-bundles', 'smbData', array(
			'cartAddUrl' => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'smb_cart_add' ) : '',
			'nonce'      => wp_create_nonce( 'smb_cart_add' ),
			'currency'   => array(
				'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'decimals' => wc_get_price_decimals(),
				'decimal'  => wc_get_price_decimal_separator(),
				'thousand' => wc_get_price_thousand_separator(),
				'format'   => get_woocommerce_price_format(),
			),
			'i18n'       => array(
				'selected' => __( 'selected', 'smart-bundles' ),
				'save'     => __( 'Save', 'smart-bundles' ),
				'youSave'  => __( 'You save', 'smart-bundles' ),
				/* translators: %s: amount saved */
				'bundleSave' => __( 'Save %s!', 'smart-bundles' ),
				'error'    => __( 'Sorry, those products could not be added. Please try again.', 'smart-bundles' ),
			),
		) );

		// Cart page: load the assets up front so the block is styled the moment it prints.
		if ( function_exists( 'is_cart' ) && is_cart() && 'yes' === SMB_Settings::get( 'cart_on' ) ) {
			self::enqueue();
		}
	}

	/**
	 * Block themes render the page content BEFORE wp_head, i.e. before assets() has registered anything.
	 * WordPress keeps an enqueued-but-not-yet-registered handle in the queue and prints it once it is
	 * registered, so these calls must be unconditional (an unregistered handle is simply skipped).
	 */
	private static function enqueue() {
		self::enqueue_style();
		wp_enqueue_script( 'smart-bundles' );
	}

	private static function enqueue_style() {
		wp_enqueue_style( 'smart-bundles' );
		wp_enqueue_style( 'smart-bundles-fonts' );
	}

	/* ---------------------------------------------------------------
	 * Placement
	 * ------------------------------------------------------------- */

	private static function hook_map() {
		return array(
			'before_button' => array( 'woocommerce_before_add_to_cart_button', 10 ),
			'after_button'  => array( 'woocommerce_after_add_to_cart_button', 10 ),
			'before_form'   => array( 'woocommerce_before_add_to_cart_form', 10 ),
			'after_form'    => array( 'woocommerce_after_add_to_cart_form', 10 ),
			'after_price'   => array( 'woocommerce_single_product_summary', 11 ),
			'after_excerpt' => array( 'woocommerce_single_product_summary', 21 ),
		);
	}

	private static function cart_hook_map() {
		return array(
			'before_cart'   => array( 'woocommerce_before_cart', 10 ),
			'before_table'  => array( 'woocommerce_before_cart_table', 10 ),
			'after_table'   => array( 'woocommerce_after_cart_table', 10 ),
			'before_totals' => array( 'woocommerce_before_cart_collaterals', 10 ),
			'after_cart'    => array( 'woocommerce_after_cart', 10 ),
		);
	}

	public static function register_positions() {
		self::register_cart_position();

		if ( ! is_singular( 'product' ) ) {
			return;
		}
		$map = self::hook_map();
		foreach ( array( 'packs', 'fbt', 'bundle' ) as $block ) {
			$pos = SMB_Settings::get( $block . '_position' );
			if ( isset( $map[ $pos ] ) ) {
				add_action( $map[ $pos ][0], array( __CLASS__, 'auto_' . $block ), $map[ $pos ][1] );
			}
		}
	}

	/**
	 * Hook the cart block in. Split out so the AJAX cart-refresh handler can call it directly —
	 * is_cart() is not reliable inside an admin-ajax request, which has no main query.
	 */
	private static function register_cart_position() {
		if ( 'yes' !== SMB_Settings::get( 'cart_on' ) ) {
			return;
		}
		$map = self::cart_hook_map();
		$pos = SMB_Settings::get( 'cart_position' );
		if ( isset( $map[ $pos ] ) ) {
			add_action( $map[ $pos ][0], array( __CLASS__, 'auto_cart' ), $map[ $pos ][1] );
		}
	}

	public static function auto_packs() {
		echo self::packs_html( wc_get_product( get_the_ID() ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function auto_fbt() {
		echo self::fbt_html( wc_get_product( get_the_ID() ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function auto_bundle() {
		echo self::bundle_html( wc_get_product( get_the_ID() ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function auto_cart() {
		echo self::cart_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function shortcode_packs( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts );
		$id   = $atts['id'] ? absint( $atts['id'] ) : get_the_ID();
		return self::packs_html( wc_get_product( $id ), true );
	}

	public static function shortcode_bundle( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts );
		$id   = $atts['id'] ? absint( $atts['id'] ) : get_the_ID();
		return self::fbt_html( wc_get_product( $id ), true );
	}

	public static function shortcode_offer( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts );
		$id   = $atts['id'] ? absint( $atts['id'] ) : get_the_ID();
		return self::bundle_html( wc_get_product( $id ), true );
	}

	public static function shortcode_cart() {
		return self::cart_html( true );
	}

	/* ---------------------------------------------------------------
	 * Price helpers (display prices, tax aware)
	 * ------------------------------------------------------------- */

	/**
	 * @return array [current, regular] display prices. Variable products use the lowest.
	 */
	public static function display_prices( $product ) {
		if ( $product->is_type( 'variable' ) ) {
			$cur = $product->get_variation_price( 'min', false );
			$reg = $product->get_variation_regular_price( 'min', false );
		} else {
			$cur = $product->get_price();
			$reg = $product->get_regular_price();
		}
		$reg = '' === $reg ? $cur : $reg;

		return array(
			(float) wc_get_price_to_display( $product, array( 'price' => $cur ) ),
			(float) wc_get_price_to_display( $product, array( 'price' => $reg ) ),
		);
	}

	public static function pct_text( $n ) {
		return rtrim( rtrim( number_format( (float) $n, 2, '.', '' ), '0' ), '.' );
	}

	/* ---------------------------------------------------------------
	 * Pack offers
	 * ------------------------------------------------------------- */

	public static function packs_html( $product, $force = false ) {
		if ( ! $product || ! ( $product->is_type( 'simple' ) || $product->is_type( 'variable' ) ) ) {
			return '';
		}
		if ( ! $force && ! is_singular( 'product' ) ) {
			return '';
		}
		$id  = $product->get_id();
		$cfg = SMB_Source::config( $id );
		if ( ! $cfg['packs_on'] ) {
			return '';
		}
		if ( 'ranges' === $cfg['pack_mode'] ) {
			return self::ranges_html( $product, $cfg );
		}
		$guard = 'packs-' . $id;
		if ( isset( self::$printed[ $guard ] ) ) {
			return '';
		}

		list( $current, $regular ) = self::display_prices( $product );

		$tiers = array();
		foreach ( SMB_Pricing::clean_tiers( $cfg['packs'] ) as $t ) {
			if ( 'fixed' === $t['type'] ) {
				$total = (float) wc_get_price_to_display( $product, array( 'price' => $t['value'] / $t['qty'] ) ) * $t['qty'];
			} else {
				$total = $current * $t['qty'] * ( 100 - $t['value'] ) / 100;
			}
			$reg_total = $regular * $t['qty'];
			$save      = max( 0, $reg_total - $total );
			$save_pct  = $reg_total > 0 ? $save / $reg_total * 100 : 0;

			$t['total']         = $total;
			$t['reg_total']     = $reg_total;
			$t['save']          = $save;
			$t['save_pct']      = $save_pct;
			$t['save_pct_text'] = self::pct_text( $save_pct );
			$t['fixed_total']   = 'fixed' === $t['type'] ? $total : 0;
			$tiers[]            = $t;
		}
		if ( empty( $tiers ) ) {
			return '';
		}
		self::$printed[ $guard ] = true;

		self::enqueue();

		$title = '' !== $cfg['packs_title'] ? $cfg['packs_title'] : SMB_Settings::get( 'packs_title' );

		ob_start();
		smb_get_template( 'packs.php', array(
			'product'  => $product,
			'tiers'    => $tiers,
			'title'    => $title,
			'is_var'   => $product->is_type( 'variable' ),
			'hide_qty' => 'yes' === SMB_Settings::get( 'hide_qty' ),
			'layout'   => SMB_Source::layout( $id ),
		) );
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------
	 * Pack offers, quantity-range style ("1-2 = 5% off, 3-5 = 10% off, 6+ = 15% off")
	 * ------------------------------------------------------------- */

	private static function ranges_html( $product, $cfg ) {
		$id    = $product->get_id();
		$guard = 'packs-' . $id;
		if ( isset( self::$printed[ $guard ] ) ) {
			return '';
		}

		list( $current, $regular ) = self::display_prices( $product );

		$ranges = array();
		foreach ( $cfg['ranges'] as $r ) {
			$r['text']     = '' !== $r['label'] ? $r['label'] : SMB_Pricing::range_text( $r['from'], $r['to'] );
			$r['unit']     = SMB_Pricing::range_unit_price( $r, $current );
			$r['regular']  = $regular;
			$r['pct_text'] = self::pct_text( $r['value'] );
			$ranges[]      = $r;
		}
		if ( empty( $ranges ) ) {
			return '';
		}
		self::$printed[ $guard ] = true;

		self::enqueue();

		$title = '' !== $cfg['packs_title'] ? $cfg['packs_title'] : SMB_Settings::get( 'packs_title' );

		ob_start();
		smb_get_template( 'ranges.php', array(
			'product' => $product,
			'ranges'  => $ranges,
			'title'   => $title,
			'is_var'  => $product->is_type( 'variable' ),
			'current' => $current,
			'regular' => $regular,
			'layout'  => SMB_Source::layout( $id ),
		) );
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------
	 * Bought together
	 * ------------------------------------------------------------- */

	/**
	 * Turn [ ['id','variation_id','badge'], ... ] into the rows the template needs. No discounts:
	 * normal prices. A variable product is either pinned to one variation (shown as a normal row) or
	 * carries 'options' so the shopper can choose (the first option is pre-selected, and its price
	 * is the one shown).
	 */
	private static function build_items( $list, $preselect, $link ) {
		$items = array();
		foreach ( $list as $row ) {
			$vid = isset( $row['variation_id'] ) ? absint( $row['variation_id'] ) : 0;
			$r   = SMB_Variations::resolve( $row['id'], $vid, 0, false );
			if ( ! $r ) {
				continue;
			}
			$p = $r['product'];
			list( $current, $regular ) = self::display_prices( $r['purchase'] );

			$options = array();
			foreach ( $r['options'] as $o ) {
				list( $o_cur, $o_reg ) = self::display_prices( $o['product'] );
				$options[]             = array(
					'id'      => $o['id'],
					'label'   => $o['label'],
					'price'   => $o_cur,
					'regular' => $o_reg,
				);
			}

			$items[] = array(
				'id'           => (int) $row['id'],
				'variation_id' => $r['variation_id'],
				'options'      => $options,
				'name'         => $r['pinned'] ? SMB_Variations::admin_label( $row['id'], $r['variation_id'] ) : $p->get_name(),
				'url'          => $link ? get_permalink( $row['id'] ) : '',
				'image'        => $r['purchase']->get_image( 'woocommerce_gallery_thumbnail', array( 'loading' => 'lazy' ) ),
				'price'        => $current,
				'regular'      => $regular,
				// The badge typed on the product's own box wins; otherwise the global badge for that product.
				'badge'        => isset( $row['badge'] ) && '' !== (string) $row['badge'] ? (string) $row['badge'] : SMB_Settings::badge_for( $row['id'] ),
				'checked'      => $preselect,
			);
		}
		return $items;
	}

	public static function fbt_html( $product, $force = false ) {
		if ( ! $product || ! ( $product->is_type( 'simple' ) || $product->is_type( 'variable' ) ) ) {
			return '';
		}
		if ( ! $force && ! is_singular( 'product' ) ) {
			return '';
		}
		$id    = $product->get_id();
		$guard = 'fbt-' . $id;
		if ( isset( self::$printed[ $guard ] ) ) {
			return '';
		}

		$list = SMB_Source::get_items( $id );
		if ( empty( $list ) ) {
			return '';
		}

		$items = self::build_items(
			$list,
			'yes' === SMB_Settings::get( 'fbt_preselect' ),
			'yes' === SMB_Settings::get( 'fbt_link_names' )
		);
		if ( empty( $items ) ) {
			return '';
		}
		self::$printed[ $guard ] = true;

		self::enqueue();

		$cfg   = SMB_Source::config( $id );
		$title = '' !== $cfg['fbt_title'] ? $cfg['fbt_title'] : SMB_Settings::get( 'fbt_title' );

		ob_start();
		smb_get_template( 'fbt.php', array(
			'product'      => $product,
			'items'        => $items,
			'title'        => $title,
			'context'      => 'product',
			'show_summary' => 'yes' === SMB_Settings::get( 'fbt_show_summary' ),
			'layout'       => SMB_Source::layout( $id ),
		) );
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------
	 * Bundle offer (the product itself + a fixed set of others, one combined price)
	 * ------------------------------------------------------------- */

	public static function bundle_html( $product, $force = false ) {
		if ( ! $product || ! ( $product->is_type( 'simple' ) || $product->is_type( 'variable' ) ) ) {
			return '';
		}
		if ( ! $force && ! is_singular( 'product' ) ) {
			return '';
		}
		$id  = $product->get_id();
		$cfg = SMB_Source::bundle_config( $id );
		if ( ! $cfg['on'] ) {
			return '';
		}
		$guard = 'bundle-' . $id;
		if ( isset( self::$printed[ $guard ] ) ) {
			return '';
		}

		list( $main_cur, $main_reg ) = self::display_prices( $product );
		$img    = array( 'loading' => 'lazy' );
		$rows   = array( array( 'key' => 'main', 'id' => $id, 'variation_id' => 0, 'options' => array(), 'name' => $product->get_name(), 'image' => $product->get_image( 'woocommerce_gallery_thumbnail', $img ), 'current' => $main_cur, 'regular' => $main_reg ) );
		$prices = array( 'main' => $main_cur );

		foreach ( $cfg['rows'] as $row ) {
			$r = SMB_Variations::resolve( $row['id'], $row['variation_id'] );
			if ( ! $r ) {
				continue;
			}
			list( $cur, $reg ) = self::display_prices( $r['purchase'] );

			$options = array();
			foreach ( $r['options'] as $o ) {
				list( $o_cur, $o_reg ) = self::display_prices( $o['product'] );
				$options[]             = array( 'id' => $o['id'], 'label' => $o['label'], 'current' => $o_cur, 'regular' => $o_reg );
			}

			$k             = $r['product_id'] . ':' . $r['variation_id'];
			$rows[]        = array(
				'key'          => $k,
				'id'           => $r['product_id'],
				'variation_id' => $r['variation_id'],
				'options'      => $options,
				'name'         => $r['pinned'] ? SMB_Variations::admin_label( $r['product_id'], $r['variation_id'] ) : $r['product']->get_name(),
				'image'        => $r['purchase']->get_image( 'woocommerce_gallery_thumbnail', $img ),
				'current'      => $cur,
				'regular'      => $reg,
			);
			$prices[ $k ]  = $cur;
		}
		if ( count( $rows ) < 2 ) {
			return ''; // need at least one other product to call it a bundle
		}
		self::$printed[ $guard ] = true;

		$calc      = SMB_Pricing::bundle_totals( $cfg['type'], $cfg['value'], $prices );
		$reg_total = array_sum( wp_list_pluck( $rows, 'regular' ) );
		$save      = max( 0, $reg_total - $calc['total'] );

		foreach ( $rows as &$r ) {
			$r['unit'] = $calc['units'][ $r['key'] ];
		}
		unset( $r );

		self::enqueue();

		$title = '' !== $cfg['title'] ? $cfg['title'] : SMB_Settings::get( 'bundle_title' );

		ob_start();
		smb_get_template( 'bundle.php', array(
			'title'         => $title,
			'name'          => $cfg['name'],
			'badge'         => $cfg['badge'],
			'rows'          => $rows,
			'total'         => $calc['total'],
			'reg_total'     => $reg_total,
			'save'          => $save,
			'save_pct_text' => self::pct_text( $reg_total > 0 ? $save / $reg_total * 100 : 0 ),
			'type'          => $cfg['type'],
			'value'         => $cfg['value'],
			'layout'        => SMB_Source::layout( $id ),
		) );
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------
	 * Bought together on the cart page
	 * ------------------------------------------------------------- */

	public static function cart_html( $force = false ) {
		if ( ! $force && 'yes' !== SMB_Settings::get( 'cart_on' ) ) {
			return '';
		}
		if ( isset( self::$printed['cart'] ) ) {
			return '';
		}

		$items = self::build_items(
			SMB_Source::cart_items(),
			'yes' === SMB_Settings::get( 'cart_preselect' ),
			'yes' === SMB_Settings::get( 'cart_link_names' )
		);
		if ( empty( $items ) ) {
			return '';
		}
		self::$printed['cart'] = true;

		self::enqueue();

		ob_start();
		smb_get_template( 'fbt.php', array(
			'product'      => null,
			'items'        => $items,
			'title'        => SMB_Settings::get( 'cart_title' ),
			'context'      => 'cart',
			'button_text'  => SMB_Settings::get( 'cart_btn_text' ),
			'show_summary' => 'yes' === SMB_Settings::get( 'cart_show_summary' ),
		) );
		return ob_get_clean();
	}

	/**
	 * AJAX: add the ticked suggestions to the cart, then hand back the cart page's own markup
	 * (same as the [woocommerce_cart] shortcode renders) so the browser can swap just that
	 * region in place — no full page reload.
	 */
	public static function ajax_cart_add() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'smb_cart_add' ) ) {
			wp_send_json_error( array( 'message' => 'bad nonce' ), 403 );
		}

		$raw = isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : '';
		// Never trust the browser: only what we would have suggested right now ("id:variation_id" tokens).
		$picks = SMB_Variations::resolve_tokens( $raw, SMB_Source::cart_items() );

		$added = 0;
		foreach ( $picks as $r ) {
			if ( WC()->cart->add_to_cart( $r['product_id'], 1, $r['variation_id'], $r['attrs'] ) ) {
				$added++;
			}
		}

		if ( $added < 1 ) {
			wp_send_json_error( array( 'message' => 'nothing added' ) );
		}

		// is_cart() has no main query to check inside admin-ajax.php, so hook the block in by hand.
		self::$printed = array();
		self::register_cart_position();

		wp_send_json_success( array(
			'added'     => $added,
			'cart_html' => do_shortcode( '[woocommerce_cart]' ),
			'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array() ),
			'cart_hash' => WC()->cart->get_cart_hash(),
		) );
	}
}
