<?php
defined( 'ABSPATH' ) || exit;

/**
 * Global settings: WooCommerce > Smart Bundles
 * Left: controls. Right: live preview that uses the real storefront templates + CSS.
 */
class SMB_Settings {

	const OPTION = 'smb_settings';

	public static function content_defaults() {
		return array(
			// Pack offers
			'packs_title'    => 'Save More When You Buy More',
			'packs_position' => 'before_button',
			'hide_qty'       => 'yes',
			// Bought together (product page)
			'fbt_title'         => 'Frequently Bought Together',
			'fbt_position'      => 'after_form',
			'fbt_global'        => 'yes',
			'fbt_pick_from'     => 'same_cat',
			'fbt_limit'         => 4,
			'fbt_preselect'     => 'no',
			'fbt_link_names'    => 'yes',
			'fbt_hide_oos'      => 'yes',
			'fbt_show_summary'  => 'yes',
			// Bought together (cart page)
			'cart_on'           => 'no',
			'cart_title'        => 'Frequently Bought Together',
			'cart_position'     => 'before_totals',
			'cart_limit'        => 4,
			'cart_btn_text'     => 'Add selected to cart',
			'cart_show_summary' => 'yes',
			// Bundle offer
			'bundle_title'        => 'Bundle Deal',
			'bundle_position'     => 'after_form',
			'bundle_name_default' => 'Complete the bundle to unlock savings',
			// Extra
			'custom_css'     => '',
		);
	}

	public static function defaults() {
		return array_merge( self::content_defaults(), SMB_Style::defaults() );
	}

	/**
	 * Settings saved by version 1.0 used a handful of colour keys. Carry them over once.
	 */
	private static function migrate_v1( $saved ) {
		if ( ! isset( $saved['color_accent'] ) || isset( $saved['pack_sel_border'] ) ) {
			return $saved;
		}
		$map = array(
			'color_accent'     => array( 'pack_sel_border', 'item_sel_border', 'radio_color', 'check_color', 'btn_bg' ),
			'color_border'     => array( 'pack_border_color', 'item_border_color', 'radio_border', 'check_border' ),
			'color_card_bg'    => array( 'pack_bg', 'pack_sel_bg', 'item_bg', 'item_sel_bg' ),
			'color_text'       => array( 'text_color', 'title_color', 'pack_label_color', 'pack_price_color', 'item_name_color', 'item_price_color' ),
			'color_muted'      => array( 'pack_sub_color', 'pack_strike_color', 'item_strike_color', 'item_qty_color', 'pack_hover_border', 'item_hover_border' ),
			'color_pill_bg'    => array( 'pill_bg' ),
			'color_pill_text'  => array( 'pill_color' ),
			'color_badge_bg'   => array( 'pack_badge_bg', 'item_badge_bg' ),
			'color_badge_text' => array( 'pack_badge_color', 'item_badge_color' ),
		);
		foreach ( $map as $old => $new_keys ) {
			foreach ( $new_keys as $nk ) {
				$saved[ $nk ] = $saved[ $old ];
			}
		}
		if ( isset( $saved['radius'] ) ) {
			foreach ( array( 'pack_radius', 'item_radius' ) as $nk ) {
				$saved[ $nk ] = (int) $saved['radius'];
			}
		}
		return $saved;
	}

	public static function all() {
		static $cache = null;
		if ( null === $cache ) {
			$saved = self::migrate_v1( (array) get_option( self::OPTION, array() ) );
			$cache = wp_parse_args( $saved, self::defaults() );
		}
		return $cache;
	}

