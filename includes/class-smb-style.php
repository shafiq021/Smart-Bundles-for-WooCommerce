<?php
defined( 'ABSPATH' ) || exit;

/**
 * Style schema.
 *
 * ONE list of controls drives three things, so they can never drift apart:
 *   1. the controls on the settings page,
 *   2. the CSS variables printed on the storefront,
 *   3. the live preview on the settings page.
 *
 * A control's CSS variable is derived from its key:  pack_label_size  =>  --smb-pack-label-size
 */
class SMB_Style {

	private static $tabs = null;

	/* ---------------------------------------------------------------
	 * Option lists
	 * ------------------------------------------------------------- */

	/**
	 * Font stacks. 'google' = family name to load from Google Fonts (empty = no loading needed).
	 */
	public static function fonts() {
		return array(
			'inherit'                                                 => array( 'label' => 'Theme default', 'google' => '', 'wght' => '' ),
			'system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif' => array( 'label' => 'System UI', 'google' => '', 'wght' => '' ),
			'Arial,Helvetica,sans-serif'                              => array( 'label' => 'Arial', 'google' => '', 'wght' => '' ),
			'Georgia,"Times New Roman",serif'                         => array( 'label' => 'Georgia', 'google' => '', 'wght' => '' ),
			'"Trebuchet MS",Helvetica,sans-serif'                     => array( 'label' => 'Trebuchet MS', 'google' => '', 'wght' => '' ),
			'Verdana,Geneva,sans-serif'                               => array( 'label' => 'Verdana', 'google' => '', 'wght' => '' ),
			'Tahoma,Geneva,sans-serif'                                => array( 'label' => 'Tahoma', 'google' => '', 'wght' => '' ),
			'"Courier New",monospace'                                 => array( 'label' => 'Courier New', 'google' => '', 'wght' => '' ),
			'"Inter",sans-serif'                                      => array( 'label' => 'Inter (Google)', 'google' => 'Inter', 'wght' => '400;500;600;700;800' ),
			'"Poppins",sans-serif'                                    => array( 'label' => 'Poppins (Google)', 'google' => 'Poppins', 'wght' => '400;500;600;700;800' ),
			'"Roboto",sans-serif'                                     => array( 'label' => 'Roboto (Google)', 'google' => 'Roboto', 'wght' => '400;500;700;900' ),
			'"Open Sans",sans-serif'                                  => array( 'label' => 'Open Sans (Google)', 'google' => 'Open Sans', 'wght' => '400;500;600;700;800' ),
			'"Montserrat",sans-serif'                                 => array( 'label' => 'Montserrat (Google)', 'google' => 'Montserrat', 'wght' => '400;500;600;700;800' ),
			'"Lato",sans-serif'                                       => array( 'label' => 'Lato (Google)', 'google' => 'Lato', 'wght' => '400;700;900' ),
			'"Nunito",sans-serif'                                     => array( 'label' => 'Nunito (Google)', 'google' => 'Nunito', 'wght' => '400;500;600;700;800' ),
			'"DM Sans",sans-serif'                                    => array( 'label' => 'DM Sans (Google)', 'google' => 'DM Sans', 'wght' => '400;500;600;700' ),
			'"Raleway",sans-serif'                                    => array( 'label' => 'Raleway (Google)', 'google' => 'Raleway', 'wght' => '400;500;600;700;800' ),
			'"Noto Sans",sans-serif'                                  => array( 'label' => 'Noto Sans (Google)', 'google' => 'Noto Sans', 'wght' => '400;500;600;700;800' ),
			'"Oswald",sans-serif'                                     => array( 'label' => 'Oswald (Google)', 'google' => 'Oswald', 'wght' => '400;500;600;700' ),
			'"Playfair Display",serif'                                => array( 'label' => 'Playfair Display (Google)', 'google' => 'Playfair Display', 'wght' => '400;500;600;700;800' ),
		);
	}

	private static function weights() {
		return array(
			'300' => 'Light (300)',
			'400' => 'Normal (400)',
			'500' => 'Medium (500)',
			'600' => 'Semi-bold (600)',
			'700' => 'Bold (700)',
			'800' => 'Extra-bold (800)',
			'900' => 'Black (900)',
		);
	}

