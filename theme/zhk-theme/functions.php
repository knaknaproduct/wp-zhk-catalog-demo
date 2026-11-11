<?php
/**
 * Тема ЖК: подключение ресурсов, меню, настройки аналитики и контактов.
 * Данные квартир, фильтры и заявки — в плагине ZHK Catalog.
 */

defined( 'ABSPATH' ) || exit;

define( 'ZHK_THEME_VERSION', '1.0.0' );

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		register_nav_menus( array( 'primary' => 'Главное меню' ) );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$uri = get_template_directory_uri();
		wp_enqueue_style( 'zhk-fonts', 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap', array(), null );
		wp_enqueue_style( 'zhk-main', $uri . '/assets/css/main.css', array(), ZHK_THEME_VERSION );

		wp_enqueue_script( 'zhk-app', $uri . '/assets/js/app.js', array(), ZHK_THEME_VERSION, array( 'strategy' => 'defer' ) );
		wp_localize_script(
			'zhk-app',
			'ZHK',
			array(
				'api'          => esc_url_raw( rest_url( 'zhk/v1/' ) ),
				'favoritesUrl' => esc_url( home_url( '/izbrannoe/' ) ),
			)
		);

		if ( is_post_type_archive( 'apartment' ) || is_singular( 'apartment' ) || is_page( 'izbrannoe' ) ) {
			wp_enqueue_script( 'zhk-catalog', $uri . '/assets/js/catalog.js', array( 'zhk-app' ), ZHK_THEME_VERSION, array( 'strategy' => 'defer' ) );
		}

		if ( is_front_page() ) {
			wp_enqueue_style( 'leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css', array(), '1.9.4' );
			wp_enqueue_script( 'leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js', array(), '1.9.4', true );
			wp_enqueue_script( 'zhk-map', $uri . '/assets/js/map.js', array( 'leaflet' ), ZHK_THEME_VERSION, true );
			wp_localize_script( 'zhk-map', 'ZHK_MAP', zhk_map_data() );
		}
	}
);

/**
 * Меню по умолчанию, пока в админке не создано своё.
 */
function zhk_fallback_menu(): void {
	$items = array(
		home_url( '/kvartiry/' )  => 'Квартиры',
		home_url( '/#about' )     => 'О проекте',
		home_url( '/#location' )  => 'Расположение',
		home_url( '/#gallery' )   => 'Галерея',
		home_url( '/novosti/' )   => 'Новости',
		home_url( '/#contacts' )  => 'Контакты',
	);
	echo '<ul class="main-nav__list">';
	foreach ( $items as $url => $label ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $label ) );
	}
	echo '</ul>';
}

/**
 * Без плагина каталога тема работает как обычная, но без квартир.
 */
function zhk_catalog_active(): bool {
	return class_exists( 'ZHK_Apartment' );
}

function zhk_price( int $rub ): string {
	return number_format( $rub, 0, '', ' ' ) . ' ₽';
}

function zhk_price_short( int $rub ): string {
	return number_format( $rub / 1000000, 1, ',', '' ) . ' млн ₽';
}

/* ---------- Контакты и аналитика: Внешний вид → Настроить → «ЖК: контакты и аналитика» ---------- */

function zhk_option( string $key ): string {
	$defaults = array(
		'phone'      => '+7 (495) 000-00-00',
		'address'    => 'Москва, ул. Примерная, 1 — офис продаж',
		'hours'      => 'Ежедневно с 9:00 до 21:00',
		'metrika_id' => '',
		'gtm_id'     => '',
	);
	return (string) get_theme_mod( 'zhk_' . $key, $defaults[ $key ] ?? '' );
}

