<?php
/**
 * Админка для менеджеров: форма квартиры, колонки, сортировка,
 * фильтр по статусу и корпусу, быстрое изменение цены и статуса прямо в списке.
 */

defined( 'ABSPATH' ) || exit;

final class ZHK_Admin {

	const NONCE = 'zhk_apartment_nonce';
	const PT    = ZHK_Apartment::POST_TYPE;

	public static function init(): void {
		add_action( 'add_meta_boxes_' . self::PT, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::PT, array( __CLASS__, 'save' ), 10, 2 );

		add_filter( 'manage_' . self::PT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::PT . '_posts_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
		add_filter( 'manage_edit-' . self::PT . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'admin_query' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'list_filters' ) );

		add_action( 'quick_edit_custom_box', array( __CLASS__, 'quick_edit_box' ), 10, 2 );
		add_action( 'admin_footer-edit.php', array( __CLASS__, 'quick_edit_script' ) );

		add_filter( 'manage_' . ZHK_Leads::POST_TYPE . '_posts_columns', array( __CLASS__, 'lead_columns' ) );
		add_action( 'manage_' . ZHK_Leads::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'lead_column_value' ), 10, 2 );

		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/* ---------- Форма квартиры ---------- */

	public static function add_meta_box(): void {
		add_meta_box( 'zhk_apartment', 'Параметры квартиры', array( __CLASS__, 'render_meta_box' ), self::PT, 'normal', 'high' );
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'zhk_save_' . $post->ID, self::NONCE );
		echo '<p class="description">Планировка — «Изображение записи» справа. Без неё на сайте показывается схема по числу комнат.</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( ZHK_Apartment::fields() as $key => $field ) {
			$id    = 'zhk_' . $key;
			$value = ZHK_Apartment::get( $post->ID, $key );
			printf( '<tr><th scope="row"><label for="%s">%s</label></th><td>', esc_attr( $id ), esc_html( $field['label'] ) );
			self::render_input( $key, $field, $value, $id );
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function render_input( string $key, array $field, $value, string $id ): void {
		$name = 'zhk[' . $key . ']';
		if ( isset( $field['options'] ) ) {
			printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
			foreach ( $field['options'] as $opt => $label ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $label ) );
			}
			echo '</select>';
			return;
		}
		$type = 'string' === $field['type'] ? 'text' : 'number';
		$step = 'number' === $field['type'] ? ' step="0.01"' : '';
		printf(
			'<input type="%s" id="%s" name="%s" value="%s" class="regular-text" min="0"%s>',
			esc_attr( $type ),
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( $value ),
			$step // phpcs:ignore WordPress.Security.EscapeOutput -- константная строка.
		);
	}

	/**
	 * Сохранение из формы квартиры и из быстрого редактирования в списке.
	 */
	public static function save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE ], $_POST['zhk'] ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) );
		if ( ! wp_verify_nonce( $nonce, 'zhk_save_' . $post_id ) && ! wp_verify_nonce( $nonce, 'zhk_quick_edit' ) ) {
			return;
		}
		$data = wp_unslash( (array) $_POST['zhk'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- каждое поле проверяет ZHK_Apartment::sanitize().
		ZHK_Apartment::save_fields( $post_id, $data );
	}

	/* ---------- Список квартир ---------- */

	public static function columns( array $columns ): array {
		return array(
			'cb'           => $columns['cb'],
			'title'        => 'Название',
			'zhk_number'   => '№',
			'zhk_building' => 'Корпус',
			'zhk_floor'    => 'Этаж',
			'zhk_rooms'    => 'Комнат',
			'zhk_area'     => 'Площадь',
			'zhk_price'    => 'Цена',
			'zhk_status'   => 'Статус',
		);
	}

	public static function column_value( string $column, int $post_id ): void {
		$key = substr( $column, 4 );
		if ( 'zhk_' !== substr( $column, 0, 4 ) ) {
			return;
		}
		$value = ZHK_Apartment::get( $post_id, $key );
		switch ( $key ) {
			case 'rooms':
				echo esc_html( 0 === (int) $value ? 'Студия' : $value );
				break;
			case 'area':
				echo esc_html( number_format( (float) $value, 1, ',', ' ' ) . ' м²' );
				break;
			case 'price':
				echo esc_html( number_format( (int) $value, 0, '', ' ' ) . ' ₽' );
				// Данные для быстрого редактирования (см. quick_edit_script).
				printf(
					'<span hidden class="zhk-inline" data-price="%s" data-status="%s"></span>',
					esc_attr( $value ),
					esc_attr( ZHK_Apartment::get( $post_id, 'status' ) )
				);
				break;
			case 'status':
				$colors = array( 'free' => '#1a7f37', 'reserved' => '#b26b00', 'sold' => '#8c8f94' );
				printf(
					'<strong style="color:%s">%s</strong>',
					esc_attr( $colors[ $value ] ?? '#000' ),
					esc_html( ZHK_Apartment::STATUSES[ $value ] ?? '—' )
				);
				break;
			default:
				echo esc_html( $value );
		}
	}

	public static function sortable_columns( array $columns ): array {
		foreach ( array( 'number', 'floor', 'rooms', 'area', 'price', 'status' ) as $key ) {
			$columns[ 'zhk_' . $key ] = 'zhk_' . $key;
		}
		return $columns;
	}

	public static function admin_query( WP_Query $query ): void {
		if ( ! $query->is_main_query() || self::PT !== $query->get( 'post_type' ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'edit-' . self::PT !== $screen->id ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( is_string( $orderby ) && str_starts_with( $orderby, 'zhk_' ) && array_key_exists( substr( $orderby, 4 ), ZHK_Apartment::fields() ) ) {
			$numeric = in_array( $orderby, array( 'zhk_floor', 'zhk_rooms', 'zhk_area', 'zhk_price', 'zhk_number' ), true );
			$query->set( 'meta_key', $orderby );
			$query->set( 'orderby', $numeric ? 'meta_value_num' : 'meta_value' );
		}

		$meta = array();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- фильтр списка, только чтение.
		$status   = isset( $_GET['zhk_status'] ) ? sanitize_key( $_GET['zhk_status'] ) : '';
		$building = isset( $_GET['zhk_building'] ) ? sanitize_text_field( wp_unslash( $_GET['zhk_building'] ) ) : '';
		// phpcs:enable
		if ( isset( ZHK_Apartment::STATUSES[ $status ] ) ) {
			$meta[] = array( 'key' => 'zhk_status', 'value' => $status );
		}
		if ( '' !== $building ) {
			$meta[] = array( 'key' => 'zhk_building', 'value' => $building );
		}
		if ( $meta ) {
			$query->set( 'meta_query', $meta );
		}
	}

	public static function list_filters( string $post_type ): void {
		if ( self::PT !== $post_type ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- фильтр списка, только чтение.
		$status   = isset( $_GET['zhk_status'] ) ? sanitize_key( $_GET['zhk_status'] ) : '';
		$building = isset( $_GET['zhk_building'] ) ? sanitize_text_field( wp_unslash( $_GET['zhk_building'] ) ) : '';
		// phpcs:enable

		echo '<select name="zhk_status"><option value="">Все статусы</option>';
		foreach ( ZHK_Apartment::STATUSES as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $status, $key, false ), esc_html( $label ) );
		}
		echo '</select><select name="zhk_building"><option value="">Все корпуса</option>';
		foreach ( ZHK_Apartment::facets()['buildings'] as $b ) {
			printf( '<option value="%s"%s>Корпус %s</option>', esc_attr( $b ), selected( $building, $b, false ), esc_html( $b ) );
		}
		echo '</select>';
	}

	/* ---------- Быстрое редактирование цены и статуса ---------- */

	public static function quick_edit_box( string $column, string $post_type ): void {
		if ( self::PT !== $post_type || 'zhk_price' !== $column ) {
			return;
		}
		wp_nonce_field( 'zhk_quick_edit', self::NONCE );
		echo '<fieldset class="inline-edit-col-right"><div class="inline-edit-col">';
		echo '<label><span class="title">Цена, ₽</span><span class="input-text-wrap"><input type="number" min="0" name="zhk[price]" class="zhk-qe-price"></span></label>';
		echo '<label><span class="title">Статус</span><select name="zhk[status]" class="zhk-qe-status">';
		foreach ( ZHK_Apartment::STATUSES as $key => $label ) {
			printf( '<option value="%s">%s</option>', esc_attr( $key ), esc_html( $label ) );
		}
		echo '</select></label></div></fieldset>';
	}

	public static function quick_edit_script(): void {
		if ( self::PT !== get_current_screen()->post_type ) {
			return;
		}
		?>
		<script>
		( function () {
			if ( ! window.inlineEditPost ) return;
			const original = inlineEditPost.edit;
			inlineEditPost.edit = function ( id ) {
				original.apply( this, arguments );
				const postId = typeof id === 'object' ? this.getId( id ) : id;
				const data = document.querySelector( '#post-' + postId + ' .zhk-inline' );
				const row = document.getElementById( 'edit-' + postId );
				if ( ! data || ! row ) return;
				row.querySelector( '.zhk-qe-price' ).value = data.dataset.price;
				row.querySelector( '.zhk-qe-status' ).value = data.dataset.status;
			};
		} )();
		</script>
		<?php
	}

	/* ---------- Заявки ---------- */

	public static function lead_columns( array $columns ): array {
		return array(
			'cb'                 => $columns['cb'],
			'title'              => 'Имя, телефон',
			'zhk_lead_apartment' => 'Квартира',
			'zhk_lead_comment'   => 'Комментарий',
			'date'               => 'Дата',
		);
	}

	public static function lead_column_value( string $column, int $post_id ): void {
		if ( 'zhk_lead_apartment' === $column ) {
			$apartment_id = (int) get_post_meta( $post_id, 'zhk_lead_apartment', true );
			if ( $apartment_id && get_post( $apartment_id ) ) {
				$a = ZHK_Apartment::to_array( get_post( $apartment_id ) );
				printf( '<a href="%s">№%s, корп. %s</a><br>%s', esc_url( get_edit_post_link( $apartment_id ) ), esc_html( $a['number'] ), esc_html( $a['building'] ), esc_html( $a['title'] ) );
			} else {
				echo '—';
			}
		}
		if ( 'zhk_lead_comment' === $column ) {
			echo esc_html( wp_trim_words( get_post_field( 'post_content', $post_id ), 20 ) );
		}
	}

	/* ---------- Настройки ---------- */

	public static function register_settings(): void {
		register_setting( 'general', ZHK_Leads::EMAIL_OPTION, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ) );
		add_settings_field(
			ZHK_Leads::EMAIL_OPTION,
			'Почта для заявок',
			static function () {
				printf(
					'<input type="email" name="%1$s" id="%1$s" value="%2$s" class="regular-text"><p class="description">Пусто — заявки уходят на адрес администратора.</p>',
					esc_attr( ZHK_Leads::EMAIL_OPTION ),
					esc_attr( get_option( ZHK_Leads::EMAIL_OPTION ) )
				);
			},
			'general'
		);
	}
}
