<?php
/**
 * Pack offers: the "Custom" card (the shopper picks a quantity and, for variable products, a variation).
 *
 * Printed by templates/packs.php after the pack cards. It reuses the pack card classes, so the global
 * Pack styling (colours, borders, radius, fonts, selected state, badge, prices) applies to it as well.
 * Until the card is selected only the heading and the badge show: the hint, dropdown, counter, price,
 * old price, "Save" pill and "You save" line are hidden by CSS and are kept up to date by
 * assets/js/pack-custom.js.
 *
 * Override in your theme: yourtheme/smart-bundles/pack-custom.php
 *
 * @var array  $custom title, subtitle, badge, variable (bool), options (variable products: id, label, attrs,
 *                     disabled, regular, unit, max), selected (variation id), start (regular, unit, max), demo
 * @var string $slide  ' smb-slide' inside a carousel, otherwise ''
 */
defined( 'ABSPATH' ) || exit;

$slide    = isset( $slide ) ? $slide : '';
$variable = ! empty( $custom['variable'] );
$demo     = ! empty( $custom['demo'] ); // the settings-page preview shows the card already selected
$start    = $custom['start'];
$subtitle = isset( $custom['subtitle'] ) ? (string) $custom['subtitle'] : '';
$total    = (float) $start['unit']; // quantity 1 to begin with
$reg      = (float) $start['regular'];
$save     = max( 0, $reg - $total );
$pct      = $reg > 0 ? $save / $reg * 100 : 0;
$max      = (int) $start['max'];
$uid      = 'smb-custom-' . wp_unique_id();
?>
<label class="smb-pack smb-pack--custom<?php echo esc_attr( $slide ); ?><?php echo $demo ? ' is-selected' : ''; ?>"
	data-variable="<?php echo $variable ? '1' : '0'; ?>"
	<?php if ( ! $variable ) : ?>
		data-regular="<?php echo esc_attr( $start['regular'] ); ?>"
		data-unit="<?php echo esc_attr( $start['unit'] ); ?>"
		data-max="<?php echo esc_attr( $max ); ?>"
	<?php endif; ?>>

	<?php if ( '' !== $custom['badge'] ) : ?>
		<span class="smb-badge smb-badge--pack"><?php echo esc_html( $custom['badge'] ); ?></span>
	<?php endif; ?>

	<input type="radio" class="smb-radio" name="smb_pack" value="custom" aria-labelledby="<?php echo esc_attr( $uid ); ?>-title"<?php checked( $demo ); ?>/>

	<span class="smb-pack-main">
		<span class="smb-pack-top">
			<span class="smb-pack-label" id="<?php echo esc_attr( $uid ); ?>-title"><?php echo esc_html( $custom['title'] ); ?></span>
			<span class="smb-pill<?php echo $save > 0 ? '' : ' is-hidden'; ?>"><?php esc_html_e( 'Save', 'smart-bundles' ); ?> <?php echo wp_kses_post( wc_price( $save ) ); ?></span>
		</span>

		<?php if ( '' !== $subtitle ) : ?>
			<span class="smb-custom-hint"><?php echo esc_html( $subtitle ); ?></span>
		<?php endif; ?>

		<span class="smb-custom-controls">
			<?php if ( $variable ) : ?>
				<span class="smb-custom-select">
					<select class="smb-var-select smb-custom-var" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: card heading */ __( 'Choose an option for %s', 'smart-bundles' ), $custom['title'] ) ); ?>">
						<?php foreach ( $custom['options'] as $o ) : ?>
							<option value="<?php echo esc_attr( $o['id'] ); ?>"
								data-regular="<?php echo esc_attr( $o['regular'] ); ?>"
								data-unit="<?php echo esc_attr( $o['unit'] ); ?>"
								data-max="<?php echo esc_attr( $o['max'] ); ?>"
								data-attrs="<?php echo esc_attr( wp_json_encode( $o['attrs'] ) ); ?>"
								<?php selected( (int) $o['id'], (int) $custom['selected'] ); ?>
								<?php disabled( ! empty( $o['disabled'] ) ); ?>><?php echo esc_html( $o['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</span>
			<?php endif; ?>

			<span class="smb-custom-stepper">
				<button type="button" class="smb-custom-minus" aria-label="<?php esc_attr_e( 'Decrease quantity', 'smart-bundles' ); ?>" disabled>&minus;</button>
				<input type="number" class="smb-custom-qty" value="1" min="1" step="1" inputmode="numeric"<?php echo $max > 0 ? ' max="' . esc_attr( $max ) . '"' : ''; ?> aria-label="<?php esc_attr_e( 'Quantity', 'smart-bundles' ); ?>"/>
				<button type="button" class="smb-custom-plus" aria-label="<?php esc_attr_e( 'Increase quantity', 'smart-bundles' ); ?>"<?php echo ( $max > 0 && $max <= 1 ) ? ' disabled' : ''; ?>>+</button>
			</span>
		</span>

		<span class="smb-pack-sub<?php echo $save > 0 ? '' : ' is-hidden'; ?>"><?php esc_html_e( 'You save', 'smart-bundles' ); ?> <?php echo esc_html( SMB_Frontend::pct_text( $pct ) ); ?>%</span>
	</span>

	<span class="smb-pack-price">
		<span class="smb-price"><?php echo wp_kses_post( wc_price( $total ) ); ?></span>
		<del class="smb-strike<?php echo $save > 0 ? '' : ' is-hidden'; ?>"><?php echo wp_kses_post( wc_price( $reg ) ); ?></del>
	</span>
</label>
