<?php
/**
 * Квартира: тип записи, схема полей, выборка и диапазоны для фильтров.
 *
 * Схема полей описана один раз в fields() — из неё строятся мета-поля,
 * форма в админке, колонки, импорт CSV и ответ REST API.
 */

defined( 'ABSPATH' ) || exit;

final class ZHK_Apartment {

	const POST_TYPE   = 'apartment';
	const META_PREFIX = 'zhk_';
	const FACETS_KEY  = 'zhk_facets';
	const MAX_ROOMS   = 4; // «4+» в фильтре.

	const STATUSES = array(
		'free'     => 'Свободна',
		'reserved' => 'Бронь',
		'sold'     => 'Продана',
	);

	const FINISHES = array(
		'none'     => 'Без отделки',
		'whitebox' => 'White box',
		'full'     => 'Чистовая',
	);

	/**
	 * Поля квартиры: ключ => [label, type, options?].
	 */
	public static function fields(): array {
		return array(
			'number'   => array( 'label' => 'Номер квартиры', 'type' => 'string' ),
			'building' => array( 'label' => 'Корпус', 'type' => 'string' ),
			'section'  => array( 'label' => 'Секция', 'type' => 'integer' ),
			'floor'    => array( 'label' => 'Этаж', 'type' => 'integer' ),
			'rooms'    => array( 'label' => 'Комнат (0 — студия)', 'type' => 'integer' ),
			'area'     => array( 'label' => 'Площадь, м²', 'type' => 'number' ),
			'price'    => array( 'label' => 'Цена, ₽', 'type' => 'integer' ),
			'status'   => array( 'label' => 'Статус', 'type' => 'string', 'options' => self::STATUSES ),
			'finish'   => array( 'label' => 'Отделка', 'type' => 'string', 'options' => self::FINISHES ),
		);
	}

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => 'Квартиры',
					'singular_name' => 'Квартира',
					'add_new_item'  => 'Добавить квартиру',
					'edit_item'     => 'Редактировать квартиру',
					'all_items'     => 'Все квартиры',
					'search_items'  => 'Найти квартиру',
					'not_found'     => 'Квартиры не найдены',
				),
				'public'       => true,
				'has_archive'  => 'kvartiry',
				'rewrite'      => array( 'slug' => 'kvartira', 'with_front' => false ),
				'menu_icon'    => 'dashicons-building',
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'show_in_rest' => true,
			)
		);

		foreach ( self::fields() as $key => $field ) {
			register_post_meta(
				self::POST_TYPE,
				self::META_PREFIX . $key,
				array(
					'type'              => $field['type'],
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => static fn( $value ) => self::sanitize( $key, $value ),
					'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
				)
			);
		}

		// Любое изменение квартир сбрасывает кэш диапазонов фильтра.
		$on_meta = static function ( $meta_id, $post_id, $meta_key ) {
			if ( str_starts_with( (string) $meta_key, self::META_PREFIX ) ) {
				self::flush_facets();
			}
		};
		add_action( 'added_post_meta', $on_meta, 10, 3 );
		add_action( 'updated_post_meta', $on_meta, 10, 3 );
		add_action(
			'transition_post_status',
			static function ( $new, $old, $post ) {
				if ( self::POST_TYPE === $post->post_type && $new !== $old ) {
					self::flush_facets();
				}
			},
			10,
			3
		);
	}

	/**
	 * Приводит значение поля к его типу. Недопустимое значение — null.
	 */
	public static function sanitize( string $key, $value ) {
		$fields = self::fields();
		if ( ! isset( $fields[ $key ] ) ) {
			return null;
		}
		$field = $fields[ $key ];
		$value = is_string( $value ) ? trim( $value ) : $value;

		if ( isset( $field['options'] ) ) {
			return array_key_exists( (string) $value, $field['options'] ) ? (string) $value : null;
		}

		switch ( $field['type'] ) {
			case 'integer':
				return is_numeric( $value ) ? max( 0, (int) $value ) : null;
			case 'number':
				$value = str_replace( ',', '.', (string) $value );
				return is_numeric( $value ) ? round( max( 0, (float) $value ), 2 ) : null;
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Записывает поля квартиры. Пропускает ключи, которых нет в схеме,
	 * и значения, не прошедшие проверку. Данные должны быть без слэшей (wp_unslash).
	 */
	public static function save_fields( int $post_id, array $data ): void {
		foreach ( self::fields() as $key => $field ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$value = self::sanitize( $key, $data[ $key ] );
			if ( null !== $value ) {
				update_post_meta( $post_id, self::META_PREFIX . $key, $value );
			}
		}
	}

	public static function get( int $post_id, string $key ) {
		return get_post_meta( $post_id, self::META_PREFIX . $key, true );
	}

	public static function rooms_label( int $rooms ): string {
		return 0 === $rooms ? 'Студия' : $rooms . '-комн. квартира';
	}

	/**
	 * Данные квартиры для REST API и шаблонов.
	 */
	public static function to_array( WP_Post $post ): array {
		$id     = $post->ID;
		$rooms  = (int) self::get( $id, 'rooms' );
		$area   = (float) self::get( $id, 'area' );
		$price  = (int) self::get( $id, 'price' );
		$status = (string) self::get( $id, 'status' );
		$finish = (string) self::get( $id, 'finish' );

		return array(
			'id'           => $id,
			'url'          => get_permalink( $post ),
			'title'        => self::rooms_label( $rooms ) . ', ' . number_format( $area, 1, ',', ' ' ) . ' м²',
			'number'       => (string) self::get( $id, 'number' ),
			'building'     => (string) self::get( $id, 'building' ),
			'section'      => (int) self::get( $id, 'section' ),
			'floor'        => (int) self::get( $id, 'floor' ),
			'rooms'        => $rooms,
			'area'         => $area,
			'price'        => $price,
			'price_m2'     => $area > 0 ? (int) round( $price / $area ) : 0,
			'status'       => $status,
			'status_label' => self::STATUSES[ $status ] ?? '',
			'finish'       => $finish,
			'finish_label' => self::FINISHES[ $finish ] ?? '',
			'plan'         => self::plan_url( $id, $rooms ),
		);
	}

	/**
	 * Планировка: миниатюра записи, иначе схема по числу комнат.
	 */
	public static function plan_url( int $post_id, int $rooms ): string {
		$thumb = get_the_post_thumbnail_url( $post_id, 'large' );
		if ( $thumb ) {
			return $thumb;
		}
		return ZHK_URL . 'assets/plans/' . min( $rooms, self::MAX_ROOMS ) . '.svg';
	}

	/**
	 * Аргументы WP_Query из проверенных фильтров (см. ZHK_Rest::filters_from_request()).
	 */
	public static function query_args( array $f ): array {
		$meta = array( 'relation' => 'AND' );

		if ( ! empty( $f['rooms'] ) ) {
			$rooms = array_map( 'intval', (array) $f['rooms'] );
			$exact = array_values( array_filter( $rooms, static fn( $r ) => $r < self::MAX_ROOMS ) );
			$or    = array( 'relation' => 'OR' );
			if ( $exact ) {
				$or[] = array( 'key' => 'zhk_rooms', 'value' => $exact, 'compare' => 'IN', 'type' => 'NUMERIC' );
			}
			if ( count( $exact ) !== count( $rooms ) ) {
				$or[] = array( 'key' => 'zhk_rooms', 'value' => self::MAX_ROOMS, 'compare' => '>=', 'type' => 'NUMERIC' );
			}
			$meta[] = $or;
		}

		$ranges = array(
			'price' => 'NUMERIC',
			'area'  => 'DECIMAL(10,2)',
			'floor' => 'NUMERIC',
		);
		foreach ( $ranges as $key => $type ) {
			if ( isset( $f[ $key . '_min' ] ) ) {
				$meta[] = array( 'key' => 'zhk_' . $key, 'value' => $f[ $key . '_min' ], 'compare' => '>=', 'type' => $type );
			}
			if ( isset( $f[ $key . '_max' ] ) ) {
				$meta[] = array( 'key' => 'zhk_' . $key, 'value' => $f[ $key . '_max' ], 'compare' => '<=', 'type' => $type );
			}
		}

		foreach ( array( 'building', 'finish' ) as $key ) {
			if ( ! empty( $f[ $key ] ) ) {
				$meta[] = array( 'key' => 'zhk_' . $key, 'value' => $f[ $key ] );
			}
		}

		// Проданные скрыты из каталога, но в избранном остаются — со статусом «Продана».
		if ( empty( $f['ids'] ) ) {
			$meta[] = array(
				'key'     => 'zhk_status',
				'value'   => empty( $f['only_free'] ) ? array( 'free', 'reserved' ) : array( 'free' ),
				'compare' => 'IN',
			);
		}

		// Именованное условие нужно для сортировки по числовому полю.
		$sorts = array(
			'price_asc'  => array( 'price', 'NUMERIC', 'ASC' ),
			'price_desc' => array( 'price', 'NUMERIC', 'DESC' ),
			'area_asc'   => array( 'area', 'DECIMAL(10,2)', 'ASC' ),
			'area_desc'  => array( 'area', 'DECIMAL(10,2)', 'DESC' ),
			'floor_asc'  => array( 'floor', 'NUMERIC', 'ASC' ),
		);
		list( $sort_key, $sort_type, $sort_dir ) = $sorts[ $f['sort'] ?? 'price_asc' ] ?? $sorts['price_asc'];
		$meta['sort_clause'] = array( 'key' => 'zhk_' . $sort_key, 'compare' => 'EXISTS', 'type' => $sort_type );

		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $f['per_page'] ?? 12,
			'paged'          => $f['page'] ?? 1,
			'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery -- сотни квартир, этого достаточно.
			'orderby'        => array( 'sort_clause' => $sort_dir, 'ID' => 'ASC' ),
		);

		if ( ! empty( $f['ids'] ) ) {
			$args['post__in']       = array_map( 'intval', $f['ids'] );
			$args['posts_per_page'] = count( $args['post__in'] );
			$args['paged']          = 1;
		}
		if ( ! empty( $f['exclude'] ) ) {
			$args['post__not_in'] = array_map( 'intval', (array) $f['exclude'] );
		}

		return $args;
	}

	/**
	 * Диапазоны значений и список корпусов для подсказок в фильтре.
	 * Кэшируются до изменения квартир.
	 */
	public static function facets(): array {
		$cached = get_transient( self::FACETS_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- результат кэшируется в transient.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_key AS k, MIN(CAST(pm.meta_value AS DECIMAL(14,2))) AS lo, MAX(CAST(pm.meta_value AS DECIMAL(14,2))) AS hi
				 FROM {$wpdb->postmeta} pm
				 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_key IN ('zhk_price','zhk_area','zhk_floor')
				 GROUP BY pm.meta_key",
				self::POST_TYPE
			),
			OBJECT_K
		);

		$buildings = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
				 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_key = 'zhk_building'
				 ORDER BY pm.meta_value",
				self::POST_TYPE
			)
		);
		// phpcs:enable

		$range = static fn( string $key ) => isset( $rows[ $key ] )
			? array( (float) $rows[ $key ]->lo, (float) $rows[ $key ]->hi )
			: array( 0, 0 );

		$facets = array(
			'price'     => $range( 'zhk_price' ),
			'area'      => $range( 'zhk_area' ),
			'floor'     => $range( 'zhk_floor' ),
			'buildings' => array_values( array_filter( $buildings ) ),
			'finishes'  => self::FINISHES,
		);
		set_transient( self::FACETS_KEY, $facets, DAY_IN_SECONDS );
		return $facets;
	}

	public static function flush_facets(): void {
		delete_transient( self::FACETS_KEY );
	}
}
