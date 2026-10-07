<?php
defined( 'ABSPATH' ) || exit;

/**
 * "Smart Bundles" box on the product edit screen.
 */
class SMB_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ) );
		add_action( 'save_post_product', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SMB_FILE ), function ( $links ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=smart-bundles' ) ) . '">Settings</a>' );
			return $links;
		} );
	}

	public static function assets( $hook ) {
		global $post;
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $post || 'product' !== $post->post_type ) {
			return;
		}
		wp_enqueue_style( 'smb-admin', SMB_URL . 'assets/css/admin.css', array(), SMB_VERSION );
		wp_enqueue_script( 'smb-admin', SMB_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), SMB_VERSION, true );
	}

	public static function add_box() {
		add_meta_box( 'smb_box', 'Smart Bundles', array( __CLASS__, 'render' ), 'product', 'normal', 'default' );
	}

	private static function terms_select( $name, $taxonomy, $selected ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		echo '<select multiple class="wc-enhanced-select" style="width:100%" name="' . esc_attr( $name ) . '[]" data-placeholder="' . esc_attr__( 'Choose…', 'smart-bundles' ) . '">';
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				printf( '<option value="%d"%s>%s</option>', (int) $t->term_id, selected( in_array( (int) $t->term_id, array_map( 'intval', $selected ), true ), true, false ), esc_html( $t->name ) );
			}
		}
		echo '</select>';
	}

	/* ---------------------------------------------------------------
	 * Pack rows
	 * ------------------------------------------------------------- */

	private static function row( $i, $r ) {
		$r = wp_parse_args( $r, array( 'label' => '', 'qty' => 1, 'type' => 'percent', 'value' => 0, 'badge' => '', 'default' => '' ) );
		?>
		<tr class="smb-pack-row">
			<td><input type="text" name="smb_packs[<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( $r['label'] ); ?>" placeholder="2 pack"/></td>
			<td><input type="number" min="1" step="1" name="smb_packs[<?php echo esc_attr( $i ); ?>][qty]" value="<?php echo esc_attr( $r['qty'] ); ?>" class="small-text"/></td>
			<td>
				<select name="smb_packs[<?php echo esc_attr( $i ); ?>][type]">
					<option value="percent" <?php selected( $r['type'], 'percent' ); ?>>% off</option>
					<option value="fixed" <?php selected( $r['type'], 'fixed' ); ?>>Fixed pack price</option>
				</select>
			</td>
			<td><input type="number" min="0" step="any" name="smb_packs[<?php echo esc_attr( $i ); ?>][value]" value="<?php echo esc_attr( $r['value'] ); ?>" class="small-text"/></td>
			<td><input type="text" name="smb_packs[<?php echo esc_attr( $i ); ?>][badge]" value="<?php echo esc_attr( $r['badge'] ); ?>" placeholder="Most Popular"/></td>
			<td style="text-align:center"><input type="radio" name="smb_pack_default" value="<?php echo esc_attr( $i ); ?>" <?php checked( ! empty( $r['default'] ) ); ?>/></td>
			<td><button type="button" class="button smb-remove" aria-label="Remove">&times;</button></td>
		</tr>
		<?php
	}

	/* ---------------------------------------------------------------
	 * Picked-product rows (name [+ badge] + remove, drag to re-order).
	 * Used for both the "Frequently bought together" table (with a badge column) and the
	 * "Bundle offer" table (no badge column — the bundle has one badge for the whole deal).
	 * ------------------------------------------------------------- */

	private static function item_row( $i, $id, $thumb_html, $name, $name_prefix, $badge = null ) {
		?>
		<tr class="smb-item-row">
			<td class="smb-col-handle" title="Drag to re-order"><span class="dashicons dashicons-menu"></span></td>
			<td class="smb-col-product">
				<input type="hidden" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $i ); ?>][id]" value="<?php echo esc_attr( $id ); ?>"/>
				<span class="smb-thumb"><?php echo wp_kses_post( $thumb_html ); ?></span>
				<span class="smb-pname"><?php echo esc_html( $name ); ?></span>
			</td>
			<?php if ( null !== $badge ) : ?>
				<td class="smb-col-badge"><input type="text" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $i ); ?>][badge]" value="<?php echo esc_attr( $badge ); ?>" placeholder="e.g. New Launched"/></td>
			<?php endif; ?>
			<td class="smb-col-remove"><button type="button" class="button smb-remove-item" aria-label="Remove">&times;</button></td>
		</tr>
		<?php
	}

	/**
	 * A self-contained "search a product -> sortable table of picked products" control.
	 * $badges = true adds the per-row badge column (used by Frequently bought together);
	 * false leaves it out (used by Bundle offer, which has one badge for the whole deal).
	 */
	private static function item_picker( $group, $exclude_id, $rows, $badges ) {
		?>
		<div class="smb-item-picker" data-group="<?php echo esc_attr( $group ); ?>">
			<p>
				<select class="wc-product-search smb-product-search" style="width:100%;max-width:560px" data-placeholder="Search for a product to add…" data-action="woocommerce_json_search_products" data-exclude_type="variable" data-exclude="<?php echo esc_attr( $exclude_id ); ?>" data-allow_clear="true">
					<option></option>
				</select>
			</p>
			<table class="widefat smb-itemtable">
				<thead><tr><th class="smb-col-handle"></th><th>Product</th><?php if ( $badges ) : ?><th>Badge (optional)</th><?php endif; ?><th class="smb-col-remove"></th></tr></thead>
				<tbody>
					<?php
					$n = 0;
					foreach ( $rows as $it ) {
						$p = wc_get_product( $it['id'] );
						if ( ! $p ) {
							continue;
						}
						self::item_row( $n++, $it['id'], $p->get_image( array( 36, 36 ) ), $p->get_name(), $group . '_items', $badges ? $it['badge'] : null );
					}
					?>
				</tbody>
			</table>
			<p class="smb-empty description"<?php echo $n ? ' style="display:none"' : ''; ?>>No products picked yet. Search above to add some<?php echo 'fbt' === $group ? ', or leave the list empty and let the plugin fill it automatically' : ''; ?>.</p>
			<span class="smb-items-next" data-next="<?php echo esc_attr( $n ); ?>"></span>
			<script type="text/html" class="smb-item-tpl"><?php self::item_row( '__i__', '__id__', '', '', $group . '_items', $badges ? '' : null ); ?></script>
		</div>
		<?php
	}

	public static function render( $post ) {
		wp_nonce_field( 'smb_save', 'smb_nonce' );
		$c = SMB_Source::config( $post->ID );

		$packs = get_post_meta( $post->ID, '_smb_packs', true );
		$packs = is_array( $packs ) ? $packs : array();
		if ( empty( $packs ) ) {
			$packs = $c['packs']; // the built-in "1 pack / 2 pack, no discount" default, so the admin sees (and can edit) what's live.
		}
		$packs_on = metadata_exists( 'post', $post->ID, '_smb_packs_on' ) ? ( 'yes' === get_post_meta( $post->ID, '_smb_packs_on', true ) ) : true;
		$pick  = get_post_meta( $post->ID, '_smb_pick_from', true );
		$pick  = $pick ? $pick : 'inherit';
		$limit = get_post_meta( $post->ID, '_smb_limit', true );
		$g     = SMB_Settings::all();
		$bc    = SMB_Source::bundle_config( $post->ID );
		// Show exactly what was saved (not the inherited fallback) so an empty field stays empty in the editor.
		$bc_name  = get_post_meta( $post->ID, '_smb_bundle_name', true );
		$bc_badge = get_post_meta( $post->ID, '_smb_bundle_badge', true );
		?>
		<div class="smb-admin">

			<h3 class="smb-pack-section-h">1. Pack offers <small>(Buy 1 / Buy 2 pack…)</small></h3>
			<p>
				<label><input type="checkbox" class="smb-packs-on" name="smb_packs_on" value="yes" <?php checked( $packs_on ); ?>/> Show pack offers on this product</label>
				<span class="description">On by default for every product — "1 pack" and "2 pack" below, no discount, 1 pack pre-selected. Add a discount or more tiers any time.</span>
			</p>
			<p class="smb-packs-bundle-note description" style="display:none">Switched off automatically because the Bundle offer below is on for this product. Turn the bundle off to use pack offers here instead.</p>
			<p>
				<label><strong>Heading for this product</strong></label><br/>
				<input type="text" class="smb-wide" name="smb_packs_title" value="<?php echo esc_attr( $c['packs_title'] ); ?>" placeholder="<?php echo esc_attr( $g['packs_title'] ); ?>"/>
				<span class="description">Leave empty to use the heading from the Smart Bundles settings page.</span>
			</p>
			<table class="widefat smb-packs">
				<thead><tr><th>Label</th><th>Quantity</th><th>Discount</th><th>Value</th><th>Badge (optional)</th><th>Selected by default</th><th></th></tr></thead>
				<tbody>
					<?php
					$i = 0;
					foreach ( $packs as $r ) {
						self::row( $i++, $r );
					}
					?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button smb-add" data-next="<?php echo esc_attr( $i ); ?>">+ Add pack</button>
				<span class="description">"% off" is taken off the product's current price. "Fixed pack price" is the total for the whole pack (e.g. 2 pack = 2565). For variable products use "% off".</span>
			</p>
			<script type="text/html" id="smb-row-tpl"><?php self::row( '__i__', array() ); ?></script>

			<hr/>

			<h3>2. Frequently bought together</h3>
			<div class="smb-grid">
				<p>
					<label><strong>Show on this product</strong></label><br/>
					<select name="smb_fbt_mode">
						<option value="inherit" <?php selected( $c['fbt_mode'], 'inherit' ); ?>>Use global setting (<?php echo 'yes' === $g['fbt_global'] ? 'on' : 'off'; ?>)</option>
						<option value="on" <?php selected( $c['fbt_mode'], 'on' ); ?>>Always show</option>
						<option value="off" <?php selected( $c['fbt_mode'], 'off' ); ?>>Hide</option>
					</select>
				</p>
				<p>
					<label><strong>Max products</strong></label><br/>
					<input type="number" min="1" max="12" name="smb_limit" value="<?php echo esc_attr( $limit ); ?>" placeholder="<?php echo esc_attr( $g['fbt_limit'] ); ?>" class="small-text"/>
				</p>
			</div>
			<p>
				<label><strong>Heading for this product</strong></label><br/>
				<input type="text" class="smb-wide" name="smb_fbt_title" value="<?php echo esc_attr( $c['fbt_title'] ); ?>" placeholder="<?php echo esc_attr( $g['fbt_title'] ); ?>"/>
				<span class="description">Leave empty to use the heading from the Smart Bundles settings page.</span>
			</p>

			<p><label><strong>Products picked for this product</strong></label> <span class="description">(these always come first, in this order)</span></p>
			<?php self::item_picker( 'fbt', $post->ID, $c['items'], true ); ?>

			<div class="smb-grid">
				<p>
					<label><strong>Then fill the remaining slots from</strong></label><br/>
					<select name="smb_pick_from" class="smb-pick">
						<option value="inherit" <?php selected( $pick, 'inherit' ); ?>>Use global setting</option>
						<?php foreach ( SMB_Settings::pick_from_options() as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $pick, $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			</div>

			<p class="smb-if-cats"><label><strong>Categories</strong></label><br/><?php self::terms_select( 'smb_cats', 'product_cat', $c['cats'] ); ?></p>
			<p class="smb-if-tags"><label><strong>Tags</strong></label><br/><?php self::terms_select( 'smb_tags', 'product_tag', $c['tags'] ); ?></p>
			<p class="description">Only simple, purchasable products are suggested. Add-ons are sold at their normal price. Automatically filled products show no badge.</p>

			<hr/>

			<h3>3. Bundle offer <small>(this product + a fixed set of others, sold as one deal)</small></h3>
			<p>
				<label><input type="checkbox" class="smb-bundle-on" name="smb_bundle_on" value="yes" <?php checked( get_post_meta( $post->ID, '_smb_bundle_on', true ), 'yes' ); ?>/> Show a bundle offer on this product</label>
			</p>
			<p class="description">This product is always the first item in the bundle. Pick the others below; the discount is applied to the combined total, not to each product separately. Pack offers (section 1) switch off automatically on this product while a bundle is active here.</p>

			<div class="smb-grid">
				<p>
					<label><strong>Heading for this product</strong></label><br/>
					<input type="text" class="smb-wide" name="smb_bundle_title" value="<?php echo esc_attr( $bc['title'] ); ?>" placeholder="<?php echo esc_attr( $g['bundle_title'] ); ?>"/>
					<span class="description">Leave empty to use the heading from the Smart Bundles settings page.</span>
				</p>
				<p>
					<label><strong>Badge for this bundle</strong></label><br/>
					<input type="text" name="smb_bundle_badge" value="<?php echo esc_attr( $bc_badge ); ?>" placeholder="e.g. Save 20%"/>
				</p>
			</div>
			<p>
				<label><strong>Headline next to the toggle</strong></label><br/>
				<input type="text" class="smb-wide" name="smb_bundle_name" value="<?php echo esc_attr( $bc_name ); ?>" placeholder="<?php echo esc_attr( $g['bundle_name_default'] ); ?>"/>
				<span class="description">e.g. "Complete the bundle to unlock savings". Leave empty to use the default headline from the settings page.</span>
			</p>

			<div class="smb-grid">
				<p>
					<label><strong>Discount</strong></label><br/>
					<select name="smb_bundle_type">
						<option value="percent" <?php selected( $bc['type'], 'percent' ); ?>>% off the combined price</option>
						<option value="fixed" <?php selected( $bc['type'], 'fixed' ); ?>>Fixed total price for the whole bundle</option>
					</select>
				</p>
				<p>
					<label><strong>Value</strong></label><br/>
					<input type="number" min="0" step="any" name="smb_bundle_value" value="<?php echo esc_attr( $bc['value'] ); ?>" class="small-text"/>
				</p>
			</div>

			<p><label><strong>Other products in this bundle</strong></label></p>
			<?php self::item_picker( 'bundle', $post->ID, array_map( function ( $id ) { return array( 'id' => $id ); }, $bc['items'] ), false ); ?>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------
	 * Save
	 * ------------------------------------------------------------- */

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['smb_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['smb_nonce'] ) ), 'smb_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Packs
		$rows    = array();
		$default = isset( $_POST['smb_pack_default'] ) ? absint( $_POST['smb_pack_default'] ) : -1;
		if ( isset( $_POST['smb_packs'] ) && is_array( $_POST['smb_packs'] ) ) {
			foreach ( wp_unslash( $_POST['smb_packs'] ) as $i => $r ) {
				$qty = isset( $r['qty'] ) ? absint( $r['qty'] ) : 0;
				if ( $qty < 1 ) {
					continue;
				}
				$rows[] = array(
					'label'   => sanitize_text_field( isset( $r['label'] ) ? $r['label'] : '' ),
					'qty'     => $qty,
					'type'    => ( isset( $r['type'] ) && 'fixed' === $r['type'] ) ? 'fixed' : 'percent',
					'value'   => max( 0, (float) ( isset( $r['value'] ) ? $r['value'] : 0 ) ),
					'badge'   => sanitize_text_field( isset( $r['badge'] ) ? $r['badge'] : '' ),
					'default' => ( (int) $i === $default ),
				);
			}
		}
		update_post_meta( $post_id, '_smb_packs', $rows );
		update_post_meta( $post_id, '_smb_packs_on', isset( $_POST['smb_packs_on'] ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_smb_packs_title', isset( $_POST['smb_packs_title'] ) ? sanitize_text_field( wp_unslash( $_POST['smb_packs_title'] ) ) : '' );

		// Bought together
		$mode = isset( $_POST['smb_fbt_mode'] ) ? sanitize_key( wp_unslash( $_POST['smb_fbt_mode'] ) ) : 'inherit';
		update_post_meta( $post_id, '_smb_fbt_mode', in_array( $mode, array( 'inherit', 'on', 'off' ), true ) ? $mode : 'inherit' );
		update_post_meta( $post_id, '_smb_fbt_title', isset( $_POST['smb_fbt_title'] ) ? sanitize_text_field( wp_unslash( $_POST['smb_fbt_title'] ) ) : '' );

		$pick = isset( $_POST['smb_pick_from'] ) ? sanitize_key( wp_unslash( $_POST['smb_pick_from'] ) ) : 'inherit';
		update_post_meta( $post_id, '_smb_pick_from', ( 'inherit' === $pick || isset( SMB_Settings::pick_from_options()[ $pick ] ) ) ? $pick : 'inherit' );

		// Picked products, in the order they were arranged (the order of the submitted rows).
		update_post_meta( $post_id, '_smb_items', self::parsed_items( 'fbt_items', $post_id, true ) );
		delete_post_meta( $post_id, '_smb_manual' ); // old version 1.0 list, now stored in _smb_items

		$ints = function ( $key ) {
			return isset( $_POST[ $key ] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST[ $key ] ) ) ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		};
		update_post_meta( $post_id, '_smb_cats', $ints( 'smb_cats' ) );
		update_post_meta( $post_id, '_smb_tags', $ints( 'smb_tags' ) );

		$limit = isset( $_POST['smb_limit'] ) ? sanitize_text_field( wp_unslash( $_POST['smb_limit'] ) ) : '';
		update_post_meta( $post_id, '_smb_limit', '' === $limit ? '' : max( 1, min( 12, absint( $limit ) ) ) );

		// Bundle offer
		update_post_meta( $post_id, '_smb_bundle_on', isset( $_POST['smb_bundle_on'] ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_smb_bundle_title', isset( $_POST['smb_bundle_title'] ) ? sanitize_text_field( wp_unslash( $_POST['smb_bundle_title'] ) ) : '' );
		update_post_meta( $post_id, '_smb_bundle_name', isset( $_POST['smb_bundle_name'] ) ? sanitize_text_field( wp_unslash( $_POST['smb_bundle_name'] ) ) : '' );
		update_post_meta( $post_id, '_smb_bundle_badge', isset( $_POST['smb_bundle_badge'] ) ? sanitize_text_field( wp_unslash( $_POST['smb_bundle_badge'] ) ) : '' );
		update_post_meta( $post_id, '_smb_bundle_type', ( isset( $_POST['smb_bundle_type'] ) && 'fixed' === $_POST['smb_bundle_type'] ) ? 'fixed' : 'percent' );
		update_post_meta( $post_id, '_smb_bundle_value', max( 0, (float) ( $_POST['smb_bundle_value'] ?? 0 ) ) );

		$bundle_items = array();
		foreach ( self::parsed_items( 'bundle_items', $post_id, false ) as $r ) {
			$bundle_items[] = array( 'id' => $r['id'] );
		}
		update_post_meta( $post_id, '_smb_bundle_items', $bundle_items );
	}

	/**
	 * Read one picked-products table from $_POST: unique ids, not the product itself,
	 * in the order the admin arranged them.
	 */
	private static function parsed_items( $field, $post_id, $with_badge ) {
		$items = array();
		$seen  = array();
		if ( isset( $_POST[ $field ] ) && is_array( $_POST[ $field ] ) ) {
			foreach ( wp_unslash( $_POST[ $field ] ) as $r ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$id = isset( $r['id'] ) ? absint( $r['id'] ) : 0;
				if ( ! $id || $id === (int) $post_id || isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;
				$row         = array( 'id' => $id );
				if ( $with_badge ) {
					$row['badge'] = sanitize_text_field( isset( $r['badge'] ) ? $r['badge'] : '' );
				}
				$items[] = $row;
			}
		}
		return $items;
	}
}
