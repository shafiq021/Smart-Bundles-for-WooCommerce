<?php
defined( 'ABSPATH' ) || exit;

/**
 * Read-only REST API: /wp-json/smart-bundles/v1/{packs|bundles|fbt}[/<product id>]
 *
 * Read-only (GET only). The rules, in order:
 *  - the product post type must have show_in_rest on, otherwise every route answers 404 (a site that
 *    keeps products out of the REST API does not get them back through this plugin);
 *  - collections list the products that currently show that kind of offer (paged, searchable) and
 *    need edit_products; the Gutenberg blocks use them for their product picker. A headless store
 *    that wants a public list can open it with the smb_rest_can_list filter;
 *  - a single offer is public for published products, because the same data is already printed on
 *    the shop page. Drafts and private products need edit rights on that product, and a
 *    password-protected product is only shown to someone who can edit it.
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
				'schema'              => array( __CLASS__, 'schema' ),
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
				'schema'              => array( __CLASS__, 'schema' ),
				'args'                => array(
					'id' => array( 'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint' ),
				),
			) );
		}
	}

	/** Products are only offered here while WordPress itself offers them to the REST API. */
	private static function exposed() {
		$type = get_post_type_object( 'product' );
		return $type && $type->show_in_rest;
	}

	private static function hidden() {
		return new WP_Error( 'rest_no_route', __( 'No route was found matching the URL and request method.', 'smart-bundles' ), array( 'status' => 404 ) );
	}

	public static function can_list() {
		if ( ! self::exposed() ) {
			return self::hidden();
		}
		$allowed = current_user_can( 'edit_products' );
		/**
		 * Open the offer lists to everyone (headless stores) or close them further.
		 *
		 * @param bool $allowed Default: users who can edit products.
		 */
		$allowed = (bool) apply_filters( 'smb_rest_can_list', $allowed );
		if ( ! $allowed ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to list offers.', 'smart-bundles' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	public static function can_read( $request ) {
		if ( ! self::exposed() ) {
			return self::hidden();
		}
		$post = get_post( absint( $request['id'] ) );
		if ( ! $post || 'product' !== $post->post_type ) {
			return true; // the callback answers 404, so nothing about other post types is revealed
		}
		$can_edit = current_user_can( 'edit_post', $post->ID );
		if ( ( 'publish' !== $post->post_status || post_password_required( $post ) ) && ! $can_edit ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to view this offer.', 'smart-bundles' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/** JSON schema of one offer (shown by OPTIONS requests and used by REST clients). */
	public static function schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'smart-bundles-offer',
			'type'       => 'object',
			'properties' => array(
				'id'        => array( 'type' => 'integer', 'description' => 'Product id.', 'readonly' => true ),
				'type'      => array( 'type' => 'string', 'enum' => array( 'packs', 'bundles', 'fbt' ), 'readonly' => true ),
				'name'      => array( 'type' => 'string', 'readonly' => true ),
				'permalink' => array( 'type' => 'string', 'format' => 'uri', 'readonly' => true ),
				'thumbnail' => array( 'type' => 'string', 'description' => 'Thumbnail URL, empty when the product has no image.', 'readonly' => true ),
				'layout'    => array( 'type' => 'string', 'enum' => array( 'list', 'grid', 'carousel' ), 'readonly' => true ),
				'offer'     => array( 'type' => 'object', 'description' => 'The settings of the offer; the fields depend on the type.', 'readonly' => true ),
			),
		);
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
			'layout'    => SMB_Source::layout( $id ),
		);

		$row = self::build_offer( $type, $product, $base );
		return null === $row ? null : apply_filters( 'smb_rest_offer', $row, $type, $product );
	}

	private static function build_offer( $type, $product, $base ) {
		$id = $product->get_id();

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
