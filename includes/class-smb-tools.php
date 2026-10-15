<?php
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce > Smart Bundles Tools: export and import the offer settings of every product
 * (pack offers, bundle offers, bought-together) as one JSON file.
 *
 * What is moved: the per-product settings only (the _smb_* product meta). The global style and
 * behaviour settings stay where they are.
 *
 * Files must work between sites, so product ids are never used. Every product, and every product
 * inside an offer, is written as a reference (SKU + slug; variations by SKU or attributes) and
 * looked up again on import: first by SKU, then by slug.
 *
 * File layout (schema 1):
 *   { "plugin": "smart-bundles", "schema": 1, "plugin_version": "...", "exported_at": "...", "site": "...",
 *     "products": [ { "ref": { "sku", "slug", "name" }, "config": { "packs_on", ..., "items": [ ... ] } } ] }
 */
class SMB_Tools {

	const SCHEMA   = 1;
	const MAX_SIZE = 5242880; // 5 MB
	const MAX_ROWS = 5000;

	/** Simple per-product keys (stored as _smb_<key>) and how each one is cleaned. */
	private static $simple = array(
		'packs_on'     => 'yesno',
		'packs_title'  => 'text',
		'pack_mode'    => 'pack_mode',
		'fbt_mode'     => 'fbt_mode',
		'fbt_title'    => 'text',
		'pick_from'    => 'pick_from',
		'limit'        => 'limit',
		'layout'       => 'layout',
		'bundle_on'    => 'yesno',
		'bundle_title' => 'text',
		'bundle_name'  => 'text',
		'bundle_badge' => 'text',
		'bundle_type'  => 'discount_type',
		'bundle_value' => 'number',
	);