	private static function borders() {
		return array(
			'solid'  => 'Solid',
			'dashed' => 'Dashed',
			'dotted' => 'Dotted',
			'double' => 'Double',
			'none'   => 'None',
		);
	}

	private static function shadows() {
		return array(
			'none'                             => 'None',
			'0 2px 8px rgba(0,0,0,.10)'        => 'Soft',
			'0 6px 18px rgba(0,0,0,.14)'       => 'Medium',
			'0 12px 30px rgba(0,0,0,.20)'      => 'Strong',
		);
	}

	private static function selected_shadows( $var ) {
		return array(
			'none'                                    => 'None',
			'0 0 0 1px var(' . $var . ')'             => 'Outline 1px',
			'0 0 0 2px var(' . $var . ')'             => 'Outline 2px',
			'0 2px 8px rgba(0,0,0,.10)'               => 'Soft shadow',
			'0 6px 18px rgba(0,0,0,.14)'              => 'Medium shadow',
			'0 12px 30px rgba(0,0,0,.20)'             => 'Strong shadow',
		);
	}

	/* ---------------------------------------------------------------
	 * Control builders
	 * ------------------------------------------------------------- */

	private static function c( $key, $label, $default ) {
		return array( 'key' => $key, 'label' => $label, 'type' => 'color', 'default' => $default );
	}

	private static function n( $key, $label, $default, $min, $max, $step = 1 ) {
		return array( 'key' => $key, 'label' => $label, 'type' => 'size', 'default' => $default, 'min' => $min, 'max' => $max, 'step' => $step );
	}

	private static function o( $key, $label, $default, $options ) {
		return array( 'key' => $key, 'label' => $label, 'type' => 'select', 'default' => (string) $default, 'options' => $options );
	}

	/** Font family + size + weight + colour for one text element. */
	private static function typo( $p, $size, $weight, $color, $min = 8, $max = 60 ) {
		return array(
			array( 'key' => $p . '_font', 'label' => 'Font family', 'type' => 'font', 'default' => 'inherit' ),
			self::n( $p . '_size', 'Font size (px)', $size, $min, $max ),
			self::o( $p . '_weight', 'Font weight', $weight, self::weights() ),
			self::c( $p . '_color', 'Text color', $color ),
		);
	}

	/* ---------------------------------------------------------------
	 * The schema
	 * ------------------------------------------------------------- */

