<?php
defined( 'ABSPATH' ) || exit;

/**
 * Read-only REST API: /wp-json/smart-bundles/v1/{packs|bundles|fbt}[/<product id>]
 *
 * Collections list the products that currently show that kind of offer (paged, searchable) and are
 * for editors (edit_products); the Gutenberg blocks use them for their product picker. A single
 * offer is public for published products, because the same data is already printed on the shop
 * page, and needs edit rights for drafts / private products.
 */
class SMB_REST {

	const NS = 'smart-bundles/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		foreach ( array( 'packs', 'bundles', 'fbt' ) as $type ) {
			register_rest_route( self::NS, '/' . $type, array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( $request ) use ( $type ) {
					return self::collection( $request, $type );
				},
				'permission_callback' => array( __CLASS__, 'can_list' ),
				'args'                => array(
					'search'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'sanitize_callback' => 'absint' ),
					'per_page' => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 50, 'sanitize_callback' => 'absint' ),
				),
			) );
			register_rest_route( self::NS, '/' . $type . '/(?P<id>\d+)', array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( $request ) use ( $type ) {
					return self::single( $request, $type );
				},
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'args'                => array(
					'id'   => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				),
			) );
		}
	}

	public static function can_list() {
		return current_user_can( 'edit_products' );
	}

	public static function can_read( $request ) {
		$post = get_post( absint( $request['id'] ) );
		if ( ! $post || 'product' !== $post->post_type ) {
			return true; // the callback answers 404
		}
		return 'publish' === $post->post_status || current_user_can( 'edit_post', $post->ID );
	}

	/* ------------------------------------------------------------- */

	private static function meta_query( $type ) {
		if ( 'bundles' === $type ) {
			return array( array( 'key' => '_smb_bundle_on', 'value' => 'yes' ) );
		}
		if ( 'fbt' === $type ) {
			$on = array( array( 'key' => '_smb_fbt_mode', 'value' => 'on' ) );
			if ( 'yes' === SMB_Settings::get( 'fbt_global' ) ) {
				$on = array(
					'relation' => 'OR',
					array( 'key' => '_smb_fbt_mode', 'compare' => 'NOT EXISTS' ),
					array( 'key' => '_smb_fbt_mode', 'value' => array( 'off' ), 'compare' => 'NOT IN' ),
					array( 'key' => '_smb_fbt_mode', 'value' => 'on' ),
				);
			}
			return array( $on );
		}
		// Packs are on unless switched off, and a bundle takes over from them.
		return array(
			'relation' => 'AND',
			array(
				'relation' => 'OR',
				array( 'key' => '_smb_packs_on', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_smb_packs_on', 'value' => 'yes' ),
			),
			array(
				'relation' => 'OR',
				array( 'key' => '_smb_bundle_on', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_smb_bundle_on', 'value' => 'yes', 'compare' => '!=' ),
			),
		);
	}

	public static function collection( $request, $type ) {
		$q    = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $request['per_page'],
			'paged'          => (int) $request['page'],
			's'              => (string) $request['search'],
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => self::meta_query( $type ), // phpcs:ignore WordPress.DB.SlowDBQuery
			'tax_query'      => array( array( 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => array( 'simple', 'variable' ) ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );

		$out = array();
		foreach ( $q->posts as $post ) {
			$row = self::offer( $type, wc_get_product( $post->ID ) );
			if ( $row ) {
				$out[] = $row;
			}
		}

		$response = rest_ensure_response( $out );
		$response->header( 'X-WP-Total', (int) $q->found_posts );
		$response->header( 'X-WP-TotalPages', (int) $q->max_num_pages );
		return $response;
	}

	public static function single( $request, $type ) {
		$product = wc_get_product( absint( $request['id'] ) );
		$row     = $product ? self::offer( $type, $product ) : null;
		if ( ! $row ) {
			return new WP_Error( 'smb_no_offer', __( 'This product has no offer of that kind.', 'smart-bundles' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $row );
	}

	/**
	 * @return array|null null when the product does not show that offer.
	 */
	public static function offer( $type, $product ) {
		if ( ! $product || ! ( $product->is_type( 'simple' ) || $product->is_type( 'variable' ) ) ) {
			return null;
		}
		$id   = $product->get_id();
		$base = array(
			'id'        => $id,
			'type'      => $type,
			'name'      => $product->get_name(),
			'permalink' => get_permalink( $id ),
			'thumbnail' => wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_gallery_thumbnail' ) ?: '',
		);

		if ( 'bundles' === $type ) {
			$c = SMB_Source::bundle_config( $id );
			if ( ! $c['on'] ) {
				return null;
			}
			return $base + array( 'offer' => array(
				'title'    => $c['title'],
				'name'     => $c['name'],
				'badge'    => $c['badge'],
				'discount' => array( 'type' => $c['type'], 'value' => $c['value'] ),
				'items'    => $c['rows'],
			) );
		}

		if ( 'fbt' === $type ) {
			if ( ! SMB_Source::fbt_enabled( $id ) ) {
				return null;
			}
			$c = SMB_Source::config( $id );
			return $base + array( 'offer' => array(
				'title' => $c['fbt_title'],
				'items' => $c['items'],
				'limit' => $c['limit'],
			) );
		}

		$c = SMB_Source::config( $id );
		if ( ! $c['packs_on'] ) {
			return null;
		}
		return $base + array( 'offer' => array(
			'title'  => $c['packs_title'],
			'mode'   => $c['pack_mode'],
			'packs'  => 'ranges' === $c['pack_mode'] ? array() : SMB_Pricing::clean_tiers( $c['packs'] ),
			'ranges' => 'ranges' === $c['pack_mode'] ? $c['ranges'] : array(),
		) );
	}
}
