<?php
/**
 * Frequently bought together list (product page and cart page).
 *
 * Override in your theme: yourtheme/smart-bundles/fbt.php
 *
 * @var WC_Product|null $product
 * @var array           $items        each: id, name, url, image, price, regular, badge, checked
 * @var string          $title
 * @var string          $context       'product' or 'cart'
 * @var string          $button_text   cart page button label
 * @var bool            $show_summary  show the "N selected · +Rs.X" line
 * @var bool            $demo          true inside the settings-page preview
 */
defined( 'ABSPATH' ) || exit;

$context      = isset( $context ) ? $context : 'product';
$button_text  = isset( $button_text ) ? $button_text : '';
$show_summary = ! isset( $show_summary ) || $show_summary;
$demo         = ! empty( $demo );
?>
<div class="smb-wrap smb-fbt-wrap" data-smb="fbt" data-context="<?php echo esc_attr( $context ); ?>">

	<?php if ( '' !== $title ) : ?>
		<div class="smb-title"><span><?php echo esc_html( $title ); ?></span></div>
	<?php endif; ?>

	<div class="smb-items">
		<?php foreach ( $items as $it ) : ?>
			<label class="smb-item<?php echo $it['checked'] ? ' is-selected' : ''; ?>"
				data-id="<?php echo esc_attr( $it['id'] ); ?>"
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