	public static function tabs() {
		if ( null !== self::$tabs ) {
			return self::$tabs;
		}

		$tabs = array();

		$tabs['general'] = array(
			'label'    => 'General',
			'demo'     => 'all',
			'sections' => array(
				'Section title' => array_merge(
					self::typo( 'title', 15, '700', '#1f2937' ),
					array(
						self::o( 'title_transform', 'Letter case', 'none', array( 'none' => 'As typed', 'uppercase' => 'UPPERCASE', 'lowercase' => 'lowercase', 'capitalize' => 'Capitalize' ) ),
						self::n( 'title_spacing', 'Letter spacing (px)', 0, 0, 10, 0.5 ),
						self::c( 'title_line_color', 'Side lines color', '#9ca3af' ),
						self::n( 'title_line_width', 'Side lines thickness (px, 0 = hide)', 1, 0, 6 ),
						self::n( 'title_gap', 'Space under title (px)', 18, 0, 60 ),
					)
				),
				'Block' => array(
					self::c( 'text_color', 'Default text color', '#1f2937' ),
					self::n( 'wrap_margin', 'Space above & below block (px)', 18, 0, 80 ),
				),
			),
		);

		$tabs['packs'] = array(
			'label'    => 'Pack offers',
			'demo'     => 'packs',
			'sections' => array(
				'Card' => array(
					self::c( 'pack_bg', 'Background', '#ffffff' ),
					self::c( 'pack_border_color', 'Border color', '#cbd5e1' ),
					self::n( 'pack_border_width', 'Border width (px)', 2, 0, 10 ),
					self::o( 'pack_border_style', 'Border style', 'solid', self::borders() ),
					self::n( 'pack_radius', 'Corner radius (px)', 10, 0, 60 ),
					self::n( 'pack_pad_y', 'Padding top & bottom (px)', 18, 0, 60 ),
					self::n( 'pack_pad_x', 'Padding left & right (px)', 20, 0, 60 ),
					self::n( 'pack_gap', 'Space between cards (px)', 22, 0, 60 ),
					self::o( 'pack_shadow', 'Shadow', 'none', self::shadows() ),
				),
				'Card: hover & selected' => array(
					self::c( 'pack_hover_border', 'Hover border color', '#64748b' ),
					self::c( 'pack_sel_border', 'Selected border color', '#1f2937' ),
					self::c( 'pack_sel_bg', 'Selected background', '#ffffff' ),
					self::o( 'pack_sel_shadow', 'Selected shadow', '0 0 0 1px var(--smb-pack-sel-border)', self::selected_shadows( '--smb-pack-sel-border' ) ),
				),
				'Radio button' => array(
					self::c( 'radio_color', 'Selected color', '#1f2937' ),
					self::c( 'radio_border', 'Border color', '#cbd5e1' ),
					self::n( 'radio_size', 'Size (px)', 24, 14, 40 ),
				),
				'Pack label (e.g. "2 pack")' => self::typo( 'pack_label', 22, '800', '#1f2937' ),
				'"Save Rs.X" pill' => array_merge(
					self::typo( 'pill', 13, '500', '#065f46' ),
					array(
						self::c( 'pill_bg', 'Background', '#d1fae5' ),
						self::n( 'pill_radius', 'Corner radius (px)', 8, 0, 40 ),
					)
				),
				'"You save …%" text' => self::typo( 'pack_sub', 14, '400', '#64748b' ),
				'Price' => self::typo( 'pack_price', 23, '800', '#1f2937' ),
				'Old price (strike-through)' => self::typo( 'pack_strike', 15, '500', '#64748b' ),
				'Badge (e.g. "Most Popular")' => array_merge(
					self::typo( 'pack_badge', 12, '700', '#ffffff' ),
					array(
						self::c( 'pack_badge_bg', 'Background', '#374151' ),
						self::n( 'pack_badge_radius', 'Corner radius (px)', 8, 0, 40 ),
					)
				),
			),
		);

		$tabs['fbt'] = array(
			'label'    => 'Bought together',
			'demo'     => 'fbt',
			'sections' => array(
				'Card' => array(
					self::c( 'item_bg', 'Background', '#ffffff' ),
					self::c( 'item_border_color', 'Border color', '#cbd5e1' ),
					self::n( 'item_border_width', 'Border width (px)', 2, 0, 10 ),
					self::o( 'item_border_style', 'Border style', 'dashed', self::borders() ),
					self::n( 'item_radius', 'Corner radius (px)', 10, 0, 60 ),
					self::n( 'item_pad_y', 'Padding top & bottom (px)', 14, 0, 60 ),
					self::n( 'item_pad_x', 'Padding left & right (px)', 18, 0, 60 ),
					self::n( 'item_gap', 'Space between cards (px)', 18, 0, 60 ),
					self::o( 'item_shadow', 'Shadow', 'none', self::shadows() ),
				),
				'Card: hover & selected' => array(
					self::c( 'item_hover_border', 'Hover border color', '#64748b' ),
					self::c( 'item_sel_border', 'Selected border color', '#1f2937' ),
					self::o( 'item_sel_border_style', 'Selected border style', 'solid', self::borders() ),
					self::c( 'item_sel_bg', 'Selected background', '#ffffff' ),
				),
				'Checkbox' => array(
					self::c( 'check_color', 'Checked color', '#1f2937' ),
					self::c( 'check_border', 'Border color', '#cbd5e1' ),
					self::n( 'check_size', 'Size (px)', 24, 14, 40 ),
					self::n( 'check_radius', 'Corner radius (px)', 6, 0, 20 ),
				),
				'Product image' => array(
					self::n( 'item_img_size', 'Size (px)', 46, 24, 140 ),
					self::n( 'item_img_radius', 'Corner radius (px)', 4, 0, 70 ),
				),
				'Quantity label ("1×")' => array(
					self::n( 'item_qty_size', 'Font size (px)', 15, 8, 40 ),
					self::c( 'item_qty_color', 'Text color', '#64748b' ),
				),
				'Product name' => self::typo( 'item_name', 16, '700', '#1f2937' ),
				'Price' => self::typo( 'item_price', 21, '800', '#1f2937' ),
				'Old price (strike-through)' => self::typo( 'item_strike', 14, '500', '#64748b' ),
				'Badge (e.g. "New Launched")' => array_merge(
					self::typo( 'item_badge', 12, '700', '#ffffff' ),
					array(
						self::c( 'item_badge_bg', 'Background', '#374151' ),
						self::n( 'item_badge_radius', 'Corner radius (px)', 8, 0, 40 ),
					)
				),
				'Summary line' => array_merge(
					self::typo( 'sum', 14, '500', '#1f2937' ),
					array(
						self::c( 'sum_bg', 'Background', '#f1f5f9' ),
						self::n( 'sum_radius', 'Corner radius (px)', 10, 0, 40 ),
					)
				),
			),
		);

		$tabs['bundle'] = array(
			'label'    => 'Bundle offer',
			'demo'     => 'bundle',
			'sections' => array(
				'Card' => array(
					self::c( 'bundle_bg', 'Background', '#ffffff' ),
					self::c( 'bundle_border_color', 'Border color', '#d1d5db' ),
					self::n( 'bundle_border_width', 'Border width (px)', 1, 0, 10 ),
					self::o( 'bundle_border_style', 'Border style', 'solid', self::borders() ),
					self::n( 'bundle_radius', 'Corner radius (px)', 10, 0, 60 ),
					self::n( 'bundle_pad_y', 'Padding top & bottom (px)', 20, 0, 60 ),
					self::n( 'bundle_pad_x', 'Padding left & right (px)', 20, 0, 60 ),
					self::o( 'bundle_shadow', 'Shadow', 'none', self::shadows() ),
				),
				'Card: selected' => array(
					self::c( 'bundle_sel_border', 'Selected border color', '#1f2937' ),
					self::c( 'bundle_sel_bg', 'Selected background', '#ffffff' ),
					self::o( 'bundle_sel_shadow', 'Selected shadow', '0 0 0 1px var(--smb-bundle-sel-border)', self::selected_shadows( '--smb-bundle-sel-border' ) ),
				),
				'Toggle' => array(
					self::c( 'bundle_radio_color', 'Selected color', '#1f2937' ),
					self::c( 'bundle_radio_border', 'Border color', '#cbd5e1' ),
					self::n( 'bundle_radio_size', 'Size (px)', 24, 14, 40 ),
				),
				'Headline (e.g. "Complete the bundle…")' => self::typo( 'bundle_name', 17, '700', '#1f2937' ),
				'Price' => self::typo( 'bundle_price', 24, '800', '#1f2937' ),
				'Old price (strike-through)' => self::typo( 'bundle_strike', 15, '500', '#64748b' ),
				'"Save Rs.X!" line' => self::typo( 'bundle_save', 14, '500', '#64748b' ),
				'Badge (e.g. "Bundle Deal")' => array_merge(
					self::typo( 'bundle_badge', 12, '700', '#ffffff' ),
					array(
						self::c( 'bundle_badge_bg', 'Background', '#374151' ),
						self::n( 'bundle_badge_radius', 'Corner radius (px)', 8, 0, 40 ),
					)
				),
				'Items list' => array(
					self::c( 'bundle_item_border_color', 'Divider line color', '#e5e7eb' ),
					self::n( 'bundle_item_gap', 'Space between items (px)', 0, 0, 40 ),
					self::n( 'bundle_item_pad_y', 'Padding top & bottom (px)', 12, 0, 50 ),
					self::n( 'bundle_item_img_size', 'Image size (px)', 42, 24, 140 ),
					self::n( 'bundle_item_img_radius', 'Image corner radius (px)', 6, 0, 70 ),
					self::c( 'bundle_divider_color', '"+" divider color', '#9ca3af' ),
					self::c( 'bundle_divider_bg', '"+" divider background', '#ffffff' ),
					self::n( 'bundle_divider_size', '"+" divider size (px)', 22, 12, 40 ),
				),
				'Item name' => self::typo( 'bundle_item_name', 15, '600', '#1f2937' ),
				'Item price' => self::typo( 'bundle_item_price', 15, '700', '#1f2937' ),
				'Item old price (strike-through)' => self::typo( 'bundle_item_strike', 13, '500', '#9ca3af' ),
			),
		);

		$tabs['cart'] = array(
			'label'    => 'Cart button',
			'demo'     => 'cart',
			'sections' => array(
				'"Add selected to cart" button' => array_merge(
					self::typo( 'btn', 15, '600', '#ffffff' ),
					array(
						self::c( 'btn_bg', 'Background', '#1f2937' ),
						self::c( 'btn_hover_bg', 'Hover background', '#111827' ),
						self::n( 'btn_radius', 'Corner radius (px)', 8, 0, 60 ),
						self::n( 'btn_pad_y', 'Padding top & bottom (px)', 12, 0, 40 ),
						self::n( 'btn_pad_x', 'Padding left & right (px)', 22, 0, 80 ),
						self::o( 'btn_align', 'Alignment', 'left', array( 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ) ),
					)
				),
			),
		);

		self::$tabs = $tabs;
		return $tabs;
	}