	/** Keys that hold lists. */
	private static $lists = array( 'packs', 'ranges', 'items', 'bundle_items', 'cats', 'tags' );

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_smb_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_smb_import', array( __CLASS__, 'handle_import' ) );
	}

	public static function menu() {
		add_submenu_page( 'woocommerce', __( 'Smart Bundles Tools', 'smart-bundles' ), __( 'Smart Bundles Tools', 'smart-bundles' ), 'manage_woocommerce', 'smart-bundles-tools', array( __CLASS__, 'page' ) );
	}

	/* ---------------------------------------------------------------
	 * Page
	 * ------------------------------------------------------------- */

	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'smart-bundles' ) );
		}
		$report = get_transient( 'smb_tools_' . get_current_user_id() );
		if ( $report ) {
			delete_transient( 'smb_tools_' . get_current_user_id() );
		}
		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=smb_export' ), 'smb_export' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Smart Bundles Tools', 'smart-bundles' ); ?></h1>

			<?php if ( $report ) : ?>
				<?php self::print_report( $report ); ?>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Export', 'smart-bundles' ); ?></h2>
			<p><?php esc_html_e( 'Download the offer settings of every product (pack offers, bundle offers, bought together) as a JSON file. Style settings are not included.', 'smart-bundles' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export all offers', 'smart-bundles' ); ?></a></p>

			<hr />

			<h2><?php esc_html_e( 'Import', 'smart-bundles' ); ?></h2>
			<p><?php esc_html_e( 'Upload a file exported from Smart Bundles. Products are matched by SKU first, then by slug. The offer settings of every matched product are replaced by the ones in the file. Products that cannot be found are skipped.', 'smart-bundles' ); ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="smb_import" />
				<?php wp_nonce_field( 'smb_import', 'smb_import_nonce' ); ?>
				<p><input type="file" name="smb_file" accept=".json,application/json" required /></p>
				<p><label><input type="checkbox" name="smb_dry_run" value="1" checked /> <?php esc_html_e( 'Check only: show what would happen without changing anything', 'smart-bundles' ); ?></label></p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'smart-bundles' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	private static function print_report( $r ) {
		$class = ! empty( $r['fatal'] ) ? 'notice-error' : ( empty( $r['warnings'] ) ? 'notice-success' : 'notice-warning' );
		echo '<div class="notice ' . esc_attr( $class ) . '"><p><strong>';
		if ( ! empty( $r['fatal'] ) ) {
			esc_html_e( 'Nothing was imported.', 'smart-bundles' );
		} elseif ( ! empty( $r['dry_run'] ) ) {
			/* translators: 1: products that would be imported, 2: products that would be skipped */
			echo esc_html( sprintf( __( 'Check finished, nothing was changed. %1$d product(s) can be imported, %2$d would be skipped.', 'smart-bundles' ), $r['imported'], $r['skipped'] ) );
		} else {
			/* translators: 1: products imported, 2: products skipped */
			echo esc_html( sprintf( __( 'Import finished. %1$d product(s) imported, %2$d skipped.', 'smart-bundles' ), $r['imported'], $r['skipped'] ) );
		}
		echo '</strong></p>';
		$lines = array_merge( (array) ( $r['errors'] ?? array() ), (array) ( $r['warnings'] ?? array() ) );
		if ( $lines ) {
			echo '<ul style="list-style:disc;margin-left:20px">';
			foreach ( array_slice( $lines, 0, 100 ) as $l ) {
				echo '<li>' . esc_html( $l ) . '</li>';
			}
			if ( count( $lines ) > 100 ) {
				/* translators: %d: number of messages not shown */
				echo '<li>' . esc_html( sprintf( __( '... and %d more.', 'smart-bundles' ), count( $lines ) - 100 ) ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	/* ---------------------------------------------------------------
	 * Export
	 * ------------------------------------------------------------- */

	public static function handle_export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'smart-bundles' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'smb_export' );

		$json = wp_json_encode( self::build_export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="smart-bundles-export-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file download
		exit;
	}

	/**
	 * @return array the whole export document.
	 */
	public static function build_export() {
		global $wpdb;

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key LIKE %s AND p.post_type = 'product' AND p.post_status NOT IN ( 'trash', 'auto-draft' ) ORDER BY pm.post_id ASC",
				$wpdb->esc_like( '_smb_' ) . '%'
			)
		);

		$products = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( (int) $id );
			if ( ! $product ) {
				continue;
			}
			$config = self::read_config( (int) $id );
			if ( empty( $config ) ) {
				continue;
			}
			$products[] = array(
				'ref'    => self::product_ref( $product ),
				'config' => $config,
			);
		}

		return array(
			'plugin'         => 'smart-bundles',
			'schema'         => self::SCHEMA,
			'plugin_version' => SMB_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'site'           => home_url(),
			'products'       => $products,
		);
	}

	private static function product_ref( $product ) {
		return array(
			'sku'  => (string) $product->get_sku(),
			'slug' => (string) $product->get_slug(),
			'name' => (string) $product->get_name(),
		);
	}

	private static function variation_ref( $variation ) {
		return array(
			'sku'        => (string) $variation->get_sku(),
			'attributes' => (object) array_map( 'strval', (array) $variation->get_attributes() ),
		);
	}

	/** One product's settings with ids replaced by references. */
	private static function read_config( $id ) {
		$out = array();

		foreach ( array_keys( self::$simple ) as $key ) {
			if ( metadata_exists( 'post', $id, '_smb_' . $key ) ) {
				$out[ $key ] = get_post_meta( $id, '_smb_' . $key, true );
			}
		}
		foreach ( array( 'packs', 'ranges' ) as $key ) {
			if ( metadata_exists( 'post', $id, '_smb_' . $key ) ) {
				$out[ $key ] = array_values( (array) get_post_meta( $id, '_smb_' . $key, true ) );
			}
		}
		foreach ( array( 'items', 'bundle_items' ) as $key ) {
			if ( ! metadata_exists( 'post', $id, '_smb_' . $key ) ) {
				continue;
			}
			$rows = array();
			foreach ( (array) get_post_meta( $id, '_smb_' . $key, true ) as $r ) {
				$pid = is_array( $r ) ? absint( $r['id'] ?? 0 ) : absint( $r );
				$vid = is_array( $r ) ? absint( $r['variation_id'] ?? 0 ) : 0;
				$p   = $pid ? wc_get_product( $pid ) : false;
				if ( ! $p ) {
					continue;
				}
				$row = array( 'ref' => self::product_ref( $p ), 'variation' => null );
				if ( $vid ) {
					$v = wc_get_product( $vid );
					if ( $v && $v->is_type( 'variation' ) && (int) $v->get_parent_id() === $pid ) {
						$row['variation'] = self::variation_ref( $v );
					} else {
						continue;
					}
				}
				if ( 'items' === $key ) {
					$row['badge'] = is_array( $r ) && isset( $r['badge'] ) ? (string) $r['badge'] : '';
				}
				$rows[] = $row;
			}
			$out[ $key ] = $rows;
		}
		foreach ( array( 'cats' => 'product_cat', 'tags' => 'product_tag' ) as $key => $tax ) {
			if ( ! metadata_exists( 'post', $id, '_smb_' . $key ) ) {
				continue;
			}
			$slugs = array();
			foreach ( array_filter( array_map( 'absint', (array) get_post_meta( $id, '_smb_' . $key, true ) ) ) as $tid ) {
				$t = get_term( $tid, $tax );
				if ( $t && ! is_wp_error( $t ) ) {
					$slugs[] = $t->slug;
				}
			}
			$out[ $key ] = $slugs;
		}

		return apply_filters( 'smb_export_product_config', $out, $id );
	}

	/* ---------------------------------------------------------------
	 * Import
	 * ------------------------------------------------------------- */

	public static function handle_import() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'smart-bundles' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'smb_import', 'smb_import_nonce' );

		$dry    = ! empty( $_POST['smb_dry_run'] );
		$report = array( 'fatal' => true, 'dry_run' => $dry, 'imported' => 0, 'skipped' => 0, 'errors' => array(), 'warnings' => array() );

		$file = isset( $_FILES['smb_file'] ) ? $_FILES['smb_file'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $file || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$report['errors'][] = __( 'The file could not be uploaded.', 'smart-bundles' );
		} elseif ( (int) $file['size'] > self::MAX_SIZE ) {
			$report['errors'][] = __( 'The file is too large (5 MB maximum).', 'smart-bundles' );
		} else {
			$raw = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$report = self::import_json( (string) $raw, $dry );
		}

		set_transient( 'smb_tools_' . get_current_user_id(), $report, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=smart-bundles-tools' ) );
		exit;
	}

	/**
	 * Validate the whole file, and only then write.
	 *
	 * @param string $raw JSON text.
	 * @param bool   $dry True = check only.
	 * @return array report: fatal, dry_run, imported, skipped, errors[], warnings[]
	 */
	public static function import_json( $raw, $dry = false ) {
		$report = array( 'fatal' => false, 'dry_run' => $dry, 'imported' => 0, 'skipped' => 0, 'errors' => array(), 'warnings' => array() );

		$errors = self::validate_document( $raw, $data );
		if ( $errors ) {
			$report['fatal']  = true;
			$report['errors'] = $errors;
			return $report;
		}

		$plan = array();
		foreach ( $data['products'] as $n => $entry ) {
			$label = self::ref_label( $entry['ref'], $n );
			$id    = self::find_product( $entry['ref'] );
			if ( ! $id ) {
				/* translators: %s: product name or SKU */
				$report['warnings'][] = sprintf( __( 'Skipped %s: no product with that SKU or slug on this site.', 'smart-bundles' ), $label );
				$report['skipped']++;
				continue;
			}
			if ( ! current_user_can( 'edit_post', $id ) ) {
				/* translators: %s: product name or SKU */
				$report['warnings'][] = sprintf( __( 'Skipped %s: you are not allowed to edit it.', 'smart-bundles' ), $label );
				$report['skipped']++;
				continue;
			}
			$warnings = array();
			$values   = self::clean_config( $entry['config'], $id, $label, $warnings );
			foreach ( $warnings as $w ) {
				$report['warnings'][] = $w;
			}
			$plan[ $id ] = $values;
			$report['imported']++;
		}

		if ( ! $dry ) {
			foreach ( $plan as $id => $values ) {
				self::write_config( $id, $values );
			}
			if ( $plan ) {
				SMB_Source::bump_cache();
			}
		}
		return $report;
	}

	/**
	 * Structure check. Returns a list of problems; an empty list means the document is usable.
	 * Nothing is looked up or written here.
	 *
	 * @param string $raw  JSON text.
	 * @param array  $data (out) decoded document.
	 */
	public static function validate_document( $raw, &$data = null ) {
		$errors = array();
		$data   = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			return array( __( 'This is not a valid JSON file.', 'smart-bundles' ) );
		}
		if ( ( $data['plugin'] ?? '' ) !== 'smart-bundles' ) {
			return array( __( 'This file was not exported by Smart Bundles.', 'smart-bundles' ) );
		}
		$schema = isset( $data['schema'] ) ? (int) $data['schema'] : 0;
		if ( $schema < 1 ) {
			return array( __( 'The file has no version header.', 'smart-bundles' ) );
		}
		if ( $schema > self::SCHEMA ) {
			/* translators: 1: file version, 2: supported version */
			return array( sprintf( __( 'The file uses format version %1$d, but this plugin only understands up to version %2$d. Update Smart Bundles on this site first.', 'smart-bundles' ), $schema, self::SCHEMA ) );
		}
		if ( ! isset( $data['products'] ) || ! is_array( $data['products'] ) ) {
			return array( __( 'The file has no product list.', 'smart-bundles' ) );
		}
		if ( count( $data['products'] ) > self::MAX_ROWS ) {
			/* translators: %d: maximum products */
			return array( sprintf( __( 'The file has too many products (%d maximum).', 'smart-bundles' ), self::MAX_ROWS ) );
		}

		$known = array_merge( array_keys( self::$simple ), self::$lists );
		foreach ( $data['products'] as $n => $entry ) {
			$pos = $n + 1;
			if ( ! is_array( $entry ) || ! isset( $entry['ref'], $entry['config'] ) || ! is_array( $entry['ref'] ) || ! is_array( $entry['config'] ) ) {
				/* translators: %d: position in the file */
				$errors[] = sprintf( __( 'Product #%d: needs a "ref" and a "config".', 'smart-bundles' ), $pos );
				continue;
			}
			if ( '' === self::str( $entry['ref'], 'sku' ) && '' === self::str( $entry['ref'], 'slug' ) ) {
				/* translators: %d: position in the file */
				$errors[] = sprintf( __( 'Product #%d: the reference needs a SKU or a slug.', 'smart-bundles' ), $pos );
			}
			foreach ( self::$lists as $k ) {
				if ( isset( $entry['config'][ $k ] ) && ! is_array( $entry['config'][ $k ] ) ) {
					/* translators: 1: position in the file, 2: setting name */
					$errors[] = sprintf( __( 'Product #%1$d: "%2$s" must be a list.', 'smart-bundles' ), $pos, $k );
				}
			}
			foreach ( array( 'items', 'bundle_items' ) as $k ) {
				foreach ( (array) ( $entry['config'][ $k ] ?? array() ) as $row ) {
					if ( ! is_array( $row ) || ! isset( $row['ref'] ) || ! is_array( $row['ref'] ) || ( '' === self::str( $row['ref'], 'sku' ) && '' === self::str( $row['ref'], 'slug' ) ) ) {
						/* translators: 1: position in the file, 2: setting name */
						$errors[] = sprintf( __( 'Product #%1$d: every row of "%2$s" needs a product reference.', 'smart-bundles' ), $pos, $k );
						break;
					}
				}
			}
			foreach ( array_keys( $entry['config'] ) as $k ) {
				if ( ! in_array( $k, $known, true ) ) {
					/* translators: 1: position in the file, 2: setting name */
					$errors[] = sprintf( __( 'Product #%1$d: unknown setting "%2$s".', 'smart-bundles' ), $pos, $k );
				}
			}
			if ( count( $errors ) >= 50 ) {
				$errors[] = __( 'Too many problems, stopped checking.', 'smart-bundles' );
				break;
			}
		}
		return $errors;
	}

	private static function str( $arr, $key ) {
		return isset( $arr[ $key ] ) && is_scalar( $arr[ $key ] ) ? trim( (string) $arr[ $key ] ) : '';
	}

	private static function ref_label( $ref, $n = 0 ) {
		$bits = array_filter( array( self::str( $ref, 'name' ), self::str( $ref, 'sku' ) ? 'SKU ' . self::str( $ref, 'sku' ) : '', self::str( $ref, 'slug' ) ) );
		return $bits ? '"' . implode( ' / ', $bits ) . '"' : '#' . ( $n + 1 );
	}

	/** SKU first, then slug. Only simple and variable products count. */
	private static function find_product( $ref ) {
		$id  = 0;
		$sku = self::str( $ref, 'sku' );
		if ( '' !== $sku ) {
			$id = (int) wc_get_product_id_by_sku( $sku );
			$p  = $id ? wc_get_product( $id ) : false;
			if ( $p && $p->is_type( 'variation' ) ) {
				$id = 0; // a variation's SKU is not a product reference
			}
		}
		if ( ! $id ) {
			$slug = sanitize_title( self::str( $ref, 'slug' ) );
			if ( '' !== $slug ) {
				$post = get_page_by_path( $slug, OBJECT, 'product' );
				$id   = $post ? (int) $post->ID : 0;
			}
		}
		$p = $id ? wc_get_product( $id ) : false;
		return ( $p && ( $p->is_type( 'simple' ) || $p->is_type( 'variable' ) ) ) ? (int) $p->get_id() : 0;
	}

	/** Find the variation named by $spec among the children of $parent_id. */
	private static function find_variation( $parent_id, $spec ) {
		if ( ! is_array( $spec ) ) {
			return 0;
		}
		$parent = wc_get_product( $parent_id );
		if ( ! $parent || ! $parent->is_type( 'variable' ) ) {
			return 0;
		}
		$sku = self::str( $spec, 'sku' );
		if ( '' !== $sku ) {
			$vid = (int) wc_get_product_id_by_sku( $sku );
			$v   = $vid ? wc_get_product( $vid ) : false;
			if ( $v && $v->is_type( 'variation' ) && (int) $v->get_parent_id() === (int) $parent_id ) {
				return $vid;
			}
		}
		$want = isset( $spec['attributes'] ) && is_array( $spec['attributes'] ) ? array_map( 'strval', $spec['attributes'] ) : array();
		if ( $want ) {
			ksort( $want );
			foreach ( $parent->get_children() as $vid ) {
				$v = wc_get_product( $vid );
				if ( ! $v ) {
					continue;
				}
				$have = array_map( 'strval', (array) $v->get_attributes() );
				ksort( $have );
				if ( $have === $want ) {
					return (int) $vid;
				}
			}
		}
		return 0;
	}

	/**
	 * Turn one product's settings from the file into values that are safe to store.
	 * Anything that cannot be used is dropped and reported in $warnings; nothing is guessed.
	 */
	private static function clean_config( $cfg, $product_id, $label, &$warnings ) {
		$out = array();
		$say = function ( $msg ) use ( $label, &$warnings ) {
			$warnings[] = $label . ': ' . $msg;
		};

		foreach ( self::$simple as $key => $kind ) {
			if ( ! array_key_exists( $key, $cfg ) ) {
				continue;
			}
			$v = $cfg[ $key ];
			if ( ! is_scalar( $v ) && null !== $v ) {
				$say( sprintf( /* translators: %s: setting name */ __( 'ignored "%s" (not a plain value).', 'smart-bundles' ), $key ) );
				continue;
			}
			$v     = (string) $v;
			$clean = self::clean_value( $kind, $v );
			if ( null === $clean ) {
				$say( sprintf( /* translators: 1: setting name, 2: value */ __( 'ignored "%1$s": "%2$s" is not allowed.', 'smart-bundles' ), $key, mb_substr( $v, 0, 40 ) ) );
				continue;
			}
			$out[ $key ] = $clean;
		}

		if ( isset( $cfg['packs'] ) ) {
			$rows = array();
			foreach ( $cfg['packs'] as $r ) {
				if ( ! is_array( $r ) ) {
					continue;
				}
				$rows[] = array(
					'label'   => sanitize_text_field( self::str( $r, 'label' ) ),
					'qty'     => isset( $r['qty'] ) ? absint( $r['qty'] ) : 0,
					'type'    => ( isset( $r['type'] ) && 'fixed' === $r['type'] ) ? 'fixed' : 'percent',
					'value'   => isset( $r['value'] ) ? max( 0, (float) $r['value'] ) : 0,
					'badge'   => sanitize_text_field( self::str( $r, 'badge' ) ),
					'default' => ! empty( $r['default'] ),
				);
			}
			$out['packs'] = array_values( array_filter( $rows, function ( $r ) {
				return $r['qty'] >= 1;
			} ) );
		}

		if ( isset( $cfg['ranges'] ) ) {
			$raw = array();
			foreach ( $cfg['ranges'] as $r ) {
				if ( is_array( $r ) ) {
					$to    = self::str( $r, 'to' );
					$raw[] = array(
						'from'  => self::str( $r, 'from' ),
						'to'    => '0' === $to ? '' : $to, // stored as 0 = "and up"; the editor's rules call that empty
						'value' => self::str( $r, 'value' ),
						'label' => sanitize_text_field( self::str( $r, 'label' ) ),
					);
				}
			}
			$problems = SMB_Pricing::validate_ranges( $raw );
			if ( $problems ) {
				// Same rule as the product editor: overlapping ranges are never stored.
				$say( sprintf( /* translators: %s: problems */ __( 'quantity ranges not imported (%s).', 'smart-bundles' ), implode( ' ', $problems ) ) );
				unset( $out['ranges'] );
				if ( isset( $out['pack_mode'] ) && 'ranges' === $out['pack_mode'] ) {
					$out['pack_mode'] = 'cards';
				}
			} else {
				$out['ranges'] = SMB_Pricing::clean_ranges( $raw );
				if ( isset( $out['pack_mode'] ) && 'ranges' === $out['pack_mode'] && empty( $out['ranges'] ) ) {
					$out['pack_mode'] = 'cards';
				}
			}
		}

		foreach ( array( 'items', 'bundle_items' ) as $key ) {
			if ( ! isset( $cfg[ $key ] ) ) {
				continue;
			}
			$rows = array();
			$seen = array();
			foreach ( $cfg[ $key ] as $r ) {
				$id = self::find_product( $r['ref'] );
				$nm = self::ref_label( $r['ref'] );
				if ( ! $id ) {
					$say( sprintf( /* translators: %s: product */ __( 'item %s not found on this site, left out.', 'smart-bundles' ), $nm ) );
					continue;
				}
				if ( $id === (int) $product_id ) {
					continue; // a product cannot be part of its own offer
				}
				$vid = 0;
				if ( ! empty( $r['variation'] ) ) {
					$vid = self::find_variation( $id, $r['variation'] );
					if ( ! $vid ) {
						$say( sprintf( /* translators: %s: product */ __( 'the chosen variation of %s was not found, item left out.', 'smart-bundles' ), $nm ) );
						continue;
					}
				}
				$k = $id . ':' . $vid;
				if ( isset( $seen[ $k ] ) ) {
					continue;
				}
				$seen[ $k ] = true;
				$row        = array( 'id' => $id, 'variation_id' => $vid );
				if ( 'items' === $key ) {
					$row['badge'] = sanitize_text_field( self::str( $r, 'badge' ) );
				}
				$rows[] = $row;
			}
			$out[ $key ] = $rows;
		}

		foreach ( array( 'cats' => 'product_cat', 'tags' => 'product_tag' ) as $key => $tax ) {
			if ( ! isset( $cfg[ $key ] ) ) {
				continue;
			}
			$ids = array();
			foreach ( $cfg[ $key ] as $slug ) {
				$t = is_scalar( $slug ) ? get_term_by( 'slug', sanitize_title( (string) $slug ), $tax ) : false;
				if ( $t ) {
					$ids[] = (int) $t->term_id;
				} else {
					$say( sprintf( /* translators: %s: term slug */ __( 'category or tag "%s" does not exist here, left out.', 'smart-bundles' ), is_scalar( $slug ) ? (string) $slug : '?' ) );
				}
			}
			$out[ $key ] = $ids;
		}

		return $out;
	}

	/** @return mixed|null null = not allowed. */
	private static function clean_value( $kind, $v ) {
		switch ( $kind ) {
			case 'yesno':
				return in_array( $v, array( 'yes', 'no' ), true ) ? $v : null;
			case 'text':
				return sanitize_text_field( $v );
			case 'pack_mode':
				return in_array( $v, array( 'cards', 'ranges' ), true ) ? $v : null;
			case 'fbt_mode':
				return in_array( $v, array( 'inherit', 'on', 'off' ), true ) ? $v : null;
			case 'pick_from':
				return ( 'inherit' === $v || isset( SMB_Settings::pick_from_options()[ $v ] ) ) ? $v : null;
			case 'limit':
				return '' === $v ? '' : ( ctype_digit( $v ) ? max( 1, min( 12, (int) $v ) ) : null );
			case 'layout':
				return in_array( $v, array( 'list', 'grid', 'carousel' ), true ) ? $v : null;
			case 'discount_type':
				return in_array( $v, array( 'percent', 'fixed' ), true ) ? $v : null;
			case 'number':
				return is_numeric( $v ) ? max( 0.0, (float) $v ) : null;
		}
		return null;
	}

	/** Replace all of a product's Smart Bundles settings with the imported ones. */
	private static function write_config( $id, $values ) {
		foreach ( array_merge( array_keys( self::$simple ), self::$lists ) as $key ) {
			delete_post_meta( $id, '_smb_' . $key );
		}
		delete_post_meta( $id, '_smb_manual' ); // old 1.0 list; the imported items replace it
		foreach ( $values as $key => $v ) {
			update_post_meta( $id, '_smb_' . $key, $v );
		}
		do_action( 'smb_config_imported', $id, $values );
	}
}
