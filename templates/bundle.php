<?php
/**
 * Bundle offer: the main product + a fixed set of other products, sold as one deal
 * at one combined price.
 *
 * Override in your theme: yourtheme/smart-bundles/bundle.php
 *
 * @var string $title     section heading
 * @var string $name      headline next to the toggle (e.g. "Complete the bundle to unlock savings")
 * @var string $badge     badge text for the whole bundle ('' = none)
 * @var array  $rows      each: id, name, image, unit, regular (the first row is the product itself)
 * @var float  $total     combined price
 * @var float  $reg_total combined regular price
 * @var float  $save      reg_total - total (0 or more)
 * @var string $save_pct_text
 * @var bool   $demo      true inside the settings-page preview
 */
defined( 'ABSPATH' ) || exit;

$demo  = ! empty( $demo );
$type  = isset( $type ) ? $type : 'percent'; // how the discount is worked out: percent | fixed
$value = isset( $value ) ? $value : 0;
?>
<div class="smb-wrap smb-offer-wrap" data-smb="bundle" data-type="<?php echo esc_attr( $type ); ?>" data-value="<?php echo esc_attr( $value ); ?>">

	<?php if ( '' !== $title ) : ?>
		<div class="smb-title"><span><?php echo esc_html( $title ); ?></span></div>
	<?php endif; ?>

	<div class="smb-bundle-card<?php echo $demo ? ' is-selected' : ''; ?>">

		<?php if ( '' !== $badge ) : ?>
			<span class="smb-badge smb-badge--bundle"><?php echo esc_html( $badge ); ?></span>
		<?php endif; ?>

		<label class="smb-bundle-toggle">
			<input type="checkbox" class="smb-radio smb-bundle-check" <?php checked( $demo ); ?>/>
			<span class="smb-bundle-main">
				<span class="smb-bundle-name"><?php echo esc_html( $name ); ?></span>
			</span>
			<span class="smb-bundle-price">
				<span class="smb-price"><?php echo wp_kses_post( wc_price( $total ) ); ?></span>
				<del class="smb-strike smb-bundle-strike<?php echo $save > 0 ? '' : ' is-hidden'; ?>"><?php echo wp_kses_post( wc_price( $reg_total ) ); ?></del>
			</span>
		</label>

		<p class="smb-bundle-save<?php echo $save > 0 ? '' : ' is-hidden'; ?>"><?php /* translators: %s: amount saved */ echo wp_kses_post( sprintf( esc_html__( 'Save %s!', 'smart-bundles' ), wc_price( $save ) ) ); ?></p>

		<div class="smb-bundle-items">
			<?php foreach ( $rows as $i => $r ) : ?>
				<div class="smb-bundle-item"
					data-key="<?php echo esc_attr( isset( $r['key'] ) ? $r['key'] : $i ); ?>"
					data-id="<?php echo esc_attr( isset( $r['id'] ) ? $r['id'] : 0 ); ?>"
					data-current="<?php echo esc_attr( isset( $r['current'] ) ? $r['current'] : $r['unit'] ); ?>"
					data-regular="<?php echo esc_attr( $r['regular'] ); ?>">
					<?php if ( $i > 0 ) : ?><span class="smb-bundle-plus" aria-hidden="true">+</span><?php endif; ?>
					<span class="smb-bundle-item-img"><?php echo wp_kses_post( $r['image'] ); ?></span>
					<span class="smb-bundle-item-name">
						<?php echo esc_html( $r['name'] ); ?>
						<?php if ( ! empty( $r['options'] ) ) : ?>
							<select class="smb-var-select" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name */ __( 'Choose a variation of %s', 'smart-bundles' ), $r['name'] ) ); ?>">
								<?php foreach ( $r['options'] as $o ) : ?>
									<option value="<?php echo esc_attr( $o['id'] ); ?>"
										data-current="<?php echo esc_attr( $o['current'] ); ?>"
										data-regular="<?php echo esc_attr( $o['regular'] ); ?>"
										<?php selected( (int) $o['id'], (int) $r['variation_id'] ); ?>><?php echo esc_html( $o['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</span>
					<span class="smb-bundle-item-price">
						<span class="smb-price"><?php echo wp_kses_post( wc_price( $r['unit'] ) ); ?></span>
						<del class="smb-strike<?php echo $r['regular'] > $r['unit'] ? '' : ' is-hidden'; ?>"><?php echo wp_kses_post( wc_price( $r['regular'] ) ); ?></del>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