	/** Flat list: key => control. */
	public static function controls() {
		static $flat = null;
		if ( null === $flat ) {
			$flat = array();
			foreach ( self::tabs() as $tab ) {
				foreach ( $tab['sections'] as $controls ) {
					foreach ( $controls as $ctl ) {
						$flat[ $ctl['key'] ] = $ctl;
					}
				}
			}
		}
		return $flat;
	}

	public static function defaults() {
		$out = array();
		foreach ( self::controls() as $k => $ctl ) {
			$out[ $k ] = $ctl['default'];
		}
		return $out;
	}

	public static function var_name( $key ) {
		return '--smb-' . str_replace( '_', '-', $key );
	}

	private static function option_keys( $ctl ) {
		if ( 'font' === $ctl['type'] ) {
			return array_map( 'strval', array_keys( self::fonts() ) );
		}
		return array_map( 'strval', array_keys( $ctl['options'] ) );
	}

	/* ---------------------------------------------------------------
	 * Sanitising
	 * ------------------------------------------------------------- */

	public static function sanitize( $in ) {
		$out = array();
		$in  = (array) $in;
		foreach ( self::controls() as $k => $ctl ) {
			$raw = isset( $in[ $k ] ) && ! is_array( $in[ $k ] ) ? (string) $in[ $k ] : null;
			switch ( $ctl['type'] ) {
				case 'color':
					$c         = null === $raw ? '' : sanitize_hex_color( $raw );
					$out[ $k ] = $c ? $c : $ctl['default'];
					break;
				case 'size':
					if ( null === $raw || ! is_numeric( $raw ) ) {
						$out[ $k ] = $ctl['default'];
					} else {
						$out[ $k ] = max( $ctl['min'], min( $ctl['max'], round( (float) $raw, 2 ) ) );
					}
					break;
				default: // select, font
					$out[ $k ] = ( null !== $raw && in_array( $raw, self::option_keys( $ctl ), true ) ) ? $raw : $ctl['default'];
			}
		}
		return $out;
	}

