<?php
/**
 * REST API каталога: GET /wp-json/zhk/v1/apartments и POST /wp-json/zhk/v1/leads.
 */

defined( 'ABSPATH' ) || exit;

final class ZHK_Rest {

	const NS = 'zhk/v1';

	public static function register_routes(): void {
		$int = array( 'type' => 'integer', 'minimum' => 0 );
		$num = array( 'type' => 'number', 'minimum' => 0 );

		register_rest_route(
			self::NS,
			'/apartments',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_apartments' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'rooms'     => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => ZHK_Apartment::MAX_ROOMS ),
					),
					'price_min' => $int,
					'price_max' => $int,
					'area_min'  => $num,
					'area_max'  => $num,
					'floor_min' => $int,
					'floor_max' => $int,
					'building'  => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'finish'    => array( 'type' => 'string', 'enum' => array_merge( array( '' ), array_keys( ZHK_Apartment::FINISHES ) ) ),
					'only_free' => array( 'type' => 'boolean', 'default' => false ),
					'sort'      => array(
						'type'    => 'string',
						'default' => 'price_asc',
						'enum'    => array( 'price_asc', 'price_desc', 'area_asc', 'area_desc', 'floor_asc' ),
					),
					'page'      => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
					'per_page'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 48, 'default' => 12 ),
					'ids'       => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'integer', 'minimum' => 1 ),
						'maxItems' => 100,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/leads',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( 'ZHK_Leads', 'create_from_request' ),
				'permission_callback' => '__return_true', // Публичная форма; защита — honeypot и лимит частоты.
				'args'                => array(
					'name'         => array( 'type' => 'string', 'required' => true, 'maxLength' => 100 ),
					'phone'        => array( 'type' => 'string', 'required' => true, 'maxLength' => 30 ),
					'comment'      => array( 'type' => 'string', 'maxLength' => 1000, 'default' => '' ),
					'apartment_id' => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
					'source'       => array( 'type' => 'string', 'maxLength' => 300, 'default' => '' ),
					'consent'      => array( 'type' => 'boolean', 'required' => true ),
					'website'      => array( 'type' => 'string', 'default' => '' ), // Honeypot.
				),
			)
		);
	}

	/**
	 * Проверенные фильтры из запроса. Типы и диапазоны уже проверил REST API по схеме args.
	 */
	public static function filters_from_request( WP_REST_Request $request ): array {
		$keys = array( 'rooms', 'price_min', 'price_max', 'area_min', 'area_max', 'floor_min', 'floor_max', 'building', 'finish', 'only_free', 'sort', 'page', 'per_page', 'ids' );
		$f    = array();
		foreach ( $keys as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value && '' !== $value && array() !== $value ) {
				$f[ $key ] = $value;
			}
		}
		return $f;
	}

	public static function list_apartments( WP_REST_Request $request ): WP_REST_Response {
		$filters = self::filters_from_request( $request );
		$query   = new WP_Query( ZHK_Apartment::query_args( $filters ) );

		$items = array_map( array( 'ZHK_Apartment', 'to_array' ), $query->posts );

		// Для избранного сохраняем порядок, в котором пользователь добавлял квартиры.
		if ( ! empty( $filters['ids'] ) ) {
			$order = array_flip( array_map( 'intval', $filters['ids'] ) );
			usort( $items, static fn( $a, $b ) => $order[ $a['id'] ] <=> $order[ $b['id'] ] );
		}

		return new WP_REST_Response(
			array(
				'items'  => $items,
				'total'  => (int) $query->found_posts,
				'pages'  => (int) $query->max_num_pages,
				'facets' => ZHK_Apartment::facets(),
			)
		);
	}
}