	public static function get( $key, $default = '' ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	public static function pick_from_options() {
		return array(
			'manual_only' => __( 'Only the products I pick manually', 'smart-bundles' ),
			'same_cat'    => __( 'Same category as this product', 'smart-bundles' ),
			'same_tag'    => __( 'Same tag as this product', 'smart-bundles' ),
			'cats'        => __( 'Specific categories (choose in the product)', 'smart-bundles' ),
			'tags'        => __( 'Specific tags (choose in the product)', 'smart-bundles' ),
			'related'     => __( 'WooCommerce related products', 'smart-bundles' ),
			'all'         => __( 'All products', 'smart-bundles' ),
		);
	}

	public static function position_options() {
		return array(
			'before_button' => __( 'Above the Add to cart button (inside the form)', 'smart-bundles' ),
			'after_button'  => __( 'Under the Add to cart button (inside the form)', 'smart-bundles' ),
			'before_form'   => __( 'Above the Add to cart form', 'smart-bundles' ),
			'after_form'    => __( 'Under the Add to cart form', 'smart-bundles' ),
			'after_price'   => __( 'Under the price', 'smart-bundles' ),
			'after_excerpt' => __( 'Under the short description', 'smart-bundles' ),
			'shortcode'     => __( 'Do not auto-insert (use the shortcode)', 'smart-bundles' ),
		);
	}

	public static function cart_position_options() {
		return array(
			'before_cart'   => __( 'Top of the cart page', 'smart-bundles' ),
			'before_table'  => __( 'Above the cart table', 'smart-bundles' ),
			'after_table'   => __( 'Under the cart table', 'smart-bundles' ),
			'before_totals' => __( 'Above the cart totals', 'smart-bundles' ),
			'after_cart'    => __( 'Bottom of the cart page', 'smart-bundles' ),
			'shortcode'     => __( 'Do not auto-insert (use the shortcode)', 'smart-bundles' ),
		);
	}

	/* ---------------------------------------------------------------
	 * Registration + saving
	 * ------------------------------------------------------------- */

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		add_submenu_page( 'woocommerce', 'Smart Bundles', 'Smart Bundles', 'manage_woocommerce', 'smart-bundles', array( __CLASS__, 'page' ) );
	}