	/* ---------------------------------------------------------------
	 * Output
	 * ------------------------------------------------------------- */

	private static function num( $v ) {
		return rtrim( rtrim( number_format( (float) $v, 2, '.', '' ), '0' ), '.' );
	}

	/** Value of one control as it goes into the CSS variable. */
	public static function css_value( $ctl, $value ) {
		if ( 'size' === $ctl['type'] ) {
			return self::num( $value ) . 'px';
		}
		return (string) $value;
	}

	/** CSS rule that defines every variable. $values = saved settings (already merged with defaults). */
	public static function css( $values ) {
		$decl = '';
		foreach ( self::controls() as $k => $ctl ) {
			$v = isset( $values[ $k ] ) ? $values[ $k ] : $ctl['default'];
			$decl .= self::var_name( $k ) . ':' . self::css_value( $ctl, $v ) . ';';
		}
		return '.smb-wrap{' . $decl . '}';
	}

	/** Google Fonts URL for the fonts that are actually in use ('' if none). */
	public static function font_url( $values ) {
		$fonts  = self::fonts();
		$params = array();
		foreach ( self::controls() as $k => $ctl ) {
			if ( 'font' !== $ctl['type'] ) {
				continue;
			}
			$stack = isset( $values[ $k ] ) ? $values[ $k ] : 'inherit';
			if ( isset( $fonts[ $stack ] ) && '' !== $fonts[ $stack ]['google'] ) {
				$params[ $fonts[ $stack ]['google'] ] = 'family=' . str_replace( ' ', '+', $fonts[ $stack ]['google'] ) . ':wght@' . $fonts[ $stack ]['wght'];
			}
		}
		if ( empty( $params ) ) {
			return '';
		}
		return 'https://fonts.googleapis.com/css2?' . implode( '&', $params ) . '&display=swap';
	}