add_action(
	'customize_register',
	static function ( WP_Customize_Manager $wp_customize ) {
		$wp_customize->add_section( 'zhk', array( 'title' => 'ЖК: контакты и аналитика', 'priority' => 30 ) );
		$fields = array(
			'phone'      => array( 'Телефон', 'sanitize_text_field' ),
			'address'    => array( 'Адрес офиса продаж', 'sanitize_text_field' ),
			'hours'      => array( 'Часы работы', 'sanitize_text_field' ),
			'metrika_id' => array( 'Яндекс Метрика — номер счётчика', 'absint' ),
			'gtm_id'     => array( 'Google Tag Manager — ID (GTM-XXXX)', 'zhk_sanitize_gtm' ),
		);
		foreach ( $fields as $key => $field ) {
			$wp_customize->add_setting( 'zhk_' . $key, array( 'sanitize_callback' => $field[1], 'default' => zhk_option( $key ) ) );
			$wp_customize->add_control( 'zhk_' . $key, array( 'label' => $field[0], 'section' => 'zhk', 'type' => 'text' ) );
		}
	}
);

function zhk_sanitize_gtm( $value ): string {
	$value = strtoupper( trim( (string) $value ) );
	return preg_match( '/^GTM-[A-Z0-9]{4,12}$/', $value ) ? $value : '';
}

add_action(
	'wp_head',
	static function () {
		$gtm = zhk_option( 'gtm_id' );
		if ( $gtm ) {
			printf(
				"<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',%s);</script>\n",
				wp_json_encode( $gtm )
			);
		}
		$metrika = (int) zhk_option( 'metrika_id' );
		if ( $metrika ) {
			printf(
				"<script>(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,'script','https://mc.yandex.ru/metrika/tag.js','ym');ym(%d,'init',{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:true});window.ZHK_METRIKA=%d;</script>\n",
				$metrika,
				$metrika
			);
		}
	},
	1
);

/* ---------- Карта инфраструктуры ---------- */

function zhk_map_data(): array {
	return array(
		'center' => array( 55.7512, 37.5340 ),
		'home'   => array(
			'coords' => array( 55.7512, 37.5340 ),
			'title'  => 'ЖК «Демо-квартал»',
		),
		'places' => array(
			array( 'school', 'Школа №1250', 55.7538, 37.5302 ),
			array( 'school', 'Гимназия «Лидер»', 55.7481, 37.5401 ),
			array( 'kinder', 'Детский сад «Солнышко»', 55.7527, 37.5391 ),
			array( 'kinder', 'Детский сад во дворе', 55.7506, 37.5322 ),
			array( 'shop', 'Супермаркет', 55.7499, 37.5361 ),
			array( 'shop', 'ТЦ «Галерея»', 55.7552, 37.5379 ),
			array( 'park', 'Парк у реки', 55.7471, 37.5288 ),
			array( 'park', 'Сквер с детской площадкой', 55.7545, 37.5266 ),
			array( 'metro', 'Метро «Примерная»', 55.7490, 37.5420 ),
			array( 'sport', 'Фитнес-клуб', 55.7530, 37.5440 ),
		),
		'types'  => array(
			'school' => 'Школы',
			'kinder' => 'Детские сады',
			'shop'   => 'Магазины',
			'park'   => 'Парки',
			'metro'  => 'Метро',
			'sport'  => 'Спорт',
		),
	);
}

/* ---------- Изображения демо-сайта (Unsplash) ---------- */

function zhk_img( string $id, int $w = 1600 ): string {
	return 'https://images.unsplash.com/photo-' . $id . '?auto=format&fit=crop&q=80&w=' . $w;
}

function zhk_gallery(): array {
	return array(
		array( '1624204386084-dd8c05e32226', 'Фасад со стороны двора' ),
		array( '1724582586529-62622e50c0b3', 'Кухня-гостиная с панорамным окном' ),
		array( '1515263487990-61b07816b324', 'Архитектура корпуса' ),
		array( '1600210491369-e753d80a41f3', 'Гостиная с чистовой отделкой' ),
		array( '1630809327488-cba7abac4ec7', 'Корпус 2' ),
		array( '1705326701287-346fc37a2c86', 'Интерьер: вариант дизайн-проекта' ),
	);
}
