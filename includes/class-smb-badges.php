<?php
defined( 'ABSPATH' ) || exit;

/**
 * Global product badges + the "Save Rs.X" / "You save N%" switches for Frequently bought together.
 *
 * Lives on its own page (WooCommerce > Smart Bundles > Badges & Savings) with its own options, so the
 * frozen settings class and its option keys are untouched:
 *
 *   smb_product_badges  [ product id => badge text ]   shown on that product whenever it appears in a
 *                                                      bought-together list (product page + cart page,
 *                                                      hand-picked or automatically filled)
 *   smb_fbt_display     [ save_amt, save_pct => yes|no ]
 *
 * A badge typed on a single product's own Smart Bundles box still wins over the global one.
 */
class SMB_Badges {

	const OPT_BADGES  = 'smb_product_badges';
	const OPT_DISPLAY = 'smb_fbt_display';
	const GROUP       = 'smb_badges_group';
	const SLUG        = 'smart-bundles-badges';

	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/* ---------------------------------------------------------------
	 * Read
	 * ------------------------------------------------------------- */

	/** [ product id => badge text ] */
	public static function map() {
		$out = array();
		foreach ( (array) get_option( self::OPT_BADGES, array() ) as $id => $badge ) {
			$id = absint( $id );
			if ( $id && '' !== trim( (string) $badge ) ) {
				$out[ $id ] = (string) $badge;
			}
		}
		return $out;
	}

	/** Global badge for a product ('' = none). Variations use their parent's id. */
	public static function get( $product_id ) {
		$map = self::map();
		$id  = absint( $product_id );
		return isset( $map[ $id ] ) ? $map[ $id ] : '';
	}

	private static function flag( $key ) {
		$o = (array) get_option( self::OPT_DISPLAY, array() );
		return ! isset( $o[ $key ] ) || 'yes' === $o[ $key ]; // on until the admin switches it off
	}

	public static function show_save_amt() {
		return (bool) apply_filters( 'smb_show_item_save_amount', self::flag( 'save_amt' ) );
	}

	public static function show_save_pct() {
		return (bool) apply_filters( 'smb_show_item_save_percent', self::flag( 'save_pct' ) );
	}

	/* ---------------------------------------------------------------
	 * Save
	 * ------------------------------------------------------------- */

	public static function register() {
		register_setting( self::GROUP, self::OPT_BADGES, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize_badges' ),
			'default'           => array(),
		) );
		register_setting( self::GROUP, self::OPT_DISPLAY, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize_display' ),
			'default'           => array(),
		) );
	}

	public static function sanitize_badges( $in ) {
		$out = array();
		foreach ( (array) $in as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$id    = isset( $r['id'] ) ? absint( $r['id'] ) : 0;
			$badge = isset( $r['badge'] ) ? sanitize_text_field( $r['badge'] ) : '';
			if ( $id && '' !== $badge && 'product' === get_post_type( $id ) ) {
				$out[ $id ] = $badge;
			}
		}
		return $out;
	}

	public static function sanitize_display( $in ) {
		$in = (array) $in;
		return array(
			'save_amt' => ( isset( $in['save_amt'] ) && 'yes' === $in['save_amt'] ) ? 'yes' : 'no',
			'save_pct' => ( isset( $in['save_pct'] ) && 'yes' === $in['save_pct'] ) ? 'yes' : 'no',
		);
	}

	/* ---------------------------------------------------------------
	 * Page
	 * ------------------------------------------------------------- */

	public static function menu() {
		self::$hook = add_submenu_page(
			'woocommerce',
			__( 'Smart Bundles: Badges & Savings', 'smart-bundles' ),
			__( 'Bundle Badges', 'smart-bundles' ),
			'manage_woocommerce',
			self::SLUG,
			array( __CLASS__, 'page' )
		);
	}

	public static function assets( $hook ) {
		if ( ! self::$hook || self::$hook !== $hook ) {
			return;
		}
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_script( 'smb-badges', SMB_URL . 'assets/js/badges.js', array( 'jquery', 'wc-enhanced-select' ), SMB_VERSION, true );
	}

	private static function row( $i, $id, $name, $badge ) {
		?>
		<tr class="smb-badge-row">
			<td>
				<input type="hidden" name="<?php echo esc_attr( self::OPT_BADGES ); ?>[<?php echo esc_attr( $i ); ?>][id]" value="<?php echo esc_attr( $id ); ?>"/>
				<strong class="smb-bname"><?php echo esc_html( $name ); ?></strong>
			</td>
			<td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPT_BADGES ); ?>[<?php echo esc_attr( $i ); ?>][badge]" value="<?php echo esc_attr( $badge ); ?>" placeholder="<?php esc_attr_e( 'e.g. New Launched', 'smart-bundles' ); ?>"/></td>
			<td style="width:48px;text-align:center"><button type="button" class="button smb-badge-remove" aria-label="<?php esc_attr_e( 'Remove', 'smart-bundles' ); ?>">&times;</button></td>
		</tr>
		<?php
	}

	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$map = self::map();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Smart Bundles: Badges & Savings', 'smart-bundles' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2><?php esc_html_e( 'Savings text (Frequently bought together)', 'smart-bundles' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Shown under each product that is on sale (regular price higher than the current price), on the product page and the cart page. Colours and fonts come from the Style tabs of Smart Bundles (the "Save Rs.X" pill and the "You save …%" text).', 'smart-bundles' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Save amount', 'smart-bundles' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPT_DISPLAY ); ?>[save_amt]" value="yes" <?php checked( self::flag( 'save_amt' ) ); ?>/> <?php esc_html_e( 'Show "Save Rs.X"', 'smart-bundles' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Save percent', 'smart-bundles' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPT_DISPLAY ); ?>[save_pct]" value="yes" <?php checked( self::flag( 'save_pct' ) ); ?>/> <?php esc_html_e( 'Show "You save N%"', 'smart-bundles' ); ?></label></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Product badges', 'smart-bundles' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Search a product and give it a badge. The badge shows on that product every time it appears in "Frequently bought together" (product page and cart page), including automatically filled slots. A badge typed in a single product\'s own Smart Bundles box overrides this one.', 'smart-bundles' ); ?></p>

				<p>
					<select class="wc-product-search smb-badge-search" style="width:100%;max-width:520px" data-placeholder="<?php esc_attr_e( 'Search for a product…', 'smart-bundles' ); ?>" data-action="woocommerce_json_search_products" data-exclude_type="variation" data-allow_clear="true">
						<option></option>
					</select>
				</p>

				<table class="widefat striped smb-badge-table" style="max-width:820px<?php echo $map ? '' : ';display:none'; ?>">
					<thead><tr><th><?php esc_html_e( 'Product', 'smart-bundles' ); ?></th><th><?php esc_html_e( 'Badge', 'smart-bundles' ); ?></th><th></th></tr></thead>
					<tbody>
						<?php
						$n = 0;
						foreach ( $map as $pid => $badge ) {
							$p = wc_get_product( $pid );
							if ( ! $p ) {
								continue;
							}
							self::row( $n++, $pid, $p->get_name() . ' (#' . $pid . ')', $badge );
						}
						?>
					</tbody>
				</table>
				<p class="description smb-badge-empty"<?php echo $n ? ' style="display:none"' : ''; ?>><?php esc_html_e( 'No product badges yet.', 'smart-bundles' ); ?></p>
				<span class="smb-badge-next" data-next="<?php echo esc_attr( $n ); ?>"></span>
				<script type="text/html" id="smb-badge-tpl"><?php self::row( '__i__', '__id__', '', '' ); ?></script>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