	/* ---------------------------------------------------------------
	 * Admin controls
	 * ------------------------------------------------------------- */

	public static function render_tab( $tab_id, $values, $option_name ) {
		$tabs = self::tabs();
		if ( ! isset( $tabs[ $tab_id ] ) ) {
			return;
		}
		$first = true;
		foreach ( $tabs[ $tab_id ]['sections'] as $title => $controls ) {
			echo '<details class="smb-acc"' . ( $first ? ' open' : '' ) . '><summary>' . esc_html( $title ) . '</summary><div class="smb-acc-body">';
			foreach ( $controls as $ctl ) {
				self::render_control( $ctl, $values, $option_name );
			}
			echo '</div></details>';
			$first = false;
		}
	}

	private static function render_control( $ctl, $values, $option_name ) {
		$k     = $ctl['key'];
		$v     = isset( $values[ $k ] ) ? $values[ $k ] : $ctl['default'];
		$name  = $option_name . '[' . $k . ']';
		$common = sprintf(
			' name="%s" data-var="%s" data-type="%s" data-default="%s"',
			esc_attr( $name ),
			esc_attr( self::var_name( $k ) ),
			esc_attr( 'size' === $ctl['type'] ? 'size' : ( 'color' === $ctl['type'] ? 'color' : 'select' ) ),
			esc_attr( $ctl['default'] )
		);

		echo '<div class="smb-ctl smb-ctl--' . esc_attr( $ctl['type'] ) . '"><label>' . esc_html( $ctl['label'] ) . '</label><div class="smb-ctl-field">';

		if ( 'color' === $ctl['type'] ) {
			echo '<input type="text" class="smb-color"' . $common . ' value="' . esc_attr( $v ) . '" data-default-color="' . esc_attr( $ctl['default'] ) . '"/>'; // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( 'size' === $ctl['type'] ) {
			printf(
				'<input type="range" class="smb-range" min="%1$s" max="%2$s" step="%3$s" value="%4$s"/><input type="number" class="smb-num"%5$s data-unit="px" min="%1$s" max="%2$s" step="%3$s" value="%4$s"/><span class="smb-unit">px</span>',
				esc_attr( $ctl['min'] ),
				esc_attr( $ctl['max'] ),
				esc_attr( $ctl['step'] ),
				esc_attr( self::num( $v ) ),
				$common // phpcs:ignore WordPress.Security.EscapeOutput
			);
		} else {
			echo '<select' . $common . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( 'font' === $ctl['type'] ) {
				foreach ( self::fonts() as $stack => $f ) {
					printf(
						'<option value="%s"%s data-google="%s" data-wght="%s">%s</option>',
						esc_attr( $stack ),
						selected( (string) $v, (string) $stack, false ),
						esc_attr( $f['google'] ),
						esc_attr( $f['wght'] ),
						esc_html( $f['label'] )
					);
				}
			} else {
				foreach ( $ctl['options'] as $val => $label ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( (string) $v, (string) $val, false ), esc_html( $label ) );
				}
			}
			echo '</select>';
		}

		echo '</div></div>';
	}
}
