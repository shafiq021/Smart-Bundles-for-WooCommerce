<?php
/**
 * Pack offers, quantity-range style: "Buy 3-5 and get 10% off".
 *
 * Override in your theme: yourtheme/smart-bundles/ranges.php
 *
 * @var WC_Product|null $product
 * @var array           $ranges  each: from, to (0 = and up), value (% off), label, text, unit, regular, pct_text
 * @var string          $title
 * @var bool            $is_var  variable product: prices follow the chosen variation
 * @var float           $current display price of one unit (lowest variation price for variable products)
 * @var float           $regular display regular price of one unit
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="smb-wrap smb-ranges-wrap"
	data-smb="ranges"
	data-variable="<?php echo ! empty( $is_var ) ? '1' : '0'; ?>"
	data-current="<?php echo esc_attr( $current ); ?>"
	data-regular="<?php echo esc_attr( $regular ); ?>">

	<?php if ( '' !== $title ) : ?>
		<div class="smb-title"><span><?php echo esc_html( $title ); ?></span></div>
	<?php endif; ?>

	<div class="smb-range-list" role="list">
		<?php foreach ( $ranges as $r ) : ?>
			<div class="smb-range" role="listitem"
				data-from="<?php echo esc_attr( $r['from'] ); ?>"
				data-to="<?php echo esc_attr( $r['to'] ); ?>"
				data-value="<?php echo esc_attr( $r['value'] ); ?>">

				<span class="smb-range-main">
					<span class="smb-range-label"><?php echo esc_html( $r['text'] ); ?></span>
					<?php if ( $r['value'] > 0 ) : ?>
						<span class="smb-pill"><?php /* translators: %s: percent off, e.g. 10 */ echo esc_html( sprintf( __( '%s%% off', 'smart-bundles' ), $r['pct_text'] ) ); ?></span>
					<?php endif; ?>
				</span>

				<span class="smb-range-price">
					<span class="smb-price"><?php echo wp_kses_post( wc_price( $r['unit'] ) ); ?></span>
					<del class="smb-strike<?php echo $r['regular'] > $r['unit'] ? '' : ' is-hidden'; ?>"><?php echo wp_kses_post( wc_price( $r['regular'] ) ); ?></del>
					<span class="smb-range-each"><?php esc_html_e( 'each', 'smart-bundles' ); ?></span>
				</span>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="smb-ranges-summary is-hidden" aria-live="polite"></div>
	<p class="smb-ranges-note"><?php esc_html_e( 'The discount is based on the total quantity of this product in your cart.', 'smart-bundles' ); ?></p>
</div>
