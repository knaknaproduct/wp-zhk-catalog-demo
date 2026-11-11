<?php
/**
 * Заявки с сайта: сохраняются в админке (раздел «Заявки») и уходят письмом.
 *
 * Для CRM или Telegram подключитесь к действию zhk_lead_created( $lead_id, $data ).
 */

defined( 'ABSPATH' ) || exit;

final class ZHK_Leads {

	const POST_TYPE    = 'zhk_lead';
	const RATE_LIMIT   = 5;   // Заявок с одного IP...
	const RATE_WINDOW  = 600; // ...за 10 минут.
	const EMAIL_OPTION = 'zhk_lead_email';

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => 'Заявки',
					'singular_name' => 'Заявка',
					'edit_item'     => 'Заявка',
					'all_items'     => 'Все заявки',
					'not_found'     => 'Заявок пока нет',
				),
				'public'          => false,
				'show_ui'         => true,
				'menu_icon'       => 'dashicons-phone',
				'menu_position'   => 26,
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ), // Заявки создаёт только сайт.
				'map_meta_cap'    => true,
			)
		);
	}

	public static function create_from_request( WP_REST_Request $request ) {
		// Honeypot: поле скрыто от людей, его заполняют только боты. Отвечаем «успехом», чтобы не подсказывать.
		if ( '' !== trim( (string) $request['website'] ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 201 );
		}

		if ( true !== $request['consent'] ) {
			return new WP_Error( 'zhk_consent', 'Нужно согласие на обработку персональных данных.', array( 'status' => 400 ) );
		}

		$name  = sanitize_text_field( $request['name'] );
		$phone = preg_replace( '/\D+/', '', (string) $request['phone'] );
		if ( '' === $name ) {
			return new WP_Error( 'zhk_name', 'Укажите имя.', array( 'status' => 400 ) );
		}
		if ( strlen( $phone ) < 10 || strlen( $phone ) > 15 ) {
			return new WP_Error( 'zhk_phone', 'Проверьте номер телефона.', array( 'status' => 400 ) );
		}

		$rate_key = 'zhk_rate_' . md5( self::client_ip() );
		$count    = (int) get_transient( $rate_key );
		if ( $count >= self::RATE_LIMIT ) {
			return new WP_Error( 'zhk_rate', 'Слишком много заявок. Попробуйте позже или позвоните нам.', array( 'status' => 429 ) );
		}
		set_transient( $rate_key, $count + 1, self::RATE_WINDOW );

		$apartment_id = (int) $request['apartment_id'];
		$apartment    = $apartment_id ? get_post( $apartment_id ) : null;
		if ( ! $apartment || ZHK_Apartment::POST_TYPE !== $apartment->post_type ) {
			$apartment_id = 0;
		}

		$data = array(
			'name'         => $name,
			'phone'        => $phone,
			'comment'      => sanitize_textarea_field( $request['comment'] ),
			'apartment_id' => $apartment_id,
			'source'       => esc_url_raw( $request['source'] ),
		);

		$lead_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $name . ', ' . self::format_phone( $phone ),
				'post_content' => $data['comment'],
				'meta_input'   => array(
					'zhk_lead_phone'     => $phone,
					'zhk_lead_apartment' => $apartment_id,
					'zhk_lead_source'    => $data['source'],
				),
			),
			true
		);
		if ( is_wp_error( $lead_id ) ) {
			return new WP_Error( 'zhk_save', 'Не удалось отправить заявку. Позвоните нам, пожалуйста.', array( 'status' => 500 ) );
		}

		self::notify( $lead_id, $data );
		do_action( 'zhk_lead_created', $lead_id, $data );

		return new WP_REST_Response( array( 'ok' => true ), 201 );
	}

	private static function notify( int $lead_id, array $data ): void {
		$to    = get_option( self::EMAIL_OPTION ) ?: get_option( 'admin_email' );
		$lines = array(
			'Имя: ' . $data['name'],
			'Телефон: ' . self::format_phone( $data['phone'] ),
		);
		if ( $data['apartment_id'] ) {
			$a       = ZHK_Apartment::to_array( get_post( $data['apartment_id'] ) );
			$lines[] = sprintf( 'Квартира: №%s, корпус %s — %s, %s ₽', $a['number'], $a['building'], $a['title'], number_format( $a['price'], 0, '', ' ' ) );
		}
		if ( $data['comment'] ) {
			$lines[] = 'Комментарий: ' . $data['comment'];
		}
		$lines[] = 'Страница: ' . $data['source'];
		$lines[] = 'В админке: ' . admin_url( 'post.php?post=' . $lead_id . '&action=edit' );

		wp_mail( $to, 'Новая заявка с сайта — ' . $data['name'], implode( "\n", $lines ) );
	}

	public static function format_phone( string $digits ): string {
		if ( 11 === strlen( $digits ) && in_array( $digits[0], array( '7', '8' ), true ) ) {
			return sprintf( '+7 %s %s-%s-%s', substr( $digits, 1, 3 ), substr( $digits, 4, 3 ), substr( $digits, 7, 2 ), substr( $digits, 9, 2 ) );
		}
		return '+' . $digits;
	}

	private static function client_ip(): string {
		// REMOTE_ADDR нельзя подделать заголовком. За прокси/CDN замените на доверенный заголовок.
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}
}
