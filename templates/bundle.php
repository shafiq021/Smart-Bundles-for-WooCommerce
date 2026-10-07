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

$demo = ! empty( $demo );
?>
<div class="smb-wrap smb-offer-wrap" data-smb="bundle">

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
				<?php if ( $save > 0 ) : ?>
					<del class="smb-strike"><?php echo wp_kses_post( wc_price( $reg_total ) ); ?></del>
				<?php endif; ?>
			</span>
		</label>

		<?php if ( $save > 0 ) : ?>
			<p class="smb-bundle-save"><?php /* translators: %s: amount saved */ echo wp_kses_post( sprintf( esc_html__( 'Save %s!', 'smart-bundles' ), wc_price( $save ) ) ); ?></p>
		<?php endif; ?>

		<div class="smb-bundle-items">
			<?php foreach ( $rows as $i => $r ) : ?>
				<div class="smb-bundle-item">
					<?php if ( $i > 0 ) : ?><span class="smb-bundle-plus" aria-hidden="true">+</span><?php endif; ?>
					<span class="smb-bundle-item-img"><?php echo wp_kses_post( $r['image'] ); ?></span>
					<span class="smb-bundle-item-name"><?php echo esc_html( $r['name'] ); ?></span>
					<span class="smb-bundle-item-price">
						<span class="smb-price"><?php echo wp_kses_post( wc_price( $r['unit'] ) ); ?></span>
						<?php if ( $r['regular'] > $r['unit'] ) : ?>
							<del class="smb-strike"><?php echo wp_kses_post( wc_price( $r['regular'] ) ); ?></del>
						<?php endif; ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
