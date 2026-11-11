<?php
/**
 * Демо-наполнение: два корпуса квартир, страницы и новости.
 * Запуск: wp eval 'zhk_seed_demo();' или шаг runPHP в blueprint.json.
 * Повторный запуск ничего не дублирует.
 */

defined( 'ABSPATH' ) || exit;

function zhk_seed_demo(): void {
	zhk_seed_apartments();
	zhk_seed_pages();
	update_option( 'permalink_structure', '/%postname%/' );
	flush_rewrite_rules();
}

function zhk_seed_apartments(): void {
	if ( wp_count_posts( ZHK_Apartment::POST_TYPE )->publish > 0 ) {
		return;
	}

	mt_srand( 42 ); // Одинаковые данные при каждом запуске.

	// Планировки на этаже секции: комнаты и базовая площадь.
	$layouts = array(
		array( 0, 24.6 ),
		array( 1, 38.2 ),
		array( 2, 57.9 ),
		array( 3, 79.4 ),
		array( 1, 41.5 ),
		array( 4, 104.8 ),
	);
	$price_m2 = array( 0 => 265000, 1 => 248000, 2 => 236000, 3 => 229000, 4 => 224000 );

	foreach ( array( '1', '2' ) as $b_index => $building ) {
		$number = 1;
		foreach ( array( 1, 2 ) as $section ) {
			for ( $floor = 2; $floor <= 17; $floor++ ) {
				foreach ( $layouts as $l_index => $layout ) {
					list( $rooms, $base_area ) = $layout;
					$area  = round( $base_area + mt_rand( -15, 15 ) / 10, 1 );
					$m2    = $price_m2[ $rooms ] * ( 1 + ( $floor - 2 ) * 0.006 ) * ( $b_index ? 1.04 : 1 );
					$price = (int) ( round( $area * $m2 / 10000 ) * 10000 );
					$roll  = mt_rand( 1, 100 );

					$post_id = wp_insert_post(
						array(
							'post_type'   => ZHK_Apartment::POST_TYPE,
							'post_status' => 'publish',
							'post_title'  => "Квартира №{$number}, корпус {$building}",
							'post_name'   => "k{$building}-{$number}",
						)
					);
					ZHK_Apartment::save_fields(
						$post_id,
						array(
							'number'   => (string) $number,
							'building' => $building,
							'section'  => $section,
							'floor'    => $floor,
							'rooms'    => $rooms,
							'area'     => $area,
							'price'    => $price,
							'status'   => $roll <= 68 ? 'free' : ( $roll <= 82 ? 'reserved' : 'sold' ),
							'finish'   => $b_index ? 'full' : ( $l_index % 2 ? 'whitebox' : 'none' ),
						)
					);
					++$number;
				}
			}
		}
	}
	ZHK_Apartment::flush_facets();
}

function zhk_seed_pages(): void {
	$pages = array(
		'glavnaya'  => array( 'Главная', '' ),
		'novosti'   => array( 'Новости', '' ),
		'izbrannoe' => array( 'Избранное', '' ),
		'politika'  => array( 'Политика обработки персональных данных', '<p>Демо-страница. Здесь размещается политика обработки персональных данных по 152-ФЗ.</p>' ),
	);
	$ids   = array();
	foreach ( $pages as $slug => $page ) {
		$existing = get_page_by_path( $slug );
		$ids[ $slug ] = $existing ? $existing->ID : wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => $page[0],
				'post_content' => $page[1],
			)
		);
	}
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $ids['glavnaya'] );
	update_option( 'page_for_posts', $ids['novosti'] );
	update_option( 'wp_page_for_privacy_policy', $ids['politika'] );

	if ( get_posts( array( 'post_type' => 'post', 'meta_key' => 'zhk_demo', 'posts_per_page' => 1 ) ) ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		return;
	}
	wp_delete_post( 1, true ); // «Привет, мир!»

	$news = array(
		array( '2026-10-28', 'Завершено остекление корпуса 1', 'Подрядчик закончил монтаж витражного остекления на всех 17 этажах. Сейчас идут работы по фасаду и благоустройству двора.' ),
		array( '2026-10-14', 'Открыты продажи в корпусе 2', 'В продажу поступили квартиры с чистовой отделкой во втором корпусе. Доступны студии, одно-, двух- и трёхкомнатные квартиры.' ),
		array( '2026-09-30', 'Ход строительства: сентябрь', 'Корпус 1 — монолитные работы завершены, ведутся внутренние инженерные сети. Корпус 2 — возведено 12 этажей из 17.' ),
		array( '2026-09-02', 'Семейная ипотека от 6%', 'Совместно с банками-партнёрами доступна семейная ипотека. Менеджер поможет подобрать программу и подготовить документы.' ),
	);
	foreach ( $news as $item ) {
		wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_date'    => $item[0] . ' 10:00:00',
				'post_title'   => $item[1],
				'post_content' => '<p>' . $item[2] . '</p>',
				'meta_input'   => array( 'zhk_demo' => 1 ),
			)
		);
	}
}
