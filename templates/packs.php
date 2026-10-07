<?php
/**
 * Pack offers (Buy 1 / Buy 2 ...).
 *
 * Override in your theme: yourtheme/smart-bundles/packs.php
 *
 * @var WC_Product|null $product
 * @var array           $tiers   each: label, qty, type, value, badge, default, total, reg_total, save, save_pct, save_pct_text, fixed_total
 * @var string          $title
 * @var bool            $is_var
 * @var bool            $hide_qty
 */
defined( 'ABSPATH' ) || exit;

$has_default = false;
foreach ( $tiers as $t ) {
	if ( ! empty( $t['default'] ) ) {
		$has_default = true;
	}
}
?>
<div class="smb-wrap smb-packs-wrap"
	data-smb="packs"
	data-variable="<?php echo ! empty( $is_var ) ? '1' : '0'; ?>"
	data-hide-qty="<?php echo ! empty( $hide_qty ) ? '1' : '0'; ?>">

	<?php if ( '' !== $title ) : ?>
		<div class="smb-title"><span><?php echo esc_html( $title ); ?></span></div>
	<?php endif; ?>

	<div class="smb-packs" role="radiogroup">
		<?php foreach ( $tiers as $i => $t ) :
			$checked = $has_default ? ! empty( $t['default'] ) : ( 0 === $i );
			?>
			<label class="smb-pack<?php echo $checked ? ' is-selected' : ''; ?>"
				data-qty="<?php echo esc_attr( $t['qty'] ); ?>"
				data-type="<?php echo esc_attr( $t['type'] ); ?>"
				data-value="<?php echo esc_attr( $t['value'] ); ?>"
				data-fixed="<?php echo esc_attr( $t['fixed_total'] ); ?>">

				<?php if ( '' !== $t['badge'] ) : ?>
					<span class="smb-badge smb-badge--pack"><?php echo esc_html( $t['badge'] ); ?></span>
				<?php endif; ?>

				<input type="radio" class="smb-radio" name="smb_pack" value="<?php echo esc_attr( $t['qty'] ); ?>" <?php checked( $checked ); ?>/>

				<span class="smb-pack-main">
					<span class="smb-pack-top">
						<span class="smb-pack-label"><?php echo esc_html( $t['label'] ); ?></span>
						<span class="smb-pill<?php echo $t['save'] > 0 ? '' : ' is-hidden'; ?>"><?php esc_html_e( 'Save', 'smart-bundles' ); ?> <?php echo wp_kses_post( wc_price( $t['save'] ) ); ?></span>
					</span>
					<span class="smb-pack-sub<?php echo $t['save'] > 0 ? '' : ' is-hidden'; ?>"><?php esc_html_e( 'You save', 'smart-bundles' ); ?> <?php echo esc_html( $t['save_pct_text'] ); ?>%</span>
				</span>

				<span class="smb-pack-price">
					<span class="smb-price"><?php echo wp_kses_post( wc_price( $t['total'] ) ); ?></span>
					<del class="smb-strike<?php echo $t['save'] > 0 ? '' : ' is-hidden'; ?>"><?php echo wp_kses_post( wc_price( $t['reg_total'] ) ); ?></del>
				</span>
			</label>
		<?php endforeach; ?>
	</div>
</div>