	public static function register() {
		register_setting( 'smb_group', self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $in ) {
		$d   = self::content_defaults();
		$out = array();
		$in  = (array) $in;

		foreach ( array( 'packs_title', 'fbt_title', 'cart_title', 'cart_btn_text', 'bundle_title', 'bundle_name_default' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : $d[ $k ];
		}
		foreach ( array( 'packs_position', 'fbt_position', 'bundle_position' ) as $k ) {
			$out[ $k ] = ( isset( $in[ $k ] ) && isset( self::position_options()[ $in[ $k ] ] ) ) ? $in[ $k ] : $d[ $k ];
		}
		$out['cart_position'] = ( isset( $in['cart_position'] ) && isset( self::cart_position_options()[ $in['cart_position'] ] ) ) ? $in['cart_position'] : $d['cart_position'];
		$out['fbt_pick_from'] = ( isset( $in['fbt_pick_from'] ) && isset( self::pick_from_options()[ $in['fbt_pick_from'] ] ) ) ? $in['fbt_pick_from'] : $d['fbt_pick_from'];
		$out['fbt_limit']     = max( 1, min( 12, absint( isset( $in['fbt_limit'] ) ? $in['fbt_limit'] : $d['fbt_limit'] ) ) );
		$out['cart_limit']    = max( 1, min( 12, absint( isset( $in['cart_limit'] ) ? $in['cart_limit'] : $d['cart_limit'] ) ) );

		foreach ( array( 'hide_qty', 'fbt_global', 'fbt_preselect', 'fbt_link_names', 'fbt_hide_oos', 'fbt_show_summary', 'cart_on', 'cart_show_summary' ) as $k ) {
			$out[ $k ] = ( isset( $in[ $k ] ) && 'yes' === $in[ $k ] ) ? 'yes' : 'no';
		}

		$out['custom_css'] = isset( $in['custom_css'] ) ? wp_strip_all_tags( $in['custom_css'] ) : '';

		$out = array_merge( $out, SMB_Style::sanitize( $in ) );

		SMB_Source::bump_cache();
		return $out;
	}

	/* ---------------------------------------------------------------
	 * Admin assets
	 * ------------------------------------------------------------- */

	public static function assets( $hook ) {
		if ( 'woocommerce_page_smart-bundles' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'smb-settings', SMB_URL . 'assets/css/settings.css', array(), SMB_VERSION );
		wp_enqueue_script( 'smb-settings', SMB_URL . 'assets/js/settings.js', array( 'jquery', 'wp-color-picker' ), SMB_VERSION, true );
		wp_localize_script( 'smb-settings', 'smbSettings', array(
			'fontBase' => 'https://fonts.googleapis.com/css2?',
			'confirm'  => __( 'Reset every style control to the plugin defaults? (Nothing is saved until you click Save.)', 'smart-bundles' ),
		) );
	}

	/* ---------------------------------------------------------------
	 * Content fields (non-style)
	 * ------------------------------------------------------------- */

	private static function row_open( $label ) {
		echo '<div class="smb-ctl"><label>' . esc_html( $label ) . '</label><div class="smb-ctl-field smb-ctl-field--wide">';
	}

	private static function row_close( $help = '' ) {
		echo '</div>' . ( $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) . '</div>';
	}

	private static function f_text( $key, $label, $help = '', $live = '' ) {
		self::row_open( $label );
		printf(
			'<input type="text" name="%s[%s]" value="%s"%s/>',
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			esc_attr( self::get( $key ) ),
			$live ? ' data-live="' . esc_attr( $live ) . '"' : ''
		);
		self::row_close( $help );
	}

	private static function f_select( $key, $label, $options, $help = '' ) {
		self::row_open( $label );
		echo '<select name="' . esc_attr( self::OPTION . '[' . $key . ']' ) . '">';
		foreach ( $options as $v => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( self::get( $key ), $v, false ), esc_html( $l ) );
		}
		echo '</select>';
		self::row_close( $help );
	}

	private static function f_number( $key, $label, $min, $max, $help = '' ) {
		self::row_open( $label );
		printf(
			'<input type="number" step="1" min="%s" max="%s" name="%s[%s]" value="%s" class="small-text"/>',
			esc_attr( $min ),
			esc_attr( $max ),
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			esc_attr( self::get( $key ) )
		);
		self::row_close( $help );
	}

	private static function f_yesno( $key, $label, $text ) {
		self::row_open( $label );
		printf(
			'<label class="smb-check-label"><input type="checkbox" name="%s[%s]" value="yes"%s/> %s</label>',
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			checked( self::get( $key ), 'yes', false ),
			esc_html( $text )
		);
		self::row_close();
	}

	/* ---------------------------------------------------------------
	 * Preview document (shown in an iframe so media queries are real)
	 * ------------------------------------------------------------- */

	private static function demo_blocks() {
		$tiers = array(
			array( 'label' => '1 pack', 'qty' => 1, 'type' => 'percent', 'value' => 0, 'badge' => '', 'default' => false, 'total' => 1599, 'reg_total' => 1599, 'save' => 0, 'save_pct' => 0, 'save_pct_text' => '0', 'fixed_total' => 0 ),
			array( 'label' => '2 pack', 'qty' => 2, 'type' => 'fixed', 'value' => 2565, 'badge' => 'Most Popular', 'default' => true, 'total' => 2565, 'reg_total' => 3198, 'save' => 633, 'save_pct' => 19.79, 'save_pct_text' => '19.79', 'fixed_total' => 2565 ),
			array( 'label' => '3 pack', 'qty' => 3, 'type' => 'percent', 'value' => 25, 'badge' => 'Best Value', 'default' => false, 'total' => 3598, 'reg_total' => 4797, 'save' => 1199, 'save_pct' => 25, 'save_pct_text' => '25', 'fixed_total' => 0 ),
		);

		$img = '<span class="smb-demo-img"></span>';

		$items = array(
			array( 'id' => 1, 'name' => 'Vitamin C Serum 30ml', 'url' => '#', 'image' => $img, 'price' => 899, 'regular' => 899, 'badge' => 'New Launched', 'checked' => true ),
			array( 'id' => 2, 'name' => 'Hydrating Face Cream', 'url' => '#', 'image' => $img, 'price' => 1250, 'regular' => 1500, 'badge' => '', 'checked' => true ),
			array( 'id' => 3, 'name' => 'Gentle Foaming Cleanser', 'url' => '#', 'image' => $img, 'price' => 450, 'regular' => 450, 'badge' => 'Best Seller', 'checked' => false ),
		);

		$bundle_rows = array(
			array( 'id' => 1, 'name' => 'Roastella citrus blend coffee', 'image' => $img, 'unit' => 24, 'regular' => 30 ),
			array( 'id' => 2, 'name' => 'Brewhaus copper pour-over kit', 'image' => $img, 'unit' => 10.4, 'regular' => 13 ),
			array( 'id' => 3, 'name' => 'Thermaflow insulated tumbler', 'image' => $img, 'unit' => 12, 'regular' => 15 ),
		);
		$bundle_total = 0;
		$bundle_reg   = 0;
		foreach ( $bundle_rows as $r ) {
			$bundle_total += $r['unit'];
			$bundle_reg   += $r['regular'];
		}
		$bundle_save = max( 0, $bundle_reg - $bundle_total );

		ob_start();
		echo '<section data-demo="packs"><h6 class="smb-demo-label">Pack offers</h6>';
		smb_get_template( 'packs.php', array(
			'product'  => null,
			'tiers'    => $tiers,
			'title'    => self::get( 'packs_title' ),
			'is_var'   => false,
			'hide_qty' => false,
		) );
		echo '</section><section data-demo="fbt"><h6 class="smb-demo-label">Frequently bought together (product page)</h6>';
		smb_get_template( 'fbt.php', array(
			'product'      => null,
			'items'        => $items,
			'title'        => self::get( 'fbt_title' ),
			'context'      => 'product',
			'show_summary' => 'yes' === self::get( 'fbt_show_summary' ),
			'demo'         => true,
		) );
		echo '</section><section data-demo="bundle"><h6 class="smb-demo-label">Bundle offer</h6>';
		smb_get_template( 'bundle.php', array(
			'title'     => self::get( 'bundle_title' ),
			'name'      => self::get( 'bundle_name_default' ),
			'badge'     => '',
			'rows'      => $bundle_rows,
			'total'     => $bundle_total,
			'reg_total' => $bundle_reg,
			'save'      => $bundle_save,
			'save_pct_text' => SMB_Frontend::pct_text( $bundle_reg > 0 ? $bundle_save / $bundle_reg * 100 : 0 ),
			'demo'      => true,
		) );
		echo '</section><section data-demo="cart"><h6 class="smb-demo-label">Frequently bought together (cart page)</h6>';
		smb_get_template( 'fbt.php', array(
			'product'      => null,
			'items'        => $items,
			'title'        => self::get( 'cart_title' ),
			'context'      => 'cart',
			'button_text'  => self::get( 'cart_btn_text' ),
			'show_summary' => 'yes' === self::get( 'cart_show_summary' ),
			'demo'         => true,
		) );
		echo '</section>';
		return ob_get_clean();
	}

	private static function preview_doc() {
		$values = self::all();
		$font   = SMB_Style::font_url( $values );

		$head  = '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		$head .= '<link rel="stylesheet" href="' . esc_url( SMB_URL . 'assets/css/frontend.css?ver=' . SMB_VERSION ) . '">';
		if ( $font ) {
			$head .= '<link rel="stylesheet" id="smb-font-initial" href="' . esc_url( $font ) . '">';
		}
		$head .= '<style id="smb-live">' . SMB_Style::css( $values ) . '</style>';
		$head .= '<style>
			html,body{margin:0;padding:0;background:#fff}
			body{padding:22px 20px 26px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;font-size:16px;color:#1f2937}
			.smb-demo-label{margin:26px 0 -6px;font:600 11px/1 -apple-system,Arial,sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8}
			.smb-demo-label:first-child{margin-top:0}
			body.is-single .smb-demo-label{display:none}
			.smb-demo-img{display:block;width:100%;height:100%;border-radius:6px;background:linear-gradient(135deg,#e2e8f0,#cbd5e1)}
			section[hidden]{display:none}
		</style>';
		$head .= '<style id="smb-custom">' . self::get( 'custom_css' ) . '</style>';

		$js = '(function(){
			document.addEventListener("change",function(e){
				var t=e.target;
				if(t.classList.contains("smb-radio")){
					var cards=t.closest(".smb-packs").querySelectorAll(".smb-pack");
					for(var i=0;i<cards.length;i++){cards[i].classList.remove("is-selected");}
					t.closest(".smb-pack").classList.add("is-selected");
				}
				if(t.classList.contains("smb-check")){t.closest(".smb-item").classList.toggle("is-selected",t.checked);}
			});
			document.addEventListener("click",function(e){var a=e.target.closest("a");if(a){e.preventDefault();}});
		})();';

		return '<!doctype html><html><head>' . $head . '</head><body>' . self::demo_blocks() . '<script>' . $js . '</script></body></html>';
	}

	/* ---------------------------------------------------------------
	 * The page
	 * ------------------------------------------------------------- */

	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$opt    = self::OPTION;
		$values = self::all();
		$tabs   = array(
			'content' => array( 'Settings', 'all' ),
		);
		foreach ( SMB_Style::tabs() as $id => $t ) {
			$tabs[ $id ] = array( $t['label'], $t['demo'] );
		}
		$tabs['css'] = array( 'Extra CSS', 'all' );
		?>
		<div class="wrap smb-settings">
			<h1>Smart Bundles</h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php" id="smb-form">
				<?php settings_fields( 'smb_group' ); ?>

				<div class="smb-layout">

					<div class="smb-side">
						<div class="smb-tabs" role="tablist">
							<?php
							$first = true;
							foreach ( $tabs as $id => $t ) {
								printf(
									'<button type="button" class="smb-tab%s" data-tab="%s" data-demo="%s">%s</button>',
									$first ? ' is-active' : '',
									esc_attr( $id ),
									esc_attr( $t[1] ),
									esc_html( $t[0] )
								);
								$first = false;
							}
							?>
						</div>

						<div class="smb-panel is-active" data-panel="content">
							<p class="description smb-intro">Global defaults. Every product can override the headings and products in its own <strong>Smart Bundles</strong> box on the product edit screen.</p>

							<details class="smb-acc" open>
								<summary>Pack offers</summary>
								<div class="smb-acc-body">
									<?php
									self::f_text( 'packs_title', 'Section title', 'Shown above the pack cards.', 'packs_title' );
									self::f_select( 'packs_position', 'Position', self::position_options(), 'Shortcode: [smb_packs]' );
									self::f_yesno( 'hide_qty', 'Quantity box', 'Hide the normal quantity box when a product has pack offers (the pack choice sets the quantity)' );
									?>
								</div>
							</details>

							<details class="smb-acc">
								<summary>Frequently bought together: product page</summary>
								<div class="smb-acc-body">
									<?php
									self::f_yesno( 'fbt_global', 'Show on all products', 'Show the box on every product by default (a product can switch it off in its own settings)' );
									self::f_text( 'fbt_title', 'Section title', 'A product can use its own heading instead.', 'fbt_title' );
									self::f_select( 'fbt_position', 'Position', self::position_options(), 'Shortcode: [smb_bundle]' );
									self::f_select( 'fbt_pick_from', 'Fill remaining slots from', self::pick_from_options(), 'Products you pick by hand in the product always come first.' );
									self::f_number( 'fbt_limit', 'Max products shown', 1, 12 );
									self::f_yesno( 'fbt_preselect', 'Pre-select', 'Tick all add-ons by default' );
									self::f_yesno( 'fbt_link_names', 'Product links', 'Link product names to their pages' );
									self::f_yesno( 'fbt_hide_oos', 'Stock', 'Hide out-of-stock products' );
									self::f_yesno( 'fbt_show_summary', 'Summary line', 'Show the "N selected · +Rs.X" line under the list' );
									?>
								</div>
							</details>

							<details class="smb-acc">
								<summary>Frequently bought together: cart page</summary>
								<div class="smb-acc-body">
									<?php
									self::f_yesno( 'cart_on', 'Show on cart page', 'Suggest add-ons for the products in the cart' );
									self::f_text( 'cart_title', 'Section title', '', 'cart_title' );
									self::f_select( 'cart_position', 'Position', self::cart_position_options(), 'Shortcode: [smb_cart_bundle]. Works with the classic cart page; for the block-based cart, put the shortcode in a Shortcode block.' );
									self::f_number( 'cart_limit', 'Max products shown', 1, 12 );
									self::f_text( 'cart_btn_text', 'Button text', '', 'cart_btn_text' );
									self::f_yesno( 'cart_show_summary', 'Summary line', 'Show the "N selected · +Rs.X" line under the list' );
									?>
								</div>
							</details>

							<details class="smb-acc">
								<summary>Bundle offer</summary>
								<div class="smb-acc-body">
									<?php
									self::f_text( 'bundle_title', 'Section title', 'Shown above the bundle card.', 'bundle_title' );
									self::f_text( 'bundle_name_default', 'Default headline', 'Shown next to the toggle, e.g. "Complete the bundle to unlock savings". A product can use its own instead.', 'bundle_name_default' );
									self::f_select( 'bundle_position', 'Position', self::position_options(), 'Shortcode: [smb_offer]' );
									?>
									<p class="description smb-intro">Which products are in the bundle, its badge and its discount are set per product, in the product's own Smart Bundles box.</p>
								</div>
							</details>
						</div>

						<?php foreach ( SMB_Style::tabs() as $id => $t ) : ?>
							<div class="smb-panel" data-panel="<?php echo esc_attr( $id ); ?>">
								<?php SMB_Style::render_tab( $id, $values, $opt ); ?>
							</div>
						<?php endforeach; ?>

						<div class="smb-panel" data-panel="css">
							<div class="smb-ctl">
								<label>Extra CSS</label>
								<div class="smb-ctl-field smb-ctl-field--wide">
									<textarea name="<?php echo esc_attr( $opt ); ?>[custom_css]" rows="10" class="large-text code" data-live="custom_css" placeholder=".smb-pack { ... }"><?php echo esc_textarea( self::get( 'custom_css' ) ); ?></textarea>
								</div>
								<p class="description">Need even more control? Copy files from <code>smart-bundles/templates/</code> into <code>your-theme/smart-bundles/</code> and edit the markup.</p>
							</div>
						</div>

						<div class="smb-actions">
							<?php submit_button( 'Save changes', 'primary', 'submit', false ); ?>
							<button type="button" class="button smb-reset">Reset styles</button>
						</div>
					</div>

					<div class="smb-preview">
						<div class="smb-toolbar">
							<div class="smb-seg" data-group="demo">
								<button type="button" data-demo="all" class="is-active">All</button>
								<button type="button" data-demo="packs">Pack offers</button>
								<button type="button" data-demo="fbt">Bought together</button>
								<button type="button" data-demo="bundle">Bundle offer</button>
								<button type="button" data-demo="cart">Cart page</button>
							</div>
							<div class="smb-seg" data-group="device">
								<button type="button" data-w="100%" class="is-active" title="Desktop">Desktop</button>
								<button type="button" data-w="768px" title="Tablet">Tablet</button>
								<button type="button" data-w="390px" title="Mobile">Mobile</button>
							</div>
						</div>
						<div class="smb-frame-wrap">
							<iframe id="smb-preview-frame" title="Live preview" srcdoc="<?php echo esc_attr( self::preview_doc() ); ?>"></iframe>
						</div>
						<p class="description smb-preview-note">Live preview: changes show instantly. Click the cards to see the selected style. Nothing is saved until you press <strong>Save changes</strong>.</p>
					</div>

				</div>
			</form>
		</div>
		<?php
	}
}
