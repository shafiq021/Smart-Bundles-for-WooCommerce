<?php
/**
 * Frequently bought together list (product page and cart page).
 *
 * Override in your theme: yourtheme/smart-bundles/fbt.php
 *
 * @var WC_Product|null $product
 * @var array           $items        each: id, name, url, image, price, regular, badge, checked
 *                                    and, for variable products, variation_id + options [ id, label, price, regular ]
 * @var string          $title
 * @var string          $context       'product' or 'cart'
 * @var string          $button_text   cart page button label
 * @var bool            $show_summary  show the "N selected · +Rs.X" line
 * @var bool            $demo          true inside the settings-page preview
 * @var bool            $show_save_amt show the "Save Rs.X" pill under items that are on sale (default: the Bundle Badges setting)
 * @var bool            $show_save_pct show the "You save N%" text under items that are on sale (default: the Bundle Badges setting)
 * @var string          $layout  list | grid | carousel (default list)
 */
defined( 'ABSPATH' ) || exit;

$context       = isset( $context ) ? $context : 'product';
$button_text   = isset( $button_text ) ? $button_text : '';
$show_summary  = ! isset( $show_summary ) || $show_summary;
$demo          = ! empty( $demo );
$show_save_amt = isset( $show_save_amt ) ? (bool) $show_save_amt : SMB_Badges::show_save_amt();
$show_save_pct = isset( $show_save_pct ) ? (bool) $show_save_pct : SMB_Badges::show_save_pct();
$layout = SMB_Source::clean_layout( isset( $layout ) ? $layout : 'list' ); // list | grid | carousel
$slide  = 'carousel' === $layout ? ' smb-slide' : '';
$track  = 'carousel' === $layout ? ' smb-carousel' : '';
?>
<div class="smb-wrap smb-fbt-wrap smb-layout--<?php echo esc_attr( $layout ); ?>" data-layout="<?php echo esc_attr( $layout ); ?>" data-smb="fbt" data-context="<?php echo esc_attr( $context ); ?>">

	<?php if ( '' !== $title ) : ?>
		<div class="smb-title"><span><?php echo esc_html( $title ); ?></span></div>
	<?php endif; ?>

	<div class="smb-items<?php echo esc_attr( $track ); ?>">
		<?php
		foreach ( $items as $it ) :
			$save     = max( 0, (float) $it['regular'] - (float) $it['price'] );
			$save_pct = (float) $it['regular'] > 0 ? $save / (float) $it['regular'] * 100 : 0;
			?>
			<label class="smb-item<?php echo esc_attr( $slide ); ?><?php echo $it['checked'] ? ' is-selected' : ''; ?>"
				data-id="<?php echo esc_attr( $it['id'] ); ?>"
				data-vid="<?php echo esc_attr( ! empty( $it['variation_id'] ) ? $it['variation_id'] : 0 ); ?>"
				data-price="<?php echo esc_attr( $it['price'] ); ?>">

				<?php if ( '' !== $it['badge'] ) : ?>
					<span class="smb-badge smb-badge--item"><?php echo esc_html( $it['badge'] ); ?></span>
				<?php endif; ?>

				<input type="checkbox" class="smb-check" value="<?php echo esc_attr( $it['id'] ); ?>" <?php checked( $it['checked'] ); ?>/>

				<span class="smb-item-img"><?php echo wp_kses_post( $it['image'] ); ?></span>
				<span class="smb-item-qty">1&times;</span>

				<span class="smb-item-main">
					<span class="smb-item-name">
						<?php if ( $it['url'] ) : ?>
							<a href="<?php echo esc_url( $it['url'] ); ?>"><?php echo esc_html( $it['name'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $it['name'] ); ?>
						<?php endif; ?>
					</span>
					<?php if ( ! empty( $it['options'] ) ) : ?>
						<select class="smb-var-select" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name */ __( 'Choose a variation of %s', 'smart-bundles' ), $it['name'] ) ); ?>">
							<?php foreach ( $it['options'] as $o ) : ?>
								<option value="<?php echo esc_attr( $o['id'] ); ?>"
									data-price="<?php echo esc_attr( $o['price'] ); ?>"
									data-regular="<?php echo esc_attr( $o['regular'] ); ?>"
									<?php selected( (int) $o['id'], (int) $it['variation_id'] ); ?>><?php echo esc_html( $o['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
					<?php if ( $show_save_amt || $show_save_pct ) : ?>
						<span class="smb-item-savings<?php echo $save > 0 ? '' : ' is-hidden'; ?>">
							<?php if ( $show_save_amt ) : ?>
								<span class="smb-pill smb-item-save"><?php esc_html_e( 'Save', 'smart-bundles' ); ?> <?php echo wp_kses_post( wc_price( $save ) ); ?></span>
							<?php endif; ?>
							<?php if ( $show_save_pct ) : ?>
								<span class="smb-pack-sub smb-item-sub"><?php esc_html_e( 'You save', 'smart-bundles' ); ?> <?php echo esc_html( SMB_Frontend::pct_text( $save_pct ) ); ?>%</span>
							<?php endif; ?>
						</span>
					<?php endif; ?>
				</span>

				<span class="smb-item-price">
					<span class="smb-price"><?php echo wp_kses_post( wc_price( $it['price'] ) ); ?></span>
					<?php if ( $it['regular'] > $it['price'] ) : ?>
						<del class="smb-strike"><?php echo wp_kses_post( wc_price( $it['regular'] ) ); ?></del>
					<?php endif; ?>
				</span>
			</label>
		<?php endforeach; ?>
	</div>

	<?php if ( $show_summary ) : ?>
		<div class="smb-fbt-summary<?php echo $demo ? '' : ' is-hidden'; ?>"><?php echo $demo ? '2 selected &middot; +' . wp_kses_post( wc_price( 2149 ) ) : ''; ?></div>
	<?php endif; ?>

	<?php if ( 'cart' === $context ) : ?>
		<div class="smb-cart-actions">
			<button type="button" class="smb-cart-btn"<?php echo $demo ? '' : ' disabled'; ?>><?php echo esc_html( $button_text ); ?></button>
		</div>
	<?php endif; ?>
</div>
